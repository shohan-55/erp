<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">WORKSPACE CONFIGURATION · PHASE 10</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Workspace Settings &amp; Partner Policy</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Configure independent Partner Unit policies, Manager delegation thresholds, and toggle active Student dashboard widgets.</p>
    </div>
    @if($previewRole === 'partner')
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-600">Active Partner Unit:</label>
            <select wire:change="setPartnerUnit($event.target.value)" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-extrabold text-amber-950 outline-none">
                <option value="Partner Unit 01" @selected($partnerUnit === 'Partner Unit 01')>Partner Unit 01 (Mohitul Haq, FCA)</option>
                <option value="Partner Unit 02" @selected($partnerUnit === 'Partner Unit 02')>Partner Unit 02 (Anwarul Kabir, FCA)</option>
            </select>
        </div>
    @endif
</section>

<div class="grid gap-5 lg:grid-cols-2">
    <!-- Partner Identity & Email Change -->
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]">
                <i data-lucide="mail-check" class="h-5 w-5"></i>
            </div>
            <div class="flex-1">
                <h3 class="text-sm font-extrabold">Partner Account &amp; Email</h3>
                <p class="mt-1 text-xs text-slate-500">View and update your registered partner credentials.</p>
            </div>
        </div>

        <div class="mt-5 space-y-4">
            <div class="rounded-xl border border-slate-100 bg-slate-50 p-4 text-xs">
                <p class="text-slate-500">Current Registered Email:</p>
                <p class="mt-1 text-sm font-bold text-slate-800">{{ $partnerEmail }}</p>
                <span class="mt-2 inline-block rounded bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-900">{{ $partnerUnit }}</span>
            </div>

            @if($previewRole === 'partner')
                <form wire:submit.prevent="updatePartnerEmail" class="space-y-3">
                    <label class="block text-xs font-bold text-slate-700">Change Partner Email Address
                        <input type="email" wire:model="newPartnerEmail" placeholder="Enter new email (e.g. new.partner@rahmananis.com)" required
                               class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs outline-none focus:border-amber-400">
                    </label>
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                        <i data-lucide="check" class="h-4 w-4"></i> Update Partner Email
                    </button>
                </form>
            @else
                <p class="text-[11px] text-slate-400">Only an authenticated Partner can update the practice email address.</p>
            @endif
        </div>
    </section>

    <!-- Partner Policy Configuration: Office Hours, Conveyance Limits & Late Submission -->
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                <i data-lucide="sliders" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-extrabold">Unit Policy Defaults ({{ $partnerUnit }})</h3>
                <p class="mt-1 text-xs text-slate-500">Each Partner sets independent thresholds for their students and managers.</p>
            </div>
        </div>

        @if($previewRole === 'partner')
            <form wire:submit.prevent="savePolicySettings" class="mt-5 space-y-4 text-xs">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block font-bold text-slate-700">Office Start Time
                        <input type="text" wire:model="officeStart" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>
                    <label class="block font-bold text-slate-700">Office End Time
                        <input type="text" wire:model="officeEnd" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block font-bold text-slate-700">Manager Daily Claim Limit (৳)
                        <input type="number" wire:model="managerConveyanceLimit" min="50" max="10000" step="10" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        <span class="mt-1 block text-[10px] text-slate-500">Claims &gt; ৳{{ $managerConveyanceLimit }} escalate to Partner</span>
                    </label>
                    <label class="block font-bold text-slate-700">Late Claim Window (Days)
                        <input type="number" wire:model="conveyanceLateDays" min="1" max="30" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        <span class="mt-1 block text-[10px] text-slate-500">Claims &gt; {{ $conveyanceLateDays }} days late require Partner sanction</span>
                    </label>
                </div>
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#17191F] px-4 py-2.5 text-xs font-extrabold text-white hover:bg-slate-800">
                    <i data-lucide="save" class="h-4 w-4 text-[#FCDF02]"></i> Save Policy Settings
                </button>
            </form>
        @else
            <div class="mt-5 grid grid-cols-2 gap-3 text-xs">
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Office Hours</span><p class="mt-1 font-bold">{{ $officeStart }} — {{ $officeEnd }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Grace Period</span><p class="mt-1 font-bold">{{ $graceMinutes }} minutes</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Manager Conveyance Limit</span><p class="mt-1 font-bold">৳ {{ number_format($managerConveyanceLimit, 2) }}</p></div>
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Late Submission Window</span><p class="mt-1 font-bold">{{ $conveyanceLateDays }} Days</p></div>
            </div>
        @endif
    </section>

    <!-- Student Dashboard Grids: Active/Deactive Controls -->
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                <i data-lucide="layout-grid" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-extrabold">Student Dashboard Grids (Active / Deactive)</h3>
                <p class="mt-1 text-xs text-slate-500">Partner controls which cards and features appear on the Articled Student dashboard.</p>
            </div>
        </div>

        <div class="mt-5 space-y-2.5">
            @php
                $gridLabels = [
                    'attendance_clock'   => ['Attendance Clock-in Card', 'Allows GPS check-in/out button'],
                    'live_gps_status'    => ['Live GPS Location Tag', 'Shows current GPS address & coordinates'],
                    'leave_balance'      => ['Leave Balance & Quota Card', 'Displays annual, sick, and ICAB study days'],
                    'conveyance_tracker' => ['Conveyance Expense Tracker', 'Displays monthly conveyance claim ledger'],
                    'client_site_duty'   => ['Client Site Assignment Tag', 'Displays assigned client audit site'],
                    'firm_notices'       => ['Firm Notices & Policy Alert', 'Displays partner policy announcements'],
                ];
            @endphp

            @foreach($gridLabels as $key => $meta)
                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                    <div>
                        <p class="font-bold text-slate-800">{{ $meta[0] }}</p>
                        <p class="text-[10px] text-slate-500">{{ $meta[1] }}</p>
                    </div>
                    @if($previewRole === 'partner')
                        <button type="button" wire:click="toggleStudentGrid('{{ $key }}')"
                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition {{ ($studentGrids[$key] ?? true) ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                            {{ ($studentGrids[$key] ?? true) ? 'ACTIVE' : 'DEACTIVE' }}
                        </button>
                    @else
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ ($studentGrids[$key] ?? true) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                            {{ ($studentGrids[$key] ?? true) ? 'ACTIVE' : 'DEACTIVE' }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <!-- Manager Permissions & Feature Visibility Matrix -->
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                <i data-lucide="user-check" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-extrabold">Manager Delegation &amp; Permissions</h3>
                <p class="mt-1 text-xs text-slate-500">Partner grants or restricts Manager capabilities across modules.</p>
            </div>
        </div>

        <div class="mt-5 space-y-2.5">
            @php
                $permLabels = [
                    'proxy_attendance'  => ['Proxy Attendance', 'Can submit check-in on behalf of students'],
                    'edit_attendance'   => ['Edit Late / In-Out Time', 'Can modify late flag and timing errors'],
                    'review_leave'      => ['Review & Recommend Leave', 'Can review student leave and forward to Partner'],
                    'review_claims'     => ['Review & Adjust Conveyance', 'Can approve up to ৳' . $managerConveyanceLimit . ' and adjust amount'],
                    'view_team_reports' => ['View Team Audit Reports', 'Can inspect aggregated timesheet and claim reports'],
                ];
            @endphp

            @foreach($permLabels as $key => $meta)
                <div class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-3 text-xs">
                    <div>
                        <p class="font-bold text-slate-800">{{ $meta[0] }}</p>
                        <p class="text-[10px] text-slate-500">{{ $meta[1] }}</p>
                    </div>
                    @if($previewRole === 'partner')
                        <button type="button" wire:click="toggleManagerPermissionKey('{{ $key }}')"
                                class="rounded-lg px-2.5 py-1 text-[11px] font-extrabold transition {{ ($managerPermissions[$key] ?? true) ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600' }}">
                            {{ ($managerPermissions[$key] ?? true) ? 'ALLOWED' : 'DISABLED' }}
                        </button>
                    @else
                        <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ ($managerPermissions[$key] ?? true) ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                            {{ ($managerPermissions[$key] ?? true) ? 'ALLOWED' : 'DISABLED' }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
</div>