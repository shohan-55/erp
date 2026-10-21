<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayrollStatementController extends Controller
{
    public function payslip(Request $request,string $firmSlug,int $runId,int $memberId)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner','student']);
        if ($actor->role==='student' && $actor->id!==$memberId){
            abort(404);
        }
        $run=DB::table('payroll_runs')
            ->where('id',$runId)->where('company_id',$company->id)
            ->where('workspace_id',$workspace->id)
            ->when($actor->role==='student',fn($q)=>$q->where('status','locked'))
            ->first();
        abort_unless($run,404);
        $line=DB::table('payroll_lines as p')
            ->join('memberships as m','m.id','=','p.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('p.payroll_run_id',$run->id)
            ->where('p.membership_id',$memberId)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->where('m.role','student')
            ->select('p.*','u.name as member_name')->first();
        abort_unless($line,404);
        return response()->view('erp.payslip',compact(
            'company','workspace','actor','firmSlug','run','line'
        ))->header('Cache-Control','private, no-store, max-age=0')
            ->header('X-Content-Type-Options','nosniff');
    }

    public function register(Request $request,string $firmSlug,int $runId)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $run=DB::table('payroll_runs')->where('id',$runId)
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
            ->first();
        abort_unless($run,404);
        $lines=DB::table('payroll_lines as p')
            ->join('memberships as m','m.id','=','p.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('p.payroll_run_id',$run->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->select('m.id as member_id','u.name','p.base_allowance',
                'p.approved_claims','p.adjustment','p.total_amount')
            ->orderBy('m.id')->get();
        TenantOps::audit($actor,'payroll.register.exported','payroll_run',$run->id,[
            'status'=>$run->status,'lines'=>$lines->count(),
        ]);
        $safe=static function($v): string{
            $value=(string)$v;
            return preg_match('/^\s*[=+\-@]/u',$value) ? "'".$value : $value;
        };
        return response()->streamDownload(function()use($lines,$run,$safe) {
            $out=fopen('php://output','w');
            fputcsv($out,['Run','Period','Status','Membership','Student',
                'Base BDT','Approved Claims BDT','Authorized Adjustment BDT','Net BDT']);
            foreach($lines as $line) {
                fputcsv($out,[$run->id,$run->period_month,$run->status,
                    $line->member_id,$safe($line->name),$line->base_allowance,
                    $line->approved_claims,$line->adjustment,$line->total_amount]);
            }
            fclose($out);
        },'partner-payroll-'.$run->period_month.'-run-'.$run->id.'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8',
            'Cache-Control'=>'private, no-store, max-age=0',
        ]);
    }
}
