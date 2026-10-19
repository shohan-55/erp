@extends('layouts.erp')
@section('title','Monthly Payroll')
@section('content')
<section><h2 class="text-2xl font-extrabold">Allowance & Payroll</h2><p class="mt-2 max-w-3xl text-sm text-slate-500">Monthly allowance snapshots and approved claims for your Home Partner. <strong>Payment must actually occur outside this app before selecting Record Paid.</strong> Automatic attendance or late/half-day salary penalties are not calculated. Drafts require manual Partner review.</p></section>
@include('erp.partials.module-guide',['module'=>'payroll'])
@if($canManage)
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Create Monthly Payroll Draft</h3><form action="{{ route('erp.payroll.generate',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-4 flex flex-wrap items-end gap-3">@csrf<label class="text-xs font-bold">Month<input type="month" name="month" required class="mt-2 block rounded-xl border border-slate-300 p-3 text-sm"></label><button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Generate Draft</button></form><p class="mt-3 text-xs text-slate-500">Each Partner workspace has one run per period. Review freezes approved claims and allowance snapshots.</p></section>
@endif
@if($canManage && $run && $overview)
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Partner Payroll Review — Attendance, Leave & Claims</h3>
<p class="mt-2 text-xs text-slate-600">Read-only reconciliation flags for this payroll period. Verify unresolved items with the assigned staff/Manager. Missing punches, leave or late arrivals never create automatic deductions. Counts are informational, not payroll penalties.</p>
<div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
@foreach([
['label'=>'Recorded Sessions','value'=>$overview['recorded_sessions']],
['label'=>'Late Sessions','value'=>$overview['late_sessions']],
['label'=>'Open Sessions · Review','value'=>$overview['open_sessions']],
['label'=>'Approved Leave','value'=>$overview['approved_leaves']],
['label'=>'Pending Leave · Review','value'=>$overview['pending_leave_requests']],
['label'=>'Attendance Requests · Review','value'=>$overview['pending_attendance_reviews']],
['label'=>'Unpaid Approved Claims','value'=>$overview['approved_unpaid_claims']],
] as $metric)
<div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-600">{{ $metric['label'] }}</p><p class="mt-2 text-2xl font-extrabold tabular-nums">{{ $metric['value'] }}</p></div>
@endforeach
</div>
<a class="mt-4 inline-block rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold" href="{{ route('erp.attendance.reconcile',['firmSlug'=>$firmSlug]) }}">Review Attendance Dates</a>
<a class="mt-4 inline-block rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold" href="{{ route('erp.payroll.register',['firmSlug'=>$firmSlug,'runId'=>$run->id]) }}">Export Private Partner Register</a>
</section>
@if($run->status==='draft')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Explicit Partner-authorized Financial Adjustments</h3>
<p class="mt-2 text-xs text-slate-600">Only active Student lines in this draft. Bonuses and deductions are recorded with a reason and immutable authorization/void history; no automatic leave or lateness penalties. Approved/reviewed payroll cannot be changed.</p>
<form action="{{ route('erp.payroll.adjustments.create',['firmSlug'=>$firmSlug,'runId'=>$run->id]) }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">@csrf
<label class="text-xs font-bold">Student
<select name="membership_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Choose</option>
@foreach($lines as $line)
<option value="{{ $line->membership_id }}">{{ $line->member_name }} (#{{ $line->membership_id }})</option>
@endforeach
</select></label>
<label class="text-xs font-bold">Adjustment Type
<select name="kind" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="bonus">Partner-authorized Addition / Bonus</option>
<option value="deduction">Explicitly Authorized Deduction</option>
</select></label>
<label class="text-xs font-bold">Amount BDT<input name="amount" type="number" step=".01" min=".01" max="500000" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2 xl:col-span-4">Auditable Explanation<textarea name="reason" rows="2" required minlength="15" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></label>
<label class="flex items-start gap-2 text-xs font-bold sm:col-span-2"><input type="checkbox" name="confirm_authorized" value="1" required> I confirm this financial adjustment has been authorized by the Partner and will not be inferred automatically from attendance or leave.</label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold sm:col-span-2">Authorize & Recalculate Draft</button>
</form>
</section>
@endif
@if($adjustments->isNotEmpty())
<section class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="text-sm font-extrabold">Adjustment History (includes voided records)</h3>
@foreach($adjustments as $adjustment)
<div class="mt-3 rounded-lg border border-slate-100 p-3">
<p class="text-xs font-bold">{{ $adjustment->student_name }} · {{ ucfirst($adjustment->kind) }} · ৳ {{ number_format((float)$adjustment->amount,2) }} · {{ $adjustment->voided_at ? 'VOIDED' : 'ACTIVE' }}</p>
<p class="mt-2 text-xs text-slate-500">{{ $adjustment->reason }}</p>
@if(!$adjustment->voided_at && $run->status==='draft')
<form data-corporate-confirm="Void this financial adjustment? The change will remain visible in the audit history." method="POST" action="{{ route('erp.payroll.adjustments.void',['firmSlug'=>$firmSlug,'runId'=>$run->id,'adjustmentId'=>$adjustment->id]) }}" class="mt-2 flex flex-wrap gap-2">@csrf
<input name="reason" minlength="15" maxlength="1000" required placeholder="Reason for voiding (required)" class="min-w-52 flex-1 rounded-lg border border-slate-200 p-2 text-xs">
<button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Void Adjustment</button>
</form>
@endif
</div>
@endforeach
</section>
@endif
@endif
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Payroll Statement</h3>
@if($run)<p class="mt-1 text-xs text-slate-500">{{ $run->period_month }} · Status: <strong>{{ strtoupper($run->status) }}</strong></p>@endif</div>
<div class="overflow-x-auto"><table class="w-full min-w-[580px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-4">Student</th><th class="p-4">Allowance</th><th class="p-4">Approved Claims</th><th class="p-4">Adjustment</th><th class="p-4">Total BDT</th></tr></thead><tbody class="divide-y divide-slate-100">
@forelse($lines as $line)<tr><td class="p-4 font-bold">{{ $line->member_name }}
@if($run && ($canManage || $run->status==='locked'))
<a href="{{ route('erp.payroll.payslip',['firmSlug'=>$firmSlug,'runId'=>$run->id,'memberId'=>$line->membership_id]) }}" class="mt-2 block text-xs font-bold text-amber-900 hover:underline">{{ $run->status==='locked' ? 'View Final Payslip' : 'Preview Payslip' }}</a>
@endif
</td><td class="p-4">{{ number_format((float)$line->base_allowance,2) }}</td><td class="p-4">{{ number_format((float)$line->approved_claims,2) }}</td><td class="p-4">{{ number_format((float)$line->adjustment,2) }}</td><td class="p-4 font-extrabold">{{ number_format((float)$line->total_amount,2) }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center text-slate-500">No payroll statement for your scope.</td></tr>@endforelse</tbody></table></div>
@if($canManage && $run)
<div class="border-t border-slate-100 p-5">
<p class="mb-3 text-xs text-slate-500">Current state: <strong>{{ strtoupper($run->status) }}</strong>. Transitions are audited and cannot be repeated.</p>
@if(in_array($run->status,['paid','locked']) && $run->payment_evidence_path)
<a class="mb-4 inline-flex rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-100" href="{{ route('erp.payroll.evidence',['firmSlug'=>$firmSlug,'runId'=>$run->id]) }}">Download Private Payment Evidence</a>
<p class="mb-4 text-xs text-slate-500">Recorded {{ $run->payment_channel }} · {{ $run->payment_date }} · Reconciled {{ number_format((float) $run->reconciled_total, 2) }} BDT</p>
@endif
@if(in_array($run->status,['draft','reviewed','approved','paid']))
<form data-corporate-confirm="Review this payroll transition carefully. Recording Paid is not a money transfer; only confirm it after payment evidence has been verified. Locked payroll cannot be casually edited." action="{{ route('erp.payroll.advance',['firmSlug'=>$firmSlug,'runId'=>$run->id]) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">@csrf
@if($run->status==='approved')
<div class="w-full rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900">
    Mark Paid only after disbursement occurred externally. Upload supporting evidence and reconcile the exact total of every payroll line. Uploading evidence is not verification by a bank or payment provider.
</div>
<label class="text-xs font-bold">External Payment Reference<input type="text" name="payment_reference" required minlength="5" maxlength="120" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Transfer / batch reference"></label>
<label class="text-xs font-bold">Payment Method
<select name="payment_channel" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select method</option><option value="bank_transfer">Bank Transfer</option>
<option value="mobile_financial_service">Mobile Financial Service</option>
<option value="cash">Cash with signed receipts</option><option value="cheque">Cheque</option>
</select></label>
<label class="text-xs font-bold">Actual Payment Date<input type="date" name="payment_date" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Reconciled Total (BDT)<input type="number" name="reconciled_total" required min="0" step=".01" value="{{ number_format((float) $lines->sum('total_amount'), 2, '.', '') }}" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="w-full text-xs font-bold">Private Evidence (PDF/JPG/PNG up to 5 MB)<input type="file" name="payment_evidence" accept="application/pdf,image/jpeg,image/png" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
@endif
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