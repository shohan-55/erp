<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase02ShortLinksDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $slug): array
    {
        $company = DB::table('companies')->insertGetId([
            'slug' => $slug,
            'name' => 'QA Company '.$slug,
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
            'slug' => 'unit-one',
            'name' => 'Partner Unit One',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$company, $workspace];
    }

    private function member(int $company, int $workspace, string $role): object
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'user_id' => $user->id,
            'role' => $role,
            'monthly_allowance' => '8000.00',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (object) ['user' => $user, 'id' => $id];
    }

    public function test_short_link_redirects_only_known_active_tenants(): void
    {
        [$company, $workspace] = $this->tenant('qa-pilot');
        $this->get('/qa-pilot')->assertRedirect('/c/qa-pilot/app/login');
        $this->get('/nonexistent')->assertNotFound();
        $this->get('/QA-PILOT')->assertNotFound();
        $this->get('/')->assertOk();

        DB::table('companies')->where('id', $company)->update(['active' => false]);
        $this->get('/qa-pilot')->assertNotFound();
    }

    public function test_short_link_preserves_firm_membership_validation(): void
    {
        [$company, $workspace] = $this->tenant('qa-pilot');
        [$other, $otherWorkspace] = $this->tenant('other-firm');
        $partner = $this->member($company, $workspace, 'partner');

        $this->actingAs($partner->user)->get('/qa-pilot')
            ->assertRedirect('/c/qa-pilot/app/login');
        $this->get('/c/qa-pilot/app/login')->assertRedirect('/c/qa-pilot/app');

        $this->get('/other-firm')->assertRedirect('/c/other-firm/app/login');
        // Another company's link never grants another company's membership.
        $this->get('/c/other-firm/app')->assertForbidden();
    }

    public function test_student_can_check_in_and_out_from_dashboard_without_another_members_session(): void
    {
        [$company, $workspace] = $this->tenant('qa-pilot');
        $student = $this->member($company, $workspace, 'student');
        $other = $this->member($company, $workspace, 'student');

        DB::table('attendance_sessions')->insert([
            'company_id' => $company,
            'workspace_id' => $workspace,
            'membership_id' => $other->id,
            'work_date' => now('Asia/Dhaka')->toDateString(),
            'work_mode' => 'office',
            'started_at' => now()->utc()->toDateTimeString(),
            'late' => false, 'holiday' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($student->user)->get('/c/qa-pilot/app')
            ->assertOk()
            ->assertSee('QUICK ATTENDANCE')
            ->assertSee('Check In')
            ->assertSee('name="work_mode"', false);

        $this->post('/c/qa-pilot/app/attendance/check-in', [
            'work_mode' => 'office',
        ])->assertRedirect();

        $this->get('/c/qa-pilot/app')->assertOk()
            ->assertSee('A productive day is underway.')
            ->assertSee('Check Out')
            ->assertDontSee('Not checked in');

        $this->post('/c/qa-pilot/app/attendance/check-out')->assertRedirect();
        $this->get('/c/qa-pilot/app')->assertOk()
            ->assertSee('Check In');
        $this->assertDatabaseCount('attendance_sessions', 2);
        $this->assertDatabaseHas('tenant_audit_events', [
            'actor_membership_id' => $student->id,
            'action' => 'attendance.check_in',
        ]);
        $this->assertDatabaseHas('tenant_audit_events', [
            'actor_membership_id' => $student->id,
            'action' => 'attendance.check_out',
        ]);
    }

    public function test_dashboard_offers_personal_attendance_to_manager_but_not_partner(): void
    {
        [$company, $workspace] = $this->tenant('qa-pilot');
        $manager = $this->member($company, $workspace, 'manager');
        $partner = $this->member($company, $workspace, 'partner');

        $this->actingAs($manager->user)->get('/c/qa-pilot/app')->assertOk()
            ->assertSee('QUICK ATTENDANCE')
            ->assertSee('data-attendance-confirm-form', false)
            ->assertSee('name="work_mode"', false);

        $this->actingAs($partner->user)->get('/c/qa-pilot/app')->assertOk()
            ->assertDontSee('QUICK ATTENDANCE')
            ->assertDontSee('name="work_mode"', false);
    }
}
