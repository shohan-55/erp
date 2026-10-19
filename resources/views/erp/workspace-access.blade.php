@extends('layouts.erp')
@section('title','Company Access Control')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">COMPANY SECURITY · HOME PARTNER BOUNDARIES</p>
<h2 class="text-2xl font-extrabold">Company Access Control Center</h2>
<p class="max-w-3xl text-sm text-slate-600">Assign a Company Admin or Moderator to an existing, verified Manager in <strong>{{ $workspace->name }}</strong>. Company Admins can add and revoke delegates within this Partner workspace; they cannot grant DhakaWala Platform Admin access or see another Partner's private files.</p>
</section>
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<section class="grid gap-6 lg:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Grant / Update Access</h3>
<form method="POST" action="{{ route('erp.workspace_access.save',['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-4">@csrf
<label class="block text-xs font-bold text-slate-700">Active Manager
<select name="membership_id" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select verified account</option>
@foreach($members as $member)
@if($member->id !== $actor->id)
<option value="{{ $member->id }}">{{ $member->name }} · {{ $member->email }}</option>
@endif
@endforeach
</select></label>
<label class="block text-xs font-bold text-slate-700">Delegated Role
<select name="access_role" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="moderator">Moderator</option>
<option value="company_admin">Company Admin — can manage other delegates</option>
</select></label>
<fieldset class="space-y-3 rounded-xl border border-slate-200 p-4">
<legend class="px-2 text-xs font-extrabold text-slate-700">Granular Permissions</legend>
@foreach(\App\Support\WorkspaceAccess::PERMISSIONS as $key=>$label)
<label class="flex items-start gap-3 text-xs text-slate-700">
<input type="checkbox" name="permissions[]" value="{{ $key }}" class="mt-0.5 rounded border-slate-300" @checked(in_array($key,old('permissions',[])))>
<span>{{ $label }}</span></label>
@endforeach
</fieldset>
<p class="text-xs text-slate-500">Permissions are checked server-side. No cross-tenant escalation, payroll modification or impersonated student punch is permitted.</p>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Save Delegation</button>
</form>
</div>
<div class="space-y-3">
<h3 class="font-extrabold">Active Company Delegates</h3>
@forelse($grants as $grant)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex flex-wrap items-center justify-between gap-2"><div><strong class="text-sm">{{ $grant->name }}</strong><p class="mt-1 text-xs text-slate-500">{{ $grant->email }}</p></div>
<span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-900">{{ $grant->access_role === 'company_admin' ? 'Company Admin' : 'Moderator' }}</span></div>
<p class="mt-3 text-xs text-slate-600">Authorized actions: {{ implode(', ',json_decode($grant->permissions,true)?:[]) ?: 'No extra actions' }}</p>
@if($grant->membership_id !== $actor->id)
<form method="POST" action="{{ route('erp.workspace_access.revoke',['firmSlug'=>$firmSlug,'id'=>$grant->id]) }}" class="mt-3 flex flex-wrap items-center gap-2" data-corporate-confirm="Revoke delegated privileges? Future requests will be denied and this action will be audited.">@csrf
<input name="reason" required minlength="10" maxlength="500" placeholder="Reason (required)" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button class="rounded-lg bg-rose-50 px-3 py-2 text-xs font-bold text-rose-900">Revoke</button>
</form>
@endif
</article>
@empty
<p class="rounded-xl bg-slate-100 p-5 text-sm text-slate-600">No delegated access. Partners retain their existing workspace ownership.</p>
@endforelse
</div>
</section>
@endsection
