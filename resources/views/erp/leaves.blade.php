@extends('layouts.erp')
@section('title','Advanced Leave Management')
@section('content')
<section>
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PARTNER-WISE LEAVE GOVERNANCE</p>
<h2 class="mt-1 text-2xl font-extrabold">Leave, Balances & Approvals</h2>
<p class="mt-2 max-w-3xl text-sm text-slate-600">Students can apply and withdraw pending leave. Your Partner chooses a one-step or two-step workflow for new applications. Older requests retain their original approval route. Leave does not automatically reduce payroll.</p>
</section>
@include('erp.partials.module-guide',['module'=>'leave'])

@if($actor->role==='student')
<section class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">My {{ $year }} Leave Balance</h3>
@if(!$policy)
<p class="mt-3 text-xs text-amber-900">Your Partner has not activated a yearly leave allowance policy. The earlier Annual, Sick and Emergency approval workflow is still available; no quota is assumed.</p>
@else
<div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-5">
@foreach($balances as $type=>$balance)
<div class="rounded-xl bg-slate-50 p-3">
<p class="text-xs font-bold text-slate-600">{{ ucfirst($type) }}</p>
<p class="mt-2 text-2xl font-extrabold tabular-nums">{{ number_format(max(0,$balance['remaining']),1) }}</p>
<p class="mt-1 text-[10px] text-slate-500">of {{ number_format($balance['entitled'],1) }} days · {{ number_format($balance['reserved'],1) }} pending</p>
@if($type==='annual' && $balance['carry']>0)
<p class="mt-1 text-[10px] text-amber-800">{{ number_format($balance['carry'],1) }} carried</p>
@endif
</div>
@endforeach
</div>
<p class="mt-3 text-xs text-slate-500">Policy: {{ $policy->annual_notice_days }} days' notice for Annual Leave · {{ $policy->count_holidays ? 'Calendar days count' : 'Approved working days count' }} · Compensatory days require explicit Partner credits. Unpaid Leave requires final Partner approval and does not deduct salary automatically.</p>
@endif
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
<h3 class="text-base font-extrabold">Apply for Leave</h3>
<form action="{{ route('erp.leaves.submit',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Leave Type
<select name="leave_type" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
@foreach(['annual'=>'Annual','sick'=>'Sick','casual'=>'Casual','emergency'=>'Emergency','compensatory'=>'Compensatory Off','unpaid'=>'Unpaid Leave'] as $key=>$label)
<option value="{{ $key }}">{{ $label }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Day Portion
<select name="day_portion" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="full">Full Day(s)</option>
<option value="morning">Morning Half-day</option>
<option value="afternoon">Afternoon Half-day</option>
</select></label>
<label class="text-xs font-bold text-slate-700">Start Date<input type="date" name="date_from" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">End Date<input type="date" name="date_to" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<p class="md:col-span-2 text-xs text-slate-500">Half-day requires the same start/end date. Approved Partner holidays are excluded unless the policy counts calendar days. Split requests at a calendar-year boundary.</p>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Reason<textarea name="reason" rows="3" required minlength="10" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit Leave Request</button>
</form></div>
</section>
@endif

@if($canSubmitFor && $members->isNotEmpty())
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm sm:p-7">
<h3 class="text-base font-extrabold">Submit Leave for an Assigned Student</h3>
<p class="mt-2 text-xs text-slate-600">This does not impersonate the Student. The original request, your identity and a separate delegation reason are audited. A different Partner must approve; Managers cannot approve their own delegated applications.</p>
<form action="{{ route('erp.leaves.delegated.submit',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
<label class="text-xs font-bold">Assigned Student
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select assigned student</option>
@foreach($members as $member)
<option value="{{ $member->id }}">{{ $member->name }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold">Leave Type
<select name="leave_type" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
@foreach(['annual'=>'Annual','sick'=>'Sick','casual'=>'Casual','emergency'=>'Emergency','compensatory'=>'Compensatory','unpaid'=>'Unpaid'] as $key=>$label)
<option value="{{ $key }}">{{ $label }}</option>
@endforeach</select></label>
<label class="text-xs font-bold">Day Portion
<select name="day_portion" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="full">Full day</option><option value="morning">Morning half-day</option><option value="afternoon">Afternoon half-day</option></select></label>
<div class="hidden md:block"></div>
<label class="text-xs font-bold">From
<input type="date" name="date_from" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">To
<input type="date" name="date_to" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold md:col-span-2">Student's Leave Reason
<textarea name="reason" rows="2" required minlength="10" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<label class="text-xs font-bold md:col-span-2">Why are you submitting for the Student?
<textarea name="delegation_reason" rows="2" required minlength="15" maxlength="1000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Provide your justification and any supporting confirmation"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit for Independent Partner Review</button>
</form>
</section>
@endif

@if($actor->role==='partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
<h3 class="text-base font-extrabold">Configure Partner Leave Policy — {{ $year }}</h3>
<p class="mt-2 text-xs text-slate-600">Set day allowances for this Partner workspace only. There are no universal RA&C defaults. Policies with leave/credit activity or frozen payroll cannot be rewritten retroactively. Prior-year unused Annual Leave carries forward only when both years have a policy, subject to the new year's cap.</p>
<form method="POST" action="{{ route('erp.leaves.policy',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
<label class="text-xs font-bold text-slate-700">Policy Year<input name="leave_year" type="number" min="{{ $year }}" max="{{ $year+1 }}" value="{{ $year }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
@foreach(['annual_days'=>'Annual Days','sick_days'=>'Sick Days','casual_days'=>'Casual Days','emergency_days'=>'Emergency Days','carry_forward_limit'=>'Annual Carry Limit'] as $key=>$label)
<label class="text-xs font-bold text-slate-700">{{ $label }}<input name="{{ $key }}" type="number" min="0" max="365" step="0.5" value="{{ $policy?->{$key} ?? 0 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
@endforeach
<label class="text-xs font-bold text-slate-700">Annual Leave Notice (days)<input name="annual_notice_days" type="number" min="0" max="90" value="{{ $policy?->annual_notice_days ?? 0 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Holiday Charging Rule — Default: No
<select name="count_holidays" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="0" @selected((int)old('count_holidays', $policy?->count_holidays ?? \App\Support\LeaveBalance::COUNT_HOLIDAYS_DEFAULT) === 0)>No — Working Days Only (Default)</option>
<option value="1" @selected((int)old('count_holidays', $policy?->count_holidays ?? \App\Support\LeaveBalance::COUNT_HOLIDAYS_DEFAULT) === 1)>Yes — Count Calendar Days</option>
</select>
<span class="mt-1 block font-normal text-slate-500">Weekly and Partner-approved holidays are not deducted by default. Partners can explicitly choose Yes for this workspace.</span>
</label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold sm:col-span-2 lg:col-span-4">Save Partner Leave Policy</button>
</form>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Grant Compensatory / Additional Leave Credit</h3>
<p class="mt-1 text-xs text-slate-500">Credit requires an active policy for the selected year. The reason and authorizing Partner are permanently audited.</p>
<form method="POST" action="{{ route('erp.leaves.credit',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@csrf
<label class="text-xs font-bold text-slate-700">Student
<select name="membership_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">Select Student</option>
@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }}</option>@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Year<input name="leave_year" type="number" min="{{ $year }}" max="{{ $year+1 }}" value="{{ $year }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Credit Type
<select name="leave_type" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
@foreach(\App\Support\LeaveBalance::CREDIT_TYPES as $type)
<option value="{{ $type }}" @selected($type==='compensatory')>{{ ucfirst($type) }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Credit Days<input name="days" type="number" min="0.5" max="90" step="0.5" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Expiry (optional)<input name="expires_on" type="date" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 sm:col-span-2 lg:col-span-3">Reason<textarea name="reason" minlength="15" maxlength="1000" rows="2" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#17191F] px-4 py-3 text-sm font-extrabold text-white sm:col-span-2 lg:col-span-3">Authorize Leave Credit</button>
</form>
</section>
@endif

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Upcoming Approved Leave Calendar (next 60 days)</h3>
<div class="mt-3 grid gap-2 md:grid-cols-2">
@forelse($calendar as $event)
<div class="rounded-xl bg-amber-50 p-3 text-xs"><strong>{{ $event->member_name }}</strong>
<p class="mt-1">{{ $event->date_from }} → {{ $event->date_to }} · {{ ucfirst($event->leave_type) }} · {{ ucfirst($event->day_portion) }}</p></div>
@empty
<p class="text-xs text-slate-500">No approved upcoming leave in your authorized scope.</p>
@endforelse
</div></section>

<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Leave History & Review Queue</h3></div>
@include('erp.partials.history-filter',['historyKey'=>'leave','historyTitle'=>'Leave records','historyStatuses'=>['pending'=>'Pending','manager_reviewed'=>'Manager reviewed','approved'=>'Approved','rejected'=>'Rejected','withdrawn'=>'Withdrawn','cancelled'=>'Cancelled']])
<div class="divide-y divide-slate-100" data-corporate-history-group="leave">
@forelse($rows as $row)
<article data-corporate-history-item data-history-status="{{ $row->status }}" class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:justify-between">
<div><p class="text-sm font-bold">{{ $row->member_name }} <span class="text-xs font-normal text-slate-400">#{{ $row->id }}</span></p>
<p class="mt-1 text-xs text-slate-600">{{ ucfirst($row->leave_type) }} · {{ $row->date_from }} → {{ $row->date_to }} · {{ ucfirst($row->day_portion) }} @if($row->charge_days !== null)· {{ number_format($row->charge_days,1) }} days @endif</p>
<p class="mt-2 max-w-xl text-xs text-slate-500">{{ $row->reason }}</p>
@if($row->delegation_reason)
<p class="mt-2 text-xs font-semibold text-amber-800">Delegated by Manager Membership #{{ $row->initiated_by }} · {{ $row->delegation_reason }}</p>
@endif
@if($row->withdrawal_reason)
<p class="mt-2 text-xs text-rose-700">Withdrawal by #{{ $row->withdrawn_by }}: {{ $row->withdrawal_reason }}</p>
@endif
</div>
<div class="space-y-2">
<span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">{{ str_replace('_',' ',ucfirst($row->status)) }}</span>
@php($approvalMode=\App\Support\ApprovalFlow::mode($row))
@if(in_array($actor->role,['partner','manager']) && ($row->status==='pending' || ($actor->role==='partner' && $row->status==='manager_reviewed')) && !($actor->role==='manager' && $approvalMode==='partner_only'))
<form data-corporate-confirm="Review this leave decision? The student's request will advance or be rejected, and the action will be audited." action="{{ route('erp.leaves.decide',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<input type="text" name="decision_note" maxlength="1000" placeholder="Decision note (optional)" class="min-w-44 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">{{ $actor->role==='manager' && $approvalMode==='manager_partner' ? 'Review' : 'Approve' }}</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject</button></form>
@endif
@if($canWithdrawFor && $row->status==='pending'
    && (int)$row->initiated_by===(int)$actor->id && $row->delegation_reason)
<form action="{{ route('erp.leaves.delegated.withdraw',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}"
method="POST" data-corporate-confirm="Withdraw this pending delegated request? The Student and Partner will be notified."
class="mt-2 flex flex-wrap gap-2">@csrf
<input name="reason" required minlength="15" maxlength="1000" placeholder="Reason for withdrawal"
class="min-w-40 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button class="rounded-lg bg-rose-100 px-3 py-2 text-xs font-bold text-rose-900">Withdraw My Delegated Request</button>
</form>
@endif
@if($row->status==='approved')
<div class="flex flex-wrap gap-2" aria-label="Approved leave letter downloads">
  <a href="{{ route('erp.official.leave.print',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-extrabold text-emerald-900">Print / Save PDF</a>
  <a href="{{ route('erp.official.leave.word',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-extrabold text-slate-800">Download Word</a>
</div>
@endif
@if($actor->role==='student' && in_array($row->status,['pending','manager_reviewed']))
<form action="{{ route('erp.leaves.withdraw',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST">@csrf
<button class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-900">Withdraw Pending Request</button>
</form>
@endif
@if($actor->role==='partner' && $row->status==='approved' && $row->date_from > now($company->timezone)->toDateString())
<form data-corporate-confirm="Cancel this previously approved future leave? The authorized change will be audited and leave entitlement recalculated." action="{{ route('erp.leaves.cancel',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<input name="reason" required minlength="15" maxlength="1000" placeholder="Cancellation reason (mandatory)" class="min-w-44 rounded-lg border border-slate-300 p-2 text-xs">
<button class="rounded-lg bg-rose-100 px-3 py-2 text-xs font-bold text-rose-950">Cancel Approved Future Leave</button>
</form>
@endif
</div></article>
@empty
<p class="p-8 text-sm text-slate-500">No leave records in your authorized workspace.</p>
@endforelse
</div></section>
@endsection
