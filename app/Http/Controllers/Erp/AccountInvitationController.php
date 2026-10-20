<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AccountInvitationController extends Controller
{
    private function invitation(Request $request, string $token, bool $lock = false): ?object
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $token) === 1, 404);
        $query = DB::table('erp_identity_link_invitations as i')
            ->join('memberships as m', 'm.id', '=', 'i.membership_id')
            ->join('companies as c', 'c.id', '=', 'i.company_id')
            ->join('workspaces as w', 'w.id', '=', 'i.workspace_id')
            ->where('i.token_hash', hash('sha256', $token))
            ->where('i.user_id', $request->user()->id)
            ->whereColumn('m.company_id', 'i.company_id')
            ->whereColumn('m.workspace_id', 'i.workspace_id')
            ->whereColumn('m.user_id', 'i.user_id')
            ->whereColumn('w.company_id', 'i.company_id')
            ->whereNull('i.accepted_at')->where('i.expires_at', '>', now())
            ->where('m.active', false)->whereNull('m.platform_suspended_at')
            ->where('c.active', true)->whereNull('c.archived_at')->whereNull('c.deleted_at')
            ->where('w.active', true)
            ->select('i.id', 'i.membership_id', 'i.company_id', 'i.workspace_id',
                'c.name as company_name', 'c.slug as company_slug',
                'w.name as workspace_name', 'm.role');
        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    public function show(Request $request, string $token)
    {
        $invitation = $this->invitation($request, $token);
        abort_unless($invitation, 404);

        return response()->view('erp.account-invitation', compact('invitation', 'token'))
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function accept(Request $request, string $token)
    {
        $input = $request->validate(['current_password' => ['required', 'string']]);
        if (!Hash::check($input['current_password'], $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Account verification failed.',
            ]);
        }

        $companySlug = DB::transaction(function () use ($request, $token) {
            $invitation = $this->invitation($request, $token, true);
            abort_unless($invitation, 404);
            DB::table('memberships')->where('id', $invitation->membership_id)
                ->where('company_id', $invitation->company_id)
                ->where('workspace_id', $invitation->workspace_id)
                ->where('user_id', $request->user()->id)
                ->where('active', false)
                ->update(['active' => true, 'activated_at' => now(), 'updated_at' => now()]);
            DB::table('erp_identity_link_invitations')
                ->where('id', $invitation->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            DB::table('tenant_audit_events')->insert([
                'company_id' => $invitation->company_id,
                'workspace_id' => $invitation->workspace_id,
                'actor_membership_id' => $invitation->membership_id,
                'action' => 'team.identity_link.accepted',
                'entity_type' => 'membership', 'entity_id' => $invitation->membership_id,
                'details' => json_encode(['method' => 'email_token_and_account_password'], JSON_THROW_ON_ERROR),
                'created_at' => now(),
            ]);
            return $invitation->company_slug;
        }, 3);

        $request->session()->regenerate();
        // This is not a new authentication session. The same user's existing
        // MFA verified session is maintained; never switch by request ID alone.
        $request->session()->forget('erp.active_membership');

        return redirect()->route('erp.account.companies')->with('status',
            'Company invitation accepted. Choose a company to continue.');
    }
}
