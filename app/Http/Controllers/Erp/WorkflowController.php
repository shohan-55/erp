<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
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
        return view('erp.claims', compact('company', 'workspace', 'actor', 'firmSlug', 'rows'));
    }

    public function submitClaim(Request $request, string $firmSlug)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['student']);

        $input = $request->validate([
            'journey_date' => ['required', 'date_format:Y-m-d'],
            'journey_route' => ['required', 'string', 'min:3', 'max:200'],
            'purpose' => ['required', 'string', 'min:10', 'max:2000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:50000', 'decimal:0,2'],
            'receipt' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ]);

        $filePath = $request->file('receipt')->store('claim-evidence/'.$actor->company_id.'/'.$actor->id, 'local');

        try {
            DB::transaction(function () use ($actor, $input, $filePath) {
                $id = DB::table('conveyance_claims')->insertGetId([
                    'company_id' => $actor->company_id, 'workspace_id' => $actor->workspace_id,
                    'membership_id' => $actor->id,
                    'journey_date' => $input['journey_date'], 'journey_route' => $input['journey_route'],
                    'purpose' => $input['purpose'], 'amount' => $input['amount'],
                    'receipt_path' => $filePath, 'status' => 'pending',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                TenantOps::audit($actor, 'claim.submit', 'conveyance_claim', $id);
                foreach (TenantOps::teamRecipients($actor, $actor) as $recipientId) {
                    TenantOps::notify($actor, $recipientId, 'New conveyance claim', 'A team member submitted a travel expense claim.');
                }
            }, 3);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($filePath);
            throw $e;
        }

        return back()->with('success', 'Claim submitted with private supporting evidence.');
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
        ]);

        DB::transaction(function () use ($actor, $id, $input) {
            $claim = DB::table('conveyance_claims')
                ->where('id', $id)->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)->lockForUpdate()->first();
            abort_unless($claim, 404);
            $subject = TenantOps::subject($actor, $claim->membership_id);
            abort_unless(TenantOps::actorCanReview($actor, $subject), 403);

            if ($claim->status !== 'pending' || $claim->reserved_payroll_run_id || $claim->paid_payroll_run_id) {
                throw ValidationException::withMessages(['decision' => 'This claim cannot be reviewed again.']);
            }

            $status = $input['decision'] === 'approve' ? 'approved' : 'rejected';
            DB::table('conveyance_claims')->where('id', $id)->update([
                'status' => $status, 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'decision_note' => $input['decision_note'] ?? null,
                'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'claim.'.$status, 'conveyance_claim', $id);
            TenantOps::notify($actor, $subject->id, 'Conveyance claim update', 'Your conveyance claim status is '.$status.'.');
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

    private function ownedRows(object $actor, string $table)
    {
        return DB::table($table.' as r')
            ->join('memberships as m', 'm.id', '=', 'r.membership_id')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('r.company_id', $actor->company_id)
            ->where('r.workspace_id', $actor->workspace_id)
            ->when($actor->role === 'manager', fn ($q) => $q->where('m.manager_membership_id', $actor->id))
            ->when($actor->role === 'student', fn ($q) => $q->where('m.id', $actor->id))
            ->select('r.*', 'u.name as member_name')
            ->orderByDesc('r.created_at')->limit(100)->get();
    }
}