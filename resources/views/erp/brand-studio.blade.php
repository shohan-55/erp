@extends('layouts.erp')
@section('title','Company Identity & Letterhead Studio')
@section('content')
<section class="overflow-hidden rounded-3xl border border-amber-300 bg-[#17191F] p-6 text-white shadow-lg sm:p-8">
  <p class="text-xs font-black uppercase tracking-[.18em] text-[#FCDF02]">✦ COMPANY BRANDING · OFFICIAL HR DOCUMENTS</p>
  <h2 class="mt-3 text-2xl font-black sm:text-3xl">Brand Identity Studio</h2>
  <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300">
    Customize your Partner workspace's approved visual identity, letterhead, signature display and contact information. The registered legal company name and firm-wide governance remain controlled separately. Private assets never enter the public webroot.
  </p>
  <div class="mt-5 flex flex-wrap gap-2 text-xs font-bold">
    <span class="rounded-full border border-amber-300/40 px-3 py-2 text-[#FCDF02]">{{ $workspace->name }}</span>
    <span class="rounded-full border border-white/20 px-3 py-2">{{ ucfirst($actor->role) }} {{ $actor->role==='partner'?'· Can edit':'· Read-only' }}</span>
  </div>
</section>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(290px,1fr)]">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <div><h3 class="text-lg font-extrabold">Company & Workspace Profile</h3>
      <p class="mt-1 text-xs text-slate-500">Business contact and branded document header for this Partner workspace</p></div>
    <i data-lucide="building-2" class="h-7 w-7 text-amber-800"></i>
  </div>
  <p class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-700"><strong>Registered Legal Name:</strong> {{ $company->name }} <span class="block pt-1 text-slate-500">Read-only here. Company-wide registered name remains Platform-governed.</span></p>
  <form method="POST" action="{{ route('erp.brand.save',['firmSlug'=>$firmSlug]) }}" class="mt-5 space-y-4" data-corporate-confirm="Apply this branded workspace identity and document contact information? Existing issued letters will keep their recorded snapshot.">
    @csrf
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="text-xs font-bold text-slate-700 sm:col-span-2">Display / Letterhead Name
        <input name="public_name" required maxlength="160" value="{{ old('public_name',$profile->public_name) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700 sm:col-span-2">Office Address
        <textarea name="office_address" maxlength="400" rows="2" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">{{ old('office_address',$profile->office_address) }}</textarea>
      </label>
      <label class="text-xs font-bold text-slate-700">Email
        <input name="email" type="email" maxlength="180" value="{{ old('email',$profile->email) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Phone
        <input name="phone" maxlength="60" value="{{ old('phone',$profile->phone) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700 sm:col-span-2">Official Website (HTTPS)
        <input name="website" type="url" maxlength="200" placeholder="https://example.com" value="{{ old('website',$profile->website) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Authorized Signatory Display Name
        <input name="signatory_name" maxlength="160" placeholder="Printed name only — not a digital signature" value="{{ old('signatory_name',$profile->signatory_name) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
      <label class="text-xs font-bold text-slate-700">Signatory Title
        <input name="signatory_title" maxlength="160" placeholder="Partner / Principal" value="{{ old('signatory_title',$profile->signatory_title) }}" @disabled($actor->role!=='partner') class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
      </label>
    </div>
    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
      <h4 class="text-sm font-extrabold">Adaptive Corporate Color Palette</h4>
      <p class="mt-1 text-xs text-slate-600">Choose your brand accent and a strongly contrasting dark navigation background. The system automatically selects readable accent text.</p>
      <div class="mt-4 grid grid-cols-2 gap-3">
        <label class="text-xs font-bold text-slate-700">Accent Color
          <input type="color" name="accent_color" required value="{{ old('accent_color',$profile->accent_color) }}" @disabled($actor->role!=='partner') class="mt-2 h-12 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
        </label>
        <label class="text-xs font-bold text-slate-700">Sidebar Color
          <input type="color" name="sidebar_color" required value="{{ old('sidebar_color',$profile->sidebar_color) }}" @disabled($actor->role!=='partner') class="mt-2 h-12 w-full cursor-pointer rounded-lg border border-slate-300 bg-white p-1">
        </label>
      </div>
    </div>
    @if($actor->role==='partner')
    <button type="submit" class="min-h-12 rounded-xl bg-[#17191F] px-6 py-3 text-sm font-extrabold text-[#FCDF02]">Save Workspace Brand Profile →</button>
    @endif
  </form>
</section>

