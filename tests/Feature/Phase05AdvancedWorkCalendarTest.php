<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PolicyResolver;
use App\Support\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase05AdvancedWorkCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function firm(): array
    {
        $companyId = DB::table('companies')->insertGetId([
            'slug' => 'client-ca', 'name' => 'Client CA Firm', 'timezone' => 'Asia/Dhaka',
            'office_start' => '10:00:00', 'office_end' => '18:00:00',
            'grace_minutes' => 10, 'weekly_holidays' => '[5,6]',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $workspaces = [];
        $people = [];
        foreach (['partner-a', 'partner-b'] as $slug) {
            $workspaceId = DB::table('workspaces')->insertGetId([
                'company_id' => $companyId, 'slug' => $slug,
                'name' => ucfirst($slug), 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $workspaces[$slug] = $workspaceId;

            foreach (['partner', 'manager', 'student'] as $role) {
                $user = User::factory()->create();
                $membershipId = DB::table('memberships')->insertGetId([
                    'company_id' => $companyId, 'workspace_id' => $workspaceId,
                    'user_id' => $user->id, 'role' => $role,
                    'active' => true, 'monthly_allowance' => '0.00',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $people[$slug][$role] = (object) ['user' => $user, 'id' => $membershipId];
            }
        }
        return [$companyId, $workspaces, $people];
    }

    private function friday(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->next(Carbon::FRIDAY)->toDateString();
    }

    private function payload(string $date): array
    {
        return [
            'work_date' => $date, 'day_type' => 'working',
            'holiday_category' => 'client_duty',
            'office_start' => '11:30', 'office_end' => '17:30',
            'grace_minutes' => 10,
            'reason' => 'A special audit client duty has been authorized.',
        ];
    }

    public function test_partner_opens_friday_at_specific_hours_and_only_own_attendance_uses_late_cutoff(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = $this->friday();
        $this->actingAs($people['partner-a']['partner']->user)
            ->post('/c/client-ca/app/policies/calendar/day', $this->payload($date))
            ->assertRedirect();

        $this->assertDatabaseHas('workspace_day_overrides', [
            'company_id' => $firm, 'workspace_id' => $ws['partner-a'],
            'work_date' => $date, 'office_start' => '11:30:00',
            'office_end' => '17:30:00', 'grace_minutes' => 10,
            'status' => 'approved', 'holiday_category' => 'client_duty',
        ]);
        $company = DB::table('companies')->find($firm);
        $a = DB::table('workspaces')->find($ws['partner-a']);
        $b = DB::table('workspaces')->find($ws['partner-b']);
        $ruleA = WorkCalendar::forDate($company, $a, $date, [5,6]);
        $ruleB = WorkCalendar::forDate($company, $b, $date, [5,6]);
        $this->assertFalse($ruleA['holiday']);
        $this->assertSame('11:30:00', $ruleA['office_start']);
        $this->assertSame('17:30:00', $ruleA['office_end']);
        $this->assertSame(10, $ruleA['grace_minutes']);
        $this->assertTrue($ruleB['holiday']);
        $this->assertSame('10:00:00', $ruleB['office_start']);

        $clock = CarbonImmutable::parse($date.' 11:42:00', 'Asia/Dhaka');
        CarbonImmutable::setTestNow($clock);
        Carbon::setTestNow($clock);
        try {
            $this->actingAs($people['partner-a']['student']->user)
                ->post('/c/client-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect();
            $this->actingAs($people['partner-b']['student']->user)
                ->post('/c/client-ca/app/attendance/check-in', ['work_mode' => 'office'])
                ->assertRedirect();
            $this->assertDatabaseHas('attendance_sessions', [
                'workspace_id' => $ws['partner-a'], 'work_date' => $date,
                'holiday' => 0, 'late' => 1,
            ]);
            $this->assertDatabaseHas('attendance_sessions', [
                'workspace_id' => $ws['partner-b'], 'work_date' => $date,
                'holiday' => 1, 'late' => 0,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    public function test_manager_proposal_never_applies_until_partner_approval(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = $this->friday();
        $actor = $people['partner-a']['manager'];
        $this->actingAs($actor->user)->post('/c/client-ca/app/policies/calendar/day',
            $this->payload($date))->assertRedirect();
        $row = DB::table('workspace_day_overrides')->where('workspace_id', $ws['partner-a'])->first();
        $this->assertSame('pending', $row->status);
        $this->assertNull($row->authorized_by);
        $company = DB::table('companies')->find($firm);
        $workspace = DB::table('workspaces')->find($ws['partner-a']);
        $this->assertTrue(WorkCalendar::forDate($company, $workspace, $date, [5,6])['holiday']);

        $this->actingAs($people['partner-b']['partner']->user)
            ->post("/c/client-ca/app/policies/calendar/day/{$row->id}/decision",
                ['decision' => 'approve'])->assertNotFound();

        $this->actingAs($people['partner-a']['partner']->user)
            ->post("/c/client-ca/app/policies/calendar/day/{$row->id}/decision",
                ['decision' => 'approve'])->assertRedirect();
        $this->assertDatabaseHas('workspace_day_overrides', [
            'id' => $row->id, 'status' => 'approved',
            'authorized_by' => $people['partner-a']['partner']->id,
            'reviewed_by' => $people['partner-a']['partner']->id,
        ]);
        $this->assertFalse(WorkCalendar::forDate($company, $workspace, $date, [5,6])['holiday']);
        $this->assertSame('11:30:00',
            WorkCalendar::forDate($company, $workspace, $date, [5,6])['office_start']);

        $this->actingAs($actor->user)
            ->post("/c/client-ca/app/policies/calendar/day/{$row->id}/decision",
                ['decision' => 'reject'])->assertForbidden();
        $this->actingAs($people['partner-a']['partner']->user)
            ->post("/c/client-ca/app/policies/calendar/day/{$row->id}/decision",
                ['decision' => 'reject'])->assertSessionHasErrors('decision');
    }

    public function test_manager_rejection_preserves_history_and_previous_approved_day(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = $this->friday();
        $this->actingAs($people['partner-a']['partner']->user)->post('/c/client-ca/app/policies/calendar/day',
            $this->payload($date))->assertRedirect();

        $manager = $people['partner-a']['manager']->user;
        $this->actingAs($manager)->post('/c/client-ca/app/policies/calendar/day', [
            'work_date' => $date, 'day_type' => 'holiday',
            'holiday_category' => 'emergency',
            'reason' => 'Proposal to cancel the unusual Friday audit duty.',
        ])->assertRedirect();
        $proposal = DB::table('workspace_day_overrides')->where('status', 'pending')->first();
        $company = DB::table('companies')->find($firm);
        $workspace = DB::table('workspaces')->find($ws['partner-a']);
        $this->assertFalse(WorkCalendar::forDate($company, $workspace, $date, [5,6])['holiday']);

        $this->actingAs($people['partner-a']['partner']->user)
            ->post("/c/client-ca/app/policies/calendar/day/{$proposal->id}/decision", [
                'decision' => 'reject',
            ])->assertRedirect();
        $this->assertDatabaseCount('workspace_day_overrides', 2);
        $this->assertFalse(WorkCalendar::forDate($company, $workspace, $date, [5,6])['holiday']);
        $this->assertDatabaseHas('tenant_audit_events', [
            'action' => 'calendar.day_rejected', 'workspace_id' => $ws['partner-a'],
        ]);
    }

    public function test_manual_government_holiday_remains_private_to_one_partner_and_does_not_assume_live_feed(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = CarbonImmutable::now('Asia/Dhaka')->next(Carbon::MONDAY)->toDateString();
        $this->actingAs($people['partner-a']['partner']->user)
            ->post('/c/client-ca/app/policies/calendar/day', [
                'work_date' => $date, 'day_type' => 'holiday',
                'holiday_category' => 'government',
                'reason' => 'Firm-verified public holiday from an official notice.',
            ])->assertRedirect();

        $company = DB::table('companies')->find($firm);
        $first = DB::table('workspaces')->find($ws['partner-a']);
        $second = DB::table('workspaces')->find($ws['partner-b']);
        $this->assertTrue(WorkCalendar::forDate($company, $first, $date, [5,6])['holiday']);
        $this->assertFalse(WorkCalendar::forDate($company, $second, $date, [5,6])['holiday']);
        $this->assertDatabaseHas('workspace_day_overrides', [
            'workspace_id' => $ws['partner-a'], 'holiday_category' => 'government',
            'status' => 'approved',
        ]);
    }

    public function test_invalid_schedule_and_frozen_payroll_cannot_change_future_calendar(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = $this->friday();
        $partner = $people['partner-a']['partner']->user;
        $this->actingAs($partner)->post('/c/client-ca/app/policies/calendar/day', [
            ...$this->payload($date),
            'office_start' => '19:00', 'office_end' => '17:30',
        ])->assertSessionHasErrors('office_end');
        $this->post('/c/client-ca/app/policies/calendar/day', [
            ...$this->payload($date),
            'day_type' => 'holiday',
        ])->assertSessionHasErrors('office_start');

        DB::table('payroll_runs')->insert([
            'company_id' => $firm, 'workspace_id' => $ws['partner-a'],
            'period_month' => substr($date,0,7).'-01', 'status' => 'locked',
            'policy_snapshot' => '{}', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->post('/c/client-ca/app/policies/calendar/day', $this->payload($date))
            ->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('workspace_day_overrides', 0);
    }

    public function test_partner_calendar_preview_shows_effective_hours_but_pending_manager_shift_is_unpublished(): void
    {
        [$firm, $ws, $people] = $this->firm();
        $date = $this->friday();
        $this->actingAs($people['partner-a']['manager']->user)
            ->post('/c/client-ca/app/policies/calendar/day', $this->payload($date))
            ->assertRedirect();
        $this->actingAs($people['partner-a']['partner']->user)
            ->get('/c/client-ca/app/policies')
            ->assertOk()->assertSeeText('Next 14 Days — Approved Schedule')
            ->assertSeeText('Partner Decision') // options exist
            ->assertSeeText('Approve Request');

        $row = DB::table('workspace_day_overrides')->first();
        $this->post("/c/client-ca/app/policies/calendar/day/{$row->id}/decision", [
            'decision' => 'approve',
        ])->assertRedirect();
        $this->get('/c/client-ca/app/policies')->assertOk()
            ->assertSeeText('Special Decision')
            ->assertSeeText('11:30–17:30');
    }
}
