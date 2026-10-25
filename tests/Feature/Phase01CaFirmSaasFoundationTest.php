<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase01CaFirmSaasFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_platform_admin' => true]);
    }

    private function company(string $slug, int $minimum = 1): array
    {
        $id = DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Independent CA Firm '.$slug,
            'organization_type' => 'ca_firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'min_active_partners_for_company_policy' => $minimum,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$id, DB::table('companies')->find($id)];
    }

    private function partner(int $companyId, int $index): array
    {
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'partner-'.str_pad((string)$index, 2, '0', STR_PAD_LEFT),
            'name' => 'Partner Unit '.$index, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $user = User::factory()->create();
        $membershipId = DB::table('memberships')->insertGetId([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'user_id' => $user->id, 'role' => 'partner', 'active' => true,
            'monthly_allowance' => '0.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $workspaceId, $membershipId];
    }

    public function test_platform_admin_onboards_branded_ca_firm_and_first_partner_with_single_login(): void
    {
        Mail::fake();
        $payload = [
            'company_name' => 'Example Chartered Accountants',
            'company_slug' => 'example-ca',
            'partner_name' => 'Example Managing Partner',
            'partner_email' => 'partner@example.test',
            'timezone' => 'Asia/Dhaka',
            'brand_name' => 'Example CA',
            'brand_accent_color' => '#AABBCC',
            'min_active_partners_for_company_policy' => 2,
        ];

        $this->post('/platform/app/companies', $payload)->assertRedirect('/platform/app/login');
        $this->assertDatabaseCount('companies', 0);

        $this->actingAs($this->admin())->post('/platform/app/companies', $payload)->assertRedirect();

        $firm = DB::table('companies')->where('slug', 'example-ca')->first();
        $this->assertNotNull($firm);
        $this->assertSame('ca_firm', $firm->organization_type);
        $this->assertSame('Example CA', $firm->brand_name);
        $this->assertSame('#AABBCC', $firm->brand_accent_color);
        $this->assertSame(2, (int) $firm->min_active_partners_for_company_policy);

        $first = DB::table('workspaces')->where('company_id', $firm->id)->first();
        $this->assertSame('partner-01', $first->slug);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $firm->id, 'workspace_id' => $first->id,
            'role' => 'partner', 'active' => 0,
        ]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $firm->id, 'action' => 'company.onboarded',
        ]);
        $this->assertDatabaseHas('users', ['email' => 'partner@example.test']);
        $this->get('/example-ca')->assertRedirect('/c/example-ca/app/login');
    }

    public function test_admin_can_update_independent_firm_profile_without_changing_immutable_login_slug(): void
    {
        [$id] = $this->company('other-ca');
        $this->actingAs($this->admin())->patch("/platform/app/companies/$id", [
            'company_name' => 'Renamed CA Firm',
            'brand_name' => 'Independent Practice',
            'brand_accent_color' => '#102030',
            'timezone' => 'UTC',
            'min_active_partners_for_company_policy' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('companies', [
            'id' => $id, 'name' => 'Renamed CA Firm',
            'slug' => 'other-ca', 'brand_name' => 'Independent Practice',
            'brand_accent_color' => '#102030', 'timezone' => 'UTC',
            'min_active_partners_for_company_policy' => 4,
        ]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $id, 'action' => 'company.profile.updated',
        ]);

        $this->patch("/platform/app/companies/$id", [
            'company_name' => 'Invalid Brand', 'brand_accent_color' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('brand_accent_color');
        $this->assertDatabaseHas('companies', ['id' => $id, 'name' => 'Renamed CA Firm']);
    }

    public function test_34_partners_share_one_firm_login_but_have_independent_workspaces(): void
    {
        [$companyId] = $this->company('bigfirm');
        $users = [];
        for ($i = 1; $i <= 34; $i++) {
            $users[] = $this->partner($companyId, $i);
        }
        $this->assertSame(34, DB::table('workspaces')->where('company_id', $companyId)->count());
        $this->assertSame(34, DB::table('memberships')->where('company_id', $companyId)
            ->where('role', 'partner')->count());

        $first = $users[0];
        $last = $users[33];
        $this->actingAs($first[0])->get('/c/bigfirm/app')->assertOk();
        $this->get('/c/bigfirm/app')->assertDontSee('Partner Unit 34');
        $this->actingAs($last[0])->get('/c/bigfirm/app')->assertOk();
        $this->get('/c/bigfirm/app')->assertDontSee('Partner Unit 1</');
        $this->assertNotEquals($first[1], $last[1]);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyId, 'workspace_id' => $first[1], 'user_id' => $first[0]->id,
        ]);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $companyId, 'workspace_id' => $last[1], 'user_id' => $last[0]->id,
        ]);
    }

    public function test_no_firm_name_is_special_cased_in_company_wide_policy_minimum(): void
    {
        Carbon::setTestNow('2026-10-17 12:00:00');
        try {
            [$company] = $this->company('rac', 3);
            [$partner] = $this->partner($company, 1);
            $this->actingAs($partner)->post('/c/rac/app/policies/company/propose', [
                'effective_date' => '2026-11-01',
                'office_start' => '09:00', 'office_end' => '17:00',
                'grace_minutes' => 10, 'weekly_holidays' => [5,6],
                'justification' => 'Changing firm-wide scheduling for approved use.',
            ])->assertSessionHasErrors('effective_date');
            $this->assertDatabaseCount('company_policy_proposals', 0);

            DB::table('companies')->where('id', $company)->update([
                'min_active_partners_for_company_policy' => 1,
            ]);
            $this->post('/c/rac/app/policies/company/propose', [
                'effective_date' => '2026-11-01',
                'office_start' => '09:00', 'office_end' => '17:00',
                'grace_minutes' => 10, 'weekly_holidays' => [5,6],
                'justification' => 'Changing firm-wide scheduling for approved use.',
            ])->assertRedirect();
            $this->assertDatabaseHas('company_policy_proposals', [
                'company_id' => $company, 'status' => 'pending',
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_reserved_slug_and_existing_login_cannot_be_reused_for_new_firms(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->actingAs($admin)->post('/platform/app/companies', [
            'company_name' => 'Bad Slug LLP', 'company_slug' => 'platform',
            'partner_name' => 'Prospective Partner',
            'partner_email' => 'new@example.test',
        ])->assertSessionHasErrors('company_slug');

        $this->post('/platform/app/companies', [
            'company_name' => 'Collision Firm', 'company_slug' => 'newfirm',
            'partner_name' => 'Prospective Partner',
            'partner_email' => $admin->email,
        ])->assertSessionHasErrors('partner_email');
        $this->assertDatabaseCount('companies', 0);
        Mail::assertNothingSent();
    }
}
