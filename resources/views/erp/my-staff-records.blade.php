@extends('layouts.erp')
@section('title','My Staff & Articleship Records')
@section('content')
<section class="space-y-2"><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRIVATE HUMAN RESOURCES RECORDS</p>
<h2 class="text-2xl font-extrabold">My Staff & Articleship Records</h2>
<p class="max-w-2xl text-sm text-slate-600">Only your authenticated workspace membership's records are displayed. Partner HR documents are stored privately, never published as public URLs.</p></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Employment & Articleship Details</h3>
@if($profile)
<div class="mt-4 grid gap-3 sm:grid-cols-2">
@foreach([
['label'=>'Designation','value'=>$profile->designation],
['label'=>'Staff Code','value'=>$profile->staff_code],
['label'=>'Joined On','value'=>$profile->joined_on],
['label'=>'Articleship Registration','value'=>$profile->articleship_registration],
['label'=>'Articleship Start','value'=>$profile->articleship_started_on],
['label'=>'Articleship End','value'=>$profile->articleship_ends_on],
] as $item)
<div class="rounded-xl bg-slate-50 p-3"><p class="text-xs text-slate-500">{{ $item['label'] }}</p>
<p class="mt-1 text-sm font-bold">{{ $item['value'] ?: 'Not available' }}</p></div>
@endforeach</div>
@else
<p class="mt-3 text-sm text-slate-500">Your Partner has not entered your Staff Profile yet.</p>
@endif
@if($latestSalary)
<p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs font-bold text-amber-950">Latest approved monthly allowance: ৳ {{ number_format((float)$latestSalary->new_amount,2) }} · Effective {{ $latestSalary->effective_month }}</p>
@endif
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">My Private Documents</h3>
@forelse($documents as $doc)
<a class="mt-3 block rounded-xl border border-slate-200 p-3 text-sm font-bold hover:border-amber-300" href="{{ route('erp.staff.documents.download',['firmSlug'=>$firmSlug,'documentId'=>$doc->id]) }}">{{ $doc->title }} · {{ ucfirst($doc->document_type) }} <span class="text-xs text-amber-900">Download</span></a>
@empty<p class="mt-3 text-sm text-slate-500">Your Partner has not uploaded any staff documents.</p>
@endforelse
</section>
@endsection
