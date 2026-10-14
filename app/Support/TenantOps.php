<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class TenantOps
{
    public static function context(Request $request): array
    {
        return [
            $request->attributes->get('tenant.company'),
            $request->attributes->get('tenant.workspace'),
            $request->attributes->get('tenant.membership'),
        ];
    }

    public static function requireRole(object $actor, array $roles): void
    {
        abort_unless(in_array($actor->role, $roles, true), 403, 'Not authorized for this operation.');
    }

    /** Returns only a membership in the requester's own company and Home Partner. */
    public static function subject(object $actor, int $memberId): object
    {
        $query = DB::table('memberships')
            ->where('id', $memberId)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('active', true);

        if ($actor->role === 'manager') {
            $query->where('manager_membership_id', $actor->id);
        } elseif ($actor->role === 'student') {
            $query->where('id', $actor->id);
        } elseif ($actor->role !== 'partner') {
            abort(403);
        }

        $member = $query->first();
        abort_unless($member, 404, 'No accessible team member.');
        return $member;
    }

    public static function audit(object $actor, string $action, string $type, int $id, array $details = []): void
    {
        DB::table('tenant_audit_events')->insert([
            'company_id' => $actor->company_id,
            'workspace_id' => $actor->workspace_id,
            'actor_membership_id' => $actor->id,
            'action' => $action,
            'entity_type' => $type,
            'entity_id' => $id,
            'details' => json_encode($details, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);
    }

    public static function notify(object $actor, int $recipientId, string $title, string $message): void
    {
        $recipient = DB::table('memberships')
            ->where('id', $recipientId)
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('active', true)->first();

        if (! $recipient) {
            throw new HttpException(422, 'Notification recipient is not part of your workspace.');
        }

        DB::table('tenant_notifications')->insert([
            'company_id' => $actor->company_id,
            'workspace_id' => $actor->workspace_id,
            'recipient_membership_id' => $recipient->id,
            'title' => $title,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public static function teamRecipients(object $actor, object $subject): array
    {
        $recipients = DB::table('memberships')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('active', true)
            ->where(function ($q) use ($subject) {
                $q->where('role', 'partner');
                if ($subject->manager_membership_id) {
                    $q->orWhere('id', $subject->manager_membership_id);
                }
            })
            ->pluck('id')->all();

        return array_unique(array_filter($recipients, fn ($id) => $id !== $actor->id));
    }

    public static function actorCanReview(object $actor, object $subject): bool
    {
        if (! in_array($actor->role, ['partner', 'manager'], true)) {
            return false;
        }

        if ($subject->company_id !== $actor->company_id || $subject->workspace_id !== $actor->workspace_id) {
            return false;
        }

        return $actor->role === 'partner' || $subject->manager_membership_id === $actor->id;
    }
}