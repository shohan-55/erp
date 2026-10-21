<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\PracticeReport;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PracticeReportController extends Controller
{
    private function data(Request $request):array
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate(['month'=>['nullable','date_format:Y-m']]);
        $month=$input['month']??CarbonImmutable::now($company->timezone)->format('Y-m');
        return [$company,$workspace,$actor,$month,PracticeReport::month($actor,$month)];
    }

    public function index(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor,$month,$report]=$this->data($request);
        return response()->view('erp.practice-reports',compact(
            'company','workspace','actor','firmSlug','month','report'
        ))->header('Cache-Control','private, no-store, max-age=0');
    }

    public function export(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor,$month,$report]=$this->data($request);
        TenantOps::audit($actor,'report.practice.exported','workspace',$workspace->id,[
            'month'=>$month,'students'=>$report['rows']->count(),
        ]);
        $safe=static function($value):string{
            $v=(string)($value??'');
            return preg_match('/^\s*[=+\-@]/u',$v)?"'".$v:$v;
        };
        return response()->streamDownload(function()use($report,$safe){
            $out=fopen('php://output','w');
            fputcsv($out,['Staff ID','Staff Name','Status','Recorded Sessions',
                'Late Sessions','Open Sessions','Approved Leave Requests',
                'Approved Expense BDT','Approved Time Hours','Billable Hours',
                'Locked Net Payroll BDT']);
            foreach($report['rows'] as $r){
                fputcsv($out,[$r->member_id,$safe($r->name),
                    $r->active?'Active':'Inactive',$r->sessions,$r->late,$r->open,
                    $r->leave_requests,number_format($r->approved_claim_bdt,2,'.',''),
                    number_format($r->approved_hours,2,'.',''),
                    number_format($r->billable_hours,2,'.',''),
                    $r->locked_payroll_bdt===null?'NOT LOCKED':number_format((float)$r->locked_payroll_bdt,2,'.','')]);
            }
            fclose($out);
        },'partner-report-'.$month.'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store, max-age=0',
        ]);
    }
}
