<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceRules;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PracticeManagementAndCompanyRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-17 12:00:00', 'Asia/Dhaka'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function company(string $slug = 'rac'): int
    {
        return DB::table('companies')->insertGetId([
            'slug' => $slug, 'name' => 'Test Company '.$slug,
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function workspace(int $company, string $slug): int
    {
        return DB::table('workspaces')->insertGetId([
            'company_id' => $company, 'slug' => $slug, 'name' => $slug,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function member(int $company, int $workspace, string $role, ?int $manager = null): array
    {
        $user = User::factory()->create();
        $id = DB::table('memberships')->insertGetId([
            'company_id' => $company, 'workspace_id' => $workspace,
            'user_id' => $user->id, 'role' => $role,
            'manager_membership_id' => $manager, 'active' => true,
            'monthly_allowance' => 8000, 'created_at' => now(), 'updated_at' => now(),
        ]);
        return [$user, $id];
    }

    private function ruleInput(string $date = '2026-11-01'): array
    {
        return [
            'effective_date' => $date,
            'leave_max_days' => 3,
            'annual_leave_notice_days' => 5,
            'conveyance_max_per_claim' => 500,
            'reason' => 'Policy reviewed for a fictional test workforce.',
        ];
    }

    public function test_partner_and_manager_have_operational_control_page_but_student_cannot_manage(): void
    {
        $company = $this->company();
        $workspace = $this->workspace($company, 'partner-a');
        [$partner] = $this->member($company, $workspace, 'partner');
        [$manager] = $this->member($company, $workspace, 'manager');
        [$student] = $this->member($company, $workspace, 'student');

        $this->actingAs($partner)->get('/c/rac/app')->assertOk()
            ->assertSee('PARTNER CONTROL CENTER')
            ->assertSee('Practice Management');
        $this->get('/c/rac/app/manage')->assertOk()
            ->assertSee('Approve Workspace Rules');
        $this->actingAs($manager)->get('/c/rac/app')->assertOk()
            ->assertSee('MANAGER OPERATIONS');
        $this->get('/c/rac/app/manage')->assertOk()
            ->assertSee('Request a Workspace Policy Change');
        $this->actingAs($student)->get('/c/rac/app/manage')->assertForbidden();
        $this->post('/c/rac/app/manage/workflow-policy', $this->ruleInput())->assertForbidden();
    }

    public function test_manager_request_requires_partner_approval_and_enforces_leave_conveyance_limits(): void
    {
        Storage::fake('local');
        $company = $this->company();
        $workspace = $this->workspace($company, 'partner-a');
        [$partner] = $this->member($company, $workspace, 'partner');
        [$manager, $managerId] = $this->member($company, $workspace, 'manager');
        [$student] = $this->member($company, $workspace, 'student', $managerId);

        $this->actingAs($manager)->post('/c/rac/app/manage/workflow-policy', $this->ruleInput())
            ->assertRedirect();
        $row = DB::table('workspace_operational_policy_requests')->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(61, WorkspaceRules::effective(
            DB::table('companies')->find($company), DB::table('workspaces')->find($workspace), '2026-11-12'
        )['leave_max_days']);

        $this->actingAs($partner)->post("/c/rac/app/manage/workflow-policy/$row->id/decision", [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('workspace_operational_policy_requests', [
            'id' => $row->id, 'status' => 'approved',
        ]);

        $this->actingAs($student)->post('/c/rac/app/leave', [
            'leave_type' => 'annual', 'date_from' => '2026-11-01',
            'date_to' => '2026-11-05', 'reason' => 'Annual leave requested for a planned personal trip.',
        ])->assertSessionHasErrors('date_to');

        $this->post('/c/rac/app/leave', [
            'leave_type' => 'annual', 'date_from' => '2026-11-01',
            'date_to' => '2026-11-03', 'reason' => 'Annual leave requested for a planned personal trip.',
        ])->assertRedirect();
        $this->assertDatabaseCount('leave_requests', 1);

        $this->post('/c/rac/app/claims', [
            'journey_date' => '2026-11-02', 'journey_route' => 'Client site to office',
            'purpose' => 'Return travel after the audit visit',
            'amount' => 800,
            'receipt' => UploadedFile::fake()->create('receipt.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('conveyance_claims', 0);
    }

    public function test_manager_cannot_approve_policy_and_other_partner_cannot_approve(): void
    {
        $company = $this->company();
        $a = $this->workspace($company, 'partner-a');
        $b = $this->workspace($company, 'partner-b');
        [$partnerA] = $this->member($company, $a, 'partner');
        [$partnerB] = $this->member($company, $b, 'partner');
        [$manager] = $this->member($company, $a, 'manager');

        $this->actingAs($manager)->post('/c/rac/app/manage/workflow-policy', $this->ruleInput())
            ->assertRedirect();
        $requestId = DB::table('workspace_operational_policy_requests')->value('id');

        $this->post("/c/rac/app/manage/workflow-policy/$requestId/decision", [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($partnerB)
            ->post("/c/rac/app/manage/workflow-policy/$requestId/decision", [
                'decision' => 'approve',
            ])->assertNotFound();

        $this->actingAs($partnerA)->post("/c/rac/app/manage/workflow-policy/$requestId/decision", [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->post("/c/rac/app/manage/workflow-policy/$requestId/decision", [
            'decision' => 'approve',
        ])->assertSessionHasErrors('decision');
    }

    public function test_manager_holiday_request_requires_partner_approval_and_calendar_is_versioned(): void
    {
        $company = $this->company();
        $workspace = $this->workspace($company, 'partner-a');
        [$partner] = $this->member($company, $workspace, 'partner');
        [$manager] = $this->member($company, $workspace, 'manager');

        $this->actingAs($manager)->post('/c/rac/app/manage/holidays', [
            'work_date' => '2026-11-25', 'day_type' => 'holiday',
            'reason' => 'Special holiday for the partner office team.',
        ])->assertRedirect();
        $this->assertDatabaseCount('workspace_day_overrides', 0);
        $id = DB::table('workspace_holiday_requests')->value('id');
        $this->post("/c/rac/app/manage/holidays/$id/decision", [
            'decision' => 'approve',
        ])->assertForbidden();

        $this->actingAs($partner)->post("/c/rac/app/manage/holidays/$id/decision", [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->assertDatabaseHas('workspace_day_overrides', [
            'company_id' => $company, 'workspace_id' => $workspace,
            'work_date' => '2026-11-25', 'day_type' => 'holiday',
        ]);
        $this->post("/c/rac/app/manage/holidays/$id/decision", [
            'decision' => 'approve',
        ])->assertSessionHasErrors('decision');
    }

    public function test_manager_team_directory_only_includes_assigned_students_and_no_edit_forms(): void
    {
        $company = $this->company();
        $w1 = $this->workspace($company, 'partner-a');
        $w2 = $this->workspace($company, 'partner-b');
        [$manager, $managerId] = $this->member($company, $w1, 'manager');
        [$ownStudent] = $this->member($company, $w1, 'student', $managerId);
        [$otherStudent] = $this->member($company, $w2, 'student');

        $this->actingAs($manager)->get('/c/rac/app/team')->assertOk()
            ->assertSee($ownStudent->email)
            ->assertDontSee($otherStudent->email)
            ->assertDontSee('Save', false)
            ->assertSee('Manager Review Tools');
        $this->post('/c/rac/app/team/invite', [
            'name' => 'Unauthorized Hire', 'email' => 'hire@example.test', 'role' => 'student',
        ])->assertForbidden();
    }

    public function test_platform_company_delete_is_recoverable_and_audited_not_cascaded(): void
    {
        $company = $this->company();
        $workspace = $this->workspace($company, 'partner-a');
        [$student] = $this->member($company, $workspace, 'student');
        $platform = User::factory()->create(['is_platform_admin' => true]);
        $route = "/platform/app/companies/$company/lifecycle";

        $this->actingAs($student)->post($route, [
            'action' => 'delete', 'confirmation_slug' => 'rac', 'delete_confirmation' => 'DELETE',
        ])->assertForbidden();

        $this->actingAs($platform)->post($route, [
            'action' => 'delete', 'confirmation_slug' => 'wrong', 'delete_confirmation' => 'DELETE',
        ])->assertSessionHasErrors('confirmation_slug');
        $this->post($route, [
            'action' => 'delete', 'confirmation_slug' => 'rac', 'delete_confirmation' => 'DELETE',
        ])->assertRedirect('/platform/app');

        $this->assertNotNull(DB::table('companies')->where('id', $company)->value('deleted_at'));
        $this->assertDatabaseHas('memberships', ['company_id' => $company, 'user_id' => $student->id]);
        $this->assertDatabaseHas('platform_control_events', [
            'company_id' => $company, 'action' => 'company.delete',
        ]);
        $this->get('/platform/app')->assertDontSee('Test Company rac');
        $this->get('/platform/app?status=deleted')->assertSee('Test Company rac');
        $this->actingAs($student)->get('/c/rac/app')->assertForbidden();

        $this->actingAs($platform)->post($route, [
            'action' => 'restore', 'confirmation_slug' => 'rac',
        ])->assertRedirect();
        $this->assertDatabaseHas('companies', [
            'id' => $company, 'active' => 0, 'deleted_at' => null, 'archived_at' => null,
        ]);
        $this->actingAs($student)->get('/c/rac/app')->assertForbidden();
    }
}
