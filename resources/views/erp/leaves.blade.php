@extends('layouts.erp')
@section('title','Leave Requests')
@section('content')
<section><h2 class="text-2xl font-extrabold">Leave Management</h2><p class="mt-2 text-sm text-slate-500">Students apply; assigned Managers review; authorized Partners give final approval.</p></section>
@if($actor->role==='student')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h3 class="text-base font-extrabold">Apply for Leave</h3>
<form action="{{ route('erp.leaves.submit',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Type<select name="leave_type" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"><option value="annual">Annual</option><option value="sick">Sick</option><option value="emergency">Emergency</option></select></label>
<div class="hidden md:block"></div>
<label class="text-xs font-bold text-slate-700">Start Date<input type="date" name="date_from" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">End Date<input type="date" name="date_to" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Reason<textarea name="reason" rows="3" required minlength="10" maxlength="2000" class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit Leave Request</button>
</form></section>@endif
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Leave History & Review Queue</h3></div>
<div class="divide-y divide-slate-100">@forelse($rows as $row)<article class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-sm font-bold">{{ $row->member_name }} <span class="text-xs font-normal text-slate-400">#{{ $row->id }}</span></p><p class="mt-1 text-xs text-slate-600">{{ ucfirst($row->leave_type) }} · {{ $row->date_from }} → {{ $row->date_to }}</p><p class="mt-2 max-w-xl text-xs text-slate-500">{{ $row->reason }}</p></div><div class="space-y-2"><span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">{{ str_replace('_',' ',ucfirst($row->status)) }}</span>
@if(in_array($actor->role,['partner','manager']) && ($row->status==='pending' || ($actor->role==='partner' && $row->status==='manager_reviewed')))
<form action="{{ route('erp.leaves.decide',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<input type="text" name="decision_note" maxlength="1000" placeholder="Decision note (optional)" class="min-w-44 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button type="submit" name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">{{ $actor->role==='manager' ? 'Review' : 'Approve' }}</button><button type="submit" name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject</button></form>
@endif</div></article>@empty<p class="p-8 text-sm text-slate-500">No leave records found.</p>@endforelse</div>
</section>
@endsection