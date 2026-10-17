@extends('layouts.erp')
@section('title','Practice Management')
@section('content')
<section class="rounded-3xl bg-[#17191F] p-6 text-white shadow-sm sm:p-8">
<div class="flex flex-wrap items-center justify-between gap-4">
<div><p class="text-xs font-extrabold uppercase tracking-[.18em] text-[#FCDF02]">{{ $actor->role === 'partner' ? 'PARTNER CONTROL CENTER' : 'MANAGER OPERATIONS CENTER' }}</p>
<h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">Manage {{ $workspace->name }}</h2>
<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300">Attendance schedules, leave rules, conveyance limits, working days, staff reviews and approvals are controlled from your own workspace. Every change is permission-checked and audited.</p></div>
<span class="rounded-full border border-white/25 bg-white/10 px-4 py-2 text-xs font-bold">{{ ucfirst($actor->role) }} access</span>
</div>
<div class="mt-6 flex flex-wrap gap-2 text-xs font-bold">
<a href="{{ route('erp.policies', ['firmSlug'=>$firmSlug]) }}" class="rounded-xl bg-[#FCDF02] px-4 py-3 text-slate-950">Attendance & Weekly Holidays →</a>
<a href="{{ route('erp.attendance.report', ['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/30 px-4 py-3">Attendance Report</a>
<a href="{{ route('erp.leaves', ['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/30 px-4 py-3">Review Leave</a>
<a href="{{ route('erp.claims', ['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/30 px-4 py-3">Review Conveyance</a>
<a href="{{ route('erp.team', ['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/30 px-4 py-3">{{ $actor->role === 'partner' ? 'Manage Staff' : 'My Assigned Team' }}</a>
</div>
</section>

