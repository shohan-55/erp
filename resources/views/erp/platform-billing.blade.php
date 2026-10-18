<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Subscription Plans · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="bg-[#17191F] px-6 py-5 text-white"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3"><div><p class="text-xl font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span></p><p class="text-xs text-slate-400">Restricted SaaS Billing Console</p></div><a class="rounded-lg border border-white/25 px-4 py-2 text-xs font-bold" href="{{ route('erp.platform.dashboard') }}">Back to Platform</a></div></header>
<main class="mx-auto max-w-6xl space-y-6 p-5 sm:p-8">
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<section><p class="text-xs font-bold uppercase tracking-widest text-amber-800">PLATFORM-ONLY BILLING</p><h1 class="mt-1 text-3xl font-extrabold">Plans & Subscription Administration</h1><p class="mt-2 text-sm text-slate-600">Company-level commercial metadata only. No access to private Partner payroll, HR, attendance, or audit clients. No automatic collection or suspension. Money records require external verification.</p></section>
<section class="grid gap-3 sm:grid-cols-3">
@foreach([['label'=>'Outstanding Invoices','value'=>$stats['pending_invoices']],['label'=>'Unverified Payments','value'=>$stats['pending_payments']],['label'=>'Settled Invoices','value'=>$stats['paid_invoices']]] as $metric)
<div class="rounded-xl border bg-white p-5"><p class="text-xs text-slate-500">{{ $metric['label'] }}</p><p class="mt-3 text-2xl font-extrabold">{{ $metric['value'] }}</p></div>@endforeach
</section>
<section class="rounded-2xl border bg-white p-5"><h2 class="text-lg font-extrabold">Create Immutable-priced Subscription Plan</h2>
<p class="mt-2 text-xs text-slate-500">For changes in pricing, publish a new plan code. Never overwrite amounts on already-issued invoices.</p>
<form method="POST" action="{{ route('erp.platform.billing.plans.create') }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@csrf
<label class="text-xs font-bold">Code<input required name="code" maxlength="50" placeholder="professional_v1" class="mt-1 block w-full rounded-lg border p-3 text-sm"></label>
<label class="text-xs font-bold">Plan Name<input required name="name" maxlength="120" placeholder="Professional" class="mt-1 block w-full rounded-lg border p-3 text-sm"></label>
<label class="text-xs font-bold">Monthly BDT<input required name="monthly_bdt" type="number" min="1" max="99999999" step=".01" class="mt-1 block w-full rounded-lg border p-3 text-sm"></label>
<label class="text-xs font-bold">Maximum Partner Units<input required name="max_partner_workspaces" type="number" value="50" min="1" max="500" class="mt-1 block w-full rounded-lg border p-3 text-sm"></label>
<label class="text-xs font-bold">Maximum Memberships<input required name="max_members" type="number" value="500" min="1" max="100000" class="mt-1 block w-full rounded-lg border p-3 text-sm"></label>
<label class="text-xs font-bold">Custom Domain Included?
<select name="custom_domain_enabled" class="mt-1 block w-full rounded-lg border p-3 text-sm"><option value="0">Not included</option><option value="1">Included (after DNS/HTTPS checks)</option></select></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold sm:col-span-2 lg:col-span-3">Create New Plan</button>
</form>
</section>
<section class="rounded-2xl border bg-white p-5"><h2 class="text-lg font-extrabold">Available / Retired Plans</h2>
<div class="mt-4 grid gap-3 lg:grid-cols-2">
@forelse($plans as $plan)
<div class="rounded-xl border p-4"><div class="flex justify-between gap-3"><h3 class="text-sm font-extrabold">{{ $plan->name }} · {{ $plan->code }}</h3><span class="text-xs">{{ $plan->available ? 'Available' : 'Retired' }}</span></div>
<p class="mt-2 text-xs text-slate-500">৳ {{ number_format((float)$plan->monthly_bdt,2) }} / month · {{ $plan->max_partner_workspaces }} Partner Units · {{ $plan->max_members }} members · Custom Domain {{ $plan->custom_domain_enabled ? 'Included':'Not included' }}</p>
<form method="POST" action="{{ route('erp.platform.billing.plans.availability',['planId'=>$plan->id]) }}" class="mt-3">@csrf
<input type="hidden" name="available" value="{{ $plan->available ? 0 : 1 }}">
<button class="rounded-lg border px-3 py-2 text-xs font-bold">{{ $plan->available?'Retire Plan for New Billing':'Restore Plan' }}</button></form>
</div>
@empty<p class="text-sm text-slate-500">Create the first SaaS plan before assigning any subscription.</p>@endforelse</div></section>
<section class="rounded-2xl border bg-white p-5"><h2 class="text-lg font-extrabold">Current Company Subscriptions</h2>
<div class="mt-4 divide-y">
@forelse($subs as $sub)
<div class="flex flex-wrap items-center justify-between gap-3 py-4">
<div><p class="text-sm font-extrabold">{{ $sub->company_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $sub->plan_name }} · {{ \App\Support\SaasBilling::effectiveStatus($sub) }} · Valid to {{ $sub->current_period_end ?? 'Not funded' }}</p></div>
<a href="{{ route('erp.platform.billing.company',['companyId'=>$sub->company_id]) }}" class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Invoices & Payments</a>
</div>
@empty<p class="py-5 text-xs text-slate-500">No companies explicitly enrolled. Existing ERP companies remain unbilled and untouched.</p>@endforelse
</div></section>
</main></body></html>
