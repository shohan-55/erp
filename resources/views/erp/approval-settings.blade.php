@extends('layouts.erp')
@section('title','Approval Workflow Builder')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">WORKSPACE GOVERNANCE · AUDITABLE WORKFLOWS</p>
<h2 class="text-2xl font-extrabold">Approval Workflow Builder</h2>
<p class="max-w-3xl text-sm text-slate-600">Set the approval route separately for Leave and Conveyance within {{ $workspace->name }}. Only your Partner can change these rules. New requests snapshot the chosen route so pending records cannot silently change their approvers.</p>
<p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-xs text-amber-950">Single Manager approval is restricted to the student's <strong>assigned active Manager</strong>. If no Manager was assigned when submitted, the request is routed to Partner-only approval. Payroll freeze, leave balance, claim amount and evidence checks remain mandatory. No unrelated workspace can be accessed.</p>
</section>
@if(session('success'))<div role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
<div class="grid gap-5 lg:grid-cols-2">
@foreach($modules as $key=>$label)
@php($chosen=$policies[$key]->mode ?? 'manager_partner')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<h3 class="text-lg font-extrabold">{{ $label }}</h3>
<p class="mt-2 text-xs text-slate-600">Current workflow: <strong>{{ $modes[$chosen] }}</strong></p>
<form action="{{ route('erp.approval_settings.update',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 space-y-3">@csrf
<input type="hidden" name="module" value="{{ $key }}">
<fieldset class="space-y-3"><legend class="mb-3 text-xs font-extrabold">Choose Approval Sequence</legend>
@foreach($modes as $mode=>$title)
<label class="flex items-start gap-3 rounded-xl border border-slate-200 p-3 text-xs">
<input type="radio" name="mode" value="{{ $mode }}" required @checked($chosen===$mode) class="mt-1">
<span>{{ $title }}</span>
</label>
@endforeach</fieldset>
<label class="block text-xs font-bold">Reason for Changing Workflow
<textarea name="reason" rows="2" minlength="12" maxlength="1000" required placeholder="Why this approval routing is appropriate" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Save New Request Workflow</button>
</form>
</section>
@endforeach
</div>
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<h3 class="text-base font-extrabold">Approval Decision History</h3>
<p class="mt-2 text-xs text-slate-600">Immutable event history for this Partner Workspace. Each decision records the actor, previous and resulting status, approval route, and note.</p>
<div class="mt-4 overflow-x-auto">
<table class="w-full text-left text-xs">
<thead><tr class="border-b border-slate-200 text-slate-600"><th class="p-3">Time</th><th class="p-3">Module / Request</th><th class="p-3">Reviewer</th><th class="p-3">Decision</th><th class="p-3">Flow</th></tr></thead>
<tbody>@forelse($events as $event)
<tr class="border-b border-slate-100">
<td class="p-3">{{ $event->created_at }}</td>
<td class="p-3">{{ ucfirst($event->module) }} #{{ $event->request_id }}</td>
<td class="p-3">{{ $event->actor_name }}</td>
<td class="p-3 font-semibold">{{ ucfirst($event->action) }} · {{ $event->previous_status }} → {{ $event->new_status }}@if($event->note)<p class="mt-1 font-normal text-slate-500">{{ $event->note }}</p>@endif</td>
<td class="p-3">{{ $modes[$event->approval_mode] ?? $event->approval_mode }}</td>
</tr>
@empty<tr><td colspan="5" class="p-5 text-slate-500">No workflow decisions recorded under these new rules yet. Older decisions remain in the existing tenant audit log.</td></tr>
@endforelse</tbody></table>
</div>
</section>
@endsection
