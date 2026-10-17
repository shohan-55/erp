<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset Password · {{ $company->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#F6F7F9] p-5 font-sans">
<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
<h1 class="text-2xl font-extrabold">Set your new password</h1><p class="mt-2 text-sm text-slate-500">Use a unique password with 12+ characters, mixed case and numbers.</p>
@if($errors->any())<p role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<form action="{{ route('erp.password.reset',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-6 space-y-4">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label class="block text-xs font-bold text-slate-700">Registered Email<input type="email" name="email" value="{{ old('email',$email) }}" required autocomplete="username" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">New Password<input type="password" name="password" required minlength="12" autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Confirm Password<input type="password" name="password_confirmation" required minlength="12" autocomplete="new-password" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Update Password</button></form>
</main></body></html>