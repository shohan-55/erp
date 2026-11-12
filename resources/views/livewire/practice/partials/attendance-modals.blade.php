{{-- ─────────────────────────────────────────────────────────────────────
     Attendance dialogs: the Close / On-Duty checkout pop-up, the final
     check-out review and the daily movement timeline.

     These live outside the attendance partial because the same buttons appear
     on the dashboard dock (and inside the movement timeline itself), while
     only one section is rendered at a time. They are included once from
     preview.blade.php so every screen can open them.
     ───────────────────────────────────────────────────────────────────── --}}

{{-- Check-out Popup Modal: Option A (Close Duty) vs Option B (On Duty Move) --}}
@if($showCheckoutModeModal && !$showFinalCheckoutReview)
    <div class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">CHECK-OUT ACTION REQUIRED</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">Select Out Mode</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-slate-600">Are you finishing your duty for today, or moving from this location to another Client Site / Office on duty?</p>

            <div class="mt-5 space-y-3">
                <button type="button" wire:click="confirmCheckoutWithAction('on_duty')" class="flex w-full items-start gap-3 rounded-xl border-2 border-amber-400 bg-amber-50/70 p-4 text-left transition hover:bg-amber-100">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCDF02] text-[#17191F] font-black text-sm">B</div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-900">On Duty (Moving to Client / Office)</p>
                        <p class="mt-1 text-xs text-slate-600">Log departure time &amp; GPS from current site and keep today's attendance open so you can Check-in at your next Client/Office location.</p>
                    </div>
                </button>

                <button type="button" wire:click="openFinalCheckoutReview" class="flex w-full items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-left transition hover:bg-slate-100">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#17191F] text-white font-black text-sm">A</div>
                    <div>
                        <p class="text-sm font-extrabold text-slate-900">Close (End Today's Duty)</p>
                        <p class="mt-1 text-xs text-slate-600">Complete your workday and record final Check-out time and GPS location. You will be asked to review and confirm — one click cannot end the day.</p>
                    </div>
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ── FINAL CHECK-OUT REVIEW (step 2) ─────────────────────────────────────
     Somebody travelling between client sites is the person most likely to
     tap "Close" by mistake. Before the day can end they see every leg of
     today's movement, the elapsed duty time and their current location, and
     they must tick a box. The server refuses the close without it. --}}
