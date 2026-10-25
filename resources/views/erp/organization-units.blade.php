@extends('layouts.erp')
@section('title','Organization Structure')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">ORGANIZATION · WORKSPACE STRUCTURE</p>
<h2 class="text-2xl font-extrabold">Structure & Team Assignment</h2>
<p class="max-w-3xl text-sm text-slate-600">{{ \App\Support\OrganizationTemplate::TYPES[\App\Support\OrganizationTemplate::type($company->organization_type)] }} · {{ $workspace->name }}. Build offices, branches, departments, practices, programs and teams. Every unit remains private to this workspace.</p>
<p class="text-xs font-semibold text-amber-800">Suggested starter units: @foreach($recommended as $type){{ $kinds[$type] }}{{ !$loop->last ? ' → ' : '' }}@endforeach. These suggestions are optional; use any supported unit type.</p>
</section>
<section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(290px,1fr)]">
<div class="space-y-4">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Organization Units</h3><p class="mt-1 text-xs text-slate-500">Scoped to your selected Partner workspace · {{ $units->count() }} displayed</p></div>
<div class="divide-y divide-slate-100">
@forelse($units as $unit)
<article class="space-y-3 p-5">
<div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0">
<p class="font-bold text-slate-900">{{ $unit->name }}</p>
<p class="mt-1 text-xs text-slate-500">{{ $kinds[$unit->kind] ?? ucfirst($unit->kind) }} · {{ $unit->slug }} · Unit #{{ $unit->id }}</p>
@if($unit->parent_id)
@php($parent=$units->firstWhere('id',$unit->parent_id))
<p class="mt-1 text-xs text-slate-500">Parent: {{ $parent?->name ?? 'Other scoped unit' }}</p>
@endif
</div>
<span class="rounded-lg px-3 py-1 text-xs font-semibold {{ $unit->active ? 'bg-emerald-50 text-emerald-900' : 'bg-slate-100 text-slate-700' }}">{{ $unit->active ? 'Active' : 'Inactive' }}</span>
</div>
@if($unit->active)
<form method="POST" action="{{ route('erp.organization.rename',['firmSlug'=>$firmSlug,'unitId'=>$unit->id]) }}" class="flex flex-wrap gap-2">@csrf
<input name="name" value="{{ $unit->name }}" required minlength="2" maxlength="160" aria-label="Unit name" class="min-w-[130px] flex-1 rounded-lg border border-slate-300 p-2.5 text-xs">
<button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Rename</button></form>
@endif
<div class="rounded-xl bg-slate-50 p-3">
<p class="text-xs font-bold text-slate-800">Assigned members</p>
<div class="mt-2 flex flex-wrap gap-2">
@forelse(($assignments[$unit->id] ?? collect()) as $assigned)
<form method="POST" action="{{ route('erp.organization.unassign',['firmSlug'=>$firmSlug,'unitId'=>$unit->id,'membershipId'=>$assigned->membership_id]) }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1 text-xs">@csrf
<span>{{ $assigned->name }}</span><button class="font-bold text-rose-800" aria-label="Remove {{ $assigned->name }} from unit">×</button></form>
@empty<p class="text-xs text-slate-500">No members assigned.</p>@endforelse
</div>
@if($unit->active)
<form method="POST" action="{{ route('erp.organization.assign',['firmSlug'=>$firmSlug,'unitId'=>$unit->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf
<select name="membership_id" required aria-label="Choose workspace member" class="min-w-[160px] flex-1 rounded-lg border border-slate-300 p-2.5 text-xs">
<option value="">Choose an active member</option>@foreach($members as $person)
<option value="{{ $person->id }}">{{ $person->name }} ({{ ucfirst($person->role) }})</option>@endforeach
</select>
<button class="rounded-lg bg-[#FCDF02] px-3 py-2 text-xs font-bold">Assign</button>
</form>
@endif
</div>
<details class="rounded-lg border border-slate-200 p-3">
<summary class="cursor-pointer text-xs font-bold text-slate-600">{{ $unit->active ? 'Deactivate unit' : 'Reactivate unit' }}</summary>
<form action="{{ route('erp.organization.toggle',['firmSlug'=>$firmSlug,'unitId'=>$unit->id]) }}" method="POST" class="mt-3 space-y-2">@csrf
<input type="hidden" name="action" value="{{ $unit->active ? 'deactivate' : 'reactivate' }}">
<label class="block text-xs font-semibold text-slate-700">Reason<input name="reason" required minlength="10" maxlength="500" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-xs"></label>
<p class="text-xs text-slate-500">Deactivate child units and unassign members first. Historical identities and attendance remain untouched.</p>
<button class="rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold">Confirm status change</button></form>
</details>
</article>
@empty<p class="p-7 text-sm text-slate-500">No organization units yet. Create your first unit using the form.</p>@endforelse
</div>
</div>
</div>
<aside class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Create Organizational Unit</h3>
<form action="{{ route('erp.organization.store',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-4 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-700">Unit Name<input name="name" required minlength="2" maxlength="160" placeholder="Audit Division" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">URL-friendly Identifier<input name="slug" required minlength="2" maxlength="80" pattern="[a-z0-9][a-z0-9-]*[a-z0-9]" placeholder="audit-division" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Unit Type<select name="kind" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
@foreach($kinds as $key=>$label)<option value="{{ $key }}" @selected($key===$recommended[0])>{{ $label }}</option>@endforeach</select></label>
<label class="block text-xs font-bold text-slate-700">Parent Unit (optional)<select name="parent_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="">Company / Workspace root</option>@foreach($units as $candidate)@if($candidate->active)
<option value="{{ $candidate->id }}">{{ $candidate->name }}</option>@endif @endforeach</select></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Create Unit</button>
</form></div>
<div class="rounded-2xl bg-[#17191F] p-5 text-white"><h3 class="font-bold">Privacy & Existing Data</h3><p class="mt-2 text-xs leading-relaxed text-slate-300">This structure is an additional organizational layer. It does not alter Partner workspaces, change payroll owners, transfer students, or grant anyone new permissions. Only a Partner of this workspace can manage these units.</p></div>
</aside></section>
@endsection
