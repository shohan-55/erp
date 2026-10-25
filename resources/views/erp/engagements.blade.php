@extends('layouts.erp')
@section('title','Audit Clients & Engagements')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">AUDIT PRACTICE · PARTNER ISOLATED</p>
<h2 class="text-2xl font-extrabold">Audit Clients & Engagements</h2>
<p class="max-w-3xl text-sm text-slate-600">Client identity, engagement plans, staff assignments and approved hours belong only to the current Partner practice. A Manager sees only engagements worked on by their assigned team. Billable hours are reporting information, not client invoices or payroll.</p>
<a class="inline-flex rounded-xl bg-[#FCDF02] px-4 py-2 text-xs font-extrabold" href="{{ route('erp.timesheets',['firmSlug'=>$firmSlug]) }}">Timesheet & Approval Inbox</a>
</section>

@if($actor->role==='partner')
<section class="grid gap-5 xl:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Register a Practice Client</h3>
<form method="POST" action="{{ route('erp.engagements.clients.create',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold">Client Code<input name="code" required maxlength="40" placeholder="CLIENT-001" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Client Name<input name="name" required minlength="3" maxlength="180" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Industry<input name="industry" maxlength="100" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Contact Person<input name="contact_name" maxlength="120" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Contact Email<input name="contact_email" type="email" maxlength="180" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold sm:col-span-2">Save Client</button>
</form></div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Create Audit Engagement</h3>
<form method="POST" action="{{ route('erp.engagements.create',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold">Client
<select name="audit_client_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">Select Client</option>
@foreach($clients->where('active',true) as $client)<option value="{{ $client->id }}">{{ $client->code }} — {{ $client->name }}</option>@endforeach
</select></label>
<label class="text-xs font-bold">Engagement Code<input name="code" maxlength="50" required placeholder="AUD-2026-001" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Engagement Title<input name="title" required maxlength="180" placeholder="Annual Statutory Audit FY 2026" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Type<select name="engagement_type" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="statutory">Statutory Audit</option><option value="internal">Internal Audit</option>
<option value="tax">Tax Assignment</option><option value="advisory">Advisory</option>
<option value="other">Other Engagement</option>
</select></label>
<label class="text-xs font-bold">Financial Period End<input type="date" name="period_end" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Assignment Starts<input type="date" name="starts_on" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Assignment Ends<input type="date" name="ends_on" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Planned Hours (not a billing amount)<input type="number" name="budget_hours" value="0" min="0" step=".25" max="100000" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold sm:col-span-2">Create Engagement</button>
</form></div>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="text-base font-extrabold">Partner Client Register</h3>
<div class="mt-4 grid gap-3 lg:grid-cols-2">
@forelse($clients as $client)
<article class="rounded-xl border border-slate-200 p-4">
<div class="flex items-center justify-between gap-2"><h4 class="text-sm font-extrabold">{{ $client->code }} · {{ $client->name }}</h4><span class="text-xs">{{ $client->active ? 'Active' : 'Archived' }}</span></div>
<p class="mt-1 text-xs text-slate-500">{{ $client->industry }} @if($client->contact_name) · Contact: {{ $client->contact_name }}@endif</p>
@if($client->active)
<details class="mt-3 rounded-lg bg-slate-50 p-3"><summary class="cursor-pointer text-xs font-bold">Edit Client</summary>
<form method="POST" action="{{ route('erp.engagements.clients.update',['firmSlug'=>$firmSlug,'id'=>$client->id]) }}" class="mt-3 grid gap-2">@csrf
<input type="text" name="code" required value="{{ $client->code }}" maxlength="40" class="rounded-lg border p-2 text-xs">
<input type="text" name="name" required minlength="3" maxlength="180" value="{{ $client->name }}" class="rounded-lg border p-2 text-xs">
<input type="text" name="industry" maxlength="100" value="{{ $client->industry }}" placeholder="Industry" class="rounded-lg border p-2 text-xs">
<input type="text" name="contact_name" maxlength="120" value="{{ $client->contact_name }}" placeholder="Contact Person" class="rounded-lg border p-2 text-xs">
<input type="email" name="contact_email" maxlength="180" value="{{ $client->contact_email }}" placeholder="Contact Email" class="rounded-lg border p-2 text-xs">
<button class="rounded-lg bg-slate-900 p-2 text-xs font-bold text-white">Save Changes</button></form></details>
<form class="mt-2" method="POST" action="{{ route('erp.engagements.clients.archive',['firmSlug'=>$firmSlug,'id'=>$client->id]) }}">@csrf
<button class="text-xs font-bold text-rose-800">Archive Client (when all engagements closed)</button></form>
@endif</article>
@empty<p class="text-sm text-slate-500">No clients registered yet.</p>@endforelse
</div></section>
@endif

