@extends('layouts.erp')
@section('title','Smart HR Policy Center')
@section('content')
<section class="overflow-hidden rounded-2xl border border-amber-300 bg-[#17191F] p-6 text-white shadow-lg sm:p-8">
  <p class="text-xs font-extrabold uppercase tracking-[.2em] text-[#FCDF02]">WORKSPACE GOVERNANCE · SMART HR</p>
  <h2 class="mt-3 text-2xl font-extrabold sm:text-3xl">Smart HR Policy Center</h2>
  <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300">Set your Partner unit's approved office time, grace window, holidays, half-day visual warning and late review rules. Policies take effect on their chosen future date: history, earlier attendance and locked payroll remain unchanged.</p>
  <div class="mt-5 flex flex-wrap gap-2 text-xs">
    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-2 font-bold">Your role: {{ ucfirst($actor->role) }}</span>
    <span class="rounded-full border border-[#FCDF02]/30 bg-[#FCDF02]/10 px-3 py-2 font-bold text-[#FCDF02]">Manager mode: {{ $managerMode === 'direct' ? 'Delegated direct schedule changes' : 'Approval required' }}</span>
    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-2 font-bold">{{ $company->timezone }}</span>
  </div>
</section>

@if($actor->role === 'partner')
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6">
  <h3 class="text-lg font-extrabold">Flexible Manager Permission</h3>
  <p class="mt-2 text-sm text-slate-600">You decide whether Managers can directly schedule future hours. Sensitive settings (weekly holidays, half-day warning, late review count and grace exemption) always require a Partner decision. Manager changes never override firm-wide Company approval rules.</p>
  <form method="POST" action="{{ route('erp.hr_policies.delegate',['firmSlug'=>$firmSlug]) }}" class="mt-4 flex flex-wrap items-end gap-3">
    @csrf
    <label class="flex-1 text-xs font-bold text-slate-700">Manager Policy Mode
      <select name="manager_mode" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
        <option value="approval_required" @selected($managerMode === 'approval_required')>Every change requires Partner approval</option>
        <option value="direct" @selected($managerMode === 'direct')>Delegate direct office start/end and grace changes</option>
      </select>
    </label>
    <button type="submit" class="min-h-11 rounded-xl bg-[#17191F] px-5 py-2.5 text-sm font-extrabold text-[#FCDF02]">Save Manager Permission</button>
  </form>
