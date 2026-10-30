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
    $myPunctuality = $punctualityLeaderboard[0] ?? ['score' => 100, 'streak' => 0];
@endphp

{{-- ===================================================================== --}}
{{-- 1A. TOPMOST SECTION FOR MANAGER / STUDENT / STAFF ONLY (#Issue04)     --}}
{{--     Live Policy-Driven Color-Shifting Attendance Command Dock         --}}
{{--     (Hidden on Partner Dashboard; Shifts Green -> Yellow -> Soft/Dark Red) --}}
{{-- ===================================================================== --}}
@if($previewRole !== 'partner' && ($previewRole !== 'student' || ($studentGrids['attendance_clock'] ?? true)))
<section x-data="{
        officeStartStr: '{{ $officeStart ?: '09:30' }}',
        graceMins: {{ (int) ($graceMinutes ?? 10) }},
        nowStr: '{{ now()->timezone('Asia/Dhaka')->format('H:i:s') }}',
        gettingGps: false,
        init() {
            const updateDhakaClock = () => {
                try {
                    const fmt = new Intl.DateTimeFormat('en-GB', {
                        timeZone: 'Asia/Dhaka',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        hour12: false
                    });
                    this.nowStr = fmt.format(new Date());
                } catch (e) {
                    const d = new Date();
                    this.nowStr = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0') + ':' + String(d.getSeconds()).padStart(2, '0');
                }
            };
            updateDhakaClock();
            setInterval(updateDhakaClock, 1000);
        },
        toMins(hm) {
            const pts = (hm || '09:30').split(':');
            return (parseInt(pts[0] || 9, 10) * 60) + parseInt(pts[1] || 30, 10);
        },
        phase() {
            const cur = this.toMins(this.nowStr);
            const start = this.toMins(this.officeStartStr);
            const graceEnd = start + this.graceMins;
            if (cur <= start) return 'green';
            if (cur <= graceEnd) return 'yellow';
            if (cur <= graceEnd + 20) return 'light_red';
            return 'dark_red';
        },
        cardStyle() {
            const p = this.phase();
            if (p === 'green') return 'background: radial-gradient(circle at top right, rgba(52,211,153,0.28), transparent 55%), linear-gradient(135deg, #065F46 0%, #0D9488 55%, #047857 100%); color: #FFFFFF; border-color: rgba(52,211,153,0.5);';
            if (p === 'yellow') return 'background: radial-gradient(circle at top right, rgba(253,230,138,0.35), transparent 55%), linear-gradient(135deg, #B45309 0%, #D97706 45%, #F59E0B 100%); color: #FFFFFF; border-color: rgba(253,230,138,0.65);';
            if (p === 'light_red') return 'background: radial-gradient(circle at top right, rgba(253,164,175,0.28), transparent 55%), linear-gradient(135deg, #BE123C 0%, #E11D48 50%, #9F1239 100%); color: #FFFFFF; border-color: rgba(253,164,175,0.55);';
            return 'background: radial-gradient(circle at top right, rgba(248,113,113,0.22), transparent 55%), linear-gradient(135deg, #7F1D1D 0%, #4C0519 55%, #2A040D 100%); color: #FFFFFF; border-color: rgba(248,113,113,0.5);';
        },
        statusLabel() {
            const p = this.phase();
            if (p === 'green') return 'RIGHT TIME WINDOW (Up to ' + this.officeStartStr + ')';
            if (p === 'yellow') return 'LATE · GRACE WINDOW (' + this.officeStartStr + ' – {{ $graceEndFormatted ?? '09:40' }})';
            if (p === 'light_red') return 'LATE PRESENT WINDOW (Past {{ $graceEndFormatted ?? '09:40' }})';
            return 'LATE PRESENT · EXTENDED DELAY (Past {{ $graceEndFormatted ?? '09:40' }})';
        },
        statusSubtext() {
            const p = this.phase();
            if (p === 'green') return 'You are within the Right Time window. Checking in now records 100% on-time credit.';
            if (p === 'yellow') return 'Grace period active (' + this.graceMins + ' mins). Checking in now records as Late.';
            return 'Grace cutoff ({{ $graceEndFormatted ?? '09:40' }}) has passed. Checking in now records as Late Present ({{ $lateDaysPerDeduction }} late days = {{ $deductionDaysCount }} day salary deduction).';
        },
        submitCheckinWithGps() {
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
    class="relative overflow-hidden rounded-3xl border-2 p-6 sm:p-8 shadow-2xl transition-all duration-700">

    {{-- Decorative Ambient Glass Orbs --}}
    <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-20 left-1/3 h-56 w-56 rounded-full bg-black/20 blur-3xl"></div>

    <div class="relative z-10 grid gap-6 lg:grid-cols-12 lg:items-center">
        {{-- Left Column: Live Digital Clock, Policy Window & Status --}}
        <div class="lg:col-span-6 space-y-3">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-2 rounded-full bg-black/30 px-3.5 py-1 text-[11px] font-black uppercase tracking-wider text-white backdrop-blur-md border border-white/15">
                    <span class="h-2 w-2 rounded-full bg-white animate-ping"></span>
                    <span x-text="statusLabel()"></span>
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold text-white backdrop-blur-md border border-white/15">
                    <i data-lucide="map-pin" class="h-3 w-3"></i>
                    <span>GPS Auto-Capture Active</span>
                </span>
            </div>

            <div class="flex items-baseline gap-3">
                <h2 class="font-mono text-3xl sm:text-4xl font-black tracking-tight text-white drop-shadow-sm" x-text="nowStr"></h2>
                <span class="text-xs font-extrabold uppercase tracking-widest text-white/80">Dhaka Standard Time</span>
            </div>

            <p class="text-xs sm:text-sm text-white/90 leading-relaxed font-medium max-w-xl" x-text="statusSubtext()"></p>

            {{-- Policy Timeline Legend Strip --}}
            <div class="pt-1 flex flex-wrap items-center gap-2 text-[11px] font-bold text-white/90">
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-black/25 px-2.5 py-1 backdrop-blur-sm border border-white/10">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span> Right Time: &le; {{ $officeStart }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-black/25 px-2.5 py-1 backdrop-blur-sm border border-white/10">
                    <span class="h-2 w-2 rounded-full bg-amber-300"></span> Late: {{ $officeStart }}–{{ $graceEndFormatted ?? '09:40' }}
                </span>
                <span class="inline-flex items-center gap-1.5 rounded-xl bg-black/25 px-2.5 py-1 backdrop-blur-sm border border-white/10">
                    <span class="h-2 w-2 rounded-full bg-rose-400"></span> Late Present: &gt; {{ $graceEndFormatted ?? '09:40' }}
                </span>
            </div>
        </div>

        {{-- Right Column: Interactive Office / Client Selector + Client Name Input + Check-In / Out Action --}}
        <div class="lg:col-span-6">
            <div class="rounded-2xl border border-white/20 bg-black/25 p-4 sm:p-5 backdrop-blur-xl shadow-inner space-y-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-extrabold uppercase tracking-wider text-white/90">
                        {{ $previewSessionActive ? 'Active Duty Session' : ($sessionState === 'on_duty_transit' ? 'On Duty Transit — Select Next Location' : 'Select Duty Location & Check In') }}
                    </span>
                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase {{ $previewSessionActive ? 'bg-emerald-400 text-slate-950' : ($sessionState === 'on_duty_transit' ? 'bg-amber-300 text-slate-950' : 'bg-white/20 text-white') }}">
                        {{ $previewSessionActive ? 'Checked In' : ($sessionState === 'on_duty_transit' ? 'On Duty Move' : 'Ready') }}
                    </span>
                </div>

                {{-- Office vs Client Mode Toggle Buttons --}}
                <div class="grid grid-cols-2 gap-2.5">
                    <button type="button"
                            wire:click="selectAttendanceWorkMode('office')"
                            @disabled($previewSessionActive)
                            class="flex items-center justify-center gap-2 rounded-xl border py-3 px-4 text-xs font-extrabold transition cursor-pointer disabled:opacity-60 {{ $workMode === 'office' ? 'border-white bg-white text-slate-950 shadow-md' : 'border-white/25 bg-white/10 text-white hover:bg-white/20' }}">
                        <i data-lucide="building-2" class="h-4 w-4"></i>
                        <span>Office HQ</span>
                    </button>
                    <button type="button"
                            wire:click="selectAttendanceWorkMode('client')"
                            @disabled($previewSessionActive)
                            class="flex items-center justify-center gap-2 rounded-xl border py-3 px-4 text-xs font-extrabold transition cursor-pointer disabled:opacity-60 {{ $workMode === 'client' ? 'border-white bg-white text-slate-950 shadow-md' : 'border-white/25 bg-white/10 text-white hover:bg-white/20' }}">
                        <i data-lucide="briefcase" class="h-4 w-4"></i>
                        <span>Client Site</span>
                    </button>
                </div>

                {{-- When Client Site is selected: Direct Client Name Input Box with Auto-Suggestions --}}
                @if($workMode === 'client')
                    <div class="space-y-1.5">
                        <label class="block text-[11px] font-extrabold uppercase tracking-wider text-white/90">
                            Client / Company Name (ক্লায়েন্টের নাম লিখুন) <span class="text-amber-300">*</span>
                        </label>
                        <div class="relative">
                            <input type="text"
                                   wire:model="clientSite"
                                   list="dashboard-audit-clients-list"
                                   @disabled($previewSessionActive)
                                   placeholder="Type Client Name (e.g. Square Pharma, BRAC Bank Audit)..."
                                   class="w-full rounded-xl border border-white/30 bg-white/95 px-3.5 py-2.5 text-xs font-bold text-slate-950 placeholder:text-slate-400 outline-none focus:border-white focus:ring-2 focus:ring-white/40">
                            <datalist id="dashboard-audit-clients-list">
                                @foreach($auditClients as $cName)
                                    @if($cName !== 'General Office / Firm HQ')
                                        <option value="{{ $cName }}"></option>
                                    @endif
                                @endforeach
                            </datalist>
                        </div>
                    </div>
                @endif

                {{-- Primary Check-In (In) or Out Button --}}
                @if(!$previewSessionActive)
                    <button type="button"
                            @click="submitCheckinWithGps()"
                            class="flex w-full items-center justify-center gap-2.5 rounded-2xl bg-white py-3.5 px-6 text-sm font-black uppercase tracking-wider text-slate-950 shadow-xl transition transform hover:scale-[1.01] active:scale-[0.99] cursor-pointer">
                        <i data-lucide="fingerprint" class="h-5 w-5 text-emerald-600"></i>
                        <span x-show="!gettingGps">
                            {{ $sessionState === 'on_duty_transit' ? 'Check-In at Next Location (In)' : ($workMode === 'client' ? 'Check-In at Client Site (In)' : 'Check-In at Office (In)') }}
                        </span>
                        <span x-show="gettingGps" x-cloak>Capturing Live GPS Location...</span>
                    </button>
                @else
                    <div class="space-y-2">
                        <button type="button"
                                wire:click="initiateCheckoutPrompt"
                                class="flex w-full items-center justify-center gap-2.5 rounded-2xl bg-slate-950 py-3.5 px-6 text-sm font-black uppercase tracking-wider text-white shadow-xl border border-white/20 transition hover:bg-slate-900 cursor-pointer">
                            <i data-lucide="log-out" class="h-5 w-5 text-amber-400"></i>
                            <span>Out (Choose: Close Duty or On Duty Move)</span>
                        </button>
                        <p class="text-center text-[11px] font-semibold text-white/80">
                            Active at: <strong>{{ $workMode === 'client' ? ($clientSite ?: 'Client Site') : 'Principal Office HQ' }}</strong> ({{ $currentLocationName }})
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>
@endif

{{-- ===================================================================== --}}
{{-- 1B. TOPMOST EXECUTIVE COMMAND HERO FOR PARTNER ONLY                   --}}
{{--     (Partners do not give attendance; they see Firm Executive Pulse)  --}}
{{-- ===================================================================== --}}
@if($previewRole === 'partner')
<section class="relative overflow-hidden rounded-3xl border border-slate-800 bg-gradient-to-br from-[#11141C] via-[#171B26] to-[#0D1117] p-6 sm:p-8 text-white shadow-2xl">
    <div class="pointer-events-none absolute -right-20 -top-20 h-72 w-72 rounded-full opacity-20 blur-3xl" style="background-color: {{ $firm['primary_color'] ?? '#0D9488' }}"></div>
    <div class="relative z-10 flex flex-col justify-between gap-6 lg:flex-row lg:items-center">
        <div class="space-y-2.5 max-w-2xl">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#FCDF02] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[#17191F]">
                    <i data-lucide="crown" class="h-3.5 w-3.5"></i> Partner Executive Command
                </span>
                <span class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[11px] font-bold text-slate-200">
                    {{ $partnerUnit }} · {{ $firm['name'] ?? 'Practice' }}
                </span>
            </div>
            <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                Your practice, in complete focus.
            </h2>
            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                Daily office and client site audit activity, real-time field radar, 3-tier attendance governance (<strong>Right Time &le; {{ $officeStart }}</strong> | <strong>Grace {{ $officeStart }}–{{ $graceEndFormatted ?? '09:40' }}</strong> | <strong>Late Present &gt; {{ $graceEndFormatted ?? '09:40' }}</strong>), and automated monthly stipend ledger.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <button type="button" wire:click="setSection('attendance')" class="inline-flex items-center gap-2 rounded-2xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#16181D] shadow-lg transition hover:brightness-105 cursor-pointer">
                <i data-lucide="calendar-check-2" class="h-4 w-4"></i> Attendance Register
            </button>
            <button type="button" wire:click="setSection('payroll')" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-bold text-white hover:bg-white/20 transition cursor-pointer">
                <i data-lucide="wallet-cards" class="h-4 w-4 text-emerald-400"></i> Payroll &amp; Vouchers
            </button>
            <button type="button" wire:click="setSection('settings')" class="inline-flex items-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 text-xs font-bold text-white hover:bg-white/20 transition cursor-pointer">
                <i data-lucide="sliders-horizontal" class="h-4 w-4 text-amber-300"></i> Policy &amp; Theme
            </button>
        </div>
    </div>
</section>
@endif

{{-- ===================================================================== --}}
{{-- 1C. UNREAD APPROVAL INBOX FOR MANAGER & PARTNER (NEW LEAVE & CLAIMS)  --}}
{{--     Works like an Unread Message Notification Center on Dashboard     --}}
{{-- ===================================================================== --}}
@if($previewRole !== 'student')
    @php
        $uLeaves = $pendingLeaveItems ?? [];
        $uClaims = $pendingClaimItems ?? [];
        $uTotal  = count($uLeaves) + count($uClaims);
    @endphp
    @if($uTotal > 0)
        <section class="overflow-hidden rounded-3xl border-2 border-amber-300/90 bg-white shadow-xl">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-200/80 bg-gradient-to-r from-[#17191F] via-[#1F2430] to-[#17191F] px-5 py-4 text-white">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-rose-500/20 border border-rose-400/40 text-rose-300">
                        <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-rose-500 animate-ping"></span>
                        <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full bg-rose-500"></span>
                        <i data-lucide="mail-warning" class="h-5 w-5 text-[#FCDF02]"></i>
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-rose-500 px-2.5 py-0.5 text-[10px] font-black uppercase tracking-wider text-white">
                                {{ $uTotal }} UNREAD REQUEST{{ $uTotal > 1 ? 'S' : '' }}
                            </span>
                            <h3 class="text-sm sm:text-base font-black tracking-tight text-white">
                                New Leave Applications &amp; Conveyance Claims Awaiting Your Approval
                            </h3>
                        </div>
                        <p class="mt-0.5 text-[11px] text-slate-300">
                            Approve or reject directly from this inbox, or open the full review queue ({{ $partnerUnit }}).
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" wire:click="setSection('leave')" class="inline-flex items-center gap-1.5 rounded-xl bg-white/10 px-3 py-2 text-xs font-extrabold text-white hover:bg-white/20 transition cursor-pointer">
                        <span>📩 Leave Queue ({{ count($uLeaves) }})</span>
                    </button>
                    <button type="button" wire:click="setSection('conveyance')" class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-3 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition cursor-pointer">
                        <span>🧾 Conveyance Queue ({{ count($uClaims) }})</span>
                    </button>
                </div>
            </div>

            <div class="grid divide-y lg:grid-cols-2 lg:divide-x lg:divide-y-0 divide-slate-200">
                {{-- Left Column: Unread Leave Applications --}}
                <div class="p-4 sm:p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ count($uLeaves) > 0 ? 'bg-rose-500 animate-ping' : 'bg-emerald-500' }}"></span>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                📩 Unread Leave Applications ({{ count($uLeaves) }})
                            </h4>
                        </div>
                        <button type="button" wire:click="setSection('leave')" class="text-[11px] font-extrabold text-amber-800 hover:underline cursor-pointer">
                            View All →
                        </button>
                    </div>

                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        @forelse($uLeaves as $ul)
                            <div wire:key="dash-unread-leave-{{ $ul['id'] }}" class="rounded-2xl border border-rose-200 border-l-4 border-l-rose-500 bg-rose-50/35 p-3.5 text-xs shadow-2xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-600 px-2 py-0.5 text-[9px] font-black uppercase text-white">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white animate-pulse"></span> NEW
                                        </span>
                                        <span class="font-extrabold text-slate-900">{{ $ul['name'] }}</span>
                                        <span class="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600 border border-slate-200">{{ $ul['student_code'] }}</span>
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[10px] font-extrabold text-amber-900">{{ $ul['type'] }}</span>
                                    </div>
                                    <span class="text-[10px] font-bold text-rose-800">{{ $ul['status_label'] }}</span>
                                </div>
                                <p class="mt-1.5 text-slate-700">
                                    <strong>📅 {{ $ul['period'] }} ({{ $ul['requested_days'] ?? 1 }} Days)</strong> · <em>"{{ $ul['reason'] }}"</em>
                                </p>
                                <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-rose-200/60 pt-2">
                                    <span class="text-[10px] font-semibold text-slate-500">Engaged Mgr: {{ $ul['manager'] ?? 'Direct' }} · {{ $ul['created_at_human'] ?? 'Unread' }}</span>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if($previewRole === 'manager' && $ul['status'] === 'pending_manager')
                                            <button type="button" wire:click="managerRecommendLeave('{{ $ul['id'] }}', 'Recommended by Manager from Dashboard Inbox.')" class="rounded-lg bg-[#FCDF02] px-2.5 py-1 text-[11px] font-extrabold text-[#17191F] hover:bg-[#F3D604] cursor-pointer">
                                                Recommend &amp; Forward →
                                            </button>
                                            <button type="button" wire:click="rejectLeave('{{ $ul['id'] }}', 'Rejected by Manager.')" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                                Reject
                                            </button>
                                        @elseif($previewRole === 'partner')
                                            <button type="button" wire:click="partnerApproveLeave('{{ $ul['id'] }}', 'Approved by Partner from Dashboard Inbox.')" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-extrabold text-white hover:bg-emerald-700 cursor-pointer">
                                                Approve Leave ✓
                                            </button>
                                            <button type="button" wire:click="rejectLeave('{{ $ul['id'] }}', 'Rejected by Partner.')" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                                Reject
                                            </button>
                                        @endif
                                        <button type="button" wire:click="setSection('leave')" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                            Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4 text-center text-xs text-slate-500">
                                ✓ No unread leave applications waiting right now.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Right Column: Unread Conveyance Claims --}}
                <div class="p-4 sm:p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full {{ count($uClaims) > 0 ? 'bg-amber-500 animate-ping' : 'bg-emerald-500' }}"></span>
                            <h4 class="text-xs font-black uppercase tracking-wider text-slate-800">
                                🧾 Unread Conveyance Claims ({{ count($uClaims) }})
                            </h4>
                        </div>
                        <button type="button" wire:click="setSection('conveyance')" class="text-[11px] font-extrabold text-amber-800 hover:underline cursor-pointer">
                            View All →
                        </button>
                    </div>

                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1">
                        @forelse($uClaims as $uc)
                            <div wire:key="dash-unread-claim-{{ $uc['id'] }}" class="rounded-2xl border border-amber-200 border-l-4 border-l-amber-500 bg-amber-50/40 p-3.5 text-xs shadow-2xs">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2 py-0.5 text-[9px] font-black uppercase text-[#17191F]">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#17191F] animate-pulse"></span> NEW
                                        </span>
                                        <span class="font-extrabold text-slate-900">{{ $uc['name'] }}</span>
                                        <span class="rounded bg-white px-1.5 py-0.5 font-mono text-[10px] font-bold text-slate-600 border border-slate-200">{{ $uc['student_code'] }}</span>
                                        <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800">{{ $uc['client_name'] }}</span>
                                    </div>
                                    <span class="text-sm font-black text-slate-900">৳ {{ number_format($uc['amount'], 2) }}</span>
                                </div>
                                <p class="mt-1.5 text-slate-700">
                                    <strong>🛣️ {{ $uc['route'] }}</strong>
                                    @if(!empty($uc['is_late']))
                                        <span class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-extrabold text-rose-800">LATE CLAIM</span>
                                    @endif
                                </p>
                                <div class="mt-2.5 flex flex-wrap items-center justify-between gap-2 border-t border-amber-200/60 pt-2">
                                    <span class="text-[10px] font-semibold text-slate-500">{{ $uc['status_label'] }} · {{ $uc['created_at_human'] ?? 'Unread' }}</span>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if($previewRole === 'manager' && in_array($uc['status'], ['pending_manager', 'Manager Review']))
                                            <button type="button" wire:click="managerReviewClaim('{{ $uc['id'] }}', {{ $uc['amount'] }}, 'Approved by Manager from Dashboard Inbox.')" class="rounded-lg bg-[#FCDF02] px-2.5 py-1 text-[11px] font-extrabold text-[#17191F] hover:bg-[#F3D604] cursor-pointer">
                                                {{ $uc['amount'] > $managerConveyanceLimit || !empty($uc['is_late']) ? 'Verify & Escalate →' : 'Approve Claim ✓' }}
                                            </button>
                                            <button type="button" wire:click="rejectClaim('{{ $uc['id'] }}', 'Rejected by Manager.')" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                                Reject
                                            </button>
                                        @elseif($previewRole === 'partner')
                                            <button type="button" wire:click="partnerApproveClaim('{{ $uc['id'] }}', 'Sanctioned by Partner from Dashboard Inbox.')" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-[11px] font-extrabold text-white hover:bg-emerald-700 cursor-pointer">
                                                Sanction ৳{{ number_format($uc['amount'], 0) }} ✓
                                            </button>
                                            <button type="button" wire:click="rejectClaim('{{ $uc['id'] }}', 'Rejected by Partner.')" class="rounded-lg border border-rose-200 bg-white px-2.5 py-1 text-[11px] font-bold text-rose-700 hover:bg-rose-50 cursor-pointer">
                                                Reject
                                            </button>
                                        @endif
                                        <button type="button" wire:click="setSection('conveyance')" class="rounded-lg border border-slate-200 bg-white px-2 py-1 text-[11px] font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                                            Adjust / View
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4 text-center text-xs text-slate-500">
                                ✓ No unread conveyance claims waiting right now.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </section>
    @endif
@endif

{{-- ===================================================================== --}}
{{-- 2. ULTRA-PREMIUM BENTO KPI METRICS STRIP                              --}}
{{-- ===================================================================== --}}
<section class="grid grid-cols-2 gap-3.5 xl:grid-cols-4" aria-label="Practice performance metrics">
    @php
        $metricCards = match ($previewRole) {
            'partner' => [
                ['label'=>'Enrolled Students', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>$partnerUnit . ' active roster', 'accent'=>'from-emerald-500 to-teal-600'],
                ['label'=>'Right Time Today', 'value'=> sprintf('%02d', $onTimeRowsCount), 'icon'=>'check-circle-2', 'note'=>$lateRowsCount . ' Late / Late Present', 'accent'=>'from-blue-500 to-indigo-600'],
                ['label'=>'Pending Leave Queue', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'calendar-clock', 'note'=>'Awaiting Partner sign-off', 'accent'=>'from-amber-500 to-orange-600'],
                ['label'=>'Approved Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>count($claimQueue) . ' claims this month', 'accent'=>'from-purple-500 to-indigo-600'],
            ],
            'manager' => [
                ['label'=>'Supervised Team', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>'Assigned under ' . $partnerUnit, 'accent'=>'from-emerald-500 to-teal-600'],
                ['label'=>'Right Time Today', 'value'=> sprintf('%02d', $onTimeRowsCount), 'icon'=>'clock-3', 'note'=>$lateRowsCount . ' Late arrivals today', 'accent'=>'from-blue-500 to-indigo-600'],
                ['label'=>'Pending Approvals', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'clipboard-check', 'note'=>'Leave & claim reviews', 'accent'=>'from-amber-500 to-orange-600'],
                ['label'=>'Team Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>'Approved monthly total', 'accent'=>'from-purple-500 to-indigo-600'],
            ],
            default => [
                ['label'=>'My Net Stipend', 'value'=> '৳ ' . number_format($myPayrollRow['net_payable'] ?? 0, 0), 'icon'=>'wallet', 'note'=>'Base + Approved Conveyance', 'accent'=>'from-emerald-500 to-teal-600'],
                ['label'=>'Punctuality Score', 'value'=> ($myPunctuality['score'] ?? 100) . '%', 'icon'=>'flame', 'note'=> ($myPunctuality['streak'] ?? 0) . ' Day Right-Time Streak 🔥', 'accent'=>'from-amber-500 to-orange-600'],
                ['label'=>'Approved Conveyance', 'value'=> '৳ ' . number_format($approvedConveyanceSum, 0), 'icon'=>'receipt', 'note'=>count($claimQueue) . ' claims this month', 'accent'=>'from-blue-500 to-indigo-600'],
                ['label'=>'Practice Notices', 'value'=>sprintf('%02d', count($notificationsList ?? [])), 'icon'=>'bell-ring', 'note'=>'Official announcements', 'accent'=>'from-purple-500 to-indigo-600'],
            ],
        };
    @endphp
    @foreach($metricCards as $metric)
        <div wire:key="metric-{{ $metric['label'] }}" class="group relative overflow-hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-sm transition hover:shadow-md">
            <div class="absolute inset-x-0 top-0 h-1 bg-gradient-to-r {{ $metric['accent'] }}"></div>
            <div class="mb-3 flex items-start justify-between gap-2">
                <p class="text-xs font-extrabold uppercase tracking-wider text-slate-500">{{ $metric['label'] }}</p>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800 shadow-inner transition group-hover:scale-105">
                    <i data-lucide="{{ $metric['icon'] }}" class="h-5 w-5"></i>
                </div>
            </div>
            <div class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ $metric['value'] }}</div>
            <p class="mt-1.5 text-[11px] font-medium text-slate-400">{{ $metric['note'] }}</p>
        </div>
    @endforeach
</section>

{{-- ===================================================================== --}}
{{-- 3. INTERACTIVE MONTHLY CALENDAR & DAILY ACTIVITY INSPECTOR            --}}
{{-- ===================================================================== --}}
<section class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(340px,1fr)]">
    {{-- Left: Current Month Interactive Calendar --}}
    <div class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
            <div>
                <div class="flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">INTERACTIVE MONTHLY ACTIVITY CALENDAR</p>
                </div>
                <h3 class="mt-1 text-lg font-black text-slate-900">
                    {{ \DateTimeImmutable::createFromFormat('!Y-m', $calendarMonth ?: now()->format('Y-m'))?->format('F Y') ?? $calendarMonth }}
                </h3>
                <p class="text-xs text-slate-500">Click any date to inspect attendance, client movements, holidays, leaves &amp; conveyance.</p>
            </div>
            <div class="flex items-center gap-1.5">
                <button type="button" wire:click="changeCalendarMonth(-1)" class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-700 hover:bg-slate-100 cursor-pointer" title="Previous Month">
                    <i data-lucide="chevron-left" class="h-4 w-4"></i>
                </button>
                <button type="button" wire:click="selectCalendarDate('{{ now()->toDateString() }}')" class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-extrabold text-slate-800 hover:bg-slate-50 cursor-pointer">
                    Today
                </button>
                <button type="button" wire:click="changeCalendarMonth(1)" class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-700 hover:bg-slate-100 cursor-pointer" title="Next Month">
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
                    <div wire:key="cal-pad-{{ $idx }}" class="h-16 sm:h-20 rounded-2xl bg-slate-50/50"></div>
                @else
                    @php
                        $isSelected = ($selectedCalendarDate ?? '') === $cDay['date'];
                        $hasHoliday = !empty($cDay['holiday']);
                        $isOpenOverride = $hasHoliday && !empty($cDay['holiday']['is_office_open_override']);
                        $cellBg = $isSelected
                            ? 'border-2 border-slate-900 bg-amber-50/80 shadow-md scale-[1.02]'
                            : ($hasHoliday
                                ? ($isOpenOverride ? 'border border-amber-300 bg-amber-50/50' : 'border border-rose-200 bg-rose-50/60')
                                : ($cDay['is_weekend'] ? 'border border-slate-100 bg-slate-50/80' : 'border border-slate-200/80 bg-white hover:border-slate-300 hover:bg-slate-50/50'));
                    @endphp
                    <button type="button"
                            wire:key="cal-day-{{ $cDay['date'] }}"
                            wire:click="selectCalendarDate('{{ $cDay['date'] }}')"
                            class="flex h-16 sm:h-20 flex-col justify-between rounded-2xl p-1.5 sm:p-2 text-left transition cursor-pointer {{ $cellBg }}">
                        <div class="flex items-center justify-between w-full">
                            <span class="inline-flex h-5 w-5 items-center justify-center rounded-lg text-xs font-black {{ $cDay['is_today'] ? 'bg-[#17191F] text-[#FCDF02]' : ($hasHoliday || $cDay['is_weekend'] ? 'text-rose-700' : 'text-slate-800') }}">
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
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Right Time (✓)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-400"></span> Late (L)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span> Late Present (LP)</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-200"></span> Holiday</span>
            <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-indigo-300"></span> Leave (Lv)</span>
        </div>
    </div>

    {{-- Right: Selected Date Daily Activity Inspector --}}
    <div class="rounded-3xl border border-slate-200/90 bg-white p-5 shadow-sm sm:p-6 flex flex-col justify-between">
        <div>
            <div class="flex items-start justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">DAILY ACTIVITY INSPECTOR</p>
                    <h3 class="mt-0.5 text-base font-black text-slate-900">{{ $selectedDayActivity['date_formatted'] ?? $selectedCalendarDate }}</h3>
                </div>
                <span class="rounded-full bg-slate-900 px-2.5 py-1 text-[10px] font-extrabold text-white">
                    {{ $selectedDayActivity['date'] ?? '' }}
                </span>
            </div>

            {{-- Holiday Banner if any --}}
            @if(!empty($selectedDayActivity['holiday']))
                @php $hol = $selectedDayActivity['holiday']; @endphp
                <div class="mt-3 rounded-2xl border p-3.5 text-xs {{ !empty($hol['is_office_open_override']) ? 'border-amber-300 bg-amber-50 text-amber-950' : 'border-rose-200 bg-rose-50 text-rose-900' }}">
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
                <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 font-semibold">
                    Weekly Practice Holiday (Friday / Saturday).
                </div>
            @endif

            {{-- Attendance Logs on Selected Date --}}
            <div class="mt-4">
                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">Attendance &amp; Client Movements ({{ count($selectedDayActivity['attendances'] ?? []) }})</h4>
                <div class="mt-2 space-y-2 max-h-52 overflow-y-auto pr-1">
                    @forelse(($selectedDayActivity['attendances'] ?? []) as $dayAtt)
                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/70 p-3 text-xs">
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
                        <p class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-[11px] text-slate-400">No attendance logs recorded on this date.</p>
                    @endforelse
                </div>
            </div>

            {{-- Conveyance & Leaves on Selected Date --}}
            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-3">
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
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50/50 p-3">
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
                <button type="button" wire:click="setSection('policies')" class="font-bold text-amber-800 hover:underline cursor-pointer">Manage Holidays →</button>
            @endif
        </div>
    </div>
</section>

{{-- ===================================================================== --}}
{{-- 4. LIVE FIELD RADAR, CLIENT COSTING & PUNCTUALITY LEADERBOARD          --}}
{{-- ===================================================================== --}}
<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(300px,1fr)]">
    <!-- Main Left Column -->
    <div class="space-y-5">
        @if($previewRole !== 'student')
            {{-- Live Team Radar ("কে কোথায় আছে" — Office vs Client Site vs On Duty Transit) --}}
            <section class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">LIVE FIELD RADAR · REAL-TIME LOCATION MATRIX</p>
                        <h3 class="mt-0.5 text-base font-black text-slate-900">Who's Where Right Now ({{ $partnerUnit }})</h3>
                    </div>
                    <button type="button" wire:click="setSection('attendance')" class="text-xs font-bold text-amber-800 hover:underline cursor-pointer">Open Full Register →</button>
                </div>
                <div class="mt-4 grid gap-3.5 sm:grid-cols-3 text-xs">
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4">
                        <div class="flex items-center justify-between font-extrabold text-emerald-950">
                            <span>🏢 Principal Office HQ</span>
                            <span class="rounded-full bg-emerald-200 px-2.5 py-0.5 text-[10px]">{{ count($teamRadar['at_office'] ?? []) }}</span>
                        </div>
                        <div class="mt-2.5 space-y-1.5">
                            @forelse(($teamRadar['at_office'] ?? []) as $mOff)
                                <div class="rounded-xl bg-white p-2.5 border border-emerald-100 flex items-center justify-between shadow-2xs">
                                    <span class="font-bold text-slate-800 truncate">{{ $mOff['name'] }}</span>
                                    <span class="text-[10px] font-mono font-bold text-emerald-700">{{ $mOff['checkin'] }}</span>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400 py-1">No active office check-ins yet.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-4">
                        <div class="flex items-center justify-between font-extrabold text-indigo-950">
                            <span>📍 Client Audit Sites</span>
                            <span class="rounded-full bg-indigo-200 px-2.5 py-0.5 text-[10px]">{{ count($teamRadar['at_client'] ?? []) }}</span>
                        </div>
                        <div class="mt-2.5 space-y-1.5">
                            @forelse(($teamRadar['at_client'] ?? []) as $mCli)
                                <div class="rounded-xl bg-white p-2.5 border border-indigo-100 shadow-2xs">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-slate-800 truncate">{{ $mCli['name'] }}</span>
                                        <span class="text-[10px] font-mono font-bold text-indigo-700">{{ $mCli['checkin'] }}</span>
                                    </div>
                                    <p class="truncate text-[10px] font-semibold text-indigo-900 mt-0.5">{{ $mCli['client_site'] ?: $mCli['in_location'] }}</p>
                                </div>
                            @empty
                                <p class="text-[11px] text-slate-400 py-1">No students at client sites right now.</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-2xl border border-amber-200 bg-amber-50/50 p-4">
                        <div class="flex items-center justify-between font-extrabold text-amber-950">
                            <span>🚕 On Duty (In Transit)</span>
                            <span class="rounded-full bg-amber-200 px-2.5 py-0.5 text-[10px]">{{ count($teamRadar['in_transit'] ?? []) }}</span>
                        </div>
                        <div class="mt-2.5 space-y-1.5">
                            @forelse(($teamRadar['in_transit'] ?? []) as $mTr)
                                <div class="rounded-xl bg-white p-2.5 border border-amber-100 shadow-2xs">
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

            {{-- Client Audit Costing & Man-Hour Analytics --}}
            <section class="overflow-hidden rounded-3xl border border-slate-200/90 bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-6 py-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">AUDIT ENGAGEMENT ANALYTICS</p>
                        <h3 class="text-sm font-black text-slate-900">Client-Wise Man-Hours &amp; Conveyance Costing ({{ $period }})</h3>
                    </div>
                    <button type="button" wire:click="setSection('conveyance')" class="text-xs font-bold text-amber-800 hover:underline cursor-pointer">Audit Claims →</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 text-[10px] font-extrabold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-6 py-3">Client / Engagement Site</th>
                                <th class="px-4 py-3">Logged Visits</th>
                                <th class="px-4 py-3">Est. Man-Hours</th>
                                <th class="px-6 py-3 text-right">Conveyance Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse(($clientCostingSummary ?? []) as $cStat)
                                <tr class="hover:bg-slate-50/70">
                                    <td class="px-6 py-3.5 font-bold text-slate-800">{{ $cStat['client'] }}</td>
                                    <td class="px-4 py-3.5 text-slate-600">{{ $cStat['visits'] }} visit(s)</td>
                                    <td class="px-4 py-3.5 font-semibold text-slate-700">{{ number_format($cStat['hours'], 1) }} hrs</td>
                                    <td class="px-6 py-3.5 text-right font-extrabold text-emerald-800">৳ {{ number_format($cStat['conveyance'], 0) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-6 text-center text-xs text-slate-400">Client audit hours and conveyance costs populate automatically as team members check in and submit claims.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if($previewRole === 'student' && ($studentGrids['payroll_allowance'] ?? true))
            <section class="rounded-3xl border border-emerald-200 bg-gradient-to-r from-emerald-50/90 via-teal-50/60 to-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-extrabold text-emerald-900 uppercase tracking-wider">Automatic Monthly Allowance ({{ $period }})</span>
                        <h3 class="mt-2 text-xl font-black text-slate-900">Net Payable: ৳ {{ number_format($myPayrollRow['net_payable'] ?? 0, 0) }}</h3>
                        <p class="mt-1 text-xs text-slate-600">
                            Base Stipend: <strong>৳ {{ number_format($myPayrollRow['base_allowance'] ?? 0, 0) }}</strong> ·
                            Approved Conveyance: <strong>৳ {{ number_format($myPayrollRow['approved_conveyance'] ?? 0, 0) }}</strong> ·
                            Late Deduction: <strong>৳ {{ number_format($myPayrollRow['adjustment'] ?? 0, 0) }}</strong>
                        </p>
                    </div>
                    <button type="button" wire:click="setSection('payroll')" class="rounded-2xl bg-emerald-700 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-emerald-800 cursor-pointer">View Statement &amp; Voucher</button>
                </div>
            </section>
        @endif

        @if($previewRole === 'student' && ($studentGrids['client_site_duty'] ?? true))
            <section class="rounded-3xl border border-indigo-200 bg-gradient-to-r from-indigo-50/80 via-slate-50 to-white p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-[10px] font-extrabold text-indigo-900 uppercase tracking-wider">Current Duty Engagement</span>
                        <h3 class="mt-2 text-base font-black text-slate-900">Assigned Site: {{ $clientSite ?: 'General Office / Firm HQ' }}</h3>
                        <p class="mt-1 text-xs text-slate-600">Location: {{ $currentLocationName ?: ($firm['office'] ?? 'Dhaka, Bangladesh') }} · Supervisor: {{ auth()->user()?->manager?->name ?? ($rows[0]['manager'] ?? 'Assigned Manager') }}</p>
                    </div>
                    <button type="button" wire:click="setSection('conveyance')" class="rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-indigo-700 cursor-pointer">Submit Conveyance Claim</button>
                </div>
            </section>
        @endif
    </div>

    <!-- Right Sidebar Column -->
    <div class="space-y-5">
        {{-- Punctuality Score & On-Time Streak Leaderboard --}}
        <section class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">PUNCTUALITY LEADERBOARD</p>
                    <h3 class="text-sm font-black text-slate-900">Right-Time Score &amp; Streak</h3>
                </div>
                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-extrabold text-emerald-800">🔥 {{ $period }}</span>
            </div>
            <div class="mt-3.5 space-y-2.5">
                @forelse(array_slice($punctualityLeaderboard ?? [], 0, 5) as $idx => $pItem)
                    <div class="flex items-center justify-between gap-2 rounded-2xl border border-slate-100 bg-slate-50/70 p-3 text-xs">
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
                            <span class="inline-block rounded-full px-2.5 py-0.5 text-[11px] font-black {{ $pItem['score'] >= 90 ? 'bg-emerald-100 text-emerald-800' : ($pItem['score'] >= 70 ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-800') }}">
                                {{ $pItem['score'] }}%
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="py-4 text-center text-xs text-slate-400">Punctuality scores appear as team members record daily check-ins.</p>
                @endforelse
            </div>
        </section>

        @if($previewRole !== 'student' || ($studentGrids['firm_notices'] ?? true))
            <section class="rounded-3xl border border-slate-800 bg-[#17191F] p-5 sm:p-6 text-white shadow-lg">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-extrabold">{{ $partnerUnit }} Policy Rules</h3>
                    <i data-lucide="shield-check" class="h-5 w-5 text-[#FCDF02]"></i>
                </div>
                <p class="mt-1.5 text-xs leading-relaxed text-slate-300">Active 3-tier arrival windows and automatic salary deduction thresholds.</p>
                <div class="mt-4 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Right Time Window</span><strong class="text-emerald-400">&le; {{ $officeStart }}</strong></div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Grace (Late) Window</span><strong class="text-amber-300">{{ $officeStart }} – {{ $graceEndFormatted ?? '09:40' }}</strong></div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2"><span class="text-slate-400">Late Present Window</span><strong class="text-rose-400">&gt; {{ $graceEndFormatted ?? '09:40' }}</strong></div>
                    <div class="flex items-center justify-between gap-2"><span class="text-slate-400">Late Deduction Rule</span><strong>{{ $lateDaysPerDeduction }} Late = {{ $deductionDaysCount }} Day Cut</strong></div>
                </div>
                <button wire:click="setSection('settings')" class="mt-4 text-xs font-bold text-[#FCDF02] hover:underline cursor-pointer">Configure policy rules →</button>
            </section>
        @endif

        <section class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm">
            <h3 class="text-sm font-black text-slate-900">Quick Workspace Navigation</h3>
            <div class="mt-3.5 grid grid-cols-2 gap-2.5">
                @foreach ([
                    ['name'=>'Leave', 'section'=>'leave', 'icon'=>'calendar-plus'],
                    ['name'=>'Conveyance', 'section'=>'conveyance', 'icon'=>'receipt'],
                    ['name'=>'Payroll', 'section'=>'payroll', 'icon'=>'wallet'],
                    ['name'=>'Policies', 'section'=>'policies', 'icon'=>'shield']
                ] as $shortcut)
                    <button type="button" wire:click="setSection('{{ $shortcut['section'] }}')" wire:key="shortcut-{{ $shortcut['section'] }}" class="flex flex-col items-center gap-2 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3.5 text-center text-xs font-bold text-slate-700 transition hover:border-amber-400 hover:bg-amber-50 cursor-pointer">
                        <i data-lucide="{{ $shortcut['icon'] }}" class="h-5 w-5 text-amber-800"></i>{{ $shortcut['name'] }}
                    </button>
                @endforeach
            </div>
        </section>
    </div>
</div>