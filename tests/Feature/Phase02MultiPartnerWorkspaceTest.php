<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Phase02MultiPartnerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug = 'onefirm'): int
    {
        return DB::table('companies')->insertGetId([
            'name' => 'Independent Practice '.$slug, 'slug' => $slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $firmId, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $firmId, 'slug' => $slug,
            'name' => 'Workspace '.$slug, 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(User $user, int $firm, int $workspace, string $role = 'student'): int
    {
        return DB::table('memberships')->insertGetId([
            'company_id' => $firm, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role, 'active' => true,
            'monthly_allowance' => '7500.00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function attendance(int $firm, int $workspace, int $member, string $client): void
    {
        DB::table('attendance_sessions')->insert([
            'company_id' => $firm, 'workspace_id' => $workspace, 'membership_id' => $member,
            'work_date' => '2026-10-09', 'work_mode' => 'client', 'client_site' => $client,
            'started_at' => '2026-10-09 10:05:00', 'ended_at' => '2026-10-09 18:00:00',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function payroll(int $firm, int $workspace, int $member, int $amount): void
    {
        $run = DB::table('payroll_runs')->insertGetId([
            'company_id' => $firm, 'workspace_id' => $workspace,
            'period_month' => '2026-09-01', 'status' => 'locked',
            'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id' => $run, 'membership_id' => $member,
            'base_allowance' => $amount, 'approved_claims' => 0,
            'adjustment' => 0, 'total_amount' => $amount,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_34_independent_partner_memberships_share_single_firm_login_without_cross_workspace_access(): void
    {
        $firm = $this->firm();
        $firstUser = null;
        $lastUser = null;
        for ($i = 1; $i <= 34; $i++) {
            $user = User::factory()->create();
            $workspace = $this->workspace($firm, 'partner-'.$i);
            $this->member($user, $firm, $workspace, 'partner');
            if ($i === 1) $firstUser = $user;
            if ($i === 34) $lastUser = $user;
        }
        $this->assertSame(34, DB::table('workspaces')->where('company_id', $firm)->count());
        $this->actingAs($firstUser)->get('/c/onefirm/app')->assertOk()
            ->assertSee('Workspace partner-1')->assertDontSee('Workspace partner-34');
        $this->get('/c/onefirm/app/workspaces')->assertOk()
            ->assertSee('Workspace partner-1')->assertDontSee('Workspace partner-34');
        $this->actingAs($lastUser)->get('/c/onefirm/app')->assertOk()
            ->assertSee('Workspace partner-34')->assertDontSee('Workspace partner-1</');
    }

    public function test_multiple_memberships_never_resolve_implicitly_and_only_authorized_workspace_can_be_selected(): void
    {
        $firm = $this->firm();
        $a = $this->workspace($firm, 'partner-a');
        $b = $this->workspace($firm, 'partner-b');
        $c = $this->workspace($firm, 'partner-c');
        $user = User::factory()->create();
        $aMember = $this->member($user, $firm, $a);
        $bMember = $this->member($user, $firm, $b);
        $other = $this->member(User::factory()->create(), $firm, $c);

        $this->actingAs($user)->get('/c/onefirm/app')
            ->assertRedirect('/c/onefirm/app/workspaces');
        $this->get('/c/onefirm/app/workspaces')->assertOk()
            ->assertSee('Workspace partner-a')->assertSee('Workspace partner-b')
            ->assertDontSee('Workspace partner-c');
        $this->post('/c/onefirm/app/workspaces/select', ['membership_id' => $other])
            ->assertSessionHasErrors('membership_id');

        $this->post('/c/onefirm/app/workspaces/select', ['membership_id' => $aMember])
            ->assertRedirect('/c/onefirm/app');
        $this->get('/c/onefirm/app')->assertOk()->assertSee('Workspace partner-a');
        $this->post('/c/onefirm/app/workspaces/select', ['membership_id' => $bMember])
            ->assertRedirect('/c/onefirm/app');
        $this->get('/c/onefirm/app')->assertOk()->assertSee('Workspace partner-b');
    }

    public function test_switching_never_merges_private_attendance_and_locked_payroll_of_partner_units(): void
    {
        $firm = $this->firm();
        $a = $this->workspace($firm, 'partner-a');
        $b = $this->workspace($firm, 'partner-b');
        $user = User::factory()->create();
        $ma = $this->member($user, $firm, $a);
        $mb = $this->member($user, $firm, $b);
        $this->attendance($firm, $a, $ma, 'CLIENT_ALPHA_UNIQUE');
        $this->attendance($firm, $b, $mb, 'CLIENT_BETA_UNIQUE');
        $this->payroll($firm, $a, $ma, 17231);
        $this->payroll($firm, $b, $mb, 28347);

        $this->actingAs($user)->post('/c/onefirm/app/workspaces/select', ['membership_id' => $ma])
            ->assertRedirect();
        $this->get('/c/onefirm/app/attendance')->assertOk()
            ->assertSee('CLIENT_ALPHA_UNIQUE')->assertDontSee('CLIENT_BETA_UNIQUE');
        $this->get('/c/onefirm/app/payroll')->assertOk()
            ->assertSee('17,231.00')->assertDontSee('28,347.00');

        $this->post('/c/onefirm/app/workspaces/select', ['membership_id' => $mb])
            ->assertRedirect();
        $this->get('/c/onefirm/app/attendance')->assertOk()
            ->assertSee('CLIENT_BETA_UNIQUE')->assertDontSee('CLIENT_ALPHA_UNIQUE');
        $this->get('/c/onefirm/app/payroll')->assertOk()
            ->assertSee('28,347.00')->assertDontSee('17,231.00');
    }

    public function test_selected_partner_is_invalidated_if_suspended_without_falling_back_to_another(): void
    {
        $firm = $this->firm();
        $wa = $this->workspace($firm, 'partner-a');
        $wb = $this->workspace($firm, 'partner-b');
        $user = User::factory()->create();
        $ma = $this->member($user, $firm, $wa);
        $this->member($user, $firm, $wb);

        $this->actingAs($user)->post('/c/onefirm/app/workspaces/select', ['membership_id' => $ma])
            ->assertRedirect();
        DB::table('memberships')->where('id', $ma)->update([
            'active' => false, 'platform_suspended_at' => now(),
        ]);
        $this->get('/c/onefirm/app')->assertRedirect('/c/onefirm/app/workspaces');
        $this->get('/c/onefirm/app/workspaces')->assertOk()
            ->assertDontSee('Workspace partner-a')->assertSee('Workspace partner-b');

        DB::table('workspaces')->where('id', $wb)->update(['active' => false]);
        $this->get('/c/onefirm/app/workspaces')->assertForbidden();
        $this->get('/c/onefirm/app')->assertForbidden();
    }

    public function test_workspace_selection_from_another_firm_is_never_accepted(): void
    {
        $one = $this->firm('onefirm');
        $two = $this->firm('otherfirm');
        $a = $this->workspace($one, 'partner-a');
        $b = $this->workspace($two, 'partner-b');
        $user = User::factory()->create();
        $first = $this->member($user, $one, $a);
        $foreign = $this->member($user, $two, $b);

        $this->actingAs($user)->post('/c/onefirm/app/workspaces/select', [
            'membership_id' => $foreign,
        ])->assertSessionHasErrors('membership_id');

        $this->post('/c/onefirm/app/workspaces/select', ['membership_id' => $first])
            ->assertRedirect('/c/onefirm/app');
        $this->get('/c/otherfirm/app')->assertOk()->assertSee('Workspace partner-b');
    }

    public function test_single_workspace_legacy_login_stays_direct_to_dashboard(): void
    {
        $firm = $this->firm();
        $w = $this->workspace($firm, 'partner-a');
        $user = User::factory()->create(['password' => Hash::make('AValidSecurePassword42')]);
        $this->member($user, $firm, $w);

        $this->post('/c/onefirm/app/login', [
            'email' => $user->email, 'password' => 'AValidSecurePassword42',
        ])->assertRedirect('/c/onefirm/app');
        $this->get('/c/onefirm/app')->assertOk();
    }

    public function test_multi_workspace_credentials_redirect_to_explicit_workspace_chooser(): void
    {
        $firm = $this->firm();
        $a = $this->workspace($firm, 'partner-a');
        $b = $this->workspace($firm, 'partner-b');
        $user = User::factory()->create(['password' => Hash::make('AValidSecurePassword42')]);
        $this->member($user, $firm, $a);
        $this->member($user, $firm, $b);

        $this->post('/c/onefirm/app/login', [
            'email' => $user->email, 'password' => 'AValidSecurePassword42',
        ])->assertRedirect('/c/onefirm/app/workspaces');
        $this->get('/c/onefirm/app')->assertRedirect('/c/onefirm/app/workspaces');
    }
}
