<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OfficialTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OfficialDocumentTemplateAndCertificatesTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $slug='cert-firm'):array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Certified '.$slug,
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'unit','name'=>'Audit Unit',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $p=[];
        foreach(['partner','manager','student','student2'] as $key){
            $role=str_starts_with($key,'student')?'student':$key;
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,'role'=>$role,'active'=>true,
                'manager_membership_id'=>$role==='student'?$p['manager']['id']:null,
                'monthly_allowance'=>0,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $p[$key]=['id'=>$id,'user'=>$user];
        }
        return [$company,$workspace,$p];
    }

    private function profile(int $company,int $workspace,int $member):void
    {
        DB::table('staff_profiles')->insert([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$member,'joined_on'=>'2025-01-10',
            'designation'=>'Audit Associate','employment_status'=>'active',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_approved_template_is_versioned_and_managers_are_read_only():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $form=[
            'template_key'=>'employment_certificate',
            'document_title'=>'Verified Employment Certificate',
            'body_text'=>'This certificate confirms {{employee_name}} works for {{company_name}} from {{joining_date}}, version one.',
            'footer_note'=>'Private ERP evidence only.',
        ];
        $this->actingAs($p['student']['user'])
            ->get('/c/cert-firm/app/settings/document-templates')->assertForbidden();
        $this->actingAs($p['manager']['user'])
            ->get('/c/cert-firm/app/settings/document-templates')->assertOk();
        $this->post('/c/cert-firm/app/settings/document-templates',$form)->assertForbidden();
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/settings/document-templates',$form)->assertRedirect();
        $this->assertDatabaseHas('workspace_document_templates',[
            'company_id'=>$company,'workspace_id'=>$workspace,
            'template_key'=>'employment_certificate','version'=>1,
        ]);
        $this->post('/c/cert-firm/app/settings/document-templates',[
            ...$form,'body_text'=>'Second version for {{employee_name}} at {{company_name}} approved by Partner.',
        ])->assertRedirect();
        $this->assertDatabaseCount('workspace_document_templates',2);
        $rows=DB::table('workspace_document_templates')->orderBy('version')->get();
        $this->assertSame(1,(int)$rows[0]->version);
        $this->assertSame(2,(int)$rows[1]->version);
        $this->assertSame(2,OfficialTemplate::current(
            DB::table('companies')->find($company),
            DB::table('workspaces')->find($workspace),
            'employment_certificate'
        )['version']);
    }

    public function test_unapproved_template_placeholders_are_rejected():void
    {
        [,,$p]=$this->fixture();
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/settings/document-templates',[
                'template_key'=>'salary_certificate',
                'document_title'=>'Suspicious Template',
                'body_text'=>'Hello {{not_a_valid_token}} we cannot allow data access.',
            ])->assertSessionHasErrors('body_text');
        $this->assertDatabaseCount('workspace_document_templates',0);
    }

    public function test_partner_issues_real_employment_word_and_student_reads_only_own_issued_certificate():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->profile($company,$workspace,$p['student']['id']);
        $this->actingAs($p['manager']['user'])
            ->post('/c/cert-firm/app/documents/certificates/'.$p['student']['id'],[
                'kind'=>'employment','purpose'=>'Required for professional membership verification',
            ])->assertForbidden();
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/documents/certificates/'.$p['student']['id'],[
                'kind'=>'employment','purpose'=>'Required for professional membership verification',
            ])->assertRedirect();
        $issue=DB::table('official_document_issues')->first();
        $this->assertSame('employment_'.now('Asia/Dhaka')->format('Ym'),$issue->document_type);
        $snapshot=json_decode($issue->approved_snapshot,true);
        $this->assertSame('Audit Associate',$snapshot['designation']);
        $this->assertSame('2025-01-10',$snapshot['joining_date']);
        $this->assertSame(0,$snapshot['template_version']);
        $this->actingAs($p['student']['user'])
            ->get('/c/cert-firm/app/documents/certificates')->assertOk()
            ->assertSeeText('Employment / Articleship Certificate');
        $this->get('/c/cert-firm/app/documents/certificates/'.$issue->id.'/print')
            ->assertOk()->assertSeeText('Employment / Articleship Certificate');
        $word=$this->get('/c/cert-firm/app/documents/certificates/'.$issue->id.'/word')->assertOk();
        $this->assertStringStartsWith('PK',(string)$word->getContent());
        $this->actingAs($p['student2']['user'])
            ->get('/c/cert-firm/app/documents/certificates/'.$issue->id.'/word')->assertNotFound();
        $this->actingAs($p['manager']['user'])
            ->get('/c/cert-firm/app/documents/certificates/'.$issue->id.'/print')->assertForbidden();
        $this->assertDatabaseCount('official_document_issues',1);
    }

    public function test_salary_certificate_needs_locked_payroll_and_never_exposes_student_pay_to_manager():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->profile($company,$workspace,$p['student']['id']);
        $input=['kind'=>'salary','period'=>'2026-09','purpose'=>'Income certificate requested for lawful verification'];
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/documents/certificates/'.$p['student']['id'],$input)
            ->assertSessionHasErrors('period');

        $run=DB::table('payroll_runs')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'period_month'=>'2026-09-01','status'=>'reviewed',
            'policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id'=>$run,'membership_id'=>$p['student']['id'],
            'base_allowance'=>35000,'approved_claims'=>200,'adjustment'=>0,
            'total_amount'=>35200,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->post('/c/cert-firm/app/documents/certificates/'.$p['student']['id'],$input)
            ->assertSessionHasErrors('period');
        DB::table('payroll_runs')->where('id',$run)->update(['status'=>'locked']);
        $this->post('/c/cert-firm/app/documents/certificates/'.$p['student']['id'],$input)
            ->assertRedirect();
        $cert=DB::table('official_document_issues')->first();
        $snapshot=json_decode($cert->approved_snapshot,true);
        $this->assertSame('35,000.00',$snapshot['salary_base']);
        $this->assertSame($run,$snapshot['payroll_run_id']);
        $this->assertStringContainsString('35,000.00',$snapshot['body_text']);
        $this->actingAs($p['manager']['user'])
            ->get('/c/cert-firm/app/documents/certificates')->assertForbidden();
        $this->actingAs($p['student']['user'])
            ->get('/c/cert-firm/app/documents/certificates/'.$cert->id.'/print')
            ->assertOk()->assertSeeText('35,000.00');
        $this->assertDatabaseCount('payroll_line_adjustments',0);
    }

    public function test_custom_leave_template_issued_once_retains_original_snapshot_after_changes():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $template=[
            'template_key'=>'leave_approval','document_title'=>'Authorized Holiday Request',
            'body_text'=>'Partner approved the leave for {{employee_name}} from {{leave_from}} to {{leave_to}}. Reason: {{leave_reason}}',
            'footer_note'=>'Official controlled document for {{company_name}}.',
        ];
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/settings/document-templates',$template)->assertRedirect();
        $id=DB::table('leave_requests')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$p['student']['id'],
            'leave_type'=>'casual','day_portion'=>'full',
            'date_from'=>'2026-11-02','date_to'=>'2026-11-02',
            'charge_days'=>1,'reason'=>'A family ceremony requiring official time off',
            'status'=>'approved','reviewed_by'=>$p['partner']['id'],
            'reviewed_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->actingAs($p['student']['user'])
            ->get('/c/cert-firm/app/documents/approved-leave/'.$id.'/print')
            ->assertOk()->assertSeeText('Authorized Holiday Request');
        $issue=DB::table('official_document_issues')->first();
        $snapshot=json_decode($issue->approved_snapshot,true);
        $this->assertSame(1,$snapshot['template_version']);
        $this->actingAs($p['partner']['user'])
            ->post('/c/cert-firm/app/settings/document-templates',[
                ...$template,'body_text'=>'A second future version of official leave for {{employee_name}} only.',
            ])->assertRedirect();
        $this->actingAs($p['student']['user'])
            ->get('/c/cert-firm/app/documents/approved-leave/'.$id.'/print')
            ->assertOk()->assertSeeText('Authorized Holiday Request');
        $this->assertDatabaseCount('official_document_issues',1);
        $this->assertSame(1,json_decode(
            DB::table('official_document_issues')->first()->approved_snapshot,true
        )['template_version']);
    }
}
