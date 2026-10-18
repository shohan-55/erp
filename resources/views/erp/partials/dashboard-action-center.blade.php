{{-- Phase 04: all links are real authorized operations, never placeholder buttons. --}}
@php
$roleCopy = match ($actor->role) {
    'partner' => ['label' => 'PARTNER CONTROL CENTER', 'heading' => 'Your practice. Your decisions.', 'detail' => 'Everything below belongs exclusively to your Partner workspace. Review outstanding requests, manage student access, and prepare payroll without viewing another Partner’s records.', 'icon' => 'briefcase-business'],
    'manager' => ['label' => 'MANAGER WORKSPACE', 'heading' => 'Your team, at a glance.', 'detail' => 'Review requests from your assigned students only. Financial payroll approvals remain Partner-controlled.', 'icon' => 'clipboard-check'],
    default => ['label' => 'STUDENT WORKSPACE', 'heading' => 'Your workday, simplified.', 'detail' => 'Record attendance, submit requests, and find your personal records from one place. Available actions follow your Partner’s permissions.', 'icon' => 'graduation-cap'],
};
@endphp
<section class="overflow-hidden rounded-2xl bg-[#17191F] text-white shadow-sm" aria-label="{{ ucfirst($actor->role) }} operations overview">
<div class="grid gap-4 p-5 sm:p-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
<div class="min-w-0">
<p class="text-[11px] font-extrabold uppercase tracking-[.18em] text-[#FCDF02]">{{ $roleCopy['label'] }}</p>
<h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">{{ $roleCopy['heading'] }}</h2>
<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-300">{{ $roleCopy['detail'] }}</p>
<p class="mt-4 inline-flex max-w-full flex-wrap items-center gap-2 rounded-lg border border-white/15 px-3 py-2 text-xs font-bold text-slate-100">
<i data-lucide="{{ $roleCopy['icon'] }}" class="h-4 w-4 text-[#FCDF02]"></i>
{{ $company->brand_name ?: $company->name }} <span aria-hidden="true" class="text-slate-500">/</span> {{ $workspace->name }}
</p>
</div>
@if($actor->role !== 'student')
<div class="grid grid-cols-2 gap-3 lg:w-[275px]">
<div class="rounded-xl border border-white/10 bg-white/10 p-4"><p class="text-xs font-semibold text-slate-300">Leave to review</p><p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $queues['leave'] }}</p></div>
<div class="rounded-xl border border-white/10 bg-white/10 p-4"><p class="text-xs font-semibold text-slate-300">Claims to review</p><p class="mt-2 text-3xl font-extrabold tabular-nums">{{ $queues['claims'] }}</p></div>
</div>
@else
<div class="flex items-center gap-3 rounded-xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-bold text-slate-100">
<i data-lucide="lock-keyhole" class="h-5 w-5 text-[#FCDF02]"></i> Your private records only
</div>
@endif
</div>
</section>

<section class="space-y-4 erp-action-finder" aria-label="Quick management actions"
         data-corporate-action-center data-scope="{{ $company->id }}-{{ $workspace->id }}-{{ $actor->id }}">
  <div class="flex flex-wrap items-end justify-between gap-2">
    <div><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">YOUR WORKDESK</p>
      <h3 class="mt-1 text-lg font-extrabold">Find an action in seconds.</h3>
      <p class="mt-1 text-xs leading-relaxed text-slate-500">Search, filter or pin up to eight shortcuts for this workspace. Every action respects your role permissions.</p>
    </div>
    <span class="erp-action-finder__count" data-corporate-action-count aria-live="polite"></span>
  </div>
  <div class="erp-action-finder__controls">
    <label class="sr-only" for="erp-dashboard-action-search">Search your available actions</label>
    <input id="erp-dashboard-action-search" type="search" class="erp-action-finder__search"
       autocomplete="off" placeholder="Search attendance, leave, claims, payroll…" data-corporate-action-search>
    <div class="erp-action-finder__filters" role="group" aria-label="Filter shortcuts">
      @foreach(['all'=>'All','pinned'=>'Pinned','daily'=>'Daily work','approvals'=>'Approvals','policies'=>'Policies','reports'=>'Reports','other'=>'More'] as $filterValue=>$filterName)
        <button type="button" class="erp-action-finder__filter" data-corporate-filter="{{ $filterValue }}"
          aria-pressed="{{ $filterValue==='all' ? 'true':'false' }}">{{ $filterName }}</button>
      @endforeach
    </div>
  </div>
  <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4" data-corporate-grid>
  @foreach($quickActions as $action)
    @php
    $category = match (true) {
      in_array($action['route'],[
        'erp.attendance','erp.leaves','erp.claims','erp.engagements','erp.timesheets',
        'erp.notifications','erp.staff.my_records'
      ],true) && $actor->role === 'student' => 'daily',
      in_array($action['route'],[
        'erp.leaves','erp.claims','erp.attendance.reviews'
      ],true) && $actor->role !== 'student' => 'approvals',
      in_array($action['route'],[
        'erp.policies','erp.hr_policies.index','erp.annual_holidays.index','erp.permissions.index'
      ],true) => 'policies',
      in_array($action['route'],[
        'erp.attendance.report','erp.reports.practice','erp.payroll'
      ],true) => 'reports',
      in_array($action['route'],[
        'erp.attendance','erp.leaves','erp.claims','erp.engagements','erp.timesheets'
      ],true) => 'daily',
      default => 'other',
    };
    @endphp
    <article class="erp-action-finder__card"
       data-corporate-action
       data-action-id="{{ $action['route'] }}"
       data-action-title="{{ $action['title'] }}"
       data-search="{{ $action['title'] }} {{ $action['description'] }}"
       data-action-category="{{ $category }}">
      <button type="button" class="erp-action-finder__pin"
        data-corporate-pin data-pinned="false" aria-pressed="false"
        aria-label="Pin {{ $action['title'] }}" title="Pin or unpin this action">
        <i data-lucide="star" aria-hidden="true"></i>
      </button>
      <a class="erp-action-finder__link" href="{{ route($action['route'], ['firmSlug'=>$firmSlug]) }}">
        <div class="flex items-start justify-between gap-3">
          <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-900">
            <i data-lucide="{{ $action['icon'] }}" class="h-5 w-5" aria-hidden="true"></i>
          </span>
          @if($action['count'] !== null)
            <span class="mr-9 rounded-full px-2 py-1 text-[10px] font-extrabold {{ $action['count']>0 ? 'bg-amber-100 text-amber-950' : 'bg-slate-100 text-slate-600' }}">{{ $action['count'] }} open</span>
          @endif
        </div>
        <div><h4 class="text-sm font-extrabold text-slate-900">{{ $action['title'] }}</h4>
          <p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $action['description'] }}</p></div>
      </a>
    </article>
  @endforeach
  </div>
  <p class="erp-action-finder__empty" data-corporate-action-empty hidden>No matching action. Try a different search or choose All.</p>
</section>
