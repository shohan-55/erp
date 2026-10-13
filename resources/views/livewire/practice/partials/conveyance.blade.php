<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">CONVEYANCE CLAIMS · PHASE 09</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Travel & Conveyance</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Journey details, receipt evidence, assigned review and duplicate-payment safeguards. Values are fictional Bangladeshi Taka examples.</p>
    </div>
    @if($previewRole !== 'student')
        <button type="button" wire:click="openDialog('claim')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]"><i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Claim (Preview)</button>
    @endif
</section>

<section class="grid gap-4 sm:grid-cols-3">
    @foreach([
        ['label'=>'Sample Submitted Claims','value'=>$previewRole === 'student' ? '01' : ($previewRole === 'manager' ? '02' : '03'),'icon'=>'receipt','hint'=>'Demo requests only'],
        ['label'=>'Illustrative Review Queue','value'=>$previewRole === 'student' ? '01' : '02','icon'=>'clock-3','hint'=>'No actual approvals'],
        ['label'=>'Illustrative Paid Claims','value'=>'৳ 0','icon'=>'wallet','hint'=>'No real disbursements'],
    ] as $metric)
        <div wire:key="conv-metric-{{ $loop->index }}" class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-2"><p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p><div class="rounded-xl bg-amber-50 p-2.5 text-amber-800"><i data-lucide="{{ $metric['icon'] }}" class="h-5 w-5"></i></div></div><p class="mt-4 text-2xl font-extrabold">{{ $metric['value'] }}</p><p class="mt-2 text-[11px] text-slate-400">{{ $metric['hint'] }}</p></div>
    @endforeach
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,1fr)]">
    <div class="space-y-5">
        @if($previewRole === 'student')
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="text-base font-extrabold">New Conveyance Claim</h3><span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">UI Prototype</span></div>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Example form validation only. No expense, payment voucher, receipt or bank transfer will be created.</p>
                <form wire:submit.prevent="submitClaimDemo" class="mt-6 space-y-4" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Journey Date
                            <input type="date" wire:model="claimDate" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Amount (BDT)
                            <input type="number" wire:model="claimAmount" min="1" max="50000" step="0.01" placeholder="e.g. 220" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                    </div>
                    <label class="block text-xs font-bold text-slate-700">Journey / Route
                        <input type="text" wire:model="claimRoute" maxlength="200" placeholder="Office → Client Site → Office" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                    </label>
                    <label class="block text-xs font-bold text-slate-700">Business Purpose
                        <textarea wire:model="claimPurpose" rows="3" maxlength="500" placeholder="Purpose of the client visit" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400"></textarea>
                    </label>
                    <div class="flex items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                        <i data-lucide="paperclip" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500"></i>
                        <div><p class="text-xs font-bold text-slate-700">Receipt / Supporting Document</p><p class="mt-1 text-[11px] leading-relaxed text-slate-500">Upload UI will be enabled with secure storage in a later backend phase. Please do not upload actual documents in this prototype.</p></div>
                    </div>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F3D604]"><i data-lucide="clipboard-check" class="h-4 w-4"></i> Validate Demo Claim</button>
                </form>
            </section>
        @else
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Claim Review Queue · Example</h3><p class="mt-1 text-xs text-slate-500">Partner Unit 01 · No real financial data</p></div>
                @php
                    $claimQueue = [
                        ['name'=>'Sample Student 01','manager'=>'Sample Manager A','route'=>'Office → Sample Client','amount'=>'৳ 220','status'=>'Manager Review'],
                        ['name'=>'Sample Student 02','manager'=>'Sample Manager A','route'=>'Sample Client → Office','amount'=>'৳ 165','status'=>'Partner Review'],
                        ['name'=>'Sample Student 03','manager'=>'Sample Manager B','route'=>'Office → Sample Client','amount'=>'৳ 310','status'=>'Evidence Pending'],
                    ];
                    if($previewRole === 'manager') $claimQueue = array_values(array_filter($claimQueue, fn($q)=>$q['manager']==='Sample Manager A'));
                @endphp
                <div class="divide-y divide-slate-100">
                    @foreach($claimQueue as $request)
                        <div wire:key="conv-claim-{{ $loop->index }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div><p class="text-sm font-bold">{{ $request['name'] }} <span class="ml-1 text-xs font-semibold text-slate-400">{{ $request['amount'] }} · Demo</span></p><p class="mt-1 text-xs text-slate-500">{{ $request['route'] }}</p></div>
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">{{ $request['status'] }}</span><button type="button" wire:click="openDialog('claim')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">Review UI</button></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Sample Claim' : 'Example Claim Approval Flow' }}</h3>
            @if($previewRole === 'student')
                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4"><div><p class="text-xs font-bold">Sample Client Visit · ৳ 220</p><p class="mt-1 text-[11px] text-slate-500">Illustrative claim, not submitted</p></div><span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-900">Preview</span></div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach(['Student submits','Manager checks proof','Partner approves (if delegated)'] as $step)
                        <div class="rounded-xl bg-slate-50 p-4 text-xs font-bold text-slate-700"><i data-lucide="check-circle-2" class="mb-2 h-4 w-4 text-amber-800"></i>{{ $step }} <span class="font-normal text-slate-400">(planned)</span></div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">Financial Controls (Planned)</h3>
            <div class="mt-4 space-y-3">
                @foreach([
                    'Partner-wise owner and assigned reviewer',
                    'Document evidence and voucher audit trail',
                    'Duplicate expense/payment protection',
                    'Payroll reimbursement once, never twice',
                    'Draft → Review → Approve → Settle',
                ] as $rule)
                    <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-300"><i data-lucide="shield-check" class="mt-0.5 h-4 w-4 shrink-0 text-[#FCDF02]"></i>{{ $rule }}</div>
                @endforeach
            </div>
        </section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm"><div class="flex gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="info" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Proof before payment</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">No automatic conveyance reimbursement is permitted until approved evidence and duplicate checks are implemented and tested in the real backend.</p></div></div></section>
    </aside>
</div>