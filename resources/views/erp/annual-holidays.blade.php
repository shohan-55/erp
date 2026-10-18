@extends('layouts.erp')
@section('title','Annual Holiday Planner')
@section('content')
<section class="overflow-hidden rounded-3xl border border-amber-300/70 bg-[#17191F] p-6 text-white shadow-xl sm:p-8">
    <p class="text-xs font-extrabold tracking-[.2em] text-[#FCDF02]">✦ WORKFORCE CALENDAR · ANNUAL PLANNING</p>
    <h2 class="mt-3 text-2xl font-black tracking-tight sm:text-3xl">Annual Holiday Planner</h2>
    <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300">Plan a full year of Bangladesh government, optional and firm-specific holidays. Import a prepared CSV once, review the dates and publish them only with Partner authorization. Approved dates integrate with Attendance, Leave and your premium Dashboard Calendar.</p>
    <div class="mt-5 flex flex-wrap gap-2 text-xs font-bold"><span class="rounded-full border border-white/20 px-3 py-2">{{ $workspace->name }}</span><span class="rounded-full border border-[#FCDF02]/40 px-3 py-2 text-[#FCDF02]">{{ ucfirst($actor->role) }} access</span><span class="rounded-full border border-white/20 px-3 py-2">{{ $company->timezone }}</span></div>
</section>
@include('erp.partials.module-guide',['module'=>'calendar'])

<div class="grid gap-5 xl:grid-cols-[minmax(0,1.45fr)_minmax(260px,1fr)]">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h3 class="text-lg font-extrabold">Import a Year's Holiday Dates</h3>
    <p class="mt-2 text-xs leading-relaxed text-slate-600">CSV rows: <code>YYYY-MM-DD,"Holiday name",government</code>. Allowed categories: <code>government</code>, <code>optional</code>, <code>partner_decision</code>. The first row may be <code>date,title,category</code>. Maximum 366 unique dates. Only future dates can be introduced; approved history is never silently rewritten.</p>
    <form method="POST" action="{{ route('erp.annual_holidays.submit',['firmSlug'=>$firmSlug]) }}" class="mt-5 space-y-4">
        @csrf
        <label class="block text-xs font-bold text-slate-700">Calendar year
            <input name="year" type="number" min="{{ $currentYear }}" max="{{ min($currentYear+5,2100) }}" value="{{ old('year',max($year,$currentYear)) }}" required class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
        </label>
        <label class="block text-xs font-bold text-slate-700">Upload a CSV file (read locally; no file stored)
            <input type="file" accept=".csv,text/csv,text/plain" data-holiday-csv-file class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
        </label>
        <label class="block text-xs font-bold text-slate-700">Annual holiday CSV
            <textarea name="holidays_csv" data-holiday-csv-content required rows="9" maxlength="60000" placeholder="date,title,category&#10;{{ $year <= $currentYear ? now($company->timezone)->addDays(14)->toDateString() : $year.'-01-01' }},Example holiday,partner_decision" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 font-mono text-xs">{{ old('holidays_csv') }}</textarea>
        </label>
        <p data-holiday-csv-count class="text-xs text-slate-500">Paste dates or choose a file above. Uploaded contents can be reviewed before submission.</p>
        <label class="block text-xs font-bold text-slate-700">Official source / authorization note
            <textarea name="source_note" required rows="3" minlength="12" maxlength="1200" placeholder="Identify the government circular/gazette, publication date, or your firm's authorized source. Government labels are not automatically verified." class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">{{ old('source_note') }}</textarea>
        </label>
        <p class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900"><strong>Important:</strong> Importing a government holiday list does not verify it against an official gazette. The firm must check applicable national/optional holiday dates (including moon-sighting changes). This annual submission is a complete replacement snapshot for future dates in that year; explicit approved one-day decisions remain higher priority.</p>
        <button class="min-h-12 w-full rounded-xl bg-gradient-to-r from-[#FCDF02] to-amber-400 px-6 py-3 text-sm font-extrabold text-[#17191F] shadow-md">{{ $actor->role === 'partner' ? 'Approve & Publish Annual Plan' : 'Submit Annual Plan for Partner Approval' }} →</button>
    </form>
</section>

