<?php

namespace Tests\Feature;

use App\Models\AttendanceDay;
use App\Models\AttendanceSession;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OperationalCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function setupFirm(): array
    {
        $company=Company::create(['name'=>'Example Firm','slug'=>'rac','timezone'=>'Asia/Dhaka']);
        $first=Workspace::create(['company_id'=>$company->id,'name'=>'Partner One']);
        $second=Workspace::create(['company_id'=>$company->id,'name'=>'Partner Two']);
        return [$company,$first,$second];
    }

    private function member(Company $company, Workspace $workspace, string $role, ?CompanyMembership $manager=null): array
    {
        $user=User::factory()->create();
        $membership=CompanyMembership::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,
            'user_id'=>$user->id,'role'=>$role,'manager_membership_id'=>$manager?->id]);
        return [$user,$membership];
    }

    private function sampleSession(Company $company, Workspace $workspace, CompanyMembership $student): AttendanceSession
    {
        $day=AttendanceDay::create(['company_id'=>$company->id,'workspace_id'=>$workspace->id,'membership_id'=>$student->id,
            'work_date'=>'2026-10-14','status'=>'incomplete']);
        return AttendanceSession::create(['attendance_day_id'=>$day->id,'company_id'=>$company->id,
            'membership_id'=>$student->id,'mode'=>'office',
            'checked_in_at'=>'2026-10-14 04:05:00','checked_out_at'=>null,'is_open'=>true]);
    }

    public function test_partner_corrects_incomplete_student_session_and_keeps_immutable_history(): void
    {
        [$company,$workspace]=$this->setupFirm();
        [$partner,$partnerMember]=$this->member($company,$workspace,'partner');
        [$student,$studentMember]=$this->member($company,$workspace,'student');
        $session=$this->sampleSession($company,$workspace,$studentMember);
        Carbon::setTestNow('2026-10-15 00:00:00');
        try {
            $this->actingAs($partner)->get('/app/c/rac/attendance/corrections')->assertOk()->assertSee('Session #'.$session->id);
            $this->actingAs($partner)->post('/app/c/rac/attendance/corrections/'.$session->id,[
                'checked_in_local'=>'2026-10-14T10:05',
                'checked_out_local'=>'2026-10-14T18:00',
                'reason'=>'Student forgot to check out from office',
            ])->assertRedirect();
            $this->assertFalse($session->fresh()->is_open);
            $this->assertSame('on_time',AttendanceDay::firstOrFail()->status);
            $this->assertDatabaseCount('attendance_corrections',1);
            $row=DB::table('attendance_corrections')->first();
            $this->assertSame($partnerMember->id,$row->actor_membership_id);
            $this->assertStringContainsString('forgot to check out',$row->reason);
            $this->assertNull($row->old_checked_out_at);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_student_and_cross_partner_reviewer_cannot_alter_attendance(): void
    {
        [$company,$one,$two]=$this->setupFirm();
        [$student,$studentMember]=$this->member($company,$one,'student');
        [$otherPartner]=$this->member($company,$two,'partner');
        $session=$this->sampleSession($company,$one,$studentMember);
        $data=['checked_in_local'=>'2026-10-14T10:05','checked_out_local'=>'2026-10-14T18:00',
            'reason'=>'Signed supervisor correction'];
        $this->actingAs($student)->post('/app/c/rac/attendance/corrections/'.$session->id,$data)->assertForbidden();
        $this->actingAs($otherPartner)->post('/app/c/rac/attendance/corrections/'.$session->id,$data)->assertForbidden();
        $this->assertDatabaseCount('attendance_corrections',0);
        $this->assertTrue($session->fresh()->is_open);
    }

    public function test_reason_must_be_provided_and_date_cannot_move_to_another_day(): void
    {
        [$company,$workspace]=$this->setupFirm();
        [$partner]=$this->member($company,$workspace,'partner');
        [$student,$studentMembership]=$this->member($company,$workspace,'student');
        $session=$this->sampleSession($company,$workspace,$studentMembership);
        $this->actingAs($partner)->post('/app/c/rac/attendance/corrections/'.$session->id,[
            'checked_in_local'=>'2026-10-14T10:05',
            'checked_out_local'=>'2026-10-14T18:00','reason'=>'short',
        ])->assertSessionHasErrors('reason');
        Carbon::setTestNow('2026-10-16 00:00:00');
        try {
            $this->actingAs($partner)->post('/app/c/rac/attendance/corrections/'.$session->id,[
                'checked_in_local'=>'2026-10-15T10:05',
                'checked_out_local'=>'2026-10-15T18:00',
                'reason'=>'Supervisor confirmed the time change',
            ])->assertSessionHasErrors('checked_in_local');
        } finally {
            Carbon::setTestNow();
        }
        $this->assertDatabaseCount('attendance_corrections',0);
    }
}
