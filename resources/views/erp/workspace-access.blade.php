@extends('layouts.erp')
@section('title','Company Access Control')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">COMPANY SECURITY · ISOLATED PARTNER WORKSPACES</p>
<h2 class="text-2xl font-extrabold">Company Access Control Center</h2>
<p class="max-w-3xl text-sm text-slate-600">Grant temporary or ongoing Moderator / Company Admin permissions within <strong>{{ $workspace->name }}</strong>. All actions are checked server-side. Permission expiry and grantor revocation take effect without a scheduled cleanup job.</p>
<p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950"><strong>Full Access</strong> grants all <em>currently supported</em> delegated workspace operations: attendance correction proposal and organization view/manage. It never grants partner ownership, final leave/payroll approval, salary access, platform administration, student impersonation or access to another Partner. New operations require separately reviewed authorization before they can be delegated.</p>
</section>
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<section class="grid gap-6 lg:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Grant / Update Scoped Delegation</h3>
<form method="POST" action="{{ route('erp.workspace_access.save',['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-4">@csrf
<label class="block text-xs font-bold text-slate-700">Active Manager
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select verified Manager account</option>
@foreach($members as $member)
@if($member->id !== $actor->id)
<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</option>
@endif
@endforeach
</select></label>
<label class="block text-xs font-bold text-slate-700">Delegated Role
<select name="access_role" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="moderator">Moderator — operational scope only</option>
@if($actor->role === 'partner')
<option value="company_admin">Company Admin — may manage limited delegates if explicitly permitted</option>
@endif
</select></label>
<label class="block text-xs font-bold text-slate-700">Delegation Mode
<select name="delegation_mode" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="limited">Partial / Selected Actions</option>
@if($actor->role === 'partner')
<option value="full">Full Supported Operations (never Platform/Payroll/Final Approval)</option>
@endif
</select></label>
<fieldset class="space-y-3 rounded-xl border border-slate-200 p-4">
<legend class="px-2 text-xs font-extrabold text-slate-700">Explicit Rights (Partial Mode; Admin Delegation Right for Full Mode)</legend>
@foreach($permissions as $key=>$label)
@if($actor->role === 'partner' || $key !== 'access.delegate')
<label class="flex items-start gap-3 text-xs text-slate-700">
<input type="checkbox" name="permissions[]" value="{{ $key }}" class="mt-0.5 rounded border-slate-300" @checked(in_array($key,old('permissions',[])))>
<span>{{ $label }}</span></label>
@endif
@endforeach
</fieldset>
<div class="grid gap-3 sm:grid-cols-2">
<label class="block text-xs font-bold text-slate-700">Starts At (optional)
<input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
</label>
<label class="block text-xs font-bold text-slate-700">Expires At (optional for Partner grants)
<input type="datetime-local" name="expires_at" value="{{ old('expires_at') }}" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
</label></div>
<p class="text-xs text-slate-500">Times follow ERP server timezone ({{ config('app.timezone','UTC') }}). Company Admin subdelegations must be Partial, Moderator-only, explicitly scoped and expire within 30 days (and no later than their parent grant).</p>
<label class="block text-xs font-bold text-slate-700">Reason for Grant or Change
<textarea name="reason" rows="2" required minlength="10" maxlength="500" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Why is this access necessary?">{{ old('reason') }}</textarea>
</label>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Save Audited Delegation</button>
</form>
</div>
<div class="space-y-3">
<h3 class="font-extrabold">Workspace Delegates & Validity</h3>
@forelse($grants as $grant)
@php
    $started = !$grant->starts_at || \Illuminate\Support\Carbon::parse($grant->starts_at)->lte(now());
    $expired = $grant->expires_at && \Illuminate\Support\Carbon::parse($grant->expires_at)->lte(now());
    $effective = \App\Support\WorkspaceAccess::grant((object)['id'=>$grant->membership_id,'company_id'=>$grant->company_id,'workspace_id'=>$grant->workspace_id,'role'=>'manager','active'=>true,'platform_suspended_at'=>null]) !== null;
@endphp
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex flex-wrap items-center justify-between gap-2"><div>
<strong class="text-sm">{{ $grant->name }}</strong><p class="mt-1 text-xs text-slate-500">{{ $grant->email }}</p></div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $effective ? 'bg-emerald-50 text-emerald-900' : 'bg-rose-50 text-rose-900' }}">{{ $effective ? 'Effective' : ($expired ? 'Expired' : ($started ? 'Inactive / Parent revoked' : 'Scheduled')) }}</span>
</div>
<p class="mt-3 text-xs font-bold text-slate-700">{{ $grant->access_role === 'company_admin' ? 'Company Admin' : 'Moderator' }} · {{ $grant->delegation_mode === 'full' ? 'Full Supported Operations' : 'Partial' }}</p>
<p class="mt-2 text-xs text-slate-600">Selected rights: {{ implode(', ',json_decode($grant->permissions,true)?:[]) ?: 'None explicitly selected' }}</p>
<p class="mt-2 text-xs text-slate-500">From: {{ $grant->starts_at ?? 'Immediately' }} · Until: {{ $grant->expires_at ?? 'No expiry' }}</p>
@if($grant->parent_grant_id)<p class="mt-1 text-xs text-amber-800">Dependent grant #{{ $grant->parent_grant_id }} — revoked when parent is no longer valid</p>@endif
@if($grant->membership_id !== $actor->id && ($actor->role === 'partner' || $grant->parent_grant_id))
<form method="POST" action="{{ route('erp.workspace_access.revoke',['firmSlug'=>$firmSlug,'id'=>$grant->id]) }}" class="mt-3 flex flex-wrap items-center gap-2" data-corporate-confirm="Revoke delegation and dependent access? This action is audited.">@csrf
<input name="reason" required minlength="10" maxlength="500" placeholder="Reason (required)" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-900">Revoke</button></form>
@endif
</article>
@empty<p class="rounded-xl bg-slate-100 p-5 text-sm text-slate-600">No active or scheduled delegations in this Partner workspace. Base Partner rights remain intact.</p>
@endforelse
</div>
</section>
@endsection
