<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">MONTHLY ALLOWANCE & PAYROLL</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Payroll Workspace</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Manage monthly student allowances, approved conveyance disbursements, and attendance reconciliation for ICAB audit students.</p>
    </div>
    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700">Payroll Period
        <select wire:model.change="period" class="rounded-md border-0 bg-slate-50 p-1 text-xs font-bold outline-none focus:ring-2 focus:ring-amber-200">
            <option value="2026-10">October 2026</option><option value="2026-09">September 2026</option><option value="2026-08">August 2026</option>
        </select>
    </label>
</section>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
        ['label'=>$previewRole === 'student' ? 'Base Allowance' : 'Gross Allowance', 'value'=>$previewRole === 'student' ? '৳ 8,000' : ($previewRole === 'manager' ? '৳ 16,000' : '৳ 32,000'), 'note'=>'ICAB stipend scale', 'icon'=>'wallet'],
        ['label'=>'Approved Conveyance', 'value'=>$previewRole === 'student' ? '৳ 220' : '৳ 385', 'note'=>'Audit site travel claims', 'icon'=>'receipt'],
        ['label'=>'Attendance Adjustments', 'value'=>'৳ 0', 'note'=>'Subject to partner policy', 'icon'=>'scale'],
        ['label'=>'Ledger Status', 'value'=>'Draft Ledger', 'note'=>'Open for reconciliation', 'icon'=>'file-lock'],
    ] as $metric)
        <div wire:key="payroll-metric-{{ $loop->index }}" class="min-w-0 rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-3 flex justify-between gap-2"><p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p><i data-lucide="{{ $metric['icon'] }}" class="h-[19px] w-[19px] shrink-0 text-amber-800"></i></div>
            <p class="break-words text-xl font-extrabold tracking-tight sm:text-2xl">{{ $metric['value'] }}</p>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">{{ $metric['note'] }}</p>
        </div>
    @endforeach
</div>

<div class="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Monthly Allowance Statement' : 'Monthly Practice Payroll Register' }}</h3><p class="mt-1 text-xs text-slate-500">Student stipends and approved conveyance reconciliation</p></div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">DRAFT LEDGER</span>
            </div>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[630px] text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-4 py-3">Base Stipend</th><th class="px-4 py-3">Approved Travel</th><th class="px-4 py-3">Adjustments</th><th class="px-5 py-3">State</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($rows as $item)
                        <tr wire:key="payroll-{{ $item['id'] }}"><td class="px-5 py-4"><p class="font-bold">{{ $item['name'] }}</p><p class="mt-1 text-xs text-slate-400">{{ $item['id'] }}</p></td><td class="px-4 py-4 font-semibold">৳ 8,000</td><td class="px-4 py-4 text-slate-600">৳ 220</td><td class="px-4 py-4 text-slate-600">None</td><td class="px-5 py-4"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">Draft Ledger</span></td></tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-xs text-slate-400">No students found in current practice ledger.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($rows as $item)
                <div wire:key="mobile-pay-{{ $item['id'] }}" class="p-4">
                    <div class="flex justify-between gap-3"><div><p class="text-sm font-bold">{{ $item['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $item['id'] }}</p></div><span class="h-fit rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">Draft Ledger</span></div>
                    <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-xs"><div><p class="text-slate-400">Base Allowance</p><p class="mt-1 font-bold">৳ 8,000</p></div><div><p class="text-slate-400">Approved Claim</p><p class="mt-1 font-bold">৳ 220</p></div></div>
                </div>
            @empty
                <p class="p-6 text-center text-xs text-slate-400">No students found.</p>
            @endforelse
        </div>
        <div class="border-t border-slate-100 bg-slate-50 p-4 text-[11px] leading-relaxed text-slate-600">
            Allowance figures are calculated under ICAB practice standards and reconciled with verified geo-attendance logs.
            <span class="sr-only">NOT A PAYSLIP · No real payroll records</span>
        </div>
    </section>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">Payroll Lifecycle (Planned)</h3>
            <div class="mt-5 space-y-4">
                @foreach ([
                    ['01','Generate draft','Policy version and attendance snapshot'],
                    ['02','Review','Manager / authorized Partner review'],
                    ['03','Approve','Record exact approval and immutable totals'],
                    ['04','Mark paid','Only after verified disbursement'],
                    ['05','Lock','Prevent retroactive mutation of finalized period'],
                ] as $step)
                    <div class="flex items-start gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#FCDF02]/15 text-xs font-extrabold text-[#FCDF02]">{{ $step[0] }}</span>
                        <div><p class="text-xs font-bold">{{ $step[1] }}</p><p class="mt-1 text-[11px] leading-relaxed text-slate-400">{{ $step[2] }}</p></div>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="lock-keyhole" class="h-5 w-5"></i></div>
                <div><h3 class="text-sm font-extrabold">Financial Integrity</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Partner ownership, rule effective dates, approved claim reconciliation, race safety, no duplicate payment, and locked payroll snapshots must be implemented and audited in the backend.</p></div>
            </div>
            <button type="button" wire:click="setSection('policies')" class="mt-4 text-xs font-bold text-amber-800 hover:underline">Review policy framework →</button>
        </section>
    </aside>
</div>