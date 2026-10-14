<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}"><meta name="robots" content="noindex,nofollow">
<title>Sign In · {{ $company->name }} ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<main class="grid min-h-screen lg:grid-cols-2">
<section class="hidden bg-[#17191F] px-12 py-14 text-white lg:flex lg:flex-col lg:justify-between">
<div class="flex items-center gap-3"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#FCDF02] p-2"><img src="{{ asset('images/rac_logo.svg') }}" alt="" class="h-full w-full object-contain"></div><span class="text-xl font-extrabold">ERP <span class="text-[#FCDF02]">SaaS</span></span></div>
<div><p class="text-xs font-bold uppercase tracking-[.2em] text-[#FCDF02]">PRACTICE MANAGEMENT</p><h1 class="mt-4 max-w-xl text-4xl font-extrabold leading-tight">Your practice.<br>One secure workspace.</h1><p class="mt-5 max-w-lg text-sm leading-relaxed text-slate-300">Attendance, leave, conveyance and monthly allowance operations with company and Partner separation.</p></div>
<p class="text-xs text-slate-400">Powered by DhakaWala · Authenticated Access</p></section>
<section class="flex items-center justify-center px-5 py-12 sm:p-12">
<div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm sm:p-10">
<div class="mb-7 flex items-center gap-3"><div class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#17191F] p-2"><img src="{{ asset('images/rac_logo.svg') }}" alt="" class="h-full w-full object-contain"></div><div><p class="text-sm font-extrabold">{{ $company->name }}</p><p class="text-[11px] text-slate-500">Authorized Firm Login</p></div></div>
<h2 class="text-2xl font-extrabold">Sign in to continue</h2><p class="mt-1.5 text-sm text-slate-500">Use your registered company email address and password.</p>
@if($errors->any())<div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800" role="alert">{{ $errors->first() }}</div>@endif
@if(session('status'))<div class="mt-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
<form action="{{ route('erp.login.attempt', ['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-7 space-y-5">@csrf
<label class="block text-xs font-bold text-slate-700">Email Address<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="254" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></label>
<label class="block text-xs font-bold text-slate-700">Password<input name="password" type="password" autocomplete="current-password" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></label>
<button type="submit" class="w-full rounded-xl bg-[#FCDF02] px-5 py-3.5 text-sm font-extrabold transition hover:bg-[#F3D604]">Sign In</button>
</form><p class="mt-6 text-xs leading-relaxed text-slate-500">No public registration. If you need access, contact your firm's authorized Partner or administrator.</p>
</div></section></main></body></html>