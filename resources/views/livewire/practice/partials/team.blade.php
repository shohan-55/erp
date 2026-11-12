<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">PRACTICE GOVERNANCE · TEAM MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Team &amp; Member Roster</h2>
        <p class="mt-1.5 text-xs text-slate-500">Manage practice partners, audit managers, and articled students. Assign supervisors, allowances, delegated permissions, and reset passwords.</p>
    </div>
    <div class="flex items-center gap-2">
        @if($canManageTeam)
            <button type="button" wire:click="openAddMemberModal" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] transition hover:bg-[#F3D604] shadow-sm">
                <i data-lucide="user-plus" class="h-4 w-4"></i> Add New Member
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                <i data-lucide="shield-alert" class="h-4 w-4 text-slate-400"></i> {{ ($canResetPasswords ?? false) ? 'Password Reset Enabled' : 'View-Only Roster' }}
            </span>
        @endif
    </div>
</section>

@if(! $canManageTeam)
    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
        <i data-lucide="info" class="h-4 w-4 shrink-0 text-amber-700 mt-0.5"></i>
        <span>
            @if($canResetPasswords ?? false)
                You can reset member passwords from this roster (the new password is automatically emailed to the member). Adding or removing members requires Team Admin authorization from a Partner.
            @else
                You are currently viewing the roster in <strong>read-only</strong> mode. Adding, editing, or removing practice members requires authorization delegated by an active Partner.
            @endif
        </span>
    </div>
@endif

<!-- Roster KPI Statistics -->
<section class="grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Team metrics">
    @php
        $rosterPool   = $allTeamMembers ?? $teamMembers;
        $partnerCount = count(array_filter($rosterPool, fn($m) => ($m['role'] ?? '') === 'partner'));
        $managerCount = count(array_filter($rosterPool, fn($m) => ($m['role'] ?? '') === 'manager'));
        $studentCount = count(array_filter($rosterPool, fn($m) => ($m['role'] ?? '') === 'student'));
        $totalCount   = count($rosterPool);
        $showActionColumn = $canManageTeam || ($canEditMembers ?? false) || ($canResetPasswords ?? false);
    @endphp
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Total Roster</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-800"><i data-lucide="users" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($totalCount) }}</p>
        <p class="text-[10px] text-slate-400">Enrolled practice staff</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Partners</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-900"><i data-lucide="shield" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($partnerCount) }}</p>
        <p class="text-[10px] text-slate-400">Principals / Partners</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Managers &amp; Staff</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><i data-lucide="briefcase" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($managerCount) }}</p>
        <p class="text-[10px] text-slate-400">Managers, Officers &amp; Office Staff</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Students &amp; Interns</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><i data-lucide="graduation-cap" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($studentCount) }}</p>
        <p class="text-[10px] text-slate-400">ICAB Students &amp; Interns</p>
    </div>
</section>

<!-- Custom Firm Designations & Rank Hierarchy Manager -->
@if($canManageTeam)
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
        <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
            <div>
                <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">CA PRACTICE HIERARCHY · CUSTOM DESIGNATIONS</p>
                <h3 class="mt-0.5 text-sm font-extrabold text-slate-900">Firm Designations &amp; Career Ranks ({{ count($customDesignations ?? []) }})</h3>
                <p class="mt-0.5 text-xs text-slate-500">Add custom designations beyond Partner, Manager, and Articled Student (e.g. Senior Manager, Assistant Manager, Audit Supervisor, Course Complete, Tax Consultant) and promote or demote members anytime.</p>
            </div>
            <form wire:submit.prevent="addCustomDesignation" class="grid grid-cols-1 sm:flex sm:flex-wrap items-center gap-2 text-xs w-full sm:w-auto">
                <input type="text" wire:model="newDesignationTitle" placeholder="New Designation (e.g. Assistant Manager)" required
                       class="w-full sm:w-auto rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold outline-none focus:border-amber-400">
                <select wire:model="newDesignationBaseRole" class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-2 text-xs font-bold outline-none focus:border-amber-400">
                    <option value="student">Student / Trainee Tier</option>
                    <option value="manager">Manager / Officer Tier</option>
                    @if($previewRole === 'partner')
                        <option value="partner">Partner / Director Tier</option>
                    @endif
                </select>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-[#17191F] px-3.5 py-2 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">
                    <i data-lucide="plus" class="h-3.5 w-3.5"></i> Add Designation
                </button>
            </form>
        </div>
        <div class="mt-3.5 flex flex-wrap gap-1.5">
            @foreach(($customDesignations ?? []) as $desigItem)
                <span wire:key="desig-chip-{{ $loop->index }}" class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ ($desigItem['base_role'] ?? '') === 'partner' ? 'border-amber-300 bg-amber-50 text-amber-950' : (($desigItem['base_role'] ?? '') === 'manager' ? 'border-indigo-200 bg-indigo-50 text-indigo-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800') }}">
                    <span>{{ $desigItem['title'] }}</span>
                    <span class="rounded bg-black/10 px-1 py-0.2 text-[9px] uppercase">{{ $desigItem['base_role'] ?? 'staff' }}</span>
                    @if(count($customDesignations ?? []) > 3)
                        <button type="button" wire:click="removeCustomDesignation('{{ addslashes($desigItem['title']) }}')" class="ml-0.5 text-slate-400 hover:text-rose-600" title="Remove designation">✕</button>
                    @endif
                </span>
            @endforeach
        </div>
    </section>
@endif

