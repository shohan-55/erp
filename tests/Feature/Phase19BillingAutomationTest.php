<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SaasBilling;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class Phase19BillingAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        config()->set('app.url','http://localhost');
        $this->withoutMiddleware(ThrottleRequests::class);
        Carbon::setTestNow('2026-10-20 13:00:00 Asia/Dhaka');
        CarbonImmutable::setTestNow('2026-10-20 13:00:00 Asia/Dhaka');
    }

    protected function tearDown():void
    {
        Carbon::setTestNow();CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin():User
    {
        $u=User::factory()->create();
        $u->forceFill(['is_platform_admin'=>true])->save();
        return $u;
    }

    private function firm(string $slug):array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'CA Practice '.$slug,'slug'=>$slug,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'name'=>'Principal Partner','slug'=>'partner',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $member=User::factory()->create();
        DB::table('memberships')->insert([
            'company_id'=>$company,'workspace_id'=>$ws,'user_id'=>$member->id,
            'role'=>'partner','active'=>true,'monthly_allowance'=>'0.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('company','ws','member','slug');
    }

    private function plan(string $code='phase19-plan',array $values=[]):int
    {
        $this->post('/platform/app/billing/plans',array_merge([
            'code'=>$code,'name'=>'Professional '.$code,
            'monthly_bdt'=>'1000.00',
            'max_partner_workspaces'=>10,'max_members'=>50,
            'custom_domain_enabled'=>1,
            'annual_discount_percent'=>20,'trial_days'=>14,
            'storage_addon_mib'=>100,'storage_addon_monthly_bdt'=>'120.00',
        ],$values))->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('saas_plans')->where('code',$code)->value('id');
    }

    private function subscribe(int $company,int $plan,bool $trial=false):void
    {
        $this->post("/platform/app/companies/$company/subscription",[
            'plan_id'=>$plan,'grace_days'=>7,'start_trial'=>$trial?1:0,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function issue(int $company,int $months):int
    {
        $this->post("/platform/app/companies/$company/invoices",[
            'months'=>$months,
        ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('saas_invoices')->where('company_id',$company)
            ->orderByDesc('id')->value('id');
    }

    private function pay(int $company,int $invoice,string $ref,string $amount):void
    {
        $this->post("/platform/app/companies/$company/invoices/$invoice/payments",[
            'method'=>'bank','reference'=>$ref,'amount'=>$amount,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $payment=(int)DB::table('saas_payments')->where('reference',$ref)->value('id');
        $this->post("/platform/app/companies/$company/payments/$payment/decide",[
            'decision'=>'approve','operator_note'=>'Verified against actual external bank reconciliation.',
            'verified_externally'=>1,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_trial_is_explicit_and_cannot_create_fake_paid_period():void
    {
        $f=$this->firm('trial-example');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($f['company'],$plan,true);
        $sub=DB::table('saas_subscriptions')->where('company_id',$f['company'])->first();
        $this->assertSame('trial',SaasBilling::effectiveStatus($sub));
        $this->assertNull($sub->current_period_end);
        $this->assertDatabaseCount('saas_invoices',0);
        $this->assertDatabaseCount('saas_payments',0);
        // A trial is not a paid subscription and cannot activate a new custom domain.
        $this->assertFalse(SaasBilling::domainAllowed($f['company']));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($sub->trial_ends_at));
        $this->assertSame('pending',SaasBilling::effectiveStatus($sub));
        $this->assertDatabaseHas('companies',['id'=>$f['company'],'active'=>true]);
        $this->actingAs($f['member'])
            ->get('/c/trial-example/app/subscription')->assertOk()
            ->assertSeeText('PENDING')
            ->assertDontSeeText('Externally verified against actual');
    }

    public function test_annual_invoice_snapshots_discount_and_storage_addon_without_charging():void
    {
        $f=$this->firm('annual-price');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($f['company'],$plan);
        $this->post("/platform/app/companies/{$f['company']}/storage-quote",[
            'units'=>2,'reason'=>'Provide extra audit document space on the next paid invoice.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $invoice=$this->issue($f['company'],12);
        // 12 * 1,000 = 12,000; annual discount 20% = 2,400;
        // two add-ons 2 * 120 * 12 = 2,880; total 12,480 BDT.
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'total_amount'=>'12480.00',
            'annual_discount_percent_snapshot'=>20,
            'discount_total_bdt'=>'2400.00',
            'storage_addon_units_snapshot'=>2,
            'storage_addon_mib_snapshot'=>200,
            'storage_addon_total_bdt'=>'2880.00',
            'status'=>'pending','paid_amount'=>'0.00',
        ]);
        $this->assertDatabaseHas('saas_subscriptions',[
            'company_id'=>$f['company'],
            'storage_addon_units'=>0,'pending_storage_addon_units'=>2,
        ]);
        $this->assertDatabaseCount('saas_payments',0);
        $this->post("/platform/app/companies/{$f['company']}/storage-quote",[
            'units'=>0,'reason'=>'Attempted repricing while open bill exists.',
        ])->assertSessionHasErrors('units');
        $this->assertDatabaseCount('saas_invoices',1);
    }

    public function test_addon_provisioning_requires_external_settlement_and_enforces_company_ceiling():void
    {
        $f=$this->firm('vault-added');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($f['company'],$plan);
        $this->post("/platform/app/companies/{$f['company']}/storage-quote",[
            'units'=>2,'reason'=>'Extra client engagement audit documents for the partner.',
        ])->assertRedirect();
        $invoice=$this->issue($f['company'],1);
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'total_amount'=>'1240.00',
        ]);
        $this->post("/platform/app/companies/{$f['company']}/storage-allocate/{$f['ws']}",[
            'target_mib'=>300,'reason'=>'Attempt to provision pending unpaid storage units.',
        ])->assertSessionHasErrors('target_mib');
        $this->assertDatabaseCount('erp_vault_quotas',0);
        $this->pay($f['company'],$invoice,'STORAGE-SUBSCRIPTION-001','1240.00');
        $this->assertDatabaseHas('saas_subscriptions',[
            'company_id'=>$f['company'],'storage_addon_units'=>2,
            'pending_storage_addon_units'=>null,'status'=>'active',
        ]);
        $this->post("/platform/app/companies/{$f['company']}/storage-allocate/{$f['ws']}",[
            'target_mib'=>301,'reason'=>'Attempt to exceed the purchased workspace allowance.',
        ])->assertSessionHasErrors('target_mib');
        $this->post("/platform/app/companies/{$f['company']}/storage-allocate/{$f['ws']}",[
            'target_mib'=>300,'reason'=>'Allocate purchased storage to the partner vault.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('erp_vault_quotas',[
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'limit_bytes'=>300*1048576,
        ]);
        $this->assertDatabaseHas('platform_control_events',[
            'company_id'=>$f['company'],'action'=>'billing.storage.allocated',
        ]);
    }

    public function test_unpaid_auto_renewal_is_opt_in_idempotent_and_never_suspends_company():void
    {
        $f=$this->firm('renewal-opt-in');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($f['company'],$plan);
        $first=$this->issue($f['company'],1);
        $this->pay($f['company'],$first,'SUBSCRIPTION-FIRST-0001','1000.00');
        $sub=DB::table('saas_subscriptions')->where('company_id',$f['company'])->first();
        Carbon::setTestNow(Carbon::parse($sub->current_period_end)->subDays(5));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($sub->current_period_end)->subDays(5));
        Artisan::call('erp:billing:renewals');
        $this->assertDatabaseCount('saas_invoices',1);
        $this->post("/platform/app/companies/{$f['company']}/renewal-settings",[
            'auto_invoice'=>1,'renewal_months'=>12,
            'reason'=>'Send a quote each year before the existing paid period ends.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        Artisan::call('erp:billing:renewals',['--dry-run'=>true]);
        $this->assertDatabaseCount('saas_invoices',1);
        Artisan::call('erp:billing:renewals');
        Artisan::call('erp:billing:renewals');
        $this->assertDatabaseCount('saas_invoices',2);
        $next=DB::table('saas_invoices')->orderByDesc('id')->firstOrFail();
        $this->assertSame($sub->current_period_end,$next->period_start);
        $this->assertSame(12,(int)$next->months);
        $this->assertSame('9600.00',$next->total_amount);
        $this->assertSame('pending',$next->status);
        $this->assertDatabaseCount('saas_payments',1);
        $this->assertDatabaseCount('saas_billing_automation_events',1);
        $this->assertDatabaseHas('companies',['id'=>$f['company'],'active'=>true]);
    }

    public function test_paid_quota_cannot_cross_company_or_shrink_under_reservation():void
    {
        $a=$this->firm('storage-a');
        $b=$this->firm('storage-b');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($a['company'],$plan);
        $first=$this->issue($a['company'],1);
        $this->pay($a['company'],$first,'CAPACITY-FIRST-0001','1000.00');
        $this->post("/platform/app/companies/{$a['company']}/storage-allocate/{$b['ws']}",[
            'target_mib'=>200,'reason'=>'Cross-company quota allocation must be denied.',
        ])->assertNotFound();
        $this->assertDatabaseCount('erp_vault_quotas',0);
        $this->post("/platform/app/companies/{$a['company']}/storage-allocate/{$a['ws']}",[
            'target_mib'=>100,'reason'=>'Allocate the basic workspace quota.',
        ])->assertRedirect();
        DB::table('erp_vault_quotas')->where('company_id',$a['company'])
            ->where('workspace_id',$a['ws'])
            ->update(['used_bytes'=>90*1048576,'reserved_bytes'=>8*1048576]);
        // Minimum allocatable is base 100MiB, so to test shrink guard
        // reserve above base as a preexisting quota mismatch.
        DB::table('erp_vault_quotas')->where('company_id',$a['company'])
            ->where('workspace_id',$a['ws'])
            ->update(['reserved_bytes'=>15*1048576]);
        $this->post("/platform/app/companies/{$a['company']}/storage-allocate/{$a['ws']}",[
            'target_mib'=>100,'reason'=>'Cannot shrink a quota below already reserved content.',
        ])->assertSessionHasErrors('target_mib');
        $this->assertDatabaseHas('erp_vault_quotas',[
            'workspace_id'=>$a['ws'],'limit_bytes'=>100*1048576,
        ]);
    }

    public function test_platform_only_controls_cannot_be_changed_by_tenant_partner():void
    {
        $f=$this->firm('tenant-readonly');
        $this->actingAs($this->admin());
        $plan=$this->plan();
        $this->subscribe($f['company'],$plan,true);
        $this->actingAs($f['member']);
        $this->get('/c/tenant-readonly/app/subscription')
            ->assertOk()->assertSeeText('Subscription Status');
        $this->post("/platform/app/companies/{$f['company']}/renewal-settings",[
            'auto_invoice'=>1,'renewal_months'=>1,'reason'=>'Unauthorized tenant policy change.',
        ])->assertForbidden();
        $this->post("/platform/app/companies/{$f['company']}/storage-quote",[
            'units'=>10,'reason'=>'Unauthorized customer storage provisioning.',
        ])->assertForbidden();
        $this->assertDatabaseHas('saas_subscriptions',[
            'company_id'=>$f['company'],'auto_invoice'=>false,
            'storage_addon_units'=>0,
        ]);
    }
}
