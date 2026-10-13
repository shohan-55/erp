<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">ATTENDANCE · PHASE 07</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Office & Client Attendance</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">One consolidated day, one active session, no duplicate hours. All the records below are fictional design samples.</p>
    </div>
    @if($previewRole !== 'student')
        <button wire:click="openDialog('correction')" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]">
            <i data-lucide="pencil-line" class="h-4 w-4"></i> Correction Review (Preview)
        </button>
    @endif
</section>

@if($previewRole === 'student')
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1.25fr)_minmax(270px,1fr)]">
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div><p class="text-xs font-extrabold uppercase tracking-wider text-amber-800">PERSONAL ATTENDANCE</p><h3 class="mt-1 text-lg font-extrabold">Today's Check-in Preview</h3></div>
                <span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $previewSessionActive ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">{{ $previewSessionActive ? 'Demo Session Active' : 'No Demo Session' }}</span>
            </div>
            <p class="mt-3 text-xs leading-relaxed text-slate-500">Choose a work mode and preview the check-in/check-out states. This does not use server time, device location or the database.</p>
            <div class="mt-5 grid grid-cols-2 gap-3" aria-label="Attendance mode">
                @foreach(['office'=>'Office','client'=>'Client Site'] as $mode => $label)
                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 transition {{ $workMode === $mode ? 'border-amber-400 bg-amber-50' : 'border-slate-200 bg-white' }}">
                        <input type="radio" wire:model.change="workMode" value="{{ $mode }}" name="attendance_mode" @disabled($previewSessionActive) class="accent-amber-600">
                        <span class="text-xs font-bold text-slate-800">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            @if($workMode === 'client')
                <label class="mt-4 block text-xs font-bold text-slate-700">Client Site (Demo Label)
                    <input type="text" wire:model="clientSite" @disabled($previewSessionActive) class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm outline-none focus:border-amber-400" placeholder="Example client site">
                </label>
            @endif
            <button type="button" wire:click="togglePreviewSession" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl px-5 py-3.5 text-sm font-extrabold transition {{ $previewSessionActive ? 'bg-[#17191F] text-white hover:bg-slate-700' : 'bg-[#FCDF02] text-[#17191F] hover:bg-[#F3D604]' }}">
                <i data-lucide="{{ $previewSessionActive ? 'log-out' : 'log-in' }}" class="h-5 w-5"></i>{{ $previewSessionActive ? 'Simulate Check-out' : 'Simulate Check-in' }}
            </button>
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">No actual attendance event or working hours have been recorded. Only one demo session can be active at a time.</div>
        </section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-6 text-white shadow-sm">
            <div class="flex items-center justify-between"><h3 class="text-sm font-extrabold">Attendance Policy</h3><i data-lucide="clock" class="h-5 w-5 text-[#FCDF02]"></i></div>
            <div class="mt-6 space-y-4">
                @foreach([
                    ['label'=>'Start time','value'=>'10:00 AM'],
                    ['label'=>'End time','value'=>'6:00 PM'],
                    ['label'=>'Grace','value'=>'10 minutes'],
                    ['label'=>'Holiday','value'=>'Fri & Sat'],
                    ['label'=>'Timezone','value'=>'Asia/Dhaka']
                ] as $item)
                    <div class="flex items-center justify-between gap-2 border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">{{ $item['label'] }}</span><strong>{{ $item['value'] }}</strong></div>
                @endforeach
            </div>
            <p class="mt-5 text-xs leading-relaxed text-amber-200">Late after 10:10:59. Missing check-out remains incomplete; no automatic salary deduction until an explicit policy is approved.</p>
        </section>
    </div>
@endif

<section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-100 p-5 sm:flex-row sm:items-center sm:justify-between">
        <div><h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Sample Attendance' : 'Attendance Records · Scoped Preview' }}</h3><p class="mt-1 text-xs text-slate-500">Office / Client · Partner Unit 01 sample data</p></div>
        <div class="flex flex-col gap-2 sm:flex-row">
            <label class="relative block"><span class="sr-only">Search demo attendance</span><i data-lucide="search" class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400"></i><input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or code" class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-xs outline-none focus:border-amber-400 sm:w-52"></label>
            <select wire:model.change="filter" aria-label="Filter attendance status" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs outline-none focus:border-amber-400"><option value="all">All statuses</option><option value="on-time">On time</option><option value="late">Late</option><option value="incomplete">Incomplete</option></select>
        </div>
    </div>
    <div class="hidden overflow-x-auto md:block">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead class="bg-slate-50 text-[11px] font-bold uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-4 py-3">Workplace</th><th class="px-4 py-3">Check-in</th><th class="px-4 py-3">Check-out</th><th class="px-4 py-3">Status</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($rows as $item)
                    <tr wire:key="attendance-{{ $item['id'] }}" class="hover:bg-slate-50">
                        <td class="px-5 py-4"><div class="font-bold text-slate-800">{{ $item['name'] }}</div><div class="mt-1 text-xs text-slate-400">{{ $item['id'] }} · {{ $item['day'] }}</div></td>
                        <td class="px-4 py-4 text-slate-600">{{ $item['mode'] }}</td>
                        <td class="px-4 py-4 font-semibold text-slate-700">{{ $item['checkin'] }}</td>
                        <td class="px-4 py-4 font-semibold text-slate-700">{{ $item['checkout'] }}</td>
                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $item['status'] === 'On time' ? 'bg-emerald-50 text-emerald-700' : ($item['status'] === 'Late' ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600') }}">{{ $item['status'] }} · Demo</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-slate-500">No sample attendance matches your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="divide-y divide-slate-100 md:hidden">
        @forelse($rows as $item)
            <div wire:key="mobile-att-{{ $item['id'] }}" class="p-4">
                <div class="flex items-center justify-between gap-3"><p class="text-sm font-bold">{{ $item['name'] }}</p><span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">{{ $item['status'] }}</span></div>
                <p class="mt-2 text-xs text-slate-500">{{ $item['id'] }} · {{ $item['mode'] }} · {{ $item['day'] }}</p>
                <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-xs"><span>In: <strong>{{ $item['checkin'] }}</strong></span><span>Out: <strong>{{ $item['checkout'] }}</strong></span></div>
            </div>
        @empty
            <p class="p-10 text-center text-xs text-slate-500">No demo attendance found.</p>
        @endforelse
    </div>
</section>