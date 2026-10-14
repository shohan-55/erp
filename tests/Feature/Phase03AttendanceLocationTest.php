<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase03AttendanceLocationTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkspace(): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => 'qa-pilot',
            'name' => 'Privacy Test Company',
            'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00',
            'office_end' => '18:00:00',
            'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $workspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company,
            'slug' => 'partner-one',
            'name' => 'Partner Unit One',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$company, $workspace];
    }

    private function member(int $company, int $workspace, string $role, ?int $manager = null): object
    {
        $user = User::factory()->create();
        $membership = DB::table('memberships')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'user_id' => $user->id,
            'role' => $role,
            'manager_membership_id' => $manager,
            'monthly_allowance' => '8000.00',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (object) ['user' => $user, 'id' => $membership];
    }

    public function test_location_is_off_by_default_and_cannot_be_silently_injected(): void
    {
        [$company, $workspace] = $this->setupWorkspace();
        $student = $this->member($company, $workspace, 'student');
        $this->assertDatabaseHas('workspaces', ['id' => $workspace, 'attendance_location_enabled' => 0]);

        $this->actingAs($student->user)
            ->post('/c/qa-pilot/app/attendance/check-in', [
                'work_mode' => 'office',
                'location_latitude' => '23.88',
                'location_longitude' => '90.40',
                'location_accuracy' => '13',
                'location_consent' => '1',
            ])->assertSessionHasErrors('location_consent');
        $this->assertDatabaseCount('attendance_sessions', 0);

        $this->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'office',
        ])->assertRedirect();
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_locations', 0);
    }

    public function test_only_partner_can_enable_location_and_students_must_consent(): void
    {
        [$company, $workspace] = $this->setupWorkspace();
        $manager = $this->member($company, $workspace, 'manager');
        $partner = $this->member($company, $workspace, 'partner');
        $student = $this->member($company, $workspace, 'student', $manager->id);

        $policy = [
            'attendance_location_enabled' => '1',
            'attendance_location_retention_days' => 30,
        ];
        $this->actingAs($manager->user)->post('/c/qa-pilot/app/attendance/location-policy', [
            ...$policy, 'notice_acknowledged' => '1',
        ])->assertForbidden();
        $this->actingAs($partner->user)->post('/c/qa-pilot/app/attendance/location-policy', $policy)
            ->assertSessionHasErrors('notice_acknowledged');
        $this->assertDatabaseHas('workspaces', ['id' => $workspace, 'attendance_location_enabled' => 0]);

        $this->post('/c/qa-pilot/app/attendance/location-policy', [
            ...$policy, 'notice_acknowledged' => '1',
        ])->assertRedirect();
        $this->assertDatabaseHas('workspaces', ['id' => $workspace, 'attendance_location_enabled' => 1]);

        $this->actingAs($student->user)->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'office',
            'location_latitude' => '23.88',
            'location_longitude' => '90.40',
            'location_accuracy' => '13',
        ])->assertSessionHasErrors('location_consent');
        $this->assertDatabaseCount('attendance_sessions', 0);

        // Consent is voluntary; student may record attendance without browser GPS.
        $this->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'office',
        ])->assertRedirect();
        $this->assertDatabaseCount('attendance_sessions', 1);
        $this->assertDatabaseCount('attendance_locations', 0);
    }

    public function test_encrypted_gps_is_readable_only_by_own_partner_and_assigned_manager(): void
    {
        [$company, $workspace] = $this->setupWorkspace();
        $partner = $this->member($company, $workspace, 'partner');
        $manager = $this->member($company, $workspace, 'manager');
        $otherManager = $this->member($company, $workspace, 'manager');
        $student = $this->member($company, $workspace, 'student', $manager->id);
        $otherStudent = $this->member($company, $workspace, 'student');

        DB::table('workspaces')->where('id', $workspace)->update(['attendance_location_enabled' => true]);

        $this->actingAs($student->user)->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'client',
            'client_site' => 'Audit Company',
            'location_latitude' => '23.880001',
            'location_longitude' => '90.400002',
            'location_accuracy' => '10.50',
            'location_consent' => '1',
        ])->assertRedirect();

        $sessionId = DB::table('attendance_sessions')->where('membership_id', $student->id)->value('id');
        $stored = DB::table('attendance_locations')->first();
        $this->assertNotNull($stored);
        $this->assertNotEquals('23.880001', $stored->latitude_encrypted);
        $this->assertNotEquals('90.400002', $stored->longitude_encrypted);
        $this->assertSame('23.880001', Crypt::decryptString($stored->latitude_encrypted));
        $this->assertSame('90.400002', Crypt::decryptString($stored->longitude_encrypted));
        $this->assertDatabaseHas('tenant_audit_events', [
            'actor_membership_id' => $student->id, 'action' => 'attendance.check_in',
        ]);

        $url = "/c/qa-pilot/app/attendance/{$sessionId}/location";
        $this->get($url)->assertForbidden();
        $this->actingAs($otherManager->user)->get($url)->assertNotFound();
        $this->actingAs($manager->user)->get($url)->assertOk()
            ->assertSee('23.880001')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->actingAs($partner->user)->get($url)->assertOk()
            ->assertSee('Browser-Reported Attendance Location');

        $secondWorkspace = DB::table('workspaces')->insertGetId([
            'company_id' => $company,
            'slug' => 'other-partner',
            'name' => 'Other Partner',
            'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherPartner = $this->member($company, $secondWorkspace, 'partner');
        $this->actingAs($otherPartner->user)->get($url)->assertNotFound();

        $this->actingAs($student->user)->post('/c/qa-pilot/app/attendance/check-out', [
            'location_latitude' => '23.889999',
            'location_longitude' => '90.411111',
            'location_accuracy' => '12',
            'location_consent' => '1',
        ])->assertRedirect();
        $this->assertDatabaseCount('attendance_locations', 2);
    }

    public function test_location_prune_respects_workspace_retention_and_dry_run(): void
    {
        [$company, $workspace] = $this->setupWorkspace();
        $student = $this->member($company, $workspace, 'student');
        DB::table('workspaces')->where('id', $workspace)->update([
            'attendance_location_enabled' => true,
            'attendance_location_retention_days' => 7,
        ]);
        $this->actingAs($student->user)->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'office',
            'location_latitude' => '23.88',
            'location_longitude' => '90.40',
            'location_accuracy' => '15',
            'location_consent' => '1',
        ])->assertRedirect();

        DB::table('attendance_locations')->update(['captured_at' => now()->subDays(8)]);
        $this->artisan('erp:attendance:prune-locations --dry-run')
            ->expectsOutputToContain('eligible for deletion: 1')
            ->assertExitCode(0);
        $this->assertDatabaseCount('attendance_locations', 1);
        $this->artisan('erp:attendance:prune-locations')
            ->expectsOutputToContain('deleted: 1')
            ->assertExitCode(0);
        $this->assertDatabaseCount('attendance_locations', 0);
        $this->assertDatabaseCount('attendance_sessions', 1);
    }
}
