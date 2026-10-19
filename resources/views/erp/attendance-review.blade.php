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
<option value="incorrect_checkin">Incorrect Check-in Time (Existing Session)</option>
<option value="client_duty">Client Duty Approval (not an attendance punch)</option>
</select></label>
<label class="text-xs font-bold text-slate-700">Work Date
<input type="date" name="work_date" required min="{{ now($company->timezone)->subDays(60)->toDateString() }}" max="{{ now($company->timezone)->toDateString() }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Session (for Check-in / Check-out Correction)
<select name="attendance_session_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Select a session</option>
@foreach($sessions as $s)
<option value="{{ $s->id }}">{{ $s->work_date }} · {{ \Carbon\Carbon::parse($s->started_at,'UTC')->timezone($company->timezone)->format('h:i A') }} · #{{ $s->id }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Client Name (for Client Duty)
<input type="text" name="client_site" maxlength="180" placeholder="Audit client/site" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Actual Check-in (Missed or Incorrect Check-in)
<input type="time" name="start_time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Actual Check-out (for Punch Corrections)
<input type="time" name="end_time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Reason and Supporting Explanation
<textarea name="reason" rows="3" required minlength="15" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Explain exactly what happened. The Partner must verify before approval."></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit for Review</button>
</form>
</section>
@endif


@if($canInitiateTeam)
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
<div class="mb-4 flex items-center gap-2"><i data-lucide="user-check" class="h-5 w-5 text-amber-700"></i><h3 class="text-base font-extrabold">Propose a Team Attendance Correction</h3></div>
<p class="text-xs leading-relaxed text-slate-600">For verified device, network, server, or client-site exceptions. This request is submitted by your own Manager identity and <strong>does not impersonate the Student</strong>. The original record is untouched until your Partner makes a separate final decision.</p>
<form method="POST" action="{{ route('erp.attendance.reviews.team_submit',['firmSlug'=>$firmSlug]) }}" class="mt-5 grid gap-3 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Assigned Student
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Choose your Student</option>
@foreach($students as $student)
<option value="{{ $student->id }}" @selected(old('membership_id')==$student->id)>{{ $student->name }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Type of Exception
<select name="kind" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="missed_checkin">Missed Check-in &amp; Check-out</option>
<option value="incorrect_checkin">Incorrect Check-in Time</option>
<option value="missed_checkout">Incorrect / Missing Check-out</option>
<option value="client_duty">Client Duty Authorization (not a punch)</option>
</select></label>
<label class="text-xs font-bold text-slate-700">Work Date
<input type="date" name="work_date" required min="{{ now($company->timezone)->subDays(60)->toDateString() }}" max="{{ now($company->timezone)->toDateString() }}" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Existing Session (Check-in / Check-out Correction)
<select name="attendance_session_id" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select existing session if applicable</option>
@foreach($sessions as $session)
<option value="{{ $session->id }}">{{ $session->work_date }} · #{{ $session->id }} · Student #{{ $session->membership_id }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Actual Check-in
<input type="time" name="start_time" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Actual Check-out
<input type="time" name="end_time" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Audit Client / Site (for Client Duty)
<input name="client_site" maxlength="180" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Client or duty assignment name"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Incident / Verification Reason
<textarea name="reason" required minlength="15" maxlength="1000" rows="3" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Explain network outage, server failure or manual verification. All changes are audited."></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit to Partner for Final Approval</button>
</form>
</section>
@endif

@if(\App\Support\WorkspaceAccess::allows($actor,'attendance.proxy'))
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Authorized Proxy Attendance — Server Time Only</h3>
<p class="mt-2 text-xs text-slate-600">For verified internet outages or inability to punch. This records the real server time, not an invented earlier time. The original student remains the owner; your identity, reason and action are permanently audited. For historical corrections, use the separate reviewed exception workflow.</p>
<form method="POST" action="{{ route('erp.attendance.proxy',['firmSlug'=>$firmSlug]) }}" data-corporate-confirm="Record an audited proxy attendance at the current SERVER time? This is not a Student login or GPS proof." class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold">Student<select required name="membership_id" class="mt-2 block w-full rounded-lg border p-3">
<option value="">Choose authorized student</option>
@foreach(\Illuminate\Support\Facades\DB::table('memberships as m')->join('users as u','u.id','=','m.user_id')->where('m.company_id',$company->id)->where('m.workspace_id',$workspace->id)->where('m.role','student')->where('m.active',true)->whereNull('m.platform_suspended_at')->when($actor->role==='manager' && !\App\Support\WorkspaceAccess::allows($actor,'scope.workspace'),fn($q)=>$q->where('m.manager_membership_id',$actor->id))->orderBy('u.name')->get(['m.id','u.name']) as $student)
<option value="{{ $student->id }}">{{ $student->name }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold">Action<select name="action" required class="mt-2 block w-full rounded-lg border p-3">
<option value="check_in">Proxy Check-in</option><option value="check_out">Proxy Check-out</option>
</select></label>
<label class="text-xs font-bold">Location Type<select name="work_mode" class="mt-2 block w-full rounded-lg border p-3">
<option value="office">Office</option><option value="client">Client Duty</option>
</select></label>
<label class="text-xs font-bold">Client Site (if required)<input name="client_site" maxlength="180" class="mt-2 block w-full rounded-lg border p-3"></label>
<label class="text-xs font-bold sm:col-span-2">Reason & verification evidence<textarea required name="reason" minlength="20" maxlength="1000" rows="3" class="mt-2 block w-full rounded-lg border p-3" placeholder="Who verified presence? What went wrong with the student's connection?"></textarea></label>
<button class="rounded-xl bg-amber-400 p-3 text-xs font-extrabold sm:col-span-2">Record Proxy Attendance (Audited)</button>
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
@if($row->initiated_by && $row->initiated_by != $row->membership_id)
<p class="mt-2 text-xs font-bold text-amber-900">Manager-initiated exception · Partner final approval required · No impersonated punch</p>
@endif
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
