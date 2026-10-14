<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function show(Request $request, string $firmSlug)
    {
        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);

        if ($request->user() && DB::table('memberships')->where('company_id', $company->id)
            ->where('user_id', $request->user()->id)->where('active', true)->exists()) {
            return redirect()->route('erp.dashboard', ['firmSlug' => $firmSlug]);
        }

        return view('erp.login', compact('company', 'firmSlug'));
    }

    public function attempt(Request $request, string $firmSlug)
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);

        $company = DB::table('companies')->where('slug', $firmSlug)->where('active', true)->first();
        abort_unless($company, 404);

        // Generic failure on missing membership, inactive membership, or bad password.
        // Never let authentication with one firm become access to another.
        if (! Auth::attempt(['email' => $input['email'], 'password' => $input['password']])) {
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid for this workspace.']);
        }

        $membership = DB::table('memberships')
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->where('active', true)->first();
        $workspaceActive = $membership && DB::table('workspaces')
            ->where('id', $membership->workspace_id)
            ->where('company_id', $company->id)
            ->where('active', true)->exists();

        if (! $workspaceActive) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid for this workspace.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('erp.dashboard', ['firmSlug' => $firmSlug]));
    }

    public function logout(Request $request, string $firmSlug)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('erp.login', ['firmSlug' => $firmSlug]);
    }
}