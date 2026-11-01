<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">PRACTICE GOVERNANCE · TEAM MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Team &amp; Member Roster</h2>
        <p class="mt-1.5 text-xs text-slate-500">Manage practice partners, audit managers, and articled students. Assign supervisors, allowances and permissions; password resets use secure one-time links.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if($hasPartnerAuthority ?? ($previewRole === 'partner'))
            <label class="text-[10px] font-bold text-slate-500">Active unit
                <select wire:change="setPartnerUnit($event.target.value)" class="ml-1 rounded-lg border border-amber-300 bg-amber-50 px-2 py-2 text-[11px] font-bold text-amber-950">
                    @foreach($availablePartnerUnits as $unitOption)
                        <option value="{{ $unitOption }}" @selected($partnerUnit === $unitOption)>{{ $unitOption }}</option>
                    @endforeach
                </select>
            </label>
        @endif
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
                You can send a member a secure, single-use password reset link. Adding or removing members requires Team Admin authorization from a Partner.
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
        $showActionColumn = $canManageTeam || ($canResetPasswords ?? false);
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
            <span class="text-xs font-bold text-slate-500">Audit Managers</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><i data-lucide="briefcase" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($managerCount) }}</p>
        <p class="text-[10px] text-slate-400">Supervisors</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Articled Students</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><i data-lucide="graduation-cap" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ number_format($studentCount) }}</p>
        <p class="text-[10px] text-slate-400">ICAB Trainees</p>
    </div>
