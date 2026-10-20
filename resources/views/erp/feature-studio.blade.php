@extends('layouts.erp')
@section('title','Feature Settings Studio')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">MODULE CONFIGURATION · WORKSPACE OWNERSHIP</p>
<h2 class="text-2xl font-extrabold">Feature Settings Studio</h2>
<p class="max-w-3xl text-sm text-slate-600">Decide which ERP modules are available without modifying code. Changes take effect on the next request, including direct URLs, downloads and form submissions. Existing student Partner/Manager restrictions still apply.</p>
<p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-950">Company → Workspace → Role → Individual: disabling a feature at any level blocks it below. Enabling it at a lower level <strong>cannot</strong> bypass a restriction above. Account security, firm governance, billing, backups and this Settings Studio cannot be disabled through these switches.</p>
@if(!$allowedCompany)
<p class="rounded-xl border border-sky-200 bg-sky-50 p-3 text-xs text-sky-900"><strong>Multi-Partner Governance:</strong> Company-wide changes require the firm's approval workflow (Phase 08). You can already configure your own isolated Partner Workspace, Roles and Users.</p>
@endif
</section>
@if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-950">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-950">{{ $errors->first() }}</p>@endif
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.3fr)_minmax(300px,1fr)]">
<div class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Modules & Your Current Effective Access</h3>
<p class="mt-1 text-xs text-slate-500">This column shows what your own Partner account can access. Another Member can have more restrictive settings.</p>
<div class="mt-4 divide-y divide-slate-100">
@foreach($modules as $key=>$label)
<div class="flex items-start justify-between gap-4 py-3">
<div class="min-w-0"><p class="text-sm font-bold text-slate-900">{{ $label }}</p><p class="mt-1 text-xs text-slate-500">{{ $key }}</p></div>
<span class="shrink-0 rounded-full px-3 py-1 text-xs font-bold {{ $effective[$key] ? 'bg-emerald-50 text-emerald-900' : 'bg-rose-50 text-rose-900' }}">{{ $effective[$key] ? 'Available' : 'Disabled' }}</span>
</div>
@endforeach</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Configured Overrides</h3>
<p class="mt-1 text-xs text-slate-500">Absence of an override means the existing default is enabled, subject to higher-level rules. Student controls remain separate.</p>
<div class="mt-4 overflow-x-auto"><table class="w-full text-left text-xs">
<thead><tr class="border-b border-slate-200 text-slate-600"><th class="px-2 py-3">Module</th><th class="px-2 py-3">Scope</th><th class="px-2 py-3">Setting</th></tr></thead>
<tbody>@forelse($controls as $item)
<tr class="border-b border-slate-100"><td class="px-2 py-3 font-semibold">{{ $modules[$item->feature_key] ?? $item->feature_key }}</td>
<td class="px-2 py-3 break-all">{{ $item->scope_key }}</td>
<td class="px-2 py-3 font-bold {{ $item->enabled ? 'text-emerald-800' : 'text-rose-800' }}">{{ $item->enabled ? 'Allow here' : 'Block here' }}</td></tr>
@empty<tr><td colspan="3" class="px-2 py-5 text-slate-500">No overrides saved. All modules retain their existing default configuration.</td></tr>
@endforelse</tbody></table></div>
</div>
</div>
<aside class="space-y-4">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Configure Feature</h3>
<p class="mt-1 text-xs text-slate-600">Only the Partner of this workspace can save these settings. Reasons are retained in the tenant audit trail.</p>
<form method="POST" action="{{ route('erp.feature_studio.update',['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-3" x-data="{ scope:'workspace' }">@csrf
<label class="block text-xs font-bold text-slate-700">Module
<select name="feature_key" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
@foreach($modules as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
</select></label>
<label class="block text-xs font-bold text-slate-700">Setting Scope
<select name="scope" x-model="scope" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
@if($allowedCompany)<option value="company">Whole Company (Single Partner)</option>@endif
<option value="workspace">Current Partner Workspace</option>
<option value="role">Base Role in this Workspace</option>
<option value="user">One Member in this Workspace</option>
</select></label>
<div x-show="scope==='role'" x-cloak>
<label class="block text-xs font-bold text-slate-700">Member Role
<select name="role" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select Role</option>
@foreach(\App\Support\ErpFeatures::BASE_ROLES as $role)<option value="{{ $role }}">{{ ucfirst($role) }}</option>@endforeach
</select></label></div>
<div x-show="scope==='user'" x-cloak>
<label class="block text-xs font-bold text-slate-700">User / Workspace Membership
<select name="membership_id" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="">Select Member</option>
@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ ucfirst($member->role) }} · #{{ $member->id }}</option>@endforeach
</select></label></div>
<label class="block text-xs font-bold text-slate-700">Action
<select name="operation" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
<option value="disable">Disable (deny access)</option>
<option value="enable">Enable at this scope (cannot override parent)</option>
<option value="reset">Reset to inherited default</option>
</select></label>
<label class="block text-xs font-bold text-slate-700">Reason (required)
<textarea name="reason" required minlength="12" maxlength="500" rows="3" placeholder="Document why the module's availability changed" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">{{ old('reason') }}</textarea></label>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Save Feature Settings</button>
</form>
</div>
<div class="rounded-2xl bg-[#17191F] p-5 text-white">
<h3 class="font-bold">Safe No-Code Settings</h3>
<p class="mt-2 text-xs leading-relaxed text-slate-300">Disabling a module blocks future requests. It does not delete existing leave, attendance, payroll, claims or historical records. Important access can be restored through this Studio. Attendance Reports remain independently configurable even if Check-in/out is disabled; disabling Engagements also blocks Timesheets.</p>
</div>
</aside>
</section>
@endsection
