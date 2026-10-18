<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\SaasBilling;
use App\Support\CustomDomainDnsVerifier;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase13SaasBillingSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        config()->set('app.url','http://localhost');
        $this->withoutMiddleware(ThrottleRequests::class);
        Carbon::setTestNow('2026-10-18 13:00:00 Asia/Dhaka');
        CarbonImmutable::setTestNow('2026-10-18 13:00:00 Asia/Dhaka');
    }

    protected function tearDown():void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function operator():User
    {
        $user=User::factory()->create();
        $user->forceFill(['is_platform_admin'=>true])->save();
        return $user;
    }

    private function firm(string $slug):array
    {
        $id=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Firm '.$slug,
            'timezone'=>'Asia/Dhaka','office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$id,'slug'=>'partner-one','name'=>'Partner One',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $partner=User::factory()->create();
        DB::table('memberships')->insert([
            'company_id'=>$id,'workspace_id'=>$ws,'user_id'=>$partner->id,
            'role'=>'partner','active'=>true,'monthly_allowance'=>'0.00',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return [$id,$ws,$partner];
    }

    private function plan(string $code='pro',string $amount='1000.00',int $domain=1):int
    {
        $this->post('/platform/app/billing/plans',[
            'code'=>$code,'name'=>'Professional '.$code,
            'monthly_bdt'=>$amount,'max_partner_workspaces'=>50,
            'max_members'=>500,'custom_domain_enabled'=>$domain,
        ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('saas_plans')->where('code',$code)->value('id');
    }

    private function subscribe(int $company,int $plan):void
    {
        $this->post("/platform/app/companies/$company/subscription",[
            'plan_id'=>$plan,'grace_days'=>7,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    private function invoice(int $company,int $months=1):int
    {
        $this->post("/platform/app/companies/$company/invoices",[
            'months'=>$months,
        ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('saas_invoices')->where('company_id',$company)
            ->orderByDesc('id')->value('id');
    }

    private function record(int $company,int $invoice,string $reference,
        string $amount):int
    {
        $this->post("/platform/app/companies/$company/invoices/$invoice/payments",[
            'method'=>'bkash','reference'=>$reference,'amount'=>$amount,
        ])->assertRedirect();
        // Prior deliberate validation-error requests can leave an empty Laravel
        // error bag; database persistence is the authoritative new-request check.
        $id=DB::table('saas_payments')->where('reference',$reference)->value('id');
        $this->assertNotNull($id,'Accepted payment evidence must be persisted as pending.');
        $this->assertDatabaseHas('saas_payments',['id'=>$id,'status'=>'pending']);
        return (int)$id;
    }

    private function approve(int $company,int $payment):\Illuminate\Testing\TestResponse
    {
        return $this->post("/platform/app/companies/$company/payments/$payment/decide",[
            'decision'=>'approve','operator_note'=>'Externally verified against merchant settlement.',
            'verified_externally'=>1,
        ]);
    }

    public function test_billing_is_platform_mfa_and_role_restricted_with_no_tenant_data_access():void
    {
        [$firm,, $partner]=$this->firm('alpha');
        $this->get('/platform/app/billing')->assertRedirect();
        $this->actingAs($partner)->get('/platform/app/billing')->assertForbidden();
        $this->post('/platform/app/billing/plans',[
            'code'=>'unauthorized','name'=>'Not allowed','monthly_bdt'=>500,
            'max_partner_workspaces'=>2,'max_members'=>5,'custom_domain_enabled'=>0,
        ])->assertForbidden();
        $this->actingAs($this->operator())->get('/platform/app/billing')
            ->assertOk()->assertSeeText('Subscription Administration');
        $this->get("/platform/app/companies/$firm/billing")->assertOk();
    }

    public function test_existing_firms_are_unbilled_without_forced_suspension_or_invoice():void
    {
        [$firm,, $partner]=$this->firm('legacy');
        $this->actingAs($partner)->get('/c/legacy/app/subscription')
            ->assertOk()->assertSeeText('Unbilled Legacy Access');
        $this->assertDatabaseHas('companies',['id'=>$firm,'active'=>true]);
        $this->assertDatabaseCount('saas_invoices',0);
        $this->assertTrue(SaasBilling::domainAllowed($firm));
    }

    public function test_plans_snapshot_prices_and_reject_double_subscription_and_open_invoices():void
    {
        [$firm]=$this->firm('billing-one');
        $this->actingAs($this->operator());
        $plan=$this->plan();
        $this->subscribe($firm,$plan);
        $invoice=$this->invoice($firm,3);
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'total_amount'=>'3000.00','currency'=>'BDT',
            'months'=>3,'paid_amount'=>'0.00','status'=>'pending',
        ]);
        $this->assertDatabaseHas('companies',['id'=>$firm,'active'=>true]);
        $this->post("/platform/app/companies/$firm/invoices",['months'=>1])
            ->assertSessionHasErrors('months');
        $this->post("/platform/app/companies/$firm/subscription",[
            'plan_id'=>$plan,'grace_days'=>7,
        ])->assertSessionHasErrors('plan_id');
        $this->assertDatabaseCount('saas_invoices',1);
    }

    public function test_partial_payments_are_transaction_safe_and_extend_only_on_full_settlement():void
    {
        [$firm]=$this->firm('billing-two');
        $this->actingAs($this->operator());
        $plan=$this->plan();
        $this->subscribe($firm,$plan);
        $invoice=$this->invoice($firm);
        $first=$this->record($firm,$invoice,'BKASH-UNIQUE-0001','600.00');
        $this->assertDatabaseHas('saas_payments',['id'=>$first,'status'=>'pending']);
        $this->assertDatabaseHas('saas_subscriptions',['company_id'=>$firm,'status'=>'pending']);
        $this->approve($firm,$first)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'paid_amount'=>'600.00','status'=>'partial',
        ]);
        $this->assertNull(DB::table('saas_subscriptions')
            ->where('company_id',$firm)->value('current_period_end'));
        $this->approve($firm,$first)->assertSessionHasErrors('decision');
        $this->post("/platform/app/companies/$firm/invoices/$invoice/payments",[
            'method'=>'bkash','reference'=>'BKASH-UNIQUE-0001','amount'=>'100.00',
        ])->assertSessionHasErrors('reference');
        $second=$this->record($firm,$invoice,'BKASH-UNIQUE-0002','400.00');
        $this->approve($firm,$second)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'paid_amount'=>'1000.00','status'=>'paid',
        ]);
        $this->assertSame('active',SaasBilling::effectiveStatus(
            DB::table('saas_subscriptions')->where('company_id',$firm)->first()));
        $this->assertDatabaseHas('companies',['id'=>$firm,'active'=>true]);
        $this->assertDatabaseCount('saas_payments',2);
        $this->assertDatabaseHas('platform_control_events',[
            'company_id'=>$firm,'action'=>'billing.payment.approved',
        ]);
    }

    public function test_no_overpayment_even_when_another_payment_is_pending_and_reject_allows_retry():void
    {
        [$firm]=$this->firm('billing-three');
        $this->actingAs($this->operator());
        $plan=$this->plan();
        $this->subscribe($firm,$plan);
        $invoice=$this->invoice($firm);
        $first=$this->record($firm,$invoice,'BANK-REF-0040','800.00');
        $this->post("/platform/app/companies/$firm/invoices/$invoice/payments",[
            'method'=>'bank','reference'=>'BANK-REF-0041','amount'=>'500',
        ])->assertSessionHasErrors('amount');
        $this->post("/platform/app/companies/$firm/payments/$first/decide",[
            'decision'=>'reject','operator_note'=>'No funds found in matching settlement.',
        ])->assertRedirect();
        $this->assertDatabaseHas('saas_payments',['id'=>$first,'status'=>'rejected']);
        // The deliberate overpayment failure flashes an error bag; clear only
        // the test session before asserting a fresh successful request.
        $this->withSession(['errors'=>new \Illuminate\Support\ViewErrorBag()]);
        $retry=$this->record($firm,$invoice,'BANK-REF-0042','1000.00');
        $this->post("/platform/app/companies/$firm/payments/$retry/decide",[
            'decision'=>'approve','operator_note'=>'Unchecked, unauthorized settlement.',
        ])->assertSessionHasErrors('verified_externally');
        $this->approve($firm,$retry)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('saas_payments',['id'=>$first,'status'=>'rejected']);
        $this->assertDatabaseHas('saas_payments',['id'=>$retry,'status'=>'approved']);
    }

    public function test_next_cycle_renewal_is_contiguous_and_grace_becomes_overdue_without_suspension():void
    {
        [$firm]=$this->firm('billing-four');
        $this->actingAs($this->operator());
        $plan=$this->plan();
        $this->subscribe($firm,$plan);
        $first=$this->invoice($firm);
        $this->approve($firm,$this->record($firm,$first,'RENEW-REF-0001','1000.00'))
            ->assertRedirect();
        $prev=DB::table('saas_invoices')->find($first);
        $next=$this->invoice($firm,3);
        $row=DB::table('saas_invoices')->find($next);
        $this->assertSame($prev->period_end,$row->period_start);
        $this->assertSame(3,$row->months);
        $this->approve($firm,$this->record($firm,$next,'RENEW-REF-0002','3000.00'))
            ->assertRedirect()->assertSessionHasNoErrors();
        $sub=DB::table('saas_subscriptions')->where('company_id',$firm)->first();
        $this->assertSame($row->period_end,$sub->current_period_end);
        CarbonImmutable::setTestNow(CarbonImmutable::parse($row->period_end)->addDays(2));
        $this->assertSame('grace',SaasBilling::effectiveStatus($sub));
        CarbonImmutable::setTestNow(CarbonImmutable::parse($row->period_end)->addDays(8));
        $this->assertSame('overdue',SaasBilling::effectiveStatus($sub));
        $this->assertDatabaseHas('companies',['id'=>$firm,'active'=>true]);
    }

    public function test_partner_only_reads_own_company_invoices_without_payment_reference():void
    {
        [$firm,,$partner]=$this->firm('tenant-a');
        [$other,,$foreign]=$this->firm('tenant-b');
        $this->actingAs($this->operator());
        $plan=$this->plan();
        $this->subscribe($firm,$plan);
        $invoice=$this->invoice($firm);
        $this->record($firm,$invoice,'PRIVATE-TRANSACTION-124','500.00');
        $this->actingAs($foreign)->get('/c/tenant-b/app/subscription')
            ->assertOk()->assertDontSeeText('Professional pro');
        $this->actingAs($partner)->get('/c/tenant-a/app/subscription')
            ->assertOk()->assertSeeText('Professional pro')
            ->assertDontSeeText('PRIVATE-TRANSACTION-124')
            ->assertSeeText('DW-ERP-');
        $this->get('/platform/app/billing')->assertForbidden();
        $this->assertDatabaseCount('saas_invoices',1);
    }

    public function test_plan_capacity_enforced_for_platform_partner_and_tenant_invitations():void
    {
        \Illuminate\Support\Facades\Mail::fake();
        [$firm,,$partner]=$this->firm('seat-limit');
        $this->actingAs($this->operator());
        $this->post('/platform/app/billing/plans',[
            'code'=>'one_seat','name'=>'Single Seat Plan','monthly_bdt'=>'250.00',
            'max_partner_workspaces'=>1,'max_members'=>1,'custom_domain_enabled'=>0,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $plan=(int)DB::table('saas_plans')->where('code','one_seat')->value('id');
        $this->subscribe($firm,$plan);
        $this->post("/platform/app/companies/$firm/partners",[
            'workspace_slug'=>'partner-two','workspace_name'=>'Partner Two',
            'partner_name'=>'Another Business Partner',
            'partner_email'=>'another_partner@example.test',
        ])->assertSessionHasErrors('workspace_slug');
        $this->post("/platform/app/companies/$firm/members",[
            'workspace_id'=>DB::table('workspaces')->where('company_id',$firm)->value('id'),
            'name'=>'Additional Audit Student',
            'email'=>'blocked-student@example.test','role'=>'student',
        ])->assertSessionHasErrors('email');
        $this->actingAs($partner)->post('/c/seat-limit/app/team/invite',[
            'name'=>'A New Audit Student','email'=>'seat-full@example.test',
            'role'=>'student','monthly_allowance'=>'8000',
        ])->assertSessionHasErrors('email');
        $this->assertDatabaseCount('memberships',1);
        $this->assertDatabaseCount('workspaces',1);
    }

    public function test_retired_plan_still_allows_renewal_for_existing_subscriber():void
    {
        [$firm]=$this->firm('retained');
        $this->actingAs($this->operator());
        $plan=$this->plan('v1');
        $this->subscribe($firm,$plan);
        $this->post("/platform/app/billing/plans/$plan/availability",[
            'available'=>0,
        ])->assertRedirect();
        $invoice=$this->invoice($firm);
        $this->assertDatabaseHas('saas_invoices',[
            'id'=>$invoice,'total_amount'=>'1000.00','status'=>'pending',
        ]);
        $this->post("/platform/app/companies/$firm/subscription",[
            'plan_id'=>$plan,'grace_days'=>7,
        ])->assertSessionHasErrors('plan_id');
    }

    public function test_unfunded_or_no_domain_plan_prevents_new_domain_activation_without_disabling_existing():void
    {
        [$firm]=$this->firm('domain-firm');
        $this->actingAs($this->operator());
        $plan=$this->plan('basic','1000.00',0);
        $this->subscribe($firm,$plan);
        DB::table('company_custom_domains')->insert([
            'company_id'=>$firm,'hostname'=>'erp.mydomain.example',
            'verification_token'=>str_repeat('a',48),
            'verified_at'=>now(),'tls_approved_at'=>now(),'active'=>false,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $checker=new class extends CustomDomainDnsVerifier {
            public function verified(string $hostname,string $token):bool{return true;}
        };
        $this->app->instance(CustomDomainDnsVerifier::class,$checker);
        $this->post("/platform/app/companies/$firm/custom-domain/activate",[
            'confirmation_hostname'=>'erp.mydomain.example',
        ])->assertSessionHasErrors('confirmation_hostname');
        $invoice=$this->invoice($firm);
        $this->approve($firm,$this->record($firm,$invoice,'DOMAIN-PAYMENT-100','1000.00'))
            ->assertRedirect();
        $this->post("/platform/app/companies/$firm/custom-domain/activate",[
            'confirmation_hostname'=>'erp.mydomain.example',
        ])->assertSessionHasErrors('confirmation_hostname');
        $this->assertDatabaseHas('company_custom_domains',['company_id'=>$firm,'active'=>false]);
    }
}
