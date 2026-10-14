<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeaveOverlapIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_submit_overlapping_pending_or_approved_leave(): void
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug' => 'rac', 'name' => 'Sample CA Firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $companyId, 'slug' => 'partner-01', 'name' => 'Partner 01',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $student = User::factory()->create();
        DB::table('memberships')->insert([
            'company_id' => $companyId, 'workspace_id' => $workspace,
            'user_id' => $student->id, 'role' => 'student', 'active' => true,
            'monthly_allowance' => '0.00', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student)->post('/c/rac/app/leave', [
            'leave_type' => 'annual',
            'date_from' => '2026-11-10', 'date_to' => '2026-11-12',
            'reason' => 'Planned family travel arrangements',
        ])->assertRedirect();

        $this->post('/c/rac/app/leave', [
            'leave_type' => 'sick',
            'date_from' => '2026-11-11', 'date_to' => '2026-11-15',
            'reason' => 'Request overlaps existing submitted leave',
        ])->assertSessionHasErrors('date_from');

        $this->assertDatabaseCount('leave_requests', 1);

        $this->post('/c/rac/app/leave', [
            'leave_type' => 'annual',
            'date_from' => '2026-11-13', 'date_to' => '2026-11-14',
            'reason' => 'Separate planned personal leave trip',
        ])->assertRedirect();

        $this->assertDatabaseCount('leave_requests', 2);
    }
}