<section class="space-y-4">
<h3 class="text-base font-extrabold">Authorized Engagements</h3>
@forelse($engagements as $engagement)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex flex-wrap items-start justify-between gap-3">
<div><p class="text-xs font-bold uppercase tracking-widest text-amber-800">{{ $engagement->code }} · {{ strtoupper($engagement->engagement_type) }}</p>
<h4 class="mt-2 text-base font-extrabold">{{ $engagement->title }}</h4><p class="mt-1 text-xs text-slate-600">{{ $engagement->client_name }} · {{ $engagement->starts_on }} → {{ $engagement->ends_on }} · {{ ucfirst($engagement->status) }}</p></div>
<div class="rounded-xl bg-slate-50 p-3 text-right"><p class="text-xs text-slate-500">{{ $actor->role==='partner' ? 'Approved Engagement Hours' : 'Approved Hours in My Authorized Team Scope' }}</p>
<p class="mt-1 text-lg font-extrabold">{{ number_format((float)($hours[$engagement->id]->approved_hours ?? 0),2) }} <span class="text-xs font-medium">/ {{ number_format((float)$engagement->budget_hours,2) }} planned</span></p></div>
</div>
<p class="mt-3 text-xs text-slate-600">Assigned Team:
@forelse($assignments->get($engagement->id,collect()) as $member)<span class="mr-2 inline-block rounded-lg bg-slate-100 px-2 py-1">{{ $member->name }} ({{ $member->role }})</span>
@empty <span class="text-slate-400">No visible assignments</span> @endforelse
</p>
@php
  $tasks=$milestones->get($engagement->id,collect());
  $done=$tasks->where('status','completed')->count();
  $pending=$tasks->whereIn('status',['submitted','manager_reviewed'])->count();
  $overdue=$tasks->filter(fn($task)=>!in_array($task->status,['completed'],true)
      && $task->due_on < now($company->timezone)->toDateString())->count();
  $approved=(float)($hours[$engagement->id]->approved_hours??0);
  $planned=(float)$engagement->budget_hours;
