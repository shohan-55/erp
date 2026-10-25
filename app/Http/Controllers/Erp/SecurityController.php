<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use App\Support\ErpSecurityEvents;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);

        $recentEvents = DB::table('erp_security_events')
            ->where('user_id',$request->user()->id)
            ->where(function($q)use($company){
                $q->where('company_id',$company->id)->orWhereNull('company_id');
            })->orderByDesc('id')->limit(20)
            ->get(['kind','severity','created_at']);

        return response()->view('erp.security', [
            'company' => $company,
            'workspace' => $workspace,
            'actor' => $actor,
            'firmSlug' => $firmSlug,
            'mfaEnabled' => (bool) $request->user()->refresh()->mfa_confirmed_at,
            'recentEvents' => $recentEvents,
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function disableMfa(Request $request, string $firmSlug)
    {
        [, , $actor] = TenantOps::context($request);
        $user = $request->user()->refresh();

        // A platform operator cannot bypass their compulsory second factor,
        // even if they have a membership in a tenant.
        abort_if($user->is_platform_admin, 403, 'Platform operators must retain MFA.');

        $input = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:48'],
        ]);

        $code = strtoupper(preg_replace('/[\s-]/', '', trim($input['code'])));
        if (! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['code' => 'Invalid account password or verification code.']);
        }

        DB::transaction(function () use ($user, $code, $actor, $request): void {
            $record = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            abort_unless($record && $record->mfa_confirmed_at && $record->mfa_secret, 409);

            $secret = Crypt::decryptString($record->mfa_secret);
            $validCode = Totp::matchingStep(
                $secret,
                $code,
                $record->mfa_last_step === null ? null : (int) $record->mfa_last_step
            ) !== null;

            // A previously unused recovery code can be used to disable MFA
            // when the authenticator device is no longer available.
            if (! $validCode) {
                $digest = hash('sha256', $code);
                foreach (json_decode($record->mfa_recovery_codes ?? '[]', true) ?: [] as $storedDigest) {
                    if (is_string($storedDigest) && hash_equals($storedDigest, $digest)) {
                        $validCode = true;
                        break;
                    }
                }
            }

            if (! $validCode) {
                throw ValidationException::withMessages([
                    'code' => 'Invalid, expired, or already-used verification code.',
                ]);
            }

            DB::table('users')->where('id', $user->id)->update([
                'mfa_secret' => null,
                'mfa_recovery_codes' => null,
                'mfa_confirmed_at' => null,
                'mfa_last_step' => null,
                'updated_at' => now(),
            ]);

            ErpSecurityEvents::revokeUserTokens($user->id,$request,$actor->company_id);
            ErpSecurityEvents::record('account.mfa.disabled',$request,$actor->company_id,$user->id);
            TenantOps::audit($actor, 'account.mfa.disabled', 'user', $user->id);
        }, 3);

        // Keep the current authenticated session; revoke all other browser
        // sessions because account-wide MFA protection was removed.
        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $request->session()->forget([
            'erp_mfa_verified_user_id', 'erp_mfa_pending_secret',
            'erp_mfa_setup_user_id', 'erp_mfa_new_recovery_codes',
        ]);
        $request->session()->regenerate();

        return redirect()->route('erp.security', ['firmSlug' => $firmSlug])
            ->with('success', 'Two-step verification is off. You can enable it again at any time.');
    }
}
