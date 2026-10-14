@extends('layouts.erp')
@section('title','Monthly Payroll')
@section('content')
<section><h2 class="text-2xl font-extrabold">Allowance & Payroll</h2><p class="mt-2 max-w-3xl text-sm text-slate-500">Monthly allowance snapshots and approved claims for your Home Partner. <strong>Payment must actually occur outside this app before selecting Record Paid.</strong> No automatic deductions.</p></section>
@if($actor->role==='partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Create Monthly Payroll Draft</h3><form action="{{ route('erp.payroll.generate',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-3">@csrf<label class="text-xs font-bold">Month<input type="month" name="month" required class="mt-2 block rounded-xl border border-slate-300 p-3 text-sm"></label><button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Generate Draft</button></form><p class="mt-3 text-xs text-slate-500">Each Partner workspace has one run per period. Review freezes approved claims and allowance snapshots.</p></section>
@endif
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Payroll Statement</h3>
@if($run)<p class="mt-1 text-xs text-slate-500">{{ $run->period_month }} · Status: <strong>{{ strtoupper($run->status) }}</strong></p>@endif</div>
<div class="overflow-x-auto"><table class="w-full min-w-[580px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-4">Student</th><th class="p-4">Allowance</th><th class="p-4">Claims</th><th class="p-4">Total BDT</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($lines as $line)<tr><td class="p-4 font-bold">{{ $line->member_name }}</td><td class="p-4">{{ number_format((float)$line->base_allowance,2) }}</td><td class="p-4">{{ number_format((float)$line->approved_claims,2) }}</td><td class="p-4 font-extrabold">{{ number_format((float)$line->total_amount,2) }}</td></tr>
@empty<tr><td colspan="4" class="p-8 text-center text-slate-500">No payroll statement for your scope.</td></tr>@endforelse</tbody></table></div>
@if($actor->role==='partner' && $run)
<div class="border-t border-slate-100 p-5">
<p class="mb-3 text-xs text-slate-500">Current state: <strong>{{ strtoupper($run->status) }}</strong>. Transitions are audited and cannot be repeated.</p>
@if(in_array($run->status,['draft','reviewed','approved','paid']))
<form action="{{ route('erp.payroll.advance',['firmSlug'=>$firmSlug,'runId'=>$run->id]) }}" method="POST" class="flex flex-wrap items-end gap-3">@csrf
@if($run->status==='approved')<label class="text-xs font-bold">External Payment Reference<input type="text" name="payment_reference" required minlength="5" maxlength="120" class="mt-2 block rounded-xl border border-slate-300 p-3 text-sm" placeholder="Transfer / batch reference"></label>@endif
@php $action=['draft'=>'review','reviewed'=>'approve','approved'=>'record_paid','paid'=>'lock'][$run->status]; @endphp
<button type="submit" name="action" value="{{ $action }}" class="rounded-xl bg-[#17191F] px-5 py-3 text-sm font-extrabold text-white">{{ ['review'=>'Review & Freeze Snapshot','approve'=>'Approve Payroll','record_paid'=>'Record Externally Paid','lock'=>'Lock Finalized Period'][$action] }}</button>
</form>
@endif
</div>@endif
</div>
<aside class="space-y-4"><div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Payroll Periods</h3><div class="mt-4 space-y-2">@forelse($runs as $entry)<a href="{{ route('erp.payroll',['firmSlug'=>$firmSlug,'run'=>$entry->id]) }}" class="flex items-center justify-between rounded-xl border border-slate-100 p-3 text-xs hover:bg-slate-50"><strong>{{ $entry->period_month }}</strong><span class="capitalize text-slate-500">{{ $entry->status }}</span></a>@empty<p class="text-xs text-slate-500">No payroll runs yet.</p>@endforelse</div></div>
<div class="rounded-2xl bg-[#17191F] p-5 text-white"><h3 class="text-sm font-extrabold">Required Sequence</h3><p class="mt-3 text-xs leading-relaxed text-slate-300">Draft → Review → Approve → Record Paid → Lock</p><p class="mt-3 text-xs text-amber-200">Do not mark Paid before bank/mobile disbursement has actually been verified. This application does not transfer money.</p></div></aside>
</section>
@endsection