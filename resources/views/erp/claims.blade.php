@extends('layouts.erp')
@section('title','Conveyance Claims')
@section('content')
<section><h2 class="text-2xl font-extrabold">Conveyance & Travel Claims</h2><p class="mt-2 text-sm text-slate-500">Receipts are stored privately; reimbursement is included in payroll only after Partner-authorized approval.</p></section>
@if($actor->role==='student')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h3 class="text-base font-extrabold">Submit Conveyance Claim</h3>
<form action="{{ route('erp.claims.submit',['firmSlug'=>$firmSlug]) }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Journey Date<input type="date" name="journey_date" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Amount (BDT)<input type="number" name="amount" min="0.01" max="50000" step="0.01" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Journey Route<input type="text" name="journey_route" maxlength="200" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Office → Client Site"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Purpose<textarea name="purpose" minlength="10" maxlength="2000" rows="3" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Receipt (PDF/JPG/PNG, max 2 MB)<input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-xs"></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit Claim</button></form></section>@endif
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Claims & Reviews</h3></div>
<div class="divide-y divide-slate-100">@forelse($rows as $row)<article class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-sm font-bold">{{ $row->member_name }} · ৳ {{ number_format((float)$row->amount,2) }}</p><p class="mt-1 text-xs text-slate-500">{{ $row->journey_date }} · {{ $row->journey_route }}</p><p class="mt-2 text-xs text-slate-600">{{ $row->purpose }}</p><a class="mt-2 inline-flex text-xs font-bold text-amber-800 hover:underline" href="{{ route('erp.claims.receipt',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}">Download private receipt</a></div><div class="space-y-2"><span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">{{ ucfirst($row->status) }}</span>
@if(in_array($actor->role,['partner','manager']) && $row->status==='pending')
<form action="{{ route('erp.claims.decide',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<input type="text" name="decision_note" maxlength="1000" placeholder="Review note" class="min-w-44 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve</button><button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject</button></form>
@endif</div></article>@empty<p class="p-8 text-sm text-slate-500">No claims found.</p>@endforelse</div></section>
@endsection