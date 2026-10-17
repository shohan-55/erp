<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 1 tenant-boundary regressions.
 *
 * The fixtures deliberately combine two separate companies with two distinct
 * Partner workspaces inside the first company. Every test uses real HTTP
 * middleware and checks that changing an ID or firmSlug cannot escape scope.
 */
class Phase01TenantBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => $slug,
            'name' => 'Private company '.$slug,
            'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00',
            'office_end' => '18:00:00',
            'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company,
            'slug' => 'unit-01',
            'name' => 'Partner Unit One',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$company, $workspace];
    }

    private function workspace(int $company, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $company,
            'slug' => $slug,
            'name' => 'Partner Unit '.$slug,
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function member(int $company, int $workspace, string $role, ?int $manager = null): array
    {
        $user = User::factory()->create();
        $membership = DB::table('memberships')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'user_id' => $user->id,
            'role' => $role,
            'manager_membership_id' => $manager,
            'monthly_allowance' => $role === 'student' ? '7300.00' : '0.00',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$user, $membership];
    }

    private function leave(int $company, int $workspace, int $student, string $secret): int
    {
        return DB::table('leave_requests')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'membership_id' => $student,
            'leave_type' => 'annual',
            'date_from' => '2026-11-18',
            'date_to' => '2026-11-19',
            'reason' => $secret,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function claim(int $company, int $workspace, int $student, string $secret): int
    {
        return DB::table('conveyance_claims')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'membership_id' => $student,
            'journey_date' => '2026-11-17',
            'journey_route' => $secret,
            'purpose' => 'Confidential test client assignment',
            'amount' => '150.00',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_guest_redirect_preserves_tenant_and_does_not_assume_rac(): void
    {
        $this->get('/c/qa-pilot/app')->assertRedirect('/c/qa-pilot/app/login');
        $this->get('/c/qa-pilot/app/settings/security')->assertRedirect('/c/qa-pilot/app/login');
        $this->get('/platform/app')->assertRedirect('/platform/app/login');
        $this->get('/security/mfa/setup')->assertRedirect('/');
    }

    public function test_company_and_partner_boundaries_hide_private_leaves_and_claims(): void
    {
        [$alpha, $first] = $this->company('tenant-alpha');
        $second = $this->workspace($alpha, 'unit-02');
        [$beta, $third] = $this->company('tenant-beta');

        [$owner] = $this->member($alpha, $first, 'partner');
        [, $firstStudent] = $this->member($alpha, $first, 'student');
        [, $secondStudent] = $this->member($alpha, $second, 'student');
        [, $betaStudent] = $this->member($beta, $third, 'student');

        $this->leave($alpha, $first, $firstStudent, 'ALPHA_OWN_LEAVE');
        $foreignLeave = $this->leave($alpha, $second, $secondStudent, 'OTHER_PARTNER_LEAVE_SECRET');
        $outsideLeave = $this->leave($beta, $third, $betaStudent, 'OTHER_COMPANY_LEAVE_SECRET');
        $this->claim($alpha, $first, $firstStudent, 'ALPHA_OWN_CLAIM');
        $foreignClaim = $this->claim($alpha, $second, $secondStudent, 'OTHER_PARTNER_CLAIM_SECRET');
        $outsideClaim = $this->claim($beta, $third, $betaStudent, 'OTHER_COMPANY_CLAIM_SECRET');

        $this->actingAs($owner)
            ->get('/c/tenant-alpha/app/leave')
            ->assertOk()
            ->assertSee('ALPHA_OWN_LEAVE')
            ->assertDontSee('OTHER_PARTNER_LEAVE_SECRET')
            ->assertDontSee('OTHER_COMPANY_LEAVE_SECRET');
        $this->get('/c/tenant-alpha/app/claims')
            ->assertOk()
            ->assertSee('ALPHA_OWN_CLAIM')
            ->assertDontSee('OTHER_PARTNER_CLAIM_SECRET')
            ->assertDontSee('OTHER_COMPANY_CLAIM_SECRET');

        foreach ([$foreignLeave, $outsideLeave] as $id) {
            $this->post("/c/tenant-alpha/app/leave/{$id}/decision", [
                'decision' => 'approve',
            ])->assertNotFound();
        }
        foreach ([$foreignClaim, $outsideClaim] as $id) {
            $this->post("/c/tenant-alpha/app/claims/{$id}/decision", [
                'decision' => 'approve',
            ])->assertNotFound();
            $this->get("/c/tenant-alpha/app/claims/{$id}/receipt")->assertNotFound();
        }

        $this->assertDatabaseHas('leave_requests', ['id' => $foreignLeave, 'status' => 'pending']);
        $this->assertDatabaseHas('leave_requests', ['id' => $outsideLeave, 'status' => 'pending']);
        $this->assertDatabaseHas('conveyance_claims', ['id' => $foreignClaim, 'status' => 'pending']);
        $this->assertDatabaseHas('conveyance_claims', ['id' => $outsideClaim, 'status' => 'pending']);
        $this->get('/c/tenant-beta/app')->assertForbidden();
        $this->get('/c/tenant-beta/app/settings/security')->assertForbidden();
    }

    public function test_manager_cannot_read_or_approve_an_unassigned_student_in_same_workspace(): void
    {
        [$company, $workspace] = $this->company('tenant-alpha');
        [$manager, $managerId] = $this->member($company, $workspace, 'manager');
        [, $assigned] = $this->member($company, $workspace, 'student', $managerId);
        [, $other] = $this->member($company, $workspace, 'student');

        $this->leave($company, $workspace, $assigned, 'MY_ASSIGNED_STUDENT_LEAVE');
        $otherLeave = $this->leave($company, $workspace, $other, 'NOT_MY_TEAM_LEAVE_SECRET');
        $otherClaim = $this->claim($company, $workspace, $other, 'NOT_MY_TEAM_CLAIM_SECRET');

        $this->actingAs($manager)
            ->get('/c/tenant-alpha/app/leave')
            ->assertOk()
            ->assertSee('MY_ASSIGNED_STUDENT_LEAVE')
            ->assertDontSee('NOT_MY_TEAM_LEAVE_SECRET');
        $this->get('/c/tenant-alpha/app/claims')
            ->assertOk()
            ->assertDontSee('NOT_MY_TEAM_CLAIM_SECRET');
        $this->post("/c/tenant-alpha/app/leave/{$otherLeave}/decision", [
            'decision' => 'approve',
        ])->assertNotFound();
        $this->post("/c/tenant-alpha/app/claims/{$otherClaim}/decision", [
            'decision' => 'approve',
        ])->assertNotFound();

        $this->assertDatabaseHas('leave_requests', ['id' => $otherLeave, 'status' => 'pending']);
        $this->assertDatabaseHas('conveyance_claims', ['id' => $otherClaim, 'status' => 'pending']);
    }

    public function test_cross_partner_attendance_correction_and_member_mutation_are_denied(): void
    {
        [$company, $first] = $this->company('tenant-alpha');
        $second = $this->workspace($company, 'unit-02');
        [$partner] = $this->member($company, $first, 'partner');
        [, $foreign] = $this->member($company, $second, 'student');

        $session = DB::table('attendance_sessions')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $second,
            'membership_id' => $foreign,
            'work_date' => '2026-11-17',
            'work_mode' => 'office',
            'started_at' => '2026-11-17 04:00:00',
            'ended_at' => null,
            'late' => false,
            'holiday' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($partner)
            ->post("/c/tenant-alpha/app/attendance/{$session}/correct", [
                'ended_at' => '2026-11-17T18:00',
                'reason' => 'A manager recorded a different checkout time.',
            ])->assertNotFound();

        $this->post("/c/tenant-alpha/app/team/{$foreign}/settings", [
            'monthly_allowance' => '20000.00',
        ])->assertNotFound();

        $this->assertDatabaseHas('memberships', [
            'id' => $foreign, 'monthly_allowance' => 7300,
        ]);
        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $session, 'corrected_by' => null,
        ]);
    }

    public function test_platform_operator_does_not_inherit_private_tenant_access(): void
    {
        [$company, $workspace] = $this->company('tenant-alpha');
        [, $student] = $this->member($company, $workspace, 'student');
        $this->claim($company, $workspace, $student, 'PRIVATE_PAYROLL_SUPPORTING_EVIDENCE');

        $operator = User::factory()->create();
        $operator->forceFill(['is_platform_admin' => true])->save();

        $this->actingAs($operator)
            ->get('/platform/app')
            ->assertOk()
            ->assertDontSee('PRIVATE_PAYROLL_SUPPORTING_EVIDENCE');
        $this->get('/c/tenant-alpha/app/claims')->assertForbidden();
        $this->get('/c/tenant-alpha/app/payroll')->assertForbidden();
    }
}
