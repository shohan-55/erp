<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AccountCompanyController extends Controller
{
    public function login()
    {
        return response()->view('erp.account-login')
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function signIn(Request $request)
    {
        $input = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string'],
        ]);
        if (!Auth::attempt($input)) {
            throw ValidationException::withMessages(['email' => 'Invalid credentials.']);
        }
        $request->session()->regenerate();
        $request->session()->forget([
            'erp_mfa_verified_user_id', 'erp_mfa_pending_secret',
            'erp_mfa_setup_user_id', 'erp_mfa_firm_slug', 'erp.active_membership',
        ]);
        $request->session()->put('erp_mfa_context', 'account');
        return redirect()->intended(route('erp.account.companies'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('erp.account.login');
    }

    /** Company labels and role names only: never inspect cross-tenant private data. */
    private function memberships(Request $request)
    {
        return DB::table('memberships as m')
            ->join('companies as c', 'c.id', '=', 'm.company_id')
            ->join('workspaces as w', function ($join) {
                $join->on('w.id', '=', 'm.workspace_id')->on('w.company_id', '=', 'm.company_id');
            })
            ->where('m.user_id', $request->user()->id)
            ->where('m.active', true)->whereNull('m.platform_suspended_at')
            ->where('c.active', true)->whereNull('c.archived_at')->whereNull('c.deleted_at')
            ->where('w.active', true)
            ->select('c.id as company_id', 'c.slug as company_slug', 'c.name as company_name',
                'm.id as membership_id', 'm.role', 'w.name as workspace_name')
            ->orderBy('c.name')->orderBy('w.name')->limit(250)->get();
    }

    public function index(Request $request)
    {
        $memberships = $this->memberships($request);
        $companies = $memberships->groupBy('company_id');

        return response()->view('erp.account-companies', compact('companies'))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function select(Request $request)
    {
        $input = $request->validate(['company_id' => ['required', 'integer', 'min:1']]);
        $options = $this->memberships($request)->where('company_id', (int) $input['company_id']);
        if ($options->isEmpty()) {
            throw ValidationException::withMessages([
                'company_id' => 'That company is not available for this account.',
            ]);
        }

        $option = $options->first();
        // Clear the previous selected membership before entering another
        // company; Partner chooser handles a multi-workspace membership safely.
        $request->session()->regenerate();
        $request->session()->forget('erp.active_membership');
        $request->session()->put([
            'erp_mfa_context' => 'firm',
            'erp_mfa_firm_slug' => $option->company_slug,
        ]);

        if ($options->count() > 1) {
            return redirect()->route('erp.workspaces.index', ['firmSlug' => $option->company_slug]);
        }

        $request->session()->put('erp.active_membership', [
            'company_id' => (int) $option->company_id,
            'user_id' => (int) $request->user()->id,
            'membership_id' => (int) $option->membership_id,
        ]);

        return redirect()->route('erp.dashboard', ['firmSlug' => $option->company_slug]);
    }
}
