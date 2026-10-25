<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/** Explicit consent, not a password-reset flow. Never change a shared account password. */
final class IdentityLink
{
    public static function issue(User $user, int $membershipId, string $companyName): void
    {
        OutboundMailGuard::ensureConfigured();
        // Platform-owner identity must never be self-enrolled into private
        // customer data by a platform invitation.
        abort_if($user->is_platform_admin, 403, 'Platform owner accounts cannot receive tenant links.');
        $member = DB::table('memberships')->where('id', $membershipId)->first();
        abort_unless($member && (int) $member->user_id === (int) $user->id
            && !$member->active && $member->platform_suspended_at === null, 422);

        $token = Str::random(64);
        DB::transaction(function () use ($member, $user, $token) {
            DB::table('erp_identity_link_invitations')->updateOrInsert(
                ['membership_id' => $member->id],
                [
                    'company_id' => $member->company_id,
                    'workspace_id' => $member->workspace_id,
                    'user_id' => $user->id,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addDays(7),
                    'accepted_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }, 3);

        // Links for global account management must never inherit an active
        // customer-owned custom domain from the URL generator.
        $url = rtrim((string) config('app.url'), '/') . '/account/invitations/' . $token;
        Mail::raw(
            "An ERP organization has invited your existing account to join ".$companyName.".\n\n".
            "Your current password, MFA and other company memberships are unchanged.\n".
            "Sign in with your existing ERP account, then open this one-time invitation:\n".
            $url."\n\nThis invitation expires in 7 days. If unexpected, ignore it.",
            fn ($message) => $message->to($user->email)->subject('DhakaWala ERP: confirm new organization membership')
        );
    }

    public static function pendingFor(int $membershipId): bool
    {
        return DB::table('erp_identity_link_invitations')
            ->where('membership_id', $membershipId)
            ->whereNull('accepted_at')->exists();
    }
}
