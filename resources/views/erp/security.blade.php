@extends('layouts.erp')
@section('title','Settings / Security')
@section('content')
<section>
  <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">ACCOUNT SETTINGS / SECURITY</p>
  <h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">Security Settings</h2>
  <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">Control your own sign-in security. Two-step verification is optional but strongly recommended for workspace accounts, especially Partners with access to payroll and approvals.</p>
</section>
<section class="max-w-3xl space-y-5">
  <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <h3 class="text-lg font-extrabold">Two-Step Verification (2FA)</h3>
        <p class="mt-1 text-sm text-slate-600">Protect your account with an authenticator app.</p>
      </div>
      @if($mfaEnabled)
        <span class="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800">Enabled</span>
      @else
        <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-900">Recommended · Off</span>
      @endif
    </div>

    @if(!$mfaEnabled)
      <p class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">You can continue using the ERP with your password. Enabling 2FA adds a second check when you sign in and is especially recommended for payroll and partner accounts.</p>
      <a href="{{ route('erp.mfa.setup') }}" class="mt-5 inline-flex items-center justify-center rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold text-slate-900 hover:bg-[#F5AD16]">Enable Two-Step Verification</a>
    @else
      <p class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">Two-step verification is active. Future sign-ins require your authenticator or a single-use recovery code.</p>
      @unless(auth()->user()->is_platform_admin)
      <details class="mt-5 rounded-xl border border-slate-200 p-4">
        <summary class="cursor-pointer text-sm font-bold text-slate-800">Turn off two-step verification</summary>
        <p class="mt-3 text-sm leading-relaxed text-slate-600">Not recommended for Partner or payroll accounts. To confirm, enter your current account password and a fresh six-digit authenticator code or an unused recovery code. Disabling 2FA signs out your other browser sessions.</p>
        <form method="POST" action="{{ route('erp.security.mfa.disable', ['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-4">
          @csrf
          <label class="block text-sm font-bold">Current Account Password
            <input name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100">
          </label>
          <label class="block text-sm font-bold">Authenticator Code or Recovery Code
            <input name="code" type="text" autocomplete="one-time-code" maxlength="48" required class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 font-mono focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100">
          </label>
          <button class="rounded-xl border border-rose-300 bg-rose-50 px-5 py-3 text-sm font-extrabold text-rose-900 hover:bg-rose-100">Confirm: Turn Off 2FA</button>
        </form>
      </details>
      @endunless
    @endif
    <p class="mt-5 border-t border-slate-100 pt-4 text-xs text-slate-500">Security settings apply to your user account across any companies where you are a member. Platform administrators are required to keep MFA enabled.</p>
  </div>
</section>
@endsection
