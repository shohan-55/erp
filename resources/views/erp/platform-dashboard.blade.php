<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>Platform Console · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-[#17191F] px-5 py-5 text-white sm:px-10"><div><p class="text-xl font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span></p><p class="mt-1 text-[11px] text-slate-400">Restricted Central Cloud Operations</p></div>
<form action="{{ route('erp.platform.logout') }}" method="POST">@csrf<button class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold">Sign Out</button></form></header>
<main class="mx-auto max-w-[1420px] space-y-6 px-4 py-7 sm:px-8">
@if(session('success'))<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">{{ $errors->first() }}</div>@endif
<section><p class="text-xs font-bold uppercase tracking-widest text-amber-800">PLATFORM OPERATIONS</p><h1 class="mt-1 text-3xl font-extrabold">Organization Management</h1><p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-500">Manage companies, staff access, Partner workspaces and verified client-owned domains. Use Manage Company & Accounts for actual protected actions. Only administrative metadata is shown. This console intentionally cannot read student attendance, salary, leave, supporting documents or private tenant records.</p></section>
<section class="grid grid-cols-2 gap-3 lg:grid-cols-3">
@foreach([
['label'=>'Companies','value'=>$stats['companies'],'icon'=>'building-2'],
['label'=>'Partner Workspaces','value'=>$stats['workspaces'],'icon'=>'network'],
['label'=>'Active Memberships','value'=>$stats['active_memberships'],'icon'=>'users'],
['label'=>'Pending Invitations','value'=>$stats['pending_invitations'],'icon'=>'mail'],
['label'=>'Active Custom Domains','value'=>$stats['active_domains'],'icon'=>'globe'],
['label'=>'Domains Awaiting Setup','value'=>$stats['domains_awaiting_setup'],'icon'=>'shield-alert'],
] as $metric)
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex justify-between gap-2"><p class="text-xs font-semibold text-slate-500">{{ $metric['label'] }}</p><i data-lucide="{{ $metric['icon'] }}" class="h-5 w-5 text-amber-700"></i></div><p class="mt-4 text-3xl font-extrabold">{{ $metric['value'] }}</p></div>
@endforeach
</section>
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,1fr)]">
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h2 class="text-sm font-extrabold">Company Directory</h2><p class="mt-1 text-xs text-slate-500">Company metadata only · no private records</p></div>
<div class="divide-y divide-slate-100">
@forelse($companies as $company)
<article class="space-y-4 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-sm font-extrabold">{{ $company->name }}</p><p class="mt-1 text-xs text-slate-500">Slug: {{ $company->slug }} · {{ $workspaces[$company->id] ?? 0 }} Partner workspaces · Custom domain: {{ isset($domains[$company->id]) ? $domains[$company->id]->hostname . ($domains[$company->id]->active ? ' (Active)' : ' (Setup)') : 'Not configured' }}</p></div><div class="flex items-center gap-2"><span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $company->archived_at ? 'bg-slate-200 text-slate-700' : ($company->active ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800') }}">{{ $company->archived_at ? 'Archived' : ($company->active ? 'Active' : 'Suspended') }}</span><a href="{{ route('erp.platform.companies.show',['companyId'=>$company->id]) }}" class="rounded-lg bg-[#17191F] px-4 py-2 text-xs font-extrabold text-white">Manage Company & Accounts →</a></div></div>
@if($company->active && !$company->archived_at)
<details class="rounded-xl border border-slate-200 bg-slate-50 p-4"><summary class="cursor-pointer text-xs font-bold text-slate-700">Invite Another Partner Workspace</summary>
<form action="{{ route('erp.platform.partners.create',['companyId'=>$company->id]) }}" method="POST" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Workspace Slug<input name="workspace_slug" placeholder="partner-02" required pattern="[a-z0-9-]+" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Workspace Name<input name="workspace_name" placeholder="Partner Unit 02" required class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Partner Name<input name="partner_name" required class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Partner Email<input type="email" name="partner_email" required class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-sm"></label>
<button class="rounded-lg bg-[#FCDF02] p-3 text-xs font-extrabold sm:col-span-2">Create Workspace & Email Invitation</button></form>
</details>
@endif
</article>
@empty<p class="p-7 text-sm text-slate-500">No company registered yet.</p>@endforelse
</div></section>
<aside class="space-y-5">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="text-sm font-extrabold">Create New Firm</h2><p class="mt-2 text-xs leading-relaxed text-slate-500">A new company gets one isolated Partner workspace with an inactive email invitation, not automatic tenant access.</p>
<form action="{{ route('erp.platform.companies.create') }}" method="POST" class="mt-5 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-700">Company Name<input name="company_name" required placeholder="Chartered Accountants Firm" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Company Slug<input name="company_slug" required placeholder="my-firm" pattern="[a-z0-9-]+" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">First Partner Full Name<input name="partner_name" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">First Partner Email<input type="email" name="partner_email" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Create Company & Invite Partner</button></form></section>
<section class="rounded-2xl bg-[#17191F] p-5 text-white"><div class="flex items-center gap-2"><i data-lucide="shield-check" class="h-5 w-5 text-[#FCDF02]"></i><h3 class="text-sm font-extrabold">Privacy-first Platform</h3></div><p class="mt-3 text-xs leading-relaxed text-slate-300">The platform administrator does not automatically join a company or gain access to private Partner records. Access requires explicit company membership and future approved support workflows.</p></section>
</aside></div></main></body></html>