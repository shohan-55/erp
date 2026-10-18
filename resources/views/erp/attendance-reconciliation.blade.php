@extends('layouts.erp')
@section('title','Daily Attendance Review')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">SMART ATTENDANCE · READ-ONLY</p>
<h2 class="text-2xl font-extrabold">Daily Attendance Reconciliation</h2>
<p class="max-w-3xl text-sm leading-relaxed text-slate-600">This review reflects your authorized Partner workspace and assigned team only. Missing check-ins, early departures and incomplete sessions are provisional review flags, not automatic salary deductions or confirmed absences. Approved leave and Student-specific shifts are respected.</p>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<form method="GET" action="{{ route('erp.attendance.reconcile',['firmSlug'=>$firmSlug]) }}" class="flex flex-wrap items-end gap-3">
<label class="text-xs font-bold text-slate-700">Review Date (last 60 days)
<input type="date" name="date" required value="{{ $date }}" min="{{ now($company->timezone)->subDays(60)->toDateString() }}" max="{{ now($company->timezone)->toDateString() }}" class="mt-2 block rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-xs font-extrabold">Review Date</button>
<a href="{{ route('erp.attendance.report',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-slate-300 px-5 py-3 text-xs font-extrabold">Monthly Session Summary</a>
<a href="{{ route('erp.attendance.reviews',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-amber-300 px-5 py-3 text-xs font-extrabold">Duty & Punch Approvals</a>
</form>
</section>

<section class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6" aria-label="Reconciliation summary">
@foreach([
['label'=>'Students','value'=>$summary['total']],
['label'=>'Present / Late','value'=>$summary['present']+$summary['late']],
['label'=>'Client Duty','value'=>$summary['client_duty']],
['label'=>'Approved Leave','value'=>$summary['leave']],
['label'=>'Holiday','value'=>$summary['holiday']],
['label'=>'Needs Review','value'=>$summary['review']+$summary['missing_punch']+$summary['early_exit']+$summary['duty_authorized']+$summary['client_duty_review']+$summary['half_leave']],
] as $metric)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<p class="text-[11px] font-semibold text-slate-500">{{ $metric['label'] }}</p>
<p class="mt-3 text-3xl font-extrabold tabular-nums">{{ $metric['value'] }}</p>
</div>
@endforeach
</section>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Student Day Status · {{ $date }}</h3><p class="mt-1 text-xs text-slate-500">Server-recorded presence and approved scheduling, never GPS or private payroll</p></div>
<div class="overflow-x-auto"><table class="w-full min-w-[750px] text-left text-sm">
<thead class="bg-slate-50 text-xs text-slate-600"><tr>
<th class="p-4">Student</th><th class="p-4">Status</th><th class="p-4">Scheduled Hours</th><th class="p-4">Sessions</th><th class="p-4">Review Notes</th>
</tr></thead><tbody class="divide-y divide-slate-100">
@forelse($rows as $row)
<tr>
<td class="p-4 font-bold">{{ $row->student_name }}</td>
<td class="p-4"><span class="inline-flex rounded-full px-3 py-1 text-xs font-bold {{ in_array($row->status_key,['review','missing_punch','early_exit','duty_authorized','client_duty_review','half_leave'],true) ? 'bg-amber-100 text-amber-950' : ($row->status_key==='holiday' || $row->status_key==='leave' ? 'bg-slate-100 text-slate-800' : 'bg-emerald-50 text-emerald-800') }}">{{ $row->status_label }}</span></td>
<td class="p-4">{{ $row->holiday ? 'Off day' : $row->scheduled_start.'–'.$row->scheduled_end }}</td>
<td class="p-4">{{ $row->session_count }}</td>
<td class="p-4 text-xs text-slate-600">
@if($row->has_conflict)
Attendance overlaps with approved leave — human review required.
@elseif(in_array($row->status_key,['review','missing_punch','early_exit','duty_authorized','client_duty_review','half_leave'],true))
Verify with Student and the authorized Partner before any action.
@else
Recorded status only; no payroll adjustment.
@endif
</td>
</tr>
@empty
<tr><td colspan="5" class="p-8 text-center text-sm text-slate-500">No active students in this authorized scope.</td></tr>
@endforelse
</tbody></table></div>
<p class="border-t border-slate-100 p-5 text-xs text-slate-500">Review findings do not amend attendance, approve leave or alter payroll. Any missing punch correction must use the existing authorized, audited Partner workflow.</p>
</section>
@endsection