<aside class="space-y-5">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <h3 class="text-base font-extrabold">Current Brand Preview</h3>
  <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
    <div class="p-5 text-white" style="background-color:{{ $profile->sidebar_color }}">
      <div class="flex items-center gap-3">
        <div class="flex h-12 w-12 items-center justify-center rounded-xl p-1" style="background-color:{{ $profile->accent_color }};color:{{ \App\Support\BrandIdentity::onAccent($profile->accent_color) }}">
          @if($profile->logo_asset_id)
            <img class="h-full w-full object-contain" alt="Company logo" src="{{ route('erp.brand.asset',['firmSlug'=>$firmSlug,'id'=>$profile->logo_asset_id]) }}">
          @else<span class="text-xs font-black">{{ mb_substr($profile->public_name,0,2) }}</span>@endif
        </div>
        <div class="min-w-0">
          <strong class="block truncate text-sm">{{ $profile->public_name }}</strong>
          <span class="text-[11px] text-white/70">{{ $workspace->name }}</span>
        </div>
      </div>
    </div>
    <div class="bg-slate-50 p-5 text-xs text-slate-700">
      <p><strong>Contact:</strong> {{ $profile->email ?: 'Not configured' }}</p>
      <p class="mt-2"><strong>Signatory:</strong> {{ $profile->signatory_name ?: 'Partner approval record' }}</p>
      <p class="mt-2"><strong>Letterhead:</strong> {{ $profile->letterhead_asset_id ? 'Uploaded' : 'Text-based corporate layout' }}</p>
    </div>
  </div>
  <p class="mt-3 text-xs leading-relaxed text-slate-500">Uploaded PDF/DOCX letterhead originals are stored privately. Image letterheads can be rendered on printable letters; uploaded DOCX/PDF originals are archived templates, not automatically merged into generated Word files.</p>
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
  <h3 class="text-base font-extrabold">Official Asset Library</h3>
  <p class="mt-2 text-xs leading-relaxed text-slate-600">Logo and seal: PNG/JPEG. Letterhead: PDF/DOCX/PNG/JPEG. Maximum 4 MB each. Updating an asset never destroys the earlier audited version.</p>
  @if($actor->role==='partner')
  <form method="POST" action="{{ route('erp.brand.upload',['firmSlug'=>$firmSlug]) }}" enctype="multipart/form-data" class="mt-4 space-y-3">
    @csrf
    <label class="block text-xs font-bold">Asset Type
      <select name="kind" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
        <option value="logo">Company Logo</option>
        <option value="letterhead">Official Letterhead</option>
        <option value="seal">Company Seal</option>
      </select>
    </label>
    <label class="block text-xs font-bold">Secure File Upload
      <input type="file" name="file" required accept=".png,.jpg,.jpeg,.pdf,.docx" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-xs">
    </label>
    <button class="w-full rounded-xl bg-[#17191F] px-4 py-3 text-sm font-extrabold text-[#FCDF02]">Upload & Activate Asset</button>
  </form>
  @endif
  <div class="mt-4 space-y-2">
    @forelse($assets as $asset)
      <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-3 text-xs">
        <div class="min-w-0"><p class="truncate font-extrabold">{{ ucfirst($asset->kind) }} · #{{ $asset->id }}</p><p class="mt-1 truncate text-slate-500">{{ $asset->original_name }}</p></div>
        @if($actor->role==='partner')
        <a href="{{ route('erp.brand.asset',['firmSlug'=>$firmSlug,'id'=>$asset->id]) }}" class="shrink-0 rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold">View</a>
        @elseif($asset->kind==='logo' && (int)$asset->id===(int)$profile->logo_asset_id)
        <span class="text-xs font-bold text-emerald-700">Active</span>
        @endif
      </div>
    @empty
      <p class="rounded-xl bg-slate-50 p-4 text-xs text-slate-500">No logo or letterhead uploaded yet.</p>
    @endforelse
  </div>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
 <h3 class="text-base font-extrabold">Official Letter & Report Center</h3>
 <a href="{{ route('erp.official_templates.index',['firmSlug'=>$firmSlug]) }}" class="mt-3 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold">Edit Official Document Templates ↗</a>
 <a href="{{ route('erp.staff_certificates.index',['firmSlug'=>$firmSlug]) }}" class="mt-3 inline-flex rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold">Official Certificate Library ↗</a>
 <p class="mt-2 text-xs text-slate-500">After final Partner approval, Students can print or download their approved Leave Letter in Word format from Leave History. Official PDF is available via the browser's Save as PDF action.</p>
 <div class="mt-4 flex flex-wrap gap-2">
   <a href="{{ route('erp.leaves',['firmSlug'=>$firmSlug]) }}" class="rounded-xl bg-[#17191F] px-4 py-3 text-xs font-bold text-[#FCDF02]">Leave Applications →</a>
   @if($actor->role==='partner')
   <a href="{{ route('erp.official.register.export',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-slate-300 px-4 py-3 text-xs font-bold">Export Official Register (Excel CSV) ↓</a>
   @endif
 </div>
</section>
</aside>
</div>
@endsection
