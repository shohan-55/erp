<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $company->name }} — Attendance</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#20232A]">
<header class="border-b border-slate-200 bg-[#17191F] text-white"><div class="mx-auto flex max-w-6xl items-center justify-between gap-3 px-5 py-4"><div><div class="text-xs font-semibold text-[#FCDF02]">LIVE WORKSPACE · {{ ucfirst($membership->role) }}</div><div class="mt-1 font-extrabold">{{ $company->name }}</div></div><form method="POST" action="{{ route('operational.logout', ['companySlug'=>$company->slug]) }}">@csrf<button type="submit" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Sign Out</button></form></div></header>
<main class="mx-auto max-w-6xl space-y-6 px-5 py-8">
<nav aria-label="Operational modules"><a href="{{ route('operational.requests',['companySlug'=>$company->slug]) }}" class="inline-flex items-center rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold text-[#17191F]">Leave, Conveyance & Notifications →</a></nav>
@if(session('status'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<div class="rounded-2xl border border-slate-200 bg-white p-6"><h1 class="text-2xl font-extrabold">Attendance</h1><p class="mt-2 text-sm text-slate-500">Records below belong to your authorized company and Home Partner workspace. Times shown in {{ $company->timezone }}.</p>
@if($membership->role === 'student')
 <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-5">
 @if($openSession)
 <p class="text-sm font-bold">Session active: {{ ucfirst($openSession->mode) }}</p><p class="mt-2 text-xs text-slate-600">Check-in: {{ $openSession->checked_in_at->setTimezone($company->timezone)->format('d M Y, h:i A') }}</p>
 <form method="POST" action="{{ route('operational.checkout', ['companySlug'=>$company->slug]) }}" class="mt-4">@csrf<button class="rounded-xl bg-[#17191F] px-6 py-3 text-sm font-bold text-white">Check Out</button></form>
 @else
 <form method="POST" action="{{ route('operational.checkin', ['companySlug'=>$company->slug]) }}" class="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">@csrf
 <label class="text-xs font-bold">Work mode<select name="mode" required class="mt-2 w-full rounded-lg border border-slate-200 bg-white p-3 text-sm"><option value="office">Office</option><option value="client">Client site</option></select></label>
 <label class="text-xs font-bold">Client site (required for Client)<input name="client_site" maxlength="200" placeholder="Client location" class="mt-2 w-full rounded-lg border border-slate-200 bg-white p-3 text-sm"></label>
 <button class="rounded-xl bg-[#FCDF02] px-6 py-3 text-sm font-extrabold text-[#17191F]">Check In</button>
 </form>
 @endif
 </div>
@endif
</div>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white"><div class="border-b border-slate-100 p-5"><h2 class="text-lg font-extrabold">Recent Attendance Days</h2><p class="mt-1 text-xs text-slate-500">Last 30 accessible day records, newest first. Incomplete sessions are not treated as full working days.</p></div>
<div class="overflow-x-auto"><table class="w-full min-w-[520px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-500"><tr><th class="px-5 py-3">Date</th><th class="px-4 py-3">Membership ID</th><th class="px-4 py-3">Status</th><th class="px-5 py-3">Workspace</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($days as $day)<tr><td class="px-5 py-4">{{ \Carbon\CarbonImmutable::parse($day->work_date)->format('d M Y') }}</td><td class="px-4 py-4">{{ $day->membership_id }}</td><td class="px-4 py-4">{{ ucfirst($day->status) }}</td><td class="px-5 py-4">{{ $day->workspace_id }}</td></tr>
@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">No recorded attendance days yet.</td></tr>@endforelse
</tbody></table></div></section>
</main></body></html>