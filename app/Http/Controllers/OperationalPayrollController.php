<?php

namespace App\Http\Controllers;

use App\Models\AllowanceRate;
use App\Models\AttendanceDay;
use App\Models\Company;
use App\Models\CompanyMembership;
use App\Models\ConveyanceClaim;
use App\Models\PayrollClaimAllocation;
use App\Models\PayrollEvent;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalPayrollController extends Controller
{
    private function context(Request $request, string $slug): array
    {
        $company = Company::query()->where('slug', $slug)->where('is_active', true)->firstOrFail();
        $actor = CompanyMembership::query()->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)->whereNotNull('workspace_id')->firstOrFail();

        abort_if($actor->role === 'manager', 403);
        return [$company, $actor];
    }

    public function index(Request $request, string $companySlug)
    {
        [$company, $actor] = $this->context($request, $companySlug);
        $runs = PayrollRun::query()->where('company_id', $company->id)
            ->where('workspace_id', $actor->workspace_id)->orderByDesc('payroll_month')->limit(18)->get();

        $visibleRunIds = $actor->role === 'student'
            ? $runs->where('status', 'locked')->pluck('id')->all()
            : $runs->pluck('id')->all();

        $items = PayrollItem::query()
            ->where('company_id', $company->id)->where('workspace_id', $actor->workspace_id)
            ->whereIn('payroll_run_id', $visibleRunIds)
            ->when($actor->role === 'student', fn ($q) => $q->where('membership_id', $actor->id))
            ->orderByDesc('id')->limit(80)->get();

        if ($actor->role === 'student') {
            $runs = $runs->where('status','locked')->values();
        }

        $auditEvents = $actor->role === 'partner'
            ? PayrollEvent::query()->where('company_id', $company->id)
                ->where('workspace_id', $actor->workspace_id)->orderByDesc('id')->limit(40)->get()
            : collect();

        return view('operational.payroll', compact('company', 'actor', 'runs', 'items', 'auditEvents'));
    }

    public function generate(Request $request, string $companySlug)
    {
        [$company, $actor] = $this->context($request, $companySlug);
        abort_unless($actor->role === 'partner', 403);
        $data = $request->validate(['month'=>['required','date_format:Y-m']]);
        $month = $data['month'];
        $start = CarbonImmutable::createFromFormat('!Y-m', $month, $company->timezone);
        $end = $start->endOfMonth()->toDateString();

        DB::transaction(function () use ($company, $actor, $month, $end) {
            CompanyMembership::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();

            if (PayrollRun::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->where('payroll_month',$month)->exists()) {
                throw ValidationException::withMessages(['month'=>'A payroll snapshot for this workspace and month already exists.']);
            }

            $students = CompanyMembership::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->where('role','student')
                ->where('is_active',true)->orderBy('id')->get();

            if ($students->isEmpty()) {
                throw ValidationException::withMessages(['month'=>'There are no active students in this workspace.']);
            }

            $rates = [];
            foreach ($students as $student) {
                $rate = AllowanceRate::query()->where('company_id',$company->id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$student->id)
                    ->where('effective_month','<=',$month)
                    ->orderByDesc('effective_month')->first();
                if (! $rate) {
                    throw ValidationException::withMessages(['month'=>'Every student must have a documented monthly allowance before generating payroll. Missing rate for membership #'.$student->id]);
                }
                $rates[$student->id] = $rate;
            }

            $run = PayrollRun::create([
                'company_id'=>$company->id, 'workspace_id'=>$actor->workspace_id,
                'payroll_month'=>$month,'status'=>'draft',
                'created_by_membership_id'=>$actor->id,
            ]);

            foreach ($students as $student) {
                $rate = $rates[$student->id];
                $claims = ConveyanceClaim::query()
                    ->where('company_id',$company->id)->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$student->id)->where('status','approved')
                    ->where('journey_date','<=',$end)
                    ->whereNotIn('id', PayrollClaimAllocation::query()->select('conveyance_claim_id'))
                    ->orderBy('id')->lockForUpdate()->get();

                $claimMinor = 0;
                foreach ($claims as $claim) {
                    $claimMinor += $this->minor((string)$claim->amount);
                }

                $baseMinor = $this->minor((string)$rate->base_amount);
                $days = AttendanceDay::query()->where('company_id',$company->id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->where('membership_id',$student->id)
                    ->where('work_date','like',$month.'%')->get();

                $item = PayrollItem::create([
                    'payroll_run_id'=>$run->id,'company_id'=>$company->id,
                    'workspace_id'=>$actor->workspace_id,'membership_id'=>$student->id,
                    'base_amount'=>$this->amount($baseMinor),
                    'approved_claim_total'=>$this->amount($claimMinor),
                    'payable_amount'=>$this->amount($baseMinor + $claimMinor),
                    'snapshot'=>[
                        'rate_id'=>$rate->id, 'rate_effective_month'=>$rate->effective_month,
                        'attendance_days'=>$days->count(), 'late_days'=>$days->where('status','late')->count(),
                        'incomplete_days'=>$days->where('status','incomplete')->count(),
                        'attendance_baseline'=>'Office 10:00-18:00; 10 min grace; Fri/Sat holidays',
                        'financial_lateness_deduction'=>0, 'claim_ids'=>$claims->pluck('id')->all(),
                    ],
                ]);

                foreach ($claims as $claim) {
                    PayrollClaimAllocation::create([
                        'payroll_item_id'=>$item->id,
                        'conveyance_claim_id'=>$claim->id,
                        'amount'=>$claim->amount,
                    ]);
                }
            }

            PayrollEvent::create([
                'payroll_run_id'=>$run->id, 'company_id'=>$company->id,
                'workspace_id'=>$actor->workspace_id, 'actor_membership_id'=>$actor->id,
                'action'=>'generate', 'previous_status'=>null, 'new_status'=>'draft',
                'metadata'=>['payroll_month'=>$month, 'students'=>$students->count()],
            ]);
        },3);

        return back()->with('status','Draft payroll snapshot generated. No funds transferred.');
    }

    public function advance(Request $request, string $companySlug, int $runId)
    {
        [$company, $actor] = $this->context($request, $companySlug);
        abort_unless($actor->role === 'partner',403);
        $data = $request->validate([
            'action'=>['required','in:review,approve,mark_paid,lock'],
            'payment_reference'=>['nullable','string','max:150'],
        ]);

        DB::transaction(function () use ($company,$actor,$runId,$data) {
            CompanyMembership::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $run = PayrollRun::query()->where('company_id',$company->id)
                ->where('workspace_id',$actor->workspace_id)->whereKey($runId)
                ->lockForUpdate()->firstOrFail();

            $transitions = [
                'review'=>['draft','reviewed'],
                'approve'=>['reviewed','approved'],
                'mark_paid'=>['approved','paid'],
                'lock'=>['paid','locked'],
            ];
            [$from,$to] = $transitions[$data['action']];
            if ($run->status !== $from) {
                throw ValidationException::withMessages(['action'=>'Invalid or repeated payroll status transition.']);
            }

            $update = ['status'=>$to];
            if ($data['action']==='review') {
                $update['reviewed_by_membership_id']=$actor->id;
                $update['reviewed_at']=now();
            }
            if ($data['action']==='approve') {
                $update['approved_by_membership_id']=$actor->id;
                $update['approved_at']=now();
            }
            if ($data['action']==='mark_paid') {
                if (mb_strlen(trim($data['payment_reference'] ?? '')) < 6) {
                    throw ValidationException::withMessages(['payment_reference'=>'Enter a verified external payment reference (6+ characters). This application never transfers money.']);
                }
                $update['paid_at']=now();
                $update['payment_reference']=trim($data['payment_reference']);
            }
            if ($data['action']==='lock') {
                $update['locked_at']=now();
            }

            $run->update($update);
            PayrollEvent::create([
                'payroll_run_id'=>$run->id, 'company_id'=>$company->id,
                'workspace_id'=>$actor->workspace_id, 'actor_membership_id'=>$actor->id,
                'action'=>$data['action'], 'previous_status'=>$from, 'new_status'=>$to,
                'metadata'=>['external_reference_recorded'=>$data['action']==='mark_paid'],
            ]);

            if ($data['action']==='mark_paid') {
                $claimIds = PayrollClaimAllocation::query()
                    ->whereIn('payroll_item_id', PayrollItem::query()->where('payroll_run_id',$run->id)->select('id'))
                    ->pluck('conveyance_claim_id');
                ConveyanceClaim::query()->where('company_id',$company->id)
                    ->where('workspace_id',$actor->workspace_id)
                    ->whereIn('id',$claimIds)->where('status','approved')
                    ->update(['status'=>'paid','paid_at'=>now()]);
            }
        },3);

        return back()->with('status','Payroll status updated. Verify all disbursements independently.');
    }

    private function minor(string $amount): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new \LogicException('Invalid stored currency amount.');
        }
        [$whole,$fraction] = array_pad(explode('.',$amount,2),2,'00');
        return ((int)$whole)*100 + (int)str_pad($fraction,2,'0');
    }

    private function amount(int $minor): string
    {
        return intdiv($minor,100).'.'.str_pad((string)($minor % 100),2,'0',STR_PAD_LEFT);
    }
}
