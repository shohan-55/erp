<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\SaasBilling;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TenantSubscriptionController extends Controller
{
    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        // Firm-level commercial terms are safe to show to a Partner, but
        // platform-only financial references and payment evidence are excluded.
        $subscription=DB::table('saas_subscriptions as s')
            ->join('saas_plans as p','p.id','=','s.plan_id')
            ->where('s.company_id',$company->id)
            ->select('s.*','p.name as plan_name','p.custom_domain_enabled')->first();
        $status=$subscription?SaasBilling::effectiveStatus($subscription):'unbilled';
        $invoices=DB::table('saas_invoices')->where('company_id',$company->id)
            ->select('invoice_number','plan_name_snapshot','period_start','period_end',
                'total_amount','paid_amount','currency','status','due_on',
                'discount_total_bdt','storage_addon_units_snapshot','storage_addon_total_bdt')
            ->orderByDesc('id')->limit(30)->get();
        return response()->view('erp.subscription',compact(
            'company','workspace','actor','firmSlug','subscription','status','invoices'
        ))->header('Cache-Control','private, no-store');
    }
}
