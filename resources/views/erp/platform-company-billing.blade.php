<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Company Billing · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="bg-[#17191F] px-6 py-5 text-white"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-4"><h1 class="text-xl font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span> · Billing</h1><a href="{{ route('erp.platform.companies.show',['companyId'=>$company->id]) }}" class="rounded-lg border border-white/25 px-4 py-2 text-xs font-bold">← Company Accounts</a></div></header>
<main class="mx-auto max-w-6xl space-y-5 px-4 py-7 sm:px-8">
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<section><p class="text-xs font-bold uppercase tracking-widest text-amber-800">CENTRAL SaaS · COMMERCIAL CONTROL</p><h2 class="mt-1 text-3xl font-extrabold">{{ $company->name }} — Subscription Billing</h2>
<p class="mt-2 text-sm text-slate-600">Firm #{{ $company->id }} · {{ $workspaceCount }} Partner workspace(s) · {{ $membershipCount }} Membership(s). Billing belongs to the company, never individual Partner payroll. No automatic payment collection, company suspension, or domain DNS modification.</p></section>
@if(!$subscription)
<section class="rounded-2xl border bg-white p-5 shadow-sm"><h3 class="font-extrabold">Assign First SaaS Plan</h3>
<p class="mt-1 text-xs text-slate-500">Existing companies remain unbilled until explicitly enrolled. Choose a plan that supports current size. Enrolment never suspends the company.</p>
<form method="POST" action="{{ route('erp.platform.billing.subscribe',['companyId'=>$company->id]) }}" class="mt-4 grid gap-3 sm:grid-cols-3">@csrf
<label class="text-xs font-bold">Plan<select name="plan_id" required class="mt-1 w-full rounded-lg border p-3 text-sm"><option value="">Choose Plan</option>
@foreach($plans as $plan)<option value="{{ $plan->id }}">{{ $plan->name }} · ৳ {{ number_format((float)$plan->monthly_bdt,2) }}/month · {{ $plan->max_partner_workspaces }} Partners</option>@endforeach
</select></label>
<label class="text-xs font-bold">Grace Period (days)<input name="grace_days" type="number" min="1" max="30" required value="7" class="mt-1 w-full rounded-lg border p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Assign Plan (No Charge)</button>
</form></section>
@else
<section class="grid gap-3 sm:grid-cols-3">
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Plan</p><p class="mt-2 text-xl font-extrabold">{{ $subscription->plan_name }}</p><p class="mt-1 text-xs text-slate-500">Custom domain: {{ $subscription->custom_domain_enabled ? 'Included' : 'Not included' }}</p></div>
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Effective Status</p><p class="mt-2 text-xl font-extrabold">{{ strtoupper($effective) }}</p><p class="mt-1 text-xs text-slate-500">Status updates by period dates; company access is not automatically suspended.</p></div>
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Current Paid Period (end exclusive)</p><p class="mt-2 text-xl font-extrabold">{{ $subscription->current_period_end ?? 'Unfunded' }}</p><p class="mt-1 text-xs text-slate-500">{{ $subscription->grace_days }} days grace after expiry</p></div>
</section>
<section class="rounded-2xl border bg-white p-5"><h3 class="font-extrabold">Issue Next Invoice</h3>
<p class="mt-2 text-xs text-slate-500">1, 3, 6 or 12 months. An open/part-paid invoice must be settled before another can be created. Every invoice snapshots the quoted amount and dates. Payment approval does not initiate a transfer.</p>
<form method="POST" action="{{ route('erp.platform.billing.invoice',['companyId'=>$company->id]) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
<label class="text-xs font-bold">Billing Cycle<select name="months" class="mt-1 block rounded-lg border p-3 text-sm"><option value="1">1 month</option><option value="3">3 months</option><option value="6">6 months</option><option value="12">12 months</option></select></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Issue Invoice (Unpaid)</button>
</form></section>
@endif
<section class="space-y-4"><h3 class="font-extrabold">Invoices and Manual Payment Record</h3>
@forelse($invoices as $invoice)
<article class="rounded-2xl border bg-white p-5">
<div class="flex flex-wrap justify-between gap-3">
<div><p class="text-sm font-extrabold">{{ $invoice->invoice_number }} · {{ $invoice->plan_name_snapshot }}</p><p class="mt-2 text-xs text-slate-500">Coverage {{ $invoice->period_start }} → {{ $invoice->period_end }} (exclusive) · Due {{ $invoice->due_on }} · {{ $invoice->months }} months</p></div>
<div class="text-right"><p class="text-xs font-bold">{{ strtoupper($invoice->status) }}</p><p class="mt-1 text-lg font-extrabold">৳ {{ number_format((float)$invoice->paid_amount,2) }} / ৳ {{ number_format((float)$invoice->total_amount,2) }}</p></div>
</div>
@if(in_array($invoice->status,['pending','partial']))
<form method="POST" action="{{ route('erp.platform.billing.payment',['companyId'=>$company->id,'invoiceId'=>$invoice->id]) }}" class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2 xl:grid-cols-4">@csrf
<label class="text-xs font-bold">Verified-Externally Channel<select name="method" class="mt-1 w-full rounded-lg border p-2 text-sm"><option value="bank">Bank</option><option value="bkash">bKash</option><option value="nagad">Nagad</option></select></label>
<label class="text-xs font-bold">Unique Transaction Reference<input name="reference" maxlength="120" required minlength="5" class="mt-1 w-full rounded-lg border p-2 text-sm"></label>
<label class="text-xs font-bold">Received Amount (BDT)<input name="amount" type="number" min=".01" step=".01" required class="mt-1 w-full rounded-lg border p-2 text-sm"></label>
<label class="text-xs font-bold xl:col-span-3">Operator Notes<input name="operator_note" maxlength="1000" class="mt-1 w-full rounded-lg border p-2 text-sm"></label>
<button class="rounded-lg bg-slate-900 p-3 text-xs font-bold text-white">Record Payment Evidence (Pending)</button>
</form>@endif
@foreach($payments->where('invoice_id',$invoice->id) as $payment)
<div class="mt-3 rounded-xl bg-slate-50 p-3">
<p class="text-xs font-bold">{{ strtoupper($payment->method) }} · {{ $payment->reference }} · ৳ {{ number_format((float)$payment->amount,2) }} · {{ strtoupper($payment->status) }}</p>
@if($payment->status==='pending')
<form method="POST" action="{{ route('erp.platform.billing.payment.decide',['companyId'=>$company->id,'paymentId'=>$payment->id]) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
<label class="text-xs font-bold sm:col-span-2">Mandatory Reconciliation Note<input name="operator_note" required minlength="12" maxlength="1000" class="mt-1 w-full rounded-lg border p-2 text-sm" placeholder="Reference independently checked against bank / merchant settlement"></label>
<label class="flex gap-2 text-xs font-bold sm:col-span-2"><input type="checkbox" name="verified_externally" value="1"> I independently verified receipt in the external payment account before approval.</label>
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 p-2 text-xs font-bold text-white">Approve Verified Payment</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 p-2 text-xs font-bold text-white">Reject Evidence</button>
</form>
@else<p class="mt-1 text-xs text-slate-500">{{ $payment->operator_note }}</p>@endif
</div>
@endforeach
</article>
@empty<p class="rounded-xl border bg-white p-5 text-sm text-slate-500">No invoices have been issued to this company.</p>@endforelse
</section>
</main></body></html>
