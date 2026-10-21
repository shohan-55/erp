@extends('layouts.erp')
@section('title','Attendance')
@section('content')
<section><h2 class="text-2xl font-extrabold">Office & Client Sessions</h2><p class="mt-2 text-sm text-slate-500">Server timestamps, one active session per student; history is scoped to your authorized team.</p>@if($actor->role !== 'student' || \App\Support\StudentFeatureAccess::enabled($actor,'attendance_report'))
<a class="mt-3 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-sm font-bold" href="{{ route('erp.attendance.report', ['firmSlug' => $firmSlug]) }}">Monthly Attendance Report</a> <a class="mt-3 inline-flex rounded-xl border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-bold text-amber-950" href="{{ route('erp.attendance.reconcile', ['firmSlug'=>$firmSlug]) }}">Daily Reconciliation</a>
@endif
<a class="mt-3 inline-flex rounded-xl border border-amber-300 px-4 py-2 text-sm font-bold text-amber-950" href="{{ route('erp.attendance.reviews',['firmSlug'=>$firmSlug]) }}">Attendance & Client Duty Requests</a>
</section>
@include('erp.partials.module-guide',['module'=>'attendance'])
@if($actor->role === 'student')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
<h3 class="text-base font-extrabold">{{ $openSession ? 'Current active session' : 'Start a new work session' }}</h3>
@if($openSession)
<p class="mt-2 text-sm text-slate-600">Started {{ \Carbon\Carbon::parse($openSession->started_at,'UTC')->timezone($company->timezone)->format('d M Y h:i A') }} · {{ ucfirst($openSession->work_mode) }}</p>
<form action="{{ route('erp.attendance.check_out',['firmSlug'=>$firmSlug]) }}" method="POST" @if($workspace->attendance_location_enabled) data-erp-attendance-form @endif class="mt-5">@csrf<div class="md:col-span-3">@if($workspace->attendance_location_enabled)
<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" data-erp-location-consent class="mt-0.5"> I agree to share my approximate browser GPS for this single attendance event. If unavailable, attendance can still be submitted and may need manual verification.</label>
<p data-erp-location-status role="status" class="text-xs text-slate-500" aria-live="polite"></p>
@endif</div><button class="rounded-xl bg-[#17191F] px-6 py-3 text-sm font-extrabold text-white">Check Out</button></form>
@else
<form action="{{ route('erp.attendance.check_in',['firmSlug'=>$firmSlug]) }}" method="POST" @if($workspace->attendance_location_enabled) data-erp-attendance-form @endif class="mt-5 grid items-end gap-3 md:grid-cols-[1fr_1.5fr_auto]">@csrf
<label class="text-xs font-bold text-slate-700">Work Mode<select name="work_mode" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"><option value="office">Office</option><option value="client">Client Site</option></select></label>
<label class="text-xs font-bold text-slate-700">Client Site (required for Client Mode)<input type="text" name="client_site" maxlength="180" placeholder="Audit client/site name" class="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"></label>
<div class="md:col-span-3">@if($workspace->attendance_location_enabled)
<label class="flex items-start gap-2 text-xs text-slate-600"><input type="checkbox" data-erp-location-consent class="mt-0.5"> I agree to share my approximate browser GPS for this single attendance event. If unavailable, attendance can still be submitted and may need manual verification.</label>
<p data-erp-location-status role="status" class="text-xs text-slate-500" aria-live="polite"></p>
@endif</div><button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Check In</button>
</form>
@endif
<p class="mt-4 text-xs leading-relaxed text-slate-500">Your local clock is not used to record attendance. Missing checkout stays incomplete. Company policies determine lateness.</p></section>
@endif
@if($canProxy && $proxyStudents->isNotEmpty())
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
<h3 class="text-lg font-extrabold">Authorized Proxy Attendance · Manager / Moderator</h3>
<p class="mt-2 text-xs leading-relaxed text-slate-600">Only expressly permitted Managers can record server-time proxy punches for assigned Students. The Student is notified, device GPS is never copied, and the session is <strong>unverified</strong> until the Partner reviews it. Full Role does not silently enable proxy rights.</p>
<div class="mt-4 grid gap-5 lg:grid-cols-2">
<form method="POST" action="{{ route('erp.attendance.proxy.in',['firmSlug'=>$firmSlug]) }}" class="space-y-3 rounded-xl bg-amber-50 p-4">@csrf
<h4 class="text-sm font-extrabold">Proxy Check In</h4>
<label class="block text-xs font-bold">Assigned Student
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"><option value="">Select Student</option>
@foreach($proxyStudents as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select></label>
<label class="block text-xs font-bold">Work Mode
<select name="work_mode" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"><option value="office">Office</option><option value="client">Client Duty</option></select></label>
<label class="block text-xs font-bold">Client Site (Client Mode Only)
<input name="client_site" maxlength="180" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold">Reason / Verification Evidence
<textarea name="reason" rows="2" minlength="15" maxlength="1000" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Explain why a proxy is needed"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Record Pending Proxy Check-in</button>
</form>
<form method="POST" action="{{ route('erp.attendance.proxy.out',['firmSlug'=>$firmSlug]) }}" class="space-y-3 rounded-xl bg-slate-50 p-4">@csrf
<h4 class="text-sm font-extrabold">Proxy Check Out</h4>
<label class="block text-xs font-bold">Assigned Student
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"><option value="">Select Student</option>
@foreach($proxyStudents as $student)<option value="{{ $student->id }}">{{ $student->name }}</option>@endforeach</select></label>
<label class="block text-xs font-bold">Reason for Proxy Check-out
<textarea name="reason" rows="2" minlength="15" maxlength="1000" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Explain why the student cannot check out"></textarea></label>
<button class="rounded-xl bg-slate-900 px-4 py-3 text-xs font-extrabold text-white">Record Pending Proxy Check-out</button>
</form>
</div></section>
@endif
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Attendance History (last 100 sessions)</h3></div>
@include('erp.partials.history-filter',['historyKey'=>'attendance','historyTitle'=>'Attendance sessions','historyStatuses'=>['on_time'=>'On time','late'=>'Late','holiday'=>'Holiday','missing_checkout'=>'Missing checkout']])
<div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="p-4">Student</th><th class="p-4">Date/Mode</th><th class="p-4">Check-in</th><th class="p-4">Check-out</th><th class="p-4">Status</th>@if($actor->role!=='student')<th class="p-4">Location</th>@endif @if($actor->role==='partner')<th class="p-4">Correction</th>@endif</tr></thead>
<tbody class="divide-y divide-slate-100" data-corporate-history-group="attendance">
@forelse($records as $item)
<tr data-corporate-history-item data-history-status="{{ ! $item->ended_at ? 'missing_checkout' : ($item->holiday ? 'holiday' : ($item->late ? 'late' : 'on_time')) }}"><td class="p-4 font-semibold">{{ $item->member_name }}</td><td class="p-4">{{ $item->work_date }}<p class="text-xs text-slate-500">{{ ucfirst($item->work_mode) }} {{ $item->client_site ? '· '.$item->client_site : '' }}</p></td><td class="p-4">{{ \Carbon\Carbon::parse($item->started_at,'UTC')->timezone($company->timezone)->format('h:i A') }}</td><td class="p-4">{{ $item->ended_at ? \Carbon\Carbon::parse($item->ended_at,'UTC')->timezone($company->timezone)->format('h:i A') : 'Incomplete' }}</td>
<td class="p-4"><span class="rounded-full bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">{{ $item->holiday ? 'Holiday' : ($item->late ? 'Late' : 'On time') }}</span>
@if($item->proxy_status)<p class="mt-2 text-xs font-bold {{ $item->proxy_status === 'verified' ? 'text-emerald-700' : 'text-rose-700' }}">Proxy · {{ ucfirst($item->proxy_status) }}</p>
@if($actor->role==='partner' && $item->proxy_status==='pending')
<form method="POST" action="{{ route('erp.attendance.proxy.decide',['firmSlug'=>$firmSlug,'sessionId'=>$item->id]) }}" class="mt-2 flex min-w-[210px] flex-col gap-2">@csrf
<input name="reason" required minlength="15" maxlength="1000" placeholder="Verification reason (15+ characters)" class="rounded-lg border border-slate-300 p-2 text-xs">
<button name="decision" value="verify" class="rounded-lg bg-emerald-700 px-2 py-2 text-xs font-bold text-white">Verify Completed Session</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-2 py-2 text-xs font-bold text-white">Reject Proxy Session</button></form>
@endif
@endif</td>
@if($actor->role!=='student')<td class="p-4">@if($locationSessionIds->contains($item->id))<a href="{{ route('erp.attendance.location',['firmSlug'=>$firmSlug,'sessionId'=>$item->id]) }}" class="text-xs font-bold text-amber-800 underline">View GPS</a>@else<span class="text-xs text-slate-400">Not provided</span>@endif</td>@endif
@if($actor->role==='partner')<td class="p-4"><form action="{{ route('erp.attendance.correct',['firmSlug'=>$firmSlug,'sessionId'=>$item->id]) }}" method="POST" class="flex min-w-[275px] flex-col gap-2">@csrf<input type="datetime-local" name="ended_at" required class="rounded-lg border border-slate-300 p-2 text-xs"><input type="text" name="reason" minlength="10" maxlength="500" required placeholder="Correction reason (10+ characters)" class="rounded-lg border border-slate-300 p-2 text-xs"><button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Save correction</button></form></td>@endif</tr>
@empty<tr><td colspan="6" class="p-8 text-center text-sm text-slate-500">No attendance sessions found.</td></tr>@endforelse
</tbody></table></div></section>
@if($workspace->attendance_location_enabled && $actor->role === 'student')
<script src="{{ asset('js/erp-attendance-location.js') }}" defer></script>
@endif
@endsection