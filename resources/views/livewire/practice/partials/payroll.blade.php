@php
    $displayPayroll = $payrollRows ?? [];
    $totalBaseAllowance = array_sum(array_column($displayPayroll, 'base_allowance'));
    $totalApprovedTravel = array_sum(array_column($displayPayroll, 'approved_conveyance'));
    $totalAdjustments = array_sum(array_column($displayPayroll, 'adjustment'));
    $totalNetPayable = array_sum(array_column($displayPayroll, 'net_payable'));
    $statusBadgeText = match ($payrollStatus ?? 'draft') {
        'reviewed' => 'Reviewed Ledger',
        'approved' => 'Approved Ledger',
        'paid'     => 'Disbursed / Paid',
        'locked'   => 'Locked Snapshot',
        default    => 'Draft Ledger',
    };
@endphp

<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">MONTHLY ALLOWANCE &amp; PAYROLL</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Payroll Workspace</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Manage monthly student allowances, approved conveyance disbursements, and attendance reconciliation for ICAB audit students.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        <label class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-700">Payroll Period
            <input type="month" wire:model.change="period" class="rounded-md border-0 bg-slate-50 p-1 text-xs font-bold outline-none focus:ring-2 focus:ring-amber-200">
        </label>
        <label class="flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700">Mode
            <select wire:model="selectedPaymentMethod" class="rounded bg-slate-50 p-1 text-xs font-bold outline-none">
                <option value="bKash / Bank">bKash / Bank</option>
                <option value="Cash Voucher">Cash Voucher</option>
                <option value="bKash Transfer">bKash Transfer</option>
                <option value="Nagad Transfer">Nagad Transfer</option>
                <option value="Bank A/C Transfer">Bank A/C Transfer</option>
            </select>
        </label>
        <button type="button" wire:click="exportStudentPayrollVoucher" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17191F] px-3.5 py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 transition shadow-sm">
            <i data-lucide="printer" class="h-4 w-4"></i>
            <span>Print All Vouchers</span>
        </button>
        <button type="button" wire:click="exportPayrollCsv" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50 transition shadow-sm">
            <i data-lucide="download" class="h-4 w-4 text-emerald-600"></i>
            <span>Export CSV</span>
        </button>
    </div>
</section>

<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    @foreach ([
        ['label' => $previewRole === 'student' ? 'Base Allowance' : 'Gross Allowance', 'value' => '৳ ' . number_format($totalBaseAllowance, 0), 'note' => 'ICAB stipend scale', 'icon' => 'wallet'],
        ['label' => 'Approved Conveyance', 'value' => '৳ ' . number_format($totalApprovedTravel, 0), 'note' => 'Audit site travel claims', 'icon' => 'receipt'],
        ['label' => 'Net Payable Total', 'value' => '৳ ' . number_format($totalNetPayable, 0), 'note' => 'Stipend + Approved Travel', 'icon' => 'scale'],
        ['label' => 'Ledger Status', 'value' => $statusBadgeText, 'note' => ($payrollStatus ?? 'draft') === 'locked' ? 'Immutable snapshot' : 'Open for reconciliation', 'icon' => 'file-lock'],
    ] as $metric)
        <div wire:key="payroll-metric-{{ $loop->index }}" class="min-w-0 rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5">
            <div class="mb-3 flex justify-between gap-2"><p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p><i data-lucide="{{ $metric['icon'] }}" class="h-[19px] w-[19px] shrink-0 text-amber-800"></i></div>
            <p class="break-words text-xl font-extrabold tracking-tight sm:text-2xl">{{ $metric['value'] }}</p>
            <p class="mt-2 text-[11px] leading-relaxed text-slate-400">{{ $metric['note'] }}</p>
        </div>
    @endforeach
</div>

