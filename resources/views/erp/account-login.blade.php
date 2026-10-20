<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>One Account · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#F6F7F9] p-5 font-sans text-[#17191F]">
<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
<div class="inline-flex rounded-xl bg-[#FCDF02] px-3 py-1.5 text-xs font-extrabold">DHAKAWALA ERP</div>
<h1 class="mt-5 text-2xl font-extrabold">Sign in to your ERP account</h1>
<p class="mt-2 text-sm text-slate-600">One verified account can access multiple organizations after each invitation is accepted. Your existing password and MFA stay the same.</p>
@if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('erp.account.login.attempt') }}" class="mt-5 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-700">Email<input type="email" name="email" autocomplete="username" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Current Password<input type="password" name="password" autocomplete="current-password" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Sign In</button></form>
<p class="mt-4 text-xs text-slate-500">After signing in, reopen an invitation from your email to accept a new company. This page never resets your password.</p>
</main></body></html>
