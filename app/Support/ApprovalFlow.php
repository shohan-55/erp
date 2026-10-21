<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Phase 09: safe, executable approval presets. All policy decisions are
 * snapshotted at submission. Legacy rows remain Manager -> Partner.
 * No delegation grants confer final financial approval by themselves.
 */
final class ApprovalFlow
{
    public const MODES = [
        'manager_partner' => 'Two-step: Assigned Manager review, Partner final approval',
        'partner_only' => 'One-step: Partner final approval',
        'manager_final' => 'One-step: Assigned Manager final approval (Partner fallback if unavailable)',
    ];
    public const MODULES = ['leave', 'conveyance'];

    public static function assignedManagerActive(object $subject): bool
    {
        if (!$subject->manager_membership_id) return false;
        return DB::table('memberships')->where('company_id', $subject->company_id)
            ->where('workspace_id', $subject->workspace_id)
            ->where('id', $subject->manager_membership_id)
            ->where('role', 'manager')->where('active', true)
            ->whereNull('platform_suspended_at')->exists();
    }

    public static function forSubmission(object $subject, string $module): string
    {
        if (!in_array($module, self::MODULES, true)) return 'manager_partner';
        $mode = DB::table('workspace_approval_policies')
            ->where('company_id', $subject->company_id)
            ->where('workspace_id', $subject->workspace_id)
            ->where('module', $module)->where('active', true)->value('mode');
        if (!array_key_exists((string) $mode, self::MODES)) $mode = 'manager_partner';

        // A Manager-only policy must never leave an unassigned student stuck:
        // capture a Partner-only route at submission if no Manager exists.
        if ($mode === 'manager_final' && !self::assignedManagerActive($subject)) {
            return 'partner_only';
        }
        return $mode;
    }

    public static function mode(object $requestRow): string
    {
        return array_key_exists((string) ($requestRow->approval_mode ?? ''), self::MODES)
            ? $requestRow->approval_mode : 'manager_partner';
    }

    /**
     * Determine the next state, without trusting a form supplied role,
     * policy ID, step number, decision author or target company.
     * Assigned Manager review rights remain enforced separately.
     */
    public static function next(object $row, object $actor, object $subject, string $decision): array
    {
        $mode = self::mode($row);
        if (!in_array($row->status, ['pending','manager_reviewed'], true)) {
            throw ValidationException::withMessages(['decision'=>'This request already has a decision.']);
        }
        if ($actor->role === 'manager') {
            if ($mode === 'partner_only' || $row->status !== 'pending') {
                throw ValidationException::withMessages(['decision'=>'Manager review is not available at this step.']);
            }
            return [
                'status' => $decision === 'reject' ? 'rejected'
                    : ($mode === 'manager_final' ? 'approved' : 'manager_reviewed'),
                'final' => $decision === 'reject' || $mode === 'manager_final',
                'mode' => $mode,
            ];
        }
        abort_unless($actor->role === 'partner', 403);
        if ($decision === 'approve') {
            if ($mode === 'partner_only' && $row->status !== 'pending') {
                throw ValidationException::withMessages(['decision'=>'This policy expects direct Partner approval.']);
            }
            if ($mode === 'manager_final' && self::assignedManagerActive($subject)) {
                throw ValidationException::withMessages(['decision'=>'The assigned Manager is responsible for final approval under the request policy.']);
            }
            if ($mode === 'manager_partner' && $row->status === 'pending'
                && self::assignedManagerActive($subject)) {
                throw ValidationException::withMessages(['decision'=>'Assigned Manager review is required before Partner approval.']);
            }
        }
        return ['status'=>$decision === 'reject' ? 'rejected' : 'approved',
            'final'=>true,'mode'=>$mode];
    }

    public static function event(object $actor, string $module, object $row,
        string $decision, array $next, ?string $note): void
    {
        DB::table('workspace_approval_events')->insert([
            'company_id' => $actor->company_id,
            'workspace_id' => $actor->workspace_id,
            'module' => $module,
            'request_id' => $row->id,
            'actor_membership_id' => $actor->id,
            'action' => $decision,
            'previous_status' => $row->status,
            'new_status' => $next['status'],
            'approval_mode' => $next['mode'],
            'note' => $note,
            'created_at' => now(),
        ]);
    }
}
