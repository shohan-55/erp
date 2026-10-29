@php
    $approvedConveyanceSum = array_sum(array_map(fn($c) => ($c['status'] ?? '') === 'approved' ? (float) ($c['amount'] ?? 0) : 0, $claimQueue));
    $myPayrollRow = $payrollRows[0] ?? [
        'base_allowance'      => (float) (auth()->user()?->monthly_allowance ?? 0),
        'approved_conveyance' => $approvedConveyanceSum,
        'adjustment'          => 0,
        'net_payable'         => (float) (auth()->user()?->monthly_allowance ?? 0) + $approvedConveyanceSum,
    ];
    $onTimeRowsCount = count(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Right Time', 'On time'], true)));
    $lateRowsCount = count(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Late', 'Late Present'], true)));
    $latestUnreadNote = collect($notificationsList ?? [])->first(fn($n) => !in_array($n['id'] ?? '', $readNotificationIds ?? [], true));
    $nextHolidayItem = collect($holidaysList ?? [])->first(fn($h) => ($h['holiday_date'] ?? '') >= now()->toDateString());
@endphp

{{-- ===================================================================== --}}
{{-- 1. LIVE ANNOUNCEMENT & POLICY TICKER BAR (#Proposal6)                 --}}
{{-- ===================================================================== --}}
<div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200/90 bg-[#17191F] px-4 py-2.5 text-xs text-white shadow-sm">
    <div class="flex flex-wrap items-center gap-2.5 min-w-0">
        <span class="inline-flex items-center gap-1.5 rounded-lg bg-[#FCDF02] px-2.5 py-1 text-[10px] font-extrabold uppercase tracking-wider text-[#17191F]">
            <i data-lucide="radio" class="h-3 w-3"></i> Live Policy Radar
        </span>
        <span class="font-semibold text-slate-200">
            Office: <strong class="text-emerald-400">&le; {{ $officeStart }} (Right Time)</strong>
            <span class="mx-1 text-slate-600">|</span>
            Grace: <strong class="text-amber-300">{{ $officeStart }}–{{ $graceEndFormatted ?? '09:40' }} (Late)</strong>
            <span class="mx-1 text-slate-600">|</span>
            Post-Grace: <strong class="text-rose-400">&gt; {{ $graceEndFormatted ?? '09:40' }} (Late Present)</strong>
            @if($latePenaltyEnabled)
                <span class="mx-1 text-slate-600">|</span>
                <span class="rounded bg-rose-500/20 px-1.5 py-0.5 text-[10px] font-bold text-rose-300">{{ $lateDaysPerDeduction }} Days Late = {{ $deductionDaysCount }} Day Salary Deduction</span>
            @endif
        </span>
    </div>
    <div class="flex items-center gap-3 text-[11px] text-slate-300">
        @if($nextHolidayItem)
            <span class="inline-flex items-center gap-1 rounded-full bg-white/10 px-2.5 py-0.5 font-semibold text-amber-200">
                <i data-lucide="calendar-heart" class="h-3 w-3"></i>
                Next Holiday: {{ $nextHolidayItem['title'] }} ({{ $nextHolidayItem['date_formatted'] ?? $nextHolidayItem['holiday_date'] }})
                @if(!empty($nextHolidayItem['is_office_open_override']))
                    <strong class="text-rose-300">[OFFICE OPEN]</strong>
                @endif
            </span>
        @elseif($latestUnreadNote)
            <button type="button" wire:click="setSection('notifications')" class="inline-flex items-center gap-1 hover:underline text-amber-300 font-semibold">
                <i data-lucide="bell-ring" class="h-3 w-3"></i> {{ $latestUnreadNote['title'] }}
            </button>
        @endif
    </div>
</div>

{{-- ===================================================================== --}}
{{-- 2. HERO HEADER & PRIMARY ACTIONS                                      --}}
{{-- ===================================================================== --}}
<section class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
    <div>
        <div class="flex items-center gap-2">
            <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">{{ $previewRole === 'student' ? 'MY WORKSPACE' : ($previewRole === 'manager' ? 'TEAM OPERATIONS' : 'PARTNER PRACTICE') }}</p>
            <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-900">{{ $partnerUnit }}</span>
        </div>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#20232A] sm:text-3xl">
            @if($previewRole === 'partner') Your practice, in focus.
            @elseif($previewRole === 'manager') Your team, aligned.
            @else Your day, organized.
            @endif
        </h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
            @if($previewRole === 'partner') Centralized practice management, live field radar, monthly activity calendar, and compliance governance for {{ $partnerUnit }}.
            @elseif($previewRole === 'manager') Team supervision, audit assignments, and real-time attendance &amp; conveyance workflows under {{ $partnerUnit }}.
            @else Real-time GPS attendance check-in, client audit conveyance claims, and ICAB study leave tracking.
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setSection('attendance')" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#16181D] transition hover:bg-[#F3D604]">
            <i data-lucide="calendar-check-2" class="h-4 w-4"></i>{{ $previewRole === 'student' ? 'Open Attendance Register' : 'Full Attendance Register' }}
        </button>
        <button type="button" wire:click="setSection('notifications')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <i data-lucide="bell" class="h-4 w-4"></i> Updates
        </button>
    </div>
</section>

{{-- ===================================================================== --}}
{{-- 3. KPI METRICS GRID                                                   --}}
{{-- ===================================================================== --}}
<section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Practice performance metrics">
    @php
        $myPunctuality = $punctualityLeaderboard[0] ?? ['score' => 100, 'streak' => 0];
        $metricCards = match ($previewRole) {
            'partner' => [
                ['label'=>'Active Students', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>$partnerUnit . ' scope'],
                ['label'=>'Right Time Today', 'value'=> sprintf('%02d', $onTimeRowsCount), 'icon'=>'clock-3', 'note'=>$lateRowsCount . ' Late / Late Present'],
                ['label'=>'Pending Leaves', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'calendar-days', 'note'=>'Queue for approval'],
                ['label'=>'Approved Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>count($claimQueue) . ' total claims'],
            ],
            'manager' => [
                ['label'=>'Assigned Team', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>'Manager supervision'],
                ['label'=>'Right Time Today', 'value'=> sprintf('%02d', $onTimeRowsCount), 'icon'=>'clock-3', 'note'=>$lateRowsCount . ' Late arrivals'],
                ['label'=>'Pending Approvals', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'clipboard-check', 'note'=>'Action required'],
                ['label'=>'Approved Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>'Team monthly total'],
            ],
            default => [
                ['label'=>'My Attendance', 'value'=> sprintf('%02d', $onTimeRowsCount), 'icon'=>'calendar-check-2', 'note'=>'Right time today'],
                ['label'=>'Punctuality Score', 'value'=> ($myPunctuality['score'] ?? 100) . '%', 'icon'=>'flame', 'note'=> ($myPunctuality['streak'] ?? 0) . ' Day Right-Time Streak'],
                ['label'=>'Approved Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>count($claimQueue) . ' claims this month'],
                ['label'=>'Firm Notices', 'value'=>sprintf('%02d', count($notificationsList ?? [])), 'icon'=>'bell', 'note'=>'Practice announcements'],
            ],
        };
    @endphp
    @foreach($metricCards as $metric)
        <div wire:key="metric-{{ $metric['label'] }}" class="min-w-0 rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-start justify-between gap-2">
                <p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="{{ $metric['icon'] }}" class="h-[18px] w-[18px]"></i></div>
            </div>
            <div class="text-2xl font-extrabold tracking-tight text-[#17191F] sm:text-3xl">{{ $metric['value'] }}</div>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">{{ $metric['note'] }}</p>
        </div>
    @endforeach
</section>

{{-- ===================================================================== --}}
{{-- 4. LIVE COLOR-SHIFTING ATTENDANCE GRID & CHECK-IN WIDGET (#UserProp2) --}}
{{--    - Until OfficeStart (e.g. 9:30): Emerald Green ("Right Time")      --}}
{{--    - OfficeStart to GraceEnd (9:30-9:40): Amber Yellow ("Late")       --}}
{{--    - After GraceEnd (9:40+): Soft Red -> Deep Dark Red ("Late Present")--}}
{{-- ===================================================================== --}}
@if($previewRole !== 'student' || ($studentGrids['attendance_clock'] ?? true))
<section x-data="{
        officeStartStr: '{{ $officeStart ?: '09:30' }}',
        graceMins: {{ (int) ($graceMinutes ?? 10) }},
        simMode: 'live',
        nowStr: '{{ now()->timezone('Asia/Dhaka')->format('H:i:s') }}',
        gettingGps: false,
        init() {
            setInterval(() => {
                if (this.simMode === 'live') {
                    const d = new Date();
                    const hh = String(d.getHours()).padStart(2, '0');
                    const mm = String(d.getMinutes()).padStart(2, '0');
                    const ss = String(d.getSeconds()).padStart(2, '0');
                    this.nowStr = hh + ':' + mm + ':' + ss;
                }
            }, 1000);
        },
        toMins(hm) {
            const pts = hm.split(':');
            return (parseInt(pts[0] || 9, 10) * 60) + parseInt(pts[1] || 30, 10);
        },
        currentMins() {
            if (this.simMode === 'green') return this.toMins(this.officeStartStr) - 5;
            if (this.simMode === 'yellow') return this.toMins(this.officeStartStr) + Math.max(1, Math.floor(this.graceMins / 2));
            if (this.simMode === 'light_red') return this.toMins(this.officeStartStr) + this.graceMins + 8;
            if (this.simMode === 'dark_red') return this.toMins(this.officeStartStr) + this.graceMins + 35;
            return this.toMins(this.nowStr);
        },
        phase() {
            const cur = this.currentMins();
            const start = this.toMins(this.officeStartStr);
            const graceEnd = start + this.graceMins;
            if (cur <= start) return 'green';
            if (cur <= graceEnd) return 'yellow';
            if (cur <= graceEnd + 20) return 'light_red';
            return 'dark_red';
        },
        cardStyle() {
            const p = this.phase();
            if (p === 'green') return 'background: linear-gradient(135deg, #059669 0%, #0D9488 50%, #047857 100%); color: #FFFFFF; border-color: #34D399;';
            if (p === 'yellow') return 'background: linear-gradient(135deg, #F59E0B 0%, #FBBF24 50%, #D97706 100%); color: #17191F; border-color: #FDE68A;';
            if (p === 'light_red') return 'background: linear-gradient(135deg, #F43F5E 0%, #E11D48 50%, #BE123C 100%); color: #FFFFFF; border-color: #FDA4AF;';
            return 'background: linear-gradient(135deg, #881337 0%, #7F1D1D 50%, #450A0A 100%); color: #FFFFFF; border-color: #F87171;';
        },
        statusLabel() {
            const p = this.phase();
            if (p === 'green') return 'RIGHT TIME WINDOW (<= ' + this.officeStartStr + ')';
            if (p === 'yellow') return 'GRACE / LATE WINDOW (' + this.officeStartStr + ' – {{ $graceEndFormatted ?? '09:40' }})';
            if (p === 'light_red') return 'LATE PRESENT WINDOW (> {{ $graceEndFormatted ?? '09:40' }} · Soft Alert)';
            return 'CRITICAL LATE PRESENT (> {{ $graceEndFormatted ?? '09:40' }} · Deep Alert)';
        },
        statusSubtext() {
            const p = this.phase();
            if (p === 'green') return 'Checking in now records RIGHT TIME attendance with 100% punctuality credit.';
            if (p === 'yellow') return 'Within ' + this.graceMins + 'm grace window — records as LATE.';
            return 'Past grace cutoff ({{ $graceEndFormatted ?? '09:40' }}) — records as LATE PRESENT ({{ $lateDaysPerDeduction }} late days = {{ $deductionDaysCount }} day salary deduction).';
        },
        triggerQuickCheckin() {
            if (!navigator.geolocation) {
                $wire.togglePreviewSession();
                return;
            }
            this.gettingGps = true;
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.gettingGps = false;
                    $wire.togglePreviewSessionWithGps(pos.coords.latitude, pos.coords.longitude, 'Live GPS (' + pos.coords.latitude.toFixed(4) + ', ' + pos.coords.longitude.toFixed(4) + ')');
                },
                () => {
                    this.gettingGps = false;
                    $wire.togglePreviewSession();
                },
                { enableHighAccuracy: true, timeout: 5000 }
            );
        }
    }"
    :style="cardStyle()"
    class="relative overflow-hidden rounded-3xl border-2 p-5 sm:p-7 shadow-xl transition-all duration-700">

    <div class="relative z-10 flex flex-col justify-between gap-5 lg:flex-row lg:items-center">
        <div class="space-y-2 max-w-2xl">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-black/25 px-3 py-1 text-[11px] font-extrabold uppercase tracking-wider backdrop-blur-sm">
                    <span class="h-2 w-2 rounded-full bg-white animate-ping"></span>
                    <span x-text="statusLabel()"></span>
                </span>
                <span class="rounded-full bg-white/20 px-2.5 py-0.5 text-[11px] font-bold backdrop-blur-sm">
                    Clock: <span class="font-mono font-extrabold" x-text="nowStr"></span>
                </span>
            </div>
            <h3 class="text-xl sm:text-2xl font-black tracking-tight">
                Live Attendance Status &amp; Policy Window
            </h3>
            <p class="text-xs sm:text-sm opacity-90 leading-relaxed font-medium" x-text="statusSubtext()"></p>

            {{-- Interactive Time Window Simulator Pills so Partner/Student can preview Green / Yellow / Light Red / Dark Red anytime --}}
            <div class="pt-2 flex flex-wrap items-center gap-1.5 text-[10px]">
                <span class="font-extrabold uppercase tracking-wider opacity-80 mr-1">Preview Color Shift:</span>
                <button type="button" @click="simMode = 'live'" :class="simMode === 'live' ? 'ring-2 ring-white bg-black/40' : 'bg-black/20 hover:bg-black/30'" class="rounded-lg px-2.5 py-1 font-bold text-white transition">Live Clock</button>
                <button type="button" @click="simMode = 'green'" :class="simMode === 'green' ? 'ring-2 ring-white bg-emerald-900/60' : 'bg-black/20 hover:bg-black/30'" class="rounded-lg px-2.5 py-1 font-bold text-white transition">🟢 &le; {{ $officeStart }} (Green)</button>
                <button type="button" @click="simMode = 'yellow'" :class="simMode === 'yellow' ? 'ring-2 ring-black bg-amber-900/40' : 'bg-black/20 hover:bg-black/30'" class="rounded-lg px-2.5 py-1 font-bold text-white transition">🟡 {{ $officeStart }}–{{ $graceEndFormatted ?? '09:40' }} (Yellow)</button>
                <button type="button" @click="simMode = 'light_red'" :class="simMode === 'light_red' ? 'ring-2 ring-white bg-rose-900/60' : 'bg-black/20 hover:bg-black/30'" class="rounded-lg px-2.5 py-1 font-bold text-white transition">🔴 &gt; {{ $graceEndFormatted ?? '09:40' }} (Soft Red)</button>
                <button type="button" @click="simMode = 'dark_red'" :class="simMode === 'dark_red' ? 'ring-2 ring-white bg-red-950/80' : 'bg-black/20 hover:bg-black/30'" class="rounded-lg px-2.5 py-1 font-bold text-white transition">🩸 Deep Late (Dark Red)</button>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row lg:flex-col items-stretch sm:items-center lg:items-end gap-3 shrink-0">
            @if($previewRole === 'student')
                @if(!$previewSessionActive)
                    <button type="button" @click="triggerQuickCheckin()"
                            class="inline-flex items-center justify-center gap-2.5 rounded-2xl bg-white px-6 py-4 text-sm font-black text-slate-950 shadow-xl transition transform hover:scale-[1.02] active:scale-[0.98] cursor-pointer">
                        <i data-lucide="fingerprint" class="h-5 w-5 text-emerald-600"></i>
                        <span x-show="!gettingGps">{{ $sessionState === 'on_duty_transit' ? 'Check-in at Next Site (GPS)' : 'Give Attendance Now (GPS)' }}</span>
                        <span x-show="gettingGps" x-cloak>Locking GPS...</span>
                    </button>
                @else
                    <button type="button" wire:click="initiateCheckoutPrompt"
                            class="inline-flex items-center justify-center gap-2.5 rounded-2xl bg-slate-950 px-6 py-4 text-sm font-black text-white shadow-xl transition hover:bg-slate-900 cursor-pointer">
                        <i data-lucide="log-out" class="h-5 w-5 text-amber-400"></i>
                        <span>Out (Close Duty / On Duty Move)</span>
                    </button>
                @endif
            @else
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-2xl bg-black/25 px-3.5 py-2.5 backdrop-blur-sm">
                        <p class="text-[10px] font-bold uppercase opacity-80">Right Time</p>
                        <p class="text-xl font-black">{{ $onTimeRowsCount }}</p>
                    </div>
                    <div class="rounded-2xl bg-black/25 px-3.5 py-2.5 backdrop-blur-sm">
                        <p class="text-[10px] font-bold uppercase opacity-80">Late / Present</p>
                        <p class="text-xl font-black">{{ $lateRowsCount }}</p>
                    </div>
                    <div class="rounded-2xl bg-black/25 px-3.5 py-2.5 backdrop-blur-sm">
                        <p class="text-[10px] font-bold uppercase opacity-80">On Duty Move</p>
                        <p class="text-xl font-black">{{ count($teamRadar['in_transit'] ?? []) }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>
@endif

{{-- ===================================================================== --}}
{{-- 5. INTERACTIVE MONTHLY CALENDAR & DAILY ACTIVITY INSPECTOR (#UserProp3)--}}
{{-- ===================================================================== --}}
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,1fr)]">
    {{-- Left: Current Month Interactive Calendar --}}
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">INTERACTIVE MONTHLY CALENDAR</p>
                <h3 class="mt-0.5 text-lg font-extrabold text-slate-900">
                    {{ \DateTimeImmutable::createFromFormat('!Y-m', $calendarMonth ?: now()->format('Y-m'))?->format('F Y') ?? $calendarMonth }}
                </h3>
                <p class="text-xs text-slate-500">Click any date to inspect that day's attendance, client movements, holidays, leaves &amp; conveyance.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" wire:click="changeCalendarMonth(-1)" class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-700 hover:bg-slate-100" title="Previous Month">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                </button>
                <button type="button" wire:click="selectCalendarDate('{{ now()->toDateString() }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50">
                    Today
                </button>
                <button type="button" wire:click="changeCalendarMonth(1)" class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-700 hover:bg-slate-100" title="Next Month">
                    <i data-lucide="chevron-right" class="h-4 w-4"></i>
                </button>
            </div>
        </div>

        {{-- Weekday Header --}}
        <div class="mt-4 grid grid-cols-7 gap-1.5 text-center text-[11px] font-extrabold uppercase tracking-wider text-slate-400">
            <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div class="text-rose-500">Fri</div><div class="text-rose-500">Sat</div>
        </div>

        {{-- Calendar Days Grid --}}
        <div class="mt-2 grid grid-cols-7 gap-1.5">
            @foreach($calendarDays as $idx => $cDay)
                @if(!empty($cDay['is_padding']))
                    <div wire:key="cal-pad-{{ $idx }}" class="h-16 sm:h-20 rounded-xl bg-slate-50/40"></div>
                @else
                    @php
                        $isSelected = ($selectedCalendarDate ?? '') === $cDay['date'];
                        $hasHoliday = !empty($cDay['holiday']);
                        $isOpenOverride = $hasHoliday && !empty($cDay['holiday']['is_office_open_override']);
                        $cellBg = $isSelected
                            ? 'border-2 border-slate-900 bg-amber-50/70 shadow-sm'
                            : ($hasHoliday
                                ? ($isOpenOverride ? 'border border-amber-300 bg-amber-50/50' : 'border border-rose-200 bg-rose-50/60')
                                : ($cDay['is_weekend'] ? 'border border-slate-100 bg-slate-50/80' : 'border border-slate-200/80 bg-white hover:border-slate-300 hover:bg-slate-50/50'));
                    @endphp
                    <button type="button"
                            wire:key="cal-day-{{ $cDay['date'] }}"
                            wire:click="selectCalendarDate('{{ $cDay['date'] }}')"
                            class="flex h-16 sm:h-20 flex-col justify-between rounded-xl p-1.5 sm:p-2 text-left transition {{ $cellBg }}">
                        <div class="flex items-center justify-between w-full">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-md text-xs font-extrabold {{ $cDay['is_today'] ? 'bg-[#17191F] text-[#FCDF02]' : ($hasHoliday || $cDay['is_weekend'] ? 'text-rose-700' : 'text-slate-800') }}">
                                {{ $cDay['day'] }}
                            </span>
                            @if($hasHoliday)
                                <span class="rounded px-1 py-0.2 text-[8px] font-extrabold uppercase {{ $isOpenOverride ? 'bg-amber-200 text-amber-950' : 'bg-rose-200 text-rose-900' }}">
                                    {{ $isOpenOverride ? 'OPEN' : 'HOL' }}
                                </span>
                            @elseif($cDay['is_weekend'])
                                <span class="text-[8px] font-bold text-slate-400">Off</span>
                            @endif
                        </div>

                        <div class="space-y-0.5 w-full overflow-hidden">
                            @if($hasHoliday)
                                <p class="truncate text-[9px] font-bold {{ $isOpenOverride ? 'text-amber-900' : 'text-rose-800' }}" title="{{ $cDay['holiday']['title'] }}">
                                    {{ $cDay['holiday']['title'] }}
                                </p>
                            @endif
                            <div class="flex flex-wrap items-center gap-0.5">
                                @if(($cDay['right_cnt'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded bg-emerald-100 px-1 py-0.2 text-[8px] font-extrabold text-emerald-800" title="Right Time">{{ $cDay['right_cnt'] }}✓</span>
                                @endif
                                @if(($cDay['late_cnt'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded bg-amber-100 px-1 py-0.2 text-[8px] font-extrabold text-amber-900" title="Late">{{ $cDay['late_cnt'] }}L</span>
                                @endif
                                @if(($cDay['late_pres_cnt'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded bg-rose-100 px-1 py-0.2 text-[8px] font-extrabold text-rose-800" title="Late Present">{{ $cDay['late_pres_cnt'] }}LP</span>
                                @endif
                                @if(($cDay['leave_cnt'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded bg-indigo-100 px-1 py-0.2 text-[8px] font-extrabold text-indigo-800" title="On Leave">{{ $cDay['leave_cnt'] }}Lv</span>
                                @endif
                                @if(($cDay['claim_sum'] ?? 0) > 0)
                                    <span class="inline-flex items-center rounded bg-teal-100 px-1 py-0.2 text-[8px] font-extrabold text-teal-900">৳{{ number_format($cDay['claim_sum'], 0) }}</span>
                                @endif
                            </div>
                        </div>
                    </button>
                @endif
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-[10px] font-bold text-slate-500">
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-emerald-500"></span> Right Time (✓)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-amber-400"></span> Late (L)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-rose-600"></span> Late Present (LP)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-rose-200"></span> Govt/Firm Holiday</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded bg-indigo-200"></span> Leave (Lv)</span>
        </div>
    </div>

    {{-- Right: Selected Date Daily Activity Inspector --}}
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">DAILY ACTIVITY INSPECTOR</p>
                    <h3 class="mt-0.5 text-base font-extrabold text-slate-900">{{ $selectedDayActivity['date_formatted'] ?? $selectedCalendarDate }}</h3>
                </div>
                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-extrabold text-slate-700">
                    {{ $selectedDayActivity['date'] ?? '' }}
                </span>
            </div>

            {{-- Holiday Banner if any --}}
            @if(!empty($selectedDayActivity['holiday']))
                @php $hol = $selectedDayActivity['holiday']; @endphp
                <div class="mt-3 rounded-xl border p-3 text-xs {{ !empty($hol['is_office_open_override']) ? 'border-amber-300 bg-amber-50 text-amber-950' : 'border-rose-200 bg-rose-50 text-rose-900' }}">
                    <div class="flex items-center justify-between font-extrabold">
                        <span>{{ $hol['title'] }}</span>
                        <span class="rounded px-2 py-0.5 text-[10px] {{ !empty($hol['is_office_open_override']) ? 'bg-amber-200 text-amber-950' : 'bg-rose-200 text-rose-900' }}">
                            {{ !empty($hol['is_office_open_override']) ? 'OFFICE OPEN OVERRIDE' : 'OFFICIAL HOLIDAY' }}
                        </span>
                    </div>
                    @if(!empty($hol['is_office_open_override']))
                        <p class="mt-1 text-[11px]">Mandatory attendance enforced ({{ $hol['absent_deduction_days'] ?? 1 }} day salary deduction if absent).</p>
                    @endif
                </div>
            @elseif(!empty($selectedDayActivity['is_weekend']))
                <div class="mt-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 font-semibold">
                    Weekly Practice Holiday (Friday / Saturday).
                </div>
            @endif

            {{-- Attendance Logs on Selected Date --}}
            <div class="mt-4">
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Attendance &amp; Client Movements ({{ count($selectedDayActivity['attendances'] ?? []) }})</h4>
                <div class="mt-2 space-y-2 max-h-52 overflow-y-auto">
                    @forelse(($selectedDayActivity['attendances'] ?? []) as $dayAtt)
                        <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-2.5 text-xs">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-bold text-slate-900">{{ $dayAtt['name'] }} <span class="text-[10px] font-mono text-slate-400">({{ $dayAtt['code'] }})</span></span>
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-extrabold {{ in_array($dayAtt['status'], ['Right Time', 'On time'], true) ? 'bg-emerald-100 text-emerald-800' : ($dayAtt['status'] === 'Late' ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800') }}">
                                    {{ $dayAtt['status'] }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-600">
                                <strong>{{ $dayAtt['mode'] }}:</strong> {{ $dayAtt['site'] }} · In: <strong>{{ $dayAtt['in_time'] }}</strong> | Out: <strong>{{ $dayAtt['out_time'] }}</strong>
                            </p>
                            @if(!empty($dayAtt['movement_logs']) && count($dayAtt['movement_logs']) > 1)
                                <p class="mt-1 text-[10px] font-semibold text-teal-800">
                                    🔄 {{ count($dayAtt['movement_logs']) }} On-Duty Legs:
                                    @foreach($dayAtt['movement_logs'] as $leg)
                                        <span>[Leg {{ $leg['seq'] ?? $loop->iteration }}: {{ $leg['site'] ?? 'Office' }} ({{ $leg['in_time'] ?? '' }}→{{ $leg['out_time'] ?? 'Active' }})]</span>
                                    @endforeach
                                </p>
                            @endif
                        </div>
                    @empty
                        <p class="rounded-xl border border-dashed border-slate-200 p-3 text-center text-[11px] text-slate-400">No attendance logs recorded on this date.</p>
                    @endforelse
                </div>
            </div>

            {{-- Conveyance & Leaves on Selected Date --}}
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-3">
                    <p class="text-[10px] font-extrabold uppercase text-slate-500">Conveyance ({{ count($selectedDayActivity['claims'] ?? []) }})</p>
                    @forelse(($selectedDayActivity['claims'] ?? []) as $dc)
                        <div class="mt-1.5 text-[11px] border-b border-slate-100 pb-1 last:border-0">
                            <span class="font-bold text-slate-800">{{ $dc['name'] }}</span>: <strong>৳{{ number_format($dc['amount'], 0) }}</strong>
                            <p class="truncate text-[10px] text-slate-500">{{ $dc['client'] }}</p>
                        </div>
                    @empty
                        <p class="mt-1.5 text-[11px] text-slate-400">No claims on this date.</p>
                    @endforelse
                </div>
                <div class="rounded-xl border border-slate-200/80 bg-slate-50/50 p-3">
                    <p class="text-[10px] font-extrabold uppercase text-slate-500">Active Leaves ({{ count($selectedDayActivity['leaves'] ?? []) }})</p>
                    @forelse(($selectedDayActivity['leaves'] ?? []) as $dl)
                        <div class="mt-1.5 text-[11px] border-b border-slate-100 pb-1 last:border-0">
                            <span class="font-bold text-slate-800">{{ $dl['name'] }}</span> ({{ $dl['type'] }})
                            <p class="text-[10px] text-indigo-700 font-semibold">{{ $dl['status'] }}</p>
                        </div>
                    @empty
                        <p class="mt-1.5 text-[11px] text-slate-400">No leaves on this date.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="mt-4 border-t border-slate-100 pt-3 flex items-center justify-between text-xs">
            <span class="text-[11px] text-slate-400">Click any date cell on the calendar to inspect.</span>
            @if($previewRole === 'partner')
                <button type="button" wire:click="setSection('policies')" class="font-bold text-amber-800 hover:underline">Manage Holidays →</button>
            @endif
        </div>
    </div>
</section>

{{-- ===================================================================== --}}
{{-- 6. LIVE TEAM RADAR, CLIENT COSTING & PUNCTUALITY LEADERBOARD           --}}
{{-- ===================================================================== --}}
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(290px,1fr)]">
    <!-- Main Left Column -->
    <div class="space-y-5">
        @if($previewRole !== 'student')
            {{-- Proposal 1: Live Team Radar ("কে কোথায় আছে" — Office vs Client Site vs On Duty Transit) --}}
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">LIVE FIELD RADAR · REAL-TIME LOCATION MATRIX</p>
                        <h3 class="mt-0.5 text-base font-extrabold text-slate-900">Who's Where Right Now ({{ $partnerUnit }})</h3>
                    </div>
                    <button type="button" wire:click="setSection('attendance')" class="text-xs font-bold text-amber-800 hover:underline">Open Full Register →</button>
                </div>
                <div class="mt-4 grid gap-3 sm:grid-cols-3 text-xs">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5">
                        <div class="flex items-center justify-between font-extrabold text-emerald-950">
                            <span>🏢 Principal Office HQ</span>
                            <span class="rounded-full bg-emerald-200 px-2 py-0.5 text-[10px]">{{ count($teamRadar['at_office'] ?? []) }}</span>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @forelse(($teamRadar['at_office'] ?? []) as $mOff)
                                <div class="rounded-lg bg-white p-2 border border-emerald-100 flex items-center justify-between">
                                    <span class="font-bold text-slate-800 truncate">{{ $mOff['name'] }}</span>
                                    <span class="text-[10px] font-mono text-emerald-700">{{ $mOff['checkin'] }}</span>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400 py-1">No active office check-ins yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-indigo-200 bg-indigo-50/50 p-3.5">
                        <div class="flex items-center justify-between font-extrabold text-indigo-950">
                            <span>📍 Client Audit Sites</span>
                            <span class="rounded-full bg-indigo-200 px-2 py-0.5 text-[10px]">{{ count($teamRadar['at_client'] ?? []) }}</span>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @forelse(($teamRadar['at_client'] ?? []) as $mCli)
                                <div class="rounded-lg bg-white p-2 border border-indigo-100">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800 truncate">{{ $mCli['name'] }}</span>
                                        <span class="text-[10px] font-mono text-indigo-700">{{ $mCli['checkin'] }}</span>
                                    </div>
                                    <p class="truncate text-[10px] text-slate-500 mt-0.5">{{ $mCli['client_site'] ?: $mCli['in_location'] }}</p>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400 py-1">No students at client sites right now.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-3.5">
                        <div class="flex items-center justify-between font-extrabold text-amber-950">
                            <span>🚕 On Duty (In Transit)</span>
                            <span class="rounded-full bg-amber-200 px-2 py-0.5 text-[10px]">{{ count($teamRadar['in_transit'] ?? []) }}</span>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            @forelse(($teamRadar['in_transit'] ?? []) as $mTr)
                                <div class="rounded-lg bg-white p-2 border border-amber-100">
                                    <span class="font-bold text-slate-800 block truncate">{{ $mTr['name'] }}</span>
                                    <span class="text-[10px] text-amber-800 font-semibold">Moving to next client/office</span>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400 py-1">No members currently in transit.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </section>

            {{-- Proposal 2: Client Audit Costing & Man-Hour Analytics --}}
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">AUDIT ENGAGEMENT ANALYTICS</p>
                        <h3 class="text-sm font-extrabold text-slate-900">Client-Wise Man-Hours &amp; Conveyance Costing ({{ $period }})</h3>
                    </div>
                    <button type="button" wire:click="setSection('conveyance')" class="text-xs font-bold text-amber-800 hover:underline">Audit Claims →</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-5 py-3">Client / Engagement Site</th>
                                <th class="px-4 py-3">Logged Visits</th>
                                <th class="px-4 py-3">Est. Man-Hours</th>
                                <th class="px-5 py-3 text-right">Conveyance Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse(($clientCostingSummary ?? []) as $cStat)
                                <tr>
                                    <td class="px-5 py-3 font-bold text-slate-800">{{ $cStat['client'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $cStat['visits'] }} visit(s)</td>
                                    <td class="px-4 py-3 font-semibold text-slate-700">{{ number_format($cStat['hours'], 1) }} hrs</td>
                                    <td class="px-5 py-3 text-right font-extrabold text-emerald-800">৳ {{ number_format($cStat['conveyance'], 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-5 py-6 text-center text-xs text-slate-400">Client audit hours and conveyance costs will populate automatically as students check in and submit claims.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($previewRole === 'student' && ($studentGrids['payroll_allowance'] ?? true))
            <section class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-900 uppercase tracking-wider">Automatic Monthly Allowance ({{ $period }})</span>
                        <h3 class="mt-1.5 text-base font-extrabold text-slate-900">Net Payable: ৳ {{ number_format($myPayrollRow['net_payable'] ?? 0, 0) }}</h3>
                        <p class="mt-1 text-xs text-slate-600">
                            Base Stipend: <strong>৳ {{ number_format($myPayrollRow['base_allowance'] ?? 0, 0) }}</strong> ·
                            Approved Conveyance: <strong>৳ {{ number_format($myPayrollRow['approved_conveyance'] ?? 0, 0) }}</strong> ·
                            Late Deduction: <strong>৳ {{ number_format($myPayrollRow['adjustment'] ?? 0, 0) }}</strong>
                        </p>
                    </div>
                    <button type="button" wire:click="setSection('payroll')" class="rounded-xl bg-emerald-700 px-3.5 py-2 text-xs font-bold text-white hover:bg-emerald-800">View Statement</button>
                </div>
            </section>
        @endif

        @if($previewRole === 'student' && ($studentGrids['client_site_duty'] ?? true))
            <section class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 uppercase tracking-wider">Current Engagement</span>
                        <h3 class="mt-1.5 text-sm font-extrabold text-slate-900">Assigned Client / Duty Site: {{ $clientSite ?: 'General Office / Firm HQ' }}</h3>
                        <p class="mt-1 text-xs text-slate-600">Location: {{ $currentLocationName ?: ($firm['office'] ?? 'Dhaka, Bangladesh') }} · Supervisor: {{ auth()->user()?->manager?->name ?? ($rows[0]['manager'] ?? 'Assigned Manager') }}</p>
                    </div>
                    <button type="button" wire:click="setSection('conveyance')" class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Submit Fare</button>
                </div>
            </section>
        @endif
    </div>

    <!-- Right Sidebar Column -->
    <div class="space-y-5">
        {{-- Proposal 4: Punctuality Score & On-Time Streak Leaderboard --}}
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">PUNCTUALITY LEADERBOARD</p>
                    <h3 class="text-sm font-extrabold text-slate-900">Right-Time Score &amp; Streak</h3>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-extrabold text-emerald-800">🔥 {{ $period }}</span>
            </div>
            <div class="mt-3 space-y-2.5">
                @forelse(array_slice($punctualityLeaderboard ?? [], 0, 5) as $idx => $pItem)
                    <div class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50/70 p-2.5 text-xs">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg font-black text-[11px] {{ $idx === 0 ? 'bg-[#FCDF02] text-[#17191F]' : 'bg-slate-200 text-slate-700' }}">
                                #{{ $idx + 1 }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-bold text-slate-900">{{ $pItem['name'] }}</p>
                                <p class="text-[10px] text-slate-500">Right Time: {{ $pItem['right_days'] }}d · Late: {{ $pItem['late_days'] }}d</p>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-block rounded-full px-2 py-0.5 text-[11px] font-black {{ $pItem['score'] >= 90 ? 'bg-emerald-100 text-emerald-800' : ($pItem['score'] >= 70 ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800') }}">
                                {{ $pItem['score'] }}%
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-slate-400">Punctuality scores appear as students record daily check-ins.</p>
                @endforelse
            </div>
        </section>

        @if($previewRole !== 'student' || ($studentGrids['firm_notices'] ?? true))
            <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-extrabold">{{ $partnerUnit }} Rules · Active</h3>
                    <i data-lucide="shield-check" class="h-5 w-5 text-[#FCDF02]"></i>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-300">3-Tier arrival classification and automatic payroll deduction rules.</p>
                <div class="mt-4 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Right Time Window</span><strong class="text-emerald-400">&le; {{ $officeStart }}</strong></div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Grace (Late) Window</span><strong class="text-amber-300">{{ $officeStart }} – {{ $graceEndFormatted ?? '09:40' }}</strong></div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Late Present Window</span><strong class="text-rose-400">&gt; {{ $graceEndFormatted ?? '09:40' }}</strong></div>
                    <div class="flex items-center justify-between gap-2"><span class="text-slate-400">Late Deduction Rule</span><strong>{{ $lateDaysPerDeduction }} Late = {{ $deductionDaysCount }} Day Cut</strong></div>
                </div>
                <button wire:click="setSection('settings')" class="mt-4 text-xs font-bold text-[#FCDF02] hover:underline">Configure policy rules →</button>
            </section>
        @endif

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <h3 class="text-sm font-extrabold">Quick Actions</h3>
            <div class="mt-4 grid grid-cols-2 gap-2">
                @foreach ([
                    ['name'=>'Leave', 'section'=>'leave', 'icon'=>'calendar-plus'],
                    ['name'=>'Conveyance', 'section'=>'conveyance', 'icon'=>'receipt'],
                    ['name'=>'Payroll', 'section'=>'payroll', 'icon'=>'wallet'],
                    ['name'=>'Policies', 'section'=>'policies', 'icon'=>'shield']
                ] as $shortcut)
                    <button type="button" wire:click="setSection('{{ $shortcut['section'] }}')" wire:key="shortcut-{{ $shortcut['section'] }}" class="flex flex-col items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3.5 text-center text-xs font-semibold text-slate-700 transition hover:border-amber-400 hover:bg-amber-50">
                        <i data-lucide="{{ $shortcut['icon'] }}" class="h-5 w-5 text-amber-800"></i>{{ $shortcut['name'] }}
                    </button>
                @endforeach
            </div>
        </section>
    </div>
</div>