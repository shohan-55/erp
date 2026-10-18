<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class Phase14ProductionReleaseGatesTest extends TestCase
{
    use RefreshDatabase;

    private function firm(string $slug):int
    {
        return DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Secret Internal Company',
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00',
            'office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_phase14_preflight_has_explicit_admin_and_partner_guard_coverage():void
    {
        // Non-production test env MUST still return NO_GO. Verify individual
        // security guards, not a misleading full release acceptance.
        $this->assertSame(1,Artisan::call('erp:release:check',['--json'=>true]));
        $response=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('NO_GO',$response['release_decision']);
        $this->assertFalse($response['checks']['production_environment']);
        foreach([
            'erp.platform.billing.payment.decide'=>
                ['platform.host','auth','platform.admin','mfa.verified'],
            'erp.platform.domains.activate'=>
                ['platform.host','auth','platform.admin','mfa.verified'],
            'erp.payroll.payslip'=>
                ['tenant.host','auth','tenant.member','mfa.verified','student.features'],
            'erp.documents.download'=>
                ['tenant.host','auth','tenant.member','mfa.verified','student.features'],
            'erp.timesheets.decide'=>
                ['tenant.host','auth','tenant.member','mfa.verified','student.features'],
            'erp.subscription'=>
                ['tenant.host','auth','tenant.member','mfa.verified','student.features'],
        ] as $name=>$required){
            $route=Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            foreach($required as $guard){
                $this->assertContains($guard,$route->gatherMiddleware(),$name);
                $key='guard_'.str_replace('.','_',$name).'_'.str_replace('.','_',$guard);
                $this->assertTrue($response['checks'][$key]??false,$key);
            }
        }
    }

    public function test_database_gate_requires_every_phase_thirteen_table_and_migration():void
    {
        $this->assertSame(0,Artisan::call('erp:db:check',['--json'=>true]));
        $output=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('SCHEMA_READY',$output['decision']);
        foreach([
            'saas_invoices_table_present','audit_timesheets_table_present',
            'private_documents_table_present','all_release_migrations_applied',
        ] as $check)$this->assertTrue($output['checks'][$check],$check);

        $n=DB::table('migrations')->where('migration',
            '2026_10_18_120000_create_saas_billing')->delete();
        $this->assertSame(1,$n);
        $this->assertSame(1,Artisan::call('erp:db:check',['--json'=>true]));
        $missing=json_decode(Artisan::output(),true,512,JSON_THROW_ON_ERROR);
        $this->assertSame('SCHEMA_NOT_READY',$missing['decision']);
        $this->assertContains('all_release_migrations_applied',$missing['failed']);
    }

    public function test_clean_saas_integrity_check_is_read_only_and_secret_free():void
    {
        $company=$this->firm('empty-billing');
        $before=DB::table('platform_control_events')->count();
        $this->assertSame(0,Artisan::call('erp:billing:check',['--json'=>true]));
        $result=Artisan::output();
        $this->assertStringContainsString('NO_ANOMALIES_DETECTED',$result);
        $this->assertStringNotContainsString('Secret Internal Company',$result);
        $this->assertSame($before,DB::table('platform_control_events')->count());
        $this->assertDatabaseHas('companies',['id'=>$company,'active'=>true]);
    }

    public function test_billing_integrity_gate_flags_inconsistent_settlement_without_mutation_or_pii():void
    {
        $company=$this->firm('fake-company');
        $owner=User::factory()->create(['name'=>'PRIVATE PAYROLL PERSON']);
        $plan=DB::table('saas_plans')->insertGetId([
            'code'=>'test-audit','name'=>'Private Growth Plan','monthly_bdt'=>'1000.00',
            'max_partner_workspaces'=>5,'max_members'=>30,
            'custom_domain_enabled'=>true,'available'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $subscription=DB::table('saas_subscriptions')->insertGetId([
            'company_id'=>$company,'plan_id'=>$plan,'status'=>'active',
            'current_period_start'=>'2026-10-01','current_period_end'=>'2026-11-01',
            'grace_days'=>7,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $invoice=DB::table('saas_invoices')->insertGetId([
            'company_id'=>$company,'subscription_id'=>$subscription,'plan_id'=>$plan,
            'invoice_number'=>'TEST-PRIVATE-001','plan_name_snapshot'=>'Private Growth Plan',
            'currency'=>'BDT','months'=>1,
            'period_start'=>'2026-10-01','period_end'=>'2026-11-01',
            'total_amount'=>'1000.00','paid_amount'=>'1000.00',
            'status'=>'paid','due_on'=>'2026-10-20','settled_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('saas_payments')->insert([
            'company_id'=>$company,'invoice_id'=>$invoice,'method'=>'bank',
            'reference'=>'PRIVATE-TXN-SECRET-987','amount'=>'800.00',
            'status'=>'approved','recorded_by'=>$owner->id,
            'decided_by'=>null,'decided_at'=>null,'operator_note'=>null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('company_custom_domains')->insert([
            'company_id'=>$company,'hostname'=>'erp.private-example.com',
            'verification_token'=>str_repeat('T',48),
            'verified_at'=>null,'tls_approved_at'=>null,'active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->assertSame(1,Artisan::call('erp:billing:check',['--json'=>true]));
        $result=Artisan::output();
        $this->assertStringContainsString('REVIEW_REQUIRED',$result);
        foreach([
            'invoices_with_paid_amount_mismatch','approved_payments_missing_verification',
            'active_domains_without_dns_or_tls_approval',
        ] as $finding)$this->assertStringContainsString('"'.$finding.'": 1',$result);
        foreach(['PRIVATE-TXN-SECRET-987','PRIVATE PAYROLL PERSON','Secret Internal Company',
            'erp.private-example.com','1000.00'] as $secret){
            $this->assertStringNotContainsString($secret,$result);
        }
        $this->assertDatabaseHas('saas_payments',['invoice_id'=>$invoice,'status'=>'approved']);
        $this->assertDatabaseHas('company_custom_domains',['company_id'=>$company,'active'=>true]);
        $this->assertDatabaseCount('tenant_audit_events',0);
    }
}