@if($previewRole !== 'student')
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-wider text-amber-800">PAYROLL GOVERNANCE ACTIONS</p>
                <h3 class="text-sm font-extrabold text-slate-900">Lifecycle State Controls ({{ $period }})</h3>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="generatePayrollDraft" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-800 hover:bg-slate-100">
                    Sync Draft
                </button>
                <button type="button" wire:click="transitionPayrollPeriod('reviewed')" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-900 hover:bg-amber-100">
                    Mark Reviewed
                </button>
                @if($previewRole === 'partner')
                    <button type="button" wire:click="transitionPayrollPeriod('approved')" class="rounded-xl bg-[#FCDF02] px-3.5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                        Partner Approve
                    </button>
                    <button type="button" wire:click="transitionPayrollPeriod('paid')" class="rounded-xl bg-emerald-600 px-3.5 py-2 text-xs font-extrabold text-white hover:bg-emerald-700">
                        Mark Paid
                    </button>
                    <button type="button" wire:click="transitionPayrollPeriod('locked')" class="rounded-xl bg-[#17191F] px-3.5 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">
                        Lock Period
                    </button>
                @endif
            </div>
        </div>
    </section>
@endif

<div class="grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Monthly Allowance Statement' : 'Monthly Practice Payroll Register' }}</h3><p class="mt-1 text-xs text-slate-500">Student stipends and approved conveyance reconciliation</p></div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900 uppercase">{{ $statusBadgeText }}</span>
            </div>
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="w-full min-w-[630px] text-left text-sm">
                <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Student</th>
                        <th class="px-4 py-3">Base Stipend</th>
                        <th class="px-4 py-3">Approved Travel</th>
                        <th class="px-4 py-3">Deductions</th>
                        <th class="px-4 py-3">Net Payable</th>
                        <th class="px-5 py-3 text-right">State &amp; Voucher</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($displayPayroll as $item)
                        <tr wire:key="payroll-{{ $item['id'] }}">
                            <td class="px-5 py-4">
                                <p class="font-bold">{{ $item['name'] }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ $item['id'] }}</p>
                            </td>
                            <td class="px-4 py-4 font-semibold">৳ {{ number_format($item['base_allowance'] ?? 0, 0) }}</td>
                            <td class="px-4 py-4 text-slate-600">৳ {{ number_format($item['approved_conveyance'] ?? 0, 0) }}</td>
                            <td class="px-4 py-4 text-xs text-rose-700 font-semibold">
                                @if(($item['adjustment'] ?? 0) > 0)
                                    - ৳ {{ number_format($item['adjustment'], 0) }}
                                @else
                                    <span class="text-slate-400">৳ 0</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 font-bold text-slate-900">৳ {{ number_format($item['net_payable'] ?? 0, 0) }}</td>
                            <td class="px-5 py-4 text-right space-x-1.5">
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $item['status_label'] ?? 'Draft Ledger' }}</span>
                                <button type="button" wire:click="exportStudentPayrollVoucher('{{ $item['id'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-bold text-slate-800 hover:bg-amber-50">
                                    <i data-lucide="printer" class="h-3 w-3"></i> Voucher
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-8 text-center text-xs text-slate-400">No students found in current practice ledger.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="divide-y divide-slate-100 md:hidden">
            @forelse($displayPayroll as $item)
                <div wire:key="mobile-pay-{{ $item['id'] }}" class="p-4">
                    <div class="flex justify-between gap-3">
                        <div>
                            <p class="text-sm font-bold">{{ $item['name'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $item['id'] }}</p>
                        </div>
                        <span class="h-fit rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">{{ $item['status_label'] ?? 'Draft Ledger' }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-3 gap-2 rounded-xl bg-slate-50 p-3 text-xs">
                        <div><p class="text-slate-400">Base Allowance</p><p class="mt-1 font-bold">৳ {{ number_format($item['base_allowance'] ?? 0, 0) }}</p></div>
                        <div><p class="text-slate-400">Approved Claim</p><p class="mt-1 font-bold">৳ {{ number_format($item['approved_conveyance'] ?? 0, 0) }}</p></div>
                        <div><p class="text-slate-400">Net Total</p><p class="mt-1 font-bold text-emerald-800">৳ {{ number_format($item['net_payable'] ?? 0, 0) }}</p></div>
                    </div>
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
            <h3 class="text-sm font-extrabold">Payroll Lifecycle</h3>
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
                <div><h3 class="text-sm font-extrabold">Financial Integrity</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Partner ownership, rule effective dates, approved claim reconciliation, race safety, no duplicate payment, and locked payroll snapshots are enforced in the ledger.</p></div>
            </div>
            <button type="button" wire:click="setSection('policies')" class="mt-4 text-xs font-bold text-amber-800 hover:underline">Review policy framework →</button>
        </section>
    </aside>
</div>