</section>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Current effective HR policy">
  <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="text-lg font-extrabold">Current Effective Policy</h3><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800">Read-only until future effective date</span></div>
  <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
    @foreach([
      ['Office Start',substr($effective['office_start'],0,5)],
      ['Office End',substr($effective['office_end'],0,5)],
      ['Grace Period',$effective['grace_minutes'].' min'],
      ['Half-day warning',$effective['half_day_warning_time'] ? substr($effective['half_day_warning_time'],0,5) : 'Off'],
      ['Review threshold',$effective['late_review_days'].' qualifying days'],
      ['Within-grace exemption',$effective['grace_late_exempt'] ? 'Enabled' : 'Disabled'],
      ['Weekly holidays',implode(', ',array_map(fn($n) => ['','Mon','Tue','Wed','Thu','Fri','Sat','Sun'][$n],$effective['weekly_holidays'] ?? [])) ?: 'None'],
      ['Active revision',$effective['hr_policy_revision_id'] ? '#'.$effective['hr_policy_revision_id'] : 'Firm / Partner baseline'],
    ] as $item)
    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
      <p class="text-[11px] font-semibold text-slate-500">{{ $item[0] }}</p><p class="mt-2 text-sm font-extrabold text-slate-900">{{ $item[1] }}</p>
    </div>
    @endforeach
  </div>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Propose future HR policy">
  <h3 class="text-lg font-extrabold">Create a Future Policy Version</h3>
  <p class="mt-2 text-sm leading-relaxed text-slate-600">Changes never apply immediately. Partner submissions approve automatically for a future date; Managers either request approval or, if delegated, directly approve non-sensitive schedule edits. Sensitive changes always wait for Partner approval.</p>
  <form method="POST" action="{{ route('erp.hr_policies.propose',['firmSlug'=>$firmSlug]) }}" class="mt-5 space-y-4">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <label class="text-xs font-bold text-slate-700">Effective Date
        <input type="date" name="effective_date" required min="{{ now($company->timezone)->addDay()->toDateString() }}" value="{{ old('effective_date',now($company->timezone)->addDay()->toDateString()) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Office Start
        <input type="time" name="office_start" required value="{{ old('office_start',substr($effective['office_start'],0,5)) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Office End
        <input type="time" name="office_end" required value="{{ old('office_end',substr($effective['office_end'],0,5)) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Grace Period (minutes)
        <input type="number" name="grace_minutes" required min="0" max="180" value="{{ old('grace_minutes',$effective['grace_minutes']) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Half-day Visual Warning (optional)
        <input type="time" name="half_day_warning_time" value="{{ old('half_day_warning_time',$effective['half_day_warning_time'] ? substr($effective['half_day_warning_time'],0,5) : '') }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Qualifying Late Days for Review
        <input type="number" name="late_review_days" required min="1" max="31" value="{{ old('late_review_days',$effective['late_review_days']) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Within-grace Late Counts Toward Review?
        <select name="grace_late_exempt" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm">
          <option value="1" @selected(old('grace_late_exempt',$effective['grace_late_exempt'] ? '1' : '0') == '1')>No — exclude grace arrivals</option>
          <option value="0" @selected(old('grace_late_exempt',$effective['grace_late_exempt'] ? '1' : '0') == '0')>Yes — include grace arrivals</option>
        </select>
      </label>
    </div>
    <fieldset class="rounded-xl border border-slate-200 p-4">
      <legend class="px-2 text-xs font-extrabold text-slate-700">Weekly Holidays (leave all unchecked for none)</legend>
      <div class="flex flex-wrap gap-4">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)
        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="weekly_holidays[]" value="{{ $loop->iteration }}" @checked(in_array($loop->iteration,old('weekly_holidays',$effective['weekly_holidays'] ?? []))) class="accent-amber-500">
          {{ $weekday }}
        </label>
        @endforeach
      </div>
    </fieldset>
    <label class="block text-xs font-bold text-slate-700">Reason for Change / Audit Note
      <textarea name="reason" required minlength="10" maxlength="1000" rows="2" placeholder="Why is this policy change needed?" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">{{ old('reason') }}</textarea>
    </label>
    <p class="text-xs leading-relaxed text-amber-800">Preview safeguard: future policy dates only. Half-day is a warning, not an automatic leave entry. Review threshold does not deduct salary. Approved historical revisions are never overwritten.</p>
    <button type="submit" class="min-h-12 w-full rounded-xl bg-gradient-to-r from-[#FCDF02] to-amber-400 px-6 py-3 text-sm font-extrabold text-[#17191F] shadow-md sm:w-auto">Save Version / Request Approval →</button>
  </form>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="HR policy version history">
  <h3 class="text-lg font-extrabold">Version History & Approval Queue</h3>
  <p class="mt-2 text-sm text-slate-600">Append-only versions scoped to your Partner workspace. Only approved versions become effective on their scheduled date.</p>
  <div class="mt-4 space-y-3">
    @forelse($revisions as $version)
    @php($snapshot = json_decode($version->rules_snapshot,true))
    <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div><p class="text-sm font-extrabold">Version #{{ $version->id }} · Effective {{ $version->effective_date }}</p><p class="mt-1 text-xs text-slate-600">Requested by {{ $version->author_name }} · {{ $version->created_at }}</p></div>
        <span class="rounded-full px-3 py-1 text-xs font-extrabold {{ $version->status === 'approved' ? 'bg-emerald-100 text-emerald-800' : ($version->status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-rose-100 text-rose-800') }}">{{ ucfirst($version->status) }}</span>
      </div>
      <p class="mt-2 text-xs text-slate-600">Start {{ substr($snapshot['office_start'],0,5) }} · End {{ substr($snapshot['office_end'],0,5) }} · Grace {{ $snapshot['grace_minutes'] }}m · Review after {{ $snapshot['late_review_days'] }} qualifying days · {{ $version->sensitive ? 'Sensitive rule change' : 'Schedule change' }}</p>
      <p class="mt-2 text-xs text-slate-700">{{ $version->reason }}</p>
      @if($actor->role === 'partner' && $version->status === 'pending')
      <div class="mt-3 flex flex-wrap gap-2">
        <form method="POST" action="{{ route('erp.hr_policies.decide',['firmSlug'=>$firmSlug,'revisionId'=>$version->id]) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-extrabold text-white">Approve</button></form>
        <form method="POST" action="{{ route('erp.hr_policies.decide',['firmSlug'=>$firmSlug,'revisionId'=>$version->id]) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-extrabold text-white">Reject</button></form>
      </div>
      @endif
    </article>
    @empty
    <p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No future HR policy versions yet. Create a dated proposal above.</p>
    @endforelse
  </div>
</section>
@endsection
