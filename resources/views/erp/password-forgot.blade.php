<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Password Recovery · {{ $company->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#F6F7F9] p-5 font-sans">
<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
<a href="{{ route('erp.login',['firmSlug'=>$firmSlug]) }}" class="text-xs font-bold text-amber-800">← Back to Login</a>
<h1 class="mt-5 text-2xl font-extrabold">Recover your password</h1><p class="mt-2 text-sm text-slate-500">{{ $company->name }} · Account recovery</p>
@if(session('status'))<p class="mt-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<p role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<form action="{{ route('erp.password.email',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-6 space-y-4">@csrf<label class="block text-xs font-bold text-slate-700">Registered Email<input type="email" name="email" value="{{ old('email') }}" required maxlength="254" autocomplete="email" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Request Reset Link</button></form>
<p class="mt-5 text-xs text-slate-500">A working SMTP mail provider is required. The response will not disclose whether an account exists.</p>
</main></body></html>