<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">CONVEYANCE CLAIMS · PHASE 09</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Travel &amp; Conveyance Expenses</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Audit client tagging, Manager threshold approval (up to ৳{{ number_format($managerConveyanceLimit, 2) }}), amount adjustments, and automatic Partner escalation for excess or late claims.</p>
    </div>
    @if($previewRole !== 'student')
        <button type="button" wire:click="openDialog('claim')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]">
            <i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Claim (Preview)
        </button>
    @endif
</section>

<!-- KPI Metrics -->
<section class="grid gap-4 sm:grid-cols-3">
    @foreach([
        ['label'=>'Submitted Claims','value'=>$previewRole === 'student' ? '01' : (count($claimQueue) < 10 ? '0' . count($claimQueue) : count($claimQueue)),'icon'=>'receipt','hint'=>'Total claims in queue'],
        ['label'=>'Partner Escalations','value'=>$previewRole === 'student' ? '01' : '02','icon'=>'clock-3','hint'=>'Exceeds ৳' . $managerConveyanceLimit . ' or late'],
        ['label'=>'Illustrative Paid Claims','value'=>'৳ 0','icon'=>'wallet','hint'=>'No real disbursements'],
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
            <!-- Student Claim Submission Form -->
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-extrabold">New Conveyance Claim</h3>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">Claim Form</span>
                </div>
                <p class="mt-2 text-xs leading-relaxed text-slate-500">Tag your audit client, route, and fare. Claims submitted after {{ $conveyanceLateDays }} days will be flagged for Partner review.</p>

                <form wire:submit.prevent="submitClaimDemo" class="mt-6 space-y-4" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Journey Date
                            <input type="date" wire:model="claimDate" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">Amount (BDT)
                            <input type="number" wire:model="claimAmount" min="1" max="50000" step="0.01" placeholder="e.g. 220" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <label class="block text-xs font-bold text-slate-700">Audit Client Assignment
                        <select wire:model="claimClientName" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                            @foreach($auditClients as $client)
                                <option value="{{ $client }}">{{ $client }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="block text-xs font-bold text-slate-700">Journey / Route
                        <input type="text" wire:model="claimRoute" maxlength="200" placeholder="Office → Client Audit Site → Return" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                    </label>

                    <label class="block text-xs font-bold text-slate-700">Business Purpose / Audit Scope
                        <textarea wire:model="claimPurpose" rows="3" maxlength="500" placeholder="Purpose of client visit, physical inventory verification, or audit working papers" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400"></textarea>
                    </label>

                    <div class="flex items-start gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                        <i data-lucide="paperclip" class="mt-0.5 h-5 w-5 shrink-0 text-slate-500"></i>
                        <div>
                            <p class="text-xs font-bold text-slate-700">Receipt / Supporting Ticket Evidence</p>
                            <p class="mt-1 text-[11px] leading-relaxed text-slate-500">Upload bus ticket, CNG slip, or Uber screenshot. (Optional in this prototype).</p>
                        </div>
                    </div>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold hover:bg-[#F3D604]">
                        <i data-lucide="clipboard-check" class="h-4 w-4"></i> Validate Demo Claim
                    </button>
                </form>
            </section>
        @else
            <!-- Multi-Tier Claim Review Queue for Manager & Partner -->
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 p-5 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold">{{ $previewRole === 'partner' ? 'Partner Final Approval & Escalation Queue' : 'Manager Review & Adjustment Queue' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">Manager Daily Limit: <strong>৳ {{ number_format($managerConveyanceLimit, 2) }}</strong> · Claims above limit or late escalate to Partner</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ count($claimQueue) }} Claims</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($claimQueue as $request)
                        <div wire:key="conv-claim-{{ $request['id'] }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-bold text-slate-800">{{ $request['name'] }}</p>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $request['student_code'] }}</span>
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">{{ $request['client_name'] }}</span>
                                    <span class="font-extrabold text-sm text-slate-900">৳ {{ number_format($request['amount'], 2) }}</span>
                                    @if(!empty($request['original_amount']) && $request['original_amount'] != $request['amount'])
                                        <span class="line-through text-xs text-slate-400">৳ {{ number_format($request['original_amount'], 2) }}</span>
                                    @endif
                                    @if(!empty($request['is_late']))
                                        <span class="rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-800">LATE CLAIM</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ $request['route'] }}</p>
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
                                    <button type="button" wire:click="rejectClaim('{{ $request['id'] }}', 'Fare rate disallowance.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @elseif($previewRole === 'partner' && in_array($request['status'], ['pending_partner', 'Escalated to Partner', 'Escalated to Partner (Late)']))
                                    <button type="button" wire:click="partnerApproveClaim('{{ $request['id'] }}', 'Partner approved exception.')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                        Sanction
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
            <h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Submitted Claim' : 'Multi-Tier Conveyance Rules' }}</h3>
            @if($previewRole === 'student')
                <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-slate-50 p-4">
                    <div>
                        <p class="text-xs font-bold">Square Pharmaceuticals Audit · ৳ 550.00</p>
                        <p class="mt-1 text-[11px] text-slate-500">Exceeds Manager ৳300 limit · Escalated to Partner</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-900">Escalated to Partner</span>
                </div>
            @else
                <div class="mt-4 grid gap-3 sm:grid-cols-3 text-xs">
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-amber-800">RULE 1: MANAGER LIMIT</span>
                        <p class="mt-1 text-slate-600">Claims &le; <strong>৳{{ number_format($managerConveyanceLimit, 2) }}</strong> are directly approved by Manager.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-indigo-700">RULE 2: ESCALATION</span>
                        <p class="mt-1 text-slate-600">Claims &gt; ৳{{ number_format($managerConveyanceLimit, 2) }} or modified must be escalated to Partner.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-100">
                        <span class="font-extrabold text-rose-700">RULE 3: LATE SUBMISSION</span>
                        <p class="mt-1 text-slate-600">Claims submitted &gt; <strong>{{ $conveyanceLateDays }} days</strong> after journey require Partner sanction.</p>
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

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex gap-3">
                <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                    <i data-lucide="sliders" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold">Manager Amount Adjustment</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Managers can adjust claimed amounts to conform to standard transit fares. An adjustment remark is mandatory for transparency.</p>
                </div>
            </div>
        </section>
    </aside>
</div>