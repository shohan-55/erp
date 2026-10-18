<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase12ReportingNoticesDocumentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Multi-operation scenarios advance business state in a single simulated
        // minute; keep production throttling intact while exercising authorization.
        $this->withoutMiddleware(ThrottleRequests::class);
        $today=CarbonImmutable::parse('2026-10-18 13:00:00','Asia/Dhaka');
        Carbon::setTestNow($today);
        CarbonImmutable::setTestNow($today);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function fixture(): array
    {
        $firm=DB::table('companies')->insertGetId([
            'slug'=>'test-practice','name'=>'Independent Firm Ltd',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $members=[];
        foreach(['a','b'] as $code){
            $ws=DB::table('workspaces')->insertGetId([
                'company_id'=>$firm,'slug'=>'partner-'.$code,'name'=>'Practice Unit '.$code,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            foreach(['partner','manager','student'] as $role){
                $user=User::factory()->create(['name'=>$code.'-'.$role]);
                $id=DB::table('memberships')->insertGetId([
                    'company_id'=>$firm,'workspace_id'=>$ws,
                    'user_id'=>$user->id,'role'=>$role,'active'=>true,
                    'manager_membership_id'=>$role==='student'?$members[$code]['manager']->id:null,
                    'monthly_allowance'=>1000,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $members[$code][$role]=(object)['id'=>$id,'user'=>$user,'ws'=>$ws];
            }
        }
        return [$firm,$members];
    }

    private function publish(object $partner,string $audience='students'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($partner->user)->post('/c/test-practice/app/notices',[
            'title'=>'Private Partner Operations Notice',
            'body'=>'Please acknowledge the revised audit work scheduling instructions.',
            'audience'=>$audience,'confirm_publish'=>1,
        ]);
    }

    private function upload(object $partner,string $visibility,?int $subject=null,
        string $title='Private Statutory Audit Working Paper'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($partner->user)->post('/c/test-practice/app/documents',[
            'title'=>$title,'category'=>'audit','visibility'=>$visibility,
            'subject_membership_id'=>$subject,
            'document'=>UploadedFile::fake()->create('working-paper.pdf',15,'application/pdf'),
        ]);
    }

    public function test_partner_student_only_broadcast_is_scoped_and_persisted_in_own_inboxes(): void
    {
        [$firm,$m]=$this->fixture();
        $this->publish($m['a']['partner'])->assertRedirect()->assertSessionHasNoErrors();
        $notice=DB::table('practice_notices')->first();
        $this->assertSame(1,$notice->recipients_count);
        $this->assertDatabaseHas('tenant_notifications',[
            'practice_notice_id'=>$notice->id,'company_id'=>$firm,
            'workspace_id'=>$m['a']['partner']->ws,
            'recipient_membership_id'=>$m['a']['student']->id,
        ]);
        $this->assertDatabaseMissing('tenant_notifications',[
            'practice_notice_id'=>$notice->id,
            'recipient_membership_id'=>$m['a']['manager']->id,
        ]);
        $this->assertDatabaseMissing('tenant_notifications',[
            'practice_notice_id'=>$notice->id,
            'recipient_membership_id'=>$m['b']['student']->id,
        ]);
        $this->actingAs($m['b']['partner']->user)
            ->get('/c/test-practice/app/notices')->assertOk()
            ->assertDontSeeText('Private Partner Operations Notice');
        $this->actingAs($m['a']['student']->user)
            ->get('/c/test-practice/app/notices')->assertForbidden();
        $this->get('/c/test-practice/app/notifications')->assertOk()
            ->assertSeeText('Private Partner Operations Notice');
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'notice.published']);
    }

    public function test_manager_cannot_broadcast_and_mark_all_read_changes_only_own_records(): void
    {
        [, $m]=$this->fixture();
        $this->actingAs($m['a']['manager']->user)->post('/c/test-practice/app/notices',[
            'title'=>'Unauthorized Broadcast',
            'body'=>'Managers cannot broadcast to an entire Partner practice.',
            'audience'=>'everyone','confirm_publish'=>1,
        ])->assertForbidden();
        $this->publish($m['a']['partner'],'everyone')->assertRedirect();
        $this->publish($m['b']['partner'],'everyone')->assertRedirect();
        $own=$m['a']['student']->id;
        $this->actingAs($m['a']['student']->user)
            ->post('/c/test-practice/app/notifications/read-all')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(0,DB::table('tenant_notifications')
            ->where('recipient_membership_id',$own)->whereNull('read_at')->count());
        $this->assertGreaterThan(0,DB::table('tenant_notifications')
            ->where('recipient_membership_id',$m['a']['partner']->id)
            ->whereNull('read_at')->count());
        $this->assertGreaterThan(0,DB::table('tenant_notifications')
            ->where('recipient_membership_id',$m['b']['student']->id)
            ->whereNull('read_at')->count());
    }

    public function test_partner_private_and_subject_document_permissions_do_not_leak(): void
    {
        [, $m]=$this->fixture();
        $this->upload($m['a']['partner'],'partner',null,'Private Partner Only Audit Contract')
            ->assertRedirect()->assertSessionHasNoErrors();
        $a=DB::table('practice_documents')->first();
        Storage::disk('local')->assertExists($a->storage_path);
        $download="/c/test-practice/app/documents/{$a->id}/download";
        $this->actingAs($m['a']['partner']->user)->get($download)->assertOk();
        $this->actingAs($m['a']['student']->user)->get($download)->assertNotFound();
        $this->actingAs($m['a']['manager']->user)->get($download)->assertNotFound();
        $this->actingAs($m['b']['partner']->user)->get($download)->assertNotFound();

        $this->upload($m['a']['partner'],'subject',$m['a']['student']->id,
            'My Private Articleship Confirmation')->assertRedirect()->assertSessionHasNoErrors();
        $doc=DB::table('practice_documents')->orderByDesc('id')->first();
        $own="/c/test-practice/app/documents/{$doc->id}/download";
        $this->actingAs($m['a']['student']->user)->get($own)->assertOk();
        $this->actingAs($m['a']['manager']->user)->get($own)->assertNotFound();
        $this->actingAs($m['b']['student']->user)->get($own)->assertNotFound();
        $this->actingAs($m['a']['student']->user)
            ->get('/c/test-practice/app/documents')->assertOk()
            ->assertSeeText('My Private Articleship Confirmation')
            ->assertDontSeeText('Private Partner Only Audit Contract');
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'document.downloaded']);
    }

    public function test_team_document_visible_to_assigned_manager_but_archive_removes_staff_access(): void
    {
        [, $m]=$this->fixture();
        $this->upload($m['a']['partner'],'team',$m['a']['student']->id,
            'Team Working Paper Checklist')->assertRedirect()->assertSessionHasNoErrors();
        $doc=DB::table('practice_documents')->first();
        $url="/c/test-practice/app/documents/{$doc->id}/download";
        $this->actingAs($m['a']['manager']->user)->get($url)->assertOk();
        $this->actingAs($m['a']['student']->user)->get($url)->assertOk();
        $this->actingAs($m['b']['manager']->user)->get($url)->assertNotFound();
        $this->actingAs($m['a']['student']->user)->post(
            "/c/test-practice/app/documents/{$doc->id}/archive")->assertForbidden();
        $this->actingAs($m['a']['partner']->user)->post(
            "/c/test-practice/app/documents/{$doc->id}/archive")->assertRedirect();
        $this->assertDatabaseHas('practice_documents',['id'=>$doc->id,'archived_by'=>$m['a']['partner']->id]);
        Storage::disk('local')->assertExists($doc->storage_path);
        $this->actingAs($m['a']['manager']->user)->get($url)->assertNotFound();
        $this->actingAs($m['a']['student']->user)->get($url)->assertNotFound();
        $this->actingAs($m['a']['partner']->user)->get($url)->assertOk();
        $this->post("/c/test-practice/app/documents/{$doc->id}/archive")
            ->assertSessionHasErrors('document');
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'document.archived','entity_id'=>$doc->id]);
    }

    public function test_document_upload_rejects_foreign_recipient_and_unsafe_audience_combination(): void
    {
        [, $m]=$this->fixture();
        $this->upload($m['a']['partner'],'subject',$m['b']['student']->id)->assertNotFound();
        $this->upload($m['a']['partner'],'partner',$m['a']['student']->id)
            ->assertSessionHasErrors('subject_membership_id');
        $this->upload($m['a']['partner'],'team',null)
            ->assertSessionHasErrors('subject_membership_id');
        $this->actingAs($m['a']['manager']->user)->post('/c/test-practice/app/documents',[
            'title'=>'Unauthorized Staff Upload','visibility'=>'team','category'=>'hr',
            'subject_membership_id'=>$m['a']['student']->id,
            'document'=>UploadedFile::fake()->create('forbidden.pdf',20,'application/pdf'),
        ])->assertForbidden();
        $this->assertDatabaseCount('practice_documents',0);
    }

    public function test_aggregate_partner_report_is_confidential_and_csv_sanitizes_spreadsheet_formula(): void
    {
        [$firm,$m]=$this->fixture();
        $student=$m['a']['student'];$workspace=$student->ws;
        $student->user->update(['name'=>'=HYPERLINK("https://unsafe.test")']);
        DB::table('attendance_sessions')->insert([
            'company_id'=>$firm,'workspace_id'=>$workspace,'membership_id'=>$student->id,
            'work_date'=>'2026-10-15','work_mode'=>'office',
            'started_at'=>'2026-10-15 04:30:00','ended_at'=>null,
            'late'=>true,'holiday'=>false,'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id'=>$firm,'workspace_id'=>$workspace,
            'membership_id'=>$student->id,'leave_type'=>'sick',
            'date_from'=>'2026-10-13','date_to'=>'2026-10-13','reason'=>'Valid reason',
            'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('conveyance_claims')->insert([
            'company_id'=>$firm,'workspace_id'=>$workspace,'membership_id'=>$student->id,
            'journey_date'=>'2026-10-14','journey_route'=>'Client site','purpose'=>'Audit duty',
            'amount'=>'450.00','approved_amount'=>'390.50','status'=>'approved',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $run=DB::table('payroll_runs')->insertGetId([
            'company_id'=>$firm,'workspace_id'=>$workspace,'period_month'=>'2026-10-01',
            'status'=>'locked','policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id'=>$run,'membership_id'=>$student->id,
            'base_allowance'=>'1000.00','approved_claims'=>'390.50',
            'adjustment'=>'0.00','total_amount'=>'1390.50',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($student->user)->get('/c/test-practice/app/reports/practice')
            ->assertForbidden();
        $this->actingAs($m['a']['manager']->user)
            ->get('/c/test-practice/app/reports/practice')->assertForbidden();
        $this->actingAs($m['b']['partner']->user)
            ->get('/c/test-practice/app/reports/practice?month=2026-10')
            ->assertOk()->assertDontSeeText('1,390.50')
            ->assertDontSeeText('390.50');
        $this->actingAs($m['a']['partner']->user)
            ->get('/c/test-practice/app/reports/practice?month=2026-10')
            ->assertOk()->assertSeeText('390.50')->assertSeeText('1,390.50')
            ->assertSeeText('Open Sessions');
        $response=$this->get('/c/test-practice/app/reports/practice/export?month=2026-10')
            ->assertOk();
        $csv=$response->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK",$csv);
        $this->assertStringContainsString('1390.50',$csv);
        $this->assertStringNotContainsString('b-student',$csv);
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'report.practice.exported']);
    }

    public function test_existing_notification_read_route_and_role_dashboard_remain_operational(): void
    {
        [, $m]=$this->fixture();
        $this->publish($m['a']['partner'])->assertRedirect();
        $id=DB::table('tenant_notifications')->first()->id;
        $this->actingAs($m['b']['student']->user)
            ->post("/c/test-practice/app/notifications/{$id}/read")->assertRedirect();
        $this->assertDatabaseHas('tenant_notifications',['id'=>$id,'read_at'=>null]);
        $this->actingAs($m['a']['student']->user)
            ->post("/c/test-practice/app/notifications/{$id}/read")->assertRedirect();
        $this->assertNotNull(DB::table('tenant_notifications')->find($id)->read_at);
        $this->actingAs($m['a']['partner']->user)->get('/c/test-practice/app/')
            ->assertOk()->assertSeeText('Practice Reports')->assertSeeText('Publish Notices');
        $this->actingAs($m['a']['student']->user)->get('/c/test-practice/app/')
            ->assertOk()->assertSeeText('My Shared Documents')
            ->assertDontSeeText('Publish Notices');
    }
}
