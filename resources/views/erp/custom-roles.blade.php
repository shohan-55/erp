@extends('layouts.erp')
@section('title','Custom Role Studio')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">WORKSPACE SECURITY · PARTNER CONTROL</p>
<h2 class="text-2xl font-extrabold">Custom Role & Permission Studio</h2>
<p class="max-w-3xl text-sm text-slate-600">Create Manager, Moderator, HR or Accounts role labels in {{ $workspace->name }} and choose permissions that are actually enforced by the ERP today. Existing base Partner/Manager/Student roles remain unchanged. Only a Partner can edit this matrix.</p>
<p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">Security: roles do not enable platform administration, payroll authorization, private salary access, leave approvals or operations not yet supported by server-side permission checks. Other Partner workspaces remain private.</p>
</section>
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(310px,1fr)]">
<div class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Role Permission Matrix</h3>
<p class="mt-1 text-xs text-slate-500">Only features with active server-side authorization are listed.</p>
<div class="mt-4 overflow-x-auto"><table class="w-full text-left text-xs"><thead><tr class="border-b border-slate-200 text-slate-600"><th class="px-2 py-3">Operation</th>@foreach($roles as $role)<th class="px-2 py-3 text-center">{{ $role->name }}</th>@endforeach</tr></thead><tbody>
@foreach($permissions as $key=>$label)
<tr class="border-b border-slate-100"><td class="px-2 py-3"><strong>{{ $key }}</strong><p class="mt-1 text-slate-500">{{ $label }}</p></td>
@foreach($roles as $role)
<td class="px-2 py-3 text-center font-bold {{ in_array($key,json_decode($role->permissions,true)?:[],true) && $role->active ? 'text-emerald-700' : 'text-slate-400' }}">{{ in_array($key,json_decode($role->permissions,true)?:[],true) && $role->active ? '✓' : '—' }}</td>
@endforeach
</tr>@endforeach
</tbody></table></div>
</div>
<div class="space-y-3">
<h3 class="font-extrabold">Manage Existing Roles</h3>
@forelse($roles as $role)
<details class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<summary class="cursor-pointer text-sm font-extrabold">{{ $role->name }} <span class="text-xs font-semibold text-slate-500">· {{ $role->slug }} · {{ $role->active ? 'Active' : 'Disabled' }}</span></summary>
<form class="mt-4 space-y-3" action="{{ route('erp.custom_roles.update',['firmSlug'=>$firmSlug,'roleId'=>$role->id]) }}" method="POST">@csrf
<input type="hidden" name="slug" value="{{ $role->slug }}">
<label class="block text-xs font-bold">Role Name<input name="name" value="{{ $role->name }}" minlength="2" maxlength="100" required class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="block text-xs font-bold">Description<textarea name="description" maxlength="500" rows="2" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm">{{ $role->description }}</textarea></label>
<fieldset class="space-y-2 rounded-xl border border-slate-200 p-3"><legend class="text-xs font-extrabold">Allowed Operations</legend>
@foreach($permissions as $key=>$label)<label class="flex items-start gap-2 text-xs"><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key,json_decode($role->permissions,true)?:[],true))><span>{{ $label }}</span></label>@endforeach
</fieldset>
<label class="block text-xs font-bold">Change Reason<input name="reason" required minlength="10" maxlength="500" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm" placeholder="Reason recorded in audit"></label>
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Save Role Permissions</button>
</form>
<form action="{{ route('erp.custom_roles.status',['firmSlug'=>$firmSlug,'roleId'=>$role->id]) }}" method="POST" class="mt-3 flex flex-wrap gap-2">@csrf
<input type="hidden" name="active" value="{{ $role->active ? 0 : 1 }}">
<input name="reason" required minlength="10" maxlength="500" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-xs" placeholder="Reason for {{ $role->active ? 'disabling' : 'enabling' }}">
<button class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">{{ $role->active ? 'Disable Role' : 'Enable Role' }}</button>
</form>
</details>
@empty<p class="rounded-xl bg-white p-5 text-sm text-slate-600">No custom roles yet. Create one using the form.</p>@endforelse
</div></div>
<aside class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Create Custom Role</h3>
<form action="{{ route('erp.custom_roles.store',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-4 space-y-3">@csrf
<label class="block text-xs font-bold">Role Name<input name="name" required minlength="2" maxlength="100" placeholder="HR Manager" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="block text-xs font-bold">Identifier<input name="slug" required minlength="2" maxlength="64" pattern="[a-z][a-z0-9-]+" placeholder="hr-manager" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="block text-xs font-bold">Description<textarea name="description" maxlength="500" rows="2" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></textarea></label>
<fieldset class="space-y-3 rounded-xl border border-slate-200 p-3"><legend class="text-xs font-extrabold">Grant Explicit Permissions</legend>
@foreach($permissions as $key=>$label)<label class="flex items-start gap-3 text-xs"><input type="checkbox" name="permissions[]" value="{{ $key }}" class="mt-1"><span>{{ $label }}</span></label>@endforeach
</fieldset>
<label class="block text-xs font-bold">Reason<input name="reason" required minlength="10" maxlength="500" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm" placeholder="Why is this role required?"></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Create Custom Role</button>
</form></div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Assign Role to Manager</h3>
<p class="mt-2 text-xs text-slate-600">Assignments are workspace-specific. Managers retain their original identity and payroll ownership. Only one custom role per Manager in this initial edition.</p>
@forelse($members as $member)
<form action="{{ route('erp.custom_roles.assign',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-4 space-y-2 border-b border-slate-100 pb-3">@csrf
<input type="hidden" name="membership_id" value="{{ $member->id }}">
<p class="text-xs font-bold">{{ $member->name }}</p><p class="text-[11px] text-slate-500">{{ $member->email }}</p>
<select name="role_id" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs">
<option value="">No custom role (revoke)</option>
@foreach($roles as $role)@if($role->active)
<option value="{{ $role->id }}" @selected(($assignments[$member->id]->role_id ?? null)===$role->id)>{{ $role->name }}</option>
@endif @endforeach
</select>
<input name="reason" required minlength="10" maxlength="500" placeholder="Assignment change reason" class="w-full rounded-lg border border-slate-300 p-2.5 text-xs">
<button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Update Assignment</button>
</form>
@empty<p class="mt-3 text-xs text-slate-500">No active managers in this workspace.</p>@endforelse
</div>
</aside>
</section>
@endsection
