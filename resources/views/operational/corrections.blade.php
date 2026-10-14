<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $company->name }} — Attendance Corrections</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#20232A]">
<header class="bg-[#17191F] text-white"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4"><div><p class="text-xs font-bold text-[#FCDF02]">LIVE WORKSPACE · REVIEWER</p><h1 class="mt-1 font-extrabold">{{ $company->name }}</h1></div><div class="flex gap-2"><a href="{{ route('operational.dashboard',['companySlug'=>$company->slug]) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Attendance</a><a href="{{ route('operational.requests',['companySlug'=>$company->slug]) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Leave & Claims</a></div></div></header>
<main class="mx-auto max-w-6xl space-y-6 px-5 py-8">
<div><p class="text-xs font-bold uppercase tracking-widest text-amber-800">Authorized Partner / Manager Workflow</p><h2 class="mt-1 text-2xl font-extrabold">Attendance Corrections</h2><p class="mt-2 text-sm text-slate-500">Correct incomplete or inaccurate sessions only for authorized Home Partner students. An immutable record of the old time, new time, reviewer and explanation is saved.</p></div>
@if(session('status'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
<div class="space-y-4">
@forelse($sessions as $session)
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="mb-4 flex flex-wrap items-center justify-between gap-2"><div><h3 class="text-sm font-extrabold">Session #{{ $session->id }} · Member #{{ $session->membership_id }}</h3><p class="mt-1 text-xs text-slate-500">{{ ucfirst($session->mode) }} · {{ $session->client_site ?: 'Firm Office' }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $session->is_open ? 'bg-amber-100 text-amber-900' : 'bg-emerald-50 text-emerald-800' }}">{{ $session->is_open ? 'Incomplete / Open' : 'Closed' }}</span></div>
<form method="POST" action="{{ route('operational.attendance.correct',['companySlug'=>$company->slug,'sessionId'=>$session->id]) }}" class="grid gap-3 lg:grid-cols-[1fr_1fr_2fr_auto] lg:items-end">@csrf
<label class="text-xs font-bold">Corrected Check-in ({{ $company->timezone }})
<input type="datetime-local" name="checked_in_local" required value="{{ $session->checked_in_at->setTimezone($company->timezone)->format('Y-m-d\TH:i') }}" class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-xs"></label>
<label class="text-xs font-bold">Corrected Check-out ({{ $company->timezone }})
<input type="datetime-local" name="checked_out_local" required value="{{ $session->checked_out_at?->setTimezone($company->timezone)->format('Y-m-d\TH:i') }}" class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-xs"></label>
<label class="text-xs font-bold">Reason (required, 10+ characters)<input type="text" name="reason" minlength="10" maxlength="2000" placeholder="Why is this correction required?" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-xs"></label>
<button type="submit" class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Save Correction</button>
</form></section>
@empty<div class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-sm text-slate-500">No sessions available in your assigned scope.</div>@endforelse
</div>
</main></body></html>