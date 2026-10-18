<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6" aria-label="Student and team shift management">
<div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">INDIVIDUAL & TEAM SCHEDULES</p>
<h3 class="mt-1 text-lg font-extrabold">Student Shifts & Team Duty Calendar</h3>
<p class="mt-2 text-sm text-slate-600">A Student's authorized date-specific shift overrides the Partner's standard day. A Manager can propose schedules only for assigned Students; a Partner approves them. Team requests cover only active Students assigned to the selected Manager at submission time.</p></div>

@if(in_array($actor->role, ['partner','manager'], true))
<form method="POST" action="{{ route('erp.policies.student_shifts',['firmSlug'=>$firmSlug]) }}" class="mt-5 grid gap-3 sm:grid-cols-2">
@csrf
<label class="text-xs font-bold text-slate-700">Apply To
<select name="scope" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="student">One Student</option><option value="team">All Students Assigned to Manager</option>
</select></label>
<label class="text-xs font-bold text-slate-700">Future Date
<input name="work_date" type="date" min="{{ now($company->timezone)->addDay()->toDateString() }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Student (for One Student)
<select name="student_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Select a Student</option>
@foreach($shiftStudents as $student)
<option value="{{ $student->id }}">{{ $student->name }} (#{{ $student->id }})</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Manager (for Whole Team)
<select name="manager_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Select a Manager</option>
@foreach($shiftManagers as $manager)
<option value="{{ $manager->id }}">{{ $manager->name }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Day Type
<select name="day_type" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="working">Working / Special Duty Day</option>
<option value="holiday">Approved Off Day</option>
</select></label>
<div class="hidden sm:block"></div>
<label class="text-xs font-bold text-slate-700">Special Office Start (optional)
<input name="office_start" type="time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Special Office End (optional)
<input name="office_end" type="time" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Grace Minutes (optional, 0–60)
<input name="grace_minutes" type="number" min="0" max="60" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 sm:col-span-2">Reason
<textarea name="reason" rows="2" required minlength="15" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Reason for the individual/team schedule change"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2">{{ $actor->role === 'partner' ? 'Authorize Student / Team Shift' : 'Request Partner Approval' }}</button>
</form>
@endif

<div class="mt-6 space-y-2">
<h4 class="text-sm font-extrabold">Shift Request & Decision History</h4>
@forelse($studentShifts as $shift)
<div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
<div class="flex flex-wrap items-center gap-2 text-xs">
<strong>{{ $shift->student_name }}</strong>
<span>{{ $shift->work_date }}</span>
<span class="rounded-full px-2 py-1 font-bold {{ $shift->status === 'approved' ? 'bg-emerald-100 text-emerald-900' : ($shift->status === 'pending' ? 'bg-amber-100 text-amber-950' : 'bg-slate-200 text-slate-800') }}">{{ ucfirst($shift->status) }}</span>
<span>{{ ucfirst($shift->day_type) }}</span>
@if($shift->office_start && $shift->office_end)
<span>{{ substr($shift->office_start,0,5) }}–{{ substr($shift->office_end,0,5) }}</span>
@endif
</div>
<p class="mt-2 text-xs text-slate-600">{{ $shift->reason }}</p>
@if($actor->role === 'partner' && $shift->status === 'pending')
<form method="POST" action="{{ route('erp.policies.student_shift_decide',['firmSlug'=>$firmSlug,'shiftId'=>$shift->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve Entire Request</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject Entire Request</button>
</form>
@endif
</div>
@empty
<p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No scheduled Student shifts yet.</p>
@endforelse
</div>
</section>
