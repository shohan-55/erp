<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{{ $company->name }} · Platform Control · DhakaWala ERP</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800 bg-[#17191F] px-5 py-5 text-white sm:px-10">
<div><p class="text-xl font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span></p><p class="mt-1 text-[11px] text-slate-400">Platform Control Center · Company Administration</p></div>
<div class="flex gap-3"><a class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold" href="{{ route('erp.platform.dashboard') }}">← All Companies</a>
<form action="{{ route('erp.platform.logout') }}" method="POST">@csrf<button class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold">Sign Out</button></form></div>
</header>
<main class="mx-auto max-w-[1450px] space-y-6 px-4 py-7 sm:px-8">
@if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
<section class="flex flex-wrap items-start justify-between gap-3">
<div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">COMPANY & ACCOUNT MANAGEMENT</p>
<h1 class="mt-2 text-3xl font-extrabold">{{ $company->name }}</h1>
<p class="mt-2 text-sm text-slate-500">Company #{{ $company->id }} · Login: <a class="font-bold text-amber-800 underline" href="{{ url('/'.$company->slug) }}" target="_blank" rel="noopener noreferrer">/{{ $company->slug }}</a> · CA Firm · {{ $workspaces->count() }} isolated Partner workspaces</p></div>
<span class="rounded-full px-4 py-2 text-xs font-bold {{ $company->archived_at ? 'bg-slate-200 text-slate-700' : ($company->active ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800') }}">
{{ $company->archived_at ? 'Archived' : ($company->active ? 'Active' : 'Suspended') }}</span>
</section>

<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(320px,0.85fr)]">
<section class="space-y-5">
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex items-center justify-between gap-3"><h2 class="text-base font-extrabold">Account Directory</h2><span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">{{ $members->count() }} memberships</span></div>
<p class="mt-2 text-xs text-slate-500">Identity and access metadata only. Private attendance, payroll and client documents remain inaccessible to the Platform Admin.</p>
<div class="mt-4 divide-y divide-slate-100">
@forelse($members as $member)
<article class="space-y-3 py-5">
<div class="flex flex-wrap items-start justify-between gap-3">
<div><h3 class="text-sm font-extrabold">{{ $member->name }}</h3><p class="mt-1 break-all text-xs text-slate-500">{{ $member->email }}</p>
<p class="mt-1 text-[11px] text-slate-500">{{ $member->workspace_name }} · {{ ucfirst($member->role) }} · Account #{{ $member->id }}</p>
@if($member->is_platform_admin)<p class="mt-1 text-[11px] font-semibold text-amber-800">Also a Platform Admin — this privilege is managed separately</p>@endif
</div>
<span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $member->platform_suspended_at ? 'bg-rose-50 text-rose-800' : ($member->active ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800') }}">
{{ $member->platform_suspended_at ? 'Suspended' : ($member->active ? 'Active' : 'Invitation Pending') }}
</span>
</div>
@if($company->active && !$company->archived_at)
<details class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
<summary class="cursor-pointer text-xs font-bold text-slate-800">Edit Account Details</summary>
<form class="mt-4 grid gap-3" action="{{ route('erp.platform.members.update',['companyId'=>$company->id,'memberId'=>$member->id]) }}" method="POST">@csrf @method('PATCH')
<label class="text-xs font-bold text-slate-600">Display Name<input name="name" value="{{ $member->name }}" required minlength="3" maxlength="255" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm"></label>
<label class="text-xs font-bold text-slate-600">Role<select name="role" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm">
@if($member->role==='partner')<option value="partner">Partner (ownership protected)</option>
@else<option value="manager" @selected($member->role==='manager')>Manager</option><option value="student" @selected($member->role==='student')>Student</option>@endif
</select></label>
@if($member->role!=='partner')
<label class="text-xs font-bold text-slate-600">Student's Manager (when role is Student)
<select name="manager_membership_id" class="mt-1 w-full rounded-lg border border-slate-300 bg-white p-2.5 text-sm">
<option value="">None</option>
@foreach($members as $potentialManager)
@if($potentialManager->role==='manager' && $potentialManager->active && $potentialManager->workspace_id===$member->workspace_id)
<option value="{{ $potentialManager->id }}" @selected($member->manager_membership_id==$potentialManager->id)>{{ $potentialManager->name }}</option>
@endif
@endforeach
</select></label>
@else<input type="hidden" name="manager_membership_id" value="">@endif
<p class="text-[11px] text-slate-500">Email address and password are not editable here. A name shared with another company cannot be changed here.</p>
<button class="rounded-lg bg-[#17191F] p-3 text-xs font-bold text-white">Save Account Details</button>
</form>
</details>
@if($member->active || $member->platform_suspended_at)
<details class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
<summary class="cursor-pointer text-xs font-bold text-amber-900">{{ $member->active ? 'Suspend Access' : 'Reactivate Access' }}</summary>
<form action="{{ route('erp.platform.members.lifecycle',['companyId'=>$company->id,'memberId'=>$member->id]) }}" method="POST" class="mt-3 space-y-3">@csrf
<input type="hidden" name="action" value="{{ $member->active ? 'suspend' : 'reactivate' }}">
<p class="text-xs text-amber-900">{{ $member->active ? 'Suspension immediately blocks this company membership. Last active Partner cannot be suspended.' : 'Only access previously suspended by Platform Admin can be restored.' }} Type the exact email to confirm.</p>
<input type="email" name="confirmation_email" required placeholder="{{ $member->email }}" autocomplete="off" class="w-full rounded-lg border border-amber-300 p-2.5 text-sm">
<button class="rounded-lg bg-amber-900 px-4 py-2 text-xs font-bold text-white">{{ $member->active ? 'Confirm Suspension' : 'Confirm Reactivation' }}</button>
</form></details>
@endif
@endif
</article>
@empty<p class="py-8 text-sm text-slate-500">No accounts for this company.</p>@endforelse
</div>
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h2 class="text-sm font-extrabold">Recent Platform Activity</h2><p class="mt-1 text-xs text-slate-500">Administrative actions are recorded separately from private tenant operations.</p>
<div class="mt-4 divide-y divide-slate-100">
@forelse($events as $event)<div class="flex flex-wrap justify-between gap-2 py-3 text-xs"><span class="font-semibold">{{ str_replace('.', ' · ', $event->action) }} · {{ $event->subject_type }} #{{ $event->subject_id }}</span><span class="text-slate-500">{{ $event->created_at }}</span></div>
@empty<p class="py-3 text-xs text-slate-500">No platform changes recorded yet.</p>@endforelse
</div></div>
</section>

<aside class="space-y-5">
<a href="{{ route('erp.platform.billing.company',['companyId'=>$company->id]) }}" class="block rounded-2xl bg-[#FCDF02] p-5 text-sm font-extrabold text-slate-900">SaaS Billing · Invoices & Payments →</a>
@include('erp.partials.platform-custom-domain')
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h2 class="text-sm font-extrabold">Edit Company</h2><p class="mt-1 text-xs text-slate-500">Company name only. The login slug is intentionally permanent to protect links and integrations.</p>
@if(!$company->archived_at)
<form class="mt-4 space-y-3" action="{{ route('erp.platform.companies.update',['companyId'=>$company->id]) }}" method="POST">@csrf @method('PATCH')
<label class="block text-xs font-bold text-slate-600">Registered Firm Name<input name="company_name" value="{{ $company->name }}" maxlength="255" minlength="3" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Client Branding Name (optional)<input name="brand_name" value="{{ $company->brand_name }}" maxlength="100" placeholder="{{ $company->name }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Brand Accent Color<input name="brand_accent_color" type="color" value="{{ $company->brand_accent_color }}" class="mt-2 h-11 w-full rounded-lg border border-slate-300 bg-white p-1"></label>
<label class="block text-xs font-bold text-slate-600">Time Zone<select name="timezone" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
@foreach(['Asia/Dhaka','UTC'] as $zone)
<option value="{{ $zone }}" @selected($company->timezone===$zone)>{{ $zone }}</option>
@endforeach</select></label>
<label class="block text-xs font-bold text-slate-600">Minimum Active Partners for Firm-wide Proposal<input type="number" name="min_active_partners_for_company_policy" value="{{ $company->min_active_partners_for_company_policy }}" min="1" max="200" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<p class="text-xs text-slate-500">Only CA Firm type is available initially. Partner workspaces remain independent; this setting affects firm-wide proposals, never a Partner's own attendance or payroll policy.</p>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Save Firm Profile</button></form>
@else<p class="mt-3 text-xs font-semibold text-rose-800">Restore the company before editing.</p>@endif
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h2 class="text-sm font-extrabold">Company Lifecycle</h2>
<p class="mt-2 text-xs leading-relaxed text-slate-500">Suspend first, then archive. No payroll, attendance or other records are permanently deleted. Restored archives remain suspended until reactivated.</p>
@if($company->archived_at)
@php($action='restore')
@elseif($company->active)
@php($action='suspend')
@else
@php($action='reactivate')
@endif
<form class="mt-4 space-y-3" action="{{ route('erp.platform.companies.lifecycle',['companyId'=>$company->id]) }}" method="POST">@csrf
<input type="hidden" name="action" value="{{ $action }}">
<label class="block text-xs font-bold text-slate-600">Type company slug to {{ $action }}<input name="confirmation_slug" required autocomplete="off" placeholder="{{ $company->slug }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl p-3 text-xs font-extrabold {{ $action==='reactivate' || $action==='restore' ? 'bg-[#FCDF02] text-slate-900' : 'bg-rose-800 text-white' }}">{{ ucfirst($action) }} Company</button></form>
@if(!$company->active && !$company->archived_at)
<form class="mt-3 space-y-2 rounded-xl border border-rose-200 p-3" action="{{ route('erp.platform.companies.lifecycle',['companyId'=>$company->id]) }}" method="POST">@csrf
<input type="hidden" name="action" value="archive">
<label class="block text-xs font-semibold text-rose-900">Archive suspended company · confirm slug<input name="confirmation_slug" placeholder="{{ $company->slug }}" required autocomplete="off" class="mt-2 w-full rounded-lg border border-rose-300 p-2.5 text-sm"></label>
<button class="w-full rounded-lg bg-rose-900 p-2.5 text-xs font-bold text-white">Archive (Retain Records)</button></form>
@endif
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h2 class="text-sm font-extrabold">Invite Staff Account</h2><p class="mt-2 text-xs leading-relaxed text-slate-500">New Manager/Student accounts start inactive and require email acceptance. Existing ERP user credentials are never reset to add a membership.</p>
@if($company->active && !$company->archived_at)
<form action="{{ route('erp.platform.members.invite',['companyId'=>$company->id]) }}" method="POST" class="mt-4 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-600">Partner Workspace<select name="workspace_id" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
@foreach($workspaces as $workspace)@if($workspace->active)<option value="{{ $workspace->id }}">{{ $workspace->name }}</option>@endif @endforeach
</select></label>
<label class="block text-xs font-bold text-slate-600">Full Name<input name="name" required minlength="3" maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Email Address<input type="email" name="email" required maxlength="254" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Role<select name="role" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="student">Student</option><option value="manager">Manager</option></select></label>
<label class="block text-xs font-bold text-slate-600">Student's Manager (optional, same workspace)<select name="manager_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">Unassigned</option>
@foreach($workspaces as $workspace)
<optgroup label="{{ $workspace->name }}">
@foreach($members as $member)
@if($member->role==='manager' && $member->active && $member->workspace_id===$workspace->id)
<option value="{{ $member->id }}">{{ $member->name }}</option>
@endif
@endforeach
</optgroup>
@endforeach
</select></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Send Secure Invitation</button></form>
@else<p class="mt-3 text-xs font-semibold text-rose-800">Company is not active. Invitations are disabled.</p>@endif
</div>
<div class="rounded-2xl bg-[#17191F] p-5 text-white"><h2 class="text-sm font-extrabold">Privacy-first Management</h2><p class="mt-3 text-xs leading-relaxed text-slate-300">This console manages organization and membership metadata only. Platform operators do not inherit Partner roles or access to tenant attendance, leave, conveyance, salary or supporting documents.</p><p class="mt-3 text-xs leading-relaxed text-amber-200">SaaS billing is administered through the separate Platform Billing console. Subscription expiry does not automatically alter tenant access or delete records.</p></div>
</aside>
</div>
</main>
</body></html>
