<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Identity boundary tests only: a signed-in student cannot submit attendance
 * on behalf of another membership. This does NOT establish physical presence
 * or prevent stolen credentials / voluntary password sharing.
 */
class Phase07AttendanceImpersonationBoundariesTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $slug): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Synthetic '.$slug,
            'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $company, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => $slug,
            'name' => 'Synthetic '.$slug,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $company, int $workspace, string $role): object
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'monthly_allowance' => '0.00', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (object) ['id' => $id, 'user' => $user];
    }

    public function test_injected_membership_and_tenant_ids_cannot_proxy_another_students_check_in(): void
    {
        $company = $this->company('qa-a');
        $otherCompany = $this->company('qa-b');
        $workspace = $this->workspace($company, 'partner-a');
        $otherWorkspace = $this->workspace($company, 'partner-b');
        $outsideWorkspace = $this->workspace($otherCompany, 'outside');

        $student = $this->member($company, $workspace, 'student');
        $peer = $this->member($company, $workspace, 'student');
        $otherPartnerStudent = $this->member($company, $otherWorkspace, 'student');
        $outsideStudent = $this->member($otherCompany, $outsideWorkspace, 'student');

        $this->actingAs($student->user)->post('/c/qa-a/app/attendance/check-in', [
            'work_mode' => 'office',
            'membership_id' => $peer->id,
            'user_id' => $peer->user->id,
            'company_id' => $otherCompany,
            'workspace_id' => $otherWorkspace,
            'started_at' => '2000-01-01 00:00:00',
            'late' => '1',
            'holiday' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_sessions', [
            'membership_id' => $student->id,
            'company_id' => $company,
            'workspace_id' => $workspace,
        ]);
        $this->assertDatabaseMissing('attendance_sessions', ['membership_id' => $peer->id]);
        $this->assertDatabaseMissing('attendance_sessions', ['membership_id' => $otherPartnerStudent->id]);
        $this->assertDatabaseMissing('attendance_sessions', ['membership_id' => $outsideStudent->id]);
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_locations', 0);
        $this->assertDatabaseHas('tenant_audit_events', [
            'company_id' => $company,
            'workspace_id' => $workspace,
            'actor_membership_id' => $student->id,
            'action' => 'attendance.check_in',
        ]);

        // A separate legitimate login cannot close the first student's session.
        $this->actingAs($peer->user)->post('/c/qa-a/app/attendance/check-out', [
            'membership_id' => $student->id,
        ])->assertSessionHasErrors('work_mode');

        $this->assertDatabaseHas('attendance_sessions', [
            'membership_id' => $student->id,
            'ended_at' => null,
        ]);
        $this->assertDatabaseCount('attendance_sessions', 1);

        // Swapping firm slugs does not create a membership in another company.
        $this->actingAs($outsideStudent->user)
            ->post('/c/qa-a/app/attendance/check-in', ['work_mode' => 'office'])
            ->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions', 1);
    }

    public function test_only_partner_can_correct_a_students_record_and_student_cannot_self_overwrite(): void
    {
        $company = $this->company('qa-a');
        $workspace = $this->workspace($company, 'partner-a');
        $student = $this->member($company, $workspace, 'student');
        $manager = $this->member($company, $workspace, 'manager');

        $this->actingAs($student->user)->post('/c/qa-a/app/attendance/check-in', [
            'work_mode' => 'office',
        ])->assertRedirect();
        $sessionId = DB::table('attendance_sessions')->where('membership_id', $student->id)->value('id');

        $payload = [
            'ended_at' => now('Asia/Dhaka')->format('Y-m-d\\TH:i'),
            'reason' => 'Synthetic test of impersonation attempt',
            'membership_id' => $manager->id,
        ];

        $this->post("/c/qa-a/app/attendance/{$sessionId}/correct", $payload)
            ->assertForbidden();
        $this->actingAs($manager->user)
            ->post("/c/qa-a/app/attendance/{$sessionId}/correct", $payload)
            ->assertForbidden();
        $this->actingAs($manager->user)
            ->post('/c/qa-a/app/attendance/check-in', ['work_mode' => 'office'])
            ->assertForbidden();

        $this->assertDatabaseHas('attendance_sessions', [
            'id' => $sessionId, 'membership_id' => $student->id,
            'ended_at' => null, 'corrected_by' => null,
        ]);
        $this->assertDatabaseCount('attendance_sessions', 1);
    }
}
