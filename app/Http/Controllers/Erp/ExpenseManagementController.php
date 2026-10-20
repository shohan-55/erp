<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\ExpenseRules;
use App\Support\TenantOps;
use App\Support\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExpenseManagementController extends Controller
{
    public function savePolicy(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate([
            'expense_year'=>['required','integer','between:2025,2100'],
            'max_single_claim'=>['required','numeric','between:1,50000','decimal:0,2'],
            'monthly_student_limit'=>['required','numeric','between:1,1000000','decimal:0,2'],
            'submission_window_days'=>['required','integer','between:1,365'],
        ]);
        $year=(int)$input['expense_year'];
        $thisYear=(int)CarbonImmutable::now($company->timezone)->year;
        if (!in_array($year,[$thisYear,$thisYear+1],true)) {
            throw ValidationException::withMessages(['expense_year'=>'Only the current or next year may be configured.']);
        }
        if (ExpenseRules::cents($input['monthly_student_limit'])<ExpenseRules::cents($input['max_single_claim'])) {
            throw ValidationException::withMessages(['monthly_student_limit'=>'Monthly allowance cannot be less than maximum single claim.']);
        }
        DB::transaction(function()use($actor,$company,$workspace,$input,$year){
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $existing=DB::table('workspace_expense_policies')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('expense_year',$year)->first();
            $claimed=DB::table('conveyance_claims')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->whereBetween('journey_date',[$year.'-01-01',$year.'-12-31'])->exists();
            $closed=DB::table('payroll_runs')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->whereBetween('period_month',[$year.'-01-01',$year.'-12-01'])
                ->whereIn('status',['reviewed','approved','paid','locked'])->exists();
            if (($existing && $claimed) || $closed) {
                throw ValidationException::withMessages([
                    'expense_year'=>'This yearly policy cannot be revised after claim activity or finalized payroll.',
                ]);
            }
            $data=[
                'max_single_claim'=>$input['max_single_claim'],
                'monthly_student_limit'=>$input['monthly_student_limit'],
                'submission_window_days'=>$input['submission_window_days'],
                'set_by'=>$actor->id,'updated_at'=>now(),
            ];
            if ($existing) {
                DB::table('workspace_expense_policies')->where('id',$existing->id)->update($data);
                $id=$existing->id;
            } else {
                $id=DB::table('workspace_expense_policies')->insertGetId([
                    'company_id'=>$company->id,'workspace_id'=>$workspace->id,
                    'expense_year'=>$year,...$data,'created_at'=>now(),
                ]);
            }
            TenantOps::audit($actor,'expense.policy.saved','workspace_expense_policy',$id,[
                'expense_year'=>$year,'max_single_claim'=>$input['max_single_claim'],
                'monthly_student_limit'=>$input['monthly_student_limit'],
                'submission_window_days'=>$input['submission_window_days'],
            ]);
        },3);
        return back()->with('success','Partner expense policy saved.');
    }

    public function withdraw(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['student']);
        DB::transaction(function()use($actor,$company,$workspace,$id){
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $row=DB::table('conveyance_claims')->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)->where('membership_id',$actor->id)
                ->where('id',$id)->lockForUpdate()->first();
            abort_unless($row,404);
            if (!in_array($row->status,['pending','manager_reviewed'],true)
                || $row->reserved_payroll_run_id || $row->paid_payroll_run_id) {
                throw ValidationException::withMessages(['claim'=>'Only unpaid, pending claims may be withdrawn.']);
            }
            DB::table('conveyance_claims')->where('id',$id)->update([
                'status'=>'withdrawn','updated_at'=>now(),
            ]);
            TenantOps::audit($actor,'expense.withdrawn','conveyance_claim',$id);
            foreach(TenantOps::teamRecipients($actor,$actor) as $recipient) {
                TenantOps::notify($actor,(int)$recipient,'Expense claim withdrawn',
                    'A Student withdrew an unapproved expense claim.');
            }
        },3);
        return back()->with('success','Unapproved expense request withdrawn.');
    }

    /**
     * A delegated submitter can withdraw only their own unreviewed, unreserved
     * claim. The student's direct withdrawal route remains independent.
     */
    public function withdrawDelegated(Request $request,string $firmSlug,int $id)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        abort_unless($actor->role==='manager'
            && WorkspaceAccess::allows($actor,'claim.withdraw_for'),403);
        $input=$request->validate([
            'reason'=>['required','string','min:15','max:1000'],
        ]);
        DB::transaction(function()use($company,$workspace,$actor,$id,$input){
            DB::table('workspaces')->where('company_id',$company->id)
                ->where('id',$workspace->id)->lockForUpdate()->first();
            $fresh=DB::table('memberships')->where('id',$actor->id)
                ->where('company_id',$company->id)
                ->where('workspace_id',$workspace->id)
                ->where('role','manager')->where('active',true)
                ->whereNull('platform_suspended_at')->first();
            abort_unless($fresh && WorkspaceAccess::allows($fresh,'claim.withdraw_for'),403);
            $row=DB::table('conveyance_claims')
                ->where('company_id',$company->id)->where('workspace_id',$workspace->id)
                ->where('id',$id)->lockForUpdate()->first();
            abort_unless($row && (int)$row->initiated_by===(int)$fresh->id
                && $row->delegation_reason!==null,404);
            $student=TenantOps::subject($fresh,(int)$row->membership_id);
            abort_unless($student->role==='student',404);
            if($row->status!=='pending'
                || $row->reserved_payroll_run_id || $row->paid_payroll_run_id) {
                throw ValidationException::withMessages([
                    'reason'=>'Only your own pending, unreserved delegated claim can be withdrawn.',
                ]);
            }
            DB::table('conveyance_claims')->where('id',$id)->where('status','pending')->update([
                'status'=>'withdrawn','withdrawn_by'=>$fresh->id,
                'withdrawal_reason'=>trim($input['reason']),
                'withdrawn_at'=>now(),'updated_at'=>now(),
            ]);
            TenantOps::audit($fresh,'claim.withdrawn_for','conveyance_claim',$id,[
                'student_membership_id'=>$student->id,
                'initiator_membership_id'=>$fresh->id,
                'prior_status'=>'pending','reason'=>trim($input['reason']),
                'claimed_amount'=>$row->amount,
            ]);
            TenantOps::notify($fresh,$student->id,'Delegated expense claim withdrawn',
                'Your Manager withdrew a pending claim. The original receipt remains in the private audit trail.');
            foreach(TenantOps::teamRecipients($fresh,$student) as $recipient) {
                TenantOps::notify($fresh,(int)$recipient,'Delegated expense claim withdrawn',
                    'A pending student expense claim was withdrawn by its original Manager.');
            }
        },3);
        return back()->with('success','Pending delegated expense withdrawn with audit evidence.');
    }

    public function export(Request $request,string $firmSlug)
    {
        [$company,$workspace,$actor]=TenantOps::context($request);
        TenantOps::requireRole($actor,['partner']);
        $input=$request->validate(['month'=>['required','date_format:Y-m']]);
        $from=$input['month'].'-01';
        $to=CarbonImmutable::parse($from)->endOfMonth()->toDateString();

        $records=DB::table('conveyance_claims as c')
            ->join('memberships as m','m.id','=','c.membership_id')
            ->join('users as u','u.id','=','m.user_id')
            ->where('c.company_id',$company->id)->where('c.workspace_id',$workspace->id)
            ->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)
            ->whereBetween('c.journey_date',[$from,$to])
            ->select('c.id','u.name','c.journey_date','c.expense_category',
                'c.journey_route','c.amount','c.approved_amount','c.status',
                'c.reserved_payroll_run_id','c.paid_payroll_run_id')
            ->orderBy('c.journey_date')->orderBy('c.id')->get();

        TenantOps::audit($actor,'expense.export','workspace',$workspace->id,[
            'month'=>$input['month'],'records'=>$records->count(),
        ]);
        $clean=static function($value): string{
            $value=(string)($value??'');
            // Prevent spreadsheet formula injection on fields from users.
            return preg_match('/^[\s]*[=+\-@]/u',$value) ? "'".$value : $value;
        };
        return response()->streamDownload(function()use($records,$clean){
            $out=fopen('php://output','w');
            fputcsv($out,['Claim ID','Student','Date','Category','Route',
                'Requested BDT','Approved BDT','Status','Payroll Reservation','Payroll Payment']);
            foreach($records as $c){
                fputcsv($out,[$c->id,$clean($c->name),$c->journey_date,
                    $clean($c->expense_category),$clean($c->journey_route),
                    $c->amount,$c->approved_amount,$c->status,
                    $c->reserved_payroll_run_id,$c->paid_payroll_run_id]);
            }
            fclose($out);
        },'partner-expenses-'.$input['month'].'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store',
        ]);
    }
}
