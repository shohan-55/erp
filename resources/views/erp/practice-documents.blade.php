@extends('layouts.erp')
@section('title','Private Practice Documents')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">SECURE DOCUMENT CENTER · PARTNER ISOLATED</p>
<h2 class="mt-2 text-2xl font-extrabold">Private Document Center</h2>
<p class="mt-2 text-sm text-slate-600">Documents are kept in private storage and served only after checking your authorized workspace membership. Partner-only documents are confidential to the Partner; personal documents are visible only to their subject, and Team documents also to the assigned Manager.</p></section>
@if($actor->role==='partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Upload Private Practice Document</h3>
<form method="POST" action="{{ route('erp.documents.upload',['firmSlug'=>$firmSlug]) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 md:grid-cols-2">@csrf
<label class="text-xs font-bold md:col-span-2">Document Title<input type="text" name="title" required minlength="4" maxlength="180" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Category
<select name="category" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
@foreach(['policy'=>'Policy','audit'=>'Audit','hr'=>'Human Resources','notice'=>'Notice / Circular','other'=>'Other'] as $value=>$label)
<option value="{{ $value }}">{{ $label }}</option>@endforeach
</select></label>
<label class="text-xs font-bold">Who can access?
<select name="visibility" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="partner">Partner only (no recipient)</option>
<option value="subject">Selected Member only (+ Partner)</option>
<option value="team">Selected Member + Assigned Manager (+ Partner)</option>
</select></label>
<label class="text-xs font-bold md:col-span-2">Selected Member (required for Member/Team documents)
<select name="subject_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">No member (Partner only)</option>
@foreach($members as $member)
<option value="{{ $member->id }}">{{ $member->name }} · {{ ucfirst($member->role) }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold md:col-span-2">Private File (PDF/JPG/PNG, max 5 MB)
<input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Upload & Audit Document</button>
</form></section>
@endif
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">My Authorized Documents</h3></div>
<div class="divide-y divide-slate-100">
@forelse($q as $document)
<article class="flex flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
<div><h4 class="text-sm font-extrabold">{{ $document->title }}</h4>
<p class="mt-1 text-xs text-slate-600">{{ ucfirst($document->category) }} · {{ str_replace('_',' ',ucfirst($document->visibility)) }} @if($document->subject_name) · {{ $document->subject_name }} @endif · {{ number_format($document->byte_size / 1024,1) }} KB</p>
@if($document->archived_at)<p class="mt-1 text-xs font-bold text-rose-800">Archived (Partner evidence only)</p>@endif
</div>
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('erp.documents.download',['firmSlug'=>$firmSlug,'id'=>$document->id]) }}" class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold">Secure Download</a>
@if($actor->role==='partner' && !$document->archived_at)
<form method="POST" action="{{ route('erp.documents.archive',['firmSlug'=>$firmSlug,'id'=>$document->id]) }}">@csrf
<button class="rounded-lg border border-rose-200 px-4 py-2 text-xs font-bold text-rose-900">Archive</button>
</form>
@endif
</div></article>
@empty<p class="p-7 text-sm text-slate-500">No documents are currently shared with your membership.</p>@endforelse
</div></section>
@endsection