<!-- Filter Tabs & Table Container -->
<section class="rounded-2xl border border-[#E8EAF0] bg-white shadow-sm overflow-hidden">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4 sm:px-6">
        <div class="flex flex-wrap gap-1">
            <button type="button" wire:click="$set('teamFilter', 'all')"
                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $teamFilter === 'all' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                All Members ({{ $totalCount }})
            </button>
            <button type="button" wire:click="$set('teamFilter', 'partner')"
                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $teamFilter === 'partner' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Partners ({{ $partnerCount }})
            </button>
            <button type="button" wire:click="$set('teamFilter', 'manager')"
                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $teamFilter === 'manager' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Managers ({{ $managerCount }})
            </button>
            <button type="button" wire:click="$set('teamFilter', 'student')"
                    class="rounded-xl px-3 py-1.5 text-xs font-bold transition {{ $teamFilter === 'student' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                Students ({{ $studentCount }})
            </button>
        </div>

        <div class="relative w-full sm:w-64">
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search by name, email, ID..."
                   class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2 pl-9 pr-3 text-xs outline-none focus:border-amber-400">
            <i data-lucide="search" class="absolute left-3 top-2.5 h-4 w-4 text-slate-400"></i>
        </div>
    </div>

    <div class="hidden overflow-x-auto md:block">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="py-3.5 pl-5 pr-3">Member Details</th>
                    <th class="px-2.5 py-3.5">Post, Unit &amp; Supervisor</th>
                    <th class="px-2.5 py-3.5">Delegated Permissions</th>
                    <th class="px-2.5 py-3.5">Allowance</th>
                    @if($showActionColumn)
                        <th class="py-3.5 pl-2 pr-5 text-right">Account Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($teamMembers as $member)
                    @php
                        $canEditThisMember = ($canEditMembers ?? $canManageTeam) && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner');
                    @endphp
                    <tr wire:key="member-{{ $member['id'] }}" class="hover:bg-slate-50/70 transition">
                        <td class="py-4 pl-5 pr-3">
                            <div class="flex items-start gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-extrabold text-xs overflow-hidden {{ $member['role'] === 'partner' ? 'bg-[#FCDF02] text-[#17191F]' : ($member['role'] === 'manager' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700') }}">
                                    @if(!empty($member['avatar_url']))
                                        <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="h-full w-full object-cover">
                                    @else
                                        {{ strtoupper(substr($member['name'], 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="truncate font-bold text-slate-900">{{ $member['name'] }}</p>
                                        @if(Auth::check() && Auth::id() === $member['id'])
                                            <span class="rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold text-amber-900">You</span>
                                        @endif
                                        @if($canEditThisMember)
                                            <button type="button" wire:click="openEditMemberModal({{ $member['id'] }})"
                                                    class="inline-flex items-center gap-1 rounded-md border border-amber-300 bg-amber-50 px-1.5 py-0.5 text-[9px] font-extrabold text-amber-950 hover:bg-[#FCDF02] transition cursor-pointer"
                                                    title="Edit {{ $member['name'] }}'s account details">
                                                <i data-lucide="pencil" class="h-2.5 w-2.5"></i> Edit
                                            </button>
                                        @endif
                                    </div>
                                    <p class="truncate text-[11px] text-slate-500">{{ $member['email'] }}</p>
                                    @if(!empty($member['student_code']))
                                        <p class="text-[10px] font-mono text-emerald-700 mt-0.5">
                                            Code: {{ $member['student_code'] }}
                                            @if(!empty($member['articleship_period']) && $member['articleship_period'] !== '—')
                                                · <span class="text-slate-600 font-sans font-semibold">ICAB Term: {{ $member['articleship_period'] }}</span>
                                            @endif
                                        </p>
                                    @endif
                                    @if(!empty($member['payment_account_no']) && $member['payment_account_no'] !== '—')
                                        <p class="text-[10px] text-amber-800 font-semibold mt-0.5">{{ $member['payment_account_mode'] ?? 'bKash' }}: {{ $member['payment_account_no'] }}</p>
                                    @endif
                                    @if(in_array($member['role'] ?? '', ['student', 'manager'], true))
                                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[10px]">
                                            <span class="inline-flex items-center gap-1 rounded-md border border-teal-200 bg-teal-50 px-1.5 py-0.5 font-extrabold text-teal-900">
                                                Available Leave: {{ $member['leave_available_days'] ?? ($member['role'] === 'student' ? 180 : 44) }}d / {{ $member['total_leave_cap'] ?? ($member['role'] === 'student' ? 180 : 44) }}d
                                            </span>
                                            @if(($member['role'] ?? '') === 'student')
                                                <span class="rounded bg-indigo-50 px-1.5 py-0.5 font-bold text-indigo-800">
                                                    {{ $member['articleship_duration_years'] ?? 3 }}-Yr Max ({{ $member['per_year_leave_cap'] ?? 60 }}d/yr) · Study: {{ $member['leave_allocations']['icab_study'] ?? 75 }}d
                                                </span>
                                                @if(is_array($member['active_articleship_window'] ?? null) && !empty($member['active_articleship_window']['label']))
                                                    <span class="rounded border border-emerald-200 bg-emerald-50 px-1.5 py-0.5 font-semibold text-emerald-800" title="Joining-Date Anniversary Year Window: {{ $member['active_articleship_window']['period_label'] ?? '' }}">
                                                        {{ $member['active_articleship_window']['label'] }}: {{ $member['active_articleship_window']['period_label'] ?? '' }}
                                                    </span>
                                                @endif
                                            @else
                                                <span class="rounded bg-slate-100 px-1.5 py-0.5 font-semibold text-slate-600">
                                                    Jan–Dec Yearly Reset{{ ($member['carry_forward_earned'] ?? 0) > 0 ? ' · +' . $member['carry_forward_earned'] . 'd Earned Carry-Fwd' : ' · Auto Earned Carry-Fwd' }}
                                                </span>
                                            @endif
                                            @if(($member['leave_used_days'] ?? 0) > 0 || ($member['leave_pending_days'] ?? 0) > 0)
                                                <span class="rounded bg-amber-50 px-1.5 py-0.5 font-bold text-amber-900">
                                                    Used: {{ $member['leave_used_days'] ?? 0 }}d{{ ($member['leave_pending_days'] ?? 0) > 0 ? ' · Pending: ' . $member['leave_pending_days'] . 'd' : '' }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-2.5 py-4 align-top">
                            <div class="flex flex-col items-start gap-1">
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $member['role'] === 'partner' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($member['role'] === 'manager' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200') }}">
                                    {{ $member['designation'] ?? $member['role_label'] }}
                                </span>
                                <span class="text-[9px] font-semibold uppercase tracking-wider text-slate-400">
                                    Tier: {{ ucfirst($member['role']) }}
                                    @if(!empty($member['promotion_history']) && count($member['promotion_history']) > 0)
                                        · <span class="text-emerald-700 font-bold">{{ $member['promotion_history'][0]['action_label'] ?? 'Updated' }} ({{ $member['promotion_history'][0]['date'] ?? '' }})</span>
                                    @endif
                                </span>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1 text-[10px] text-slate-600">
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-700">{{ $member['partner_unit'] ?? 'Partner Unit 01' }}</span>
                                    @if(!empty($member['manager_name']) && $member['manager_name'] !== '—')
                                        <span class="text-slate-500">· Sup: <strong class="text-slate-700">{{ $member['manager_name'] }}</strong></span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-2.5 py-4 align-top">
                            <div class="flex flex-wrap gap-1 max-w-[180px]">
                                @if(!empty($member['can_manage_team']))
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-900 border border-amber-300" title="Can add and remove team members">Team Admin</span>
                                @endif
                                @if(!empty($member['can_proxy_attendance']))
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-700" title="Can give proxy attendance">Proxy</span>
                                @endif
                                @if(!empty($member['can_edit_attendance']))
                                    <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold text-slate-700" title="Can edit late attendance">Edit Att.</span>
                                @endif
                                @if(!empty($member['can_approve_leave']))
                                    <span class="rounded bg-blue-50 px-1.5 py-0.5 text-[9px] font-semibold text-blue-700" title="Can review and recommend leave">Leave Rev.</span>
                                @endif
                                @if(!empty($member['can_approve_claims']))
                                    <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-700" title="Can review conveyance claims">Claims Rev.</span>
                                @endif
                                @if(!empty($member['can_manage_policies']))
                                    <span class="rounded bg-teal-100 px-1.5 py-0.5 text-[9px] font-bold text-teal-900 border border-teal-300" title="Can update workspace policy, holidays & exceptions">Policy Admin</span>
                                @endif
                                @if(!empty($member['can_manage_payroll']))
                                    <span class="rounded bg-indigo-100 px-1.5 py-0.5 text-[9px] font-bold text-indigo-900 border border-indigo-300" title="Can approve, mark paid & lock payroll">Payroll Admin</span>
                                @endif
                                @if(!empty($member['can_final_approve_leave']))
                                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[9px] font-bold text-emerald-900" title="Can grant final leave sanction">Final Leave</span>
                                @endif
                                @if(!empty($member['can_final_approve_claims']))
                                    <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-950" title="Can grant final conveyance sanction">Final Claims</span>
                                @endif
                                @if(empty($member['can_manage_team']) && empty($member['can_proxy_attendance']) && empty($member['can_edit_attendance']) && empty($member['can_approve_leave']) && empty($member['can_approve_claims']) && empty($member['can_manage_policies']) && empty($member['can_manage_payroll']) && empty($member['can_final_approve_leave']) && empty($member['can_final_approve_claims']))
                                    <span class="text-[10px] text-slate-400">Standard</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-2.5 py-4 font-semibold text-slate-800 whitespace-nowrap align-top">
                            @if(!empty($member['monthly_allowance']) && $member['monthly_allowance'] > 0)
                                ৳ {{ number_format($member['monthly_allowance'], 2) }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        @if($showActionColumn)
                            <td class="py-4 pl-2 pr-5 text-right align-top">
                                <div class="flex flex-wrap items-center justify-end gap-1.5 min-w-[200px] max-w-[280px] ml-auto">
                                    @if($canEditThisMember)
                                        <button type="button" wire:click="openEditMemberModal({{ $member['id'] }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-amber-400 bg-[#FCDF02] px-2.5 py-1.5 text-[11px] font-extrabold text-[#17191F] hover:bg-[#F3D604] transition shadow-2xs cursor-pointer"
                                                title="Edit member account details, post/designation, allowance, leave quotas, bKash/Bank &amp; permissions">
                                            <i data-lucide="user-pen" class="h-3.5 w-3.5"></i> Edit Account
                                        </button>
                                    @endif
                                    @if($canManageTeam && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner'))
                                        <button type="button" wire:click="openPromoteDemoteModal({{ $member['id'] }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-[11px] font-bold text-indigo-800 hover:bg-indigo-100 transition cursor-pointer" title="Promote, demote, or change designation &amp; salary">
                                            <i data-lucide="trending-up" class="h-3.5 w-3.5"></i> Promote / Demote
                                        </button>
                                    @endif
                                    @if(($canResetPasswords ?? false) && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner'))
                                        <button type="button" wire:click="openResetPasswordModal({{ $member['id'] }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-950 hover:bg-amber-100 transition cursor-pointer" title="Reset password and email new password to {{ $member['email'] }}">
                                            <i data-lucide="key-round" class="h-3.5 w-3.5"></i> Reset Pass
                                        </button>
                                    @endif
                                    @if(($member['role'] ?? '') === 'student' && $canManageTeam)
                                        <button type="button" wire:click="exportStudentCertificate({{ $member['id'] }}, 'noc')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition cursor-pointer" title="Print Official ICAB Articleship &amp; NOC Certificate">
                                            <i data-lucide="award" class="h-3.5 w-3.5"></i> ICAB Cert
                                        </button>
                                    @endif
                                    @if($canManageTeam && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner') && (!Auth::check() || Auth::id() !== $member['id']))
                                        <button type="button" wire:click="confirmDeleteMember({{ $member['id'] }})"
                                                class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition cursor-pointer" title="Remove member from practice">
                                            <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-12 text-center text-slate-400">
                            <i data-lucide="users" class="mx-auto h-8 w-8 text-slate-300 mb-2"></i>
                            <p class="font-bold">No members found</p>
                            <p class="text-[11px] mt-0.5">Try adjusting your search query or filter.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Mobile Touch-Friendly Member Cards (< 768px / Redmi Note 9 Pro Max) -->
    <div class="divide-y divide-slate-100 md:hidden">
        @forelse($teamMembers as $member)
            <div wire:key="mobile-member-{{ $member['id'] }}" class="p-4 space-y-3">
                <div class="flex items-start justify-between gap-2.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-extrabold text-xs overflow-hidden {{ $member['role'] === 'partner' ? 'bg-[#FCDF02] text-[#17191F]' : ($member['role'] === 'manager' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700') }}">
                            @if(!empty($member['avatar_url']))
                                <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="h-full w-full object-cover">
                            @else
                                {{ strtoupper(substr($member['name'], 0, 1)) }}
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <p class="truncate text-sm font-extrabold text-slate-900">{{ $member['name'] }}</p>
                                @if(Auth::check() && Auth::id() === $member['id'])
                                    <span class="rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold text-amber-900">You</span>
                                @endif
                            </div>
                            <p class="truncate text-[11px] text-slate-500">{{ $member['email'] }}</p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $member['role'] === 'partner' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($member['role'] === 'manager' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200') }}">
                        {{ $member['designation'] ?? $member['role_label'] }}
                    </span>
                </div>

                <!-- Member Meta Grid -->
                <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-50 p-2.5 text-[11px]">
                    <div>
                        <span class="block text-[9px] font-bold uppercase text-slate-400">Partner Unit</span>
                        <strong class="text-slate-800">{{ $member['partner_unit'] ?? 'Partner Unit 01' }}</strong>
                    </div>
                    <div>
                        <span class="block text-[9px] font-bold uppercase text-slate-400">Allowance / Stipend</span>
                        <strong class="text-slate-900">
                            @if(!empty($member['monthly_allowance']) && $member['monthly_allowance'] > 0)
                                ৳ {{ number_format($member['monthly_allowance'], 0) }}
                            @else
                                —
                            @endif
                        </strong>
                    </div>
                    @if(($member['role'] ?? '') === 'student')
                        <div>
                            <span class="block text-[9px] font-bold uppercase text-slate-400">ICAB Code &amp; Supervisor</span>
                            <strong class="text-emerald-800">{{ $member['student_code'] ?: '—' }}</strong>
                            <span class="text-slate-500">· {{ $member['manager_name'] ?? 'Direct' }}</span>
                        </div>
                        <div>
                            <span class="block text-[9px] font-bold uppercase text-slate-400">Articleship Period</span>
                            <strong class="text-slate-700">{{ $member['articleship_period'] ?? '—' }}</strong>
                        </div>
                    @endif
                </div>

                <!-- Leave & Cycle Badges -->
                @if(in_array($member['role'] ?? '', ['student', 'manager'], true))
                    <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                        <span class="inline-flex items-center gap-1 rounded-md border border-teal-200 bg-teal-50 px-2 py-0.5 font-extrabold text-teal-900">
                            Leave: {{ $member['leave_available_days'] ?? ($member['role'] === 'student' ? 180 : 44) }}d / {{ $member['total_leave_cap'] ?? ($member['role'] === 'student' ? 180 : 44) }}d
                        </span>
                        @if(($member['role'] ?? '') === 'student')
                            <span class="rounded bg-indigo-50 px-2 py-0.5 font-bold text-indigo-800">
                                {{ $member['articleship_duration_years'] ?? 3 }}-Yr · Study: {{ $member['leave_allocations']['icab_study'] ?? 75 }}d
                            </span>
                            @if(is_array($member['active_articleship_window'] ?? null) && !empty($member['active_articleship_window']['label']))
                                <span class="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800">
                                    {{ $member['active_articleship_window']['label'] }} ({{ $member['active_articleship_window']['period_label'] ?? '' }})
                                </span>
                            @endif
                        @else
                            <span class="rounded bg-slate-100 px-2 py-0.5 font-semibold text-slate-600">
                                Jan–Dec Reset{{ ($member['carry_forward_earned'] ?? 0) > 0 ? ' · +' . $member['carry_forward_earned'] . 'd Carry-Fwd' : '' }}
                            </span>
                        @endif
                    </div>
                @endif

                <!-- Action Buttons Bar (Full-Width Mobile Responsive) -->
                @if($showActionColumn)
                    @php
                        $canEditThisMobileMember = ($canEditMembers ?? $canManageTeam) && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner');
                    @endphp
                    <div class="flex flex-wrap items-center gap-1.5 border-t border-slate-100 pt-2.5">
                        @if($canEditThisMobileMember)
                            <button type="button" wire:click="openEditMemberModal({{ $member['id'] }})"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-amber-400 bg-[#FCDF02] px-3 py-1.5 text-[11px] font-extrabold text-[#17191F] hover:bg-[#F3D604] transition shadow-2xs">
                                <i data-lucide="user-pen" class="h-3.5 w-3.5"></i> Edit Account
                            </button>
                        @endif
                        @if($canManageTeam && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner'))
                            <button type="button" wire:click="openPromoteDemoteModal({{ $member['id'] }})"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-[11px] font-bold text-indigo-800 hover:bg-indigo-100 transition">
                                <i data-lucide="trending-up" class="h-3.5 w-3.5"></i> Promote / Demote
                            </button>
                        @endif
                        @if(($canResetPasswords ?? false) && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner'))
                            <button type="button" wire:click="openResetPasswordModal({{ $member['id'] }})"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-950 hover:bg-amber-100 transition">
                                <i data-lucide="key-round" class="h-3.5 w-3.5"></i> Reset Pass
                            </button>
                        @endif
                        @if(($member['role'] ?? '') === 'student' && $canManageTeam)
                            <button type="button" wire:click="exportStudentCertificate({{ $member['id'] }}, 'noc')"
                                    class="inline-flex items-center justify-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition">
                                <i data-lucide="award" class="h-3.5 w-3.5"></i> ICAB Cert
                            </button>
                        @endif
                        @if($canManageTeam && ($previewRole === 'partner' || ($member['role'] ?? '') !== 'partner') && (!Auth::check() || Auth::id() !== $member['id']))
                            <button type="button" wire:click="confirmDeleteMember({{ $member['id'] }})"
                                    class="ml-auto inline-flex items-center justify-center rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition" title="Remove member">
                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="py-10 text-center text-slate-400">
                <p class="font-bold text-xs">No members found</p>
            </div>
        @endforelse
    </div>
</section>

<!-- ========================================== -->
<!-- ADD NEW MEMBER MODAL                       -->
<!-- ========================================== -->
@if($showAddMemberModal)
    <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-lg my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Practice Roster Provisioning</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Add Practice Team Member</h2>
                        <p class="mt-0.5 text-[11px] text-slate-500">Login link, username, and password will be sent directly to the member's email.</p>
                    </div>
                    <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>

                <form wire:submit.prevent="createMember" class="mt-4 space-y-4 text-xs">
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
                    @php
                        $groupedPosts = [
                            'Students & Interns'        => [],
                            'Office Staff & Executives' => [],
                            'Managers & Consultants'    => [],
                            'Partners & Principals'     => [],
                        ];
                        foreach (($customDesignations ?? \App\Models\Firm::defaultDesignations()) as $dOpt) {
                            $bRole = $dOpt['base_role'] ?? 'student';
                            if ($previewRole !== 'partner' && $bRole === 'partner') {
                                continue;
                            }
                            $tStr = (string) ($dOpt['title'] ?? '');
                            $grp = $dOpt['group'] ?? (
                                $bRole === 'partner' ? 'Partners & Principals' : (
                                    $bRole === 'student' ? 'Students & Interns' : (
                                        (stripos($tStr, 'Staff') !== false || stripos($tStr, 'Officer') !== false || stripos($tStr, 'Reception') !== false || stripos($tStr, 'Executive') !== false)
                                            ? 'Office Staff & Executives'
                                            : 'Managers & Consultants'
                                    )
                                )
                            );
                            $groupedPosts[$grp][] = $dOpt;
                        }
                    @endphp
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <label class="block font-bold text-slate-700 mb-1.5">Select Post / Designation *</label>
                            <select wire:change="selectNewMemberPresetPost($event.target.value)"
                                    class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs font-extrabold text-slate-900 outline-none focus:border-amber-400 focus:bg-white">
                                @foreach($groupedPosts as $grpLabel => $grpItems)
                                    @if(!empty($grpItems))
                                        <optgroup label="{{ $grpLabel }}">
                                            @foreach($grpItems as $gItem)
                                                <option value="{{ $gItem['title'] }}" @selected(!$newMemberCustomPostMode && strcasecmp($newMemberDesignation, $gItem['title']) === 0)>
                                                    {{ $gItem['title'] }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    @endif
                                @endforeach
                                <option value="__CUSTOM__" @selected($newMemberCustomPostMode)>+ Type Custom Post / Designation...</option>
                            </select>
                            <span class="mt-1 block text-[10px] text-slate-400">Includes Articled Student, Internship Student, Office Staff, Officers, Managers &amp; Partners</span>
                        </div>
                        <div>
                            <label class="block font-bold text-slate-700 mb-1.5">Post Title &amp; Access / Leave Tier *</label>
                            <div class="space-y-1.5">
                                <input type="text" wire:model="newMemberDesignation" list="firm-designations-datalist" required
                                       placeholder="e.g. Office Staff, Internship Student, Audit Manager..."
                                       class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2 text-xs font-bold text-slate-900 outline-none focus:border-amber-400 focus:bg-white">
                                <select wire:model.live="newMemberRole" class="w-full rounded-xl border border-slate-200 bg-slate-100/80 px-2.5 py-1.5 text-[11px] font-bold text-slate-700 outline-none focus:border-amber-400">
                                    <option value="student">Tier: Student / Intern (ICAB or Custom Period Leave)</option>
                                    <option value="manager">Tier: Manager / Office Staff (Jan–Dec Yearly Leave)</option>
                                    @if($previewRole === 'partner')
                                        <option value="partner">Tier: Partner / Principal (Executive Governance)</option>
                                    @endif
                                </select>
                            </div>
                            <datalist id="firm-designations-datalist">
                                @foreach(($customDesignations ?? []) as $dOpt)
                                    <option value="{{ $dOpt['title'] }}">{{ strtoupper($dOpt['base_role'] ?? '') }}</option>
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Full Name *
                            <input type="text" wire:model="newMemberName" required placeholder="e.g. Farhana Rahman, ACA" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Practice Email (Username) *
                            <input type="email" wire:model="newMemberEmail" required placeholder="{{ 'e.g. farhana@' . ($firm['custom_domain'] ?? ($firmSlug . '.dhakawala.com')) }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Initial Password (Emailed to User)
                            <input type="text" wire:model="newMemberPassword" placeholder="Leave blank to auto-generate" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400 font-mono text-[11px]">
                        </label>
                        <label class="block font-bold text-slate-700">Partner Unit
                            <select wire:model="newMemberPartnerUnit" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                                @foreach($availablePartnerUnits as $unit)
                                    <option value="{{ $unit }}">{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    @if($newMemberRole === 'student')
                        <div class="space-y-3 rounded-2xl border {{ $newMemberLeavePolicyMode === 'custom' ? 'border-amber-300 bg-amber-50/40' : 'border-emerald-200 bg-emerald-50/50' }} p-3.5">
                            <!-- Leave Policy Mode Selector: 1. As per ICAB vs 2. Custom -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b {{ $newMemberLeavePolicyMode === 'custom' ? 'border-amber-200/80' : 'border-emerald-200/80' }} pb-2.5">
                                <div>
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider {{ $newMemberLeavePolicyMode === 'custom' ? 'text-amber-950' : 'text-emerald-950' }}">
                                        Leave &amp; Holiday Policy Option
                                    </p>
                                    <p class="text-[10px] {{ $newMemberLeavePolicyMode === 'custom' ? 'text-amber-800' : 'text-emerald-800' }}">
                                        {{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Mode: Set your firm’s own custom leave quotas freely without the 180/240 day cap.' : 'ICAB Standard: 3-Year = 180 Days · 4-Year = 240 Days (Study Leave = Remaining Days).' }}
                                    </p>
                                </div>
                                <div class="inline-flex rounded-xl border border-slate-300 bg-white p-0.5 shadow-2xs">
                                    <button type="button" wire:click="selectNewMemberLeavePolicyMode('icab')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $newMemberLeavePolicyMode === 'icab' ? 'bg-[#17191F] text-[#FCDF02] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        1. As per ICAB
                                    </button>
                                    <button type="button" wire:click="selectNewMemberLeavePolicyMode('custom')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $newMemberLeavePolicyMode === 'custom' ? 'bg-[#FCDF02] text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        2. Custom
                                    </button>
                                </div>
                            </div>

                            <!-- Duration Selector -->
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-slate-700">
                                    {{ $newMemberLeavePolicyMode === 'custom' ? 'Articleship / Course Duration (Years):' : 'ICAB Articleship Period (180d / 240d):' }}
                                </span>
                                @if($newMemberLeavePolicyMode === 'icab')
                                    <div class="inline-flex rounded-xl border border-emerald-300 bg-white p-0.5">
                                        <button type="button" wire:click="selectNewMemberArticleshipYears(3)"
                                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $newMemberArticleshipYears === 3 ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                            3 Years (180 Days)
                                        </button>
                                        <button type="button" wire:click="selectNewMemberArticleshipYears(4)"
                                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $newMemberArticleshipYears === 4 ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                            4 Years (240 Days)
                                        </button>
                                    </div>
                                @else
                                    <div class="inline-flex rounded-xl border border-amber-300 bg-white p-0.5">
                                        @foreach([1, 2, 3, 4, 5] as $yrOpt)
                                            <button type="button" wire:click="selectNewMemberArticleshipYears({{ $yrOpt }})"
                                                    class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $newMemberArticleshipYears === $yrOpt ? 'bg-amber-500 text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                                {{ $yrOpt }} {{ $yrOpt === 1 ? 'Yr' : 'Yrs' }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label class="block font-bold text-slate-700">Student ICAB / ID Code
                                    <input type="text" wire:model="newMemberStudentCode" placeholder="e.g. S-105" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                                <label class="block font-bold text-slate-700">Supervising Manager
                                    <select wire:model="newMemberManagerId" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                        <option value="">— Direct Partner Report —</option>
                                        @foreach($availableManagers as $mgr)
                                            <option value="{{ $mgr['id'] }}">{{ $mgr['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block font-bold text-slate-700">Articleship Joining Date
                                    <input type="date" wire:model.live="newMemberArticleshipStart" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                                <label class="block font-bold text-slate-700">Articleship Completion ({{ $newMemberArticleshipYears }}-Yr)
                                    <input type="date" wire:model="newMemberArticleshipEnd" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                            </div>

                            <div class="rounded-xl border {{ $newMemberLeavePolicyMode === 'custom' ? 'border-amber-300' : 'border-emerald-200' }} bg-white p-3 space-y-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <span class="text-[11px] font-extrabold text-slate-800">
                                        {{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Leave Allocation (Freely Editable)' : 'ICAB Leave Allocation (Anniversary Cycle from Joining Date)' }}
                                    </span>
                                    <span class="rounded-full {{ $newMemberLeavePolicyMode === 'custom' ? 'bg-amber-100 text-amber-950' : 'bg-emerald-100 text-emerald-900' }} px-2.5 py-0.5 text-[10px] font-black">
                                        {{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Total:' : 'ICAB Cap:' }} {{ $newMemberTotalLeaveCap }} Days ({{ (int) round($newMemberTotalLeaveCap / max(1, (int) $newMemberArticleshipYears)) }}d/yr × {{ $newMemberArticleshipYears }} Yrs)
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <label class="block text-[10px] font-bold text-slate-600">Total Cap (Days)
                                        <input type="number" wire:model.live="newMemberTotalLeaveCap" min="0" max="600" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 p-1.5 text-xs font-extrabold text-slate-900">
                                        <span class="mt-0.5 block text-[9px] font-semibold text-slate-400">{{ (int) round($newMemberTotalLeaveCap / max(1, (int) $newMemberArticleshipYears)) }}d / year</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Casual / Yr (d)
                                        <input type="number" wire:model.live="newMemberLeaveCasual" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $newMemberArticleshipYears }} yrs = {{ (int) $newMemberLeaveCasual * (int) $newMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Sick / Yr (d)
                                        <input type="number" wire:model.live="newMemberLeaveSick" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $newMemberArticleshipYears }} yrs = {{ (int) $newMemberLeaveSick * (int) $newMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Emergency / Yr (d)
                                        <input type="number" wire:model.live="newMemberLeaveEmergency" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $newMemberArticleshipYears }} yrs = {{ (int) $newMemberLeaveEmergency * (int) $newMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-extrabold text-indigo-800">Study Pool ({{ $newMemberArticleshipYears }}-Yr)
                                        <input type="number" wire:model.live="newMemberLeaveStudy" min="0" max="400" class="mt-1 w-full rounded-lg border border-indigo-300 bg-indigo-50/70 p-1.5 text-xs font-black text-indigo-900">
                                        <span class="mt-0.5 block text-[9px] font-bold text-indigo-700">{{ (int) round($newMemberLeaveStudy / max(1, (int) $newMemberArticleshipYears)) }}d / year</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed">
                                    <strong>{{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Summary:' : ($newMemberArticleshipYears . '-Year ICAB Split:') }}</strong>
                                    Regular Leave = ({{ $newMemberLeaveCasual }} + {{ $newMemberLeaveSick }} + {{ $newMemberLeaveEmergency }}) × {{ $newMemberArticleshipYears }} yrs = <strong>{{ ((int)$newMemberLeaveCasual + (int)$newMemberLeaveSick + (int)$newMemberLeaveEmergency) * (int)$newMemberArticleshipYears }} Days</strong> + Study Leave Pool = <strong class="text-indigo-700">{{ $newMemberLeaveStudy }} Days</strong> &rarr; Total = <strong>{{ $newMemberTotalLeaveCap }} Days</strong>.
                                </p>
                            </div>
                        </div>
                    @elseif($newMemberRole === 'manager')
                        <div class="rounded-2xl border {{ $newMemberLeavePolicyMode === 'custom' ? 'border-amber-300 bg-amber-50/40' : 'border-indigo-200 bg-indigo-50/50' }} p-3.5 space-y-2.5">
                            <!-- Leave Policy Mode Selector: 1. As per ICAB vs 2. Custom -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b {{ $newMemberLeavePolicyMode === 'custom' ? 'border-amber-200/80' : 'border-indigo-200/80' }} pb-2.5">
                                <div>
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider {{ $newMemberLeavePolicyMode === 'custom' ? 'text-amber-950' : 'text-indigo-950' }}">
                                        Manager / Staff Leave Policy Option (Jan–Dec Yearly Reset)
                                    </p>
                                    <p class="text-[10px] {{ $newMemberLeavePolicyMode === 'custom' ? 'text-amber-800' : 'text-indigo-800' }}">
                                        {{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Mode: Set your firm’s own custom yearly leave days for this Manager/Staff.' : 'Standard Preset: 44 Days/Yr (Casual 10d, Sick 14d, Earned 15d, Emergency 5d + Carry-Forward).' }}
                                    </p>
                                </div>
                                <div class="inline-flex rounded-xl border border-slate-300 bg-white p-0.5 shadow-2xs">
                                    <button type="button" wire:click="selectNewMemberLeavePolicyMode('icab')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $newMemberLeavePolicyMode === 'icab' ? 'bg-[#17191F] text-[#FCDF02] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        1. As per ICAB
                                    </button>
                                    <button type="button" wire:click="selectNewMemberLeavePolicyMode('custom')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $newMemberLeavePolicyMode === 'custom' ? 'bg-[#FCDF02] text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        2. Custom
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-slate-600">Annual Quota (Jan 1 – Dec 31):</span>
                                <span class="rounded-full {{ $newMemberLeavePolicyMode === 'custom' ? 'bg-amber-100 text-amber-950' : 'bg-indigo-100 text-indigo-900' }} px-2.5 py-0.5 text-[10px] font-black">
                                    {{ $newMemberLeavePolicyMode === 'custom' ? 'Custom Total:' : 'Standard Total:' }} {{ $newMemberTotalLeaveCap }} Days / Year
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
                                <label class="block text-[10px] font-bold text-slate-700">Casual / Yr (d)
                                    <input type="number" wire:model.live="newMemberLeaveCasual" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Sick / Yr (d)
                                    <input type="number" wire:model.live="newMemberLeaveSick" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Earned Base / Yr
                                    <input type="number" wire:model.live="newMemberLeaveEarned" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Emergency / Yr
                                    <input type="number" wire:model.live="newMemberLeaveEmergency" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-extrabold text-emerald-800">Carry-Fwd Earned
                                    <input type="number" wire:model.live="newMemberLeaveCarryForward" min="0" max="180" class="mt-1 w-full rounded-lg border border-emerald-300 bg-emerald-50/70 p-2 text-xs font-black text-emerald-900">
                                </label>
                            </div>
                        </div>
                    @endif

                    <label class="block font-bold text-slate-700">Monthly Stipend / Allowance (BDT)
                        <input type="number" wire:model="newMemberMonthlyAllowance" step="100" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>

                    <!-- Delegated Permissions Box -->
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 space-y-2.5">
                        <p class="font-extrabold text-amber-950 uppercase tracking-wider text-[10px]">Delegated Authority &amp; Permissions</p>
                        
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="newMemberCanManageTeam" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-900">Can Add / Remove &amp; Manage Team Members</span>
                                <p class="text-[10px] text-slate-500">Allows this member to enroll or delete managers/students and delegate permissions.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="newMemberCanProxyAttendance" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Give Proxy Attendance</span>
                                <p class="text-[10px] text-slate-500">Allows check-in/out on behalf of assigned students.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="newMemberCanEditAttendance" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Edit Late Timing / In-Out Waivers</span>
                                <p class="text-[10px] text-slate-500">Allows adjusting student attendance timestamps and late waiver reasons.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="newMemberCanApproveLeave" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Review &amp; Recommend Leave Applications</span>
                                <p class="text-[10px] text-slate-500">Allows verifying student leave requests and forwarding to Partner.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="newMemberCanApproveClaims" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Review &amp; Adjust Conveyance Claims</span>
                                <p class="text-[10px] text-slate-500">Allows approving conveyance claims within manager limit.</p>
                            </div>
                        </label>

                        <div class="border-t border-amber-200/80 pt-2.5 mt-2 space-y-2.5">
                            <p class="font-extrabold text-teal-900 uppercase tracking-wider text-[10px]">⚡ Partner-Level Executive Delegation</p>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="newMemberCanManagePolicies" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Update Firm Policy, Office Hours &amp; Holidays</span>
                                    <p class="text-[10px] text-slate-500">Allows this manager to update office timing, grace rules, weekly holidays &amp; working-day exceptions on behalf of Partner.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="newMemberCanManagePayroll" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Approve, Mark Paid &amp; Lock Monthly Payroll</span>
                                    <p class="text-[10px] text-slate-500">Allows full Partner-level payroll approval and disbursement sign-off.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="newMemberCanFinalApproveLeave" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Grant Final Leave Sanction (Without Escalation)</span>
                                    <p class="text-[10px] text-slate-500">Allows directly approving student leave without waiting for Partner sign-off.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="newMemberCanFinalApproveClaims" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Grant Final Conveyance Sanction (Any Amount / Late)</span>
                                    <p class="text-[10px] text-slate-500">Allows approving conveyance claims above limit or older claims directly.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="createMember" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition disabled:opacity-60">
                            <i data-lucide="check" class="h-4 w-4"></i>
                            <span wire:loading.remove wire:target="createMember">Create Member</span>
                            <span wire:loading wire:target="createMember" style="display:none">Creating...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- ========================================== -->
<!-- RESET PASSWORD MODAL (PARTNER / MANAGER)   -->
<!-- ========================================== -->
@if($showResetPasswordModal ?? false)
    <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-md my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-900">
                            <i data-lucide="key-round" class="h-5 w-5"></i>
                        </div>
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Account Security</p>
                            <h2 class="text-base font-black text-slate-900">Reset Member Password</h2>
                        </div>
                    </div>
                    <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>

                <form wire:submit.prevent="resetMemberPassword" class="mt-4 space-y-4 text-xs">
                    @if($error)
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800 flex items-start gap-2" role="alert">
                            <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0 mt-0.5"></i>
                            <p>{{ $error }}</p>
                        </div>
                    @endif

                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-3.5 space-y-1">
                        <p class="font-bold text-slate-900">{{ $resetPasswordMemberName }}</p>
                        <p class="text-[11px] text-slate-600">Username / Email: <strong>{{ $resetPasswordMemberEmail }}</strong></p>
                        <p class="text-[10px] text-slate-500">Role: {{ $resetPasswordMemberRole }}</p>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1">
                            New Password (Enter custom password or leave blank to auto-generate)
                        </label>
                        <input type="text"
                               wire:model="resetPasswordInput"
                               placeholder="Type new password (min 6 chars) or leave blank"
                               class="w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-mono text-xs outline-none focus:border-amber-400 focus:bg-white">
                        <p class="mt-1.5 text-[11px] text-slate-500 leading-relaxed">
                            The direct workspace login link, username, and new password will be sent immediately to <strong>{{ $resetPasswordMemberEmail }}</strong>.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="resetMemberPassword" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition shadow-sm">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            <span wire:loading.remove wire:target="resetMemberPassword">Reset &amp; Send Password to Email</span>
                            <span wire:loading wire:target="resetMemberPassword" style="display:none">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- ========================================== -->
<!-- EDIT MEMBER & PERMISSIONS MODAL            -->
<!-- ========================================== -->
@if($showEditMemberModal)
    <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-lg my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Account, Profile, Leave &amp; Authority Management</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Edit Member Account &amp; Profile</h2>
                        <p class="text-xs text-slate-500 font-semibold mt-0.5">{{ $editMemberName }} ({{ $editMemberEmail }})</p>
                    </div>
                    <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>

                <form wire:submit.prevent="updateMember" class="mt-4 space-y-4 text-xs">
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
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="block font-bold text-slate-700">Full Name
                            <input type="text" wire:model="editMemberName" required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Email Address
                            <input type="email" wire:model="editMemberEmail" required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Phone Number
                            <input type="text" wire:model="editMemberPhone" placeholder="+880 1711-XXXXXX" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Reset Password (Emailed)
                            <input type="text" wire:model="editMemberNewPassword" placeholder="Type new pass to reset" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2 outline-none focus:border-amber-400 font-mono text-[11px]">
                        </label>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="block font-bold text-slate-700">Select Post / Designation
                            <select wire:change="selectEditMemberPresetPost($event.target.value)" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-bold outline-none focus:border-amber-400">
                                @foreach(($customDesignations ?? \App\Models\Firm::defaultDesignations()) as $dOpt)
                                    <option value="{{ $dOpt['title'] }}" @selected(strcasecmp($editMemberDesignation, $dOpt['title']) === 0)>{{ $dOpt['title'] }}</option>
                                @endforeach
                                <option value="__CUSTOM__">+ Custom Post Title...</option>
                            </select>
                        </label>
                        <label class="block font-bold text-slate-700">Post Title &amp; Access Tier
                            <input type="text" wire:model="editMemberDesignation" list="firm-designations-datalist" placeholder="e.g. Office Staff / Internship Student"
                                   class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2 font-bold outline-none focus:border-amber-400">
                            <select wire:model.live="editMemberRole" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-100 px-2 py-1 text-[10px] font-bold text-slate-700 outline-none">
                                <option value="student">Tier: Student / Intern</option>
                                <option value="manager">Tier: Manager / Office Staff</option>
                                @if($previewRole === 'partner')
                                    <option value="partner">Tier: Partner / Principal</option>
                                @endif
                            </select>
                        </label>
                        <label class="block font-bold text-slate-700">Partner Unit
                            <select wire:model="editMemberPartnerUnit" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                                @foreach($availablePartnerUnits as $unit)
                                    <option value="{{ $unit }}">{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    @if($editMemberRole === 'student')
                        <div class="space-y-3 rounded-2xl border {{ $editMemberLeavePolicyMode === 'custom' ? 'border-amber-300 bg-amber-50/40' : 'border-emerald-200 bg-emerald-50/50' }} p-3.5">
                            <!-- Leave Policy Mode Selector: 1. As per ICAB vs 2. Custom -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b {{ $editMemberLeavePolicyMode === 'custom' ? 'border-amber-200/80' : 'border-emerald-200/80' }} pb-2.5">
                                <div>
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider {{ $editMemberLeavePolicyMode === 'custom' ? 'text-amber-950' : 'text-emerald-950' }}">
                                        Leave &amp; Holiday Policy Option
                                    </p>
                                    <p class="text-[10px] {{ $editMemberLeavePolicyMode === 'custom' ? 'text-amber-800' : 'text-emerald-800' }}">
                                        {{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Mode: Set your firm’s own custom leave quotas freely without the 180/240 day cap.' : 'ICAB Standard: 3-Year = 180 Days · 4-Year = 240 Days (Study Leave = Remaining Days).' }}
                                    </p>
                                </div>
                                <div class="inline-flex rounded-xl border border-slate-300 bg-white p-0.5 shadow-2xs">
                                    <button type="button" wire:click="selectEditMemberLeavePolicyMode('icab')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $editMemberLeavePolicyMode === 'icab' ? 'bg-[#17191F] text-[#FCDF02] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        1. As per ICAB
                                    </button>
                                    <button type="button" wire:click="selectEditMemberLeavePolicyMode('custom')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $editMemberLeavePolicyMode === 'custom' ? 'bg-[#FCDF02] text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        2. Custom
                                    </button>
                                </div>
                            </div>

                            <!-- Duration Selector -->
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[11px] font-bold text-slate-700">
                                    {{ $editMemberLeavePolicyMode === 'custom' ? 'Articleship / Course Duration (Years):' : 'ICAB Articleship Period (180d / 240d):' }}
                                </span>
                                @if($editMemberLeavePolicyMode === 'icab')
                                    <div class="inline-flex rounded-xl border border-emerald-300 bg-white p-0.5">
                                        <button type="button" wire:click="selectEditMemberArticleshipYears(3)"
                                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $editMemberArticleshipYears === 3 ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                            3 Years (180 Days)
                                        </button>
                                        <button type="button" wire:click="selectEditMemberArticleshipYears(4)"
                                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $editMemberArticleshipYears === 4 ? 'bg-emerald-600 text-white shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                            4 Years (240 Days)
                                        </button>
                                    </div>
                                @else
                                    <div class="inline-flex rounded-xl border border-amber-300 bg-white p-0.5">
                                        @foreach([1, 2, 3, 4, 5] as $yrOpt)
                                            <button type="button" wire:click="selectEditMemberArticleshipYears({{ $yrOpt }})"
                                                    class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition cursor-pointer {{ (int) $editMemberArticleshipYears === $yrOpt ? 'bg-amber-500 text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                                {{ $yrOpt }} {{ $yrOpt === 1 ? 'Yr' : 'Yrs' }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                <label class="block font-bold text-slate-700">Student ICAB / ID Code
                                    <input type="text" wire:model="editMemberStudentCode" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                                <label class="block font-bold text-slate-700">Supervising Manager
                                    <select wire:model="editMemberManagerId" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                        <option value="">— Direct Partner Report —</option>
                                        @foreach($availableManagers as $mgr)
                                            <option value="{{ $mgr['id'] }}">{{ $mgr['name'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block font-bold text-slate-700">Articleship Joining Date
                                    <input type="date" wire:model="editMemberArticleshipStart" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                                <label class="block font-bold text-slate-700">Articleship Completion Date ({{ $editMemberArticleshipYears }}-Yr)
                                    <input type="date" wire:model="editMemberArticleshipEnd" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                                </label>
                            </div>

                            <div class="rounded-xl border {{ $editMemberLeavePolicyMode === 'custom' ? 'border-amber-300' : 'border-emerald-200' }} bg-white p-3 space-y-2.5">
                                <div class="flex flex-wrap items-center justify-between gap-1.5">
                                    <span class="text-[11px] font-extrabold text-slate-800">
                                        {{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Leave Allocation (Freely Editable)' : 'Year-Wise Leave Entitlements (Anniversary Cycle from Joining Date)' }}
                                    </span>
                                    <span class="rounded-full {{ $editMemberLeavePolicyMode === 'custom' ? 'bg-amber-100 text-amber-950' : 'bg-emerald-100 text-emerald-900' }} px-2.5 py-0.5 text-[10px] font-black">
                                        {{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Total:' : 'ICAB Cap:' }} {{ $editMemberTotalLeaveCap }} Days ({{ (int) round($editMemberTotalLeaveCap / max(1, (int) $editMemberArticleshipYears)) }}d/yr × {{ $editMemberArticleshipYears }} Yrs)
                                    </span>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    <label class="block text-[10px] font-bold text-slate-600">Total Cap (Days)
                                        <input type="number" wire:model.live="editMemberTotalLeaveCap" min="0" max="600" class="mt-1 w-full rounded-lg border border-slate-200 bg-slate-50 p-1.5 text-xs font-extrabold text-slate-900">
                                        <span class="mt-0.5 block text-[9px] font-semibold text-slate-400">{{ (int) round($editMemberTotalLeaveCap / max(1, (int) $editMemberArticleshipYears)) }}d / year</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Casual / Yr (d)
                                        <input type="number" wire:model.live="editMemberLeaveCasual" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $editMemberArticleshipYears }} yrs = {{ (int) $editMemberLeaveCasual * (int) $editMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Sick / Yr (d)
                                        <input type="number" wire:model.live="editMemberLeaveSick" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $editMemberArticleshipYears }} yrs = {{ (int) $editMemberLeaveSick * (int) $editMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-bold text-slate-600">Emergency / Yr (d)
                                        <input type="number" wire:model.live="editMemberLeaveEmergency" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-1.5 text-xs font-bold">
                                        <span class="mt-0.5 block text-[9px] font-bold text-emerald-700">× {{ $editMemberArticleshipYears }} yrs = {{ (int) $editMemberLeaveEmergency * (int) $editMemberArticleshipYears }}d</span>
                                    </label>
                                    <label class="block text-[10px] font-extrabold text-indigo-800">Study Pool ({{ $editMemberArticleshipYears }}-Yr)
                                        <input type="number" wire:model.live="editMemberLeaveStudy" min="0" max="400" class="mt-1 w-full rounded-lg border border-indigo-300 bg-indigo-50/70 p-1.5 text-xs font-black text-indigo-900">
                                        <span class="mt-0.5 block text-[9px] font-bold text-indigo-700">{{ (int) round($editMemberLeaveStudy / max(1, (int) $editMemberArticleshipYears)) }}d / year</span>
                                    </label>
                                </div>
                                <p class="text-[10px] text-slate-500 leading-relaxed">
                                    <strong>{{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Summary:' : ($editMemberArticleshipYears . '-Year ICAB Split:') }}</strong>
                                    Regular Leave = ({{ $editMemberLeaveCasual }} + {{ $editMemberLeaveSick }} + {{ $editMemberLeaveEmergency }}) × {{ $editMemberArticleshipYears }} yrs = <strong>{{ ((int)$editMemberLeaveCasual + (int)$editMemberLeaveSick + (int)$editMemberLeaveEmergency) * (int)$editMemberArticleshipYears }} Days</strong> + Study Leave Pool = <strong class="text-indigo-700">{{ $editMemberLeaveStudy }} Days</strong> &rarr; Total = <strong>{{ $editMemberTotalLeaveCap }} Days</strong>.
                                </p>
                            </div>
                        </div>
                    @elseif($editMemberRole === 'manager')
                        <div class="rounded-2xl border {{ $editMemberLeavePolicyMode === 'custom' ? 'border-amber-300 bg-amber-50/40' : 'border-indigo-200 bg-indigo-50/50' }} p-3.5 space-y-2.5">
                            <!-- Leave Policy Mode Selector: 1. As per ICAB vs 2. Custom -->
                            <div class="flex flex-wrap items-center justify-between gap-2 border-b {{ $editMemberLeavePolicyMode === 'custom' ? 'border-amber-200/80' : 'border-indigo-200/80' }} pb-2.5">
                                <div>
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider {{ $editMemberLeavePolicyMode === 'custom' ? 'text-amber-950' : 'text-indigo-950' }}">
                                        Manager / Staff Leave Policy Option (Jan–Dec Yearly Reset)
                                    </p>
                                    <p class="text-[10px] {{ $editMemberLeavePolicyMode === 'custom' ? 'text-amber-800' : 'text-indigo-800' }}">
                                        {{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Mode: Set your firm’s own custom yearly leave days for this Manager/Staff.' : 'Standard Preset: 44 Days/Yr (Casual 10d, Sick 14d, Earned 15d, Emergency 5d + Carry-Forward).' }}
                                    </p>
                                </div>
                                <div class="inline-flex rounded-xl border border-slate-300 bg-white p-0.5 shadow-2xs">
                                    <button type="button" wire:click="selectEditMemberLeavePolicyMode('icab')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $editMemberLeavePolicyMode === 'icab' ? 'bg-[#17191F] text-[#FCDF02] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        1. As per ICAB
                                    </button>
                                    <button type="button" wire:click="selectEditMemberLeavePolicyMode('custom')"
                                            class="rounded-lg px-3 py-1.5 text-[11px] font-extrabold transition cursor-pointer {{ $editMemberLeavePolicyMode === 'custom' ? 'bg-[#FCDF02] text-[#17191F] shadow-2xs' : 'text-slate-600 hover:bg-slate-50' }}">
                                        2. Custom
                                    </button>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] font-bold text-slate-600">Annual Quota (Jan 1 – Dec 31):</span>
                                <span class="rounded-full {{ $editMemberLeavePolicyMode === 'custom' ? 'bg-amber-100 text-amber-950' : 'bg-indigo-100 text-indigo-900' }} px-2.5 py-0.5 text-[10px] font-black">
                                    {{ $editMemberLeavePolicyMode === 'custom' ? 'Custom Total:' : 'Standard Total:' }} {{ $editMemberTotalLeaveCap }} Days / Year
                                </span>
                            </div>
                            <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-5">
                                <label class="block text-[10px] font-bold text-slate-700">Casual / Yr (d)
                                    <input type="number" wire:model.live="editMemberLeaveCasual" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Sick / Yr (d)
                                    <input type="number" wire:model.live="editMemberLeaveSick" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Earned Base / Yr
                                    <input type="number" wire:model.live="editMemberLeaveEarned" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-bold text-slate-700">Emergency / Yr
                                    <input type="number" wire:model.live="editMemberLeaveEmergency" min="0" max="120" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs font-bold">
                                </label>
                                <label class="block text-[10px] font-extrabold text-emerald-800">Carry-Fwd Earned
                                    <input type="number" wire:model.live="editMemberLeaveCarryForward" min="0" max="180" class="mt-1 w-full rounded-lg border border-emerald-300 bg-emerald-50/70 p-2 text-xs font-black text-emerald-900">
                                </label>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <label class="block font-bold text-slate-700">Monthly Allowance / Salary (BDT)
                            <input type="number" wire:model="editMemberMonthlyAllowance" step="100" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Disbursement Mode
                            <select wire:model="editMemberPaymentMode" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-bold outline-none focus:border-amber-400">
                                <option value="bKash">bKash</option>
                                <option value="Nagad">Nagad</option>
                                <option value="Rocket">Rocket</option>
                                <option value="Bank A/C">Bank A/C</option>
                                <option value="Cash">Cash</option>
                            </select>
                        </label>
                        <label class="block font-bold text-slate-700">Wallet / Bank Account No.
                            <input type="text" wire:model="editMemberPaymentAccountNo" placeholder="017XXXXXXXX / Bank A/C" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <!-- Delegated Permissions Box -->
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 space-y-2.5">
                        <p class="font-extrabold text-amber-950 uppercase tracking-wider text-[10px]">Delegated Authority &amp; Access Controls</p>
                        
                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberCanManageTeam" class="mt-0.5 rounded border-amber-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-900">Can Add / Remove &amp; Manage Team Members</span>
                                <p class="text-[10px] text-slate-500">Allows this manager/student to enroll or delete team members and manage permissions.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberCanProxyAttendance" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Give Proxy Attendance</span>
                                <p class="text-[10px] text-slate-500">Allows recording check-in on behalf of students.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberCanEditAttendance" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Edit Late Timing / Waivers</span>
                                <p class="text-[10px] text-slate-500">Allows modifying attendance timestamps and recording audit remarks.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberCanApproveLeave" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Review &amp; Recommend Leave</span>
                                <p class="text-[10px] text-slate-500">Allows evaluating student leaves before partner final sign-off.</p>
                            </div>
                        </label>

                        <label class="flex items-start gap-2.5 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberCanApproveClaims" class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-400">
                            <div>
                                <span class="font-bold text-slate-800">Can Review &amp; Adjust Conveyance Claims</span>
                                <p class="text-[10px] text-slate-500">Allows verifying and modifying student travel claims within policy limits.</p>
                            </div>
                        </label>

                        <div class="border-t border-amber-200/80 pt-2.5 mt-2 space-y-2.5">
                            <p class="font-extrabold text-teal-900 uppercase tracking-wider text-[10px]">⚡ Partner-Level Executive Delegation</p>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="editMemberCanManagePolicies" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Update Firm Policy, Office Hours &amp; Holidays</span>
                                    <p class="text-[10px] text-slate-500">Allows this manager to update office timing, grace rules, weekly holidays &amp; working-day exceptions on behalf of Partner.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="editMemberCanManagePayroll" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Approve, Mark Paid &amp; Lock Monthly Payroll</span>
                                    <p class="text-[10px] text-slate-500">Allows full Partner-level payroll approval and disbursement sign-off.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="editMemberCanFinalApproveLeave" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Grant Final Leave Sanction (Without Escalation)</span>
                                    <p class="text-[10px] text-slate-500">Allows directly approving student leave without waiting for Partner sign-off.</p>
                                </div>
                            </label>
                            <label class="flex items-start gap-2.5 cursor-pointer">
                                <input type="checkbox" wire:model="editMemberCanFinalApproveClaims" class="mt-0.5 rounded border-teal-400 text-teal-600 focus:ring-teal-400">
                                <div>
                                    <span class="font-bold text-slate-900">Can Grant Final Conveyance Sanction (Any Amount / Late)</span>
                                    <p class="text-[10px] text-slate-500">Allows approving conveyance claims above limit or older claims directly.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="updateMember" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition disabled:opacity-60">
                            <i data-lucide="save" class="h-4 w-4"></i>
                            <span wire:loading.remove wire:target="updateMember">Save Account Changes</span>
                            <span wire:loading wire:target="updateMember" style="display:none">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif

<!-- ========================================== -->
<!-- DELETE CONFIRMATION POPUP MODAL            -->
<!-- ========================================== -->
@if($showDeleteMemberModal)
    <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-md my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-center gap-3 text-rose-600">
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100">
                        <i data-lucide="alert-triangle" class="h-6 w-6"></i>
                    </div>
                    <div>
                        <h2 class="text-base font-black text-slate-900">Remove Practice Member</h2>
                        <p class="text-xs text-rose-700 font-semibold">Security Confirmation Required</p>
                    </div>
                </div>

                <div class="mt-4 rounded-xl border border-rose-100 bg-rose-50/50 p-4 text-xs leading-relaxed text-slate-700">
                    Are you sure you want to permanently remove <strong class="text-rose-950">{{ $selectedMemberName }}</strong> from {{ $firm['name'] ?? 'the firm' }}?
                    <p class="mt-2 text-slate-500">This action will deactivate their login access and dissociate student assignments. Historical attendance and audit records will remain archived.</p>
                </div>

                    @if($error || $errors->any())
                        <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800 flex items-start gap-2" role="alert">
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
                <div class="mt-4">
                    <label class="block text-xs font-bold text-slate-700">
                        To confirm permanent deletion, please type <span class="font-mono text-rose-600 font-black">DELETE</span> below:
                        <input type="text" wire:model="deleteConfirmationInput" placeholder="Type DELETE to confirm"
                               class="mt-1.5 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-xs font-mono uppercase outline-none focus:border-rose-500 focus:bg-white">
                    </label>
                </div>

                <div class="mt-5 flex items-center justify-end gap-2">
                    <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                    <button type="button" wire:click="deleteMember" wire:loading.attr="disabled"
                            class="inline-flex items-center gap-2 rounded-xl bg-rose-600 px-4 py-2 text-xs font-extrabold text-white hover:bg-rose-700 transition shadow-sm">
                        <i data-lucide="trash-2" class="h-4 w-4"></i> Permanently Remove
                    </button>
                </div>
            </div>
        </div>
    </div>
@endif

<!-- ========================================================= -->
<!-- PROMOTE / DEMOTE & DESIGNATION CHANGE MODAL               -->
<!-- ========================================================= -->
@if($showPromoteDemoteModal ?? false)
    <div class="fixed inset-0 z-[75] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
         wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs" role="dialog" aria-modal="true">
        <div class="flex min-h-full items-center justify-center">
            <div class="w-full max-w-lg my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-indigo-700">CAREER PROGRESSION &amp; RANK GOVERNANCE</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Promote / Demote Practice Member</h2>
                        <p class="mt-0.5 text-xs font-semibold text-slate-500">{{ $promoteMemberName }} · Current: <span class="text-indigo-700 font-bold">{{ $promoteMemberCurrentDesignation }}</span> (৳{{ number_format($promoteMemberCurrentAllowance, 0) }})</p>
                    </div>
                    <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>

                <form wire:submit.prevent="submitPromotionOrDemotion" class="mt-4 space-y-4 text-xs">
                    @if($error)
                        <div class="rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-800 flex items-start gap-2" role="alert">
                            <i data-lucide="alert-circle" class="h-4 w-4 text-rose-600 shrink-0 mt-0.5"></i>
                            <p>{{ $error }}</p>
                        </div>
                    @endif

                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Action Type (Promote / Demote / Reassign Title)</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['promote' => '⬆️ Promote', 'demote' => '⬇️ Demote', 'transfer' => '🔄 Reassign Title'] as $actKey => $actLabel)
                                <button type="button" wire:click="$set('promoteActionType', '{{ $actKey }}')"
                                        class="rounded-xl border p-2.5 text-center font-extrabold transition {{ $promoteActionType === $actKey ? ($actKey === 'demote' ? 'border-rose-400 bg-rose-50 text-rose-900 ring-2 ring-rose-200' : 'border-emerald-400 bg-emerald-50 text-emerald-950 ring-2 ring-emerald-200') : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                                    {{ $actLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Quick Select Firm Designation</label>
                        <div class="flex flex-wrap gap-1.5 max-h-28 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-2.5">
                            @foreach(($customDesignations ?? []) as $dItem)
                                <button type="button" wire:click="selectPresetDesignationForPromotion('{{ addslashes($dItem['title']) }}', '{{ $dItem['base_role'] ?? 'student' }}')"
                                        class="rounded-lg border px-2.5 py-1 text-[11px] font-bold transition {{ $promoteNewDesignation === ($dItem['title'] ?? '') ? 'border-amber-400 bg-[#17191F] text-[#FCDF02]' : 'border-slate-200 bg-white text-slate-700 hover:bg-amber-50' }}">
                                    {{ $dItem['title'] }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">New Designation Title *
                            <input type="text" wire:model="promoteNewDesignation" required placeholder="e.g. Senior Audit Manager"
                                   class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-bold outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">New Base Access Tier *
                            <select wire:model="promoteNewRole" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-bold outline-none focus:border-amber-400">
                                <option value="student">Student / Trainee Tier</option>
                                <option value="manager">Manager / Officer Tier</option>
                                @if($previewRole === 'partner')
                                    <option value="partner">Partner / Principal Tier</option>
                                @endif
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Revised Monthly Pay / Stipend (BDT)
                            <input type="number" wire:model="promoteNewAllowance" min="0" step="100"
                                   class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-extrabold outline-none focus:border-amber-400">
                            <span class="mt-1 block text-[10px] text-slate-400">Previous: ৳{{ number_format($promoteMemberCurrentAllowance, 0) }}</span>
                        </label>
                        <label class="block font-bold text-slate-700">Effective Date
                            <input type="date" wire:model="promoteEffectiveDate"
                                   class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <label class="block font-bold text-slate-700">Appraisal / Order Remarks
                        <input type="text" wire:model="promoteRemarks" placeholder="e.g. Completed ICAB Articleship / Promoted for outstanding audit performance"
                               class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#17191F] px-5 py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800 transition shadow-sm">
                            <i data-lucide="award" class="h-4 w-4"></i> Confirm &amp; Record Order
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
