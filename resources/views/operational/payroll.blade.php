<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $company->name }} — Payroll</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#20232A]">
<header class="bg-[#17191F] text-white"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4"><div><p class="text-xs font-bold text-[#FCDF02]">LIVE WORKSPACE · {{ ucfirst($actor->role) }}</p><h1 class="mt-1 font-extrabold">{{ $company->name }}</h1></div><div class="flex flex-wrap gap-2"><a href="{{ route('operational.dashboard',['companySlug'=>$company->slug]) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Attendance</a><a href="{{ route('operational.requests',['companySlug'=>$company->slug]) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Leave & Claims</a><form method="POST" action="{{ route('operational.logout',['companySlug'=>$company->slug]) }}">@csrf<button class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Sign Out</button></form></div></div></header>
<main class="mx-auto max-w-6xl space-y-6 px-5 py-8">
<div><p class="text-xs font-bold uppercase tracking-widest text-amber-800">Partner-scoped Financial Workflow</p><h2 class="mt-1 text-2xl font-extrabold">Monthly Allowance & Payroll</h2><p class="mt-2 max-w-3xl text-sm text-slate-500">Allowance rates must be authorized before creating a draft. Approved conveyance claims are allocated once. Late attendance does not automatically deduct money. This application never transfers funds.</p></div>
@if(session('status'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
@if($actor->role === 'partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Generate Monthly Snapshot</h3><p class="mt-2 text-xs leading-relaxed text-slate-600">Only an initial draft can be generated. Every active student must have an authorized allowance version on or before the selected month; otherwise no run is created.</p>
<form method="POST" action="{{ route('operational.payroll.generate',['companySlug'=>$company->slug]) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
<label class="text-xs font-bold">Payroll Month<input type="month" name="month" required value="{{ now($company->timezone)->format('Y-m') }}" class="mt-2 block rounded-xl border border-slate-200 p-3 text-sm"></label>
<button type="submit" class="rounded-xl bg-[#FCDF02] px-5 py-3 text-xs font-extrabold">Generate Draft</button></form>
</section>
@endif
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Payroll Runs</h3>
<div class="mt-4 space-y-4">
@forelse($runs as $run)
<article class="rounded-xl border border-slate-200 p-4">
<div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-extrabold">{{ $run->payroll_month }} · Run #{{ $run->id }}</p><p class="mt-1 text-xs text-slate-500">Partner Workspace #{{ $run->workspace_id }} · Manual disbursement required</p></div><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold uppercase text-amber-900">{{ $run->status }}</span></div>
@if($actor->role === 'partner' && in_array($run->status,['draft','reviewed','approved','paid'],true))
<form method="POST" action="{{ route('operational.payroll.advance',['companySlug'=>$company->slug,'runId'=>$run->id]) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf
@if($run->status === 'draft')<button name="action" value="review" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-bold">Mark Reviewed</button>
@elseif($run->status === 'reviewed')<button name="action" value="approve" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-bold">Approve Snapshot</button>
@elseif($run->status === 'approved')
<label class="text-xs font-bold">Verified External Payment Reference<input name="payment_reference" required minlength="6" maxlength="150" placeholder="Verified batch reference" class="mt-2 block w-60 rounded-xl border border-slate-200 p-3 text-xs"></label>
<button name="action" value="mark_paid" class="rounded-xl bg-[#17191F] px-4 py-2.5 text-xs font-bold text-white">Mark Externally Paid</button>
@elseif($run->status === 'paid')<button name="action" value="lock" class="rounded-xl bg-[#17191F] px-4 py-2.5 text-xs font-bold text-white">Lock Finalized Payroll</button>
@endif
</form>
@endif
@if($run->payment_reference && $actor->role === 'partner')<p class="mt-3 text-xs text-slate-500">Recorded payment reference: {{ $run->payment_reference }}</p>@endif
</article>
@empty<p class="text-sm text-slate-500">No visible payroll runs.</p>@endforelse
</div>
</section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 px-5 py-4"><h3 class="text-base font-extrabold">{{ $actor->role === 'student' ? 'My Finalized Payroll Statements' : 'Payable Snapshot Items' }}</h3><p class="mt-1 text-xs text-slate-500">Students can only view their own locked records. Every snapshot records effective allowance, claim allocations and attendance metadata.</p></div>
<div class="overflow-x-auto"><table class="w-full min-w-[650px] text-left text-sm"><thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Run</th><th class="px-4 py-3">Member</th><th class="px-4 py-3">Base</th><th class="px-4 py-3">Claims</th><th class="px-5 py-3">Payable</th></tr></thead>
<tbody class="divide-y divide-slate-100">
@forelse($items as $item)<tr><td class="px-5 py-4">#{{ $item->payroll_run_id }}</td><td class="px-4 py-4">#{{ $item->membership_id }}</td><td class="px-4 py-4">৳ {{ number_format($item->base_amount,2) }}</td><td class="px-4 py-4">৳ {{ number_format($item->approved_claim_total,2) }}</td><td class="px-5 py-4 font-extrabold">৳ {{ number_format($item->payable_amount,2) }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-sm text-slate-500">No visible payroll items yet.</td></tr>@endforelse
</tbody></table></div>
</section>
@if($actor->role === 'partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Payroll Audit Trail</h3>
<p class="mt-1 text-xs text-slate-500">Append-only transition history for this Partner workspace. No bank transfer is performed by the application.</p>
<div class="mt-4 divide-y divide-slate-100">
@forelse($auditEvents as $event)
<div class="flex flex-wrap items-center justify-between gap-3 py-3 text-xs"><div><p class="font-bold">{{ ucfirst(str_replace('_',' ',$event->action)) }} · Run #{{ $event->payroll_run_id }}</p><p class="mt-1 text-slate-500">{{ $event->previous_status ?: 'New' }} → {{ $event->new_status }} · Actor membership #{{ $event->actor_membership_id }}</p></div><time class="text-slate-400">{{ $event->created_at?->setTimezone($company->timezone)->format('d M Y h:i A') }}</time></div>
@empty<p class="py-3 text-xs text-slate-500">No payroll transitions recorded.</p>@endforelse
</div></section>
@endif
</main></body></html>