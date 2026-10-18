@extends('layouts.erp')
@section('title','Attendance & Duty Approvals')
@section('content')
<section>
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">SMART ATTENDANCE · AUDITED WORKFLOW</p>
<h2 class="mt-2 text-2xl font-extrabold">Attendance & Client Duty Requests</h2>
<p class="mt-2 max-w-3xl text-sm text-slate-600">Missed punches and audit-client duty require a reason. Assigned Managers recommend, Partners decide. A request is not a check-in, proof of presence, or payroll adjustment. Only authorized workspace members can access these records.</p>
</section>
@if($actor->role === 'student')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Request Attendance Correction or Client Duty</h3>
<form method="POST" action="{{ route('erp.attendance.reviews.submit',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Request Type
<select name="kind" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="missed_checkout">Missing / Incorrect Check-out</option>
<option value="missed_checkin">Missed Check-in and Check-out</option>
<option value="client_duty">Client Duty Approval (not an attendance punch)</option>
</select></label>
<label class="text-xs font-bold text-slate-700">Work Date
<input type="date" name="work_date" required min="{{ now($company->timezone)->subDays(60)->toDateString() }}" max="{{ now($company->timezone)->toDateString() }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Session (for Check-out Correction)
<select name="attendance_session_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Select a session</option>
@foreach($sessions as $s)
<option value="{{ $s->id }}">{{ $s->work_date }} · {{ \Carbon\Carbon::parse($s->started_at,'UTC')->timezone($company->timezone)->format('h:i A') }} · #{{ $s->id }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Client Name (for Client Duty)
<input type="text" name="client_site" maxlength="180" placeholder="Audit client/site" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Actual Check-in (for Missed Check-in)
<input type="time" name="start_time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Actual Check-out (for Punch Corrections)
<input type="time" name="end_time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Reason and Supporting Explanation
<textarea name="reason" rows="3" required minlength="15" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Explain exactly what happened. The Partner must verify before approval."></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit for Review</button>
</form>
</section>
@endif

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-base font-extrabold">{{ $actor->role === 'student' ? 'My Requests' : 'Authorized Attendance Approval Inbox' }}</h3></div>
<div class="space-y-3 p-4">
@forelse($rows as $row)
<article class="rounded-xl border border-slate-200 p-4">
<div class="flex flex-wrap items-center gap-2">
<strong class="text-sm">{{ $row->student_name }}</strong>
<span class="rounded-lg bg-slate-100 px-2 py-1 text-xs">{{ str_replace('_',' ',ucwords($row->kind,'_')) }}</span>
<span class="text-xs text-slate-600">{{ $row->work_date }}</span>
<span class="rounded-full px-2 py-1 text-xs font-bold {{ $row->status==='approved' ? 'bg-emerald-100 text-emerald-800' : ($row->status==='rejected' ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-900') }}">{{ str_replace('_',' ',ucfirst($row->status)) }}</span>
</div>
<p class="mt-2 text-sm text-slate-700">{{ $row->reason }}</p>
@if($row->client_site)<p class="mt-1 text-xs text-slate-600">Client: {{ $row->client_site }}</p>@endif
@if($row->manager_recommendation)<p class="mt-2 text-xs font-bold">Manager recommended: {{ ucfirst($row->manager_recommendation) }}</p>@endif
@if(in_array($row->status,['pending','manager_reviewed'],true) && in_array($actor->role,['partner','manager'],true))
@if($actor->role === 'partner' || $row->status==='pending')
<form method="POST" action="{{ route('erp.attendance.reviews.decide',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
<label class="min-w-[180px] flex-1 text-xs text-slate-600">Review Note (optional)
<input name="decision_note" maxlength="1000" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-xs"></label>
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">{{ $actor->role==='partner' ? 'Approve' : 'Recommend Approval' }}</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">{{ $actor->role==='partner' ? 'Reject' : 'Recommend Rejection' }}</button>
</form>
@endif
@endif
</article>
@empty
<p class="p-4 text-sm text-slate-500">There are no requests in your authorized scope.</p>
@endforelse
</div>
</section>
@endsection
