<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Your Companies · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<header class="border-b border-slate-800 bg-[#17191F] p-5 text-white"><div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
<div><p class="text-lg font-extrabold">DhakaWala <span class="text-[#FCDF02]">ERP</span></p><p class="mt-1 text-xs text-slate-300">Account & Organization Switcher</p></div>
<form method="POST" action="{{ route('erp.account.logout') }}">@csrf<button class="rounded-xl border border-white/30 px-4 py-2 text-xs font-bold">Sign Out</button></form>
</div></header>
<main class="mx-auto max-w-5xl space-y-6 px-5 py-9">
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">SINGLE VERIFIED IDENTITY</p>
<h1 class="mt-2 text-3xl font-extrabold">Choose Your Company</h1>
<p class="mt-2 text-sm text-slate-600">You can only enter companies where this exact account has an active membership. Your permissions, workspace access, payroll and private records remain independent.</p></section>
@if(session('status'))<div role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('status') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
<section class="grid gap-4 sm:grid-cols-2" aria-label="Your authorized companies">
@forelse($companies as $companyId=>$options)
@php($first=$options->first())
<article class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<div class="mb-4 inline-flex h-11 w-11 items-center justify-center rounded-xl bg-[#FCDF02]"><i data-lucide="building-2"></i></div>
<h2 class="text-lg font-extrabold">{{ $first->company_name }}</h2>
<p class="mt-1 text-xs text-slate-500">{{ $first->company_slug }} · {{ $options->count() }} accessible workspace{{ $options->count()===1 ? '' : 's' }}</p>
<div class="mt-3 flex flex-wrap gap-2">@foreach($options as $option)<span class="rounded-lg bg-slate-100 px-2 py-1 text-[11px] font-medium">{{ $option->workspace_name }} · {{ ucfirst($option->role) }}</span>@endforeach</div>
<form class="mt-5" method="POST" action="{{ route('erp.account.companies.select') }}">@csrf
<input type="hidden" name="company_id" value="{{ $companyId }}">
<button class="w-full rounded-xl bg-[#17191F] px-4 py-3 text-sm font-extrabold text-white">Enter Company →</button>
</form></article>
@empty<div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600">No active company membership found. An authorized firm administrator must invite this account and you must accept its one-time email link.</div>
@endforelse
</section>
</main></body></html>
