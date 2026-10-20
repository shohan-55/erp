@extends('layouts.erp')
@section('title','Company Exit & Verified Export')
@section('content')
<section>
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">CONFIDENTIAL · ALL-PARTNER CONSENT · NO PHYSICAL PURGE</p>
<h2 class="mt-2 text-2xl font-extrabold">Company Data Export &amp; Safe Exit</h2>
<p class="mt-2 text-sm text-slate-600">This is a bounded encrypted offboarding package pilot. Every Partner must explicitly approve before company-wide data may be packaged or downloaded. Saved exports remain private. A checked package is not a certified independent restore. No permanent deletion is available here.</p>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Preflight Company Data Inventory</h3>
<p class="mt-1 text-xs text-slate-600">Read-only, current company-scoped row counts and known private-file references. Inventory is metadata; source contents are not displayed.</p>
<div class="mt-4 grid gap-3 md:grid-cols-3">
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Tenant tables</p><p class="text-xl font-extrabold">{{ count($inventory['tables']) }}</p></div>
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Tenant DB rows</p><p class="text-xl font-extrabold">{{ number_format(array_sum($inventory['tables'])) }}</p></div>
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Known private file references</p><p class="text-xl font-extrabold">{{ number_format(array_sum($inventory['file_references'])) }}</p></div>
</div>
<details class="mt-3 text-xs"><summary class="cursor-pointer font-bold">Show table and file-reference counts</summary>
<div class="mt-3 grid gap-3 md:grid-cols-2">
<div class="space-y-1">@foreach($inventory['tables'] as $name=>$count)<div class="flex justify-between gap-2 border-b pb-1"><span>{{ $name }}</span><strong>{{ number_format($count) }}</strong></div>@endforeach</div>
<div class="space-y-1">@foreach($inventory['file_references'] as $name=>$count)<div class="flex justify-between gap-2 border-b pb-1"><span>{{ $name }}</span><strong>{{ number_format($count) }}</strong></div>@endforeach</div>
</div></details>
</section>
@if(!$current || in_array($current->state,['blocked','rejected'],true))
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Request a Company Exit Export</h3>
<p class="mt-1 text-xs text-slate-600">An approved request is executed by the server's Laravel scheduler, not synchronously in this browser. The standard pilot capacity is {{ number_format(config('erp_exit.max_plaintext_bytes')/1048576,0) }} MiB of plaintext data, including private file content. A larger tenant will be blocked, never silently truncated.</p>
<form method="POST" action="{{ route('erp.exit.request',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf
<label class="text-xs font-bold">Exact Company Slug<input name="confirmation_slug" required value="{{ old('confirmation_slug') }}" placeholder="{{ $company->slug }}" maxlength="70" class="mt-1 block w-full rounded-lg border p-3"></label>
<label class="text-xs font-bold">Retention Not Before (minimum {{ config('erp_exit.minimum_retention_days') }} days)<input name="retention_until" type="date" required min="{{ now($company->timezone)->addDays(config('erp_exit.minimum_retention_days'))->toDateString() }}" value="{{ old('retention_until') }}" class="mt-1 block w-full rounded-lg border p-3"></label>
<label class="text-xs font-bold md:col-span-2">Reason for Offboarding Export<textarea name="reason" minlength="20" maxlength="1000" required rows="2" class="mt-1 block w-full rounded-lg border p-3">{{ old('reason') }}</textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Request Unanimous Partner Consent</button>
</form></section>
@endif
@if($current)
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex flex-wrap items-start justify-between gap-2"><div><h3 class="font-extrabold">Exit Case #{{ $current->id }} · {{ strtoupper(str_replace('_',' ',$current->state)) }}</h3>
<p class="mt-1 text-xs text-slate-500">Retention until: {{ $current->retention_until }} · Requested {{ $current->created_at }} UTC</p></div>
<a class="rounded-lg border px-3 py-2 text-xs font-bold" href="{{ route('erp.exit.preflight',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}">View Read-only Purge Preflight JSON</a></div>
@if($current->block_reason)<p class="mt-3 text-xs font-bold text-rose-800">{{ $current->block_reason }}</p>@endif
<p class="mt-3 text-sm text-slate-600">{{ $current->reason }}</p>
<div class="mt-4 space-y-2">
@foreach($votes as $vote)
<div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3">
<div class="text-sm font-bold">{{ $vote->name }} <span class="text-xs font-normal text-slate-500">· Workspace #{{ $vote->workspace_id }}</span></div>
<div class="flex items-center gap-3"><span class="text-xs font-bold">{{ ucfirst($vote->decision) }}</span>
@if($current->state==='awaiting_consent' && (int)$vote->membership_id===(int)$actor->id && $vote->decision==='pending')
<form method="POST" action="{{ route('erp.exit.vote',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}" class="flex flex-wrap items-center gap-2">@csrf
<input name="reason" required minlength="15" maxlength="1000" placeholder="Reason for decision" class="min-w-40 rounded-lg border p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject</button>
</form>
@endif</div></div>
@endforeach
</div>
@if($current->manifest_sha256)
<p class="mt-4 break-all rounded-lg bg-slate-50 p-3 font-mono text-xs">Manifest SHA-256: {{ $current->manifest_sha256 }}</p>
@endif
@if($current->state==='prepared')
<form method="POST" class="mt-3" action="{{ route('erp.exit.verify',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}">@csrf
<button class="rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold">Verify Encrypted Artifact Checksums</button></form>
@endif
@if($current->state==='integrity_verified' && (int)$current->requested_by===(int)$actor->id)
<form method="POST" action="{{ route('erp.exit.acknowledge',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}" class="mt-4 space-y-2">@csrf
<p class="text-xs font-semibold">Customer receipt — use the displayed manifest hash only after actually receiving your complete package. This is not a restore certificate.</p>
<input name="manifest_sha256" placeholder="Manifest SHA-256" required minlength="64" maxlength="64" class="block w-full rounded-lg border p-2 text-xs">
<input name="confirmation" placeholder="Type RECEIVED" required pattern="RECEIVED" class="block w-full rounded-lg border p-2 text-xs">
<button class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-xs font-bold">Confirm Actual Package Receipt</button>
</form>
@endif
@if($current->state==='acknowledged')
<form method="POST" action="{{ route('erp.exit.attest',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}" class="mt-4 space-y-2">@csrf
<p class="text-xs font-semibold">Independent Partner only: record a real separate-environment staging restore, reconciliation and evidence. You must be a different user account from the requester.</p>
<input name="manifest_sha256" placeholder="Manifest SHA-256" required maxlength="64" class="block w-full rounded-lg border p-2 text-xs">
<textarea name="restore_evidence" required minlength="30" maxlength="1000" placeholder="Describe the staging restore run, record counts and verification evidence" class="block w-full rounded-lg border p-2 text-xs"></textarea>
<input name="confirmation" required placeholder="Type INDEPENDENT_RESTORE_TESTED" class="block w-full rounded-lg border p-2 text-xs">
<button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Record Independent Restore Attestation</button>
</form>
@endif
</section>
@if($artifacts->isNotEmpty())
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Encrypted Private Export Artifacts</h3>
<p class="mt-1 text-xs text-slate-600">Downloads are decrypted after each access check. Save the manifest and every relevant table/file part in numeric order. Artifacts are paginated in sets of 100; download ALL pages and independently restore each numbered part before attesting completion.</p>
<div class="mt-3 space-y-2">
@foreach($artifacts as $part)
<div class="flex flex-wrap items-center justify-between gap-2 border-b pb-2"><div class="text-xs"><strong>#{{ $part->id }} · {{ $part->kind }} · {{ $part->source_name }}</strong>
@if($part->source_id) #{{ $part->source_id }} @endif
 · Part {{ $part->part_number }} · {{ number_format($part->byte_size) }} bytes
