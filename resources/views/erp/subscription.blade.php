@extends('layouts.erp')
@section('title','SaaS Subscription')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">FIRM-LEVEL COMMERCIAL INFORMATION</p><h2 class="mt-1 text-2xl font-extrabold">Subscription & Billing Status</h2>
<p class="mt-2 text-sm text-slate-600">View your firm's SaaS subscription and invoices. Subscription billing belongs to the entire company, not an individual Partner's payroll or expenses. Contact the DhakaWala Platform Operator to verify manual payments or request renewals.</p></section>
@if(!$subscription)
<section class="rounded-2xl border bg-white p-6"><h3 class="font-extrabold">Unbilled Legacy Access</h3>
<p class="mt-2 text-sm text-slate-600">This company has not yet been enrolled into paid SaaS billing. Existing staff accounts and workspaces remain available; no invoice or payment is implied.</p></section>
@else
<section class="grid gap-3 sm:grid-cols-3">
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Current Plan</p><p class="mt-2 text-xl font-extrabold">{{ $subscription->plan_name }}</p></div>
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Subscription Status</p><p class="mt-2 text-xl font-extrabold">{{ strtoupper($status) }}</p><p class="mt-1 text-xs text-slate-500">Unpaid does not automatically suspend tenant access.</p></div>
<div class="rounded-2xl border bg-white p-5"><p class="text-xs text-slate-500">Current Paid Period Ends</p><p class="mt-2 text-xl font-extrabold">{{ $subscription->current_period_end ?? 'Pending Payment' }}</p><p class="mt-1 text-xs text-slate-500">{{ $subscription->grace_days }}-day post-expiry grace · Custom domain {{ $subscription->custom_domain_enabled ? 'included' : 'not included' }}</p></div>
</section>
@endif
<section class="rounded-2xl border bg-white p-5 shadow-sm"><h3 class="font-extrabold">Company Invoices</h3>
<p class="mt-2 text-xs text-slate-500">Reference-only records. Only actual externally verified payments are marked paid. No payment transaction IDs, bank credentials or private payroll details are displayed.</p>
<div class="mt-4 divide-y">
@forelse($invoices as $invoice)
<div class="flex flex-wrap items-center justify-between gap-3 py-4">
<div><p class="text-sm font-extrabold">{{ $invoice->invoice_number }} · {{ $invoice->plan_name_snapshot }}</p><p class="mt-1 text-xs text-slate-500">{{ $invoice->period_start }} to {{ $invoice->period_end }} (exclusive) · Due {{ $invoice->due_on }}</p></div>
<div class="text-right"><p class="text-sm font-extrabold">৳ {{ number_format((float)$invoice->paid_amount,2) }} / ৳ {{ number_format((float)$invoice->total_amount,2) }}</p><p class="mt-1 text-xs font-bold text-amber-800">{{ ucfirst($invoice->status) }}</p></div>
</div>
@empty<p class="py-5 text-sm text-slate-500">No invoice issued yet.</p>@endforelse
</div></section>
@endsection
