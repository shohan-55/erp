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
                <button type="button" wire:click="confirmFinalCheckout" @disabled(! $finalCloseConfirmed)
                        class="flex-1 rounded-xl bg-rose-600 px-4 py-3 text-xs font-extrabold text-white transition hover:bg-rose-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:text-slate-500">
                    Confirm Final Check-out
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
