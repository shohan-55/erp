<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\ExpenseRules;
use App\Support\ApprovalFlow;
use App\Support\WorkspaceAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WorkflowController extends Controller
{
    public function leaves(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $rows = $this->ownedRows($actor, 'leave_requests');
        return view('erp.leaves', compact('company', 'workspace', 'actor', 'firmSlug', 'rows'));
    }

    public function submitLeave(Request $request, string $firmSlug)
    {
        [$company, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);

        $input = $request->validate([
            'leave_type' => ['required', 'in:annual,sick,emergency'],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        if (\Carbon\CarbonImmutable::parse($input['date_from'])
            ->diffInDays(\Carbon\CarbonImmutable::parse($input['date_to'])) > 60) {
            throw ValidationException::withMessages(['date_to' => 'Request a maximum of 61 calendar days at a time.']);
        }

        DB::transaction(function () use ($actor, $input) {
            // Lock the student membership to serialize concurrent leave
            // submissions: overlapping leave ranges cannot be double-booked.
            DB::table('memberships')->where('id', $actor->id)
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->lockForUpdate()->first();

            $overlap = DB::table('leave_requests')
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('membership_id', $actor->id)
                ->whereIn('status', ['pending', 'manager_reviewed', 'approved'])
                ->where('date_from', '<=', $input['date_to'])
                ->where('date_to', '>=', $input['date_from'])
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'date_from' => 'An existing open or approved leave request overlaps these dates.',
                ]);
            }

            $id = DB::table('leave_requests')->insertGetId([
                'company_id' => $actor->company_id, 'workspace_id' => $actor->workspace_id,
                'membership_id' => $actor->id,
                ...$input,
                'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'leave.submit', 'leave_request', $id);
            foreach (TenantOps::teamRecipients($actor, $actor) as $recipientId) {
                TenantOps::notify($actor, $recipientId, 'New leave request', 'A team member submitted leave for review.');
            }
        }, 3);

        return back()->with('success', 'Leave request submitted for review.');
    }

    public function decideLeave(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $input = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($actor, $id, $input) {
            $row = DB::table('leave_requests')
                ->where('id', $id)->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)->lockForUpdate()->first();
            abort_unless($row, 404);
            $subject = TenantOps::subject($actor, $row->membership_id);
            abort_unless(TenantOps::actorCanReview($actor, $subject), 403);

            if (! in_array($row->status, ['pending', 'manager_reviewed'], true)) {
                throw ValidationException::withMessages(['decision' => 'This request has already received its final decision.']);
            }

            if ($actor->role === 'manager' && $row->status !== 'pending') {
                throw ValidationException::withMessages(['decision' => 'Already reviewed by a manager.']);
            }

            // An assigned active Manager must make the first review before a
            // Partner can give final approval. Students without an assigned
            // active Manager remain eligible for direct Partner review.
            if ($actor->role === 'partner' && $input['decision'] === 'approve'
                && $row->status === 'pending' && $this->activeManagerAssigned($subject)) {
                throw ValidationException::withMessages([
                    'decision' => 'The assigned Manager must review this leave before final Partner approval.',
                ]);
            }

            $status = $input['decision'] === 'reject' ? 'rejected'
                : ($actor->role === 'partner' ? 'approved' : 'manager_reviewed');

            DB::table('leave_requests')->where('id', $id)->update([
                'status' => $status, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'decision_note' => $input['decision_note'] ?? null,
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'leave.'.$status, 'leave_request', $id);
            TenantOps::notify($actor, $subject->id, 'Leave request update', 'Your leave request status is '.$status.'.');
            if ($status === 'manager_reviewed') {
                foreach (TenantOps::teamRecipients($actor, $subject) as $recipientId) {
                    TenantOps::notify($actor, $recipientId, 'Leave awaiting Partner review', 'A leave request requires final Partner review.');
                }
            }
        }, 3);

        return back()->with('success', 'Leave review recorded.');
    }

    public function claims(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $rows = $this->ownedRows($actor, 'conveyance_claims');
        $canSubmitFor = $actor->role === 'manager'
            && WorkspaceAccess::allows($actor, 'claim.submit_for');
        $canWithdrawFor = $actor->role === 'manager'
            && WorkspaceAccess::allows($actor, 'claim.withdraw_for');
        $assignedStudents = $canSubmitFor
            ? DB::table('memberships as m')
                ->join('users as u', 'u.id', '=', 'm.user_id')
                ->where('m.company_id', $company->id)
                ->where('m.workspace_id', $workspace->id)
                ->where('m.manager_membership_id', $actor->id)
                ->where('m.role', 'student')->where('m.active', true)
                ->whereNull('m.platform_suspended_at')
                ->select('m.id', 'u.name')->orderBy('u.name')->get()
            : collect();
        $year = (int) now($company->timezone)->year;
        $policy = ExpenseRules::policy($actor, now($company->timezone)->toDateString());
        $summary = [
            'submitted' => $rows->count(),
            'awaiting_review' => $rows->whereIn('status', ['pending','manager_reviewed'])->count(),
            'approved_bdt' => $rows->where('status','approved')
                ->sum(fn($c) => (float)($c->approved_amount??$c->amount)),
            'paid_bdt' => $rows->where('status','paid')
                ->sum(fn($c) => (float)($c->approved_amount??$c->amount)),
        ];
        return view('erp.claims', compact('company', 'workspace', 'actor', 'firmSlug',
            'rows', 'policy', 'year', 'summary',
            'canSubmitFor', 'canWithdrawFor', 'assignedStudents'));
    }

    public function submitClaim(Request $request, string $firmSlug)
    {
        return $this->submitClaimRequest($request, false);
    }

    public function submitClaimForStudent(Request $request, string $firmSlug)
    {
        return $this->submitClaimRequest($request, true);
    }

    private function submitClaimRequest(Request $request, bool $delegated)
    {
        [, , $actor] = TenantOps::context($request);
        if ($delegated) {
            abort_unless($actor->role === 'manager'
                && WorkspaceAccess::allows($actor, 'claim.submit_for'), 403);
            $student = TenantOps::subject($actor, (int) $request->input('membership_id'));
            abort_unless($student->role === 'student'
                && $student->platform_suspended_at === null, 404);
        } else {
            TenantOps::requireRole($actor, ['student']);
            $student = $actor;
        }

        $input = $request->validate([
            'membership_id' => $delegated ? ['required','integer','min:1'] : ['prohibited'],
            'delegation_reason' => $delegated
                ? ['required','string','min:15','max:1000'] : ['prohibited'],
            'journey_date' => ['required', 'date_format:Y-m-d'],
            'expense_category' => ['nullable', 'in:transport,client_duty,meals,lodging,other'],
            'journey_route' => ['required', 'string', 'min:3', 'max:200'],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:50000', 'decimal:0,2'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $input['expense_category'] = $input['expense_category'] ?? 'transport';
        if ($input['journey_date'] > now('Asia/Dhaka')->toDateString()) {
            throw ValidationException::withMessages([
                'journey_date' => 'Claims cannot be submitted for a future journey.',
            ]);
        }
        $hash = hash_file('sha256', $request->file('receipt')->getRealPath());
        $period = substr($input['journey_date'], 0, 7).'-01';
        if (DB::table('payroll_runs')->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('period_month', $period)
            ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
            throw ValidationException::withMessages(['journey_date' => 'The payroll for this journey month is frozen; contact your Partner.']);
        }

        // Receipt is privately stored against its actual beneficiary, never
        // the Manager's payroll identity; no public filesystem exposure.
        $filePath = $request->file('receipt')->store('claim-evidence/'.$actor->company_id.'/'.$student->id, 'local');

        try {
            DB::transaction(function () use ($actor, $input, $filePath, $period, $hash, $delegated) {
                // Use the same workspace lock as payroll review: a claim cannot
                // slip into a month while its payroll snapshot is being frozen.
                DB::table('workspaces')->where('id', $actor->workspace_id)
                    ->where('company_id', $actor->company_id)->lockForUpdate()->first();
                $fresh = DB::table('memberships')->where('id', $actor->id)
                    ->where('company_id', $actor->company_id)
                    ->where('workspace_id', $actor->workspace_id)
                    ->where('active', true)->whereNull('platform_suspended_at')->first();
                abort_unless($fresh, 403);
                if ($delegated) {
                    abort_unless($fresh->role === 'manager'
                        && WorkspaceAccess::allows($fresh, 'claim.submit_for'), 403);
                    $subject = TenantOps::subject($fresh, (int) $input['membership_id']);
                } else {
                    abort_unless($fresh->role === 'student', 403);
                    $subject = $fresh;
                }
                abort_unless($subject->role === 'student'
                    && $subject->platform_suspended_at === null, 404);
                // Serialize per-subject receipt/allowance checks with self-submission.
                DB::table('memberships')->where('id',$subject->id)->lockForUpdate()->first();
                if (DB::table('payroll_runs')->where('company_id', $actor->company_id)
                    ->where('workspace_id', $actor->workspace_id)
                    ->where('period_month', $period)
                    ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                    throw ValidationException::withMessages(['journey_date' => 'This payroll month is already frozen.']);
                }
                if (ExpenseRules::receiptDuplicate($subject, $hash)) {
                    throw ValidationException::withMessages([
                        'receipt' => 'This receipt was already submitted in an active or approved claim.',
                    ]);
                }
                ExpenseRules::enforce($subject, $input['journey_date'], $input['amount'],
                    $input['expense_category'], $hash);
                $id = DB::table('conveyance_claims')->insertGetId([
                    'company_id' => $actor->company_id, 'workspace_id' => $actor->workspace_id,
                    'membership_id' => $subject->id,
                    'initiated_by' => $fresh->id,
                    'delegation_reason' => $delegated ? trim($input['delegation_reason']) : null,
                    'journey_date' => $input['journey_date'], 'journey_route' => $input['journey_route'],
                    'purpose' => $input['purpose'], 'amount' => $input['amount'],
                    'receipt_path' => $filePath,
                    'receipt_sha256' => $hash,
                    'expense_category' => $input['expense_category'],
                    'status' => 'pending',
                    'approval_mode' => $delegated ? 'partner_only'
                        : ApprovalFlow::forSubmission($subject, 'conveyance'),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($fresh,
                    $delegated ? 'claim.submit_for' : 'claim.submit',
                    'conveyance_claim', $id, [
                        'beneficiary_membership_id' => $subject->id,
                        'initiator_membership_id' => $fresh->id,
                        'delegation_reason' => $delegated
                            ? trim($input['delegation_reason']) : null,
                        'claimed_amount' => $input['amount'],
                        'receipt_sha256' => $hash,
                        'approval_mode' => $delegated ? 'partner_only' : 'workspace_policy',
                    ]);
                if ($delegated) {
                    TenantOps::notify($fresh, $subject->id,
                        'Conveyance claim submitted on your behalf',
                        'Your assigned Manager submitted an expense claim for independent Partner approval.');
                }
                foreach (TenantOps::teamRecipients($fresh, $subject) as $recipientId) {
                    TenantOps::notify($fresh, (int) $recipientId,
                        'New conveyance claim',
                        'An expense claim needs independent review in your workspace.');
                }
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($filePath);
            throw $e;
        }

        return back()->with('success', $delegated
            ? 'Delegated claim submitted; independent Partner approval is required.'
            : 'Claim submitted with private supporting evidence.');
    }

    public function receipt(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        $claim = DB::table('conveyance_claims')->where('id', $id)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)->first();
        abort_unless($claim, 404);
        TenantOps::subject($actor, $claim->membership_id);

        abort_unless($claim->receipt_path && Storage::disk('local')->exists($claim->receipt_path), 404);

        return Storage::disk('local')->download($claim->receipt_path);
    }

    public function decideClaim(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner', 'manager']);

        $input = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:1000'],
            'approved_amount' => ['nullable', 'numeric', 'min:0.01', 'max:50000', 'decimal:0,2'],
        ]);

        DB::transaction(function () use ($actor, $id, $input) {
            // Serialize claim review with payroll snapshot/reservation.
            DB::table('workspaces')->where('id', $actor->workspace_id)->lockForUpdate()->first();
            $claim = DB::table('conveyance_claims')
                ->where('id', $id)->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)->lockForUpdate()->first();
            abort_unless($claim, 404);
            $subject = TenantOps::subject($actor, $claim->membership_id);
            abort_unless(TenantOps::actorCanReview($actor, $subject), 403);

            if (! in_array($claim->status, ['pending', 'manager_reviewed'], true)
                || $claim->reserved_payroll_run_id || $claim->paid_payroll_run_id) {
                throw ValidationException::withMessages(['decision' => 'This claim cannot be reviewed again.']);
            }

            $period = substr($claim->journey_date, 0, 7).'-01';
            if (DB::table('payroll_runs')->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('period_month', $period)
                ->whereIn('status', ['reviewed', 'approved', 'paid', 'locked'])->exists()) {
                throw ValidationException::withMessages(['decision' => 'The payroll for this claim month is already frozen.']);
            }

            $next = ApprovalFlow::next($claim, $actor, $subject, $input['decision']);

            // Do all monetary comparison in integer cents. Claim originals
            // are immutable; only accepted/recommended amounts are separate.
            $requestedCents = (int) round(((float) $claim->amount) * 100);
            $chosenAmount = $input['approved_amount']
                ?? ($next['final'] ? $claim->manager_recommended_amount : null)
                ?? $claim->amount;
            $chosenCents = (int) round(((float) $chosenAmount) * 100);
            if ($input['decision'] === 'approve' && ($chosenCents <= 0 || $chosenCents > $requestedCents)) {
                throw ValidationException::withMessages([
                    'approved_amount' => 'The reviewed amount must be greater than zero and cannot exceed the original claim.',
                ]);
            }
            if ($input['decision'] === 'approve' && $chosenCents < $requestedCents
                && mb_strlen(trim((string) ($input['decision_note'] ?? ''))) < 10) {
                throw ValidationException::withMessages([
                    'decision_note' => 'Explain reduced reimbursements in at least 10 characters.',
                ]);
            }

            // For manager_final, the Partner explicitly authorized the
            // assigned Manager to make a final decision on NEW claims only.
            // Legacy claims continue Manager review -> Partner final.
            $status = $next['status'];
            $changes = [
                'status' => $status, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'decision_note' => $input['decision_note'] ?? null,
                'updated_at' => now(),
            ];
            if ($input['decision'] === 'approve' && !$next['final']) {
                $changes['manager_recommended_amount'] = sprintf('%.2f', $chosenCents / 100);
            } elseif ($input['decision'] === 'approve' && $next['final']) {
                $changes['approved_amount'] = sprintf('%.2f', $chosenCents / 100);
            }
            DB::table('conveyance_claims')->where('id', $id)->update($changes);
            ApprovalFlow::event($actor,'conveyance',$claim,$input['decision'],$next,$input['decision_note']??null);
            TenantOps::audit($actor, 'claim.'.$status, 'conveyance_claim', $id, [
                'original_amount' => $claim->amount,
                'reviewed_amount' => $input['decision'] === 'approve' ? $changes[$next['final']
                    ? 'approved_amount' : 'manager_recommended_amount'] : null,
            ]);
            TenantOps::notify($actor, $subject->id, 'Conveyance claim update', 'Your conveyance claim status is '.$status.'.');
            if ($status === 'manager_reviewed') {
                foreach (TenantOps::teamRecipients($actor, $subject) as $recipientId) {
                    TenantOps::notify($actor, $recipientId, 'Conveyance awaiting Partner approval', 'A reviewed claim requires Partner approval.');
                }
            }
        }, 3);

        return back()->with('success', 'Claim decision saved.');
    }

    public function notifications(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);
        $rows = DB::table('tenant_notifications')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->where('recipient_membership_id', $actor->id)->latest()->limit(100)->get();
        return view('erp.notifications', compact('company', 'workspace', 'actor', 'firmSlug', 'rows'));
    }

    public function readNotification(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        DB::table('tenant_notifications')->where('id', $id)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('recipient_membership_id', $actor->id)
            ->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return back();
    }

    private function activeManagerAssigned(object $subject): bool
    {
        if (! $subject->manager_membership_id) {
            return false;
        }

        return DB::table('memberships')
            ->where('id', $subject->manager_membership_id)
            ->where('company_id', $subject->company_id)
            ->where('workspace_id', $subject->workspace_id)
            ->where('role', 'manager')
            ->where('active', true)->exists();
    }

    private function ownedRows(object $actor, string $table)
    {
        return DB::table($table.' as r')
            ->join('memberships as m', 'm.id', '=', 'r.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $actor->company_id)
            ->where('r.workspace_id', $actor->workspace_id)
            ->where('m.company_id', $actor->company_id)
            ->where('m.workspace_id', $actor->workspace_id)
            ->when($actor->role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
            ->select('r.*', 'u.name as member_name')
            ->orderByDesc('r.created_at')->limit(100)->get();
    }
}