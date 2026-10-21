<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\OutboundMailGuard;
use App\Support\ErpSecurityEvents;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function forgot(Request $request, string $firmSlug)
    {
        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);
        return view('erp.password-forgot', compact('company', 'firmSlug'));
    }

    public function send(Request $request, string $firmSlug)
    {
        $input = $request->validate(['email' => ['required', 'email', 'max:254']]);
        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);

        $user = User::query()->where('email', $input['email'])->first();
        if ($user && DB::table('memberships')->where('company_id', $company->id)
            ->where('user_id', $user->id)->where('active', true)->exists()) {
            OutboundMailGuard::ensureConfigured();
            $token = Password::broker()->createToken($user);
            $url = route('erp.password.reset.form', [
                'firmSlug' => $firmSlug,
                'token' => $token,
                'email' => $user->email,
            ]);

            Mail::raw(
                'A password reset was requested for '.$company->name.".\n\n".
                "Use this one-time reset link if you made the request:\n".$url.
                "\n\nIf you did not request this, you can ignore this email.",
                function ($message) use ($user, $company) {
                    $message->to($user->email)->subject('Reset your '.$company->name.' ERP password');
                }
            );
        }

        // Same message regardless of whether email/member exists.
        return back()->with('status', 'If an active account exists, a password recovery email has been requested.');
    }

    public function resetForm(Request $request, string $firmSlug, string $token)
    {
        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);
        $email = (string) $request->query('email', '');

        return view('erp.password-reset', compact('company', 'firmSlug', 'token', 'email'));
    }

    public function update(Request $request, string $firmSlug)
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);

        $user = User::query()->where('email', $input['email'])->first();
        if (! $user || ! DB::table('memberships')->where('company_id', $company->id)
            ->where('user_id', $user->id)->where('active', true)->exists()) {
            throw ValidationException::withMessages(['email' => 'The reset link is invalid or expired.']);
        }

        $status = Password::broker()->reset($input, function (User $account, string $password) use ($request, $company) {
            $account->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();
            // Password recovery invalidates all previously minted integration
            // credentials across companies, not only this firm's tokens.
            ErpSecurityEvents::revokeUserTokens($account->id, $request, $company->id);
            DB::table('sessions')->where('user_id', $account->id)->delete();
            ErpSecurityEvents::record('account.password.reset',$request,$company->id,$account->id);
            event(new PasswordReset($account));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'The reset link is invalid or expired.']);
        }

        return redirect()->route('erp.login', ['firmSlug' => $firmSlug])
            ->with('status', 'Your password was reset. Please sign in again.');
    }
}