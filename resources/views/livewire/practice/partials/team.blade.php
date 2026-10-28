<section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">PRACTICE GOVERNANCE · TEAM MANAGEMENT</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Team &amp; Member Roster</h2>
        <p class="mt-1.5 text-xs text-slate-500">Manage practice partners, audit managers, and articled students. Assign supervisors, allowances, and delegated permissions.</p>
    </div>
    <div class="flex items-center gap-2">
        @if($canManageTeam)
            <button type="button" wire:click="openAddMemberModal" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] transition hover:bg-[#F3D604] shadow-sm">
                <i data-lucide="user-plus" class="h-4 w-4"></i> Add New Member
            </button>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-500">
                <i data-lucide="shield-alert" class="h-4 w-4 text-slate-400"></i> View-Only Roster
            </span>
        @endif
    </div>
</section>

@if(! $canManageTeam)
    <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3.5 text-xs text-amber-900 flex items-start gap-2.5">
        <i data-lucide="info" class="h-4 w-4 shrink-0 text-amber-700 mt-0.5"></i>
        <span>You are currently viewing the roster in <strong>read-only</strong> mode. Adding, editing, or removing practice members requires authorization delegated by an active Partner.</span>
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
    @endphp
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Total Roster</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-800"><i data-lucide="users" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ sprintf('%02d', $totalCount) }}</p>
        <p class="text-[10px] text-slate-400">Enrolled practice staff</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Partners</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-900"><i data-lucide="shield" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ sprintf('%02d', $partnerCount) }}</p>
        <p class="text-[10px] text-slate-400">Principals / Partners</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Audit Managers</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-700"><i data-lucide="briefcase" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ sprintf('%02d', $managerCount) }}</p>
        <p class="text-[10px] text-slate-400">Supervisors</p>
    </div>
    <div class="rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-500">Articled Students</span>
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700"><i data-lucide="graduation-cap" class="h-4 w-4"></i></div>
        </div>
        <p class="mt-2 text-2xl font-black text-[#17191F]">{{ sprintf('%02d', $studentCount) }}</p>
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
                    @if($canManageTeam)
                        <th class="py-3.5 pl-3 pr-6 text-right">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($teamMembers as $member)
                    <tr wire:key="member-{{ $member['id'] }}" class="hover:bg-slate-50/70 transition">
                        <td class="py-4 pl-6 pr-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl font-extrabold text-xs {{ $member['role'] === 'partner' ? 'bg-[#FCDF02] text-[#17191F]' : ($member['role'] === 'manager' ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-700') }}">
                                    {{ strtoupper(substr($member['name'], 0, 1)) }}
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
                                        <p class="text-[10px] font-mono text-emerald-700 mt-0.5">Code: {{ $member['student_code'] }}</p>
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
                                @if(empty($member['can_manage_team']) && empty($member['can_proxy_attendance']) && empty($member['can_edit_attendance']) && empty($member['can_approve_leave']) && empty($member['can_approve_claims']))
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
                        @if($canManageTeam)
                            <td class="py-4 pl-3 pr-6 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
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
                    </div>
                    <button type="button" wire:click="closeDialogs" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 transition"><i data-lucide="x" class="h-5 w-5"></i></button>
                </div>

                <form wire:submit.prevent="createMember" class="mt-4 space-y-4 text-xs">
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
                        <label class="block font-bold text-slate-700">Practice Email *
                            <input type="email" wire:model="newMemberEmail" required placeholder="e.g. farhana@{{ $firm['custom_domain'] ?? ($firmSlug . '.dhakawala.com') }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700">Temporary Password
                            <input type="text" wire:model="newMemberPassword" placeholder="Default: Secret123!" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400 font-mono text-[11px]">
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
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition">
                            <i data-lucide="check" class="h-4 w-4"></i> Create Member
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
                    </div>

                    <div class="flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">Cancel</button>
                        <button type="submit" wire:loading.attr="disabled" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-2 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition">
                            <i data-lucide="save" class="h-4 w-4"></i> Save Permissions
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
