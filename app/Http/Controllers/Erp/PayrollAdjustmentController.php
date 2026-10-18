<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\PayrollCalculator;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollAdjustmentController extends Controller
{
    public function create(Request $request,string $firmSlug,int $runId)
    {
        [, $workspace, $actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'kind'=>['required','in:bonus,deduction'],
            'amount'=>['required','numeric','between:0.01,500000','decimal:0,2'],
            'reason'=>['required','string','min:15','max:1000'],
            'confirm_authorized'=>['required','accepted'],
        ]);
        DB::transaction(function()use($actor,$workspace,$runId,$input){
            DB::table('workspaces')->where('id',$workspace->id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $run=DB::table('payroll_runs')->where('id',$runId)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
            abort_unless($run,404);
            if($run->status!=='draft'){
                throw ValidationException::withMessages(['amount'=>'Financial adjustments are allowed only while payroll is a draft.']);
            }
            $member=DB::table('memberships')->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('id',$input['membership_id'])->where('role','student')
                ->where('active',true)->first();
            abort_unless($member,404);
            if(!DB::table('payroll_lines')->where('payroll_run_id',$run->id)
                ->where('membership_id',$member->id)->exists()){
                throw ValidationException::withMessages(['membership_id'=>'Student is not included in the draft payroll.']);
            }

            $id=DB::table('payroll_line_adjustments')->insertGetId([
                'company_id'=>$actor->company_id,'workspace_id'=>$actor->workspace_id,
                'payroll_run_id'=>$run->id,'membership_id'=>$member->id,
                'kind'=>$input['kind'],'amount'=>$input['amount'],
                'reason'=>trim($input['reason']),'authorized_by'=>$actor->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            PayrollCalculator::refresh($run->id,$run->period_month,
                (int)$actor->company_id,(int)$actor->workspace_id);
            TenantOps::audit($actor,'payroll.adjustment.authorized',
                'payroll_line_adjustment',$id,[
                    'payroll_run_id'=>$run->id,'membership_id'=>$member->id,
                    'kind'=>$input['kind'],'amount'=>$input['amount'],
                ]);
        },3);
        return back()->with('success','Partner-authorized adjustment recorded; draft recalculated. No bank payment was made.');
    }

    public function voidAdjustment(Request $request,string $firmSlug,int $runId,int $adjustmentId)
    {
        [, $workspace, $actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($workspace,$actor,$runId,$adjustmentId,$input) {
            DB::table('workspaces')->where('id',$workspace->id)
                ->where('company_id',$actor->company_id)->lockForUpdate()->first();
            $run=DB::table('payroll_runs')->where('id',$runId)
                ->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)->lockForUpdate()->first();
            abort_unless($run,404);
            if($run->status!=='draft'){
                throw ValidationException::withMessages(['reason'=>'Reviewed payroll cannot be amended or reversed.']);
            }
            $adjustment=DB::table('payroll_line_adjustments')
                ->where('id',$adjustmentId)->where('company_id',$actor->company_id)
                ->where('workspace_id',$actor->workspace_id)
                ->where('payroll_run_id',$run->id)->lockForUpdate()->first();
            abort_unless($adjustment,404);
            if($adjustment->voided_at){
                throw ValidationException::withMessages(['reason'=>'Adjustment was already voided.']);
            }
            DB::table('payroll_line_adjustments')->where('id',$adjustment->id)->update([
                'voided_at'=>now(),'voided_by'=>$actor->id,'updated_at'=>now(),
            ]);
            PayrollCalculator::refresh($run->id,$run->period_month,
                (int)$actor->company_id,(int)$actor->workspace_id);
            TenantOps::audit($actor,'payroll.adjustment.voided',
                'payroll_line_adjustment',$adjustment->id,[
                    'reason'=>trim($input['reason']),'payroll_run_id'=>$run->id,
                ]);
        },3);
        return back()->with('success','Adjustment voided without erasing its audit history.');
    }
}
