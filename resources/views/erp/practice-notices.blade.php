@extends('layouts.erp')
@section('title','Publish Practice Notices')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PARTNER ONLY · AUDITED COMMUNICATION</p>
<h2 class="mt-2 text-2xl font-extrabold">Notice Center</h2>
<p class="mt-2 text-sm text-slate-600">Publish an immutable message to currently active authorized members of this Partner practice. Delivery is in-app; this action does not send email or SMS. Notices are copied into each member's private inbox and audited.</p></section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Publish a New Notice</h3>
<form data-corporate-confirm="Publish this notice to the selected authorized audience? Recipients will receive it in their private ERP inbox." method="POST" action="{{ route('erp.notices.publish',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 md:grid-cols-2">@csrf
<label class="text-xs font-bold">Notice Title<input name="title" required minlength="5" maxlength="160" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm" placeholder="Monthly staff meeting"></label>
<label class="text-xs font-bold">Who should receive it?
<select name="audience" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm">
<option value="everyone">Everyone in this Partner workspace</option>
<option value="managers">Managers only</option>
<option value="students">Students only</option>
</select></label>
<label class="text-xs font-bold md:col-span-2">Message
<textarea name="body" required minlength="20" maxlength="5000" rows="5" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea></label>
<label class="flex items-start gap-2 text-xs font-bold md:col-span-2"><input type="checkbox" name="confirm_publish" value="1" required> I confirm this notice is approved for the selected Partner workspace audience. Delivery cannot be reversed.</label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Publish to Private Inboxes</button></form>
</section>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
<div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Published Notices</h3></div>
@forelse($notices as $notice)
<article class="border-b border-slate-100 p-5"><div class="flex flex-wrap items-start justify-between gap-2"><h4 class="text-sm font-extrabold">{{ $notice->title }}</h4><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-900">{{ ucfirst($notice->audience) }} · {{ $notice->recipients_count }} recipient(s)</span></div>
<p class="mt-2 whitespace-pre-wrap text-xs leading-relaxed text-slate-600">{{ $notice->body }}</p>
<p class="mt-2 text-xs text-slate-400">{{ $notice->created_at }}</p></article>
@empty<p class="p-6 text-sm text-slate-500">No notices have been published by this Partner.</p>@endforelse
</section>
@endsection
