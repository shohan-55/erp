@extends('layouts.erp')
@section('title','Workspace Dashboard')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRACTICE OPERATIONS</p><h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">Welcome, {{ auth()->user()->name }}</h2><p class="mt-2 text-sm text-slate-500">Live statistics for {{ $workspace->name }}. {{ $actor->role === 'manager' ? 'Your assigned student team only.' : ($actor->role === 'student' ? 'Your own records only.' : 'Your own Partner workspace only.') }}</p></section>
@if(in_array($actor->role, ['partner', 'manager'], true))
<section class="rounded-3xl border border-amber-200 bg-gradient-to-br from-[#17191F] to-[#3B3424] p-6 text-white shadow-sm sm:p-8">
<p class="text-xs font-extrabold uppercase tracking-widest text-[#FCDF02]">{{ $actor->role === 'partner' ? 'PARTNER CONTROL CENTER' : 'MANAGER OPERATIONS' }}</p>
<h3 class="mt-2 text-2xl font-extrabold">Run your practice, not just view reports.</h3>
<p class="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">Manage team attendance, policy proposals, leave reviews, conveyance approvals and upcoming holidays from real operational controls. {{ $actor->role === 'partner' ? 'You authorize changes within your workspace.' : 'You review assigned staff and propose controlled policy changes to your Partner.' }}</p>
<div class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
@foreach([
['title'=>'Practice Management','desc'=>'Leave limits, conveyance & holiday rules','route'=>'erp.manage','icon'=>'sliders-horizontal'],
['title'=>'Attendance & Office Policy','desc'=>'Hours, grace, weekly and special days','route'=>'erp.policies','icon'=>'calendar-cog'],
['title'=>'Attendance Report','desc'=>'Review staff time and sessions','route'=>'erp.attendance.report','icon'=>'calendar-check-2'],
['title'=>'Leave Approvals','desc'=>'Review pending team requests','route'=>'erp.leaves','icon'=>'calendar-days'],
['title'=>'Conveyance Reviews','desc'=>'Inspect evidence and decisions','route'=>'erp.claims','icon'=>'receipt'],
['title'=>$actor->role === 'partner' ? 'Team & Accounts' : 'My Assigned Team','desc'=>'Staff and manager assignments','route'=>'erp.team','icon'=>'users'],
] as $action)
<a href="{{ route($action['route'],['firmSlug'=>$firmSlug]) }}" class="group rounded-2xl border border-white/15 bg-white/10 p-4 transition hover:border-[#FCDF02] hover:bg-white/15">
<i data-lucide="{{ $action['icon'] }}" class="h-5 w-5 text-[#FCDF02]"></i>
<p class="mt-3 text-sm font-extrabold">{{ $action['title'] }} →</p>
<p class="mt-1 text-xs text-slate-300">{{ $action['desc'] }}</p>
</a>
@endforeach
</div>
</section>
@endif
@if($actor->role === 'student')
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Quick attendance">
  <div class="flex flex-wrap items-start justify-between gap-3">
    <div>
      <p class="text-xs font-extrabold uppercase tracking-wider text-amber-800">QUICK ATTENDANCE</p>
      <h3 class="mt-1 text-xl font-extrabold">{{ $activeAttendance ? 'Currently checked in' : 'Check in for work' }}</h3>
      <p class="mt-2 text-sm text-slate-600">Office: {{ substr($effectivePolicy['office_start'],0,5) }} – {{ substr($effectivePolicy['office_end'],0,5) }} · Grace: {{ $effectivePolicy['grace_minutes'] }} minutes</p>
    </div>
    <span class="rounded-full px-3 py-1.5 text-xs font-extrabold {{ $activeAttendance ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ $activeAttendance ? 'On duty' : 'Not checked in' }}</span>
  </div>
  @if($activeAttendance)
    <p class="mt-4 text-sm text-slate-700">Started at {{ \Carbon\Carbon::parse($activeAttendance->started_at, 'UTC')->timezone($company->timezone)->format('h:i A') }} · {{ ucfirst($activeAttendance->work_mode) }}{{ $activeAttendance->client_site ? ' · '.$activeAttendance->client_site : '' }}</p>
    <form action="{{ route('erp.attendance.check_out', ['firmSlug'=>$firmSlug]) }}" method="POST" @if($workspace->attendance_location_enabled) data-erp-attendance-form @endif class="mt-4">
      @csrf
      <div class="mb-3">@if($workspace->attendance_location_enabled)
