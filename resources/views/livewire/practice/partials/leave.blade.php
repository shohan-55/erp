<section class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">LEAVE MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Leave Requests &amp; Multi-Tier Approvals</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">2-Level Hierarchy: Student Application → Manager Verification &amp; Recommendation → Home Partner Final Sanction.</p>
    </div>
    @if($previewRole !== 'student')
        <button type="button" wire:click="openDialog('leave')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]">
            <i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Leave Application
        </button>
    @endif
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <div class="space-y-5">
        @if($previewRole === 'student')
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-base font-extrabold">New Leave Application</h3>
                    <span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">Application Form</span>
                </div>
                <p class="mt-2 text-xs text-slate-500">Select leave category, specify dates, and submit. Application will be routed to your assigned Manager.</p>

                <form wire:submit.prevent="submitLeaveApplication" class="mt-6 space-y-4" novalidate>
                    <label class="block text-xs font-bold text-slate-700">Leave Category
                        <select wire:model="leaveType" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                            <option value="annual">Annual Leave (Casual)</option>
                            <option value="sick">Sick / Medical Leave</option>
                            <option value="emergency">Emergency Family Leave</option>
                            <option value="icab_exam">ICAB Exam Preparation Leave</option>
                            <option value="icab_study">ICAB Study Leave (Certificate/Professional)</option>
                        </select>
                    </label>

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
            <!-- Multi-Tier Review Queue for Manager & Partner -->
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold">{{ $previewRole === 'partner' ? 'Partner Final Approval Queue' : 'Manager Review & Recommendation Queue' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $partnerUnit }} · 2-Tier Approval Chain</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ count($leaveQueue) }} Requests</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($leaveQueue as $request)
                        <div wire:key="leave-{{ $request['id'] }}" class="flex flex-col gap-3 p-5 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-bold text-slate-800">{{ $request['name'] }}</p>
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600">{{ $request['student_code'] }}</span>
                                    <span class="rounded bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800">{{ $request['type'] }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">{{ $request['period'] }} · <em>"{{ $request['reason'] }}"</em></p>
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

                                @if($previewRole === 'manager' && $request['status'] === 'pending_manager')
                                    <button type="button" wire:click="managerRecommendLeave('{{ $request['id'] }}', 'Verified with audit engagement schedule. Recommended.')"
                                            class="rounded-lg bg-[#FCDF02] px-3 py-1.5 text-xs font-bold text-[#17191F] hover:bg-[#F3D604]">
                                        Forward to Partner →
                                    </button>
                                    <button type="button" wire:click="rejectLeave('{{ $request['id'] }}', 'Engagement deadline conflict.')"
                                            class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-50">
                                        Reject
                                    </button>
                                @elseif($previewRole === 'partner' && in_array($request['status'], ['pending_partner', 'pending_manager']))
                                    <button type="button" wire:click="partnerApproveLeave('{{ $request['id'] }}', 'Sanctioned by Partner.')"
                                            class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-emerald-700">
                                        Approve
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
                                <p class="font-bold text-slate-800">{{ $item['type'] }} ({{ $item['period'] }})</p>
                                <p class="text-[11px] text-slate-500">Reason: {{ $item['reason'] }}</p>
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
                        <span class="text-xs font-extrabold text-amber-800">TIER 1</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Student Applies</p>
                        <p class="mt-1 text-xs text-slate-500">Regular or ICAB Exam Leave submitted with study dates.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-indigo-700">TIER 2</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Manager Reviews</p>
                        <p class="mt-1 text-xs text-slate-500">Audit site coverage verified; recommendation forwarded to Partner.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-emerald-700">TIER 3</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Partner Final Sanction</p>
                        <p class="mt-1 text-xs text-slate-500">Home Partner grants final approval or rejects with reasons.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <!-- Aside policy & ICAB quota overview -->
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">ICAB Leave Quota &amp; Policy</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Articled Students are governed by ICAB training bylaws and {{ $partnerUnit }} rules.</p>
            <div class="mt-5 space-y-3 text-xs">
                <div class="flex justify-between border-b border-white/10 pb-2.5">
                    <span class="text-slate-400">Casual / Annual</span>
                    <strong>14 Days / Year</strong>
                </div>
                <div class="flex justify-between border-b border-white/10 pb-2.5">
                    <span class="text-slate-400">Medical / Sick</span>
                    <strong>14 Days / Year</strong>
                </div>
                <div class="flex justify-between border-b border-white/10 pb-2.5">
                    <span class="text-slate-400">ICAB Exam Study</span>
                    <strong class="text-[#FCDF02]">Up to 30 Days (Per Level)</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-400">Approval Hierarchy</span>
                    <strong class="text-amber-400">Manager → Partner</strong>
                </div>
            </div>
        </section>

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3">
                <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                    <i data-lucide="shield-alert" class="h-5 w-5"></i>
                </div>
                <div>
                    <h3 class="text-sm font-extrabold">No Manager-Only Bypass</h3>
                    <p class="mt-1 text-xs leading-relaxed text-slate-500">Per CA firm governance, a Manager cannot approve leave independently without Partner sanction unless specifically delegated in writing.</p>
                </div>
            </div>
        </section>
    </aside>
</div>