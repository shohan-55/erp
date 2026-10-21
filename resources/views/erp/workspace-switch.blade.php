<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Select Partner Workspace · {{ $company->brand_name ?: $company->name }} · DhakaWala ERP</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="border-b border-slate-800 bg-[#17191F] px-5 py-5 text-white sm:px-10">
<div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3">
<div><p class="text-lg font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span></p><p class="text-xs text-slate-300">{{ $company->brand_name ?: $company->name }}</p></div>
<form action="{{ route('erp.logout',['firmSlug'=>$firmSlug]) }}" method="POST">@csrf<button class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold">Sign Out</button></form>
</div></header>
<main class="mx-auto max-w-5xl space-y-6 px-5 py-9">
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRIVATE PARTNER ACCESS</p>
<h1 class="mt-2 text-3xl font-extrabold">Choose Your Partner Workspace</h1>
<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600">Your firm uses one login address. Each Partner workspace manages separate managers, students, attendance, leave, conveyance, payroll and approvals. Choose only a workspace assigned to your own account.</p>
</section>
@if($errors->any())<div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900" role="alert">{{ $errors->first() }}</div>@endif
<section aria-label="Your authorized Partner workspaces" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
@foreach($options as $option)
<div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<div class="flex items-center justify-between gap-3"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FCDF02] text-slate-950"><i data-lucide="briefcase-business" class="h-5 w-5"></i></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">{{ ucfirst($option->role) }}</span></div>
<h2 class="mt-5 text-lg font-extrabold">{{ $option->workspace_name }}</h2>
<p class="mt-1 text-xs text-slate-500">Independent Practice Unit</p>
<form action="{{ route('erp.workspaces.select',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-auto pt-6">@csrf
<input type="hidden" name="membership_id" value="{{ $option->id }}">
<button type="submit" class="w-full rounded-xl bg-[#17191F] px-4 py-3.5 text-sm font-extrabold text-white hover:bg-slate-800">Enter Workspace →</button>
</form></div>
@endforeach
</section>
<p class="border-t border-slate-200 pt-5 text-xs text-slate-500">Only your current, active memberships appear above. Changing workspaces never combines private data or payroll.</p>
<a href="{{ rtrim(config('app.url'), '/') . '/account/companies' }}" class="inline-block rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-900">Switch Company →</a>
</main>
</body></html>
