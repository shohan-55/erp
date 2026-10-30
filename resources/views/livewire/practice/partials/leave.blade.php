<section class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">LEAVE MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Leave Requests &amp; Partial Approval Workflow</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Engage your supervising Manager on the form → Manager Verification → Partner Full or Partial Approval ({{ $partnerUnit }} isolated scope).</p>
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
                <p class="mt-2 text-xs text-slate-500">Select your leave category, mention the Manager you are currently engaged with, and specify your dates.</p>

                <form wire:key="student-leave-form-{{ $formResetIteration ?? 1 }}" wire:submit.prevent="requestLeaveSubmissionConfirm" class="mt-6 space-y-4" novalidate>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Leave Category
                            <select wire:model="leaveType" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                                @foreach($leaveQuotas as $q)
                                    <option value="{{ $q['key'] }}">{{ $q['label'] }} ({{ $q['days'] }} Days)</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block text-xs font-bold text-slate-700">Mention Engaged Manager (কাজে যুক্ত ম্যানেজার)
                            <select wire:model="leaveEngagedManagerId" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
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
            <!-- Multi-Tier Review Queue for Manager & Partner -->
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-extrabold">{{ $previewRole === 'partner' ? 'Partner Final Approval Queue' : 'Manager Review & Recommendation Queue' }}</h3>
                        <p class="mt-1 text-xs text-slate-500">{{ $partnerUnit }} · Supports Full Approve, Partial Approve (আংশিক ছুটি), or Reject</p>
                    </div>
                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-bold text-slate-700">{{ count($leaveQueue) }} Requests</span>
                </div>

                <div class="divide-y divide-slate-100">
                    @forelse($leaveQueue as $request)
                        @php
                            $isUnreadLeave = ($previewRole === 'manager' && ($request['status'] ?? '') === 'pending_manager')
                                || ($previewRole === 'partner' && in_array($request['status'] ?? '', ['pending_partner', 'pending_manager'], true));
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
                                    <button type="button" wire:click="exportLeaveSanctionLetter('{{ $request['id'] }}')" class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800 hover:bg-emerald-100" title="Print Official Leave Sanction Letter">
                                        <i data-lucide="printer" class="h-3 w-3"></i> Print Letter
                                    </button>
                                @endif

                                @if($previewRole === 'manager' && $request['status'] === 'pending_manager')
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
                                @elseif($previewRole === 'partner' && in_array($request['status'], ['pending_partner', 'pending_manager']))
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
                                <p class="font-bold text-slate-800">{{ $item['type'] }} ({{ $item['period'] }}) · Mgr: {{ $item['manager'] ?? 'Assigned' }}</p>
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
                        <span class="text-xs font-extrabold text-amber-800">TIER 1</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Student Mentions Manager</p>
                        <p class="mt-1 text-xs text-slate-500">Student selects the specific Manager they are engaged with on the audit.</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-indigo-700">TIER 2</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Manager Reviews</p>
                        <p class="mt-1 text-xs text-slate-500">Can recommend full leave or modify/exclude middle dates (Partial Approve).</p>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100">
                        <span class="text-xs font-extrabold text-emerald-700">TIER 3</span>
                        <p class="mt-1 text-sm font-bold text-slate-800">Partner Final Sanction</p>
                        <p class="mt-1 text-xs text-slate-500">Home Partner grants full/partial approval or rejects with remarks.</p>
                    </div>
                </div>
            @endif
        </section>
    </div>

    <!-- Aside policy & Partner customizable leave quota overview -->
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">Partner Leave Quotas ({{ $partnerUnit }})</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Leave categories and annual day limits configured by {{ $partnerUnit }}.</p>
            <div class="mt-5 space-y-3 text-xs">
                @foreach($leaveQuotas as $q)
                    <div class="flex justify-between border-b border-white/10 pb-2.5">
                        <span class="text-slate-400">{{ $q['label'] }}</span>
                        <strong class="text-[#FCDF02]">{{ $q['days'] }} Days / Year</strong>
                    </div>
                @endforeach
            </div>
        </section>

        @if(in_array($previewRole, ['partner', 'manager']))
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                <h3 class="text-sm font-extrabold">Add Custom Leave Category</h3>
                <p class="mt-1 text-xs text-slate-500">Partners can add custom leave types and day quotas for their unit.</p>
                <form wire:submit.prevent="addCustomLeaveType" class="mt-3 space-y-3 text-xs">
                    <label class="block font-bold text-slate-700">Leave Category Name
                        <input type="text" wire:model="newLeaveTypeLabel" placeholder="e.g. Marriage / Paternity Leave" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    </label>
                    <label class="block font-bold text-slate-700">Allowed Days / Year
                        <input type="number" wire:model="newLeaveTypeDays" min="1" max="180" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                    </label>
                    <button type="submit" class="w-full rounded-xl bg-[#17191F] py-2.5 font-extrabold text-[#FCDF02] hover:bg-slate-800">+ Save Leave Category</button>
                </form>
            </section>
        @endif
    </aside>
</div>

{{-- Partial Leave Approval Modal (আংশিক ছুটি অনুমোদন) --}}
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
                <div class="rounded-xl bg-amber-50 p-3 text-amber-950">
                    Student requested <strong>{{ $partialRequestedDays }} day(s)</strong>. You can reduce or exclude middle dates and approve a partial number of days.
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
                Click <strong>OK, Confirm &amp; Submit</strong> to send this application to your supervising Manager and Partner. Duplicate requests for the same dates are automatically blocked.
            </p>

            <div class="mt-5 flex justify-end gap-2.5">
                <button type="button" wire:click="cancelSubmissionConfirm" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 cursor-pointer">
                    Cancel / Edit
                </button>
                <button type="button" wire:click="confirmAndSubmitLeaveApplication" wire:loading.attr="disabled" class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-5 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-md disabled:opacity-50 cursor-pointer">
                    <i data-lucide="check-circle-2" class="h-4 w-4"></i>
                    <span wire:loading.remove wire:target="confirmAndSubmitLeaveApplication">OK, Confirm &amp; Submit</span>
                    <span wire:loading wire:target="confirmAndSubmitLeaveApplication">Submitting...</span>
                </button>
            </div>
        </div>
    </div>
@endif
