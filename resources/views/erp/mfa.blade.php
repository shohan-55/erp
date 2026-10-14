<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Account Verification · DhakaWala ERP</title>
@vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<main class="mx-auto flex min-h-screen max-w-xl items-center px-4 py-10">
  <section class="w-full rounded-2xl border border-slate-200 bg-white p-6 shadow-md sm:p-10">
    <div class="mb-5 flex items-center gap-3">
      <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-[#FCDF02] text-lg font-black">DW</span>
      <div><p class="text-base font-extrabold">DhakaWala ERP</p><p class="text-xs text-slate-500">Two-step account protection</p></div>
    </div>
    @if($errors->any())
       <div role="alert" class="mb-5 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-900">{{ $errors->first() }}</div>
    @endif

    @if($mode === 'setup')
      <h1 class="text-2xl font-extrabold">Set up your authenticator</h1>
      <p class="mt-2 text-sm leading-relaxed text-slate-600">Use an authenticator app to add a time-based account. Enter this secret manually, then confirm with your account password and a six-digit code. This is mandatory for privileged accounts.</p>
      <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 p-4">
        <p class="text-xs font-bold uppercase tracking-wide text-amber-900">Your one-time setup secret — keep private</p>
        <p class="mt-2 select-all break-all font-mono text-base font-bold tracking-widest text-slate-900">{{ $secret }}</p>
        <p class="mt-2 text-xs text-slate-600">Issuer: DhakaWala ERP · Type: Time-based · 6 digits · 30 seconds</p>
      </div>
      <form method="POST" action="{{ route('erp.mfa.confirm') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-bold">Account Password<input required type="password" name="password" autocomplete="current-password" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100"></label>
        <label class="block text-sm font-bold">Six-digit Authenticator Code<input required type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" minlength="6" maxlength="6" autocomplete="one-time-code" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 font-mono text-lg tracking-widest focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100"></label>
        <button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F5AD16]">Enable two-step verification</button>
      </form>
    @elseif($mode === 'challenge')
      <h1 class="text-2xl font-extrabold">Verify it’s you</h1>
      <p class="mt-2 text-sm text-slate-600">Enter the current six-digit code from your authenticator app, or one of your unused recovery codes.</p>
      <form method="POST" action="{{ route('erp.mfa.verify') }}" class="mt-6 space-y-4">
        @csrf
        <label class="block text-sm font-bold">Authenticator or Recovery Code<input required type="text" name="code" autocomplete="one-time-code" maxlength="48" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 font-mono tracking-wider focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-100"></label>
        <button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F5AD16]">Verify and continue</button>
      </form>
      <p class="mt-4 text-xs text-slate-500">Each authenticator time step can be used only once. Recovery codes are single-use.</p>
    @elseif($mode === 'recovery')
      <h1 class="text-2xl font-extrabold">Save your recovery codes</h1>
      <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950">
      These codes are displayed only during setup. Save them offline before continuing. Anyone holding one may bypass your authenticator after knowing your password.</div>
      <div class="mt-5 grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-4">
        @foreach($codes as $code)
          <code class="rounded-lg bg-white px-2 py-2 text-center font-mono text-xs font-bold tracking-wider text-slate-900">{{ $code }}</code>
        @endforeach
      </div>
      <form method="GET" action="{{ route('erp.mfa.challenge') }}" class="mt-6">
        <button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F5AD16]">I saved my recovery codes</button>
      </form>
    @endif
    <div class="mt-7 border-t border-slate-100 pt-5 text-xs text-slate-500">Account verification is required before accessing private firm and payroll data.</div>
  </section>
</main>
</body></html>
