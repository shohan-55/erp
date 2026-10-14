<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>{{ $company->name }} — Requests & Claims</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#20232A]">
<header class="bg-[#17191F] text-white"><div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-5 py-4"><div><p class="text-xs font-bold text-[#FCDF02]">LIVE WORKSPACE · {{ ucfirst($actor->role) }}</p><h1 class="mt-1 font-extrabold">{{ $company->name }}</h1></div><div class="flex flex-wrap items-center gap-2"><a href="{{ route('operational.dashboard',['companySlug'=>$company->slug]) }}" class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Attendance</a><form method="POST" action="{{ route('operational.logout',['companySlug'=>$company->slug]) }}">@csrf<button class="rounded-lg border border-white/20 px-3 py-2 text-xs font-bold">Sign Out</button></form></div></div></header>
<main class="mx-auto max-w-6xl space-y-6 px-5 py-8">
  <div><p class="text-xs font-bold uppercase tracking-widest text-amber-800">Partner-scoped Operations</p><h2 class="mt-1 text-2xl font-extrabold">Leave, Conveyance & Notifications</h2><p class="mt-2 text-sm text-slate-500">Requests are stored against the student's Home Partner. Approvals are recorded; conveyance approval does not transfer funds or create a payroll payment.</p></div>
  @if(session('status'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('status') }}</p>@endif
  @if($errors->any())<p role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">{{ $errors->first() }}</p>@endif

  @if($actor->role === 'student')
  <div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 class="text-lg font-extrabold">Apply for Leave</h3><p class="mt-1 text-xs text-slate-500">Dates and reason are required. Approval is not automatic.</p>
      <form method="POST" action="{{ route('operational.leave.submit',['companySlug'=>$company->slug]) }}" class="mt-5 space-y-4">@csrf
        <label class="block text-xs font-bold">Leave Type<select name="type" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"><option value="annual">Annual</option><option value="sick">Sick</option><option value="emergency">Emergency</option></select></label>
        <div class="grid grid-cols-2 gap-3"><label class="block text-xs font-bold">From<input name="starts_on" type="date" value="{{ old('starts_on') }}" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"></label><label class="block text-xs font-bold">To<input name="ends_on" type="date" value="{{ old('ends_on') }}" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"></label></div>
        <label class="block text-xs font-bold">Reason<textarea name="reason" minlength="5" maxlength="2000" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm" rows="3">{{ old('reason') }}</textarea></label>
        <button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Submit Leave Request</button>
      </form>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
      <h3 class="text-lg font-extrabold">Conveyance Claim</h3><p class="mt-1 text-xs text-slate-500">Payments are processed separately after authorized review. Supporting receipts are not yet uploaded through this portal.</p>
      <form method="POST" action="{{ route('operational.claim.submit',['companySlug'=>$company->slug]) }}" class="mt-5 space-y-4">@csrf
        <div class="grid grid-cols-2 gap-3"><label class="block text-xs font-bold">Journey Date<input name="journey_date" type="date" value="{{ old('journey_date') }}" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"></label><label class="block text-xs font-bold">Amount (BDT)<input name="amount" type="number" min="0.01" max="50000" step="0.01" value="{{ old('amount') }}" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"></label></div>
        <label class="block text-xs font-bold">Journey Route<input name="journey_route" maxlength="255" value="{{ old('journey_route') }}" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm"></label>
        <label class="block text-xs font-bold">Business Purpose<textarea name="purpose" minlength="5" maxlength="2000" required class="mt-2 w-full rounded-xl border border-slate-200 p-3 text-sm" rows="3">{{ old('purpose') }}</textarea></label>
        <button class="w-full rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-extrabold">Submit Conveyance Claim</button>
      </form>
    </section>
  </div>
  @endif

  <div class="grid gap-5 lg:grid-cols-2">
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-5 py-4"><h3 class="text-base font-extrabold">Leave Applications</h3></div>
      <div class="divide-y divide-slate-100">
      @forelse($leaves as $item)
        <article class="space-y-3 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold">{{ ucfirst($item->type) }} · #{{ $item->id }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->starts_on }} → {{ $item->ends_on }} · Member #{{ $item->membership_id }}</p></div><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{ ucfirst($item->status) }}</span></div>
        <p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-600">{{ $item->reason }}</p>
        @if($actor->role !== 'student' && $item->status === 'pending')
        <form method="POST" action="{{ route('operational.leave.review',['companySlug'=>$company->slug,'requestId'=>$item->id]) }}" class="flex flex-wrap gap-2">@csrf<button name="decision" value="approved" class="rounded-lg bg-[#FCDF02] px-3 py-2 text-xs font-bold">Approve</button><button name="decision" value="rejected" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Reject</button></form>
        @endif
        </article>
      @empty<p class="p-6 text-sm text-slate-500">No leave applications in your scope.</p>@endforelse
      </div>
    </section>
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-5 py-4"><h3 class="text-base font-extrabold">Conveyance Claims</h3></div>
      <div class="divide-y divide-slate-100">
      @forelse($claims as $item)
        <article class="space-y-3 p-5"><div class="flex flex-wrap items-center justify-between gap-3"><div><p class="text-xs font-bold">৳ {{ number_format($item->amount,2) }} · #{{ $item->id }}</p><p class="mt-1 text-xs text-slate-500">{{ $item->journey_date }} · Member #{{ $item->membership_id }}</p></div><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">{{ ucfirst($item->status) }}</span></div>
        <p class="break-words text-xs font-medium text-slate-700">{{ $item->journey_route }}</p><p class="whitespace-pre-wrap break-words text-xs leading-relaxed text-slate-500">{{ $item->purpose }}</p>
        @if($actor->role !== 'student' && $item->status === 'pending')
        <form method="POST" action="{{ route('operational.claim.review',['companySlug'=>$company->slug,'requestId'=>$item->id]) }}" class="flex flex-wrap gap-2">@csrf<button name="decision" value="approved" class="rounded-lg bg-[#FCDF02] px-3 py-2 text-xs font-bold">Approve</button><button name="decision" value="rejected" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Reject</button></form>
        @endif
        </article>
      @empty<p class="p-6 text-sm text-slate-500">No conveyance claims in your scope.</p>@endforelse
      </div>
    </section>
  </div>

  <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-100 px-5 py-4"><h3 class="text-base font-extrabold">My Notifications</h3></div>
    <div class="divide-y divide-slate-100">
    @forelse($notifications as $note)
      <div class="flex flex-col justify-between gap-3 p-5 sm:flex-row sm:items-center"><div><p class="text-sm font-bold">{{ $note->title }}</p><p class="mt-1 text-xs text-slate-500">{{ $note->body }}</p></div>@if(!$note->read_at)<form method="POST" action="{{ route('operational.notification.read',['companySlug'=>$company->slug,'notificationId'=>$note->id]) }}">@csrf<button class="whitespace-nowrap rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold">Mark Read</button></form>@else<span class="text-xs text-slate-400">Read</span>@endif</div>
    @empty<p class="p-6 text-sm text-slate-500">No notifications yet.</p>@endforelse
    </div>
  </section>
</main></body></html>