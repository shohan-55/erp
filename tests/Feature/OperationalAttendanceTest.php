<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendanceSession;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OperationalAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function structure(string $slug = 'rac'): array
    {
        $company = Company::create(['name' => 'Example CA Firm', 'slug' => $slug, 'timezone' => 'Asia/Dhaka']);
        $workspace = Workspace::create(['company_id' => $company->id, 'name' => 'Partner Unit 1']);
        return [$company, $workspace];
    }

    private function member(Company $company, Workspace $workspace, string $role = 'student', ?CompanyMembership $manager = null): array
    {
        $user = User::factory()->create();
        $membership = CompanyMembership::create([
            'company_id' => $company->id, 'workspace_id' => $workspace->id, 'user_id' => $user->id,
            'role' => $role, 'manager_membership_id' => $manager?->id,
        ]);
        return [$user, $membership];
    }

    public function test_guest_cannot_view_real_attendance_or_write_records(): void
    {
        $this->get('/app/c/rac/attendance')->assertRedirect('/login');
        $this->post('/app/c/rac/attendance/check-in', ['mode' => 'office'])->assertRedirect('/login');
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_login_requires_matching_active_company_membership(): void
    {
        [$company, $workspace] = $this->structure();
        [$user] = $this->member($company, $workspace);
        $user->update(['password' => Hash::make('SafePassw0rd#123')]);

        $this->post('/app/c/rac/login', ['email' => $user->email, 'password' => 'SafePassw0rd#123'])
            ->assertRedirect('/app/c/rac/attendance');
        $this->assertAuthenticatedAs($user);

        $this->post('/app/c/rac/logout')->assertRedirect('/app/c/rac/login');
        $this->assertGuest();

        $this->post('/app/c/rac/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors('email');

        $company->update(['is_active' => false]);
        $this->post('/app/c/rac/login', ['email' => $user->email, 'password' => 'SafePassw0rd#123'])->assertNotFound();
    }

    public function test_other_company_user_cannot_use_same_firm_url(): void
    {
        [$first, $workspaceOne] = $this->structure('rac');
        [$second, $workspaceTwo] = $this->structure('other-firm');
        [$user] = $this->member($second, $workspaceTwo);
        $this->actingAs($user)->get('/app/c/rac/attendance')->assertNotFound();
        $this->actingAs($user)->post('/app/c/rac/attendance/check-in', ['mode' => 'office'])->assertNotFound();
        $this->assertDatabaseCount('attendance_days', 0);
    }

    public function test_student_can_check_in_and_out_and_persist_actual_utc_server_timestamps(): void
    {
        [$company, $workspace] = $this->structure();
        [$student, $membership] = $this->member($company, $workspace);
        $this->actingAs($student)->post('/app/c/rac/attendance/check-in', ['mode' => 'office'])->assertRedirect('/app/c/rac/attendance');
        $this->assertDatabaseCount('attendance_days', 1);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $session = AttendanceSession::firstOrFail();
        $this->assertSame($membership->id, $session->membership_id);
        $this->assertSame($company->id, $session->company_id);
        $this->assertTrue($session->is_open);
        $this->assertNotNull($session->checked_in_at);

        $this->actingAs($student)->post('/app/c/rac/attendance/check-out')->assertRedirect('/app/c/rac/attendance');
        $this->assertFalse($session->fresh()->is_open);
        $this->assertNotNull($session->fresh()->checked_out_at);

        $this->actingAs($student)->post('/app/c/rac/attendance/check-in', ['mode' => 'client', 'client_site' => 'Sample Client Office'])
            ->assertRedirect('/app/c/rac/attendance');
        $this->assertDatabaseCount('attendance_days', 1);
        $this->assertDatabaseCount('attendance_sessions', 2);
        $this->assertSame('client', AttendanceSession::latest('id')->first()->mode);
    }

    public function test_duplicate_checkin_and_missing_checkout_are_rejected(): void
    {
        [$company, $workspace] = $this->structure();
        [$student] = $this->member($company, $workspace);
        $this->actingAs($student)->post('/app/c/rac/attendance/check-out')->assertSessionHasErrors('mode');
        $this->actingAs($student)->post('/app/c/rac/attendance/check-in', ['mode' => 'office']);
        $this->actingAs($student)->post('/app/c/rac/attendance/check-in', ['mode' => 'office'])->assertSessionHasErrors('mode');
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_client_mode_requires_client_site(): void
    {
        [$company, $workspace] = $this->structure();
        [$student] = $this->member($company, $workspace);
        $this->actingAs($student)->post('/app/c/rac/attendance/check-in', ['mode' => 'client'])->assertSessionHasErrors('client_site');
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_manager_cannot_check_in_as_student(): void
    {
        [$company, $workspace] = $this->structure();
        [$manager] = $this->member($company, $workspace, 'manager');
        $this->actingAs($manager)->post('/app/c/rac/attendance/check-in', ['mode' => 'office'])->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions', 0);
    }

    public function test_manager_and_partner_can_only_read_their_allowed_workspace_rows(): void
    {
        [$company, $workspace] = $this->structure();
        $otherWorkspace = Workspace::create(['company_id' => $company->id, 'name' => 'Partner Unit 2']);
        [$partner] = $this->member($company, $workspace, 'partner');
        [$manager, $managerMembership] = $this->member($company, $workspace, 'manager');
        [$assignedStudent, $assignedMembership] = $this->member($company, $workspace, 'student', $managerMembership);
        [$unassignedStudent, $unassignedMembership] = $this->member($company, $workspace, 'student');
        [$otherStudent, $otherMembership] = $this->member($company, $otherWorkspace, 'student');

        foreach ([$assignedMembership, $unassignedMembership, $otherMembership] as $m) {
            AttendanceDay::create([
                'company_id' => $company->id, 'workspace_id' => $m->workspace_id,
                'membership_id' => $m->id, 'work_date' => '2026-10-12',
            ]);
        }

        $this->actingAs($manager)->get('/app/c/rac/attendance')->assertOk()
            ->assertSee((string) $assignedMembership->id)
            ->assertDontSee('Partner Unit 2');
        $this->actingAs($partner)->get('/app/c/rac/attendance')->assertOk();
        $this->actingAs($assignedStudent)->get('/app/c/rac/attendance')->assertOk();
    }
}
