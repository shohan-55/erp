<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Platform Administration · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#17191F] p-5 font-sans">
<main class="w-full max-w-md rounded-2xl border border-white/10 bg-white p-7 shadow-2xl sm:p-10">
<div class="inline-flex rounded-xl bg-[#FCDF02] px-4 py-2 text-xs font-extrabold">DHAKAWALA ERP · PLATFORM</div>
<h1 class="mt-6 text-2xl font-extrabold tracking-tight text-[#17191F]">Platform Administration</h1><p class="mt-2 text-sm text-slate-500">Restricted to the platform operator. This login cannot grant tenant record permissions.</p>
@if($errors->any())<p role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</p>@endif
<form method="POST" action="{{ route('erp.platform.login.attempt') }}" class="mt-6 space-y-4">@csrf
<label class="block text-xs font-bold text-slate-700">Administrator Email<input type="email" name="email" required autocomplete="username" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Password<input type="password" name="password" required autocomplete="current-password" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button type="submit" class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Sign In to Platform</button>
</form>
<p class="mt-5 text-xs leading-relaxed text-slate-500">Platform access is provisioned through an explicitly authorized secure server command. Public sign-up is disabled.</p>
</main></body></html>