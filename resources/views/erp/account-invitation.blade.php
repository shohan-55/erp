<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow"><meta name="referrer" content="no-referrer"><title>Accept Company Invitation · DhakaWala ERP</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="flex min-h-screen items-center justify-center bg-[#F6F7F9] p-5 font-sans text-[#17191F]">
<main class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-7 shadow-sm">
<span class="inline-flex rounded-lg bg-[#FCDF02] px-3 py-1.5 text-xs font-extrabold">VERIFY & ACCEPT INVITATION</span>
<h1 class="mt-5 text-2xl font-extrabold">Join {{ $invitation->company_name }}</h1>
<p class="mt-2 text-sm text-slate-600">Workspace: {{ $invitation->workspace_name }} · Role: {{ ucfirst($invitation->role) }}</p>
<p class="mt-3 text-sm text-slate-600">This invitation grants access only to the named workspace, after you confirm. It will not modify your password, MFA, or memberships in other companies.</p>
@if($errors->any())<div role="alert" class="mt-4 rounded-xl bg-rose-50 p-3 text-sm text-rose-900">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('erp.account.invitations.accept',['token'=>$token]) }}" class="mt-5 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-700">Confirm Existing Account Password
<input type="password" name="current_password" required autocomplete="current-password" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Accept Membership</button>
</form><a class="mt-5 inline-block text-xs font-bold text-amber-800" href="{{ route('erp.account.companies') }}">My Companies</a>
</main></body></html>
