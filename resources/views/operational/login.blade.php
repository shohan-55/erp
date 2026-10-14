<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $company->name }} — Secure Sign In</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#20232A]">
<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-5 py-10">
  <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm sm:p-9">
    <div class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-[#17191F] text-xl font-bold text-[#FCDF02]">R</div>
    <p class="text-xs font-bold uppercase tracking-[0.16em] text-amber-800">Live Workspace · Secure Sign In</p>
    <h1 class="mt-2 text-2xl font-extrabold">{{ $company->name }}</h1>
    <p class="mt-2 text-sm text-slate-500">Sign in with your assigned account. This is separate from the public design preview.</p>
    @if($errors->any())<div role="alert" class="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-800">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ route('operational.login.submit', ['companySlug'=>$company->slug]) }}" class="mt-7 space-y-5">
      @csrf
      <label class="block text-xs font-bold">Email<input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-amber-400"></label>
      <label class="block text-xs font-bold">Password<input type="password" name="password" autocomplete="current-password" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm outline-none focus:border-amber-400"></label>
      <label class="flex items-center gap-2 text-xs text-slate-600"><input type="checkbox" name="remember" value="1" class="accent-amber-600"> Remember this browser</label>
      <button type="submit" class="w-full rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold text-[#17191F] hover:bg-[#F3D604]">Sign In</button>
    </form>
    <p class="mt-6 text-xs leading-relaxed text-slate-500">Account access is issued by an authorized firm administrator. Public self-registration is disabled.</p>
  </div>
</main></body></html>