</div><a href="{{ route('erp.exit.download',['firmSlug'=>$firmSlug,'id'=>$current->id,'artifactId'=>$part->id]) }}" class="rounded-lg border px-3 py-2 text-xs font-bold">Private Download</a></div>
@endforeach</div>
<div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs">
<p>Page {{ $artifactPage }} · {{ number_format($artifactCount) }} encrypted artifacts</p>
<div class="flex gap-2">
@if($artifactPage>1)
<a class="rounded-lg border px-3 py-2 font-bold" href="{{ route('erp.exit.index',['firmSlug'=>$firmSlug,'export_page'=>$artifactPage-1]) }}">Previous 100</a>
@endif
@if($artifactPage*100<$artifactCount)
<a class="rounded-lg border px-3 py-2 font-bold" href="{{ route('erp.exit.index',['firmSlug'=>$firmSlug,'export_page'=>$artifactPage+1]) }}">Next 100</a>
@endif
</div></div>
</section>
@endif
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Retention &amp; Legal Holds</h3>
<p class="mt-1 text-xs text-slate-600">Any authorized Partner may place an auditable hold. A hold release does not authorize deletion, and archived Vault objects also have independent holds.</p>
<form method="POST" action="{{ route('erp.exit.hold',['firmSlug'=>$firmSlug,'id'=>$current->id]) }}" class="mt-4 flex flex-wrap gap-2">@csrf
<input name="reason" minlength="20" maxlength="1000" required placeholder="Reason for legal/contract hold" class="min-w-44 flex-1 rounded-lg border p-3 text-xs">
<button class="rounded-lg border border-amber-300 px-4 py-2 text-xs font-bold">Place Retention Hold</button></form>
@foreach($holds as $hold)
<div class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-50 p-3">
<p class="text-xs">Hold #{{ $hold->id }}: {{ $hold->reason }} · {{ $hold->released_at ? 'Released' : 'Active' }}</p>
@if(!$hold->released_at)
<form method="POST" action="{{ route('erp.exit.hold.release',['firmSlug'=>$firmSlug,'id'=>$current->id,'holdId'=>$hold->id]) }}" class="flex gap-2">@csrf
<input name="reason" required minlength="20" maxlength="1000" class="rounded-lg border p-2 text-xs" placeholder="Release justification">
<button class="rounded-lg border px-3 py-2 text-xs font-bold">Release Hold</button></form>
@endif
</div>
@endforeach
</section>
@endif
@endsection
