@extends('layouts.erp')
@section('title','Staff & Articleship Management')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">INDEPENDENT PARTNER · PEOPLE OPERATIONS</p>
<h2 class="text-2xl font-extrabold">Staff & Articleship Management</h2>
<p class="max-w-3xl text-sm leading-relaxed text-slate-600">Only your Partner's staff profiles and documents are visible. Promotions update designations without silently changing security roles. Salary decisions take effect in a future payroll month; historic payroll snapshots are never rewritten. Transfers require the receiving Partner's consent.</p>
<a href="{{ route('erp.team',['firmSlug'=>$firmSlug]) }}" class="inline-flex rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold">Manage Team Invitations & Manager Assignments</a>
</section>

<section class="space-y-5" aria-label="Partner staff profiles">
@forelse($members as $member)
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="flex flex-wrap items-center justify-between gap-3">
<div>
<h3 class="text-base font-extrabold">{{ $member->name }}</h3>
<p class="mt-1 text-xs text-slate-600">{{ ucfirst($member->role) }} · #{{ $member->id }} · {{ $member->staff_code ?: 'Staff code not assigned' }} · {{ $member->designation ?: 'Designation pending' }}</p>
</div>
<span class="rounded-full px-3 py-1 text-xs font-bold {{ $member->active ? 'bg-emerald-50 text-emerald-900' : 'bg-rose-50 text-rose-800' }}">{{ $member->active ? 'Active' : 'Inactive / Exited' }}</span>
</div>
<div class="mt-5 grid gap-5 lg:grid-cols-2">
<form method="POST" action="{{ route('erp.staff.profile',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" class="space-y-3 rounded-xl bg-slate-50 p-4">@csrf
<h4 class="text-sm font-extrabold">Joining, Designation & Articleship</h4>
<p class="text-xs text-slate-500">Changing the designation is an auditable promotion. Security roles require separate authorized account management.</p>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-bold">Staff Code<input name="staff_code" maxlength="50" value="{{ $member->staff_code }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold">Joining Date<input name="joined_on" type="date" value="{{ $member->joined_on ?? now($company->timezone)->toDateString() }}" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Designation / Promotion Title<input name="designation" maxlength="100" required value="{{ $member->designation ?? ucfirst($member->role) }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold sm:col-span-2">Articleship Registration No. (if applicable)<input name="articleship_registration" maxlength="100" value="{{ $member->articleship_registration }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold">Articleship Start<input name="articleship_started_on" type="date" value="{{ $member->articleship_started_on }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold">Articleship End<input name="articleship_ends_on" type="date" value="{{ $member->articleship_ends_on }}" class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
</div>
<button class="rounded-lg bg-[#FCDF02] px-4 py-2 text-xs font-extrabold">Save Staff Profile</button>
</form>
<div class="space-y-4">
@if($member->role==='student')
<form method="POST" action="{{ route('erp.staff.compensation',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" class="space-y-3 rounded-xl border border-slate-200 p-4">@csrf
<h4 class="text-sm font-extrabold">Future Salary Increment</h4>
<p class="text-xs text-slate-500">Current baseline ৳ {{ number_format((float)$member->monthly_allowance,2) }}. A scheduled future rate is applied by the Payroll engine only from its effective month.</p>
<div class="grid gap-3 sm:grid-cols-2">
<label class="text-xs font-bold">Effective Month<input name="effective_month" type="month" min="{{ now($company->timezone)->addMonth()->format('Y-m') }}" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="text-xs font-bold">New Monthly Allowance (BDT)<input name="new_amount" type="number" min="0" max="500000" step="0.01" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
</div>
<label class="block text-xs font-bold">Salary Decision Reason<textarea name="reason" minlength="15" maxlength="1000" rows="2" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></textarea></label>
<button class="rounded-lg bg-slate-900 px-4 py-2 text-xs font-bold text-white">Schedule Salary Change</button>
</form>
@endif
<form method="POST" action="{{ route('erp.staff.documents.upload',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl border border-slate-200 p-4">@csrf
<h4 class="text-sm font-extrabold">Secure Staff Documents</h4>
<label class="block text-xs font-bold">Document Type
<select name="document_type" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm">
<option value="appointment">Appointment</option><option value="articleship">Articleship</option><option value="certificate">Certificate</option><option value="identity">Identity</option><option value="other">Other</option>
</select></label>
<label class="block text-xs font-bold">Document Title<input name="title" maxlength="160" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-sm"></label>
<label class="block text-xs font-bold">Private File (PDF/JPG/PNG, max 3 MB)<input name="document" type="file" accept=".pdf,.jpg,.jpeg,.png" required class="mt-1 w-full rounded-lg border border-slate-300 p-2 text-xs"></label>
<button class="rounded-lg border border-slate-300 px-4 py-2 text-xs font-bold">Upload Private Document</button>
</form>
</div>
</div>
<div class="mt-4 grid gap-4 lg:grid-cols-2">
<form method="POST" action="{{ route('erp.staff.lifecycle',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" class="flex flex-wrap items-end gap-2 rounded-xl bg-slate-50 p-4">@csrf
<div class="w-full text-sm font-bold">Staff Exit / Rejoin</div>
<select name="action" required class="rounded-lg border border-slate-300 p-2 text-xs"><option value="exit">Record Exit</option><option value="rejoin">Rejoin Previously Activated Staff</option></select>
<input name="reason" required minlength="15" maxlength="1000" placeholder="Reason (required)" class="min-w-48 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button class="rounded-lg bg-slate-900 px-3 py-2 text-xs font-bold text-white">Save Lifecycle</button>
</form>
@if($member->role==='student' && $member->active)
<form method="POST" action="{{ route('erp.staff.transfer.request',['firmSlug'=>$firmSlug,'memberId'=>$member->id]) }}" class="flex flex-wrap items-end gap-2 rounded-xl bg-amber-50 p-4">@csrf
<div class="w-full text-sm font-bold">Request Cross-Partner Transfer</div>
<select name="target_workspace_id" required class="rounded-lg border border-amber-300 p-2 text-xs">
<option value="">Destination Partner</option>
@foreach($workspaces as $target)<option value="{{ $target->id }}">{{ $target->name }}</option>@endforeach
</select>
<input name="reason" required minlength="15" maxlength="1000" placeholder="Transfer purpose" class="min-w-40 flex-1 rounded-lg border border-amber-300 p-2 text-xs">
<button class="rounded-lg bg-amber-700 px-3 py-2 text-xs font-bold text-white">Request Consent</button>
</form>
@endif
</div>
</article>
@empty
<p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">No Managers or Students have been added to this Partner workspace.</p>
@endforelse
</section>
<section class="grid gap-4 lg:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="font-extrabold">Incoming Partner Transfers</h3>
<p class="mt-1 text-xs text-slate-500">Accept only after the source Partner's unresolved work is cleared. Historical financial and HR records remain with the old membership.</p>
@forelse($inbound as $transfer)
<div class="mt-3 rounded-xl bg-slate-50 p-3 text-xs">Transfer #{{ $transfer->id }} · Source workspace #{{ $transfer->source_workspace_id }} · Student membership #{{ $transfer->source_membership_id }} · {{ ucfirst($transfer->status) }}
<p class="mt-1">{{ $transfer->reason }}</p>
@if($transfer->status==='pending')
<form method="POST" action="{{ route('erp.staff.transfer.decide',['firmSlug'=>$firmSlug,'id'=>$transfer->id]) }}" class="mt-2 flex gap-2">@csrf
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 font-bold text-white">Accept Transfer</button>
<button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 font-bold text-white">Reject</button>
</form>
@endif
</div>
@empty<p class="mt-3 text-xs text-slate-500">No incoming transfers.</p>@endforelse
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="font-extrabold">Outgoing Transfers</h3>
@forelse($outbound as $transfer)
<p class="mt-3 rounded-xl bg-slate-50 p-3 text-xs">#{{ $transfer->id }} · Member #{{ $transfer->source_membership_id }} → Workspace #{{ $transfer->target_workspace_id }} · {{ ucfirst($transfer->status) }}</p>
@empty<p class="mt-3 text-xs text-slate-500">No outgoing transfers.</p>@endforelse
</div>
</section>
<section class="grid gap-4 lg:grid-cols-2">
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="text-sm font-extrabold">Recent Staff Lifecycle Events</h3>
@forelse($events as $event)
<p class="mt-2 rounded-xl bg-slate-50 p-3 text-xs">{{ $event->name }} · {{ str_replace('_',' ',ucwords($event->event_type,'_')) }} · {{ $event->created_at }}</p>
@empty<p class="mt-3 text-xs text-slate-500">No lifecycle events yet.</p>@endforelse
</div>
<div class="rounded-2xl border border-slate-200 bg-white p-5">
<h3 class="text-sm font-extrabold">Private Documents</h3>
@forelse($documents as $document)
<a href="{{ route('erp.staff.documents.download',['firmSlug'=>$firmSlug,'documentId'=>$document->id]) }}" class="mt-2 block rounded-xl bg-slate-50 p-3 text-xs font-bold hover:underline">{{ $document->title }} · Member #{{ $document->membership_id }}</a>
@empty<p class="mt-3 text-xs text-slate-500">No private staff documents uploaded.</p>@endforelse
</div>
</section>
@endsection
