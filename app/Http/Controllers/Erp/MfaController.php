<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MfaController extends Controller
{
    public function setup(Request $request)
    {
        $user = $request->user()->refresh();
        if ($user->mfa_confirmed_at) {
            return redirect()->route('erp.mfa.challenge');
        }

        if ($request->session()->get('erp_mfa_setup_user_id') !== $user->id
            || ! $request->session()->has('erp_mfa_pending_secret')) {
            $request->session()->put([
                'erp_mfa_setup_user_id' => $user->id,
                'erp_mfa_pending_secret' => Totp::generateSecret(),
            ]);
        }

        $secret = $request->session()->get('erp_mfa_pending_secret');

        return response()->view('erp.mfa', [
            'mode' => 'setup', 'secret' => $secret,
            'provisioningUri' => Totp::provisioningUri($secret, $user->email),
        ])->header('Cache-Control', 'private, no-store, max-age=0')
          ->header('Pragma', 'no-cache');
    }

    public function confirmSetup(Request $request)
    {
        $user = $request->user()->refresh();
        $input = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'regex:/^[0-9]{6}$/'],
        ]);

        $secret = $request->session()->get('erp_mfa_pending_secret');
        if ($user->mfa_confirmed_at || ! is_string($secret)
            || $request->session()->get('erp_mfa_setup_user_id') !== $user->id
            || ! Hash::check($input['password'], $user->password)) {
            throw ValidationException::withMessages(['code' => 'Invalid password or authenticator code.']);
        }

        $step = Totp::matchingStep($secret, $input['code']);
        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'Invalid password or authenticator code.']);
        }

        $codes = array_map(fn () => Totp::encode(random_bytes(10)), range(1, 10));
        DB::transaction(function () use ($user, $secret, $step, $codes): void {
            $current = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            abort_unless($current && ! $current->mfa_confirmed_at, 409);

            DB::table('users')->where('id', $user->id)->update([
                'mfa_secret' => Crypt::encryptString($secret),
                'mfa_recovery_codes' => json_encode(array_map(
                    fn (string $code) => hash('sha256', $code), $codes
                ), JSON_THROW_ON_ERROR),
                'mfa_last_step' => $step,
                'mfa_confirmed_at' => now(),
                'updated_at' => now(),
            ]);
        }, 3);

        $request->session()->forget(['erp_mfa_setup_user_id', 'erp_mfa_pending_secret']);
        $request->session()->regenerate();
        $request->session()->put('erp_mfa_verified_user_id', $user->id);
        $request->session()->flash('erp_mfa_new_recovery_codes', $codes);

        return redirect()->route('erp.mfa.recovery');
    }

    public function challenge(Request $request)
    {
        if (! $request->user()->refresh()->mfa_confirmed_at) {
            return redirect()->route('erp.mfa.setup');
        }

        if ((int) $request->session()->get('erp_mfa_verified_user_id', 0) === (int) $request->user()->id) {
            return $this->nextDestination($request);
        }

        return view('erp.mfa', ['mode' => 'challenge']);
    }

    public function verify(Request $request)
    {
        $input = $request->validate([
            'code' => ['required', 'string', 'max:48'],
        ]);
        $user = $request->user()->refresh();
        if (! $user->mfa_confirmed_at || ! $user->mfa_secret) {
            return redirect()->route('erp.mfa.setup');
        }

        $presented = strtoupper(preg_replace('/[\s-]/', '', trim($input['code'])));
        $valid = DB::transaction(function () use ($user, $presented): bool {
            $record = DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            abort_unless($record && $record->mfa_confirmed_at, 403);

            $secret = Crypt::decryptString($record->mfa_secret);
            $counter = Totp::matchingStep($secret, $presented, $record->mfa_last_step === null
                ? null : (int) $record->mfa_last_step);

            if ($counter !== null) {
                DB::table('users')->where('id', $user->id)->update([
                    'mfa_last_step' => $counter, 'updated_at' => now(),
                ]);
                return true;
            }

            $digest = hash('sha256', $presented);
            $codes = json_decode($record->mfa_recovery_codes ?? '[]', true);
            foreach ($codes as $index => $storedDigest) {
                if (is_string($storedDigest) && hash_equals($storedDigest, $digest)) {
                    unset($codes[$index]);
                    DB::table('users')->where('id', $user->id)->update([
                        'mfa_recovery_codes' => json_encode(array_values($codes), JSON_THROW_ON_ERROR),
                        'updated_at' => now(),
                    ]);
                    return true;
                }
            }

            return false;
        }, 3);

        if (! $valid) {
            throw ValidationException::withMessages(['code' => 'Invalid, expired, or already-used verification code.']);
        }

        $request->session()->regenerate();
        $request->session()->put('erp_mfa_verified_user_id', $user->id);
        return $this->nextDestination($request);
    }

    public function recovery(Request $request)
    {
        $codes = $request->session()->get('erp_mfa_new_recovery_codes');
        if (! is_array($codes)
            || (int) $request->session()->get('erp_mfa_verified_user_id', 0) !== (int) $request->user()->id) {
            return $this->nextDestination($request);
        }

        return view('erp.mfa', ['mode' => 'recovery', 'codes' => $codes]);
    }

    private function nextDestination(Request $request)
    {
        if ($request->session()->get('erp_mfa_context') === 'account') {
            return redirect()->route('erp.account.companies');
        }
        if ($request->user()->is_platform_admin && $request->session()->get('erp_mfa_context') === 'platform') {
            return redirect()->route('erp.platform.dashboard');
        }

        $slug = $request->session()->get('erp_mfa_firm_slug');
        if (is_string($slug)) {
            $company = DB::table('companies')->where('slug', $slug)->where('active', true)->first();
            if ($company && DB::table('memberships')->where('company_id', $company->id)
                ->where('user_id', $request->user()->id)->where('active', true)->exists()) {
                return redirect()->route('erp.dashboard', ['firmSlug' => $slug]);
            }
        }

        if ($request->user()->is_platform_admin) {
            return redirect()->route('erp.platform.dashboard');
        }

        // Never use an unvalidated return_to URL for a privileged redirect.
        return redirect()->route('home');
    }
}
