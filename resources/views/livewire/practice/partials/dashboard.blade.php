<section class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">{{ $previewRole === 'student' ? 'MY WORKSPACE' : ($previewRole === 'manager' ? 'TEAM OPERATIONS' : 'PARTNER PRACTICE') }}</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-[#20232A] sm:text-3xl">
            @if($previewRole === 'partner') Your practice, in focus.
            @elseif($previewRole === 'manager') Your team, aligned.
            @else Your day, organized.
            @endif
        </h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">
            @if($previewRole === 'partner') Example Partner Unit 01 dashboard. Records from other Partners are deliberately excluded from this screen.
            @elseif($previewRole === 'manager') Example view of only Sample Manager A's assigned students under their home Partner.
            @else A personal-first experience for an articled student. Only your own demo attendance, leave and claim rows appear.
            @endif
        </p>
    </div>
    <div class="flex flex-wrap gap-2">
        <button type="button" wire:click="setSection('attendance')" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#16181D] transition hover:bg-[#F3D604]">
            <i data-lucide="calendar-check-2" class="h-4 w-4"></i>{{ $previewRole === 'student' ? 'Open My Attendance' : 'View Attendance' }}
        </button>
        <button type="button" wire:click="setSection('notifications')" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-bold text-slate-700 hover:bg-slate-50">
            <i data-lucide="bell" class="h-4 w-4"></i> Sample Updates
        </button>
    </div>
</section>

<section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Fictional dashboard metrics">
    @php
        $demoMetrics = match ($previewRole) {
            'partner' => [
                ['label'=>'Demo Students', 'value'=>'04', 'icon'=>'users', 'note'=>'Partner Unit 01 sample only'],
                ['label'=>'On-time Samples', 'value'=>'02', 'icon'=>'clock-3', 'note'=>'Not a current live count'],
                ['label'=>'Leave Previews', 'value'=>'02', 'icon'=>'calendar-days', 'note'=>'Unprocessed example entries'],
                ['label'=>'Claim Previews', 'value'=>'03', 'icon'=>'receipt', 'note'=>'No amounts paid'],
            ],
            'manager' => [
                ['label'=>'My Demo Team', 'value'=>'02', 'icon'=>'users', 'note'=>'Assigned manager scope only'],
                ['label'=>'On-time Samples', 'value'=>'01', 'icon'=>'clock-3', 'note'=>'Historical demonstration'],
                ['label'=>'Pending Review', 'value'=>'02', 'icon'=>'clipboard-check', 'note'=>'No real approvals'],
                ['label'=>'Open Tasks', 'value'=>'03', 'icon'=>'list-todo', 'note'=>'Illustrative tasks only'],
            ],
            default => [
                ['label'=>'My Demo Attendance', 'value'=>'01', 'icon'=>'calendar-check-2', 'note'=>'One sample attendance day'],
                ['label'=>'Example Leave Days', 'value'=>'02', 'icon'=>'calendar-days', 'note'=>'Not an actual entitlement'],
                ['label'=>'Claim Examples', 'value'=>'01', 'icon'=>'receipt', 'note'=>'No reimbursement due'],
                ['label'=>'Unread Examples', 'value'=>'03', 'icon'=>'bell', 'note'=>'Local preview notifications'],
            ],
        };
    @endphp
    @foreach($demoMetrics as $metric)
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
    <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-5">
            <div><h3 class="text-sm font-extrabold text-slate-900">{{ $previewRole === 'student' ? 'My Attendance Snapshot' : 'Team Attendance Snapshot' }}</h3><p class="mt-1 text-xs text-slate-500">Fictional office and client engagement activity</p></div>
            <button type="button" wire:click="setSection('attendance')" class="text-xs font-bold text-amber-800 hover:underline">View all →</button>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($previewRole === 'partner' ? $allRows : array_values(array_filter($allRows, fn($r) => $previewRole === 'student' ? $r['id'] === 'S-101' : $r['manager'] === 'Sample Manager A')) as $item)
                <div wire:key="dash-row-{{ $item['id'] }}" class="flex items-center gap-3 px-5 py-4">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-xs font-extrabold text-slate-700">{{ substr($item['id'], -3) }}</div>
                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-bold text-slate-800">{{ $item['name'] }}</p><p class="mt-0.5 text-xs text-slate-500">{{ $item['mode'] }} · {{ $item['day'] }} · {{ $item['checkin'] }}</p></div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold {{ $item['status'] === 'On time' ? 'bg-emerald-50 text-emerald-700' : ($item['status'] === 'Late' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $item['status'] }}</span>
                </div>
            @endforeach
        </div>
    </section>
    <div class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-extrabold">Practice Rules · Demo</h3>
                <i data-lucide="shield-check" class="h-5 w-5 text-[#FCDF02]"></i>
            </div>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Company default policy and Partner permitted overrides are separated.</p>
            <div class="mt-4 space-y-3">
                <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 text-xs"><span class="text-slate-400">Office Hours</span><strong>10:00 – 18:00</strong></div>
                <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-2 text-xs"><span class="text-slate-400">Late Grace</span><strong>10 minutes</strong></div>
                <div class="flex items-center justify-between gap-2 text-xs"><span class="text-slate-400">Weekly Holidays</span><strong>Fri & Sat</strong></div>
            </div>
            <p class="mt-4 text-[11px] leading-relaxed text-amber-200">10:10:59 or earlier is on time; 10:11 onward is late in the planned policy.</p>
            <button wire:click="setSection('policies')" class="mt-4 text-xs font-bold text-[#FCDF02] hover:underline">View governance →</button>
        </section>
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