<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class ActivationController extends Controller
{
    public function form(Request $request, string $firmSlug, string $token)
    {
        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);
        $email = (string) $request->query('email', '');
        return view('erp.activate', compact('company', 'firmSlug', 'token', 'email'));
    }

    public function accept(Request $request, string $firmSlug)
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);

        $user = User::where('email', $input['email'])->first();
        if (! $user || ! DB::table('memberships')->where('company_id', $company->id)
            ->where('user_id', $user->id)->where('active', false)->exists()) {
            throw ValidationException::withMessages(['email' => 'This invitation is invalid, expired or already accepted.']);
        }

        $status = Password::broker()->reset($input, function (User $account, string $password) use ($company) {
            DB::transaction(function () use ($account, $password, $company) {
                $member = DB::table('memberships')->where('company_id', $company->id)
                    ->where('user_id', $account->id)->where('active', false)->lockForUpdate()->first();
                abort_unless($member, 422);

                $account->forceFill([
                    'password' => Hash::make($password), 'remember_token' => Str::random(60),
                ])->save();
                DB::table('memberships')->where('id', $member->id)->update([
                    'active' => true, 'activated_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('sessions')->where('user_id', $account->id)->delete();
                DB::table('tenant_audit_events')->insert([
                    'company_id' => $company->id,
                    'workspace_id' => $member->workspace_id,
                    'actor_membership_id' => $member->id,
                    'action' => 'team.activate', 'entity_type' => 'membership',
                    'entity_id' => $member->id, 'details' => '{}', 'created_at' => now(),
                ]);
                event(new PasswordReset($account));
            }, 3);
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This invitation is invalid or expired.']);
        }

        return redirect()->route('erp.login', ['firmSlug' => $firmSlug])
            ->with('status', 'Your company account is active. Sign in with your new password.');
    }
}