<section class="grid gap-3 sm:grid-cols-3">
@foreach([
['title'=>'Max Leave Per Request','value'=>$rules['leave_max_days'].' days','hint'=>'All leave types; calendar days'],
['title'=>'Annual Leave Notice','value'=>$rules['annual_leave_notice_days'].' days','hint'=>'Sick/emergency excluded'],
['title'=>'Max Conveyance Claim','value'=>'BDT '.number_format($rules['conveyance_max_per_claim'],2),'hint'=>'Per claim; private receipt still required']
] as $metric)
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<p class="text-xs font-semibold text-slate-500">{{ $metric['title'] }}</p>
<p class="mt-3 text-2xl font-extrabold">{{ $metric['value'] }}</p>
<p class="mt-2 text-xs text-slate-500">{{ $metric['hint'] }}</p></div>
@endforeach
</section>
<p class="text-xs text-slate-500">Current effective rules: {{ $rules['effective_date'] ?? 'Existing default policy' }}. Changes start on the first day of a future month and do not alter already-submitted leave, claims or locked payroll.</p>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
<div class="flex flex-wrap items-center justify-between gap-4">
<div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">ATTENDANCE POLICY MANAGEMENT</p>
<h3 class="mt-1 text-xl font-extrabold">Office Hours, Grace Period & Weekly Holidays</h3>
<p class="mt-2 text-sm text-slate-600">Currently {{ substr($attendanceRules['office_start'],0,5) }}–{{ substr($attendanceRules['office_end'],0,5) }} with {{ $attendanceRules['grace_minutes'] }} minutes grace. {{ $actor->role === 'partner' ? 'Save your Partner workspace override directly.' : 'Submit a proposed change for your Partner to approve.' }}</p></div>
<a class="rounded-xl border border-slate-300 px-4 py-2.5 text-xs font-bold" href="{{ route('erp.policies',['firmSlug'=>$firmSlug]) }}">Company Policy & Votes →</a>
</div>
<form method="POST" action="{{ route('erp.manage.attendance.propose',['firmSlug'=>$firmSlug]) }}" class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@csrf
<label class="text-xs font-bold text-slate-700 xl:col-span-4">Effective first of month
<input type="date" name="effective_date" min="{{ $firstFutureMonth=now($company->timezone)->addMonth()->startOfMonth()->toDateString() }}" value="{{ $firstFutureMonth }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Office start
<input type="time" name="office_start" value="{{ substr($attendanceRules['office_start'],0,5) }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Office end
<input type="time" name="office_end" value="{{ substr($attendanceRules['office_end'],0,5) }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 xl:col-span-2">Grace minutes (0–30)
<input type="number" name="grace_minutes" min="0" max="30" value="{{ $attendanceRules['grace_minutes'] }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<fieldset class="sm:col-span-2 xl:col-span-4"><legend class="text-xs font-bold text-slate-700">Weekly Holidays (1 to 3 days)</legend>
<div class="mt-2 flex flex-wrap gap-4">
@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $weekday=>$weekdayLabel)
<label class="flex items-center gap-2 text-xs"><input type="checkbox" name="weekly_holidays[]" value="{{ $weekday }}" @checked(in_array($weekday,$attendanceRules['weekly_holidays'],true))>{{ $weekdayLabel }}</label>
@endforeach</div></fieldset>
<label class="text-xs font-bold text-slate-700 sm:col-span-2 xl:col-span-4">Reason for the schedule adjustment
<textarea name="reason" minlength="15" maxlength="1000" required rows="2" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#17191F] px-5 py-3 text-sm font-extrabold text-white sm:col-span-2 xl:col-span-4">{{ $actor->role === 'partner' ? 'Authorize Future Attendance Schedule' : 'Send Attendance Rules to Partner' }}</button>
</form>
<div class="mt-6 border-t border-slate-200 pt-4">
<h4 class="text-sm font-extrabold">Attendance Change Requests</h4>
<div class="mt-3 grid gap-3 md:grid-cols-2">
@forelse($attendanceRequests as $change)
<div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
<div class="flex flex-wrap justify-between gap-2"><strong class="text-xs">{{ $change->proposer_name }} · {{ $change->effective_date }}</strong><span class="text-xs font-bold">{{ strtoupper($change->status) }}</span></div>
<p class="mt-2 text-xs text-slate-600">{{ substr($change->office_start,0,5) }}–{{ substr($change->office_end,0,5) }} · Grace {{ $change->grace_minutes }} minutes · Holidays {{ implode(', ',json_decode($change->weekly_holidays,true) ?: []) }}</p>
<p class="mt-1 text-xs text-slate-500">{{ $change->reason }}</p>
@if($actor->role === 'partner' && $change->status === 'pending')
<form method="POST" action="{{ route('erp.manage.attendance.decide',['firmSlug'=>$firmSlug,'id'=>$change->id]) }}" class="mt-3 flex gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">Reject</button>
</form>@endif
</div>
@empty<p class="py-3 text-xs text-slate-500">No attendance rule proposals.</p>@endforelse
</div></div>
</section>
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(300px,0.95fr)]">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">LEAVE & CONVEYANCE POLICY</p>
<h3 class="mt-1 text-lg font-extrabold">{{ $actor->role === 'partner' ? 'Approve Workspace Rules' : 'Request a Workspace Policy Change' }}</h3>
<p class="mt-2 text-sm text-slate-600">{{ $actor->role === 'partner' ? 'As Partner, you can approve new limits for your own Partner workspace. They never change company-wide policies.' : 'Managers can propose limits and reasons. They take effect only after the owning Partner approves.' }}</p>
@php $firstFutureMonth=now($company->timezone)->addMonth()->startOfMonth()->toDateString(); @endphp
<form action="{{ route('erp.manage.rules.propose', ['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-4 sm:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700 sm:col-span-2">Effective month (first day only)
<input type="date" name="effective_date" min="{{ $firstFutureMonth }}" value="{{ $firstFutureMonth }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Maximum consecutive leave days
<input type="number" name="leave_max_days" min="1" max="61" value="{{ $rules['leave_max_days'] }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Annual leave notice (days)
<input type="number" name="annual_leave_notice_days" min="0" max="30" value="{{ $rules['annual_leave_notice_days'] }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 sm:col-span-2">Conveyance claim limit (BDT)
<input type="number" name="conveyance_max_per_claim" min="1" max="50000" step="0.01" value="{{ $rules['conveyance_max_per_claim'] }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 sm:col-span-2">Reason for change
<textarea name="reason" minlength="15" maxlength="1000" rows="3" required placeholder="Explain this policy and its operational justification" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2">{{ $actor->role === 'partner' ? 'Approve Future Workspace Policy' : 'Submit Policy for Partner Approval' }}</button>
</form>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">CALENDAR MANAGEMENT</p>
<h3 class="mt-1 text-lg font-extrabold">Special Holidays & Working Days</h3>
<p class="mt-2 text-sm text-slate-600">{{ $actor->role === 'partner' ? 'Authorize future exceptions for your own Partner workspace.' : 'Propose a day change; your Partner provides final approval.' }} Changes never retroactively modify finalized payroll.</p>
<form action="{{ route('erp.manage.holidays.propose', ['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 space-y-4">@csrf
<label class="block text-xs font-bold text-slate-700">Future date
<input type="date" name="work_date" min="{{ now($company->timezone)->addDay()->toDateString() }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Day classification
<select name="day_type" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"><option value="holiday">Special Holiday</option><option value="working">Special Working Day</option></select></label>
<label class="block text-xs font-bold text-slate-700">Reason / authorization note
<textarea name="reason" minlength="15" maxlength="1000" rows="3" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="w-full rounded-xl bg-[#17191F] px-5 py-3 text-sm font-extrabold text-white">{{ $actor->role === 'partner' ? 'Authorize Day Change' : 'Request Day Change' }}</button></form>
</section>
</div>
<section class="grid gap-5 xl:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Policy Requests & History</h3>
<div class="mt-4 space-y-3">
@forelse($policyRequests as $change)
<article class="rounded-xl border border-slate-200 p-4">
<div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-bold">{{ $change->proposer_name }} · Effective {{ $change->effective_date }}</p><span class="text-xs font-extrabold {{ $change->status === 'approved' ? 'text-emerald-700' : 'text-amber-800' }}">{{ strtoupper($change->status) }}</span></div>
<p class="mt-2 text-xs text-slate-600">Leave {{ $change->leave_max_days }} days · Notice {{ $change->annual_leave_notice_days }} days · Claim BDT {{ $change->conveyance_max_per_claim }}</p>
<p class="mt-2 text-xs text-slate-500">{{ $change->reason }}</p>
@if($actor->role === 'partner' && $change->status === 'pending')
<form method="POST" action="{{ route('erp.manage.rules.decide', ['firmSlug'=>$firmSlug,'id'=>$change->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">Reject</button>
</form>@endif
</article>
@empty<p class="py-6 text-sm text-slate-500">No policy changes submitted.</p>@endforelse
</div></div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Holiday Requests & Decisions</h3>
<div class="mt-4 space-y-3">
@forelse($holidayRequests as $change)
<article class="rounded-xl border border-slate-200 p-4">
<div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-bold">{{ $change->work_date }} · {{ ucfirst($change->day_type) }}</p><span class="text-xs font-extrabold {{ $change->status === 'approved' ? 'text-emerald-700' : 'text-amber-800' }}">{{ strtoupper($change->status) }}</span></div>
<p class="mt-2 text-xs text-slate-600">Requested by {{ $change->proposer_name }} · {{ $change->reason }}</p>
@if($actor->role === 'partner' && $change->status === 'pending')
<form method="POST" action="{{ route('erp.manage.holidays.decide', ['firmSlug'=>$firmSlug,'id'=>$change->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">Reject</button>
</form>@endif
</article>
@empty<p class="py-3 text-sm text-slate-500">No Manager holiday requests.</p>@endforelse
</div>
<div class="mt-5 border-t border-slate-200 pt-4"><h4 class="text-sm font-bold">Approved Calendar Exceptions</h4>
@forelse($calendar as $day)
<p class="mt-2 text-xs text-slate-600">{{ $day->work_date }} · {{ $day->day_type === 'working' ? 'Working day' : 'Holiday' }}</p>
@empty<p class="mt-2 text-xs text-slate-500">No exceptions scheduled.</p>@endforelse
</div></div>
</section>
<section class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950">
<strong>Governance safeguards:</strong> Company-wide attendance hours and weekly holidays are governed by the existing Partner-vote process. Manager decisions for leave/claims remain preliminary; the owning Partner has final financial approval. No automatic salary deduction, GPS enforcement or employee monitoring is added.
</section>
@endsection
