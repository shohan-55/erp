@extends('layouts.erp')
@section('title','Corporate Document Template Studio')
@section('content')
<section class="rounded-3xl bg-[#17191F] p-6 text-white sm:p-8">
  <p class="text-xs font-extrabold uppercase tracking-widest text-[#FCDF02]">✦ CORPORATE DOCUMENTS · APPROVED TEMPLATES</p>
  <h2 class="mt-2 text-2xl font-black">Official Document Template Studio</h2>
  <p class="mt-2 max-w-3xl text-sm text-slate-300">Edit the wording of official approved leave letters, employment certificates and locked-payroll salary certificates. Each change creates a new immutable version. Prior documents continue using their originally issued content and brand.</p>
  <div class="mt-4 flex flex-wrap gap-2 text-xs">
    <a href="{{ route('erp.brand.index',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2 text-[#FCDF02]">Brand Identity Studio ↗</a>
    <a href="{{ route('erp.staff_certificates.index',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2 text-[#FCDF02]">Certificate Library ↗</a>
  </div>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <h3 class="font-extrabold">Available Plain-text Placeholders</h3>
  <p class="mt-2 text-xs text-slate-600">You may use any of the following exactly as written. HTML, arbitrary code and unknown placeholders will be rejected. Values are pulled from approved records and XML/HTML escaped during export.</p>
  <div class="mt-3 flex flex-wrap gap-2">
    @foreach(\App\Support\OfficialTemplate::TOKENS as $token)
      <code class="rounded-lg bg-slate-100 px-2.5 py-1.5 text-xs text-slate-700">{!! '&#123;&#123;'.e($token).'&#125;&#125;' !!}</code>
    @endforeach
  </div>
</section>
<div class="grid gap-5 lg:grid-cols-2">
@foreach($templates as $key=>$template)
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h3 class="text-lg font-extrabold">{{ $template['title'] }}</h3>
    <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Version {{ $template['version'] }}</span>
  </div>
  <p class="mt-2 text-xs text-slate-500">{{ str_replace('_',' ',ucfirst($key)) }} · Editable text only · Partner authorization required</p>
  <form method="POST" action="{{ route('erp.official_templates.save',['firmSlug'=>$firmSlug]) }}" data-corporate-confirm="Publish a new official document template version? Existing issued letters will not be rewritten." class="mt-4 space-y-3">
    @csrf
    <input type="hidden" name="template_key" value="{{ $key }}">
    <label class="block text-xs font-bold">Document Title
      <input name="document_title" value="{{ $template['title'] }}" maxlength="180" required @disabled($actor->role!=='partner') class="mt-1 block w-full rounded-xl border border-slate-300 p-3 text-sm">
    </label>
    <label class="block text-xs font-bold">Main Letter Wording
      <textarea name="body_text" required minlength="30" maxlength="2500" rows="7" @disabled($actor->role!=='partner') class="mt-1 block w-full rounded-xl border border-slate-300 p-3 font-mono text-xs leading-relaxed">{{ $template['body'] }}</textarea>
    </label>
    <label class="block text-xs font-bold">Footer Note
      <textarea name="footer_note" maxlength="500" rows="2" @disabled($actor->role!=='partner') class="mt-1 block w-full rounded-xl border border-slate-300 p-3 text-xs">{{ $template['footer'] }}</textarea>
    </label>
    @if($actor->role==='partner')
    <button class="w-full rounded-xl bg-[#17191F] px-5 py-3 text-sm font-extrabold text-[#FCDF02]">Publish New Version →</button>
    @else<p class="rounded-lg bg-slate-50 p-3 text-xs font-semibold text-slate-600">Read-only. Only the Partner can publish official templates.</p>@endif
  </form>
</section>
@endforeach
</div>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <h3 class="text-lg font-extrabold">Approved Template Version History</h3>
  <p class="mt-2 text-xs text-slate-600">Append-only, Partner approved. Older issued documents retain their original text and approved source values.</p>
  <div class="mt-4 grid gap-3 sm:grid-cols-2">
  @forelse($history as $version)
    <div class="rounded-xl bg-slate-50 p-4">
      <p class="text-sm font-bold">{{ str_replace('_',' ',ucfirst($version->template_key)) }}</p>
      <p class="mt-1 text-xs text-slate-600">Version {{ $version->version }} · {{ $version->created_at }}</p>
      <p class="mt-2 text-xs text-slate-500">{{ $version->document_title }}</p>
    </div>
  @empty
    <p class="text-xs text-slate-500">No customized templates yet. Safe product defaults are available above.</p>
  @endforelse
  </div>
</section>
@endsection
