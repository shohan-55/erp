<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkCalendar;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase05StudentTeamShiftsTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $firm = DB::table('companies')->insertGetId([
            'slug' => 'practice', 'name' => 'Practice CA Firm',
            'timezone' => 'Asia/Dhaka', 'office_start' => '10:00:00',
            'office_end' => '18:00:00', 'grace_minutes' => 10,
            'weekly_holidays' => '[5,6]', 'active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $actors = [];
        foreach (['a', 'b'] as $slug) {
            $ws = DB::table('workspaces')->insertGetId([
                'company_id' => $firm, 'slug' => 'partner-'.$slug,
                'name' => 'Partner '.$slug, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach (['partner','manager','student1','student2'] as $role) {
                $u = User::factory()->create();
                $r = str_starts_with($role,'student') ? 'student' : $role;
                $id = DB::table('memberships')->insertGetId([
                    'company_id' => $firm, 'workspace_id' => $ws,
                    'user_id' => $u->id, 'role' => $r,
                    'manager_membership_id' => $r === 'student' ? $actors[$slug]['manager']->id : null,
                    'active' => true, 'monthly_allowance' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $actors[$slug][$role] = (object) ['user' => $u, 'id' => $id, 'workspace' => $ws];
            }
        }
        return [$firm,$actors];
    }

    private function friday(): string
    {
        return CarbonImmutable::now('Asia/Dhaka')->next(Carbon::FRIDAY)->toDateString();
    }

    private function payload(string $date): array
    {
        return [
            'scope' => 'student', 'work_date' => $date, 'day_type' => 'working',
            'office_start' => '11:30', 'office_end' => '17:30',
            'grace_minutes' => 8,
            'reason' => 'Special student attendance for client engagement.',
        ];
    }

    public function test_student_shift_changes_late_rule_only_for_that_student_not_peer_or_other_partner(): void
    {
        [$firm,$p] = $this->fixture();
        $date = $this->friday();
        $this->actingAs($p['a']['partner']->user)->post('/c/practice/app/policies/calendar/students', [
            ...$this->payload($date), 'student_membership_id' => $p['a']['student1']->id,
        ])->assertRedirect();
        $company = DB::table('companies')->find($firm);
        $ws = DB::table('workspaces')->find($p['a']['student1']->workspace);
        $own = DB::table('memberships')->find($p['a']['student1']->id);
        $peer = DB::table('memberships')->find($p['a']['student2']->id);
        $mine = WorkCalendar::forMemberDate($company,$ws,$own,$date);
        $theirs = WorkCalendar::forMemberDate($company,$ws,$peer,$date);
        $this->assertFalse($mine['holiday']);
        $this->assertTrue($theirs['holiday']);
        $this->assertSame('11:30:00', $mine['office_start']);
        $this->assertSame('17:30:00', $mine['office_end']);
        $this->assertSame(8, $mine['grace_minutes']);

        $clock = CarbonImmutable::parse($date.' 11:40:00','Asia/Dhaka');
        CarbonImmutable::setTestNow($clock);
        Carbon::setTestNow($clock);
        try {
            $this->actingAs($p['a']['student1']->user)
                ->post('/c/practice/app/attendance/check-in',['work_mode'=>'office'])->assertRedirect();
            $this->actingAs($p['a']['student2']->user)
                ->post('/c/practice/app/attendance/check-in',['work_mode'=>'office'])->assertRedirect();
            $this->actingAs($p['b']['student1']->user)
                ->post('/c/practice/app/attendance/check-in',['work_mode'=>'office'])->assertRedirect();
            $this->assertDatabaseHas('attendance_sessions', [
                'membership_id' => $p['a']['student1']->id, 'holiday' => 0, 'late' => 1,
            ]);
            $this->assertDatabaseHas('attendance_sessions', [
                'membership_id' => $p['a']['student2']->id, 'holiday' => 1, 'late' => 0,
            ]);
            $this->assertDatabaseHas('attendance_sessions', [
                'membership_id' => $p['b']['student1']->id, 'holiday' => 1, 'late' => 0,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    public function test_manager_team_proposal_requires_partner_batch_approval(): void
    {
        [$firm,$p] = $this->fixture();
        $date = $this->friday();
        $this->actingAs($p['a']['manager']->user)
            ->post('/c/practice/app/policies/calendar/students', [
                ...$this->payload($date),
                'scope' => 'team',
                'manager_membership_id' => $p['a']['manager']->id,
            ])->assertRedirect();
        $rows = DB::table('student_calendar_shifts')->get();
        $this->assertCount(2,$rows);
        $this->assertSame('pending',$rows[0]->status);
        $this->assertSame($rows[0]->batch_ref,$rows[1]->batch_ref);
        $company = DB::table('companies')->find($firm);
        $workspace = DB::table('workspaces')->find($p['a']['manager']->workspace);
        $member = DB::table('memberships')->find($p['a']['student1']->id);
        $this->assertTrue(WorkCalendar::forMemberDate($company,$workspace,$member,$date)['holiday']);

        $this->actingAs($p['b']['partner']->user)
            ->post("/c/practice/app/policies/calendar/students/{$rows[0]->id}/decision", [
                'decision' => 'approve',
            ])->assertNotFound();
        $this->actingAs($p['a']['manager']->user)
            ->post("/c/practice/app/policies/calendar/students/{$rows[0]->id}/decision", [
                'decision' => 'approve',
            ])->assertForbidden();

        $this->actingAs($p['a']['partner']->user)
            ->post("/c/practice/app/policies/calendar/students/{$rows[0]->id}/decision", [
                'decision' => 'approve',
            ])->assertRedirect();
        $this->assertSame(2,DB::table('student_calendar_shifts')->where('status','approved')->count());
        $this->assertFalse(WorkCalendar::forMemberDate($company,$workspace,$member,$date)['holiday']);
        $this->assertDatabaseHas('tenant_audit_events', ['action'=>'shift.approved','workspace_id'=>$workspace->id]);
    }

    public function test_cross_partner_student_and_other_manager_team_cannot_be_targeted(): void
    {
        [$firm,$p] = $this->fixture();
        $date = $this->friday();
        $this->actingAs($p['a']['partner']->user)->post('/c/practice/app/policies/calendar/students',[
            ...$this->payload($date), 'student_membership_id' => $p['b']['student1']->id,
        ])->assertNotFound();
        $this->actingAs($p['a']['manager']->user)->post('/c/practice/app/policies/calendar/students',[
            ...$this->payload($date), 'scope'=>'team',
            'manager_membership_id'=>$p['b']['manager']->id,
        ])->assertForbidden();
        $this->actingAs($p['a']['student1']->user)->post('/c/practice/app/policies/calendar/students',[
            ...$this->payload($date), 'student_membership_id'=>$p['a']['student1']->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('student_calendar_shifts',0);
    }

    public function test_locked_payroll_and_past_date_block_student_or_team_shift(): void
    {
        [$firm,$p] = $this->fixture();
        $date = $this->friday();
        DB::table('payroll_runs')->insert([
            'company_id'=>$firm, 'workspace_id'=>$p['a']['partner']->workspace,
            'period_month'=>substr($date,0,7).'-01', 'status'=>'locked',
            'policy_snapshot'=>'{}', 'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($p['a']['partner']->user)->post('/c/practice/app/policies/calendar/students',[
            ...$this->payload($date), 'student_membership_id'=>$p['a']['student1']->id,
        ])->assertSessionHasErrors('work_date');
        $this->post('/c/practice/app/policies/calendar/students',[
            ...$this->payload(now('Asia/Dhaka')->subDay()->toDateString()),
            'student_membership_id'=>$p['a']['student1']->id,
        ])->assertSessionHasErrors('work_date');
        $this->assertDatabaseCount('student_calendar_shifts',0);
    }

    public function test_policy_screen_shows_only_authorized_student_and_team_schedule_history(): void
    {
        [$firm,$p] = $this->fixture();
        $date = $this->friday();
        $this->actingAs($p['a']['partner']->user)
            ->post('/c/practice/app/policies/calendar/students', [
                ...$this->payload($date), 'student_membership_id'=>$p['a']['student1']->id,
            ])->assertRedirect();
        $this->get('/c/practice/app/policies')->assertOk()
            ->assertSeeText('Student Shifts & Team Duty Calendar')
            ->assertSeeText('Shift Request & Decision History')
            ->assertSeeText('Special student attendance for client engagement.');
        $this->actingAs($p['b']['partner']->user)->get('/c/practice/app/policies')->assertOk()
            ->assertDontSeeText('Special student attendance for client engagement.');
    }
}
