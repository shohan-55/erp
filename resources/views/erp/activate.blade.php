<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><title>Accept Company Invitation · {{ $company->name }}</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#F6F7F9] p-5 font-sans">
<main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
<div class="inline-flex rounded-xl bg-[#FCDF02] px-3 py-1.5 text-xs font-extrabold">PRIVATE INVITATION</div>
<h1 class="mt-5 text-2xl font-extrabold">Join {{ $company->name }}</h1><p class="mt-2 text-sm text-slate-500">Verify your invited email address and set a strong password to activate the workspace membership.</p>
@if($errors->any())<p role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-sm text-rose-900">{{ $errors->first() }}</p>@endif
<form action="{{ route('erp.activate.accept',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-6 space-y-4">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<label class="block text-xs font-bold text-slate-700">Invited Email<input type="email" name="email" value="{{ old('email',$email) }}" required autocomplete="username" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Set Password<input type="password" name="password" autocomplete="new-password" minlength="12" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-700">Confirm Password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<p class="text-xs text-slate-500">Use at least 12 characters, mixed case letters and numbers.</p>
<button type="submit" class="w-full rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold">Accept Invitation & Activate</button>
</form><a href="{{ route('erp.login',['firmSlug'=>$firmSlug]) }}" class="mt-5 inline-block text-xs font-bold text-amber-800">Back to Firm Login</a>
</main></body></html>