@endphp
<div class="mt-4 grid gap-2 sm:grid-cols-3">
<div class="rounded-xl bg-slate-50 p-3"><p class="text-xs font-bold text-slate-600">Milestone Progress · {{ $actor->role==='partner'?'Practice':'My / Assigned Team' }}</p>
<p class="mt-2 text-lg font-extrabold">{{ $done }} / {{ $tasks->count() }} <span class="text-xs font-normal">completed</span></p></div>
<div class="rounded-xl bg-amber-50 p-3"><p class="text-xs font-bold text-amber-900">Awaiting Review</p>
<p class="mt-2 text-lg font-extrabold">{{ $pending }} <span class="text-xs font-normal">pending</span></p></div>
<div class="rounded-xl bg-rose-50 p-3"><p class="text-xs font-bold text-rose-900">Past Due</p>
<p class="mt-2 text-lg font-extrabold">{{ $overdue }} <span class="text-xs font-normal">tasks</span></p></div>
</div>
@if($actor->role==='partner' && $planned>0 && $approved>$planned)
<p class="mt-3 rounded-xl border border-rose-200 bg-rose-50 p-3 text-xs font-bold text-rose-900">
<icon name="alert-triangle" inline/> Planned hours exceeded: {{ number_format($approved-$planned,2) }} additional approved hours. Budget is for monitoring; it never changes salary or invoices automatically.
</p>
@endif
@if($actor->role==='partner' && $engagement->status==='open')
<details class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4">
<summary class="cursor-pointer text-xs font-extrabold">Create & Assign an Engagement Milestone</summary>
<form method="POST" action="{{ route('erp.engagements.milestones.store',['firmSlug'=>$firmSlug,'engagementId'=>$engagement->id]) }}" class="mt-3 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold">Milestone Title<input required name="title" minlength="3" maxlength="180" placeholder="Complete sample testing workpapers" class="mt-1 w-full rounded-lg border border-amber-300 p-3 text-xs"></label>
<label class="text-xs font-bold">Assigned Staff<select required name="membership_id" class="mt-1 w-full rounded-lg border border-amber-300 p-3 text-xs">
<option value="">Select assigned team member</option>
@foreach($assignments->get($engagement->id,collect()) as $assigned)
<option value="{{ $assigned->membership_id }}">{{ $assigned->name }}</option>@endforeach
</select></label>
<label class="text-xs font-bold">Due Date<input required type="date" name="due_on" min="{{ $engagement->starts_on }}" max="{{ $engagement->ends_on }}" class="mt-1 w-full rounded-lg border border-amber-300 p-3 text-xs"></label>
<label class="text-xs font-bold sm:col-span-2">Instructions<textarea name="description" maxlength="2000" rows="2" class="mt-1 w-full rounded-lg border border-amber-300 p-3 text-xs"></textarea></label>
<button class="rounded-lg bg-[#FCDF02] px-4 py-3 text-xs font-extrabold sm:col-span-2">Assign Milestone</button>
</form></details>
@endif
<div class="mt-4 space-y-2">
<h5 class="text-sm font-extrabold">Engagement Milestones</h5>
@forelse($tasks as $task)
<div class="rounded-xl border border-slate-200 bg-white p-3 text-xs">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $task->title }}</strong>
<span class="rounded-lg px-2 py-1 font-bold {{ $task->status==='completed'?'bg-emerald-100 text-emerald-900':($task->status==='rejected'?'bg-rose-100 text-rose-900':'bg-amber-100 text-amber-900') }}">{{ ucwords(str_replace('_',' ',$task->status)) }}</span>
</div>
<p class="mt-1 text-slate-600">Assigned: {{ $task->assigned_name }} · Due {{ $task->due_on }}</p>
@if($task->description)<p class="mt-1 text-slate-600">{{ $task->description }}</p>@endif
@if($task->submission_note)<p class="mt-2 text-slate-700"><strong>Work submitted:</strong> {{ $task->submission_note }}</p>@endif
@if($task->decision_note)<p class="mt-1 text-slate-700"><strong>Review:</strong> {{ $task->decision_note }}</p>@endif
@if(in_array($actor->role,['student','manager'],true) && (int)$task->membership_id===(int)$actor->id
   && in_array($task->status,['open','rejected'],true) && $engagement->status==='open')
<form method="POST" action="{{ route('erp.engagements.milestones.submit',['firmSlug'=>$firmSlug,'id'=>$task->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<input name="submission_note" required minlength="15" maxlength="2000" placeholder="Describe completed work and evidence" class="min-w-48 flex-1 rounded-lg border p-2 text-xs">
<button class="rounded-lg bg-slate-900 px-3 py-2 font-bold text-white">Submit Milestone</button></form>
@endif
@if($actor->role==='manager' && $task->assigned_role==='student'
    && (int)$task->assigned_manager_id===(int)$actor->id && $task->status==='submitted')
