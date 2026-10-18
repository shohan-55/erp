@extends('layouts.erp')
@section('title','Conveyance Claims')
@section('content')
<section><h2 class="text-2xl font-extrabold">Conveyance & Travel Claims</h2><p class="mt-2 text-sm text-slate-500">Receipts are stored privately; reimbursement is included in payroll only after Partner-authorized approval.</p></section>
@include('erp.partials.module-guide',['module'=>'claims'])
<section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
@foreach([
['label'=>'Claims in View','value'=>$summary['submitted']],
['label'=>'Awaiting Review','value'=>$summary['awaiting_review']],
['label'=>'Approved, Pending Payroll','value'=>'৳ '.number_format($summary['approved_bdt'],2)],
['label'=>'Paid Through Payroll','value'=>'৳ '.number_format($summary['paid_bdt'],2)]
] as $item)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-bold text-slate-500">{{ $item['label'] }}</p><p class="mt-3 text-2xl font-extrabold">{{ $item['value'] }}</p></div>
@endforeach
</section>
@if($actor->role === 'partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-base font-extrabold">Partner Expense Policy — {{ $year }}</h3>
<p class="mt-2 text-xs text-slate-600">Only your Partner workspace is affected. Once claims exist under the current year's policy, it cannot be retroactively revised. Every change is audited. Payment still occurs only through the approved Payroll flow.</p>
<form method="POST" action="{{ route('erp.claims.policy',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">@csrf
<label class="text-xs font-bold">Policy Year<input type="number" name="expense_year" value="{{ $year }}" min="{{ $year }}" max="{{ $year+1 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Maximum Per Claim (BDT)<input type="number" name="max_single_claim" step=".01" min="1" max="50000" value="{{ $policy?->max_single_claim ?? 50000 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Monthly Student Limit (BDT)<input type="number" name="monthly_student_limit" step=".01" min="1" max="1000000" value="{{ $policy?->monthly_student_limit ?? 100000 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold">Submission Window (days)<input type="number" name="submission_window_days" min="1" max="365" value="{{ $policy?->submission_window_days ?? 90 }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold sm:col-span-2 lg:col-span-4">Save Partner Expense Policy</button>
</form>
<form method="GET" action="{{ route('erp.claims.export',['firmSlug'=>$firmSlug]) }}" class="mt-5 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-5">
<label class="text-xs font-bold">Export a Month (private CSV)
<input type="month" name="month" value="{{ now($company->timezone)->format('Y-m') }}" required class="mt-2 block rounded-lg border border-slate-300 p-3 text-sm"></label>
<button class="rounded-lg border border-slate-300 px-4 py-3 text-xs font-extrabold">Export Partner Expense Register</button>
</form>
</section>
@endif
@if($actor->role === 'student' && $policy)
<p class="rounded-xl bg-amber-50 p-4 text-xs text-amber-950">Your Partner's policy permits a maximum of ৳ {{ number_format((float)$policy->max_single_claim,2) }} per claim, up to ৳ {{ number_format((float)$policy->monthly_student_limit,2) }} monthly, within {{ $policy->submission_window_days }} days of travel.</p>
@endif
@if($actor->role==='student')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7"><h3 class="text-base font-extrabold">Submit Conveyance Claim</h3>
<form action="{{ route('erp.claims.submit',['firmSlug'=>$firmSlug]) }}" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">@csrf
<label class="text-xs font-bold text-slate-700">Expense Category
<select name="expense_category" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm">
@foreach(\App\Support\ExpenseRules::CATEGORIES as $code=>$label)
<option value="{{ $code }}">{{ $label }}</option>
@endforeach
</select></label>
<label class="text-xs font-bold text-slate-700">Journey Date<input type="date" name="journey_date" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700">Amount (BDT)<input type="number" name="amount" min="0.01" max="50000" step="0.01" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Journey Route<input type="text" name="journey_route" maxlength="200" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm" placeholder="Office → Client Site"></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Purpose<textarea name="purpose" minlength="10" maxlength="2000" rows="3" required class="mt-2 w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea></label>
<label class="text-xs font-bold text-slate-700 md:col-span-2">Receipt (PDF/JPG/PNG, max 2 MB)<input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png" required class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-xs"></label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold md:col-span-2">Submit Claim</button></form></section>@endif
<section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Claims & Reviews</h3></div>
@include('erp.partials.history-filter',['historyKey'=>'claims','historyTitle'=>'Conveyance claims','historyStatuses'=>['pending'=>'Pending','manager_reviewed'=>'Manager reviewed','approved'=>'Approved','rejected'=>'Rejected','paid'=>'Paid','withdrawn'=>'Withdrawn']])
<div class="divide-y divide-slate-100" data-corporate-history-group="claims">@forelse($rows as $row)<article data-corporate-history-item data-history-status="{{ $row->status }}" class="flex flex-col gap-3 p-5 lg:flex-row lg:items-center lg:justify-between"><div><p class="text-sm font-bold">{{ $row->member_name }} · Claimed ৳ {{ number_format((float)$row->amount,2) }}</p>
@if($row->manager_recommended_amount !== null)<p class="mt-1 text-xs text-slate-600">Manager recommended: ৳ {{ number_format((float)$row->manager_recommended_amount, 2) }}</p>@endif
@if($row->approved_amount !== null)<p class="mt-1 text-xs font-bold text-emerald-800">Partner approved: ৳ {{ number_format((float)$row->approved_amount, 2) }} · Payroll uses approved amount</p>@endif<p class="mt-1 text-xs text-slate-500">{{ $row->journey_date }} · {{ $row->journey_route }} · {{ \App\Support\ExpenseRules::CATEGORIES[$row->expense_category] ?? 'Transport' }}</p><p class="mt-2 text-xs text-slate-600">{{ $row->purpose }}</p><a class="mt-2 inline-flex text-xs font-bold text-amber-800 hover:underline" href="{{ route('erp.claims.receipt',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}">Download private receipt</a></div><div class="space-y-2"><span class="inline-flex rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">{{ ucfirst($row->status) }}</span>
@if($row->status === 'paid')
<p class="mt-1 text-xs font-bold text-emerald-900">Paid via Payroll #{{ $row->paid_payroll_run_id }}</p>
@elseif($row->reserved_payroll_run_id)
<p class="mt-1 text-xs font-bold text-amber-900">Reserved in Payroll #{{ $row->reserved_payroll_run_id }} — not yet paid</p>
@elseif($row->status === 'approved')
<p class="mt-1 text-xs font-bold text-amber-900">Approved — awaiting Payroll settlement</p>
@endif
@if(in_array($actor->role,['partner','manager']) && ($row->status==='pending' || ($actor->role==='partner' && $row->status==='manager_reviewed')))
<form data-corporate-confirm="Confirm this expense-claim review? Approved amounts may flow into a future payroll after final Partner approval." action="{{ route('erp.claims.decide',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" method="POST" class="flex flex-wrap gap-2">@csrf
<label class="text-xs font-bold text-slate-600">Reviewed amount (৳)
<input type="number" name="approved_amount" min="0.01" max="{{ $row->amount }}" step="0.01" value="{{ $actor->role==='partner' ? ($row->manager_recommended_amount ?? $row->amount) : $row->amount }}" class="mt-1 w-36 rounded-lg border border-slate-300 p-2 text-xs"></label>
<input type="text" name="decision_note" maxlength="1000" placeholder="Review note" class="min-w-44 flex-1 rounded-lg border border-slate-300 p-2 text-xs">
<button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">{{ $actor->role==='manager' ? 'Review' : 'Approve' }}</button><button name="decision" value="reject" class="rounded-lg bg-rose-700 px-3 py-2 text-xs font-bold text-white">Reject</button></form>
@endif
@if($actor->role === 'student' && in_array($row->status,['pending','manager_reviewed']) && !$row->reserved_payroll_run_id && !$row->paid_payroll_run_id)
<form method="POST" action="{{ route('erp.claims.withdraw',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="mt-2">@csrf
<button class="rounded-lg bg-slate-100 px-3 py-2 text-xs font-bold text-slate-800">Withdraw Unapproved Claim</button>
</form>
@endif
</div></article>@empty<p class="p-8 text-sm text-slate-500">No claims found.</p>@endforelse</div></section>
@endsection