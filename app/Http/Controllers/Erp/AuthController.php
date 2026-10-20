<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\ErpSecurityEvents;
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

        if ($request->user()) {
            $count = DB::table('memberships as m')
                ->join('workspaces as w', function ($join) {
                    $join->on('w.id', '=', 'm.workspace_id')
                        ->on('w.company_id', '=', 'm.company_id');
                })
                ->where('m.company_id', $company->id)
                ->where('m.user_id', $request->user()->id)
                ->where('m.active', true)->whereNull('m.platform_suspended_at')
                ->where('w.active', true)->count();
            if ($count > 0) {
                return redirect()->route($count > 1 ? 'erp.workspaces.index' : 'erp.dashboard',
                    ['firmSlug' => $firmSlug]);
            }
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
            ErpSecurityEvents::record('login.denied',$request,$company->id);
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid for this workspace.']);
        }

        $activeMemberships = DB::table('memberships as m')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'm.workspace_id')
                    ->on('w.company_id', '=', 'm.company_id');
            })
            ->where('m.company_id', $company->id)
            ->where('m.user_id', Auth::id())
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->where('w.active', true)->count();

        if ($activeMemberships === 0) {
            ErpSecurityEvents::record('login.denied',$request,$company->id);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email' => 'The supplied credentials are invalid for this workspace.']);
        }

        $request->session()->regenerate();
        ErpSecurityEvents::record('login.accepted',$request,$company->id,(int)Auth::id());
        $request->session()->forget([
            'erp_mfa_verified_user_id', 'erp_mfa_pending_secret', 'erp_mfa_setup_user_id',
            'erp.active_membership',
        ]);
        $request->session()->put(['erp_mfa_context' => 'firm', 'erp_mfa_firm_slug' => $firmSlug]);

        // Never arbitrarily choose the first Partner when the same user holds
        // multiple authorized memberships in this firm.
        return redirect()->intended(route($activeMemberships > 1
            ? 'erp.workspaces.index' : 'erp.dashboard', ['firmSlug' => $firmSlug]));
    }

    public function logout(Request $request, string $firmSlug)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('erp.login', ['firmSlug' => $firmSlug]);
    }
}