<form method="POST" action="{{ route('erp.engagements.milestones.decide',['firmSlug'=>$firmSlug,'id'=>$task->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<input name="decision_note" required minlength="15" maxlength="1000" placeholder="Review evidence and rationale" class="min-w-48 flex-1 rounded-lg border p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-100 px-3 py-2 font-bold text-emerald-900">Recommend Accept</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-100 px-3 py-2 font-bold text-rose-900">Recommend Reject</button></form>
@endif
@if($actor->role==='partner' && in_array($task->status,['submitted','manager_reviewed'],true))
<form method="POST" action="{{ route('erp.engagements.milestones.decide',['firmSlug'=>$firmSlug,'id'=>$task->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<input name="decision_note" required minlength="15" maxlength="1000" placeholder="Independent Partner decision reason" class="min-w-48 flex-1 rounded-lg border p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 font-bold text-white">Complete Task</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 font-bold text-white">Return for Rework</button></form>
@endif
@if($actor->role==='partner' && in_array($task->status,['open','rejected'],true) && $engagement->status==='open')
<details class="mt-3"><summary class="cursor-pointer font-bold text-slate-800">Edit or Reassign Unsubmitted Task</summary>
<form method="POST" action="{{ route('erp.engagements.milestones.update',['firmSlug'=>$firmSlug,'id'=>$task->id]) }}" class="mt-2 grid gap-2 sm:grid-cols-2">@csrf
<input name="title" value="{{ $task->title }}" required minlength="3" maxlength="180" class="rounded-lg border p-2">
<select name="membership_id" required class="rounded-lg border p-2">
@foreach($assignments->get($engagement->id,collect()) as $assigned)
<option value="{{ $assigned->membership_id }}" @selected($assigned->membership_id==$task->membership_id)>{{ $assigned->name }}</option>
@endforeach
</select>
<input name="due_on" type="date" value="{{ $task->due_on }}" min="{{ $engagement->starts_on }}" max="{{ $engagement->ends_on }}" required class="rounded-lg border p-2">
<textarea name="description" maxlength="2000" rows="2" class="rounded-lg border p-2">{{ $task->description }}</textarea>
<input name="reason" required minlength="15" maxlength="1000" placeholder="Reason for change" class="rounded-lg border p-2 sm:col-span-2">
<button class="rounded-lg bg-slate-900 px-3 py-2 font-bold text-white sm:col-span-2">Save and Audit Change</button>
</form></details>
@endif
</div>
@empty<p class="text-xs text-slate-500">No milestones visible in your authorized team scope.</p>
@endforelse
</div>
@if($actor->role==='partner' && $engagement->status==='open')
<div class="mt-4 grid gap-3 border-t border-slate-100 pt-4 xl:grid-cols-2">
<form method="POST" action="{{ route('erp.engagements.assign',['firmSlug'=>$firmSlug,'id'=>$engagement->id]) }}" class="flex flex-wrap items-end gap-2">@csrf
<label class="flex-1 text-xs font-bold">Assigned Manager / Student
<select name="membership_id" required class="mt-2 w-full rounded-lg border p-3 text-xs"><option value="">Choose staff</option>
@foreach($people as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ ucfirst($member->role) }}</option>@endforeach</select></label>
<label class="text-xs font-bold">Action<select name="active" class="mt-2 rounded-lg border p-3 text-xs"><option value="1">Assign / Reactivate</option><option value="0">Unassign</option></select></label>
<button class="rounded-lg bg-[#FCDF02] px-3 py-3 text-xs font-extrabold">Save Assignment</button></form>
<div class="flex flex-col gap-2">
<details class="rounded-lg bg-slate-50 p-3"><summary class="cursor-pointer text-xs font-bold">Edit Engagement</summary>
<form method="POST" action="{{ route('erp.engagements.update',['firmSlug'=>$firmSlug,'id'=>$engagement->id]) }}" class="mt-3 grid gap-2 sm:grid-cols-2">@csrf
<select name="audit_client_id" required class="rounded-lg border p-2 text-xs">
@foreach($clients->where('active',true) as $client)<option value="{{ $client->id }}" @selected($client->id===$engagement->audit_client_id)>{{ $client->code }}</option>@endforeach</select>
<input name="code" required value="{{ $engagement->code }}" class="rounded-lg border p-2 text-xs">
<input name="title" required value="{{ $engagement->title }}" class="rounded-lg border p-2 text-xs sm:col-span-2">
<select name="engagement_type" class="rounded-lg border p-2 text-xs">@foreach(['statutory','internal','tax','advisory','other'] as $type)<option value="{{ $type }}" @selected($engagement->engagement_type===$type)>{{ ucfirst($type) }}</option>@endforeach</select>
<input name="period_end" type="date" value="{{ $engagement->period_end }}" class="rounded-lg border p-2 text-xs">
<input name="starts_on" type="date" value="{{ $engagement->starts_on }}" required class="rounded-lg border p-2 text-xs">
<input name="ends_on" type="date" value="{{ $engagement->ends_on }}" required class="rounded-lg border p-2 text-xs">
<input name="budget_hours" type="number" min="0" max="100000" step=".25" value="{{ $engagement->budget_hours }}" required class="rounded-lg border p-2 text-xs">
<button class="rounded-lg bg-slate-900 p-2 text-xs font-bold text-white sm:col-span-2">Save Engagement</button></form></details>
<form method="POST" action="{{ route('erp.engagements.close',['firmSlug'=>$firmSlug,'id'=>$engagement->id]) }}">@csrf
<button class="rounded-lg border border-rose-200 p-2 text-xs font-bold text-rose-900">Close Completed Engagement</button></form></div>
</div>
@endif
</article>
@empty<p class="rounded-xl bg-white p-6 text-sm text-slate-500">No engagements in your authorized Partner/team scope.</p>@endforelse
</section>
@endsection
