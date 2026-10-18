@extends('layouts.erp')
@section('title','Notifications')
@section('content')
<section><h2 class="text-2xl font-extrabold">Your Notifications</h2><p class="mt-2 text-sm text-slate-500">Persisted notifications belonging to your authenticated company membership only.</p></section>
@include('erp.partials.module-guide',['module'=>'notifications'])
<form method="POST" action="{{ route('erp.notifications.read_all',['firmSlug'=>$firmSlug]) }}" class="flex justify-end">@csrf<button class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-extrabold">Mark All My Notifications Read</button></form>
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="divide-y divide-slate-100">
@forelse($rows as $item)
<article class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between {{ $item->read_at ? 'bg-white' : 'bg-amber-50/50' }}"><div><p class="text-sm font-extrabold">{{ $item->title }}</p><p class="mt-1 text-xs text-slate-600">{{ $item->message }}</p><p class="mt-2 text-[11px] text-slate-400">{{ \Carbon\Carbon::parse($item->created_at,'UTC')->timezone($company->timezone)->format('d M Y, h:i A') }}</p></div>
@if(!$item->read_at)<form method="POST" action="{{ route('erp.notifications.read',['firmSlug'=>$firmSlug,'id'=>$item->id]) }}">@csrf<button class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold">Mark Read</button></form>@else<span class="text-xs font-semibold text-emerald-700">Read</span>@endif</article>
@empty<p class="p-8 text-sm text-slate-500">No notifications yet.</p>@endforelse
</div></section>
@endsection