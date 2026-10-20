<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\WorkspaceAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase10ProxyAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug='proxy-firm'): array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Proxy Test Practice',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'one','name'=>'Partner One',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $users=[];
        foreach (['partner','manager','student'] as $role) {
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$ws,
                'user_id'=>$user->id,'role'=>$role,'monthly_allowance'=>'5000.00',
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $users[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$users['student']->id)
            ->update(['manager_membership_id'=>$users['manager']->id]);
        return compact('company','ws','users','slug');
    }

    private function permission(array $f, array $codes=['attendance.proxy']): int
    {
        return DB::table('workspace_access_grants')->insertGetId([
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'membership_id'=>$f['users']['manager']->id,
            'access_role'=>'moderator','permissions'=>json_encode($codes),
            'granted_by'=>$f['users']['partner']->id,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    private function checkIn(array $f,array $extras=[])
    {
        return $this->post('/c/'.$f['slug'].'/app/attendance/proxy/check-in',[
            'membership_id'=>$f['users']['student']->id,
            'work_mode'=>'office',
            'reason'=>'Student device was offline and the supervisor verified arrival.',
            ...$extras,
        ]);
    }

    private function checkOut(array $f,array $extras=[])
    {
        return $this->post('/c/'.$f['slug'].'/app/attendance/proxy/check-out',[
            'membership_id'=>$f['users']['student']->id,
            'reason'=>'Student device remained unavailable after finishing client duty.',
            ...$extras,
        ]);
    }

    public function test_explicit_permission_required_and_pending_proxy_has_no_fake_student_location(): void
    {
        $f=$this->firm();
        $this->actingAs($f['users']['manager']->user)->checkIn($f)->assertForbidden();
        $this->permission($f);
        $this->checkIn($f)->assertRedirect()->assertSessionHasNoErrors();
        $s=DB::table('attendance_sessions')->firstOrFail();
        $this->assertSame('pending',$s->proxy_status);
        $this->assertSame((int)$f['users']['manager']->id,(int)$s->proxy_started_by);
        $this->assertSame((int)$f['users']['student']->id,(int)$s->membership_id);
        $this->assertNotNull($s->proxy_start_reason);
        $this->assertDatabaseCount('attendance_locations',0);
        $company=DB::table('companies')->find($f['company']);
        $workspace=DB::table('workspaces')->find($f['ws']);
        $student=DB::table('memberships')->find($f['users']['student']->id);
        $preview=\App\Support\AttendanceReconciliation::forDate(
            $company,$workspace,$student,$s->work_date
        );
        $this->assertSame('proxy_pending',$preview['rows']->first()->status_key);
        $this->assertSame(0,$preview['rows']->first()->session_count);
        $this->assertDatabaseHas('tenant_audit_events',[
            'entity_id'=>$s->id,'action'=>'attendance.proxy.check_in',
        ]);
        $this->checkIn($f)->assertSessionHasErrors('membership_id');
        $this->checkOut($f)->assertRedirect()->assertSessionHasNoErrors();
        $s=DB::table('attendance_sessions')->firstOrFail();
        $this->assertSame('pending',$s->proxy_status);
        $this->assertNotNull($s->ended_at);
        $this->assertNotNull($s->proxy_end_reason);
        $this->assertDatabaseCount('attendance_locations',0);
        $this->actingAs($f['users']['partner']->user)
            ->post('/c/proxy-firm/app/attendance/proxy/'.$s->id.'/decision',[
                'decision'=>'verify',
                'reason'=>'Verified attendance with the office supervisor and work diary.',
            ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('attendance_sessions',[
            'id'=>$s->id,'proxy_status'=>'verified',
            'proxy_verified_by'=>$f['users']['partner']->id,
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'entity_id'=>$s->id,'action'=>'attendance.proxy.verified',
        ]);
        $accepted=\App\Support\AttendanceReconciliation::forDate(
            $company,$workspace,$student,$s->work_date
        );
        $this->assertSame(1,$accepted['rows']->first()->session_count);
        $this->post('/c/proxy-firm/app/attendance/proxy/'.$s->id.'/decision',[
            'decision'=>'reject','reason'=>'Trying to change an already verified session.',
        ])->assertNotFound();
    }

    public function test_location_spoof_and_permission_revocation_fail_closed(): void
    {
        $f=$this->firm();
        $id=$this->permission($f);
        $this->actingAs($f['users']['manager']->user)->checkIn($f,[
            'location_latitude'=>23.1,'location_longitude'=>90.1,
            'location_accuracy'=>20,'location_consent'=>1,
        ])->assertSessionHasErrors('location');
        $this->assertDatabaseCount('attendance_sessions',0);
        DB::table('workspace_access_grants')->where('id',$id)
            ->update(['revoked_at'=>now(),'revoked_by'=>$f['users']['partner']->id]);
        $this->checkIn($f)->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions',0);
    }

    public function test_cross_firm_access_and_manager_scope_are_enforced(): void
    {
        $a=$this->firm('proxy-a');
        $b=$this->firm('proxy-b');
        $this->permission($a);
        $this->actingAs($a['users']['manager']->user)
            ->checkIn($a,['membership_id'=>$b['users']['student']->id])->assertNotFound();
        $this->actingAs($b['users']['partner']->user)
            ->get('/c/proxy-a/app/attendance')->assertForbidden();
        $this->assertDatabaseCount('attendance_sessions',0);
    }

    public function test_partner_can_reject_open_proxy_without_fabricated_student_punch(): void
    {
        $f=$this->firm();
        $this->permission($f);
        $this->actingAs($f['users']['manager']->user)->checkIn($f)->assertRedirect();
        $session=DB::table('attendance_sessions')->firstOrFail();
        $this->actingAs($f['users']['partner']->user)
            ->post("/c/proxy-firm/app/attendance/proxy/{$session->id}/decision",[
                'decision'=>'verify','reason'=>'Insufficient attendance evidence to verify the open record.',
            ])->assertSessionHasErrors('decision');
        $this->post("/c/proxy-firm/app/attendance/proxy/{$session->id}/decision",[
            'decision'=>'reject','reason'=>'Supervisor confirmed entry was created in error.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $s=DB::table('attendance_sessions')->find($session->id);
        $this->assertSame('rejected',$s->proxy_status);
        $this->assertNotNull($s->ended_at);
        $this->assertDatabaseCount('attendance_locations',0);
    }

    public function test_full_legacy_grant_does_not_silently_gain_proxy_authorization(): void
    {
        $f=$this->firm();
        $id=$this->permission($f,[]);
        DB::table('workspace_access_grants')->where('id',$id)
            ->update(['delegation_mode'=>'full']);
        $actor=DB::table('memberships')->find($f['users']['manager']->id);
        $this->assertFalse(WorkspaceAccess::allows($actor,'attendance.proxy'));
        $this->actingAs($f['users']['manager']->user)->checkIn($f)->assertForbidden();
    }
}
