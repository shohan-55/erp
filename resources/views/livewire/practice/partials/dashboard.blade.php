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
            @if($previewRole === 'partner') Autonomous practice operations for {{ $partnerUnit }}. Multi-partner zero-trust boundary active.
            @elseif($previewRole === 'manager') Team supervision and operational workflows for assigned articled students under {{ $partnerUnit }}.
            @else Personal attendance, leave allowance, and client conveyance ledger.
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setSection('attendance')" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#16181D] transition hover:bg-[#F3D604]">
            <i data-lucide="calendar-check-2" class="h-4 w-4"></i>{{ $previewRole === 'student' ? 'Open My Attendance' : 'View Attendance' }}
        </button>
        <button type="button" wire:click="setSection('notifications')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <i data-lucide="bell" class="h-4 w-4"></i> Updates
        </button>
    </div>
</section>

<!-- KPI Metrics Grid -->
<section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Practice performance metrics">
    @php
        $metricCards = match ($previewRole) {
            'partner' => [
                ['label'=>'Active Students', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>$partnerUnit . ' scope'],
                ['label'=>'Present Today', 'value'=> sprintf('%02d', count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'On time'))), 'icon'=>'clock-3', 'note'=>'Live check-ins'],
                ['label'=>'Pending Leaves', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'calendar-days', 'note'=>'Queue for approval'],
                ['label'=>'Conveyance Claims', 'value'=> sprintf('%02d', count($claimQueue)), 'icon'=>'receipt', 'note'=>'Awaiting verification'],
            ],
            'manager' => [
                ['label'=>'Assigned Team', 'value'=> sprintf('%02d', count($rows)), 'icon'=>'users', 'note'=>'Manager supervision'],
                ['label'=>'Checked In', 'value'=> sprintf('%02d', count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'On time'))), 'icon'=>'clock-3', 'note'=>'Today\'s arrivals'],
                ['label'=>'Pending Approvals', 'value'=> sprintf('%02d', count($leaveQueue)), 'icon'=>'clipboard-check', 'note'=>'Action required'],
                ['label'=>'Open Audits', 'value'=> sprintf('%02d', count($claimQueue)), 'icon'=>'list-todo', 'note'=>'Audit site assignments'],
            ],
            default => [
                ['label'=>'My Attendance', 'value'=> sprintf('%02d', count(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'On time'))), 'icon'=>'calendar-check-2', 'note'=>'Recorded today'],
                ['label'=>'Leave Balance', 'value'=>'14', 'icon'=>'calendar-days', 'note'=>'Days available'],
                ['label'=>'Pending Claims', 'value'=> sprintf('%02d', count($claimQueue)), 'icon'=>'receipt', 'note'=>'Submitted this month'],
                ['label'=>'Firm Notices', 'value'=>'03', 'icon'=>'bell', 'note'=>'Practice announcements'],
            ],
        };
    @endphp
    @foreach($metricCards as $metric)
        <div wire:key="metric-{{ $metric['label'] }}" class="min-w-0 rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-4 flex items-start justify-between gap-2">
                <p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p>
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="{{ $metric['icon'] }}" class="h-[18px] w-[18px]"></i></div>
            </div>
            <div class="text-3xl font-extrabold tracking-tight text-[#17191F]">{{ $metric['value'] }}</div>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">{{ $metric['note'] }}</p>
        </div>
    @endforeach
</section>

<div class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.65fr)_minmax(270px,1fr)]">
    <!-- Main Left Column -->
    <div class="space-y-5">
        @if($previewRole !== 'student' || ($studentGrids['attendance_clock'] ?? true))
            <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-5">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">{{ $previewRole === 'student' ? 'My Attendance Snapshot' : 'Team Attendance Snapshot' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $partnerUnit }} · Daily office and client site audit activity</p>
                    </div>
                    <button type="button" wire:click="setSection('attendance')" class="text-xs font-bold text-amber-800 hover:underline">View all →</button>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse ($rows as $item)
                        <div wire:key="dash-row-{{ $item['id'] }}" class="flex items-center gap-3 px-5 py-4">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-extrabold text-slate-700">{{ substr($item['id'], -3) }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-bold text-slate-800">{{ $item['name'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500">{{ $item['mode'] }} · {{ $item['day'] }} · In: {{ $item['checkin'] }} | Out: {{ $item['checkout'] }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $item['status'] === 'On time' ? 'bg-emerald-50 text-emerald-700' : ($item['status'] === 'Late' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $item['status'] }}</span>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-xs text-slate-400">
                            <i data-lucide="calendar-x-2" class="mx-auto mb-2 h-6 w-6 text-slate-300"></i>
                            No attendance records logged for today yet. Team check-ins will appear here in real-time.
                        </div>
                    @endforelse
                </div>
            </section>
        @endif

        @if($previewRole === 'student' && ($studentGrids['client_site_duty'] ?? true))
            <section class="rounded-2xl border border-indigo-100 bg-indigo-50/50 p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-bold text-indigo-800 uppercase tracking-wider">Current Engagement</span>
                        <h3 class="mt-1.5 text-sm font-extrabold text-slate-900">Assigned Client: BEXIMCO Textiles Audit</h3>
                        <p class="mt-1 text-xs text-slate-600">Location: Dhanmondi R/A, Dhaka · Field Audit Lead: Khandaker Tanvir Ahmed, ACA</p>
                    </div>
                    <button type="button" wire:click="setSection('conveyance')" class="rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700">Submit Fare</button>
                </div>
            </section>
        @endif
    </div>

    <!-- Right Sidebar Column -->
    <div class="space-y-5">
        @if($previewRole !== 'student' || ($studentGrids['firm_notices'] ?? true))
            <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-extrabold">{{ $partnerUnit }} Rules · Active</h3>
                    <i data-lucide="shield-check" class="h-5 w-5 text-[#FCDF02]"></i>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-300">Company default policy and Partner permitted overrides are separated.</p>
                <div class="mt-4 space-y-3">
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 text-xs"><span class="text-slate-400">Office Hours</span><strong>{{ $officeStart }} – {{ $officeEnd }}</strong></div>
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 text-xs"><span class="text-slate-400">Late Grace</span><strong>{{ $graceMinutes }} minutes</strong></div>
                    <div class="flex items-center justify-between gap-2 text-xs"><span class="text-slate-400">Weekly Holidays</span><strong>Fri & Sat</strong></div>
                </div>
                <p class="mt-4 text-[11px] leading-relaxed text-amber-200">Grace threshold: arrivals within {{ $graceMinutes }} min of {{ $officeStart }} are recorded on time.</p>
                <button wire:click="setSection('policies')" class="mt-4 text-xs font-bold text-[#FCDF02] hover:underline">View governance →</button>
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