@if($showCheckoutModeModal && $showFinalCheckoutReview)
    @php $summary = $finalCheckoutSummary ?: $this->buildFinalCheckoutSummary(); @endphp
    <div class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-rose-700">FINAL CHECK-OUT · CONFIRMATION REQUIRED</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">Is today's duty really finished?</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Cancel"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">First In</p>
                    <p class="mt-1 text-sm font-extrabold text-slate-900">{{ $summary['first_in'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Duty Time</p>
                    <p class="mt-1 text-sm font-extrabold text-slate-900">{{ $summary['elapsed'] }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">Now</p>
                    <p class="mt-1 text-sm font-extrabold text-slate-900">{{ $summary['now_time'] }}</p>
                </div>
            </div>

            <div class="mt-3 rounded-xl border border-slate-200 bg-white p-3">
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                    Today's movement · {{ $summary['leg_count'] }} leg{{ $summary['leg_count'] === 1 ? '' : 's' }}
                </p>
                <ol class="mt-2 max-h-48 space-y-2 overflow-y-auto pr-1">
                    @forelse($summary['legs'] as $leg)
                        <li class="flex items-start gap-2 rounded-lg bg-slate-50 p-2 text-[11px]">
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md bg-[#17191F] text-[10px] font-black text-[#FCDF02]">{{ $leg['seq'] ?? $loop->iteration }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="font-extrabold text-slate-900">{{ $leg['mode'] ?? 'Office' }} · {{ $leg['site'] ?? 'HQ' }}</p>
                                <p class="text-slate-500">
                                    In <span class="font-bold text-emerald-700">{{ $leg['in_time'] ?? '—' }}</span>
                                    · Out <span class="font-bold {{ ($leg['out_action'] ?? '') === 'on_duty' ? 'text-amber-700' : 'text-slate-700' }}">{{ $leg['out_time'] ?? '—' }}</span>
                                    @if(($leg['out_action'] ?? '') === 'on_duty')
                                        <span class="ml-1 rounded bg-amber-100 px-1 py-0.5 text-[9px] font-bold text-amber-900">moved on duty</span>
                                    @endif
                                </p>
                            </div>
                        </li>
                    @empty
                        <li class="rounded-lg bg-slate-50 p-2 text-[11px] text-slate-500">No movement recorded for today.</li>
                    @endforelse
                </ol>
                <p class="mt-2 text-[11px] text-slate-500">Current location: <span class="font-bold text-slate-700">{{ $summary['location'] }}</span></p>
            </div>

            @if($summary['was_on_duty'])
                <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-[11px] font-semibold text-amber-950">
                    You were last marked <strong>On Duty (in transit)</strong> and have not checked in anywhere since.
                    If you are still travelling to a client, choose <strong>Keep On Duty</strong> below instead of closing.
                </div>
            @endif

            <label class="mt-4 flex cursor-pointer items-start gap-2.5 rounded-xl border-2 border-slate-200 p-3 transition hover:border-slate-300 {{ $finalCloseConfirmed ? 'border-emerald-400 bg-emerald-50' : '' }}">
                <input type="checkbox" wire:model.live="finalCloseConfirmed" class="mt-0.5 h-4 w-4 shrink-0 accent-emerald-600">
                <span class="text-xs font-bold leading-relaxed text-slate-800">
                    I have finished today's duty. This is my final location and I will not check in anywhere else today.
                </span>
            </label>

            @if($error)
                <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-[11px] font-bold text-rose-800">{{ $error }}</p>
            @endif

            <div class="mt-5 flex flex-col gap-2 sm:flex-row">
                <button type="button" wire:click="backToCheckoutChoice" class="flex-1 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                    Back
                </button>
                <button type="button" wire:click="confirmCheckoutWithAction('on_duty')" class="flex-1 rounded-xl border-2 border-amber-400 bg-amber-50 px-4 py-3 text-xs font-extrabold text-amber-950 transition hover:bg-amber-100">
                    Not finished — Keep On Duty
                </button>
                <button type="button" wire:click="confirmFinalCheckout" wire:loading.attr="disabled" wire:target="confirmFinalCheckout" @disabled(! $finalCloseConfirmed)
                        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-xl bg-rose-600 px-4 py-3 text-xs font-extrabold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500">
                    <span wire:loading wire:target="confirmFinalCheckout" style="display:none" class="dw-inline-spinner"></span>
                    <span wire:loading.remove wire:target="confirmFinalCheckout">Confirm Final Check-out</span>
                    <span wire:loading wire:target="confirmFinalCheckout" style="display:none">Closing Duty...</span>
                </button>
            </div>
            <p class="mt-2 text-center text-[10px] text-slate-400">The final check-out is written to the attendance register and the audit log.</p>
        </div>
    </div>
@endif

{{-- Daily Movement Timeline Modal for Manager / Partner / Student --}}
@if($showMovementTimelineModal && $selectedTimelineRow)
    <div class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">DAILY MOVEMENT LOG</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">{{ $selectedTimelineRow['name'] }} ({{ $selectedTimelineRow['id'] }})</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            <div class="mt-4 space-y-3 max-h-80 overflow-y-auto">
                @forelse($selectedTimelineRow['movement_logs'] ?? [] as $leg)
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-extrabold text-slate-900">Leg #{{ $leg['seq'] ?? $loop->iteration }} · {{ $leg['mode'] ?? 'Office' }} ({{ $leg['site'] ?? 'HQ' }})</span>
                            <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ ($leg['out_action'] ?? '') === 'on_duty' ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-800' }}">
                                {{ ($leg['out_action'] ?? '') === 'on_duty' ? 'Moved On Duty' : (($leg['out_action'] ?? '') === 'closed' ? 'Duty Closed' : 'Active') }}
                            </span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-[11px] text-slate-600">
                            <div><strong>Check-in:</strong> {{ $leg['in_time'] ?? '—' }}<br><span class="text-slate-400">{{ $leg['in_location'] ?? '—' }}</span></div>
                            <div><strong>Out:</strong> {{ $leg['out_time'] ?? '—' }}<br><span class="text-slate-400">{{ $leg['out_location'] ?? '—' }}</span></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-slate-500 py-4 text-center">No movement segments recorded for today.</p>
                @endforelse
            </div>

            <div class="mt-5 flex justify-end">
                <button type="button" wire:click="closeDialogs" class="rounded-xl bg-[#17191F] px-4 py-2 text-xs font-bold text-white">Close Timeline</button>
            </div>
        </div>
    </div>
@endif


{{-- Individual Member Full Monthly Attendance Sheet Modal (#Issue04) --}}
@if(($showMemberMonthlyModal ?? false) && !empty($memberMonthlyReport))
    <div x-data="{ dayFilter: 'all' }"
         class="fixed inset-0 z-[80] overflow-y-auto bg-slate-950/70 p-3 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs"
         role="dialog" aria-modal="true" aria-labelledby="member-monthly-modal-title">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-5xl my-auto overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
                {{-- Top Dark Executive Header --}}
                <div style="background: linear-gradient(135deg, #11141C 0%, #171B26 55%, #0D1117 100%); color: #FFFFFF;"
                     class="flex flex-col justify-between gap-4 border-b border-slate-800 bg-[#17191F] px-5 py-4 sm:flex-row sm:items-center sm:px-7">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-[#FCDF02] px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-[#17191F]">
                                INDIVIDUAL MONTHLY ATTENDANCE LEDGER
                            </span>
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-0.5 text-[10px] font-bold text-emerald-300">
                                {{ $memberMonthlyReport['designation'] ?? 'Practice Member' }}
                            </span>
                            <span class="rounded-full border border-white/15 bg-white/10 px-2.5 py-0.5 font-mono text-[10px] font-bold text-amber-200">
                                {{ $memberMonthlyReport['student_code'] ?? '' }}
                            </span>
                        </div>
                        <h3 id="member-monthly-modal-title" class="mt-1.5 truncate text-lg sm:text-xl font-black text-white">
                            {{ $memberMonthlyReport['name'] ?? 'Practice Member' }} — {{ $memberMonthlyReport['month_label'] ?? $monthlyViewMonth }}
                        </h3>
                        <p class="mt-0.5 text-xs text-slate-300">
                            Unit: <strong>{{ $memberMonthlyReport['partner_unit'] ?? $partnerUnit }}</strong> · Supervisor: <strong>{{ $memberMonthlyReport['manager'] ?? 'Direct Report' }}</strong>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        {{-- Month Switcher --}}
                        <div class="inline-flex items-center gap-1 rounded-xl border border-white/15 bg-white/10 p-1">
                            <button type="button" wire:click="changeMemberMonthlyViewMonth(-1)"
                                    class="rounded-lg p-1.5 text-white hover:bg-white/15 cursor-pointer" title="Previous Month">
                                <i data-lucide="chevron-left" class="h-4 w-4"></i>
                            </button>
                            <input type="month" wire:model.live="monthlyViewMonth"
                                   class="rounded-lg bg-transparent px-2 py-1 text-xs font-extrabold text-[#FCDF02] outline-none">
                            <button type="button" wire:click="changeMemberMonthlyViewMonth(1)"
                                    class="rounded-lg p-1.5 text-white hover:bg-white/15 cursor-pointer" title="Next Month">
                                <i data-lucide="chevron-right" class="h-4 w-4"></i>
                            </button>
                        </div>

                        {{-- Print / Download PDF Button --}}
                        <button type="button" wire:click="exportMemberMonthlyAttendanceSheet('{{ $memberMonthlyReport['student_code'] ?? '' }}')"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-3.5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition cursor-pointer shadow-sm">
                            <i data-lucide="printer" class="h-4 w-4"></i>
                            <span>Print / PDF</span>
                        </button>

                        <button type="button" wire:click="closeDialogs"
                                class="rounded-xl border border-white/15 bg-white/10 p-2 text-slate-300 hover:bg-rose-500/30 hover:text-white transition cursor-pointer"
                                aria-label="Close monthly attendance modal">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                </div>

                {{-- 6-Card Monthly Summary KPI Strip --}}
                <div class="grid grid-cols-2 gap-2.5 border-b border-slate-200 bg-slate-50/80 p-4 sm:grid-cols-3 lg:grid-cols-6 sm:px-7">
                    <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400">Total Present</span>
                        <p class="mt-1 text-xl font-black text-slate-900">{{ $memberMonthlyReport['present_days'] ?? 0 }} <span class="text-xs font-bold text-slate-400">Days</span></p>
                        <p class="mt-0.5 text-[10px] font-semibold text-slate-500">Office: {{ $memberMonthlyReport['office_days'] ?? 0 }} · Client: {{ $memberMonthlyReport['client_days'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-emerald-800">Right Time</span>
                        <p class="mt-1 text-xl font-black text-emerald-700">{{ $memberMonthlyReport['right_time_days'] ?? 0 }} <span class="text-xs font-bold">Days</span></p>
                        <p class="mt-0.5 text-[10px] font-bold text-emerald-800">Punctuality: {{ $memberMonthlyReport['punctuality_score'] ?? 100 }}%</p>
                    </div>
                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-amber-900">Late Arrivals</span>
                        <p class="mt-1 text-xl font-black text-amber-800">{{ ($memberMonthlyReport['late_days'] ?? 0) + ($memberMonthlyReport['late_present_days'] ?? 0) }} <span class="text-xs font-bold">Days</span></p>
                        <p class="mt-0.5 text-[10px] font-semibold text-amber-900">Grace: {{ $memberMonthlyReport['late_days'] ?? 0 }} · Late Pres: {{ $memberMonthlyReport['late_present_days'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-indigo-800">On Leave</span>
                        <p class="mt-1 text-xl font-black text-indigo-700">{{ $memberMonthlyReport['leave_days'] ?? 0 }} <span class="text-xs font-bold">Days</span></p>
                        <p class="mt-0.5 text-[10px] font-semibold text-indigo-800">Holidays/Off: {{ $memberMonthlyReport['holiday_off_days'] ?? 0 }}d</p>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/50 p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-rose-800">Absent (Elapsed)</span>
                        <p class="mt-1 text-xl font-black text-rose-700">{{ $memberMonthlyReport['absent_days'] ?? 0 }} <span class="text-xs font-bold">Days</span></p>
                        <p class="mt-0.5 text-[10px] font-semibold text-rose-800">Unexcused working days</p>
                    </div>
                    <div class="rounded-2xl border border-teal-200 bg-teal-50/50 p-3 shadow-2xs">
                        <span class="block text-[10px] font-extrabold uppercase tracking-wider text-teal-900">Month Conveyance</span>
                        <p class="mt-1 text-xl font-black text-teal-800">৳ {{ number_format((float) ($memberMonthlyReport['total_conveyance'] ?? 0), 0) }}</p>
                        <p class="mt-0.5 text-[10px] font-semibold text-teal-800">Claimed in {{ $memberMonthlyReport['month_raw'] ?? '' }}</p>
                    </div>
                </div>

                {{-- Filter Bar --}}
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-white px-5 py-3 sm:px-7 text-xs">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span class="font-bold text-slate-500 mr-1">Filter Days:</span>
                        @foreach([
                            'all'     => 'All Days (' . ($memberMonthlyReport['days_in_month'] ?? 30) . ')',
                            'present' => 'Present (' . ($memberMonthlyReport['present_days'] ?? 0) . ')',
                            'late'    => 'Late (' . (($memberMonthlyReport['late_days'] ?? 0) + ($memberMonthlyReport['late_present_days'] ?? 0)) . ')',
                            'absent'  => 'Absent (' . ($memberMonthlyReport['absent_days'] ?? 0) . ')',
                            'leave'   => 'Leave & Holidays (' . (($memberMonthlyReport['leave_days'] ?? 0) + ($memberMonthlyReport['holiday_off_days'] ?? 0)) . ')',
                        ] as $fKey => $fLabel)
                            <button type="button" @click="dayFilter = '{{ $fKey }}'"
                                    :class="dayFilter === '{{ $fKey }}' ? 'bg-[#17191F] text-[#FCDF02] font-extrabold' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-bold'"
                                    class="rounded-xl px-3 py-1.5 text-[11px] transition cursor-pointer">
                                {{ $fLabel }}
                            </button>
                        @endforeach
                    </div>
                    <span class="text-[11px] text-slate-400">Showing full day-by-day breakdown for {{ $memberMonthlyReport['month_label'] ?? '' }}</span>
                </div>

                {{-- Day-by-Day Monthly Table --}}
                <div class="max-h-[55vh] overflow-y-auto px-4 py-3 sm:px-7">
                    <table class="w-full text-left text-xs">
                        <thead class="sticky top-0 z-10 bg-slate-100/95 backdrop-blur text-[10px] font-extrabold uppercase tracking-wider text-slate-600">
                            <tr>
                                <th class="py-2.5 pl-3 pr-2 rounded-l-xl">Date &amp; Day</th>
                                <th class="px-2.5 py-2.5">Mode &amp; Client Site</th>
                                <th class="px-2.5 py-2.5">First Check-In &amp; GPS</th>
                                <th class="px-2.5 py-2.5">Last Check-Out</th>
                                <th class="px-2.5 py-2.5">Duty Hours &amp; Legs</th>
                                <th class="px-2.5 py-2.5">Status</th>
                                <th class="py-2.5 pl-2.5 pr-3 text-right rounded-r-xl">Conveyance / Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach(($memberMonthlyReport['days'] ?? []) as $dRow)
                                @php
                                    $cat = $dRow['category'] ?? 'working';
                                    $isLateRow = in_array($dRow['status_label'] ?? '', ['Late', 'Late Present'], true);
                                    $tone = $dRow['badge_tone'] ?? 'slate';
                                    $pillClass = match ($tone) {
                                        'emerald'  => 'bg-emerald-100 text-emerald-800 border border-emerald-200',
                                        'amber'    => 'bg-amber-100 text-amber-900 border border-amber-300',
                                        'rose'     => 'bg-rose-100 text-rose-800 border border-rose-300',
                                        'teal'     => 'bg-teal-100 text-teal-900 border border-teal-300',
                                        'indigo'   => 'bg-indigo-100 text-indigo-800 border border-indigo-200',
                                        'purple'   => 'bg-purple-100 text-purple-800 border border-purple-200',
                                        'red'      => 'bg-rose-600 text-white',
                                        'upcoming' => 'bg-slate-100 text-slate-400',
                                        default    => 'bg-slate-100 text-slate-600',
                                    };
                                @endphp
                                <tr x-show="dayFilter === 'all'
                                    || (dayFilter === 'present' && '{{ $cat }}' === 'present')
                                    || (dayFilter === 'late' && {{ $isLateRow ? 'true' : 'false' }})
                                    || (dayFilter === 'absent' && '{{ $cat }}' === 'absent')
                                    || (dayFilter === 'leave' && ('{{ $cat }}' === 'leave' || '{{ $cat }}' === 'holiday'))"
                                    class="{{ !empty($dRow['is_today']) ? 'bg-amber-50/70 font-semibold' : ($cat === 'holiday' ? 'bg-slate-50/60 text-slate-500' : 'hover:bg-slate-50') }}">
                                    <td class="py-2.5 pl-3 pr-2 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <span class="font-extrabold text-slate-900">{{ $dRow['display_date'] }}</span>
                                            <span class="rounded bg-slate-200/70 px-1.5 py-0.2 text-[10px] font-bold text-slate-700">{{ $dRow['day_short'] }}</span>
                                            @if(!empty($dRow['is_today']))
                                                <span class="rounded bg-[#17191F] px-1.5 py-0.2 text-[9px] font-black text-[#FCDF02]">TODAY</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-2.5 py-2.5">
                                        @if($dRow['mode'] !== '—')
                                            <span class="font-bold text-slate-800">{{ $dRow['mode'] }}</span>
                                            @if($dRow['site'] !== '—')
                                                <span class="block text-[10px] font-semibold text-amber-800 truncate max-w-[180px]" title="{{ $dRow['site'] }}">{{ $dRow['site'] }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2.5">
                                        @if($dRow['in_time'] !== '—')
                                            <span class="font-mono font-extrabold text-slate-900">{{ $dRow['in_time'] }}</span>
                                            <span class="block text-[10px] text-slate-500 truncate max-w-[190px]" title="{{ $dRow['in_location'] }}">
                                                {{ $dRow['in_location'] }}
                                                @if(!empty($dRow['in_lat']) && !empty($dRow['in_lng']))
                                                    <a href="https://www.google.com/maps?q={{ $dRow['in_lat'] }},{{ $dRow['in_lng'] }}" target="_blank" class="ml-1 font-bold text-amber-800 underline">Map ↗</a>
                                                @endif
                                            </span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2.5">
                                        @if($dRow['out_time'] !== '—')
                                            <span class="font-mono font-bold text-slate-800">{{ $dRow['out_time'] }}</span>
                                            <span class="block text-[10px] text-slate-400 truncate max-w-[160px]">{{ $dRow['out_location'] }}</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2.5 whitespace-nowrap">
                                        @if($dRow['movement_count'] > 0)
                                            <span class="font-bold text-slate-800">{{ $dRow['duration'] }}</span>
                                            <span class="ml-1 rounded bg-amber-50 border border-amber-200 px-1.5 py-0.2 text-[10px] font-bold text-amber-900">{{ $dRow['movement_count'] }} Leg{{ $dRow['movement_count'] > 1 ? 's' : '' }}</span>
                                        @else
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-2.5 py-2.5 whitespace-nowrap">
                                        <span class="inline-block rounded-full px-2.5 py-0.5 text-[10px] font-extrabold {{ $pillClass }}">
                                            {{ $dRow['status_label'] }}
                                        </span>
                                        @if(!empty($dRow['is_proxy']))
                                            <span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.2 text-[9px] font-bold text-indigo-700">Proxy</span>
                                        @endif
                                    </td>
                                    <td class="py-2.5 pl-2.5 pr-3 text-right">
                                        @if(($dRow['conveyance_amount'] ?? 0) > 0)
                                            <span class="font-extrabold text-emerald-800">৳ {{ number_format($dRow['conveyance_amount'], 0) }}</span>
                                        @endif
                                        @if(!empty($dRow['remarks']))
                                            <span class="block text-[10px] text-slate-500 truncate max-w-[180px] ml-auto" title="{{ $dRow['remarks'] }}">{{ $dRow['remarks'] }}</span>
                                        @elseif(($dRow['conveyance_amount'] ?? 0) <= 0)
                                            <span class="text-slate-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Modal Footer --}}
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 bg-slate-50 px-5 py-3.5 sm:px-7 text-xs">
                    <span class="text-slate-500">
                        Tip: Use the month selector at top-right to inspect previous months or click <strong>Print / PDF</strong> to download the official sheet.
                    </span>
                    <div class="flex items-center gap-2">
                        @if($previewRole !== 'student' && ($previewRole === 'partner' || $managerCanEditAttendance))
                            <button type="button" wire:click="openDialog('correction', '{{ $memberMonthlyReport['student_code'] }}', 'edit')"
                                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 font-bold text-slate-700 hover:bg-amber-50 cursor-pointer">
                                Edit Today's Attendance
                            </button>
                        @endif
                        <button type="button" wire:click="closeDialogs"
                                class="rounded-xl bg-[#17191F] px-4 py-2 font-extrabold text-[#FCDF02] hover:bg-slate-800 cursor-pointer">
                            Close Monthly View
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
