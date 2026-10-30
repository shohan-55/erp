<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">CONVEYANCE CLAIMS</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Multi-Transport Travel &amp; Conveyance</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Itemize each transport leg (From → To → Transport → Amount), attach extra expenses, and auto-sync approved claims with Student Payroll ({{ $partnerUnit }}).</p>
    </div>
    <div class="flex items-center gap-2">
        <button type="button" wire:click="exportConveyanceCsv" title="Export Conveyance Claims Ledger to CSV" class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-xs font-extrabold text-slate-700 hover:bg-slate-50 transition shadow-sm">
            <i data-lucide="download" class="h-4 w-4 text-emerald-600"></i>
            <span>Export CSV</span>
        </button>
        @if($previewRole !== 'student')
            <button type="button" wire:click="openDialog('claim')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]">
                <i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Conveyance Claim
            </button>
        @endif
    </div>
</section>

<!-- KPI Metrics -->
@php
    $settledTotal = array_sum(array_map(fn($c) => ($c['status'] ?? '') === 'approved' ? (float) ($c['amount'] ?? 0) : 0, $claimQueue));
    $escalatedCount = count(array_filter($claimQueue, fn($c) => in_array($c['status'] ?? '', ['pending_partner', 'Escalated to Partner', 'Escalated to Partner (Late)'], true)));
