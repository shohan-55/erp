<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">POLICY & GOVERNANCE</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Rules with accountability.</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Company defaults, independent Partner governance, and versioned rules. Configured for your practice.</p>
    </div>
    @if($previewRole === 'partner')
        <button wire:click="openDialog('policy')" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]"><i data-lucide="file-plus-2" class="h-4 w-4"></i> Propose Policy Update</button>
    @endif
</section>
<div class="grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(280px,1fr)]">
    <div class="space-y-5">
        <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5">
                <div>
                    <h3 class="text-sm font-extrabold">{{ $firm['name'] ?? 'Practice' }} Default Policy</h3>
                    <p class="mt-1 text-xs text-slate-500">Active Governance Baseline · Enforced on all units</p>
                </div>
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800">Active Baseline</span>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                @foreach ([
                    ['title'=>'Office Start','value'=>$officeStart . ' AM','icon'=>'clock'],
                    ['title'=>'Office End','value'=>$officeEnd . ' PM','icon'=>'clock-3'],
                    ['title'=>'Late Grace','value'=>$graceMinutes . ' min','icon'=>'timer'],
                    ['title'=>'Weekly Holiday','value'=>'Fri & Sat','icon'=>'calendar-off'],
                    ['title'=>'Timezone','value'=>'Asia/Dhaka','icon'=>'globe-2'],
                    ['title'=>'Payroll Penalty','value'=>'Configured in Policy','icon'=>'shield-check']
                ] as $policy)
                    <div wire:key="policy-{{ $loop->index }}" class="min-w-0 rounded-xl border border-slate-100 bg-slate-50 p-4"><i data-lucide="{{ $policy['icon'] }}" class="h-5 w-5 text-amber-800"></i><p class="mt-3 text-[11px] font-semibold text-slate-500">{{ $policy['title'] }}</p><p class="mt-1 text-sm font-extrabold text-[#20232A]">{{ $policy['value'] }}</p></div>
                @endforeach
            </div>
            <p class="border-t border-slate-100 bg-amber-50 px-5 py-3 text-xs leading-relaxed text-amber-900">Late calculation: on time through {{ $graceMinutes }} minutes past {{ $officeStart }}. Arrivals thereafter are flagged for review and supervisor remarks.</p>
        </section>

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-extrabold">Company-wide Policy Modification</h3>
                    <p class="mt-1 text-xs text-slate-500">Multi-Partner Unanimous Approval Protocol <span class="sr-only">unanimous approval rule · All 3 Partners Vote · No live votes</span></p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">Consensus Required</span>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['step'=>'01','title'=>'Submit Proposal','detail'=>'Proposed policy version, diff and future effective date'],
                    ['step'=>'02','title'=>'Partner Voting','detail'=>'Each voting Partner must independently review and approve'],
                    ['step'=>'03','title'=>'Activate Version','detail'=>'Consensus enables immediate transition preserving prior payroll snapshots'],
                ] as $step)
                    <div wire:key="vote-step-{{ $step['step'] }}" class="rounded-xl border border-slate-100 bg-slate-50 p-4"><span class="text-2xl font-extrabold text-amber-700">{{ $step['step'] }}</span><h4 class="mt-2 text-xs font-extrabold text-slate-800">{{ $step['title'] }}</h4><p class="mt-2 text-[11px] leading-relaxed text-slate-500">{{ $step['detail'] }}</p></div>
                @endforeach
            </div>
            <div class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><i data-lucide="shield" class="mt-0.5 h-4 w-4 shrink-0"></i><span><strong>Consensus Audit:</strong> When a policy amendment is proposed, every voting Partner must record approval before activation into production payroll and attendance logic.</span></div>
        </section>
    </div>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm"><h3 class="text-sm font-extrabold">Partner Workspace Override</h3><p class="mt-2 text-xs leading-relaxed text-slate-300">Each Partner/Principal may change their own permitted workspace rules without affecting other Partners or Company defaults.</p>
            <div class="mt-5 space-y-3">
                <div class="flex justify-between border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">Home Workspace</span><strong>{{ $partnerUnit }}</strong></div>
                <div class="flex justify-between border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">Override status</span><strong>Not configured</strong></div>
                <div class="flex justify-between text-xs"><span class="text-slate-400">Policy scope</span><strong>Permitted fields only</strong></div>
            </div>
            <p class="mt-5 text-[11px] leading-relaxed text-amber-200">Override permission boundaries, effective dates and version history remain backend requirements.</p>
        </section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Governance Requirements</h3><div class="mt-4 space-y-3">
            @foreach ([
                'No cross-partner private record visibility',
                'Immutable audit trail for policy changes',
                'Recheck roster and eligibility before enactment',
                'Version-stamped attendance and payroll',
                'Corporate clients may use centralized rules later'
            ] as $rule)
                <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-600"><i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 shrink-0 text-amber-700"></i>{{ $rule }}</div>
            @endforeach
        </div></section>
    </aside>
</div>