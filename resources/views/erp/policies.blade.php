@extends('layouts.erp')
@section('title','Policy & Governance')
@section('content')
<section><p class="text-[11px] font-extrabold uppercase tracking-[.15em] text-amber-800">COMPANY POLICY · HOME PARTNER GOVERNANCE</p><h2 class="mt-1 text-2xl font-extrabold">Versioned Policies & Approvals</h2><p class="mt-2 text-sm leading-relaxed text-slate-500">Company defaults require separate unanimous Partner votes. Workspace overrides apply only to your own Partner, starting on the first day of a future month.</p></section>
<section class="grid gap-3 sm:grid-cols-4">
@foreach([
['title'=>'Office Start','value'=>substr($effectivePolicy['office_start'],0,5)],
['title'=>'Office End','value'=>substr($effectivePolicy['office_end'],0,5)],
['title'=>'Late Grace','value'=>$effectivePolicy['grace_minutes'].' minutes'],
['title'=>'Policy Effective','value'=>$effectivePolicy['effective_on']],
] as $item)
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs font-semibold text-slate-500">{{ $item['title'] }}</p><p class="mt-3 text-xl font-extrabold">{{ $item['value'] }}</p></div>
@endforeach
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
  <div>
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">SCHEDULE EXCEPTIONS</p>
    <h3 class="mt-1 text-lg font-extrabold">Special Working Days & Holidays</h3>
    <p class="mt-2 text-sm text-slate-600">A future holiday can be marked as a working day, or a working day can become a special holiday. Applies only to this Partner workspace. Changes are versioned and audit logged, and do not automatically deduct pay.</p>
  </div>
  @if($actor->role === 'partner')
  <form method="POST" action="{{ route('erp.policies.day_override', ['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr]">
    @csrf
    <label class="text-xs font-bold text-slate-700">Future Date
      <input name="work_date" type="date" min="{{ now($company->timezone)->addDay()->toDateString() }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
    </label>
    <label class="text-xs font-bold text-slate-700">Day Type
      <select name="day_type" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
        <option value="working">Special Working Day</option>
        <option value="holiday">Special Holiday</option>
      </select>
    </label>
    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Reason / Authorization Notes
      <textarea name="reason" rows="2" minlength="15" maxlength="1000" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Why is this office day being changed?"></textarea>
    </label>
    <button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2">Save Future Day Override</button>
  </form>
  @endif
  <div class="mt-5 space-y-2">
    <p class="text-xs font-bold text-slate-600">Authorized Day Decisions (most recent 30 records)</p>
    @forelse($dayOverrides as $decision)
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
      <span class="font-bold">{{ $decision->work_date }}</span>
      <span>{{ $decision->day_type === 'working' ? 'Working Day' : 'Holiday' }}</span>
      <span class="text-slate-500">{{ $decision->reason }}</span>
    </div>
    @empty
    <p class="text-xs text-slate-500">No special working days or holidays have been authorized yet.</p>
    @endforelse
  </div>
</section>
@if($actor->role === 'partner')
@php $futureDate=\Carbon\Carbon::now($company->timezone)->addMonth()->startOfMonth()->toDateString(); @endphp
<section class="mb-5 rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
  <h3 class="font-extrabold">Attendance GPS Privacy · My Partner Workspace</h3>
  <p class="mt-2 text-sm text-slate-600">Location collection is OFF by default. When enabled, the employee must give browser permission separately for each check-in/out. Coordinates are encrypted and visible only to this Partner or the student's assigned Manager. Coordinates are browser-reported, not verified proof of physical presence. Declining location will not block attendance.</p>
  <form method="POST" action="{{ route('erp.attendance.location_policy', ['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-3">
    @csrf
    <label class="block text-sm font-bold">Location Collection
      <select name="attendance_location_enabled" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3">
        <option value="0" @selected(!$workspace->attendance_location_enabled)>Disabled (default)</option>
        <option value="1" @selected($workspace->attendance_location_enabled)>Enable optional GPS requests</option>
      </select>
    </label>
    <label class="block text-sm font-bold">Retention (days, 7–90)
      <input type="number" name="attendance_location_retention_days" min="7" max="90" value="{{ $workspace->attendance_location_retention_days }}" required class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-3">
    </label>
    <label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" name="notice_acknowledged" value="1" class="mt-0.5"> I acknowledge that employees must receive a location/privacy notice and their GPS participation remains voluntary. I understand backups may keep copies beyond the in-app retention period.</label>
    <button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Save Workspace GPS Settings</button>
  </form>