@endphp
<section class="grid gap-4 sm:grid-cols-3">
    @foreach([
        ['label'=>'Submitted Claims','value'=>sprintf('%02d', count($claimQueue)),'icon'=>'receipt','hint'=>'Total claims in queue'],
        ['label'=>'Partner Escalations','value'=>sprintf('%02d', $escalatedCount),'icon'=>'clock-3','hint'=>'Older than ' . $conveyanceLateDays . ' days or escalated'],
        ['label'=>'This Month Approved Conveyance','value'=>'৳ ' . number_format($settledTotal, 0),'icon'=>'wallet','hint'=>'Added directly to Student Payroll'],
    ] as $metric)
        <div wire:key="conv-metric-{{ $loop->index }}" class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <p class="text-xs font-bold text-slate-500">{{ $metric['label'] }}</p>
                <div class="rounded-xl bg-amber-50 p-2.5 text-amber-800"><i data-lucide="{{ $metric['icon'] }}" class="h-5 w-5"></i></div>
            </div>
            <p class="mt-4 text-2xl font-extrabold">{{ $metric['value'] }}</p>
            <p class="mt-2 text-[11px] text-slate-400">{{ $metric['hint'] }}</p>
        </div>
    @endforeach
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,1fr)]">
    <div class="space-y-5">
        @if($previewRole === 'student')
            <!-- Student Multi-Transport Claim Submission Form -->
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-extrabold">New Multi-Leg Conveyance Claim</h3>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">Within {{ $conveyanceLateDays }} Days = Manager Approval</span>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Add each transport change (From, To, Transport Mode, Amount). Claims older than {{ $conveyanceLateDays }} working days require Partner final approval.</p>

                <form wire:submit.prevent="submitConveyanceClaim" class="mt-6 space-y-4" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Journey Date
                            <input type="date" wire:model="claimDate" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="block text-xs font-bold text-slate-700">Audit Client Assignment</label>
                                <button type="button" wire:click="openNewClientModal" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-800 hover:text-amber-900 hover:underline">
                                    <i data-lucide="plus-circle" class="h-3.5 w-3.5"></i> Add New Client
                                </button>
                            </div>
                            <select wire:model.live="claimClientName" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                                @foreach($auditClients as $client)
                                    <option value="{{ $client }}">{{ $client }}</option>
                                @endforeach
                                <option value="__NEW__" class="font-bold text-amber-800 bg-amber-50">+ Add New Client Site...</option>
                            </select>
                        </div>
                    </div>

                    <!-- Multi-Row Transport Builder -->
                    <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-extrabold text-slate-800">Transport Breakdown (From → To → Transport → Fare)</span>
                            <button type="button" wire:click="addTransportRow" class="inline-flex items-center gap-1 rounded-lg bg-[#17191F] px-3 py-1.5 text-[11px] font-bold text-[#FCDF02] hover:bg-slate-800">
                                <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Another Transport
                            </button>
                        </div>

                        @foreach($transportRows as $idx => $tRow)
                            <div wire:key="transport-row-{{ $idx }}" class="grid grid-cols-1 gap-2 sm:grid-cols-12 items-center bg-white p-2.5 rounded-xl border border-slate-200">
                                <div class="sm:col-span-3">
                                    <input type="text" wire:model.live="transportRows.{{ $idx }}.from" placeholder="From (e.g. Office)" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs">
                                </div>
                                <div class="sm:col-span-3">
                                    <input type="text" wire:model.live="transportRows.{{ $idx }}.to" placeholder="To (e.g. Motijheel)" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs">
                                </div>
                                <div class="sm:col-span-3">
                                    <select wire:model.live="transportRows.{{ $idx }}.mode" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs font-semibold">
                                        @foreach(['Bus','Rickshaw','CNG Auto','Metro Rail','Uber / Pathao','Train','Launch / Boat'] as $m)
                                            <option value="{{ $m }}">{{ $m }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="number" wire:model.live="transportRows.{{ $idx }}.amount" placeholder="৳ Fare" min="1" step="1" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-bold">
                                </div>
                                <div class="sm:col-span-1 text-right">
                                    @if(count($transportRows) > 1)
                                        <button type="button" wire:click="removeTransportRow({{ $idx }})" class="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50" title="Remove row">✕</button>
                                    @endif
                                </div>
                            </div>
                        @endforeach

                        <!-- Optional Other Expenses Toggle -->
                        <div class="pt-2 border-t border-slate-200">
                            <label class="inline-flex items-center gap-2 text-xs font-bold text-slate-700 cursor-pointer">
                                <input type="checkbox" wire:model.live="hasOtherExpense" class="rounded border-slate-300 text-amber-600">
                                <span>+ Add Other Expense (অন্যান্য খরচ — যেমন ফটোকপি, প্রিন্টিং ইত্যাদি)</span>
                            </label>
                            @if($hasOtherExpense)
                                <div class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-3 bg-white p-2.5 rounded-xl border border-amber-200">
                                    <div class="sm:col-span-2">
                                        <input type="text" wire:model="otherExpenseDesc" placeholder="Other expense details (বিবরণ)" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs">
                                    </div>
                                    <div>
                                        <input type="number" wire:model.live="otherExpenseAmount" placeholder="৳ Amount" min="1" step="1" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-bold">
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Journey Summary / Route
                            <input type="text" wire:model="claimRoute" maxlength="200" placeholder="Office → Client Audit Site → Return" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Total Claim Amount (BDT)
                            <input type="number" wire:model="claimAmount" min="1" max="50000" step="0.01" placeholder="Auto-summed or enter total" required class="mt-2 w-full rounded-xl border border-slate-200 bg-amber-50/60 px-3 py-3 text-sm font-extrabold outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <label class="block text-xs font-bold text-slate-700">Business Purpose / Audit Scope
                        <textarea wire:model="claimPurpose" rows="2" maxlength="500" placeholder="Purpose of client visit, physical inventory verification, or audit working papers" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400"></textarea>
                    </label>

                    <div class="flex flex-col gap-2 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                        <div class="flex items-start gap-3">
                            <i data-lucide="paperclip" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500"></i>
                            <div class="flex-1">
                                <p class="text-xs font-bold text-slate-700">Receipt / Supporting Voucher <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-extrabold text-amber-900">OPTIONAL (ঐচ্ছিক)</span></p>
                                <p class="mt-1 text-[11px] leading-relaxed text-slate-500">ভাউচার বা টিকিট থাকলে সংযুক্ত করুন; রিকশা বা লোকাল বাসে টিকিট না থাকলে আপলোড করার প্রয়োজন নেই (JPG, PNG, PDF up to 5MB).</p>
                                <input type="file" wire:model="claimAttachment" accept=".jpg,.jpeg,.png,.pdf" class="mt-2 block w-full text-xs text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-amber-100 file:px-3 file:py-1.5 file:text-xs file:font-bold file:text-amber-900 hover:file:bg-amber-200">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F3D604]">
                        <i data-lucide="clipboard-check" class="h-4 w-4"></i> Submit Conveyance Claim
                    </button>
                </form>
            </section>
        @else
            <!-- Multi-Tier Claim Review Queue for Manager & Partner -->
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold">{{ $previewRole === 'partner' ? 'Partner Final Approval & Escalation Queue' : 'Manager Review & Adjustment Queue' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Managers can approve within {{ $conveyanceLateDays }} working days, increase/decrease amounts, or send to Partner for review.</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ count($claimQueue) }} Claims</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($claimQueue as $request)
                        @php
                            $isUnreadClaim = ($previewRole === 'manager' && in_array($request['status'] ?? '', ['pending_manager', 'Manager Review'], true))
                                || ($previewRole === 'partner' && in_array($request['status'] ?? '', ['pending_partner', 'Escalated to Partner', 'Escalated to Partner (Late)', 'pending_manager'], true));
                        @endphp
                        <div wire:key="conv-claim-{{ $request['id'] }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between transition {{ $isUnreadClaim ? 'bg-amber-50/40 border-l-4 border-l-amber-500' : 'bg-white' }}">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isUnreadClaim)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-500 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-[#17191F] shadow-2xs animate-pulse">
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#17191F]"></span> NEW UNREAD
                                        </span>
                                    @endif
                                    <p class="text-sm font-bold text-slate-800">{{ $request['name'] }}</p>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $request['student_code'] }}</span>
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">{{ $request['client_name'] }}</span>
                                    <span class="font-extrabold text-sm text-slate-900">৳ {{ number_format($request['amount'], 2) }}</span>
                                    @if(!empty($request['original_amount']) && $request['original_amount'] != $request['amount'])
                                        <span class="line-through text-xs text-slate-400">৳ {{ number_format($request['original_amount'], 2) }}</span>
                                    @endif
                                    @if(!empty($request['is_late']))
                                        <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">OLDER CLAIM (&gt;{{ $conveyanceLateDays }}d)</span>
                                    @endif
                                    @if(!empty($request['attachment_name']))
                                        <button type="button" wire:click="openVoucherPreviewModal('{{ $request['id'] }}')" class="inline-flex items-center gap-1 rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold text-emerald-900 hover:bg-emerald-200 underline">
                                            📎 View Voucher ({{ $request['attachment_name'] }})
                                        </button>
                                    @else
                                        <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">No Ticket (Rickshaw/Local Bus)</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $request['route'] }}</p>
                                @if(!empty($request['other_expense_description']))
                                    <p class="mt-0.5 text-[11px] text-slate-600">Other Expense: {{ $request['other_expense_description'] }} (৳{{ number_format($request['other_expense_amount'] ?? 0, 2) }})</p>
                                @endif
                                @if(!empty($request['adjustment_remark']))
                                    <p class="mt-1 text-[11px] font-semibold text-amber-800"><i data-lucide="edit-3" class="inline h-3 w-3 mr-0.5"></i> Adjusted: {{ $request['adjustment_remark'] }}</p>
                                @endif
                                @if(!empty($request['manager_remarks']))
                                    <p class="mt-0.5 text-[11px] font-semibold text-slate-600"><i data-lucide="message-square" class="inline h-3 w-3 mr-0.5"></i> {{ $request['manager_remarks'] }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold
                                    {{ $request['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($request['status'] === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800') }}">
                                    {{ $request['status_label'] }}
                                </span>

                                @if($previewRole === 'manager' && in_array($request['status'], ['pending_manager', 'Manager Review']))
                                    <button type="button" wire:click="managerReviewClaim('{{ $request['id'] }}', {{ $request['amount'] }}, 'Approved within limit.')"
                                            class="rounded-lg bg-[#FCDF02] px-3 py-1.5 text-xs font-bold text-[#17191F] hover:bg-[#F3D604]">
                                        {{ $request['amount'] > $managerConveyanceLimit || !empty($request['is_late']) ? 'Verify & Escalate →' : 'Approve Claim' }}
                                    </button>
                                    <button type="button" wire:click="openClaimAdjustModal('{{ $request['id'] }}')"
                                            class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-900 hover:bg-amber-100">
                                        Adjust (±) / Escalate
                                    </button>
                                    <button type="button" wire:click="rejectClaim('{{ $request['id'] }}', 'Fare rate disallowance.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @elseif($previewRole === 'partner' && in_array($request['status'], ['pending_partner', 'Escalated to Partner', 'Escalated to Partner (Late)', 'pending_manager']))
                                    <button type="button" wire:click="partnerApproveClaim('{{ $request['id'] }}', 'Partner approved claim.')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                        Sanction
                                    </button>
                                    <button type="button" wire:click="openClaimAdjustModal('{{ $request['id'] }}')"
                                            class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-900 hover:bg-amber-100">
                                        Adjust (±)
                                    </button>
                                    <button type="button" wire:click="rejectClaim('{{ $request['id'] }}', 'Partner rejected excess.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @else
                                    <button type="button" wire:click="openDialog('claim')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold hover:bg-slate-50">
                                        Review UI
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-500">No conveyance claims in this review queue.</div>
                    @endforelse
                </div>
            </section>
        @endif

        <!-- Conveyance Rules Card -->
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Submitted Claims' : 'Multi-Tier Conveyance Rules' }}</h3>
            @if($previewRole === 'student')
                <div class="mt-4 space-y-2.5">
                    @forelse($claimQueue as $req)
                        <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4 border border-slate-100">
                            <div>
                                <p class="text-xs font-bold">{{ $req['client_name'] }} · ৳ {{ number_format($req['amount'], 2) }}</p>
                                <p class="mt-1 text-[11px] text-slate-500">{{ $req['route'] }}</p>
                            </div>
                            <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-900">{{ $req['status_label'] }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-400 p-4 text-center">No conveyance claims submitted yet.</p>
                    @endforelse
                </div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-3 text-xs">
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-amber-800">RULE 1: 1–2 DAY WINDOW</span>
                        <p class="mt-1 text-slate-600">Claims within <strong>{{ $conveyanceLateDays }} working days</strong> (&le; ৳{{ number_format($managerConveyanceLimit, 0) }}) can be approved directly by Manager.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-indigo-700">RULE 2: INCREASE / DECREASE</span>
                        <p class="mt-1 text-slate-600">Managers can increase or reduce claimed amounts and optionally forward any claim to Partner.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-rose-700">RULE 3: PAYROLL SYNC</span>
                        <p class="mt-1 text-slate-600">Once approved, conveyance is automatically added to the student's monthly payroll ledger.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <!-- Aside Financial Controls -->
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">Billable Client Attribution</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Every conveyance expense is attributed to an Audit Client engagement for partner billing &amp; audit working papers.</p>
            <div class="mt-5 space-y-2.5 text-xs">
                @foreach($auditClients as $c)
                    <div class="flex items-center justify-between border-b border-white/10 pb-2">
                        <span class="text-slate-300">{{ $c }}</span>
                        <span class="rounded bg-white/10 px-2 py-0.5 text-[10px] text-amber-300 font-semibold">Billable</span>
                    </div>
                @endforeach
            </div>
        </section>
    </aside>
</div>

{{-- Claim Amount Increase / Decrease & Partner Escalation Modal --}}
@if($showClaimAdjustModal)
    <div class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">CONVEYANCE REVIEW &amp; ADJUSTMENT</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">Adjust Claim ({{ $adjustTargetClaimId }})</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form wire:submit.prevent="submitClaimAdjustment" class="mt-4 space-y-4 text-xs">
                <label class="block font-bold text-slate-700">Adjusted Amount (BDT — Increase or Decrease)
                    <input type="number" wire:model="adjustNewAmount" min="1" max="100000" step="1" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-sm font-extrabold">
                </label>
                <label class="block font-bold text-slate-700">Adjustment / Review Remarks
                    <input type="text" wire:model="adjustRemarks" placeholder="e.g. Added extra rickshaw fare / Deducted excess fare" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                </label>
                @if($previewRole === 'manager')
                    <label class="flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 font-bold text-amber-950 cursor-pointer">
                        <input type="checkbox" wire:model="adjustEscalateToPartner" class="rounded border-amber-300 text-amber-600">
                        <span>Send to Partner for Final Review &amp; Approval</span>
                    </label>
                @endif
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 px-4 py-2 font-bold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#FCDF02] px-4 py-2 font-extrabold text-[#17191F] hover:bg-[#F3D604]">Save &amp; Apply</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Optional Conveyance Voucher Preview Lightbox Modal (#2) --}}
@if($showVoucherPreviewModal && $previewVoucherClaim)
    <div class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/70 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-emerald-800">ATTACHED VOUCHER / RECEIPT PREVIEW</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">{{ $previewVoucherClaim['name'] ?? 'Student' }} · {{ $previewVoucherClaim['id'] ?? '' }} (৳{{ number_format($previewVoucherClaim['amount'] ?? 0, 2) }})</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <div class="mt-4 space-y-3 text-xs">
                <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-200">
                    <p><strong>Client Engagement:</strong> {{ $previewVoucherClaim['client_name'] ?? '—' }}</p>
                    <p class="mt-1"><strong>Route Breakdown:</strong> {{ $previewVoucherClaim['route'] ?? '—' }}</p>
                    <p class="mt-1"><strong>Attached File:</strong> <span class="font-mono font-bold text-emerald-800">{{ $previewVoucherClaim['attachment_name'] ?? 'voucher.pdf' }}</span></p>
                </div>
                <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center">
                    <i data-lucide="file-check-2" class="h-10 w-10 text-emerald-600 mb-2"></i>
                    <p class="font-bold text-slate-800">{{ $previewVoucherClaim['attachment_name'] }}</p>
                    <p class="text-[11px] text-slate-500 mt-1">Stored in practice vault: storage/conveyance-receipts/{{ $firmSlug }}/{{ $previewVoucherClaim['attachment_name'] }}</p>
                    <a href="{{ route('firm.voucher.view', ['firmSlug' => $firmSlug, 'filename' => $previewVoucherClaim['attachment_name']]) }}" target="_blank" class="mt-3 inline-flex items-center gap-1.5 rounded-xl bg-[#17191F] px-4 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">
                        Open Full File in New Tab ↗
                    </a>
                </div>
            </div>
            <div class="mt-5 flex justify-end">
                <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-700">Close Preview</button>
            </div>
        </div>
    </div>
@endif