<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" data-erp-location-consent class="mt-0.5"> I agree to share my approximate browser GPS for this single attendance event. If unavailable, attendance can still be submitted and may need manual verification.</label>
<p data-erp-location-status role="status" class="text-xs text-slate-500" aria-live="polite"></p>
@endif</div><button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl bg-slate-900 px-6 py-3 text-sm font-extrabold text-white hover:bg-slate-800 sm:w-auto">Check Out</button>
    </form>
  @else
    <form action="{{ route('erp.attendance.check_in', ['firmSlug'=>$firmSlug]) }}" method="POST" @if($workspace->attendance_location_enabled) data-erp-attendance-form @endif class="mt-4 flex flex-col items-stretch gap-3 sm:flex-row sm:items-end">
      @csrf
      <label class="min-w-0 flex-1 text-xs font-bold text-slate-700">Work Location
        <select name="work_mode" class="mt-2 w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm">
          <option value="office">Office</option>
          <option value="client">Client Site</option>
        </select>
      </label>
      <label class="min-w-0 flex-[1.5] text-xs font-bold text-slate-700">Client Name (required for Client Site)
        <input name="client_site" maxlength="180" placeholder="Client / engagement location" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm">
      </label>
      <div class="self-center">@if($workspace->attendance_location_enabled)
<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" data-erp-location-consent class="mt-0.5"> I agree to share my approximate browser GPS for this single attendance event. If unavailable, attendance can still be submitted and may need manual verification.</label>
<p data-erp-location-status role="status" class="text-xs text-slate-500" aria-live="polite"></p>
@endif</div><button type="submit" class="min-h-12 rounded-xl bg-[#FCDF02] px-7 py-3 text-sm font-extrabold text-slate-950 hover:bg-[#F5AD16]">Check In</button>
    </form>
  @endif
  <p class="mt-3 text-xs text-slate-500">Your attendance uses server time and is recorded in your own workspace. Optional GPS is available only when the Partner enables it and the employee grants consent per event.</p>
  <a href="{{ route('erp.attendance', ['firmSlug'=>$firmSlug]) }}" class="mt-3 inline-flex text-xs font-bold text-amber-800 underline">View attendance history</a>
</section>
@endif
<section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Live workspace metrics">
@foreach([
['label'=>'Accessible Team','value'=>$stats['members'],'icon'=>'users'],
['label'=>'Checked in Today','value'=>$stats['checked_in_today'],'icon'=>'calendar-check-2'],
['label'=>'Leave for Review','value'=>$stats['pending_leaves'],'icon'=>'calendar-days'],
['label'=>'Pending Claims','value'=>$stats['pending_claims'],'icon'=>'receipt']
] as $item)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><div class="flex items-start justify-between gap-2"><p class="text-xs font-semibold text-slate-500">{{ $item['label'] }}</p><i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 text-amber-700"></i></div><p class="mt-5 text-3xl font-extrabold">{{ $item['value'] }}</p></div>
@endforeach
</section>
<section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(260px,1fr)]">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h3 class="text-sm font-extrabold">Recent Attendance</h3><p class="mt-1 text-xs text-slate-500">Server-recorded sessions</p></div><a href="{{ route('erp.attendance',['firmSlug'=>$firmSlug]) }}" class="text-xs font-bold text-amber-800 hover:underline">View records →</a></div>
<div class="divide-y divide-slate-100">
@forelse($recent as $item)
<div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 text-sm"><div><strong>{{ $item->member_name }}</strong><p class="mt-1 text-xs text-slate-500">{{ $item->work_date }} · {{ ucfirst($item->work_mode) }}</p></div><span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $item->ended_at ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ $item->ended_at ? 'Checked out' : 'Session active' }}</span></div>
@empty<p class="p-7 text-sm text-slate-500">No attendance sessions yet.</p>@endforelse
</div></div>
<aside class="space-y-4"><div class="rounded-2xl bg-[#17191F] p-6 text-white"><h3 class="text-base font-extrabold">Office Policy</h3><p class="mt-3 text-sm text-slate-300">Office start: {{ substr($effectivePolicy['office_start'],0,5) }} · End: {{ substr($effectivePolicy['office_end'],0,5) }}</p><p class="mt-2 text-sm text-slate-300">Grace: {{ $effectivePolicy['grace_minutes'] }} minutes</p><p class="mt-2 text-xs text-amber-200">Time zone: {{ $company->timezone }} · No automatic payroll late penalty</p></div>
<a href="{{ route('erp.notifications',['firmSlug'=>$firmSlug]) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-5 text-sm font-bold shadow-sm"><span>Unread Notifications</span><span class="rounded-full bg-[#FCDF02] px-3 py-1">{{ $stats['unread_notifications'] }}</span></a></aside>
</section>
@if($workspace->attendance_location_enabled && $actor->role === 'student')
<script src="{{ asset('js/erp-attendance-location.js') }}" defer></script>
@endif
@endsection