@extends('layouts.erp')
@section('title','My Official Certificates')
@section('content')
<section class="rounded-3xl bg-[#17191F] p-6 text-white shadow-lg sm:p-8">
 <p class="text-xs font-black uppercase tracking-widest text-[#FCDF02]">✦ VERIFIED CORPORATE DOCUMENTS</p>
 <h2 class="mt-2 text-2xl font-black">Official Certificate Library</h2>
 <p class="mt-2 max-w-3xl text-sm text-slate-300">Only documents finalized by an authorized Partner appear here. Each PDF-ready A4 letter and true Word document uses an immutable, auditable issued snapshot. Salary certificates require an existing locked payroll period.</p>
 <div class="mt-4 flex flex-wrap gap-2">
 @if($actor->role==='partner')
 <a href="{{ route('erp.staff',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold text-[#FCDF02]">Issue from Staff Management ↗</a>
 <a href="{{ route('erp.official_templates.index',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold text-[#FCDF02]">Edit Official Templates ↗</a>
 @endif
 <a href="{{ route('erp.leaves',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2 text-xs font-bold text-[#FCDF02]">Approved Leave Letters ↗</a>
 </div>
</section>
<section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
 <div class="border-b border-slate-100 p-5">
 <h3 class="text-lg font-extrabold">{{ $actor->role==='partner'?'Issued Workspace Certificates':'My Authorized Certificates' }}</h3>
 <p class="mt-2 text-xs text-slate-500">Printable PDF via your browser. Word download opens as an editable copy, not a newly approved original. The immutable registered issue remains the source of truth.</p>
 </div>
 <div class="divide-y divide-slate-100">
 @forelse($rows as $row)
 <article class="flex flex-wrap items-start justify-between gap-3 p-5">
 <div>
 <p class="text-sm font-extrabold">{{ str_starts_with($row->document_type,'salary_')?'Salary / Allowance Certificate':'Employment / Articleship Certificate' }}</p>
 <p class="mt-1 text-xs text-slate-600">{{ $row->employee_name }} · {{ $row->reference }} · {{ $row->issued_at }}</p>
 <p class="mt-1 text-xs font-semibold text-emerald-700">Partner-issued · Immutable record</p>
 </div>
 <div class="flex flex-wrap gap-2">
 <a href="{{ route('erp.staff_certificates.print',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950">Print / Save PDF</a>
 <a href="{{ route('erp.staff_certificates.word',['firmSlug'=>$firmSlug,'id'=>$row->id]) }}" class="rounded-xl bg-[#17191F] px-4 py-3 text-xs font-bold text-[#FCDF02]">Download Word</a>
 </div>
 </article>
 @empty
 <p class="p-7 text-sm text-slate-500">No employment or salary certificates have been issued for your authorized membership. Partners may issue one from Staff Management.</p>
 @endforelse
 </div>
</section>
@endsection
