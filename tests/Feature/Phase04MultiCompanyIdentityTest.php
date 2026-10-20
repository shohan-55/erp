<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class Phase04MultiCompanyIdentityTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'OriginalPassword2026Strong';

    private function company(string $slug, bool $active = true): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'name' => ucwords(str_replace('-', ' ', $slug)), 'slug' => $slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => $active,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspaceId = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'partner-01',
            'name' => $slug.' workspace', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$companyId, $workspaceId];
    }

    private function member(int $company, int $workspace, User $user, string $role = 'student', bool $active = true): int
    {
        return DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role, 'active' => $active,
            'monthly_allowance' => '0.00',
            'invited_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function account(): User
    {
        return User::factory()->create(['password' => Hash::make(self::PASSWORD)]);
    }

    private function owner(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['is_platform_admin' => true])->save();

        return $user;
    }

    private function link(int $companyId, int $workspaceId, int $memberId, User $user, string $token): void
    {
        DB::table('erp_identity_link_invitations')->insert([
            'company_id' => $companyId, 'workspace_id' => $workspaceId,
            'membership_id' => $memberId, 'user_id' => $user->id,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(2),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_one_verified_account_switches_two_firms_without_combining_workspaces_or_salaries(): void
    {
        $user = $this->account();
        [$companyA, $workspaceA] = $this->company('firm-alpha');
        [$companyB, $workspaceB] = $this->company('firm-bravo');
        $memberA = $this->member($companyA, $workspaceA, $user, 'student');
        $memberB = $this->member($companyB, $workspaceB, $user, 'manager');

        $this->actingAs($user)->get('/account/companies')->assertOk()
            ->assertSee('Firm Alpha')->assertSee('Firm Bravo');
        $this->post('/account/companies/select', ['company_id' => $companyB])
            ->assertRedirect('/c/firm-bravo/app');
        $this->assertSame($memberB, (int) session('erp.active_membership.membership_id'));
        $this->assertSame($companyB, (int) session('erp.active_membership.company_id'));
        $this->get('/c/firm-bravo/app')->assertOk();

        $this->post('/account/companies/select', ['company_id' => $companyA])
            ->assertRedirect('/c/firm-alpha/app');
        $this->assertSame($memberA, (int) session('erp.active_membership.membership_id'));
        $this->get('/c/firm-alpha/app')->assertOk();
        $this->assertDatabaseHas('memberships', [
            'id' => $memberB, 'company_id' => $companyB, 'role' => 'manager',
        ]);
    }

    public function test_company_selector_rejects_ids_not_owned_and_hides_suspended_removed_firms(): void
    {
        $user = $this->account();
        [$alpha, $wa] = $this->company('firm-alpha');
        [$other, $wb] = $this->company('secret-firm');
        [$inactive, $wc] = $this->company('suspended-firm', false);
        $this->member($alpha, $wa, $user);
        $this->member($inactive, $wc, $user);

        $this->actingAs($user)->get('/account/companies')->assertOk()
            ->assertSee('Firm Alpha')
            ->assertDontSee('Secret Firm')->assertDontSee('Suspended Firm');

        $this->post('/account/companies/select', ['company_id' => $other])
            ->assertSessionHasErrors('company_id');
        $this->post('/account/companies/select', ['company_id' => $inactive])
            ->assertSessionHasErrors('company_id');
        $this->assertNull(session('erp.active_membership'));
    }

    public function test_existing_person_can_be_invited_from_platform_without_password_reset(): void
    {
        Mail::fake();
        $person = $this->account();
        $originalHash = $person->password;
        [$firmA, $wa] = $this->company('first-employer');
        $this->member($firmA, $wa, $person);
        [$firmB, $wb] = $this->company('second-employer');

        $this->actingAs($this->owner())->post("/platform/app/companies/{$firmB}/members", [
            'workspace_id' => $wb, 'name' => $person->name,
            'email' => $person->email, 'role' => 'manager',
        ])->assertRedirect();

        $pending = DB::table('memberships')->where('company_id', $firmB)
            ->where('user_id', $person->id)->firstOrFail();
        $this->assertFalse((bool) $pending->active);
        $this->assertDatabaseHas('erp_identity_link_invitations', [
            'company_id' => $firmB, 'membership_id' => $pending->id,
            'user_id' => $person->id,
        ]);
        $this->assertSame($originalHash, $person->fresh()->password);
        $this->assertDatabaseHas('memberships', [
            'company_id' => $firmA, 'user_id' => $person->id, 'active' => true,
        ]);
    }

    public function test_email_token_password_and_authenticated_identity_all_required_and_one_time(): void
    {
        $user = $this->account();
        $originalHash = $user->password;
        [$alpha, $wa] = $this->company('firm-alpha');
        $this->member($alpha, $wa, $user);
        [$bravo, $wb] = $this->company('firm-bravo');
        $pending = $this->member($bravo, $wb, $user, 'manager', false);
        $token = str_repeat('a', 64);
        $this->link($bravo, $wb, $pending, $user, $token);

        $this->actingAs($user)->get("/account/invitations/{$token}")
            ->assertOk()->assertSee('Firm Bravo')->assertSee('Confirm Existing Account Password');

        $this->post("/account/invitations/{$token}", [
            'current_password' => 'IncorrectPassword2026',
        ])->assertSessionHasErrors('current_password');
        $this->assertDatabaseHas('memberships', ['id' => $pending, 'active' => false]);

        $this->post("/account/invitations/{$token}", [
            'current_password' => self::PASSWORD,
        ])->assertRedirect('/account/companies');
        $this->assertDatabaseHas('memberships', ['id' => $pending, 'active' => true]);
        $this->assertSame($originalHash, $user->fresh()->password);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $bravo, 'entity_id' => $pending,
            'action' => 'team.identity_link.accepted',
        ]);

        $this->get("/account/invitations/{$token}")->assertNotFound();
        $this->post("/account/invitations/{$token}", [
            'current_password' => self::PASSWORD,
        ])->assertNotFound();
        $this->assertDatabaseHas('memberships', [
            'company_id' => $alpha, 'user_id' => $user->id, 'active' => true,
        ]);
    }

    public function test_token_cannot_be_used_by_another_identity_or_after_expiry_or_suspension(): void
    {
        $target = $this->account();
        $attacker = $this->account();
        [$firm, $ws] = $this->company('target-firm');
        $pending = $this->member($firm, $ws, $target, 'student', false);
        $token = str_repeat('b', 64);
        $this->link($firm, $ws, $pending, $target, $token);

        $this->actingAs($attacker)->get("/account/invitations/{$token}")->assertNotFound();
        $this->post("/account/invitations/{$token}", [
            'current_password' => self::PASSWORD,
        ])->assertNotFound();
        $this->actingAs($target)->get("/account/invitations/{$token}")->assertOk();

        DB::table('erp_identity_link_invitations')->where('membership_id', $pending)
            ->update(['expires_at' => now()->subMinute()]);
        $this->get("/account/invitations/{$token}")->assertNotFound();

        DB::table('erp_identity_link_invitations')->where('membership_id', $pending)
            ->update(['expires_at' => now()->addDay()]);
        DB::table('memberships')->where('id', $pending)
            ->update(['platform_suspended_at' => now()]);
        $this->get("/account/invitations/{$token}")->assertNotFound();
        $this->assertDatabaseHas('memberships', ['id' => $pending, 'active' => false]);
    }

    public function test_global_account_login_is_session_safe_and_account_selector_requires_login(): void
    {
        $user = $this->account();
        $this->get('/account/companies')->assertRedirect();
        $this->post('/account/login', [
            'email' => $user->email, 'password' => 'WrongPasswordForAccount',
        ])->assertSessionHasErrors('email');
        $this->post('/account/login', [
            'email' => $user->email, 'password' => self::PASSWORD,
        ])->assertRedirect('/account/companies');
        $this->assertAuthenticatedAs($user);
        $this->post('/account/logout')->assertRedirect('/account/login');
        $this->assertGuest();
    }
}
