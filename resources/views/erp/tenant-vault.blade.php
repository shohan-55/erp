@extends('layouts.erp')
@section('title','Private Object Vault')
@section('content')
<section>
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PHASE 17 · CONFIDENTIAL FILE STORAGE</p>
<h2 class="mt-2 text-2xl font-extrabold">Private Object Vault</h2>
<p class="mt-2 text-sm text-slate-600">This is a separate, quota-limited storage vault. Files are private and downloads always require your current ERP membership. Existing Document Center files have not been moved or removed. Archived files remain stored and counted.</p>
</section>
<section class="grid gap-3 md:grid-cols-3">
@php($limit=$quota?->limit_bytes ?? config('erp_vault.workspace_limit_bytes'))
@php($used=$quota?->used_bytes ?? 0)
@php($reserved=$quota?->reserved_bytes ?? 0)
@foreach([
['label'=>'Vault storage limit','value'=>number_format($limit/1048576,2).' MiB'],
['label'=>'Committed files','value'=>number_format($used/1048576,2).' MiB'],
['label'=>'Reserved uploads','value'=>number_format($reserved/1048576,2).' MiB'],
] as $metric)
<div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><p class="text-xs text-slate-500">{{ $metric['label'] }}</p><p class="mt-2 text-xl font-extrabold">{{ $metric['value'] }}</p></div>
@endforeach
</section>
@if($actor->role==='partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Upload a Verified Private Document</h3>
<p class="mt-1 text-xs text-slate-600">PDF, PNG or JPEG only · up to {{ number_format(config('erp_vault.max_file_bytes')/1048576,0) }} MiB per file · uploaded in {{ number_format(config('erp_vault.chunk_bytes')/1048576,0) }} MiB parts. Uploads expire after 24 hours. Browser must stay connected until confirmation.</p>
<form id="vault-form" class="mt-4 grid gap-3 md:grid-cols-2">
<label class="text-xs font-bold">Document Title<input name="title" type="text" minlength="4" maxlength="180" required class="mt-1 block w-full rounded-lg border border-slate-300 p-3" placeholder="Signed engagement letter"></label>
<label class="text-xs font-bold">Private Document<input name="file" type="file" accept=".pdf,.png,.jpg,.jpeg" required class="mt-1 block w-full rounded-lg border border-slate-300 p-3"></label>
<label class="text-xs font-bold">Visibility
<select name="visibility" class="mt-1 block w-full rounded-lg border border-slate-300 p-3">
<option value="partner">Partner only</option><option value="subject">Selected member + Partner</option><option value="team">Selected member + assigned Manager + Partner</option>
</select></label>
<label class="text-xs font-bold">Recipient (required for personal/team)
<select name="subject_membership_id" class="mt-1 block w-full rounded-lg border border-slate-300 p-3">
<option value="">No recipient (Partner only)</option>
@foreach($members as $member)<option value="{{ $member->id }}">{{ $member->name }} · {{ ucfirst($member->role) }}</option>@endforeach
</select></label>
<div class="md:col-span-2 flex flex-wrap items-center gap-4">
<button type="submit" class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Upload, Verify &amp; Commit</button>
<progress id="vault-progress" value="0" max="100" class="h-3 flex-1" aria-label="Upload progress"></progress>
</div>
<p id="vault-message" role="status" aria-live="polite" class="md:col-span-2 text-xs text-slate-700">Files are validated after assembly. Incomplete uploads do not become visible objects.</p>
</form>
</section>
@if($uploads->isNotEmpty())
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="font-extrabold">Pending Reserved Uploads</h3>
<p class="mt-1 text-xs text-slate-500">If a previous upload was interrupted, cancel it to release reserved storage. Existing chunk bytes will be removed.</p>
<div class="mt-3 space-y-2">
@foreach($uploads as $pending)
<div class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-100 py-2">
<div class="text-xs font-semibold">{{ $pending->title }} · {{ number_format($pending->expected_bytes/1048576,2) }} MiB · Expires {{ $pending->expires_at }} UTC</div>
<button type="button" class="vault-cancel rounded-lg border border-rose-300 px-3 py-2 text-xs font-bold text-rose-800" data-upload-id="{{ $pending->id }}">Cancel Pending Upload</button>
</div>
@endforeach
</div>
</section>
@endif
@endif
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="font-extrabold">Authorized Vault Documents</h3><p class="mt-1 text-xs text-slate-500">Records are append-only. Archive hides a document from non-Partner users, but does not free storage or override a legal hold.</p></div>
<div class="divide-y divide-slate-100">
@forelse($objects as $doc)
<article class="flex flex-wrap items-start justify-between gap-4 p-5">
<div><p class="text-sm font-extrabold">{{ $doc->title }}</p>
<p class="mt-1 text-xs text-slate-500">{{ $doc->original_filename }} · {{ number_format($doc->byte_size/1048576,2) }} MiB · {{ ucfirst($doc->visibility) }} @if($doc->subject_name) · {{ $doc->subject_name }} @endif</p>
<p class="mt-1 text-[11px] text-slate-500">Verified SHA-256: {{ substr($doc->sha256,0,16) }}…</p>
@if($doc->archived_at)<p class="mt-2 text-xs font-bold text-rose-800">Archived · evidence preserved</p>@endif
@if($doc->legal_hold)<p class="mt-1 text-xs font-bold text-amber-800">Retention/legal hold active</p>@endif</div>
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('erp.vault.download',['firmSlug'=>$firmSlug,'id'=>$doc->id]) }}" class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">Secure Download</a>
@if($actor->role==='partner')
@if(!$doc->archived_at)
<form method="POST" action="{{ route('erp.vault.archive',['firmSlug'=>$firmSlug,'id'=>$doc->id]) }}" data-corporate-confirm="Archive this object? It remains stored and counted toward quota.">@csrf
<button class="rounded-lg border border-rose-200 px-3 py-2 text-xs font-bold text-rose-800">Archive</button></form>
@endif
<form method="POST" action="{{ route('erp.vault.hold',['firmSlug'=>$firmSlug,'id'=>$doc->id]) }}" class="flex gap-2">@csrf
<input type="hidden" name="legal_hold" value="{{ $doc->legal_hold ? 0 : 1 }}">
<input type="text" name="reason" required minlength="15" maxlength="1000" placeholder="Mandatory retention reason" class="w-40 rounded-lg border p-2 text-xs">
<button class="rounded-lg border px-3 py-2 text-xs font-bold">{{ $doc->legal_hold ? 'Release Hold' : 'Place Hold' }}</button></form>
@endif</div>
</article>
@empty <p class="p-6 text-sm text-slate-500">No private vault objects are shared with this membership.</p>
@endforelse
</div>
</section>
@if($actor->role==='partner')
<script>
(() => {
  const endpoint = @json(route('erp.vault.start', ['firmSlug'=>$firmSlug]));
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const headers = {'X-CSRF-TOKEN':token,'Accept':'application/json'};
  const message = document.getElementById('vault-message');
  const progress = document.getElementById('vault-progress');
  const form = document.getElementById('vault-form');
  async function request(url, options) {
    const response = await fetch(url, {credentials:'same-origin',...options,
      headers:{...headers,...(options.headers||{})}});
    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      const details = json.errors ? Object.values(json.errors).flat().join(' ') : json.message;
      throw new Error(details || 'Upload request was rejected (HTTP '+response.status+').');
    }
    return json;
  }
  form.addEventListener('submit', async event => {
    event.preventDefault();
    const button = form.querySelector('button[type=submit]');
    const file = form.elements.file.files[0];
    if (!file) return;
    if (!/\.(pdf|png|jpe?g)$/i.test(file.name)) {message.textContent='Choose a PDF, PNG or JPEG.'; return;}
    button.disabled=true; progress.value=0;
    let uploadId=null;
    try {
      const start = await request(endpoint, {method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({
          title:form.elements.title.value, filename:file.name, expected_bytes:file.size,
          visibility:form.elements.visibility.value,
          subject_membership_id:form.elements.subject_membership_id.value || null,
        })});
      uploadId=start.upload_id;
      for(let i=0;i<start.parts;i++) {
        const chunk=file.slice(i*start.chunk_bytes, Math.min(file.size,(i+1)*start.chunk_bytes));
        const payload=new FormData();
        payload.append('chunk',chunk,'part.bin');
        await request(endpoint+'/'+uploadId+'/parts/'+(i+1),{method:'POST',body:payload});
        progress.value=Math.round((i+1)/start.parts*95);
        message.textContent='Verified part '+(i+1)+' of '+start.parts+' received.';
      }
      const complete=await request(endpoint+'/'+uploadId+'/complete',{method:'POST'});
      progress.value=100;
      message.textContent='Private file verified and committed: object #'+complete.object_id+'.';
      window.location.reload();
    } catch(error) {
      message.textContent=String(error.message || error)+' The reserved upload can be cancelled in Pending Uploads.';
    } finally {button.disabled=false;}
  });
  document.querySelectorAll('.vault-cancel').forEach(button => {
    button.addEventListener('click',async () => {
      if(!confirm('Cancel this pending upload and release reserved quota?'))return;
      button.disabled=true;
      try{await request(endpoint+'/'+button.dataset.uploadId+'/cancel',{method:'POST'});location.reload();}
      catch(error){button.disabled=false;message.textContent=String(error.message || error);}
    });
  });
})();
</script>
@endif
@endsection