</section>

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

    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead class="border-b border-slate-100 bg-slate-50/75 text-[11px] font-extrabold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="py-3.5 pl-6 pr-3">Member Details</th>
                    <th class="px-3 py-3.5">Role</th>
                    <th class="px-3 py-3.5">Reporting Manager</th>
                    <th class="px-3 py-3.5">Partner Unit</th>
                    <th class="px-3 py-3.5">Delegated Permissions</th>
                    <th class="px-3 py-3.5">Allowance</th>
                    @if($showActionColumn)
                        <th class="py-3.5 pl-3 pr-6 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($teamMembers as $member)
                    <tr wire:key="member-{{ $member['id'] }}" class="hover:bg-slate-50/70 transition">
                        <td class="py-4 pl-6 pr-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-extrabold text-xs overflow-hidden {{ $member['role'] === 'partner' ? 'bg-[#FCDF02] text-[#17191F]' : ($member['role'] === 'manager' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700') }}">
                                    @if(!empty($member['avatar_url']))
                                        <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="h-full w-full object-cover">
                                    @else
                                        {{ strtoupper(substr($member['name'], 0, 1)) }}
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <p class="truncate font-bold text-slate-900">{{ $member['name'] }}</p>
                                        @if(Auth::check() && Auth::id() === $member['id'])
                                            <span class="rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold text-amber-900">You</span>
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
                                </div>
                            </div>
                        </td>
                        <td class="px-3 py-4 whitespace-nowrap">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold {{ $member['role'] === 'partner' ? 'bg-amber-100 text-amber-900 border border-amber-300' : ($member['role'] === 'manager' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-emerald-50 text-emerald-800 border border-emerald-200') }}">
                                {{ $member['role_label'] }}
                            </span>
                        </td>
                        <td class="px-3 py-4 text-slate-600 whitespace-nowrap">
                            {{ $member['manager_name'] ?? '—' }}
                        </td>
                        <td class="px-3 py-4 text-slate-600 whitespace-nowrap">
                            <span class="rounded bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-700">{{ $member['partner_unit'] ?? 'Partner Unit 01' }}</span>
                        </td>
                        <td class="px-3 py-4">
                            <div class="flex flex-wrap gap-1 max-w-[240px]">
                                @if(!empty($member['is_partner_delegate']))
                                    <span class="rounded bg-violet-100 px-1.5 py-0.5 text-[9px] font-bold text-violet-950 border border-violet-300" title="Partner-level firm authority; revocable by a Partner">Partner Delegate</span>
                                @endif
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
                                @if(empty($member['is_partner_delegate']) && empty($member['can_manage_team']) && empty($member['can_proxy_attendance']) && empty($member['can_edit_attendance']) && empty($member['can_approve_leave']) && empty($member['can_approve_claims']) && empty($member['can_manage_policies']) && empty($member['can_manage_payroll']) && empty($member['can_final_approve_leave']) && empty($member['can_final_approve_claims']))
                                    <span class="text-[10px] text-slate-400">Standard</span>
                                @endif
                            </div>
                        </td>
                        <td class="px-3 py-4 font-semibold text-slate-800 whitespace-nowrap">
                            @if(!empty($member['monthly_allowance']) && $member['monthly_allowance'] > 0)
                                ৳ {{ number_format($member['monthly_allowance'], 2) }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        @if($showActionColumn)
                            <td class="py-4 pl-3 pr-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    @if(($member['role'] ?? '') === 'student' && $canManageTeam)
                                        <button type="button" wire:click="exportStudentCertificate({{ $member['id'] }}, 'noc')"
                                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition" title="Print Official ICAB Articleship &amp; NOC Certificate">
                                            <i data-lucide="award" class="h-3.5 w-3.5"></i> ICAB Cert
                                        </button>
                                    @endif
                                    @if(($canResetPasswords ?? false) && (($hasPartnerAuthority ?? ($previewRole === 'partner')) || ($member['role'] ?? '') !== 'partner'))
                                        <button type="button" wire:click="openResetPasswordModal({{ $member['id'] }})"
                                                class="inline-flex items-center gap-1 rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-1.5 text-[11px] font-bold text-amber-950 hover:bg-amber-100 transition" title="Reset password and email new password to {{ $member['email'] }}">
                                            <i data-lucide="key-round" class="h-3.5 w-3.5"></i> Reset Pass
                                        </button>
                                    @endif
                                    @if($canManageTeam)
                                        <button type="button" wire:click="openEditMemberModal({{ $member['id'] }})"
                                                class="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-bold text-slate-700 hover:bg-slate-50 transition" title="Modify role & delegated permissions">
                                            Permissions
                                        </button>
                                        @if(!Auth::check() || Auth::id() !== $member['id'])
                                            <button type="button" wire:click="confirmDeleteMember({{ $member['id'] }})"
                                                    class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-1.5 text-[11px] font-bold text-rose-700 hover:bg-rose-100 transition" title="Remove member from practice">
                                                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i>
                                            </button>
                                        @endif
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
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Member Practice Role</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['manager' => 'Audit Manager', 'student' => 'Articled Student', 'partner' => 'Partner'] as $rVal => $rText)
                                <button type="button" wire:click="$set('newMemberRole', '{{ $rVal }}')"
                                        class="rounded-xl border p-2 text-center font-bold transition {{ $newMemberRole === $rVal ? 'border-amber-400 bg-amber-50 text-amber-950 ring-2 ring-amber-300' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                                    {{ $rText }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Full Name *
                            <input type="text" wire:model="newMemberName" required placeholder="e.g. Farhana Rahman, ACA" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                        <label class="block font-bold text-slate-700">Practice Email (Username) *
                            <input type="email" wire:model="newMemberEmail" required placeholder="e.g. farhana@{{ $firm['custom_domain'] ?? ($firmSlug . '.dhakawala.com') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-xs leading-relaxed text-emerald-900">
                            A one-time activation link will be emailed. The member creates their own password; passwords are never emailed.
                        </p>
                        <label class="block font-bold text-slate-700">Partner Unit
                            <select wire:model="newMemberPartnerUnit" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                                @foreach($availablePartnerUnits as $unit)
                                    <option value="{{ $unit }}">{{ $unit }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    @if($newMemberRole === 'manager')
                        <p class="rounded-xl border border-violet-200 bg-violet-50 p-3 text-[11px] leading-relaxed text-violet-950">
                            To grant this Manager Partner-level authority later, first have them activate their account and complete TOTP enrolment. A Partner can then grant or revoke delegation from this team panel.
                        </p>
                    @endif

                    @if($newMemberRole === 'student')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                            <label class="block font-bold text-slate-700">Student ICAB Code
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
                            <label class="block font-bold text-slate-700">Articleship Completion (3-Yr)
                                <input type="date" wire:model="newMemberArticleshipEnd" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                            </label>
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
                            <p class="font-extrabold text-teal-900 uppercase tracking-wider text-[10px]">⚡ Partner-Level Executive Delegation (পলিসি ও ফাইনাল অ্যাপ্রুভাল পাওয়ার)</p>
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

                    <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-[11px] leading-relaxed text-emerald-900">
                        We will email <strong>{{ $resetPasswordMemberEmail }}</strong> a time-limited, single-use reset link. The requester will not see or receive the member’s password.
                    </p>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" wire:target="resetMemberPassword" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition shadow-sm">
                            <i data-lucide="send" class="h-3.5 w-3.5"></i>
                            <span wire:loading.remove wire:target="resetMemberPassword">Send Secure Reset Link</span>
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
                        <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Authority Delegation</p>
                        <h2 class="mt-1 text-lg font-black text-slate-900">Manage Permissions &amp; Profile</h2>
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
                        <p class="rounded-xl border border-emerald-200 bg-emerald-50 p-2.5 text-[11px] leading-relaxed text-emerald-900">
                            Password changes use the secure <strong>Reset Pass</strong> action and a single-use link. No password is shown or emailed.
                        </p>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Role
                            <select wire:model="editMemberRole" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                                <option value="partner">Partner / Principal</option>
                                <option value="manager">Audit Manager</option>
                                <option value="student">Articled Student</option>
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

                    @if($editMemberRole === 'manager' && (auth()->user()?->isPartner() || auth()->user()?->isPlatformAdmin()))
                        <label class="flex items-start gap-2.5 rounded-xl border border-violet-200 bg-violet-50 p-3 cursor-pointer">
                            <input type="checkbox" wire:model="editMemberIsPartnerDelegate" class="mt-0.5 rounded border-violet-300 text-violet-700 focus:ring-violet-400">
                            <span>
                                <span class="block font-bold text-violet-950">Partner-equivalent firm authority</span>
                                <span class="mt-0.5 block text-[10px] text-violet-800">Grant or revoke this Manager’s firm-wide authority after confirmed TOTP enrolment. They can manage units, team roles, policies, approvals and payroll, but never platform administration.</span>
                            </span>
                        </label>
                    @endif

                    @if($editMemberRole === 'student')
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                            <label class="block font-bold text-slate-700">Student ICAB Code
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
                            <label class="block font-bold text-slate-700">Articleship Completion Date
                                <input type="date" wire:model="editMemberArticleshipEnd" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2 outline-none focus:border-amber-400">
                            </label>
                        </div>
                    @endif

                    <label class="block font-bold text-slate-700">Monthly Stipend / Allowance (BDT)
                        <input type="number" wire:model="editMemberMonthlyAllowance" step="100" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>

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
                            <p class="font-extrabold text-teal-900 uppercase tracking-wider text-[10px]">⚡ Partner-Level Executive Delegation (পলিসি ও ফাইনাল অ্যাপ্রুভাল পাওয়ার)</p>
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
                            <span wire:loading.remove wire:target="updateMember">Save Permissions</span>
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
