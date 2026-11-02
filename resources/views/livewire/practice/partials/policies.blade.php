<div x-data="{ activePolicyTab: 'overview', editPolicyOpen: false, editGovOpen: false }" class="space-y-5">
    <!-- Top Executive Policy Header -->
    <section class="relative overflow-hidden rounded-3xl border border-slate-800 bg-gradient-to-r from-[#11141C] via-[#171B26] to-[#0D1117] p-6 text-white shadow-xl">
        <div class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div class="space-y-1.5">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-[#FCDF02] px-3 py-0.5 text-[10px] font-black uppercase tracking-wider text-[#17191F]">
                        POLICY &amp; GOVERNANCE HUB
                    </span>
                    <span class="rounded-full border border-white/15 bg-white/10 px-3 py-0.5 text-[11px] font-bold text-emerald-300">
                        {{ $partnerUnit }} · Active Baseline
                    </span>
                </div>
                <h2 class="text-2xl font-black tracking-tight text-white">Rules with accountability.</h2>
                <p class="max-w-2xl text-xs sm:text-sm leading-relaxed text-slate-300">
                    Company defaults, independent Partner governance, ICAB Articleship (3-Yr: 180d / 4-Yr: 240d) &amp; Corporate leave rules, and versioned compliance audit trail.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                    <button type="button" @click="activePolicyTab = 'overview'; editPolicyOpen = !editPolicyOpen" class="inline-flex items-center justify-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-extrabold text-white hover:bg-white/20 transition cursor-pointer">
                        <i data-lucide="sliders-horizontal" class="h-4 w-4 text-[#FCDF02]"></i>
                        <span x-text="editPolicyOpen ? 'Close Policy Editor' : 'Edit Unit Policy'">Edit Unit Policy</span>
                    </button>
                    <button wire:click="openDialog('policy')" type="button" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-md cursor-pointer">
                        <i data-lucide="file-plus-2" class="h-4 w-4"></i> Propose Policy Update
                    </button>
                @endif
            </div>
        </div>

        <!-- Segmented Tab Bar -->
        <div class="mt-6 flex flex-wrap items-center gap-1.5 border-t border-white/10 pt-4">
            @foreach([
                ['id' => 'overview',    'label' => '1. Attendance & Financial Rules', 'icon' => 'clock'],
                ['id' => 'leave_rules', 'label' => '2. ICAB & Corporate Leave Policy', 'icon' => 'calendar-check-2'],
                ['id' => 'governance',  'label' => '3. Governance & Unit Scope',       'icon' => 'shield-check'],
                ['id' => 'consensus',   'label' => '4. Partner Consensus (' . count($policyProposals ?? []) . ')', 'icon' => 'git-pull-request'],
                ['id' => 'audit',       'label' => '5. Compliance Audit Log (' . count($auditLogsList ?? []) . ')', 'icon' => 'history'],
            ] as $pTab)
                <button type="button"
                        @click="activePolicyTab = '{{ $pTab['id'] }}'"
                        :class="activePolicyTab === '{{ $pTab['id'] }}' ? 'bg-[#FCDF02] text-[#17191F] shadow-sm font-black' : 'bg-white/5 text-slate-300 hover:bg-white/15 font-bold'"
                        class="inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-xs transition cursor-pointer">
                    <i data-lucide="{{ $pTab['icon'] }}" class="h-3.5 w-3.5"></i>
                    <span>{{ $pTab['label'] }}</span>
                </button>
            @endforeach
        </div>
    </section>

    <!-- ================================================================= -->
    <!-- TAB 1: OVERVIEW & COLLAPSIBLE POLICY EDITOR                       -->
    <!-- ================================================================= -->
    <div x-show="activePolicyTab === 'overview'" class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
        <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">{{ $firm['name'] ?? 'Practice' }} Default Policy ({{ $partnerUnit }})</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Active Governance Baseline · Enforced across attendance, conveyance &amp; payroll</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800">Active Baseline</span>
                    @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                        <button type="button" @click="editPolicyOpen = !editPolicyOpen" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-extrabold text-amber-950 hover:bg-amber-100 transition cursor-pointer">
                            <i data-lucide="pencil" class="h-3.5 w-3.5"></i>
                            <span x-text="editPolicyOpen ? 'Hide Editor' : 'Edit Policy Rules'">Edit Policy Rules</span>
                        </button>
                    @endif
                </div>
            </div>

            <!-- Clean Read-Only KPI Summary Grid -->
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-3">
                @foreach ([
                    ['title'=>'Office Start','value'=>$officeStart . ' AM','icon'=>'clock', 'sub'=>'Right Time cutoff'],
                    ['title'=>'Office End','value'=>$officeEnd . ' PM','icon'=>'clock-3', 'sub'=>'Standard duty close'],
                    ['title'=>'Late Grace','value'=>$graceMinutes . ' min','icon'=>'timer', 'sub'=>'Up to ' . ($graceEndFormatted ?? '09:40')],
                    ['title'=>'Weekly Holiday','value'=>$weeklyOffSummary,'icon'=>'calendar-off', 'sub'=>'Firm recurring weekend'],
                    ['title'=>'Timezone & Geofence','value'=>($policyTimezone ?? 'Asia/Dhaka') . ' · ' . number_format((float) ($geofenceRadiusKm ?? 1.5), 2) . ' km','icon'=>'globe-2', 'sub'=>'GPS verification radius'],
                    ['title'=>'Payroll Penalty','value'=>$latePenaltyEnabled ? ($lateDaysPerDeduction . 'd Late = ' . $deductionDaysCount . 'd Cut') : 'No Financial Cut','icon'=>'shield-check', 'sub'=>'Monthly stipend rule']
                ] as $policy)
                    <div wire:key="policy-{{ $loop->index }}" class="min-w-0 rounded-2xl border border-slate-100 bg-slate-50/80 p-4 hover:border-amber-200 transition">
                        <div class="flex items-center justify-between">
                            <span class="text-[11px] font-bold text-slate-500">{{ $policy['title'] }}</span>
                            <i data-lucide="{{ $policy['icon'] }}" class="h-4 w-4 text-amber-800"></i>
                        </div>
                        <p class="mt-2 text-base font-black text-[#20232A]">{{ $policy['value'] }}</p>
                        <p class="mt-0.5 text-[10px] font-medium text-slate-400">{{ $policy['sub'] }}</p>
                    </div>
                @endforeach
            </div>

            <div class="border-t border-slate-100 bg-amber-50/70 px-5 py-3 text-xs leading-relaxed text-amber-950 flex flex-wrap items-center justify-between gap-2">
                <span>Late calculation: on time through {{ $graceMinutes }} minutes past {{ $officeStart }}. Arrivals thereafter are flagged for review and supervisor remarks.</span>
                <span class="font-extrabold text-amber-900">Mgr Claim Limit: ৳{{ number_format($managerConveyanceLimit, 0) }} ({{ $conveyanceLateDays }}d window)</span>
            </div>

            <!-- Collapsible Direct Policy Editor (Hidden until "Edit Policy Rules" is clicked) -->
            @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                <div x-show="editPolicyOpen" x-cloak class="border-t-2 border-amber-300 bg-slate-50 p-5">
                    <form wire:submit.prevent="savePolicySettings" class="space-y-4 text-xs">
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 pb-3">
                            <div>
                                <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-900">⚡ Direct Policy &amp; Late Salary Deduction Editor ({{ $partnerUnit }})</h4>
                                <p class="text-[11px] text-slate-500">Modify any parameter below and click Save to immediately update {{ $partnerUnit }}.</p>
                            </div>
                            <button type="button" @click="editPolicyOpen = false" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-bold text-slate-600 hover:bg-slate-100 cursor-pointer">
                                Collapse ✕
                            </button>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <label class="block font-bold text-slate-700">Office Start (HH:MM)
                                <input type="text" wire:model="officeStart" placeholder="09:30" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Office End (HH:MM)
                                <input type="text" wire:model="officeEnd" placeholder="18:00" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Grace Window (Min)
                                <input type="number" wire:model="graceMinutes" min="0" max="120" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Late Days for Cut
                                <input type="number" wire:model="lateDaysPerDeduction" min="1" max="30" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Salary Cut (Days)
                                <input type="number" wire:model="deductionDaysCount" min="0.5" max="10" step="0.5" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Mgr Claim Limit (৳)
                                <input type="number" wire:model="managerConveyanceLimit" min="50" max="10000" step="10" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Claim Window (Days)
                                <input type="number" wire:model="conveyanceLateDays" min="1" max="30" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Geofence Radius (KM)
                                <input type="number" wire:model="geofenceRadiusKm" min="0.05" max="50" step="0.05" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                            <label class="block font-bold text-slate-700">Policy Timezone
                                <input type="text" wire:model="policyTimezone" placeholder="Asia/Dhaka" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 font-bold">
                            </label>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-200">
                            <label class="inline-flex items-center gap-2 font-bold text-slate-800 cursor-pointer">
                                <input type="checkbox" wire:model.live="latePenaltyEnabled" class="rounded border-amber-400 text-amber-600">
                                <span>Enable Automatic Late Deduction ({{ $lateDaysPerDeduction }} Late Days = {{ $deductionDaysCount }} Day Salary Cut)</span>
                            </label>
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#17191F] px-5 py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 cursor-pointer">
                                <i data-lucide="save" class="h-4 w-4"></i> Save &amp; Activate Policy Rules
                            </button>
                        </div>
                    </form>
                </div>
            @elseif($previewRole === 'manager')
                <div class="border-t border-slate-100 bg-slate-50/80 px-5 py-3.5 text-xs text-slate-600 flex items-center justify-between gap-3">
                    <span><i data-lucide="lock" class="inline h-3.5 w-3.5 text-slate-400 mr-1"></i> <strong>Read-Only Policy View:</strong> Your Partner can grant you <em>Policy &amp; Holiday Admin</em> permission from <strong>Team &amp; Members</strong> or <strong>Workspace Settings</strong> to edit these rules directly.</span>
                    <span class="rounded bg-slate-200 px-2 py-0.5 text-[10px] font-bold text-slate-700 shrink-0">View Mode</span>
                </div>
            @endif
        </section>

        <!-- Right Quick Summary Card -->
        <aside class="space-y-4">
            <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
                <h3 class="text-sm font-extrabold">Partner Workspace Override</h3>
                <p class="mt-2 text-xs leading-relaxed text-slate-300">Each Partner/Principal may change their own permitted workspace rules without affecting other Partners or Company defaults.</p>
                <div class="mt-4 space-y-2.5">
                    <div class="flex justify-between border-b border-white/10 pb-2.5 text-xs"><span class="text-slate-400">Home Workspace</span><strong>{{ $partnerUnit }}</strong></div>
                    <div class="flex justify-between border-b border-white/10 pb-2.5 text-xs"><span class="text-slate-400">Override status</span><strong>Active ({{ $officeStart }}–{{ $officeEnd }})</strong></div>
                    <div class="flex justify-between text-xs"><span class="text-slate-400">Policy scope</span><strong>Permitted fields only</strong></div>
                </div>
                <p class="mt-4 text-[11px] leading-relaxed text-amber-200">Override permission boundaries, effective dates and version history are logged in the compliance trail.</p>
            </section>
        </aside>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 2: ICAB ARTICLESHIP (180d / 240d) & CORPORATE LEAVE POLICY    -->
    <!-- ================================================================= -->
    <div x-show="activePolicyTab === 'leave_rules'" x-cloak class="grid gap-5 lg:grid-cols-2">
        <section class="rounded-2xl border border-emerald-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold uppercase text-emerald-900">ICAB ARTICLESHIP LEAVE FRAMEWORK</span>
                    <h3 class="mt-1 text-base font-extrabold text-slate-900">3-Year (180 Days) &amp; 4-Year (240 Days) Student Caps</h3>
                </div>
                <button type="button" wire:click="setSection('team')" class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-extrabold text-emerald-900 hover:bg-emerald-100 cursor-pointer">
                    Configure Per Member →
                </button>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 text-xs">
                <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-4">
                    <p class="text-[10px] font-black uppercase text-emerald-800">3-YEAR ARTICLESHIP</p>
                    <p class="mt-1 text-xl font-black text-slate-900">Max 180 Days</p>
                    <p class="mt-1.5 text-[11px] text-slate-600 leading-relaxed">
                        Default regular leaves: Casual (14d) + Sick (14d) + Emergency (7d) = 35d. Remaining <strong>145 Days</strong> automatically assigned as <strong>ICAB Study / Exam Leave</strong>.
                    </p>
                </div>
                <div class="rounded-xl border border-teal-200 bg-teal-50/50 p-4">
                    <p class="text-[10px] font-black uppercase text-teal-800">4-YEAR ARTICLESHIP</p>
                    <p class="mt-1 text-xl font-black text-slate-900">Max 240 Days</p>
                    <p class="mt-1.5 text-[11px] text-slate-600 leading-relaxed">
                        Default regular leaves: Casual (14d) + Sick (14d) + Emergency (7d) = 35d. Remaining <strong>205 Days</strong> automatically assigned as <strong>ICAB Study / Exam Leave</strong>.
                    </p>
                </div>
            </div>
            <div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/50 p-4 text-xs">
                <div class="flex items-center justify-between">
                    <span class="font-extrabold text-indigo-950">Bangladesh Corporate Staff / Manager Standard Leave</span>
                    <span class="rounded bg-indigo-100 px-2 py-0.5 text-[10px] font-black text-indigo-900">Default 44 Days/Yr</span>
                </div>
                <p class="mt-1 text-[11px] text-slate-600">
                    Casual Leave (10d) · Medical / Sick Leave (14d) · Earned / Privilege Leave (15d) · Emergency / Festival Leave (5d). Fully customizable when creating or editing any profile.
                </p>
            </div>
        </section>

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">Unit Default Leave Quotas ({{ $partnerUnit }})</h3>
                    <p class="text-xs text-slate-500">Edit default category labels and day limits below.</p>
                </div>
            </div>
            <div class="mt-4 space-y-2.5 text-xs">
                @foreach($leaveQuotas as $qIdx => $q)
                    <div wire:key="pol-leave-quota-{{ $q['key'] ?? $qIdx }}" class="flex items-center justify-between gap-2 rounded-xl border border-slate-100 bg-slate-50 px-3 py-2">
                        @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                            <input type="text" wire:model="leaveQuotas.{{ $qIdx }}.label" class="min-w-0 flex-1 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-800">
                            <div class="flex items-center gap-1.5 shrink-0">
                                <input type="number" wire:model="leaveQuotas.{{ $qIdx }}.days" min="0" max="365" class="w-16 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-center text-xs font-extrabold text-slate-900">
                                <span class="text-[10px] text-slate-500">days</span>
                            </div>
                        @else
                            <span class="font-semibold text-slate-700">{{ $q['label'] }}</span>
                            <strong class="text-slate-900">{{ $q['days'] }} Days</strong>
                        @endif
                    </div>
                @endforeach
                @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                    <button type="button" wire:click="saveLeaveQuotas" class="mt-2 w-full rounded-xl bg-[#17191F] py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 cursor-pointer">
                        Save Default Leave Quotas
                    </button>
                @endif
            </div>
        </section>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 3: GOVERNANCE REQUIREMENTS (COLLAPSIBLE EDITOR)               -->
    <!-- ================================================================= -->
    <div x-show="activePolicyTab === 'governance'" x-cloak>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3.5">
                <div>
                    <h3 class="text-sm font-extrabold text-slate-900">Governance Requirements &amp; Compliance Mandates</h3>
                    <p class="mt-0.5 text-xs text-slate-500">Core practice governance rules enforced across all Partner Units.</p>
                </div>
                @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                    <button type="button" @click="editGovOpen = !editGovOpen" class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-extrabold text-amber-950 hover:bg-amber-100 cursor-pointer">
                        <i data-lucide="edit-3" class="h-3.5 w-3.5"></i>
                        <span x-text="editGovOpen ? 'Done Editing' : 'Edit Governance Rules'">Edit Governance Rules</span>
                    </button>
                @endif
            </div>

            <!-- Read-Only Clean Cards (Default) -->
            <div x-show="!editGovOpen" class="mt-4 grid gap-2.5 sm:grid-cols-2">
                @foreach (($governanceRules ?? []) as $gIdx => $rule)
                    <div wire:key="gov-read-{{ $gIdx }}" class="flex items-start gap-2.5 rounded-xl border border-slate-100 bg-slate-50 p-3.5 text-xs text-slate-700">
                        <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-emerald-600 mt-0.5"></i>
                        <span class="font-semibold">{{ $rule }}</span>
                    </div>
                @endforeach
            </div>

            <!-- Collapsible Edit Mode -->
            @if($previewRole === 'partner' || ($canManagePolicies ?? false))
                <div x-show="editGovOpen" x-cloak class="mt-4 space-y-2.5">
                    @foreach (($governanceRules ?? []) as $gIdx => $rule)
                        <div wire:key="gov-rule-{{ $gIdx }}" class="flex items-center gap-2 text-xs leading-relaxed text-slate-600">
                            <i data-lucide="check-circle-2" class="h-4 w-4 shrink-0 text-amber-700"></i>
                            <input type="text" wire:model="governanceRules.{{ $gIdx }}" class="flex-1 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-semibold text-slate-800 outline-none focus:border-amber-400">
                            <button type="button" wire:click="removeGovernanceRule({{ $gIdx }})" class="rounded p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Remove rule">✕</button>
                        </div>
                    @endforeach
                    <div class="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3 text-xs">
                        <input type="text" wire:model="newGovernanceRule" placeholder="Add new governance rule..." class="flex-1 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs outline-none focus:border-amber-400">
                        <button type="button" wire:click="addGovernanceRule" class="rounded-xl bg-[#FCDF02] px-4 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">+ Add Rule</button>
                        <button type="button" wire:click="savePolicySettings" class="rounded-xl bg-[#17191F] px-4 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">Save Governance Rules</button>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 4: COMPANY-WIDE POLICY CONSENSUS & VOTING                     -->
    <!-- ================================================================= -->
    <div x-show="activePolicyTab === 'consensus'" x-cloak>
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

            @if(!empty($policyProposals))
                <div class="mt-5 space-y-2.5 border-t border-slate-100 pt-4">
                    <h4 class="text-xs font-extrabold uppercase tracking-wider text-slate-700">Active Policy Proposals</h4>
                    @foreach($policyProposals as $prop)
                        <div wire:key="prop-{{ $prop['id'] }}" class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs">
                            <div>
                                <p class="font-bold text-slate-800">{{ $prop['title'] }}</p>
                                <p class="text-[11px] text-slate-500">Effective: {{ $prop['effective_date'] }} · Approvals: {{ $prop['approval_count'] }} / {{ $prop['required_votes'] }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[10px] font-bold {{ $prop['status'] === 'enacted' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900' }}">
                                    {{ strtoupper(str_replace('_', ' ', $prop['status'])) }}
                                </span>
                                @if($previewRole === 'partner' && $prop['status'] === 'pending_consensus')
                                    <button type="button" wire:click="voteOnPolicyProposal({{ $prop['id'] }})" class="rounded-lg bg-[#17191F] px-3 py-1.5 text-[11px] font-bold text-[#FCDF02] hover:bg-slate-800">
                                        Cast Partner Vote
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><i data-lucide="shield" class="mt-0.5 h-4 w-4 shrink-0"></i><span><strong>Consensus Audit:</strong> When a policy amendment is proposed, every voting Partner must record approval before activation into production payroll and attendance logic.</span></div>
        </section>
    </div>

    <!-- ================================================================= -->
    <!-- TAB 5: IMMUTABLE COMPLIANCE AUDIT LOG                             -->
    <!-- ================================================================= -->
    <div x-show="activePolicyTab === 'audit'" x-cloak>
        @if(!empty($auditLogsList))
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div>
                        <h3 class="text-sm font-extrabold">Immutable Compliance Audit Log</h3>
                        <p class="text-xs text-slate-500">Recent governance, attendance, and financial decisions</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-bold text-slate-700">{{ count($auditLogsList) }} Entries</span>
                </div>
                <div class="mt-3 divide-y divide-slate-100 text-xs">
                    @foreach($auditLogsList as $log)
                        <div wire:key="audit-log-{{ $log['id'] }}" class="py-2.5 flex items-start justify-between gap-3">
                            <div>
                                <span class="rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">{{ $log['module'] }}</span>
                                <span class="ml-1.5 font-semibold text-slate-800">{{ $log['summary'] }}</span>
                            </div>
                            <span class="shrink-0 text-[10px] text-slate-400">{{ $log['time'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @else
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-xs text-slate-500">
                No audit trail entries recorded yet.
            </div>
        @endif
    </div>
</div>