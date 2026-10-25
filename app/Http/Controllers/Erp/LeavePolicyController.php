<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\LeaveBalance;
use App\Support\TenantOps;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeavePolicyController extends Controller
{
    public function save(Request $request, string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);

        $input=$request->validate([
            'leave_year'=>['required','integer','between:2025,2100'],
            'annual_days'=>['required','numeric','between:0,365','multiple_of:0.5'],
            'sick_days'=>['required','numeric','between:0,365','multiple_of:0.5'],
            'casual_days'=>['required','numeric','between:0,365','multiple_of:0.5'],
            'emergency_days'=>['required','numeric','between:0,365','multiple_of:0.5'],
            'carry_forward_limit'=>['required','numeric','between:0,365','multiple_of:0.5'],
            'annual_notice_days'=>['required','integer','between:0,90'],
            'count_holidays'=>['sometimes','required','boolean'],
        ]);
        $now=(int)CarbonImmutable::now($company->timezone)->year;
        if (!in_array((int)$input['leave_year'],[$now,$now+1],true)) {
            throw ValidationException::withMessages(['leave_year'=>'Configure the current or next calendar year only.']);
        }

        DB::transaction(function()use($company,$workspace,$actor,$input) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $year=(int)$input['leave_year'];
            $old=LeaveBalance::policy($actor,$year);
            $existing=DB::table('leave_requests')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('date_from','>=',$year.'-01-01')
                ->where('date_from','<=',$year.'-12-31')->exists();
            $credits=DB::table('leave_balance_adjustments')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('leave_year',$year)->exists();
            $payroll=DB::table('payroll_runs')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('period_month','>=',$year.'-01-01')
                ->where('period_month','<=',$year.'-12-01')
                ->whereIn('status',['reviewed','approved','paid','locked'])->exists();

            // An existing policy is a historical allowance snapshot. Amend
            // only before any dependency, never recalculate frozen balances.
            if ($old && ($existing || $credits || $payroll)) {
                throw ValidationException::withMessages([
                    'leave_year'=>'This year already has leave, credit or finalized payroll activity; policy changes would rewrite historical entitlements.',
                ]);
            }
            if (!$old && $payroll) {
                throw ValidationException::withMessages([
                    'leave_year'=>'Finalize the leave policy before locking payroll for this year.',
                ]);
            }
            $values=[
                'annual_days'=>$input['annual_days'],'sick_days'=>$input['sick_days'],
                'casual_days'=>$input['casual_days'],'emergency_days'=>$input['emergency_days'],
                'carry_forward_limit'=>$input['carry_forward_limit'],
                'annual_notice_days'=>$input['annual_notice_days'],
                // New policies default to working-day leave; if a client omits the
                // field while editing, preserve any existing explicit Yes.
                'count_holidays'=>array_key_exists('count_holidays',$input)
                    ? (bool)$input['count_holidays']
                    : ($old ? (bool)$old->count_holidays : LeaveBalance::COUNT_HOLIDAYS_DEFAULT),
                'set_by'=>$actor->id,'updated_at'=>now(),
            ];
            if ($old) {
                DB::table('workspace_leave_policies')->where('id',$old->id)->update($values);
                $id=$old->id;
            } else {
                $id=DB::table('workspace_leave_policies')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    'leave_year'=>$year,...$values,'created_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,'leave.policy.saved','workspace_leave_policy',$id,[
                'year'=>$year,'previous_policy_id'=>$old?->id,
                'annual_days'=>$input['annual_days'],'sick_days'=>$input['sick_days'],
                'casual_days'=>$input['casual_days'],'emergency_days'=>$input['emergency_days'],
                'carry_forward_limit'=>$input['carry_forward_limit'],
                'count_holidays'=>$values['count_holidays'],
            ]);
        },3);

        return back()->with('success','Partner yearly leave policy saved. Existing leave balances remain protected.');
    }

    public function credit(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'membership_id'=>['required','integer','min:1'],
            'leave_year'=>['required','integer','between:2025,2100'],
            'leave_type'=>['required','in:annual,sick,casual,emergency,compensatory'],
            'days'=>['required','numeric','between:0.5,90','multiple_of:0.5'],
            'expires_on'=>['nullable','date_format:Y-m-d'],
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        $year=(int)$input['leave_year'];
        $today=CarbonImmutable::now($company->timezone)->toDateString();
        if (!in_array($year,[(int)substr($today,0,4),(int)substr($today,0,4)+1],true)) {
            throw ValidationException::withMessages(['leave_year'=>'Credits may only be issued for current or next year.']);
        }
        if (isset($input['expires_on']) &&
            ($input['expires_on'] < max($today,$year.'-01-01') || $input['expires_on'] > $year.'-12-31')) {
            throw ValidationException::withMessages(['expires_on'=>'Expiry must be within the credit year and not in the past.']);
        }
        DB::transaction(function()use($company,$workspace,$actor,$input,$year) {
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $student=TenantOps::subject($actor,(int)$input['membership_id']);
            abort_unless($student->role==='student',404);
            if (!LeaveBalance::policy($actor,$year)) {
                throw ValidationException::withMessages(['leave_year'=>'Save this Partner year leave policy before granting extra days.']);
            }
            $id=DB::table('leave_balance_adjustments')->insertGetId([
                'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                'membership_id'=>$student->id,'leave_year'=>$year,
                'leave_type'=>$input['leave_type'],'days'=>$input['days'],
                'expires_on'=>$input['expires_on']??null,
                'reason'=>trim($input['reason']),'authorized_by'=>$actor->id,
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'leave.credit.granted','leave_balance_adjustment',$id,[
                'membership_id'=>$student->id,'type'=>$input['leave_type'],
                'days'=>$input['days'],'year'=>$year,
            ]);
            TenantOps::notify($actor,$student->id,'Additional leave credit',
                $input['days'].' day(s) of '.$input['leave_type'].' leave were credited for '.$year.'.');
        },3);
        return back()->with('success','Additional leave credit recorded with a permanent audit trail.');
    }
}
