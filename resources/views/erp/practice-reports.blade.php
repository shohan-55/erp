@extends('layouts.erp')
@section('title','Advanced Practice Reports')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">CONFIDENTIAL · PARTNER REPORTS</p>
<h2 class="mt-2 text-2xl font-extrabold">Practice Operations Report</h2>
<p class="mt-2 text-sm text-slate-600">Consolidated, read-only overview for your independent Partner unit. Recorded Sessions do not imply verified attendance days; approved leave request counts do not imply absences. Only finalized locked payroll is included. No automatic deduction is calculated.</p></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5">
<form method="GET" action="{{ route('erp.reports.practice',['firmSlug'=>$firmSlug]) }}" class="flex flex-wrap items-end gap-3">
<label class="text-xs font-bold">Report Month<input type="month" name="month" required value="{{ $month }}" class="mt-2 block rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-lg bg-[#FCDF02] px-5 py-3 text-xs font-extrabold">Review Month</button>
<a href="{{ route('erp.reports.practice.export',['firmSlug'=>$firmSlug,'month'=>$month]) }}" class="rounded-lg border border-slate-300 px-5 py-3 text-xs font-bold">Export Private CSV</a>
</form></section>
<section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
@foreach([
['label'=>'Students (including historical)','value'=>$report['totals']['members']],
['label'=>'Recorded Sessions','value'=>$report['totals']['sessions']],
['label'=>'Late Sessions','value'=>$report['totals']['late']],
['label'=>'Open Sessions','value'=>$report['totals']['open']],
['label'=>'Approved Leave Requests','value'=>$report['totals']['leave_requests']],
['label'=>'Approved Reimbursement BDT','value'=>'৳ '.number_format($report['totals']['approved_claim_bdt'],2)],
['label'=>'Approved / Billable Hours','value'=>number_format($report['totals']['approved_hours'],2).' / '.number_format($report['totals']['billable_hours'],2)],
['label'=>'Locked Payroll BDT','value'=>'৳ '.number_format($report['totals']['locked_payroll_bdt'],2)],
] as $card)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs text-slate-500">{{ $card['label'] }}</p>
<p class="mt-2 text-xl font-extrabold">{{ $card['value'] }}</p></div>
@endforeach
</section>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="font-extrabold">Monthly Student Detail — {{ $month }}</h3>
<p class="mt-2 text-xs text-slate-500">Locked payroll is reported only where a finalized locked payroll line exists. Unpaid approved claims are not equivalent to settled payments.</p></div>
<div class="overflow-x-auto"><table class="min-w-full text-left text-xs">
<thead class="bg-slate-50"><tr>
@foreach(['Student','Sessions','Late','Open','Leave Requests','Approved Claims (BDT)','Approved Hours','Billable Hours','Locked Net (BDT)'] as $label)
<th class="whitespace-nowrap px-4 py-3 font-extrabold">{{ $label }}</th>@endforeach
</tr></thead>
<tbody class="divide-y divide-slate-100">
@forelse($report['rows'] as $member)
<tr>
<td class="whitespace-nowrap px-4 py-3 font-bold">{{ $member->name }} <span class="text-slate-400">#{{ $member->member_id }}</span></td>
<td class="px-4 py-3">{{ $member->sessions }}</td>
<td class="px-4 py-3">{{ $member->late }}</td>
<td class="px-4 py-3">{{ $member->open }}</td>
<td class="px-4 py-3">{{ $member->leave_requests }}</td>
<td class="px-4 py-3">{{ number_format($member->approved_claim_bdt,2) }}</td>
<td class="px-4 py-3">{{ number_format($member->approved_hours,2) }}</td>
<td class="px-4 py-3">{{ number_format($member->billable_hours,2) }}</td>
<td class="whitespace-nowrap px-4 py-3">{{ $member->locked_payroll_bdt===null ? 'Not locked' : number_format((float)$member->locked_payroll_bdt,2) }}</td>
</tr>
@empty<tr><td class="p-6 text-slate-500" colspan="9">No students in this Partner workspace.</td></tr>@endforelse
</tbody></table></div></section>
@endsection
