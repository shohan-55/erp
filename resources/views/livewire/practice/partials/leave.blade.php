<section class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">LEAVE MANAGEMENT &amp; PROFILE PASSBOOK</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Leave Entitlements, Balance Ledger &amp; Approval Workflow</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">ICAB Articleship (3-Yr: 180 Days Max · 4-Yr: 240 Days Max with auto-calculated Study Leave) &amp; Bangladesh Corporate Staff Leave Ledger ({{ $partnerUnit }}).</p>
    </div>
    @if($previewRole !== 'student')
        <button type="button" wire:click="openDialog('leave')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]">
            <i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Leave Application
        </button>
    @endif
</section>

@if(in_array($previewRole, ['student', 'manager'], true) && !empty($myLeaveBalanceSummary))
    <!-- Personal Leave Passbook & Live Deducting Balance Banner -->
    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-[#11141C] via-[#171B26] to-[#11141C] px-5 py-4 text-white">
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCDF02] text-[#17191F] font-black">
                    <i data-lucide="calendar-check-2" class="h-5 w-5"></i>
                </div>
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded-full bg-emerald-500/20 border border-emerald-400/40 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-emerald-300">
                            {{ $myLeaveBalanceSummary['regime_label'] ?? 'Profile Leave Ledger' }}
                        </span>
                        @if(($myLeaveBalanceSummary['regime'] ?? '') === 'icab_articleship' && !empty($myLeaveBalanceSummary['active_window']['label']))
                            <span class="rounded-full bg-amber-400/20 border border-amber-300/40 px-2.5 py-0.5 text-[10px] font-extrabold text-[#FCDF02]">
                                Active Cycle: {{ $myLeaveBalanceSummary['active_window']['label'] }} ({{ $myLeaveBalanceSummary['active_window']['period_label'] ?? '' }})
                            </span>
                        @else
                            <span class="rounded-full bg-indigo-500/25 border border-indigo-400/40 px-2.5 py-0.5 text-[10px] font-extrabold text-indigo-200">
                                Yearly Reset: {{ $myLeaveBalanceSummary['calendar_year_label'] ?? 'Jan 1 – Dec 31' }}
                            </span>
                            @if(($myLeaveBalanceSummary['carry_forward_earned'] ?? 0) > 0)
                                <span class="rounded-full bg-emerald-500/25 border border-emerald-400/40 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-200">
                                    +{{ $myLeaveBalanceSummary['carry_forward_earned'] }}d Carry-Forward Earned Included
                                </span>
                            @endif
                        @endif
                        <h3 class="text-sm font-extrabold text-white">My Profile Leave Balance &amp; Entitlement Passbook</h3>
                    </div>
                    <p class="mt-0.5 text-[11px] text-slate-300">
                        @if(($myLeaveBalanceSummary['regime'] ?? '') === 'icab_articleship')
                            Evenly split at <strong>{{ $myLeaveBalanceSummary['per_year_cap'] ?? 60 }} days/year</strong> across your {{ $myLeaveBalanceSummary['articleship_duration_years'] ?? 3 }}-year articleship (12-month anniversary cycles from joining date). Unused regular leave rolls into your <strong>ICAB Study &amp; Exam Pool</strong>.
                        @else
                            Corporate leave resets every <strong>Jan 1 – Dec 31</strong>. Unused regular leave from the previous year automatically carries forward into your <strong>Earned Leave</strong> balance.
                        @endif
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="rounded-xl border border-white/15 bg-white/10 px-3.5 py-2 text-right">
                    <span class="block text-[10px] font-bold uppercase tracking-wider text-slate-300">Total Available Leave</span>
                    <strong class="text-base font-black text-[#FCDF02]">{{ $myLeaveBalanceSummary['total_available'] ?? 0 }} <span class="text-xs font-semibold text-white/80">/ {{ $myLeaveBalanceSummary['total_cap'] ?? 180 }} Days</span></strong>
                </div>
            </div>
        </div>

        <div class="grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4 bg-slate-50/60">
            @foreach(($myLeaveBalanceSummary['categories'] ?? []) as $cat)
                @php
                    $alloc = max(1, (int) ($cat['allocated'] ?? 1));
                    $comm  = (int) ($cat['committed'] ?? 0);
                    $avail = (int) ($cat['available'] ?? 0);
                    $pct   = min(100, max(0, (int) round(($avail / $alloc) * 100)));
                    $isArticleship = ($myLeaveBalanceSummary['regime'] ?? '') === 'icab_articleship';
                @endphp
                <div wire:key="my-leave-cat-{{ $cat['key'] }}" class="rounded-xl border {{ !empty($cat['is_study']) ? 'border-indigo-200 bg-indigo-50/40' : 'border-slate-200/90 bg-white' }} p-3.5 shadow-2xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-extrabold {{ !empty($cat['is_study']) ? 'text-indigo-950' : 'text-slate-800' }}">{{ $cat['label'] }}</span>
                        @if(!empty($cat['is_study']))
                            <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-black uppercase text-indigo-800">Study Pool ({{ $cat['per_year_allocated'] ?? 25 }}d/yr)</span>
                        @elseif($isArticleship)
                            <span class="rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800">
                                This Yr: {{ $cat['year_available'] ?? $cat['per_year_allocated'] }}/{{ $cat['per_year_allocated'] ?? 14 }}d
                            </span>
                        @elseif(($cat['carry_forward'] ?? 0) > 0)
                            <span class="rounded bg-emerald-50 border border-emerald-200 px-1.5 py-0.5 text-[9px] font-bold text-emerald-800">
                                +{{ $cat['carry_forward'] }}d Carry-Fwd
                            </span>
                        @endif
                    </div>
                    <div class="mt-2 flex items-baseline justify-between">
                        <div>
                            <span class="text-xl font-black {{ $avail > 0 ? 'text-emerald-700' : 'text-rose-600' }}">{{ $avail }}</span>
                            <span class="text-[11px] font-bold text-slate-500">/ {{ $cat['allocated'] }} Days {{ $isArticleship ? '(' . ($myLeaveBalanceSummary['articleship_duration_years'] ?? 3) . '-Yr Total)' : 'left' }}</span>
                        </div>
                        <span class="text-[10px] font-bold text-slate-500">
                            Used: {{ $cat['used'] }}d{{ ($cat['pending'] ?? 0) > 0 ? ' · Pend: ' . $cat['pending'] . 'd' : '' }}
                        </span>
                    </div>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200">
                        <div class="h-full rounded-full transition-all duration-500 {{ $pct > 40 ? 'bg-emerald-500' : ($pct > 15 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <div class="space-y-5">
        @if($previewRole === 'student')
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-extrabold">New Leave Application</h3>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">
                        Available: {{ $myLeaveBalanceSummary['total_available'] ?? 180 }} / {{ $myLeaveBalanceSummary['total_cap'] ?? 180 }} Days
                    </span>
                </div>
                <p class="mt-2 text-xs text-slate-500">Select your leave category, mention the Manager you are currently engaged with, and specify your dates.</p>

                <form wire:key="student-leave-form-{{ $formResetIteration ?? 1 }}" wire:submit.prevent="requestLeaveSubmissionConfirm" class="mt-6 space-y-4" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Leave Category
                            <select wire:model="leaveType" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                                @foreach($leaveQuotas as $q)
                                    @php
                                        $catMatch = collect($myLeaveBalanceSummary['categories'] ?? [])->first(function ($c) use ($q) {
                                            if (in_array($q['key'], ['icab_study', 'icab_exam'], true) && $c['key'] === 'icab_study') return true;
                                            return $c['key'] === $q['key'];
                                        });
                                        $remLabel = $catMatch ? ($catMatch['available'] . ' of ' . $catMatch['allocated'] . ' Days Available') : ($q['days'] . ' Days');
                                    @endphp
                                    <option value="{{ $q['key'] }}">{{ $q['label'] }} ({{ $remLabel }})</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-xs font-bold text-slate-700">Mention Engaged Manager
                            <select wire:model.live="leaveEngagedManagerId" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                                <option value="">— Assigned / Default Manager —</option>
                                @foreach($availableManagers as $mgr)
                                    <option value="{{ $mgr['id'] }}">{{ $mgr['name'] }} ({{ $mgr['partner_unit'] ?? $partnerUnit }})</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Start Date
                            <input type="date" wire:model="leaveStart" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">End Date
                            <input type="date" wire:model="leaveEnd" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <label class="block text-xs font-bold text-slate-700">Reason / Exam Details
                        <textarea wire:model="leaveReason" rows="3" minlength="5" maxlength="500" placeholder="State reason, client engagement coverage, or ICAB exam papers" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400"></textarea>
                    </label>

                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                        <i data-lucide="send" class="h-4 w-4"></i> Submit Leave Application
                    </button>
                </form>
            </section>
        @else
            @if($previewRole === 'manager')
                <!-- Manager / Regular Corporate Staff Personal Leave Application Card -->
                <section x-data="{ openMgrLeave: false }" class="overflow-hidden rounded-2xl border border-indigo-200 bg-white shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-indigo-50/60 px-5 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-600 text-white">
                                <i data-lucide="calendar-plus" class="h-4 w-4"></i>
                            </div>
                            <div>
                                <h3 class="text-sm font-extrabold text-slate-900">Apply for Personal Corporate Leave</h3>
                                <p class="text-[11px] text-slate-600">Submit your own Casual, Sick, Earned, or Emergency leave directly to the Partner. Deducts from your {{ $myLeaveBalanceSummary['total_cap'] ?? 44 }}-day corporate balance.</p>
                            </div>
                        </div>
                        <button type="button" @click="openMgrLeave = !openMgrLeave" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17191F] px-3.5 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 cursor-pointer">
                            <span x-text="openMgrLeave ? '✕ Hide Leave Form' : '+ Apply for My Leave'">+ Apply for My Leave</span>
                        </button>
                    </div>

                    <div x-show="openMgrLeave" x-cloak class="border-t border-indigo-100 p-5">
                        <form wire:key="manager-leave-form-{{ $formResetIteration ?? 1 }}" wire:submit.prevent="requestLeaveSubmissionConfirm" class="space-y-4 text-xs" novalidate>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <label class="block font-bold text-slate-700">Corporate Leave Type
                                    <select wire:model="leaveType" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-semibold outline-none focus:border-indigo-400">
                                        @foreach(($myLeaveBalanceSummary['categories'] ?? []) as $mCat)
                                            <option value="{{ $mCat['key'] }}">{{ $mCat['label'] }} ({{ $mCat['available'] }}/{{ $mCat['allocated'] }}d left)</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block font-bold text-slate-700">Start Date
                                    <input type="date" wire:model="leaveStart" required class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-indigo-400">
                                </label>
                                <label class="block font-bold text-slate-700">End Date
                                    <input type="date" wire:model="leaveEnd" required class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-indigo-400">
                                </label>
                            </div>
                            <label class="block font-bold text-slate-700">Reason / Handover Note
                                <textarea wire:model="leaveReason" rows="2" placeholder="State reason and audit engagement handover details for Partner sanction..." class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-indigo-400"></textarea>
                            </label>
                            <div class="flex justify-end">
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-sm">
                                    <i data-lucide="send" class="h-4 w-4"></i> Submit Leave to Partner
                                </button>
                            </div>
                        </form>
                    </div>
                </section>
            @endif

            <!-- Multi-Tier Review Queue for Manager & Partner -->
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold">{{ $previewRole === 'partner' ? 'Partner Final Approval Queue' : 'Manager Review & Recommendation Queue' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $partnerUnit }} · Supports Full Approve, Partial Approve, or Reject</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ count($leaveQueue) }} Requests</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($leaveQueue as $request)
                        @php
                            $isOwnManagerLeave = ($previewRole === 'manager') && (($request['submitter_role'] ?? 'student') === 'manager' || (Auth::check() && (int) ($request['user_id'] ?? 0) === (int) Auth::id()));
                            $isUnreadLeave = ($previewRole === 'manager' && in_array($request['status'] ?? '', ['pending_manager', 'Awaiting Manager'], true) && !$isOwnManagerLeave)
                                || ($previewRole === 'partner' && in_array($request['status'] ?? '', ['pending_partner', 'pending_manager', 'Awaiting Manager', 'Awaiting Partner'], true));
                        @endphp
                        <div wire:key="leave-{{ $request['id'] }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between transition {{ $isUnreadLeave ? 'bg-rose-50/35 border-l-4 border-l-rose-500' : 'bg-white' }}">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isUnreadLeave)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wider text-white shadow-2xs animate-pulse">
                                            <span class="h-1.5 w-1.5 rounded-full bg-white"></span> NEW UNREAD
                                        </span>
                                    @endif
                                    <p class="text-sm font-bold text-slate-800">{{ $request['name'] }}</p>
                                    @if(($request['submitter_role'] ?? 'student') === 'manager')
                                        <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-extrabold uppercase text-indigo-800">{{ $request['designation'] ?? 'Manager' }}</span>
                                    @endif
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $request['student_code'] }}</span>
                                    <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $request['type'] }}</span>
                                    <span class="rounded bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700">Engaged Mgr: {{ $request['manager'] ?? 'Direct' }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">
                                    <strong>{{ $request['period'] }}</strong> ({{ $request['requested_days'] ?? 1 }} Days requested) · <em>"{{ $request['reason'] }}"</em>
                                </p>
                                @if(!empty($request['approved_dates_summary']))
                                    <p class="mt-1 text-[11px] font-bold text-amber-900">Partial Schedule: {{ $request['approved_dates_summary'] }}</p>
                                @endif
                                @if(!empty($request['manager_remarks']))
                                    <p class="mt-1 text-[11px] font-semibold text-indigo-700"><i data-lucide="message-square" class="inline h-3 w-3 mr-0.5"></i> Manager: {{ $request['manager_remarks'] }}</p>
                                @endif
                                @if(!empty($request['partner_remarks']))
                                    <p class="mt-1 text-[11px] font-semibold text-emerald-700"><i data-lucide="check" class="inline h-3 w-3 mr-0.5"></i> Partner: {{ $request['partner_remarks'] }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold
                                    {{ $request['status'] === 'approved' ? 'bg-emerald-50 text-emerald-700' : ($request['status'] === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-800') }}">
                                    {{ $request['status_label'] }}
                                </span>
                                @if($request['status'] === 'approved')
                                    <button type="button" wire:click="exportLeaveSanctionLetter('{{ $request['id'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800 hover:bg-emerald-100" title="Download Official PDF Leave Sanction Letter">
                                        <i data-lucide="file-down" class="h-3 w-3"></i> PDF Letter
                                    </button>
                                    @php
                                        $waLeaveMsg = "Hello " . ($request['name'] ?? '') . ", your " . ($request['type'] ?? 'Leave') . " application (" . ($request['id'] ?? '') . " for " . ($request['period'] ?? '') . ") has been APPROVED. — " . ($firm['name'] ?? 'Practice');
                                        $waLeaveUrl = \App\Livewire\Practice\PracticePreview::formatWhatsAppUrl($request['phone'] ?? null, $waLeaveMsg);
                                    @endphp
                                    <a href="{{ $waLeaveUrl }}" target="_blank" rel="noopener" data-no-loading class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-white px-2 py-1 text-[10px] font-extrabold text-emerald-800 hover:bg-emerald-50" title="Send WhatsApp Approval Confirmation">
                                        WhatsApp ↗
                                    </a>
                                @endif

                                @if($previewRole === 'manager' && in_array($request['status'], ['pending_manager', 'Awaiting Manager'], true) && !$isOwnManagerLeave)
                                    @if($canFinalApproveLeave ?? false)
                                        <button type="button" wire:click="partnerApproveLeave('{{ $request['id'] }}', 'Sanctioned by authorized Manager.')"
                                                class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                            Approve Full ✓
                                        </button>
                                    @endif
                                    <button type="button" wire:click="managerRecommendLeave('{{ $request['id'] }}', 'Verified with audit engagement schedule. Recommended.')"
                                            class="rounded-lg bg-[#FCDF02] px-3 py-1.5 text-xs font-bold text-[#17191F] hover:bg-[#F3D604]">
                                        Forward to Partner →
                                    </button>
                                    <button type="button" wire:click="openPartialLeaveModal('{{ $request['id'] }}')"
                                            class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-900 hover:bg-amber-100">
                                        Partial Approve
                                    </button>
                                    <button type="button" wire:click="rejectLeave('{{ $request['id'] }}', 'Engagement deadline conflict.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @elseif(($previewRole === 'partner' || (($canFinalApproveLeave ?? false) && !$isOwnManagerLeave)) && in_array($request['status'], ['pending_partner', 'pending_manager', 'Awaiting Manager', 'Awaiting Partner']))
                                    <button type="button" wire:click="partnerApproveLeave('{{ $request['id'] }}', 'Sanctioned by Partner.')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                        Approve Full
                                    </button>
                                    <button type="button" wire:click="openPartialLeaveModal('{{ $request['id'] }}')"
                                            class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-xs font-bold text-amber-900 hover:bg-amber-100">
                                        Partial Approve
                                    </button>
                                    <button type="button" wire:click="rejectLeave('{{ $request['id'] }}', 'Partner rejected due to critical audit.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @else
                                    <button type="button" wire:click="openDialog('leave')" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold hover:bg-slate-50">
                                        Review UI
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-500">No pending leave applications in this queue.</div>
                    @endforelse
                </div>
            </section>
        @endif

        <!-- Decision Workflow Explanation -->
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between">
                <h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Leave Applications' : 'Multi-Tier Approval Hierarchy' }}</h3>
                <i data-lucide="git-branch" class="h-5 w-5 text-amber-800"></i>
            </div>
            @if($previewRole === 'student')
                <div class="space-y-2">
                    @forelse($leaveQueue as $item)
                        <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3.5 text-xs">
                            <div>
                                <p class="font-bold text-slate-800">{{ $item['type'] }} ({{ $item['period'] }}) · {{ $item['requested_days'] ?? 1 }}d · Mgr: {{ $item['manager'] ?? 'Assigned' }}</p>
                                <p class="text-[11px] text-slate-500">Reason: {{ $item['reason'] }}</p>
                                @if(!empty($item['approved_dates_summary']))
                                    <p class="text-[11px] font-bold text-amber-800 mt-0.5">{{ $item['approved_dates_summary'] }}</p>
                                @endif
                            </div>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold bg-amber-100 text-amber-900">{{ $item['status_label'] }}</span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">No applications submitted yet.</p>
                    @endforelse
                </div>
            @else
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-amber-800">TIER 1 · ENTITLEMENT</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">3-Yr (180d) / 4-Yr (240d) &amp; Corporate</p>
                        <p class="mt-1 text-xs text-slate-500">Students get 180d (3-Yr) or 240d (4-Yr) total cap with auto Study Leave; Managers get BD corporate leave.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-indigo-700">TIER 2 · VERIFICATION</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Manager Reviews</p>
                        <p class="mt-1 text-xs text-slate-500">Can recommend full leave or modify/exclude middle dates (Partial Approve).</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-emerald-700">TIER 3 · SANCTION</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Partner Final Sanction</p>
                        <p class="mt-1 text-xs text-slate-500">Home Partner grants full/partial approval or rejects with remarks; balance updates live.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <!-- Aside policy & Partner customizable leave quota overview -->
    <aside class="space-y-5">
        <section x-data="{ editQuotasOpen: false }" class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <div class="flex items-center justify-between gap-2">
                <h3 class="text-sm font-extrabold">Partner Leave Quotas ({{ $partnerUnit }})</h3>
                @if(in_array($previewRole, ['partner', 'manager']))
                    <button type="button" @click="editQuotasOpen = !editQuotasOpen" class="rounded-lg bg-white/10 px-2.5 py-1 text-[10px] font-extrabold text-[#FCDF02] hover:bg-white/20 cursor-pointer">
                        <span x-text="editQuotasOpen ? 'Done' : '✏️ Edit Defaults'">✏️ Edit Defaults</span>
                    </button>
                @endif
            </div>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Default annual/articleship leave categories for {{ $partnerUnit }}. Individual member allocations (180d/240d ICAB or Corporate) can also be customized in <strong>Team &amp; Members</strong>.</p>

            <!-- Clean Read View -->
            <div x-show="!editQuotasOpen" class="mt-4 space-y-2.5 text-xs">
                @foreach($leaveQuotas as $qIdx => $q)
                    <div wire:key="leave-quota-read-{{ $q['key'] ?? $qIdx }}" class="flex items-center justify-between gap-2 border-b border-white/10 pb-2">
                        <span class="text-slate-300">{{ $q['label'] }}</span>
                        <strong class="text-[#FCDF02]">{{ $q['days'] }} Days</strong>
                    </div>
                @endforeach
                <div class="pt-2 space-y-1 text-[11px] text-emerald-300">
                    <p>• <strong>3-Year ICAB Articleship Cap:</strong> Max 180 Days</p>
                    <p>• <strong>4-Year ICAB Articleship Cap:</strong> Max 240 Days</p>
                </div>
            </div>

            <!-- Collapsible Edit View -->
            @if(in_array($previewRole, ['partner', 'manager']))
                <div x-show="editQuotasOpen" x-cloak class="mt-4 space-y-2.5 text-xs">
                    @foreach($leaveQuotas as $qIdx => $q)
                        <div wire:key="leave-quota-row-{{ $q['key'] ?? $qIdx }}" class="flex items-center justify-between gap-2 border-b border-white/10 pb-2.5">
                            <input type="text" wire:model="leaveQuotas.{{ $qIdx }}.label" class="min-w-0 flex-1 rounded-lg border border-white/15 bg-white/10 px-2.5 py-1.5 text-xs font-semibold text-white outline-none focus:border-amber-400">
                            <div class="flex items-center gap-1.5 shrink-0">
                                <input type="number" wire:model="leaveQuotas.{{ $qIdx }}.days" min="0" max="365" class="w-16 rounded-lg border border-white/15 bg-white/10 px-2 py-1.5 text-center text-xs font-extrabold text-[#FCDF02] outline-none focus:border-amber-400">
                                <span class="text-[10px] text-slate-400">d</span>
                                @if(count($leaveQuotas) > 1)
                                    <button type="button" wire:click="removeLeaveCategory('{{ $q['key'] }}')" class="rounded p-1 text-slate-400 hover:bg-rose-500/20 hover:text-rose-300" title="Remove leave category">✕</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    <button type="button" wire:click="saveLeaveQuotas" class="mt-3 w-full rounded-xl bg-[#FCDF02] py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                        Save Edited Leave Quotas
                    </button>
                </div>
            @endif
        </section>

        @if(in_array($previewRole, ['partner', 'manager']))
            <section x-data="{ openAddCat: false }" class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h3 class="text-sm font-extrabold">Add Custom Leave Category</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Add custom leave types for your unit.</p>
                    </div>
                    <button type="button" @click="openAddCat = !openAddCat" class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-extrabold text-slate-700 hover:bg-slate-100 cursor-pointer">
                        <span x-text="openAddCat ? 'Close' : '+ New Type'">+ New Type</span>
                    </button>
                </div>
                <form x-show="openAddCat" x-cloak wire:submit.prevent="addCustomLeaveType" class="mt-4 space-y-3 border-t border-slate-100 pt-3 text-xs">
                    <label class="block font-bold text-slate-700">Leave Category Name
                        <input type="text" wire:model="newLeaveTypeLabel" placeholder="e.g. Marriage / Paternity Leave" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    </label>
                    <label class="block font-bold text-slate-700">Allowed Days
                        <input type="number" wire:model="newLeaveTypeDays" min="1" max="240" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-[#17191F] py-2.5 font-extrabold text-[#FCDF02] hover:bg-slate-800">+ Save Leave Category</button>
                </form>
            </section>
        @endif
    </aside>
</div>

{{-- Partial Leave Approval Modal --}}
@if($showPartialLeaveModal)
    <div class="fixed inset-0 z-[75] flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">PARTIAL LEAVE APPROVAL</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-900">Approve Selected Days ({{ $partialLeaveId }})</h3>
                </div>
                <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>
            <form wire:submit.prevent="submitPartialLeaveApproval" class="mt-4 space-y-4 text-xs">
                @if($error || $errors->any())
                    <div class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800 flex items-start gap-2" role="alert">
                        <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0 mt-0.5"></i>
                        <div>
                            @if($error)
                                <p>{{ $error }}</p>
                            @endif
                            @if($errors->any())
                                <ul class="list-disc pl-4 space-y-0.5">
                                    @foreach($errors->all() as $err)
                                        <li>{{ $err }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    </div>
                @endif
                <div class="rounded-xl bg-amber-50 p-3 text-amber-950">
                    Applicant requested <strong>{{ $partialRequestedDays }} day(s)</strong>. You can reduce or exclude middle dates and approve a partial number of days. Only the approved days will be deducted from their leave balance.
                </div>
                <label class="block font-bold text-slate-700">Approved Days Count (out of {{ $partialRequestedDays }})
                    <input type="number" wire:model="partialApprovedDays" min="1" max="{{ $partialRequestedDays }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-sm font-bold">
                </label>
                <label class="block font-bold text-slate-700">Approved Dates / Excluded Middle Dates Note
                    <input type="text" wire:model="partialApprovedSummary" placeholder="e.g. Approved 2 days (14 & 18 Oct; middle dates cancelled for audit)" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                </label>
                <label class="block font-bold text-slate-700">Reviewer Remarks
                    <input type="text" wire:model="partialReviewerRemarks" placeholder="e.g. Partial sanction granted due to inventory count" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 px-4 py-2 font-bold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-[#FCDF02] px-4 py-2 font-extrabold text-[#17191F] hover:bg-[#F3D604]">Confirm Partial Approval</button>
                </div>
            </form>
        </div>
    </div>
@endif

{{-- Leave Application Submission Confirmation Popup Modal --}}
@if($showLeaveSubmitConfirmModal ?? false)
    <div class="fixed inset-0 z-[85] flex items-center justify-center bg-slate-950/65 p-4 backdrop-blur-sm" role="dialog" aria-modal="true">
        <div class="w-full max-w-md rounded-2xl border-2 border-[#FCDF02] bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]">
                        <i data-lucide="calendar-check-2" class="h-5 w-5"></i>
                    </div>
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">CONFIRM LEAVE SUBMISSION</p>
                        <h3 class="mt-0.5 text-base font-extrabold text-slate-900">Submit Leave Application?</h3>
                    </div>
                </div>
                <button type="button" wire:click="cancelSubmissionConfirm" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                    <i data-lucide="x" class="h-4 w-4"></i>
                </button>
            </div>

            <div class="mt-4 space-y-2.5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs text-slate-700">
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Leave Category:</span>
                    <strong class="text-slate-900">{{ ucfirst(str_replace('_', ' ', $leaveType)) }}</strong>
                </div>
                @if($previewRole === 'student')
                    <div class="flex items-center justify-between">
                        <span class="text-slate-500">Engaged Manager:</span>
                        <strong class="text-indigo-700">
                            @php
                                $selMgr = collect($availableManagers ?? [])->firstWhere('id', (int) ($leaveEngagedManagerId ?: (auth()->user()?->manager_id ?? 0))) ?? ($availableManagers[0] ?? null);
                            @endphp
                            {{ $selMgr['name'] ?? 'Assigned Unit Manager' }}
                        </strong>
                    </div>
                @endif
                <div class="flex items-center justify-between">
                    <span class="text-slate-500">Leave Dates:</span>
                    <strong class="text-slate-900">{{ $leaveStart }} &rarr; {{ $leaveEnd }}</strong>
                </div>
                <div class="border-t border-slate-200 pt-2">
                    <span class="text-slate-500 block mb-0.5">Reason / Details:</span>
                    <p class="font-semibold text-slate-800 italic">"{{ $leaveReason }}"</p>
                </div>
            </div>

            <p class="mt-3 text-xs leading-relaxed text-slate-500">
                Click <strong>OK, Confirm &amp; Submit</strong> to dispatch this application. The requested days will be reserved from your available profile leave balance immediately.
            </p>

            <div class="mt-5 flex justify-end gap-2.5">
                <button type="button" wire:click="cancelSubmissionConfirm" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                    Cancel / Edit
                </button>
                <button type="button" wire:click="confirmAndSubmitLeaveApplication" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-5 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-md disabled:opacity-50 cursor-pointer">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                    <span wire:loading.remove wire:target="confirmAndSubmitLeaveApplication">OK, Confirm &amp; Submit</span>
                    <span wire:loading wire:target="confirmAndSubmitLeaveApplication" style="display:none">Submitting...</span>
                </button>
            </div>
        </div>
    </div>
@endif
