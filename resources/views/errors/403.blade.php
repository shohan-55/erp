@php
    $msg = isset($exception) && $exception->getMessage() ? $exception->getMessage() : 'Access to this workspace or resource is restricted.';
    $isBillingOrSuspended = str_contains(strtolower($msg), 'subscription') || str_contains(strtolower($msg), 'suspended') || str_contains(strtolower($msg), 'billing');
    $waRenewMsg = "Hello DhakaWala ERP Support, we would like to renew / reactivate our practice workspace subscription. (" . request()->getHost() . request()->getRequestUri() . ")";
    $waRenewUrl = 'https://wa.me/8801700000000?text=' . rawurlencode($waRenewMsg);
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-[#090D16]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isBillingOrSuspended ? 'Workspace Subscription Notice — DhakaWala ERP' : '403 Access Restricted — DhakaWala ERP' }}</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/dhakawala_logo.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <x-brand-theme-style />
</head>
<body class="min-h-full flex items-center justify-center p-4 sm:p-6 bg-[#090D16] text-white font-sans antialiased">
    <div style="background-color: #11141C; color: #FFFFFF;" class="w-full max-w-lg rounded-3xl border border-white/15 bg-[#17191F] p-6 sm:p-8 shadow-2xl">
        <div class="flex items-center justify-between border-b border-white/10 pb-5">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-400/15 border border-amber-400/30 text-[#FCDF02] font-black text-sm">
                    DW
                </div>
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-[#FCDF02]">DhakaWala ERP Cloud</p>
                    <h1 class="text-base font-extrabold text-white">
                        {{ $isBillingOrSuspended ? 'Workspace Subscription & Billing Notice' : 'Workspace Access Restricted (403)' }}
                    </h1>
                </div>
            </div>
            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase {{ $isBillingOrSuspended ? 'bg-amber-400/20 text-amber-300 border border-amber-400/30' : 'bg-rose-500/20 text-rose-300 border border-rose-400/30' }}">
                {{ $isBillingOrSuspended ? 'Billing Hold' : 'HTTP 403' }}
            </span>
        </div>

        <div class="mt-5 rounded-2xl border {{ $isBillingOrSuspended ? 'border-amber-400/30 bg-amber-500/10 text-amber-100' : 'border-rose-400/30 bg-rose-500/10 text-rose-100' }} p-4 text-xs leading-relaxed font-semibold">
            {{ $msg }}
        </div>

        @if($isBillingOrSuspended)
            <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-xs space-y-2.5 text-slate-300">
                <p class="font-extrabold text-white uppercase tracking-wider text-[11px]">Instant Subscription Renewal (bKash / Nagad / Bank Transfer)</p>
                <p class="leading-relaxed text-slate-400">
                    Your practice data, student attendance logs, conveyance claims, and payroll snapshots are <strong class="text-emerald-300">100% safe and preserved</strong>. Once your subscription is renewed, full workspace access is restored immediately.
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <div class="rounded-xl bg-black/30 p-3 border border-white/10">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Billing Support Email</span>
                        <p class="mt-0.5 font-mono font-bold text-white">billing@dhakawala.com</p>
                    </div>
                    <div class="rounded-xl bg-black/30 p-3 border border-white/10">
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Payment Channels</span>
                        <p class="mt-0.5 font-bold text-[#FCDF02]">bKash · Nagad · Bank A/C</p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-2.5">
                <a href="mailto:billing@dhakawala.com?subject=Subscription%20Renewal%20Request" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#17191F] hover:brightness-105 transition">
                    ✉ Email Billing Support
                </a>
                <a href="{{ $waRenewUrl }}" target="_blank" rel="noopener" class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-400/40 bg-emerald-500/20 px-4 py-3 text-xs font-extrabold text-emerald-200 hover:bg-emerald-500/30 transition">
                    💬 WhatsApp Support ↗
                </a>
            </div>
        @else
            <p class="mt-4 text-xs leading-relaxed text-slate-400">
                If you switched accounts or need Partner delegation for this action, please return to your practice dashboard or sign in with an authorized account.
            </p>
        @endif

        <div class="mt-6 border-t border-white/10 pt-4 flex items-center justify-between text-xs">
            <a href="{{ url('/dashboard') }}" class="font-bold text-slate-300 hover:text-white">← Return to My Dashboard</a>
            <a href="{{ url('/') }}" class="font-bold text-[#FCDF02] hover:underline">DhakaWala Portal Home →</a>
        </div>
    </div>
</body>
</html>
