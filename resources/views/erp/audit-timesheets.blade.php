@extends('layouts.erp')
@section('title','Audit Engagement Timesheets')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PARTNER PRACTICE · TIMESHEET TRACKING</p>
<h2 class="text-2xl font-extrabold">Audit Engagement Timesheets</h2>
<p class="max-w-3xl text-sm text-slate-600">Assigned staff log client engagement work in 15-minute increments. Managers may recommend decisions for their assigned Students; Partners make the final decision. Reported hours never create client invoices, verified attendance, or automatic payroll adjustments.</p>
<a class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold" href="{{ route('erp.engagements',['firmSlug'=>$firmSlug]) }}">Back to Engagements</a>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<form method="GET" action="{{ route('erp.timesheets',['firmSlug'=>$firmSlug]) }}" class="flex flex-wrap items-end gap-3">
<label class="text-xs font-bold">Timesheet Month<input type="month" name="month" required value="{{ $month }}" class="mt-2 block rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-lg bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Review Month</button>
@if($actor->role==='partner')
<a href="{{ route('erp.timesheets.export',['firmSlug'=>$firmSlug,'month'=>$month]) }}" class="rounded-lg border border-slate-300 px-4 py-3 text-xs font-bold">Export Private Monthly Register</a>
@endif
</form>
</section>
<section class="grid grid-cols-2 gap-3 md:grid-cols-4">
@foreach([
['label'=>'Approved Hours','value'=>number_format((float)$summary['hours_approved'],2)],
['label'=>'Approved Billable Hours','value'=>number_format((float)$summary['hours_billable'],2)],
['label'=>'Awaiting Review (Hours)','value'=>number_format((float)$summary['hours_submitted'],2)],
['label'=>'Pending Items','value'=>$summary['pending_count']],
] as $card)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
<p class="text-xs text-slate-600">{{ $card['label'] }}</p><p class="mt-2 text-2xl font-extrabold tabular-nums">{{ $card['value'] }}</p></div>
@endforeach
</section>
@if(in_array($actor->role,['student','manager'],true))
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Submit My Engagement Working Hours</h3>
<p class="mt-2 text-xs text-slate-600">Only active assignments are available. Work dates must fall within the engagement and the last 60 days. Total submitted/approved working hours across assignments cannot exceed 16 hours for one day.</p>
<form method="POST" action="{{ route('erp.timesheets.submit',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold sm:col-span-2">Assigned Engagement
<select name="engagement_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select your engagement</option>
@foreach($options as $engagement)
<option value="{{ $engagement->id }}">{{ $engagement->code }} — {{ $engagement->title }} ({{ $engagement->starts_on }} → {{ $engagement->ends_on }})</option>
@endforeach
</select></label>
<label class="text-xs font-bold">Date of Work<input type="date" name="work_date" min="{{ now($company->timezone)->subDays(60)->toDateString() }}" max="{{ now($company->timezone)->toDateString() }}" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Hours<input type="number" name="hours" min=".25" max="16" step=".25" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Work Classification
<select name="billable" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="1">Billable / Chargeable Hours (reporting only)</option>
<option value="0">Non-billable Internal Work</option>
</select></label>
<label class="text-xs font-bold sm:col-span-2">Work Description
<textarea name="work_description" rows="3" minlength="15" maxlength="2000" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Describe audit procedures, working paper, client visit or assignment output."></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2">Submit Hours for Review</button>
</form>
</section>
@endif
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Authorized Work Log · {{ $month }}</h3>
<p class="mt-1 text-xs text-slate-600">Limited to your Partner workspace and assigned team. A Manager cannot approve their own timesheet.</p></div>
<div class="divide-y divide-slate-100">
@forelse($rows as $item)
<article class="p-5">
<div class="flex flex-wrap items-start justify-between gap-3">
<div>
<p class="text-sm font-extrabold">{{ $item->staff_name }} · {{ $item->engagement_code }}</p>
<p class="mt-1 text-xs text-slate-600">{{ $item->work_date }} · {{ number_format((float)$item->hours,2) }} hours · {{ $item->billable ? 'Billable' : 'Non-billable' }}</p>
<p class="mt-1 text-xs font-bold text-slate-800">{{ $item->engagement_title }}</p>
<p class="mt-2 text-xs text-slate-600">{{ $item->work_description }}</p>
@if($item->manager_recommendation)
<p class="mt-2 text-xs font-bold text-amber-900">Manager recommended: {{ ucfirst($item->manager_recommendation) }}</p>
@endif
</div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $item->status==='approved' ? 'bg-emerald-100 text-emerald-900' : ($item->status==='rejected' ? 'bg-rose-100 text-rose-900' : 'bg-amber-100 text-amber-900') }}">{{ str_replace('_',' ',ucfirst($item->status)) }}</span>
</div>
@if($actor->role==='student' && $item->membership_id===$actor->id && in_array($item->status,['submitted','manager_reviewed'],true) ||
    $actor->role==='manager' && $item->membership_id===$actor->id && in_array($item->status,['submitted','manager_reviewed'],true))
<form class="mt-3" method="POST" action="{{ route('erp.timesheets.withdraw',['firmSlug'=>$firmSlug,'id'=>$item->id]) }}">@csrf
<button class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Withdraw My Unapproved Timesheet</button></form>
@endif
@if(in_array($actor->role,['partner','manager'],true) &&
    in_array($item->status,['submitted','manager_reviewed'],true) &&
    ($actor->role==='partner' || ($item->status==='submitted' && $item->membership_id!=$actor->id)))
<form method="POST" action="{{ route('erp.timesheets.decide',['firmSlug'=>$firmSlug,'id'=>$item->id]) }}" class="mt-3 flex flex-wrap items-end gap-2">@csrf
<label class="min-w-48 flex-1 text-xs font-bold">Review Note (optional)
<input name="decision_note" maxlength="1000" class="mt-2 w-full rounded-lg border border-slate-300 p-2 text-xs"></label>
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-extrabold text-white">{{ $actor->role==='manager' ? 'Recommend Approval' : 'Approve' }}</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-extrabold text-white">{{ $actor->role==='manager' ? 'Recommend Rejection' : 'Reject' }}</button>
</form>
@endif
</article>
@empty<p class="p-8 text-sm text-slate-500">No engagement working hours have been logged for this month in your authorized scope.</p>
@endforelse
</div></section>
@endsection
