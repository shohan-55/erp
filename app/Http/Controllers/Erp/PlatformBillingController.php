<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\SaasBilling;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlatformBillingController extends Controller
{
    public function index()
    {
        $plans=DB::table('saas_plans')->orderBy('id')->get();
        $subs=DB::table('saas_subscriptions as s')
            ->join('companies as c','c.id','=','s.company_id')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->select('s.*','c.slug','c.name as company_name','p.name as plan_name')
            ->orderByDesc('s.id')->get();
        $stats=[
            'pending_invoices'=>DB::table('saas_invoices')->whereIn('status',['pending','partial'])->count(),
            'pending_payments'=>DB::table('saas_payments')->where('status','pending')->count(),
            'paid_invoices'=>DB::table('saas_invoices')->where('status','paid')->count(),
        ];
        return response()->view('erp.platform-billing',compact('plans','subs','stats'))
            ->header('Cache-Control','private, no-store');
    }

    public function storePlan(Request $request)
    {
        $d=$request->validate([
            'code'=>['required','regex:/^[a-z][a-z0-9_-]{1,49}$/'],
            'name'=>['required','string','min:3','max:120'],
            'monthly_bdt'=>['required','numeric','between:1,99999999','decimal:0,2'],
            'max_partner_workspaces'=>['required','integer','between:1,500'],
            'max_members'=>['required','integer','between:1,100000'],
            'custom_domain_enabled'=>['required','boolean'],
        ]);
        DB::transaction(function()use($d){
            if(DB::table('saas_plans')->where('code',$d['code'])->exists()){
                throw ValidationException::withMessages(['code'=>'Plan code already exists.']);
            }
            $id=DB::table('saas_plans')->insertGetId([
                ...$d,'available'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            // Global metadata event under a reserved platform action:
            // platform_control_events requires company_id; no synthetic
            // tenant or private workspace is created just for a plan.
            DB::table('saas_plans')->where('id',$id)->first();
        },3);
        return back()->with('success','New subscription plan created. Existing invoice prices never change.');
    }

    public function availability(Request $request,int $planId)
    {
        $d=$request->validate(['available'=>['required','boolean']]);
        $plan=DB::table('saas_plans')->find($planId);
        abort_unless($plan,404);
        DB::table('saas_plans')->where('id',$planId)->update([
            'available'=>(bool)$d['available'],'updated_at'=>now(),
        ]);
        return back()->with('success','Plan availability changed; paid invoice snapshots remain unchanged.');
    }

    public function company(int $companyId)
    {
        $company=DB::table('companies')->where('id',$companyId)->first();
        abort_unless($company,404);
        $plans=DB::table('saas_plans')->where('available',true)->orderBy('monthly_bdt')->get();
        $subscription=DB::table('saas_subscriptions as s')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->where('s.company_id',$companyId)
            ->select('s.*','p.name as plan_name','p.custom_domain_enabled')->first();
        $effective=$subscription?SaasBilling::effectiveStatus($subscription):'unbilled';
        $invoices=DB::table('saas_invoices')
            ->where('company_id',$companyId)->orderByDesc('id')->limit(100)->get();
        $payments=DB::table('saas_payments')->where('company_id',$companyId)
            ->orderByDesc('id')->limit(100)->get();
        $workspaceCount=DB::table('workspaces')->where('company_id',$companyId)->count();
        $membershipCount=DB::table('memberships')->where('company_id',$companyId)->count();
        return response()->view('erp.platform-company-billing',compact(
            'company','plans','subscription','invoices','payments','effective',
            'workspaceCount','membershipCount'
        ))->header('Cache-Control','private, no-store');
    }

    public function subscribe(Request $request,int $companyId)
    {
        $d=$request->validate([
            'plan_id'=>['required','integer','min:1'],
            'grace_days'=>['required','integer','between:1,30'],
        ]);
        DB::transaction(function()use($companyId,$d){
            $company=DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
            abort_unless($company && !$company->archived_at,404);
            $plan=DB::table('saas_plans')->where('id',$d['plan_id'])
                ->where('available',true)->first();
            abort_unless($plan,404);
            $ws=DB::table('workspaces')->where('company_id',$companyId)->count();
            $members=DB::table('memberships')->where('company_id',$companyId)->count();
            if($ws>$plan->max_partner_workspaces || $members>$plan->max_members){
                throw ValidationException::withMessages(['plan_id'=>'This plan does not cover the existing workspaces/memberships.']);
            }
            $sub=DB::table('saas_subscriptions')->where('company_id',$companyId)
                ->lockForUpdate()->first();
            if($sub){
                throw ValidationException::withMessages(['plan_id'=>
                    'An existing subscription cannot be silently repriced. Keep its plan; future plan changes require a separate reviewed migration.']);
            }
            $id=DB::table('saas_subscriptions')->insertGetId([
                'company_id'=>$companyId,'plan_id'=>$plan->id,
                'status'=>'pending','grace_days'=>$d['grace_days'],
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            SaasBilling::audit($companyId,'subscription.created','saas_subscription',$id,[
                'plan_id'=>$plan->id,'grace_days'=>$d['grace_days'],
            ]);
        },3);
        return back()->with('success','Subscription assigned; create a renewal invoice and verify payment separately.');
    }

    public function invoice(Request $request,int $companyId)
    {
        $d=$request->validate(['months'=>['required','integer','in:1,3,6,12']]);
        DB::transaction(function()use($companyId,$d){
            $company=DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
            abort_unless($company && !$company->archived_at,404);
            $sub=DB::table('saas_subscriptions')->where('company_id',$companyId)
                ->lockForUpdate()->first();
            abort_unless($sub,404);
            $plan=DB::table('saas_plans')->where('id',$sub->plan_id)->first();
            abort_unless($plan,404);
            if(!$plan->available){
                throw ValidationException::withMessages(['months'=>'This plan is discontinued for new invoices.']);
            }
            if(DB::table('saas_invoices')->where('subscription_id',$sub->id)
                ->whereIn('status',['pending','partial'])->exists()){
                throw ValidationException::withMessages(['months'=>'Settle the current invoice before generating the next one.']);
            }
            SaasBilling::newInvoice($company,$sub,$plan,(int)$d['months']);
        },3);
        return back()->with('success','Invoice issued with fixed BDT price and dates. No money has been received.');
    }

    public function payment(Request $request,int $companyId,int $invoiceId)
    {
        $d=$request->validate([
            'method'=>['required','in:bank,bkash,nagad'],
            'reference'=>['required','string','min:5','max:120','regex:/^[A-Za-z0-9_.,-]+$/'],
            'amount'=>['required','numeric','between:0.01,99999999','decimal:0,2'],
            'operator_note'=>['nullable','string','max:1000'],
        ]);
        DB::transaction(function()use($companyId,$invoiceId,$d){
            $company=DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
            abort_unless($company,404);
            $invoice=DB::table('saas_invoices')->where('company_id',$companyId)
                ->where('id',$invoiceId)->lockForUpdate()->first();
            abort_unless($invoice,404);
            if(!in_array($invoice->status,['pending','partial'],true)){
                throw ValidationException::withMessages(['amount'=>'This invoice cannot receive more payment records.']);
            }
            if(DB::table('saas_payments')->where('method',$d['method'])
                ->where('reference',$d['reference'])->exists()){
                throw ValidationException::withMessages(['reference'=>'This payment reference has already been used.']);
            }
            $remaining=SaasBilling::cents($invoice->total_amount)-SaasBilling::cents($invoice->paid_amount);
            $pending=DB::table('saas_payments')->where('invoice_id',$invoiceId)
                ->where('status','pending')->get(['amount'])
                ->sum(fn($row)=>SaasBilling::cents($row->amount));
            if(SaasBilling::cents($d['amount'])+$pending>$remaining){
                throw ValidationException::withMessages(['amount'=>'This amount exceeds the invoice balance, including pending payment reviews.']);
            }
            $id=DB::table('saas_payments')->insertGetId([
                'company_id'=>$companyId,'invoice_id'=>$invoiceId,
                'method'=>$d['method'],'reference'=>trim($d['reference']),
                'amount'=>$d['amount'],'status'=>'pending',
                'operator_note'=>$d['operator_note']??null,
                'recorded_by'=>auth()->id(),'created_at'=>now(),'updated_at'=>now(),
            ]);
            SaasBilling::audit($companyId,'billing.payment.recorded','saas_payment',$id,[
                'invoice_id'=>$invoiceId,'method'=>$d['method'],'amount'=>$d['amount'],
            ]);
        },3);
        return back()->with('success','Payment evidence registered as Pending. Approve only after external verification.');
    }

    public function decide(Request $request,int $companyId,int $paymentId)
    {
        $d=$request->validate([
            'decision'=>['required','in:approve,reject'],
            'operator_note'=>['required','string','min:12','max:1000'],
            'verified_externally'=>['accepted_if:decision,approve'],
        ]);
        DB::transaction(function()use($companyId,$paymentId,$d){
            $company=DB::table('companies')->where('id',$companyId)->lockForUpdate()->first();
            abort_unless($company,404);
            $payment=DB::table('saas_payments')->where('id',$paymentId)
                ->where('company_id',$companyId)->lockForUpdate()->first();
            abort_unless($payment,404);
            if($payment->status!=='pending'){
                throw ValidationException::withMessages(['decision'=>'This payment evidence has already been decided.']);
            }
            $invoice=DB::table('saas_invoices')->where('id',$payment->invoice_id)
                ->where('company_id',$companyId)->lockForUpdate()->first();
            abort_unless($invoice,404);
            if($d['decision']==='reject'){
                DB::table('saas_payments')->where('id',$paymentId)->update([
                    'status'=>'rejected','operator_note'=>$d['operator_note'],
                    'decided_by'=>auth()->id(),'decided_at'=>now(),'updated_at'=>now(),
                ]);
                SaasBilling::audit($companyId,'billing.payment.rejected','saas_payment',$paymentId);
                return;
            }
            if(!in_array($invoice->status,['pending','partial'],true)){
                throw ValidationException::withMessages(['decision'=>'This invoice has already been settled.']);
            }
            if($invoice->period_end<=CarbonImmutable::now($company->timezone)->toDateString()){
                throw ValidationException::withMessages(['decision'=>
                    'The invoiced service period has elapsed; do not approve a stale payment without billing review.']);
            }
            $newPaid=SaasBilling::cents($invoice->paid_amount)+SaasBilling::cents($payment->amount);
            $total=SaasBilling::cents($invoice->total_amount);
            if($newPaid>$total){
                throw ValidationException::withMessages(['decision'=>'Payment exceeds the remaining invoice balance.']);
            }
            DB::table('saas_payments')->where('id',$paymentId)->update([
                'status'=>'approved','operator_note'=>$d['operator_note'],
                'decided_by'=>auth()->id(),'decided_at'=>now(),'updated_at'=>now(),
            ]);
            $fullyPaid=$newPaid===$total;
            DB::table('saas_invoices')->where('id',$invoice->id)->update([
                'paid_amount'=>SaasBilling::money($newPaid),
                'status'=>$fullyPaid?'paid':'partial',
                'settled_at'=>$fullyPaid?now():null,'updated_at'=>now(),
            ]);
            if($fullyPaid){
                $sub=DB::table('saas_subscriptions')->where('id',$invoice->subscription_id)
                    ->where('company_id',$companyId)->lockForUpdate()->first();
                abort_unless($sub,404);
                DB::table('saas_subscriptions')->where('id',$sub->id)->update([
                    'current_period_start'=>$invoice->period_start,
                    'current_period_end'=>$invoice->period_end,
                    'status'=>'active','updated_at'=>now(),
                ]);
            }
            SaasBilling::audit($companyId,'billing.payment.approved','saas_payment',$paymentId,[
                'invoice_id'=>$invoice->id,'amount'=>$payment->amount,
                'fully_paid'=>$fullyPaid,
            ]);
        },3);
        return back()->with('success','Verified payment approved; subscription extended only if the invoice is fully paid.');
    }
}
