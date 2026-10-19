<section class="erp-month-calendar" aria-label="Workforce leave and attendance calendar" style="--erp-cal-opacity:{{ number_format((int) ($workspace->calendar_watermark_opacity ?? 6) / 100, 2, '.', '') }}">
    <header class="erp-month-calendar__head">
        <div>
            <p class="erp-month-calendar__eyebrow">✦ WORKFORCE INTELLIGENCE</p>
            <h3 class="erp-month-calendar__title">{{ $monthCalendar['month'] }}</h3>
            <p class="erp-month-calendar__subtitle">{{ $monthCalendar['is_personal'] ? 'Your attendance · approved leave · holidays' : 'Approved workspace schedule · team overview' }} · {{ $monthCalendar['timezone'] }}</p>
        </div>
        <div class="erp-month-calendar__badge"><i data-lucide="calendar-days" aria-hidden="true"></i><span>MONTH VIEW</span></div>
    </header>
    <div class="erp-month-calendar__surface">
        @php($calendarBrand = \App\Support\BrandIdentity::profile($company,$workspace))
@if(($workspace->calendar_watermark_mode ?? 'name') !== 'off')
        <div class="erp-month-calendar__watermark" aria-hidden="true">
            @if(($workspace->calendar_watermark_mode ?? 'name') === 'logo' && $calendarBrand->logo_asset_id)
                <img alt="" src="{{ route('erp.brand.asset',['firmSlug'=>$firmSlug,'id'=>$calendarBrand->logo_asset_id]) }}">
            @elseif(($workspace->calendar_watermark_mode ?? 'name') === 'logo' && $company->brand_logo_asset)
                <img alt="" src="{{ asset($company->brand_logo_asset) }}">
            @else
                <span>{{ $calendarBrand->public_name }}</span>
            @endif
        </div>
        @endif
        <div class="erp-month-calendar__weekdays" aria-hidden="true">
            @foreach(['SUN','MON','TUE','WED','THU','FRI','SAT'] as $dayName)
                <span>{{ $dayName }}</span>
            @endforeach
        </div>
        <div class="erp-month-calendar__grid">
            @for($i=0;$i<$monthCalendar['leading_blanks'];$i++)
                <span class="erp-month-calendar__empty" aria-hidden="true"></span>
            @endfor
            @foreach($monthCalendar['days'] as $day)
                <button type="button" class="erp-month-calendar__day erp-month-calendar__day--{{ $day['stage'] }} {{ $day['today'] ? 'erp-month-calendar__day--today' : '' }}"
                    data-calendar-day
                    data-date="{{ $day['date'] }}"
                    data-title="{{ $day['label'] }}"
                    data-detail="{{ $day['details'] }}"
                    data-leave-template="calendar-leave-detail-{{ $monthCalendar['month_key'] }}-{{ $loop->index }}"
                    aria-label="{{ $day['date'] }}: {{ $day['label'] }}. {{ $day['details'] }}">
                    <span class="erp-month-calendar__number">{{ $day['number'] }}</span>
                    <span class="erp-month-calendar__day-title">{{ $day['label'] }}</span>
                    <span class="erp-month-calendar__indicator" aria-hidden="true"></span>
                </button>
                <template id="calendar-leave-detail-{{ $monthCalendar['month_key'] }}-{{ $loop->index }}">
                    @if(count($day['leave_items']))
                        <div class="erp-calendar-dialog__leave-heading">
                            <i data-lucide="calendar-check" aria-hidden="true"></i>
                            <strong>{{ $monthCalendar['is_personal'] && $actor->role === 'student' ? 'My leave applications' : 'Team leave requests' }}</strong>
                            <span>{{ count($day['leave_items']) }}</span>
                        </div>
                        @foreach($day['leave_items'] as $leave)
                            <article class="erp-calendar-dialog__leave-row">
                                @if($leave['member_name'])
                                  <strong class="erp-calendar-dialog__member">{{ $leave['member_name'] }}</strong>
                                @endif
                                <div class="erp-calendar-dialog__leave-meta">
                                  <span>{{ ucfirst(str_replace('_',' ', $leave['leave_type'])) }} · {{ ucfirst($leave['day_portion']) }} day</span>
                                  <span class="erp-calendar-dialog__status-pill erp-calendar-dialog__status-pill--{{ $leave['status'] }}">
                                    {{ $leave['status'] === 'approved' ? 'Approved' : ($leave['status'] === 'manager_reviewed' ? 'Manager reviewed · Partner pending' : 'Pending review') }}
                                  </span>
                                </div>
                                @if($leave['reviewer_name'])
                                  <p class="erp-calendar-dialog__reviewer">{{ $leave['status'] === 'approved' ? 'Final approval by' : 'Last reviewed by' }}: {{ $leave['reviewer_name'] }}</p>
                                @endif
                                @if($leave['status'] === 'approved')
                                  <a class="erp-calendar-dialog__leave-link" href="{{ route('erp.official.leave.print',['firmSlug'=>$firmSlug,'id'=>$leave['id']]) }}">View approved letter <span aria-hidden="true">↗</span></a>
                                @endif
                            </article>
                        @endforeach
                    @else
                        <p class="erp-calendar-dialog__no-leaves">No leave requests for this date in your authorized view.</p>
                    @endif
                </template>
            @endforeach
        </div>
        <p class="erp-month-calendar__disclaimer"><i data-lucide="shield-check" aria-hidden="true"></i> Missing attendance is flagged for review, never assumed absent or penalized automatically.</p>
    </div>
    <div class="flex items-center justify-between gap-2 border-t border-white/10 px-5 py-3 text-xs">
  <span class="font-semibold text-slate-300">Select any date to see details</span>
  <a href="{{ route('erp.calendar.index',['firmSlug'=>$firmSlug,'month'=>$monthCalendar['month_key']]) }}" class="rounded-lg border border-amber-400/40 px-3 py-2 font-extrabold text-[#FCDF02] hover:bg-white/10">Explore all months ↗</a>
</div>
<footer class="erp-month-calendar__legend" aria-label="Calendar status legend">
        @foreach([
            ['On time','on_time'],['Grace late','grace'],['Late','late'],['Leave','leave'],['Team leave','team_leave'],['Team pending','team_pending'],['Holiday','holiday'],['Needs review','review']
        ] as $legend)
            <span><i class="erp-month-calendar__legend-dot erp-month-calendar__legend-dot--{{ $legend[1] }}" aria-hidden="true"></i>{{ $legend[0] }}</span>
        @endforeach
        @if(in_array($actor->role,['partner','manager'],true))
        <a href="{{ route('erp.annual_holidays.index',['firmSlug'=>$firmSlug]) }}" class="erp-month-calendar__manage">Annual Holiday Planner ↗</a>
        @endif
    </footer>
</section>

<dialog class="erp-calendar-dialog" data-calendar-dialog aria-labelledby="erp-calendar-dialog-heading">
    <div class="erp-calendar-dialog__top"><span>✦ CALENDAR DETAILS</span><button type="button" data-calendar-close aria-label="Close calendar details">×</button></div>
    <h3 id="erp-calendar-dialog-heading" data-calendar-modal-date></h3>
    <p class="erp-calendar-dialog__status" data-calendar-modal-status></p>
    <p class="erp-calendar-dialog__details" data-calendar-modal-detail></p>
    <div class="erp-calendar-dialog__leaves" data-calendar-modal-leaves aria-live="polite"></div>
    <button type="button" class="erp-calendar-dialog__done" data-calendar-close>Done</button>
</dialog>