<aside class="space-y-5">
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between gap-2">
            <h3 class="text-base font-extrabold">Approved Holiday Register</h3>
            <form method="GET" action="{{ route('erp.annual_holidays.index',['firmSlug'=>$firmSlug]) }}"><label class="sr-only" for="holiday-year-filter">Year</label><input id="holiday-year-filter" name="year" type="number" min="2000" max="2100" value="{{ $year }}" class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-sm"><button class="ml-1 rounded-lg bg-[#17191F] px-2 py-1 text-xs font-bold text-[#FCDF02]">View</button></form>
        </div>
        <p class="mt-2 text-xs text-slate-500">{{ count($approvedDays) }} holiday dates in the most recent approved annual snapshot for {{ $year }}. One-day exceptions and approved staff shifts take priority.</p>
        <div class="mt-4 max-h-80 space-y-2 overflow-y-auto">
        @forelse($approvedDays as $date=>$holiday)
            <div class="flex items-start gap-3 rounded-xl bg-slate-50 p-3"><span class="whitespace-nowrap text-xs font-extrabold text-amber-800">{{ $date }}</span><span class="text-xs text-slate-700"><strong>{{ $holiday['title'] }}</strong><span class="mt-1 block text-slate-500">{{ ucfirst(str_replace('_',' ',$holiday['category'])) }}</span></span></div>
        @empty
            <p class="rounded-xl bg-slate-50 p-4 text-xs text-slate-500">No approved annual plan for this year. Existing weekly and individual holiday decisions still work.</p>
        @endforelse
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <h3 class="text-base font-extrabold">Company Watermark</h3>
        <p class="mt-2 text-xs leading-relaxed text-slate-600">Brand the Monthly Calendar with your actual company name or logo. Opacity remains subtle so dates and attendance statuses are readable.</p>
        @if($actor->role === 'partner')
        <form method="POST" action="{{ route('erp.annual_holidays.branding',['firmSlug'=>$firmSlug]) }}" class="mt-4 space-y-3">
            @csrf
            <label class="block text-xs font-bold text-slate-700">Watermark type
                <select name="mode" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
                    <option value="name" @selected(($workspace->calendar_watermark_mode ?? 'name')==='name')>Company Name</option>
                    @if($company->brand_logo_asset)<option value="logo" @selected($workspace->calendar_watermark_mode==='logo')>Company Logo</option>@endif
                    <option value="off" @selected(($workspace->calendar_watermark_mode ?? 'name')==='off')>Off</option>
                </select>
            </label>
            <label class="block text-xs font-bold text-slate-700">Opacity (%)
                <input type="number" name="opacity" required min="3" max="12" value="{{ $workspace->calendar_watermark_opacity ?? 6 }}" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm">
            </label>
            <button class="w-full rounded-xl bg-[#17191F] px-4 py-3 text-sm font-bold text-[#FCDF02]">Save Watermark Settings</button>
        </form>
        @else
            <p class="mt-3 rounded-lg bg-slate-50 p-3 text-xs font-semibold text-slate-600">Only the Partner can change workspace branding.</p>
        @endif
    </section>
</aside>
</div>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <h3 class="text-lg font-extrabold">Annual Plan Versions & Approval Queue</h3>
    <p class="mt-2 text-xs text-slate-600">Append-only decisions. A rejected proposal does not change any holiday. A newer approved plan replaces the previous annual snapshot only prospectively.</p>
    <div class="mt-4 grid gap-3 md:grid-cols-2">
        @forelse($plans as $plan)
        <article class="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div class="flex flex-wrap items-center justify-between gap-2"><strong class="text-sm">Plan #{{ $plan->id }} · {{ $plan->calendar_year }}</strong><span class="rounded-full px-3 py-1 text-xs font-bold {{ $plan->status==='approved' ? 'bg-emerald-100 text-emerald-800' : ($plan->status==='pending' ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800') }}">{{ ucfirst($plan->status) }}</span></div>
            <p class="mt-2 text-xs text-slate-600">By {{ $plan->submitter_name }} · {{ count(json_decode($plan->holiday_dates,true)) }} holidays</p>
            <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $plan->source_note }}</p>
            @if($actor->role==='partner' && $plan->status==='pending')
            <div class="mt-3 flex gap-2">
                <form data-corporate-confirm="Approve or reject this annual holiday plan? Approved future dates affect leave and work schedules across this workspace." method="POST" action="{{ route('erp.annual_holidays.decide',['firmSlug'=>$firmSlug,'planId'=>$plan->id]) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="rounded-lg bg-emerald-700 px-4 py-2 text-xs font-bold text-white">Approve</button></form>
                <form data-corporate-confirm="Approve or reject this annual holiday plan? Approved future dates affect leave and work schedules across this workspace." method="POST" action="{{ route('erp.annual_holidays.decide',['firmSlug'=>$firmSlug,'planId'=>$plan->id]) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="rounded-lg bg-rose-700 px-4 py-2 text-xs font-bold text-white">Reject</button></form>
            </div>
            @endif
        </article>
        @empty
            <p class="text-sm text-slate-500">No annual plan versions yet.</p>
        @endforelse
    </div>
</section>
@push('scripts')
<script>
(() => {
 const input=document.querySelector('[data-holiday-csv-file]');
 const box=document.querySelector('[data-holiday-csv-content]');
 const count=document.querySelector('[data-holiday-csv-count]');
 if(!input||!box||!count)return;
 const refresh=()=>{const n=box.value.trim().split(/\r?\n/).filter(x=>x.trim()&&!/^date,title,category/i.test(x)).length;count.textContent=n+' holiday rows in draft (verify source and year before submitting).';};
 input.addEventListener('change',async()=>{const file=input.files?.[0];if(!file)return;if(file.size>60000){count.textContent='CSV exceeds 60KB; choose a smaller file.';return;}box.value=await file.text();refresh();});
 box.addEventListener('input',refresh);
})();
</script>
@endpush
@endsection