</section>
<div class="grid items-start gap-5 xl:grid-cols-2">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
<h3 class="text-base font-extrabold">Propose Company-wide Policy</h3><p class="mt-2 text-xs text-slate-500">RA&C requires all three Partners to vote separately. One rejection stops the proposal. Proposed changes are never applied retroactively.</p>
<form action="{{ route('erp.policies.propose',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Effective First Day of Month<input type="date" name="effective_date" value="{{ $futureDate }}" min="{{ $futureDate }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office Start<input type="time" name="office_start" value="{{ substr($effectivePolicy['office_start'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office End<input type="time" name="office_end" value="{{ substr($effectivePolicy['office_end'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Grace Minutes (0–30)<input type="number" name="grace_minutes" min="0" max="30" value="{{ $effectivePolicy['grace_minutes'] }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<fieldset class="sm:col-span-2"><legend class="text-xs font-bold text-slate-600">Weekly Holidays</legend><div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$label)<label class="flex items-center gap-1"><input type="checkbox" name="weekly_holidays[]" value="{{ $day }}" @checked(in_array($day,[5,6],true))> {{ $label }}</label>@endforeach</div></fieldset>
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Reason / Justification<textarea name="justification" rows="3" minlength="15" maxlength="2000" required placeholder="Explain why the company-wide default should change" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold sm:col-span-2">Create Proposal for 3-Partner Vote</button>
</form></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
<h3 class="text-base font-extrabold">Schedule My Partner Override</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Only {{ $workspace->name }} is affected. A Partner may apply permitted scheduling fields without other Partners' approval. Changes start in a future month and do not alter finalized history.</p>
<form action="{{ route('erp.policies.override',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Effective Date<input type="date" name="effective_date" value="{{ $futureDate }}" min="{{ $futureDate }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office Start<input type="time" name="office_start" value="{{ substr($effectivePolicy['office_start'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office End<input type="time" name="office_end" value="{{ substr($effectivePolicy['office_end'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Grace Minutes<input type="number" name="grace_minutes" min="0" max="30" value="{{ $effectivePolicy['grace_minutes'] }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<fieldset class="sm:col-span-2"><legend class="text-xs font-bold text-slate-600">Weekly Holidays</legend><div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$label)<label class="flex items-center gap-1"><input type="checkbox" name="weekly_holidays[]" value="{{ $day }}" @checked(in_array($day,[5,6],true))> {{ $label }}</label>@endforeach</div></fieldset>
<button class="rounded-xl bg-[#17191F] p-3 text-sm font-extrabold text-white sm:col-span-2">Schedule Workspace Override</button>
</form></section>
</div>
@endif
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(260px,1fr)]">
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Company Proposal Register</h3><p class="mt-1 text-xs text-slate-500">Only eligible Partners may vote; the roster is frozen when the proposal is submitted.</p></div>
<div class="divide-y divide-slate-100">
@forelse($proposals as $proposal)
@php
$proposalVotes=$votes->get($proposal->id,collect());
$eligible=json_decode($proposal->eligible_partner_ids,true);
$hasVoted=$proposalVotes->contains('partner_membership_id',$actor->id);
@endphp
<article class="space-y-3 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-extrabold">Effective {{ $proposal->effective_date }} · #{{ $proposal->id }}</p><p class="mt-1 text-xs text-slate-500">Proposed by {{ $proposal->author_name }} · {{ $proposal->office_start }}–{{ $proposal->office_end }}, {{ $proposal->grace_minutes }} min grace</p></div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $proposal->status==='approved' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ strtoupper($proposal->status) }}</span></div>
<p class="text-xs leading-relaxed text-slate-600">{{ $proposal->justification }}</p>
<p class="text-[11px] font-semibold text-slate-500">Votes: {{ $proposalVotes->where('decision','approve')->count() }} approved / {{ count($eligible) }} eligible · {{ $proposalVotes->where('decision','reject')->count() }} rejected</p>
@if($actor->role==='partner' && $proposal->status==='pending' && in_array($actor->id,$eligible,true) && !$hasVoted)
<form action="{{ route('erp.policies.vote',['firmSlug'=>$firmSlug,'id'=>$proposal->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve Proposal</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">Reject Proposal</button>
</form>@endif
</article>
@empty<p class="p-8 text-sm text-slate-500">No company-wide policy proposals have been submitted.</p>@endforelse
</div></section>
<aside class="space-y-4"><section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Partner Override History</h3><div class="mt-4 space-y-2">
@forelse($overrides as $version)
<div class="rounded-xl border border-slate-100 p-3 text-xs"><p class="font-bold">From {{ $version->effective_date }}</p><p class="mt-1 text-slate-500">{{ substr($version->office_start,0,5) }}–{{ substr($version->office_end,0,5) }} · {{ $version->grace_minutes }} min</p></div>
@empty<p class="text-xs text-slate-500">No Partner overrides scheduled.</p>@endforelse
</div></section>
<section class="rounded-2xl bg-[#17191F] p-5 text-white"><h3 class="text-sm font-extrabold">Governance Safeguards</h3><p class="mt-3 text-xs leading-relaxed text-slate-300">Every separate vote is stored. If the active Partner roster changes before unanimity, the pending proposal is suspended on the next vote. Previously locked Payroll and Attendance remain unchanged.</p></section></aside>
</div>
@endsection