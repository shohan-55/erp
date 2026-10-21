@extends('layouts.erp')
@section('title','Private Payroll Statement')
@section('content')
<style media="print">
@page { size: A4; margin: 16mm }
nav, aside, header, footer, .payroll-no-print { display:none!important }
body { background:white!important; color:black!important }
.payroll-print { box-shadow:none!important; border:1px solid #cbd5e1!important }
</style>
<div class="payroll-no-print flex flex-wrap items-center justify-between gap-3">
<a class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold" href="{{ route('erp.payroll',['firmSlug'=>$firmSlug,'run'=>$run->id]) }}">Back to Payroll</a>
<button onclick="window.print()" type="button" class="rounded-lg bg-[#FCDF02] px-4 py-2 text-xs font-extrabold">Print / Save as PDF</button>
</div>
<section class="payroll-print mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
<div class="flex flex-wrap justify-between gap-4 border-b-2 border-amber-400 pb-5">
@php($payrollBrand = \App\Support\BrandIdentity::profile($company,$workspace))
<div class="flex items-center gap-3">
@if($payrollBrand->logo_asset_id)<img class="h-12 w-12 object-contain" alt="" src="{{ route('erp.brand.asset',['firmSlug'=>$firmSlug,'id'=>$payrollBrand->logo_asset_id]) }}">@endif
<div><p class="text-2xl font-extrabold">{{ $payrollBrand->public_name }}</p>
<p class="text-xs text-slate-600">{{ $payrollBrand->office_address }}</p>
<p class="mt-1 text-sm text-slate-600">{{ $workspace->name }} · Confidential Payroll Statement</p>
</div></div>
<div class="text-right"><p class="text-xs font-bold uppercase tracking-widest text-amber-900">Payslip</p>
<p class="mt-2 text-lg font-extrabold">{{ \Carbon\CarbonImmutable::parse($run->period_month)->format('F Y') }}</p>
<p class="mt-1 text-xs font-semibold">{{ strtoupper($run->status) }} · Run #{{ $run->id }}</p></div>
</div>
@if($run->status!=='locked')
<p class="my-5 rounded-lg bg-amber-50 p-3 text-xs font-bold text-amber-900">PREVIEW ONLY — This payroll is not a finalized locked payslip.</p>
@endif
<div class="mt-6 grid grid-cols-2 gap-3 text-sm">
<div><p class="text-xs text-slate-500">Staff</p><p class="font-bold">{{ $line->member_name }}</p></div>
<div><p class="text-xs text-slate-500">Employee / Membership ID</p><p class="font-bold">#{{ $line->membership_id }}</p></div>
<div><p class="text-xs text-slate-500">Payroll Period</p><p class="font-bold">{{ $run->period_month }}</p></div>
<div><p class="text-xs text-slate-500">Payment Status</p><p class="font-bold">{{ strtoupper($run->status) }}</p></div>
</div>
<table class="mt-8 w-full text-sm">
<thead><tr class="border-b-2 border-slate-200 text-left"><th class="py-3">Description</th><th class="py-3 text-right">BDT</th></tr></thead>
<tbody>
<tr class="border-b border-slate-100"><td class="py-3">Monthly Allowance / Salary</td><td class="text-right tabular-nums">{{ number_format((float)$line->base_allowance,2) }}</td></tr>
<tr class="border-b border-slate-100"><td class="py-3">Approved Expense Reimbursement</td><td class="text-right tabular-nums">{{ number_format((float)$line->approved_claims,2) }}</td></tr>
<tr class="border-b border-slate-100"><td class="py-3">Explicit Partner-authorized Net Adjustment</td><td class="text-right tabular-nums">{{ number_format((float)$line->adjustment,2) }}</td></tr>
<tr class="bg-slate-50 text-lg font-extrabold"><td class="py-4">Net Total</td><td class="py-4 text-right tabular-nums">{{ number_format((float)$line->total_amount,2) }}</td></tr>
</tbody>
</table>
@if($run->payment_date && in_array($run->status,['paid','locked']))
<p class="mt-6 text-xs text-slate-600">Payment recorded: {{ $run->payment_date }} · Method: {{ str_replace('_',' ',ucfirst($run->payment_channel)) }}. Payment evidence is retained privately by the Partner.</p>
@endif
<p class="mt-8 border-t border-slate-200 pt-4 text-xs leading-relaxed text-slate-600">This statement reflects the audited payroll snapshot of this Partner workspace. Attendance flags, missing punches, leave and late arrival do not trigger automated salary penalties. Financial adjustments require a Partner decision. This software does not independently verify external bank transfers.</p>
</section>
@endsection
