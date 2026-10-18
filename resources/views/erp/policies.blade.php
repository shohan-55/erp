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
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Partner day calendar">
  <div>
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">ADVANCED WORK CALENDAR</p>
    <h3 class="mt-1 text-lg font-extrabold">Special Working Days & Holidays</h3>
    <p class="mt-2 text-sm text-slate-600">Only this Partner's workspace is affected. Government holiday labels are entered and authorized by your practice; this is not an automatic official holiday feed. Friday may be a working day with its own hours, e.g. 11:30–17:30. A Manager may submit a request, but only a Partner can approve. Prior calendar decisions remain in the audit history; frozen payroll is protected.</p>
  </div>

  <div class="mt-5 overflow-hidden rounded-xl border border-slate-200">
    <div class="border-b border-slate-200 bg-slate-50 px-4 py-3"><p class="text-sm font-extrabold">Next 14 Days — Approved Schedule</p><p class="mt-1 text-xs text-slate-500">Firm defaults → Partner policies → approved date-specific exceptions</p></div>
    <div class="grid grid-cols-2 gap-px bg-slate-200 sm:grid-cols-3 lg:grid-cols-7">
      @foreach($calendarPreview as $day)
      <div class="min-w-0 bg-white p-3">
        <p class="text-[11px] font-bold text-slate-500">{{ $day['day'] }} · {{ substr($day['date'],5) }}</p>
        <p class="mt-1 text-xs font-extrabold {{ $day['holiday'] ? 'text-rose-800' : 'text-emerald-800' }}">{{ $day['holiday'] ? 'Holiday' : 'Working' }}</p>
        @if(! $day['holiday'])
        <p class="mt-1 text-[11px] text-slate-700">{{ substr($day['office_start'],0,5) }}–{{ substr($day['office_end'],0,5) }}</p>
        <p class="text-[10px] text-slate-500">Grace {{ $day['grace_minutes'] }}m</p>
        @endif
        @if($day['exception_id'])<p class="mt-1 text-[10px] font-bold text-amber-800">Special Decision</p>@endif
      </div>
      @endforeach
    </div>
  </div>

  @if(in_array($actor->role, ['partner','manager'], true))
  <form method="POST" action="{{ route('erp.policies.day_override', ['firmSlug'=>$firmSlug]) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
    @csrf
    <div class="sm:col-span-2"><h4 class="text-sm font-extrabold">{{ $actor->role === 'partner' ? 'Approve a Special Day for Your Partner Unit' : 'Request a Special Day from Your Partner' }}</h4><p class="mt-1 text-xs text-slate-500">Leave working hours blank to use your Partner's normal office hours. For holidays, leave all hours blank.</p></div>
    <label class="text-xs font-bold text-slate-700">Future Date
      <input name="work_date" type="date" min="{{ now($company->timezone)->addDay()->toDateString() }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
    </label>
    <label class="text-xs font-bold text-slate-700">Day Type
      <select name="day_type" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
        <option value="working">Special Working Day</option>
        <option value="holiday">Special Holiday</option>
      </select>
    </label>
    <label class="text-xs font-bold text-slate-700">Holiday / Workday Category
      <select name="holiday_category" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
        <option value="partner_decision">Partner Decision</option>
        <option value="government">Bangladesh Government Holiday (verified by firm)</option>
        <option value="optional">Optional / Religious / Local Holiday</option>
        <option value="client_duty">Client Assignment / Special Duty</option>
        <option value="emergency">Emergency / Unplanned</option>
      </select>
    </label>
    <div class="hidden sm:block"></div>
    <label class="text-xs font-bold text-slate-700">Special Office Start (optional)
      <input type="time" name="office_start" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
    </label>
    <label class="text-xs font-bold text-slate-700">Special Office End (optional)
      <input type="time" name="office_end" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
    </label>
    <label class="text-xs font-bold text-slate-700">Special Grace Minutes (optional)
      <input type="number" name="grace_minutes" min="0" max="60" placeholder="Use normal grace" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
    </label>
    <label class="text-xs font-bold text-slate-700 sm:col-span-2">Reason / Authorization Notes
      <textarea name="reason" rows="2" minlength="15" maxlength="1000" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Explain the special day and the approved schedule"></textarea>
    </label>
    <button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2">{{ $actor->role === 'partner' ? 'Authorize Special Day' : 'Submit to Partner for Approval' }}</button>
  </form>
  @endif

  <div class="mt-5 space-y-2">
    <p class="text-xs font-bold text-slate-600">Day Decisions & Manager Requests (most recent 30, append-only history)</p>
    @forelse($dayOverrides as $decision)
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs">
      <div class="flex flex-wrap items-center gap-2">
        <strong>{{ $decision->work_date }}</strong>
        <span>{{ $decision->day_type === 'working' ? 'Working Day' : 'Holiday' }}</span>
        <span class="rounded-full px-2 py-1 font-bold {{ $decision->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($decision->status === 'pending' ? 'bg-amber-100 text-amber-900' : 'bg-slate-200 text-slate-700') }}">{{ ucfirst($decision->status) }}</span>
        @if($decision->office_start && $decision->office_end)
          <span>{{ substr($decision->office_start,0,5) }}–{{ substr($decision->office_end,0,5) }}
            @if($decision->grace_minutes !== null)
              · Grace {{ $decision->grace_minutes }}m
            @endif
          </span>
        @endif
        <span class="text-slate-500">{{ str_replace('_',' ', ucfirst($decision->holiday_category)) }}</span>
      </div>
      <p class="mt-2 text-slate-600">{{ $decision->reason }}</p>
      @if($actor->role === 'partner' && $decision->status === 'pending')
      <form method="POST" action="{{ route('erp.policies.day_review', ['firmSlug'=>$firmSlug,'id'=>$decision->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
        <button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-extrabold text-white">Approve Request</button>
        <button name="decision" value="reject" class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-extrabold text-white">Reject Request</button>
      </form>
      @endif
    </div>
    @empty
    <p class="text-xs text-slate-500">No special working days or holidays have been scheduled yet.</p>
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
<h3 class="text-base font-extrabold">Propose Company-wide Policy</h3><p class="mt-2 text-xs text-slate-500">All eligible Partners of this Client Firm must vote separately on shared firm-wide policies. Partner-local scheduling needs no other Partner's approval. Proposed changes are never retroactive.</p>
<form action="{{ route('erp.policies.propose',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Effective First Day of Month<input type="date" name="effective_date" value="{{ $futureDate }}" min="{{ $futureDate }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office Start<input type="time" name="office_start" value="{{ substr($effectivePolicy['office_start'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Office End<input type="time" name="office_end" value="{{ substr($effectivePolicy['office_end'],0,5) }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Grace Minutes (0–30)<input type="number" name="grace_minutes" min="0" max="30" value="{{ $effectivePolicy['grace_minutes'] }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<fieldset class="sm:col-span-2"><legend class="text-xs font-bold text-slate-600">Weekly Holidays</legend><div class="mt-2 flex flex-wrap gap-3 text-xs text-slate-600">@foreach([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $day=>$label)<label class="flex items-center gap-1"><input type="checkbox" name="weekly_holidays[]" value="{{ $day }}" @checked(in_array($day,[5,6],true))> {{ $label }}</label>@endforeach</div></fieldset>
<label class="text-xs font-bold text-slate-600 sm:col-span-2">Reason / Justification<textarea name="justification" rows="3" minlength="15" maxlength="2000" required placeholder="Explain why the company-wide default should change" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold sm:col-span-2">Create Firm-wide Policy Proposal</button>
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