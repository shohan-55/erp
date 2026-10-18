@extends('layouts.erp')
@section('title','Workforce Calendar')
@section('content')
@push('styles')
<style id="erp-month-calendar-style">{!! file_get_contents(public_path('css/erp-month-calendar.css')) !!}</style>
@endpush
@push('scripts')
<script id="erp-month-calendar-script">{!! file_get_contents(public_path('js/erp-month-calendar.js')) !!}</script>
@endpush
<section class="overflow-hidden rounded-2xl bg-[#17191F] p-5 text-white sm:p-7">
  <p class="text-[11px] font-black uppercase tracking-widest text-[#FCDF02]">✦ WORKFORCE CALENDAR CENTER</p>
  <h2 class="mt-2 text-2xl font-black sm:text-3xl">Your schedule. Your record.</h2>
  <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-300">Explore approved holiday decisions, historical attendance, leave records and future workdays in one private calendar. Every date opens its details without changing any record.</p>
  <div class="mt-5 flex flex-wrap items-center gap-3">
    <a href="{{ route('erp.dashboard',['firmSlug'=>$firmSlug]) }}" class="rounded-xl border border-white/20 px-4 py-2.5 text-xs font-bold text-white hover:bg-white/10">← Dashboard</a>
    <a href="{{ route('erp.calendar.export',['firmSlug'=>$firmSlug,'month'=>$monthCalendar['month_key']]) }}" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F]">Download private CSV ↓</a>
  </div>
</section>
<nav class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm" aria-label="Navigate calendar months">
  <a href="{{ route('erp.calendar.index',['firmSlug'=>$firmSlug,'month'=>$monthCalendar['previous_month']]) }}" class="min-h-11 rounded-xl border border-slate-300 px-4 py-3 text-xs font-bold text-slate-700 hover:bg-amber-50">← Previous</a>
  <div class="min-w-0 text-center">
    <p class="text-xs font-bold uppercase tracking-widest text-amber-800">Selected month</p>
    <p class="mt-1 text-lg font-black">{{ $monthCalendar['month'] }}</p>
  </div>
  <a href="{{ route('erp.calendar.index',['firmSlug'=>$firmSlug,'month'=>$monthCalendar['next_month']]) }}" class="min-h-11 rounded-xl border border-slate-300 px-4 py-3 text-xs font-bold text-slate-700 hover:bg-amber-50">Next →</a>
  <form method="GET" action="{{ route('erp.calendar.index',['firmSlug'=>$firmSlug]) }}" class="flex w-full flex-wrap items-end justify-center gap-2 border-t border-slate-100 pt-3">
    <label class="text-xs font-bold text-slate-700">Jump to month
      <input type="month" name="month" required value="{{ $monthCalendar['month_key'] }}" class="mt-1 block min-h-11 rounded-xl border border-slate-300 px-3 py-2 text-sm">
    </label>
    <button class="min-h-11 rounded-xl bg-[#17191F] px-5 py-2.5 text-xs font-bold text-[#FCDF02]">Go</button>
    <a href="{{ route('erp.calendar.index',['firmSlug'=>$firmSlug]) }}" class="min-h-11 rounded-xl border border-amber-300 px-4 py-3 text-xs font-bold text-amber-900">This month</a>
  </form>
</nav>
@include('erp.partials.dashboard-month-calendar')
<section class="rounded-2xl border border-slate-200 bg-white p-5 text-xs leading-relaxed text-slate-600">
  <h3 class="font-extrabold text-slate-900">Record integrity</h3>
  <p class="mt-2">Approved shifts, Partner holidays and individual leave are displayed from authorized records. Missing check-ins are provisional review items—not confirmed absence. Exported information is limited to your role and Partner workspace. No leave or salary adjustments are made by exploring or exporting this calendar.</p>
</section>
@endsection
