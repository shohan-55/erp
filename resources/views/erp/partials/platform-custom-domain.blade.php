<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm" aria-labelledby="custom-domain-heading">
<div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-[11px] font-extrabold uppercase tracking-wider text-amber-800">Client Branding & Access</p><h2 id="custom-domain-heading" class="mt-1 text-lg font-extrabold">Custom Domain Control</h2></div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $customDomain?->active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">{{ $customDomain?->active ? 'Active' : ($customDomain ? 'Setup Required' : 'Not Configured') }}</span></div>
<p class="mt-3 text-xs leading-relaxed text-slate-600">Give this client a branded ERP URL such as <strong>erp.clientcompany.com</strong>. The company's central <strong>/{{ $company->slug }}</strong> login remains available while active. A custom domain never grants access to another company's data.</p>
@if(! $company->archived_at && $company->active)
<form action="{{ route('erp.platform.domains.store', ['companyId' => $company->id]) }}" method="POST" class="mt-4 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-700">Client-owned subdomain<input name="hostname" value="{{ old('hostname', $customDomain?->hostname) }}" placeholder="erp.clientcompany.com" required maxlength="253" autocomplete="off" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#17191F] px-4 py-3 text-xs font-bold text-white" @disabled($customDomain?->active)>{{ $customDomain ? 'Replace Domain & Restart Verification' : 'Register Domain Request' }}</button>
@if($customDomain?->active)<p class="text-xs text-amber-900">Disable the active hostname before requesting a replacement.</p>@endif
</form>
@endif
@if($customDomain)
<div class="mt-5 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4">
<p class="break-all text-sm font-extrabold text-slate-900">{{ $customDomain->hostname }}</p>
<p class="text-xs text-slate-600">Publish both records in the client's authoritative DNS. Keep the CNAME DNS-only (not proxied) during verification.</p>
<div class="rounded-lg bg-white p-3 text-xs text-slate-700"><p class="font-bold">CNAME record</p><p class="mt-1 break-all font-mono">{{ $customDomain->hostname }} → {{ config('erp.custom_domain_cname', 'erp.dhakawala.com') }}</p></div>
<div class="rounded-lg bg-white p-3 text-xs text-slate-700"><p class="font-bold">TXT ownership challenge</p><p class="mt-1 break-all font-mono">_dhakawala-erp.{{ $customDomain->hostname }}</p><p class="mt-1 break-all font-mono">dhakawala-erp-verification={{ $customDomain->verification_token }}</p></div>
<div class="flex flex-wrap gap-2 text-[11px] font-semibold">
<span class="{{ $customDomain->verified_at ? 'text-emerald-800' : 'text-amber-900' }}">DNS: {{ $customDomain->verified_at ? 'Verified' : 'Pending' }}</span>
<span class="{{ $customDomain->tls_approved_at ? 'text-emerald-800' : 'text-amber-900' }}">· HTTPS & vhost: {{ $customDomain->tls_approved_at ? 'Operator approved' : 'Pending review' }}</span>
<span class="{{ $customDomain->active ? 'text-emerald-800' : 'text-amber-900' }}">· Routing: {{ $customDomain->active ? 'Enabled' : 'Disabled' }}</span></div>
@if(! $customDomain->verified_at && $company->active && ! $company->archived_at)
<form action="{{ route('erp.platform.domains.verify', ['companyId' => $company->id]) }}" method="POST">@csrf<button class="rounded-lg bg-[#FCDF02] px-4 py-2.5 text-xs font-bold">Check DNS Ownership & CNAME</button></form>
@endif
@if($customDomain->verified_at && ! $customDomain->tls_approved_at && $company->active && ! $company->archived_at)
<form action="{{ route('erp.platform.domains.tls', ['companyId' => $company->id]) }}" method="POST" class="space-y-3 rounded-lg border border-amber-300 bg-amber-50 p-3">@csrf
<p class="text-xs font-bold text-amber-900">Hosting operator confirmation — do NOT approve until the client hostname has a valid SSL certificate and points to this ERP's dedicated cPanel webroot.</p>
<label class="block text-xs font-semibold">Retype the exact hostname<input name="confirmation_hostname" required autocomplete="off" class="mt-1 w-full rounded-lg border p-2"></label>
<label class="flex gap-2 text-xs"><input type="checkbox" name="tls_ready" value="1" required> HTTPS certificate, virtual host, private webroot and direct browser test independently verified</label>
<button class="rounded-lg bg-amber-900 px-4 py-2.5 text-xs font-bold text-white">Record HTTPS / Vhost Approval</button></form>
@endif
@if($customDomain->verified_at && $customDomain->tls_approved_at && ! $customDomain->active && $company->active && ! $company->archived_at)
<form action="{{ route('erp.platform.domains.activate', ['companyId' => $company->id]) }}" method="POST" class="space-y-3">@csrf
<label class="block text-xs font-semibold">Confirm hostname to activate<input name="confirmation_hostname" required autocomplete="off" class="mt-1 w-full rounded-lg border p-2"></label>
<button class="rounded-lg bg-emerald-700 px-4 py-2.5 text-xs font-bold text-white">Activate Custom Domain</button></form>
@endif
@if($customDomain->active)
<a href="{{ 'https://'.$customDomain->hostname.'/login' }}" target="_blank" rel="noopener noreferrer" class="inline-block text-xs font-bold text-amber-800 underline">Open Client Login ↗</a>
<form action="{{ route('erp.platform.domains.disable', ['companyId' => $company->id]) }}" method="POST" class="mt-3 space-y-3">@csrf
<label class="block text-xs font-semibold">Confirm hostname to disable<input name="confirmation_hostname" required autocomplete="off" class="mt-1 w-full rounded-lg border border-rose-300 p-2"></label>
<button class="rounded-lg bg-rose-800 px-4 py-2.5 text-xs font-bold text-white">Disable Custom Domain</button></form>
@endif
</div>
@endif
<p class="mt-4 text-[11px] leading-relaxed text-slate-500">Client DNS ownership does not issue a TLS certificate or add a domain to WHM. Activation requires separate hosting-provider setup and operator approval. No automatic customer billing is enabled.</p>
</section>
