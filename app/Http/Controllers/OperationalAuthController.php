<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OperationalAuthController extends Controller
{
    public function loginForm(string $companySlug)
    {
        $company = Company::query()->where('slug', $companySlug)->where('is_active', true)->firstOrFail();

        return view('operational.login', compact('company'));
    }

    public function login(Request $request, string $companySlug)
    {
        $company = Company::query()->where('slug', $companySlug)->where('is_active', true)->firstOrFail();
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $key = 'operational-login:'.sha1(strtolower($credentials['email']).'|'.$company->id.'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again later.']);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The provided credentials or workspace are invalid.']);
        }

        $membership = CompanyMembership::query()
            ->where('company_id', $company->id)
            ->where('user_id', Auth::id())
            ->where('is_active', true)
            ->whereNotNull('workspace_id')
            ->first();

        if (! $membership) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The provided credentials or workspace are invalid.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->route('operational.dashboard', ['companySlug' => $company->slug]);
    }

    public function logout(Request $request, string $companySlug)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('operational.login', ['companySlug' => $companySlug]);
    }
}
