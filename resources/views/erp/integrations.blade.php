@extends('layouts.erp')
@section('title',__('integrations.title'))
@section('content')
<section>
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PHASE 20 · SAFE READ-ONLY INTEGRATIONS</p>
<h2 class="mt-2 text-2xl font-extrabold">{{ __('integrations.title') }}</h2>
<p class="mt-2 text-sm text-slate-600">{{ __('integrations.subtitle') }}</p>
</section>
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h3 class="font-extrabold">{{ __('integrations.language') }}</h3>
<form method="POST" action="{{ route('erp.integrations.locale',['firmSlug'=>$firmSlug]) }}" class="mt-3 flex flex-wrap items-end gap-3">@csrf
<label class="text-xs font-bold">Language / ভাষা
<select name="language" class="mt-1 block rounded-lg border p-3">
<option value="en" @selected(app()->getLocale()==='en')>English</option>
<option value="bn" @selected(app()->getLocale()==='bn')>বাংলা</option>
</select></label>
<button class="rounded-lg bg-[#FCDF02] px-4 py-3 text-xs font-bold">Save / সংরক্ষণ</button></form>
<p class="mt-2 text-xs text-slate-500">Phase 20 translates this integration module and provides a reusable locale foundation. Other ERP modules remain in their currently supported language.</p>
</section>
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Mobile / PWA Shortcut</h3>
<p class="mt-2 text-sm text-slate-600">{{ __('integrations.pwa_note') }}</p>
<p class="mt-3 text-xs font-semibold">Use your mobile browser menu → Add to Home Screen / Install app. HTTPS and browser support are required.</p>
</section>
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h3 class="font-extrabold">{{ __('integrations.tokens') }}</h3>
<p class="mt-2 text-sm text-slate-600">{{ __('integrations.api_note') }}</p>
@if(session('integration_token_once'))
<div class="mt-4 rounded-xl border border-amber-400 bg-amber-50 p-4" role="status">
<p class="text-sm font-extrabold text-amber-950">{{ __('integrations.one_time') }}</p>
<code class="mt-2 block break-all select-all rounded-lg bg-white p-3 font-mono text-xs">{{ session('integration_token_once') }}</code>
<p class="mt-2 text-xs text-amber-900">Never paste tokens into public pages, support messages or screenshots.</p>
</div>
@endif
<div class="mt-4 space-y-2">
@forelse($tokens as $token)
<div class="flex flex-wrap items-center justify-between gap-3 border-b py-3">
<div><p class="text-sm font-bold">{{ $token->name }} · #{{ $token->id }}</p>
<p class="mt-1 text-xs text-slate-500">{{ implode(', ',(array)json_decode($token->scopes,true)) }} · Expires {{ $token->expires_at }} UTC · {{ $token->revoked_at ? __('integrations.revoked') : (now()->gte($token->expires_at) ? 'Expired' : __('integrations.active')) }}</p></div>
@if(!$token->revoked_at)
<form method="POST" action="{{ route('erp.integrations.revoke',['firmSlug'=>$firmSlug,'id'=>$token->id]) }}" class="flex gap-2">@csrf
<input type="password" name="password" required autocomplete="current-password" placeholder="Confirm password" class="w-40 rounded-lg border p-2 text-xs">
<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-800">Revoke</button></form>
@endif
</div>
@empty
<p class="text-sm text-slate-500">No integrations have been authorized for this membership.</p>
@endforelse
</div>
</section>
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h3 class="font-extrabold">{{ __('integrations.create') }}</h3>
<p class="mt-1 text-xs text-slate-600">Generate a personal token. Your current password is required, and you must already have completed any mandatory MFA challenge.</p>
<form method="POST" action="{{ route('erp.integrations.create',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2">@csrf
<label class="text-xs font-bold">Integration name<input name="name" required minlength="4" maxlength="80" placeholder="My reporting connector" class="mt-1 block w-full rounded-lg border p-3"></label>
<label class="text-xs font-bold">Expires in days (1–30)<input name="days" type="number" min="1" max="30" required value="7" class="mt-1 block w-full rounded-lg border p-3"></label>
<label class="text-xs font-bold sm:col-span-2">Allowed scopes (check only what is necessary)</label>
@foreach(['attendance:read'=>'Attendance · own records','leave:read'=>'Leave · own records','notifications:read'=>'Notifications · own inbox'] as $scope=>$label)
<label class="flex gap-2 text-xs"><input type="checkbox" name="scopes[]" value="{{ $scope }}">{{ $label }}</label>
@endforeach
<label class="text-xs font-bold sm:col-span-2">Current account password<input name="password" type="password" required autocomplete="current-password" class="mt-1 block w-full rounded-lg border p-3"></label>
<button class="rounded-xl bg-[#FCDF02] p-3 text-sm font-extrabold sm:col-span-2">{{ __('integrations.create') }}</button>
</form>
</section>
<section class="rounded-2xl border bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Read-only API v1</h3>
<p class="mt-2 text-xs text-slate-600">Send your token in <code>Authorization: Bearer &lt;token&gt;</code>, never in a URL or browser address bar. Requests must use your own tenant slug and active membership. Rate limited, read-only, no credential or payroll access.</p>
<div class="mt-3 space-y-2 text-xs font-mono">
@foreach(['me','attendance','leaves','notifications'] as $endpoint)
<p class="break-all rounded-lg bg-slate-50 p-2">GET /api/v1/companies/{{ $company->slug }}/{{ $endpoint }}</p>
@endforeach
</div>
</section>
@endsection
