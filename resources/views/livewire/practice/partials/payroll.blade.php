<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">MONTHLY ALLOWANCE & PAYROLL · PHASE 10</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Payroll Workspace</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Snapshot-based allowance calculations and approved conveyance reimbursement will be part of a future backend. This screen uses invented demonstration amounts only.</p>
    </div>
    <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700">Sample Period
        <select wire:model.change="period" class="rounded-md border-0 bg-slate-50 p-1 text-xs font-bold outline-none focus:ring-2 focus:ring-amber-200">
            <option value="2026-10">October 2026</option><option value="2026-09">September 2026</option><option value="2026-08">August 2026</option>
        </select>
    </label>
</section>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
        ['label'=>$previewRole === 'student' ? 'Sample Allowance' : 'Sample Gross Allowance', 'value'=>$previewRole === 'student' ? '৳ 8,000' : ($previewRole === 'manager' ? '৳ 16,000' : '৳ 32,000'), 'note'=>'Fictional base amount', 'icon'=>'wallet'],
        ['label'=>'Example Reimbursement', 'value'=>$previewRole === 'student' ? '৳ 220' : '৳ 385', 'note'=>'NOT payable / confirmed', 'icon'=>'receipt'],
        ['label'=>'Sample Adjustments', 'value'=>'৳ 0', 'note'=>'No automatic lateness deductions', 'icon'=>'scale'],
        ['label'=>'Payroll Status', 'value'=>'Draft UI', 'note'=>'No real payroll records', 'icon'=>'file-lock'],
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
                <div><h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Sample Allowance Statement' : 'Monthly Sample Payroll Register' }}</h3><p class="mt-1 text-xs text-slate-500">Illustrative only · No deductions, payment instructions or accounting entries</p></div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">NOT A PAYSLIP</span>
            </div>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[630px] text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500"><tr><th class="px-5 py-3">Student</th><th class="px-4 py-3">Allowance Example</th><th class="px-4 py-3">Claim Example</th><th class="px-4 py-3">Adjustments</th><th class="px-5 py-3">State</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($rows as $item)
                        <tr wire:key="payroll-{{ $item['id'] }}"><td class="px-5 py-4"><p class="font-bold">{{ $item['name'] }}</p><p class="mt-1 text-xs text-slate-400">{{ $item['id'] }}</p></td><td class="px-4 py-4 font-semibold">৳ 8,000</td><td class="px-4 py-4 text-slate-600">৳ 220</td><td class="px-4 py-4 text-slate-600">Not configured</td><td class="px-5 py-4"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">Draft Sample</span></td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @foreach($rows as $item)
                <div wire:key="mobile-pay-{{ $item['id'] }}" class="p-4">
                    <div class="flex justify-between gap-3"><div><p class="text-sm font-bold">{{ $item['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $item['id'] }}</p></div><span class="h-fit rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">Demo Draft</span></div>
                    <div class="mt-3 grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-3 text-xs"><div><p class="text-slate-400">Example Allowance</p><p class="mt-1 font-bold">৳ 8,000</p></div><div><p class="text-slate-400">Example Claim</p><p class="mt-1 font-bold">৳ 220</p></div></div>
                </div>
            @endforeach
        </div>
        <div class="border-t border-amber-100 bg-amber-50 p-4 text-[11px] leading-relaxed text-amber-900">
            These amounts are UI placeholders and do not reflect any real RA&C student's stipend or an entitlement. No salary or claim payment has occurred.
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
            <div class="flex gap-3"><div class="rounded-xl bg-amber-50 p-2.5 text-amber-800"><i data-lucide="lock-keyhole" class="h-5 w-5"></i></div>
                <div><h3 class="text-sm font-extrabold">Financial Integrity</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Partner ownership, rule effective dates, approved claim reconciliation, race safety, no duplicate payment, and locked payroll snapshots must be implemented and audited in the backend.</p></div>
            </div>
            <button type="button" wire:click="setSection('policies')" class="mt-4 text-xs font-bold text-amber-800 hover:underline">Review policy framework →</button>
        </section>
    </aside>
</div>