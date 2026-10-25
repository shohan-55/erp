<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase14TenantRelationshipIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function company():int
    {
        return DB::table('companies')->insertGetId([
            'slug'=>'phase14-firm','name'=>'PRIVATE EXAMPLE ORGANIZATION',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_clean_migrated_saas_schema_is_relationship_consistent_and_read_only():void
    {
        $company=$this->company();
        $this->assertSame(0,Artisan::call('erp:tenant:check',['--json'=>true]));
        $report=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('NO_ANOMALIES_DETECTED',$report['decision']);
        $this->assertSame(0,array_sum($report['anomalies']));
        $this->assertDatabaseHas('companies',['id'=>$company,'name'=>'PRIVATE EXAMPLE ORGANIZATION']);
        $this->assertDatabaseCount('tenant_audit_events',0);
    }

    public function test_cross_partner_finance_and_private_documents_are_detected_without_leaking_values():void
    {
        $company=$this->company();
        $ws=[];
        foreach(['one','two'] as $slug){
            $ws[$slug]=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'slug'=>$slug,'name'=>'Practice '.$slug,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
        }
        $person=User::factory()->create(['name'=>'PRIVATE MEMBER PERSON']);
        $member=DB::table('memberships')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$ws['two'],
            'user_id'=>$person->id,'role'=>'student',
            'monthly_allowance'=>'25000.00','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $payroll=DB::table('payroll_runs')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$ws['one'],
            'period_month'=>'2026-10-01','status'=>'draft',
            'policy_snapshot'=>'{}','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('payroll_lines')->insert([
            'payroll_run_id'=>$payroll,'membership_id'=>$member,
            'base_allowance'=>'25000.00','approved_claims'=>'0.00',
            'adjustment'=>'0.00','total_amount'=>'25000.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $client=DB::table('audit_clients')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$ws['two'],
            'code'=>'PRIVATECODE','name'=>'PRIVATE AUDIT CLIENT',
            'created_by'=>$member,'active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $engagement=DB::table('audit_engagements')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$ws['one'],
            'audit_client_id'=>$client,'code'=>'ENG-P14',
            'title'=>'PRIVATE AUDIT TITLE','engagement_type'=>'statutory',
            'starts_on'=>'2026-10-01','ends_on'=>'2026-11-01',
            'budget_hours'=>'10.00','created_by'=>$member,
            'status'=>'open','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('practice_documents')->insert([
            'company_id'=>$company,'workspace_id'=>$ws['one'],
            'uploaded_by'=>$member,'subject_membership_id'=>$member,
            'title'=>'PRIVATE PDF DOCUMENT','category'=>'hr',
            'visibility'=>'subject','original_filename'=>'PRIVATE.pdf',
            'storage_path'=>'private/not-an-actual-file.pdf',
            'sha256'=>str_repeat('e',64),'byte_size'=>5,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $before=DB::table('payroll_lines')->count();
        $this->assertSame(1,Artisan::call('erp:tenant:check',['--json'=>true]));
        $output=Artisan::output();
        $data=json_decode($output,true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('REVIEW_REQUIRED',$data['decision']);
        foreach(['payroll_lines_linked_to_foreign_memberships',
            'engagements_linked_to_foreign_clients',
            'documents_linked_to_foreign_subject_memberships'] as $finding){
            $this->assertSame(1,$data['anomalies'][$finding],$finding);
        }
        foreach(['PRIVATE MEMBER PERSON','PRIVATE AUDIT CLIENT',
            'PRIVATE PDF DOCUMENT','25000.00','PRIVATECODE'] as $secret){
            $this->assertStringNotContainsString($secret,$output);
        }
        $this->assertSame($before,DB::table('payroll_lines')->count());
        $this->assertDatabaseHas('audit_engagements',['id'=>$engagement,'status'=>'open']);
        $this->assertDatabaseCount('tenant_audit_events',0);
    }
}
