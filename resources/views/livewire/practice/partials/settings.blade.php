
<!-- ===================================================================== -->
<!-- SELF-SERVICE PROFILE, PASSWORD & bKash/BANK ACCOUNT UPDATE (#1)       -->
<!-- ===================================================================== -->
<section id="my-profile"
         x-data
         @profile-focus.window="$nextTick(() => $el.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
         class="scroll-mt-24 rounded-2xl border-2 border-emerald-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex items-start gap-3 border-b border-slate-100 pb-4">
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white">
            <i data-lucide="user-cog" class="h-5 w-5"></i>
        </div>
        <div>
            <h3 class="text-base font-extrabold text-slate-900">My Profile, Password &amp; Stipend Payment Account (bKash / Nagad / Bank)</h3>
            <p class="mt-0.5 text-xs text-slate-500">Update your mobile phone, monthly allowance/conveyance receiving account number (printed on your payment voucher), and login password.</p>
        </div>
    </div>

    <form wire:submit.prevent="updateOwnProfileAndPassword" class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 text-xs">
        <!-- Profile Picture Upload & Preview -->
        <div class="sm:col-span-2 lg:col-span-3 flex items-center gap-4 p-3.5 rounded-2xl bg-slate-50 border border-slate-200/80">
            <div class="relative flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-[#17191F] text-white font-extrabold text-base overflow-hidden border border-slate-200 shadow-sm">
                @if($myAvatar)
                    <img src="{{ $myAvatar->temporaryUrl() }}" alt="New Avatar Preview" class="h-full w-full object-cover">
                @elseif(!empty($myAvatarUrl))
                    <img src="{{ $myAvatarUrl }}" alt="Avatar" class="h-full w-full object-cover">
                @else
                    <span class="text-[#FCDF02]">{{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}</span>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <label class="block font-extrabold text-slate-900 text-xs">Profile Picture / Avatar Photo (ছবি আপলোড)</label>
                <p class="text-[11px] text-slate-500 mt-0.5">JPG, PNG, or WebP up to 2MB. Appears on your top header, sidebar, and team roster.</p>
                <div class="mt-2 flex items-center gap-2">
                    <input type="file" wire:model="myAvatar" accept="image/*" class="text-[11px] text-slate-600 file:mr-2 file:py-1 file:px-2.5 file:rounded-lg file:border-0 file:text-[11px] file:font-bold file:bg-[#16181D] file:text-[#FCDF02] hover:file:bg-slate-800 cursor-pointer">
                    <span wire:loading wire:target="myAvatar" class="text-[10px] text-amber-800 font-bold animate-pulse">Uploading preview...</span>
                </div>
            </div>
        </div>

        <label class="block font-bold text-slate-700">Full Name
            <input type="text" wire:model="myName" required placeholder="e.g. Khandaker Tanvir Ahmed, ACA" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">Account Email Address
            <input type="email" wire:model="myEmail" required placeholder="user@firm.com" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">Mobile Phone Number
            <input type="text" wire:model="myPhone" placeholder="e.g. +880 1711-XXXXXX" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">Allowance / Conveyance Payment Mode
            <select wire:model="myPaymentMode" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-semibold outline-none focus:border-amber-400">
                <option value="bKash">bKash Personal / Account</option>
                <option value="Nagad">Nagad Personal / Account</option>
                <option value="Bank A/C">Bank Account Transfer</option>
                <option value="Cash">Cash Counter Voucher</option>
            </select>
        </label>

        <label class="block font-bold text-slate-700">bKash / Nagad / Bank Account No.
            <input type="text" wire:model="myPaymentAccountNo" placeholder="e.g. 01711-XXXXXX or A/C #1204..." class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-mono outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">Current Password (only required to change your email)
            <input type="password" wire:model="myCurrentPassword" autocomplete="current-password" placeholder="Enter current password before changing the sign-in email" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">New Login Password (Optional)
            <input type="password" wire:model="myNewPassword" placeholder="Leave blank to keep current password" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <label class="block font-bold text-slate-700">Confirm New Password
            <input type="password" wire:model="myNewPasswordConfirm" placeholder="Re-enter new password" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
        </label>

        <div class="flex items-end">
            <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-sm">
                <i data-lucide="save" class="h-4 w-4"></i> Save My Profile &amp; Payment Account
            </button>
        </div>
    </form>
</section>

@if($previewRole === 'partner')
<!-- ===================================================================== -->
<!-- FIRM BRAND COLOR PALETTE SELECTOR (PARTNER CONTROL)                   -->
<!-- ===================================================================== -->
<section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-white shadow-sm" style="background-color: {{ $firm['primary_color'] ?? '#0D9488' }}">
                <i data-lucide="palette" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900">Firm Brand Color Palette (ফার্ম বা কোম্পানির কালার প্যালেট)</h3>
                <p class="mt-0.5 text-xs text-slate-500">Select your firm's official brand color palette or custom color. Click any palette below to immediately update the entire workspace and login portal.</p>
            </div>
        </div>
        <span class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-extrabold text-slate-800">
            <span class="h-3 w-3 rounded-full" style="background-color: {{ $firm['primary_color'] ?? '#0D9488' }}"></span>
            <span>{{ $firm['palette_label'] ?? 'Sea Green' }}</span>
        </span>
    </div>

    <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
        @foreach(($colorPalettes ?? \App\Models\Firm::colorPalettes()) as $pKey => $pal)
            <button type="button"
                    wire:click="selectFirmThemePalette('{{ $pKey }}')"
                    class="group flex items-center gap-3 rounded-2xl border p-3 text-left transition {{ $firmThemePalette === $pKey ? 'border-slate-900 bg-slate-50 shadow-sm ring-2 ring-slate-900/10' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50/60' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl shadow-inner border border-black/10 text-white" style="background-color: {{ $pal['primary'] }}">
                    @if($firmThemePalette === $pKey)
                        <i data-lucide="check" class="h-4 w-4"></i>
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-extrabold text-slate-900">{{ $pal['label'] }}</p>
                    <p class="font-mono text-[10px] text-slate-400">{{ $pal['primary'] }}</p>
                </div>
            </button>
        @endforeach
    </div>

    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3.5 text-xs">
        <div class="flex items-center gap-3">
            <input type="color" wire:model.live="firmCustomPrimaryColor" wire:change="$set('firmThemePalette', 'custom')" class="h-9 w-14 cursor-pointer rounded-lg border border-slate-300 bg-white p-0.5">
            <div>
                <p class="font-extrabold text-slate-900">Custom Brand Hex Color (পছন্দের যেকোনো কালার কোড)</p>
                <p class="text-[11px] text-slate-500">Selected Hex: <span class="font-mono font-bold text-slate-800">{{ $firmCustomPrimaryColor }}</span></p>
            </div>
        </div>
        <button type="button" wire:click="saveFirmThemePalette" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-sm">
            <i data-lucide="check-circle-2" class="h-4 w-4"></i> Save Brand Color Palette
        </button>
    </div>
</section>
@endif


<section class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">PRACTICE GOVERNANCE · POLICY &amp; PERMISSIONS</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Workspace Settings &amp; Partner Policy</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Configure independent Partner Unit policies, late deduction rules, Manager delegation thresholds, and toggle active Student dashboard grids.</p>
    </div>
    @if($previewRole === 'partner')
        <div class="flex items-center gap-2">
            <label class="text-xs font-bold text-slate-600">Active Partner Unit:</label>
            <select wire:change="setPartnerUnit($event.target.value)" class="rounded-xl border border-amber-300 bg-amber-50 px-3 py-2 text-xs font-extrabold text-amber-950 outline-none">
                <option value="Partner Unit 01" @selected($partnerUnit === 'Partner Unit 01')>Partner Unit 01</option>
                <option value="Partner Unit 02" @selected($partnerUnit === 'Partner Unit 02')>Partner Unit 02</option>
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
                        <input type="email" wire:model="newPartnerEmail" placeholder="Enter new email (e.g. partner@{{ $firm['custom_domain'] ?? (($firm['slug'] ?? 'practice') . '.dhakawala.com') }})" required
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

    <!-- Partner Policy Configuration: Office Hours, Grace, Late Deduction Rules & Conveyance Limits -->
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                <i data-lucide="sliders" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-sm font-extrabold">Unit Policy &amp; Late Deduction Rules ({{ $partnerUnit }})</h3>
                <p class="mt-1 text-xs text-slate-500">Set office hours, grace minutes (no deduction), late deduction thresholds, and conveyance windows.</p>
            </div>
        </div>

        @if(in_array($previewRole, ['partner', 'manager']))
            <form wire:submit.prevent="savePolicySettings" class="mt-5 space-y-4 text-xs">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <label class="block font-bold text-slate-700">Office Start
                        <input type="text" wire:model="officeStart" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>
                    <label class="block font-bold text-slate-700">Office End
                        <input type="text" wire:model="officeEnd" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>
                    <label class="block font-bold text-slate-700">Grace (Min)
                        <input type="number" wire:model="graceMinutes" min="0" max="120" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                    </label>
                </div>

                <!-- Late Deduction Rule Box -->
                <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-3.5 space-y-3">
                    <label class="flex items-center justify-between cursor-pointer">
                        <span class="font-extrabold text-amber-950">Enable Automatic Late Allowance Deduction</span>
                        <input type="checkbox" wire:model.live="latePenaltyEnabled" class="rounded border-amber-300 text-amber-600">
                    </label>
                    <p class="text-[11px] text-slate-600">Arrivals after {{ $officeStart }} + {{ $graceMinutes }}m are marked Late. Configure how many Late days trigger an automatic stipend deduction:</p>
                    @if($latePenaltyEnabled)
                        <div class="grid grid-cols-2 gap-3 pt-1">
                            <label class="block font-bold text-slate-700">Late Days Count (কয়দিন লেট করলে)
                                <input type="number" wire:model="lateDaysPerDeduction" min="1" max="30" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2">
                            </label>
                            <label class="block font-bold text-slate-700">Deduct Allowance Days (কয়দিনের ভাতা কাটবে)
                                <input type="number" wire:model="deductionDaysCount" min="0.5" max="10" step="0.5" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2">
                            </label>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <label class="block font-bold text-slate-700">Manager Claim Limit (৳)
                        <input type="number" wire:model="managerConveyanceLimit" min="50" max="10000" step="10" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        <span class="mt-1 block text-[10px] text-slate-500">Claims &gt; ৳{{ $managerConveyanceLimit }} escalate to Partner</span>
                    </label>
                    <label class="block font-bold text-slate-700">Manager Claim Window (Days)
                        <input type="number" wire:model="conveyanceLateDays" min="1" max="30" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 outline-none focus:border-amber-400">
                        <span class="mt-1 block text-[10px] text-slate-500">Claims &gt; {{ $conveyanceLateDays }} days old require Partner sanction</span>
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
                <div class="rounded-xl bg-slate-50 p-3"><span class="text-slate-500">Late Deduction Rule</span><p class="mt-1 font-bold">{{ $latePenaltyEnabled ? ($lateDaysPerDeduction . ' Late = ' . $deductionDaysCount . 'd Cut') : 'No Financial Deduction' }}</p></div>
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
                <p class="mt-1 text-xs text-slate-500">Partner controls which cards (including Payroll/Allowance) appear on the Student dashboard.</p>
            </div>
        </div>

        <div class="mt-5 space-y-2.5">
            @php
                $gridLabels = [
                    'attendance_clock'   => ['Attendance Clock-in Card', 'Allows GPS check-in/out and On-Duty button'],
                    'live_gps_status'    => ['Live GPS Location Tag', 'Shows current GPS address & coordinates'],
                    'leave_balance'      => ['Leave Balance & Quota Card', 'Displays annual, sick, and ICAB study days'],
                    'conveyance_tracker' => ['Conveyance Expense Tracker', 'Displays monthly conveyance claim ledger'],
                    'payroll_allowance'  => ['Monthly Allowance & Payroll Card', 'Shows automatic stipend & approved conveyance on student dashboard'],
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
                    'review_leave'      => ['Review & Partial Approve Leave', 'Can review student leave, adjust days, and forward to Partner'],
                    'review_claims'     => ['Review & Adjust Conveyance (±)', 'Can approve up to ৳' . $managerConveyanceLimit . ' and increase/decrease amount'],
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

<!-- ===================================================================== -->
<!-- WEEKLY OFF DAYS (per firm) — Bangladesh firms do not all close Fri+Sat -->
<!-- ===================================================================== -->
<section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6" wire:key="weekly-off-days-card">
    <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-600 text-white">
                <i data-lucide="calendar-off" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900">Weekly Off Days &mdash; {{ $weeklyOffSummary }}</h3>
                <p class="mt-0.5 text-xs text-slate-500">The recurring weekend for this firm. The attendance calendar marks these weekdays as off, and the policy summary shows them to every member. A government or sudden holiday is still added in the calendar below.</p>
            </div>
        </div>
        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-rose-50 px-3 py-1.5 text-[11px] font-extrabold text-rose-800">
            <i data-lucide="calendar-days" class="h-3.5 w-3.5"></i>
            {{ count($weeklyOffDays) }} off {{ count($weeklyOffDays) === 1 ? 'day' : 'days' }} / week
        </span>
    </div>

    @if($previewRole === 'partner')
        <form wire:submit.prevent="saveWeeklyOffDays" class="mt-5 space-y-4 text-xs">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-7">
                @foreach(\App\Models\Firm::WEEKDAY_LABELS as $dow => $dayLabel)
                    <label class="cursor-pointer select-none" wire:key="weekly-off-{{ $dow }}">
                        <input type="checkbox" wire:model.live="weeklyOffDayDraft" value="{{ $dow }}" class="peer sr-only">
                        <span class="flex flex-col items-center justify-center gap-1 rounded-xl border border-slate-200 bg-slate-50 px-2 py-3 text-[11px] font-extrabold text-slate-600 transition hover:border-slate-300 peer-checked:border-rose-400 peer-checked:bg-rose-50 peer-checked:text-rose-900 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400">
                            <i data-lucide="{{ in_array($dow, $weeklyOffDayDraft, true) || in_array((string) $dow, $weeklyOffDayDraft, true) ? 'calendar-off' : 'calendar-check' }}" class="h-4 w-4"></i>
                            {{ \App\Models\Firm::WEEKDAY_SHORT[$dow] }}
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] shadow-sm hover:bg-[#F3D604]">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Weekly Holiday
                </button>
                <p class="text-[11px] text-slate-500">Currently saved: <strong class="text-slate-700">{{ $weeklyOffSummary }}</strong>. Tick a day to make it a weekly holiday; untick every day for a firm that works all seven.</p>
            </div>
        </form>
    @else
        <p class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-600">
            Only a Partner can change the weekly holiday. Current pattern: <strong>{{ $weeklyOffSummary }}</strong>.
        </p>
    @endif
</section>

<!-- ===================================================================== -->
<!-- WORKING DAY EXCEPTION — one date whose hours differ from the policy    -->
<!-- ===================================================================== -->
<section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6" wire:key="working-day-exception-card">
    <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-teal-600 text-white">
                <i data-lucide="clock-3" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900">Working Day Exception &mdash; one date, different hours</h3>
                <p class="mt-0.5 text-xs text-slate-500">For a hartal, storm, flood or an Eid half-day: shift that one date's office hours, widen the grace period, or switch lateness off for the day. The standing policy is <strong>never edited</strong>, so past days and already-approved payroll stay exactly as they were.</p>
            </div>
        </div>
        <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-teal-50 px-3 py-1.5 text-[11px] font-extrabold text-teal-800">
            <i data-lucide="calendar-clock" class="h-3.5 w-3.5"></i>
            Policy: {{ $officeStart }} &ndash; {{ $officeEnd }} &middot; grace {{ $graceMinutes }}m
        </span>
    </div>

    @if($previewRole === 'partner')
        <form wire:submit.prevent="saveWorkingDayException" class="mt-5 space-y-3.5 text-xs">
            <div class="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-4">
                <label class="block font-bold text-slate-700">Date *
                    <input type="date" wire:model="newExceptionDate" required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-teal-400">
                </label>
                <label class="block font-bold text-slate-700">Office starts (optional)
                    <input type="text" wire:model="newExceptionStart" placeholder="e.g. 12:00" inputmode="numeric" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-mono text-xs outline-none focus:border-teal-400">
                </label>
                <label class="block font-bold text-slate-700">Office ends (optional)
                    <input type="text" wire:model="newExceptionEnd" placeholder="e.g. 16:00" inputmode="numeric" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 font-mono text-xs outline-none focus:border-teal-400">
                </label>
                <label class="block font-bold text-slate-700">Grace minutes (optional)
                    <input type="number" wire:model="newExceptionGrace" min="0" max="240" placeholder="{{ $graceMinutes }}" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-teal-400">
                </label>
            </div>

            <label class="block font-bold text-slate-700">Exception name *
                <input type="text" wire:model="newExceptionTitle" required placeholder="e.g. Hartal — office opens at 12:00 / Heavy rain" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs outline-none focus:border-teal-400">
            </label>

            <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-3 space-y-2.5">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model.live="newExceptionLateExempt" class="mt-0.5 rounded border-teal-400 text-teal-600">
                    <div>
                        <span class="font-extrabold text-teal-950">Lateness is not counted on this date (দেরি গণ্য হবে না)</span>
                        <p class="text-[10px] text-teal-800 mt-0.5">Arrivals are still recorded honestly as <strong>Late (Excused)</strong>, but they never feed the late-day count and can never trigger a salary deduction.</p>
                    </div>
                </label>

                <div class="grid gap-2 pt-1 sm:grid-cols-2">
                    <label class="block font-bold text-teal-900">Reason / note
                        <input type="text" wire:model="newExceptionReason" placeholder="e.g. Hartal called overnight" class="mt-1 w-full rounded-lg border border-teal-200 bg-white p-2 text-xs">
                    </label>
                    <label class="flex items-center gap-2 pt-5 font-bold text-teal-900 cursor-pointer">
                        <input type="checkbox" wire:model="newExceptionAllUnits" class="rounded border-teal-300 text-teal-600">
                        Apply to every Partner Unit
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-xs font-extrabold text-white shadow-sm hover:bg-teal-700">
                    <i data-lucide="save" class="h-4 w-4"></i> Save Working Day Exception
                </button>
                <button type="button" wire:click="previewWorkingDayException" class="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-extrabold text-slate-700 hover:bg-slate-50">
                    <i data-lucide="scan-eye" class="h-4 w-4"></i> Preview impact
                </button>
                <p class="text-[11px] text-slate-500">Nothing is written until you save. Today's register is re-checked from each member's recorded check-in time.</p>
            </div>
        </form>

        @if(!empty($exceptionImpact))
            <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs" wire:key="exception-impact">
                @if(!empty($exceptionImpact['error']))
                    <p class="font-bold text-rose-700">{{ $exceptionImpact['error'] }}</p>
                @else
                    <p class="font-extrabold text-slate-900">
                        {{ !empty($exceptionImpact['saved']) ? 'Saved' : 'Preview' }} &mdash;
                        {{ $exceptionImpact['date'] }} &middot; office {{ $exceptionImpact['start'] }}&ndash;{{ $exceptionImpact['end'] }} &middot; grace {{ $exceptionImpact['grace'] }}m
                        @if(!empty($exceptionImpact['late_exempt'])) &middot; lateness not counted @endif
                    </p>
                    <p class="mt-1 text-[11px] text-slate-600">
                        {{ (int) ($exceptionImpact['scanned'] ?? 0) }} attendance record(s) on this date,
                        <strong>{{ (int) ($exceptionImpact['changed'] ?? 0) }}</strong> would hold a different status.
                    </p>
                    @if(!empty($exceptionImpact['rows']))
                        <ul class="mt-2 space-y-1">
                            @foreach(array_slice($exceptionImpact['rows'], 0, 8) as $change)
                                <li class="flex flex-wrap items-center gap-1.5 text-[11px]">
                                    <span class="font-bold text-slate-800">{{ $change['name'] }}</span>
                                    <span class="text-slate-400">(in {{ $change['in_time'] }})</span>
                                    <span class="rounded bg-rose-100 px-1.5 py-0.5 font-bold text-rose-800">{{ $change['from'] }}</span>
                                    <span class="text-slate-400">→</span>
                                    <span class="rounded bg-emerald-100 px-1.5 py-0.5 font-bold text-emerald-800">{{ $change['to'] }}</span>
                                </li>
                            @endforeach
                            @if(count($exceptionImpact['rows']) > 8)
                                <li class="text-[11px] font-semibold text-slate-500">+ {{ count($exceptionImpact['rows']) - 8 }} more…</li>
                            @endif
                        </ul>
                    @endif
                @endif
            </div>
        @endif
    @else
        <p class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs font-semibold text-slate-600">
            Only a Partner can shift office hours or excuse lateness for a date. Ask your Partner, or submit a policy proposal.
        </p>
    @endif

    @php $exceptions = collect($holidaysList ?? [])->where('is_working_day_exception', true)->sortByDesc('holiday_date')->take(10); @endphp
    @if($exceptions->isNotEmpty())
        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
            <div class="border-b border-slate-100 bg-slate-50 px-4 py-2.5 text-[11px] font-extrabold uppercase tracking-wider text-slate-600">
                Declared Exceptions ({{ $exceptions->count() }})
            </div>
            <div class="divide-y divide-slate-100 text-xs">
                @foreach($exceptions as $ex)
                    <div wire:key="exception-{{ $ex['id'] }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-slate-900">{{ $ex['title'] }}</span>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $ex['display_date'] ?? $ex['holiday_date'] }}</span>
                                <span class="rounded bg-teal-50 px-1.5 py-0.5 text-[10px] font-bold text-teal-800">{{ $ex['partner_unit'] }}</span>
                            </div>
                            <p class="mt-0.5 text-[11px] font-semibold text-teal-800">{{ $ex['effect_label'] ?? '' }}{{ !empty($ex['override_reason']) ? ' · ' . $ex['override_reason'] : '' }}</p>
                        </div>
                        @if($previewRole === 'partner')
                            <button type="button" wire:click="deleteWorkingDayException({{ $ex['id'] }})" class="rounded p-1 text-slate-400 hover:text-rose-600" title="Remove exception">✕</button>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>

<!-- ===================================================================== -->
<!-- GOVERNMENT HOLIDAY CALENDAR & PARTNER "OFFICE OPEN" OVERRIDE (#2)     -->
<!-- ===================================================================== -->
<section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-col justify-between gap-3 border-b border-slate-100 pb-4 sm:flex-row sm:items-center">
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]">
                <i data-lucide="calendar-days" class="h-5 w-5"></i>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-slate-900">Government Holiday Calendar &amp; Special Office-Open Override ({{ $holidayYear }})</h3>
                <p class="mt-0.5 text-xs text-slate-500">Update annual Government Holidays at the start of the year, add sudden holidays anytime, or declare any Holiday/Weekend as <strong>Office Open (Mandatory Attendance — Absent = Salary Deduction)</strong>.</p>
            </div>
        </div>
        @if(in_array($previewRole, ['partner', 'manager']))
            <button type="button" wire:click="seedAnnualGovernmentHolidays" class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-[#FCDF02] px-3.5 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] shadow-sm">
                <i data-lucide="refresh-cw" class="h-4 w-4"></i> Load / Sync {{ $holidayYear }} Govt. Holidays
            </button>
        @endif
    </div>

    <div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.35fr)]">
        @if(in_array($previewRole, ['partner', 'manager']))
            <form wire:submit.prevent="addOrUpdateHoliday" class="space-y-3.5 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 text-xs">
                <p class="font-extrabold uppercase tracking-wider text-amber-900 text-[11px]">Add Holiday or Declare Holiday Office-Open</p>

                <div class="grid grid-cols-2 gap-2.5">
                    <label class="block font-bold text-slate-700">Date *
                        <input type="date" wire:model="newHolidayDate" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs">
                    </label>
                    <label class="block font-bold text-slate-700">Type
                        <select wire:model="newHolidayCategory" class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs font-semibold">
                            <option value="government">Govt. Holiday</option>
                            <option value="sudden">Custom / Sudden Holiday</option>
                            <option value="weekend_override">Weekend / Special Working Day</option>
                        </select>
                    </label>
                </div>

                <label class="block font-bold text-slate-700">Holiday / Occasion Name *
                    <input type="text" wire:model="newHolidayTitle" placeholder="e.g. Eid-ul-Fitr / Urgent Tax Closing Saturday" required class="mt-1 w-full rounded-xl border border-slate-200 bg-white p-2.5 text-xs">
                </label>

                <div class="rounded-xl border border-rose-200 bg-rose-50/70 p-3 space-y-2.5">
                    <label class="flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" wire:model.live="newHolidayIsOfficeOpen" class="mt-0.5 rounded border-rose-300 text-rose-600">
                        <div>
                            <span class="font-extrabold text-rose-950">Keep Office Open on this Holiday / Weekend (অফিস খোলা থাকবে)</span>
                            <p class="text-[10px] text-rose-800 mt-0.5">If checked, attendance is mandatory on this date. Anyone who does not attend (without approved leave) will be marked <strong>Absent</strong> and have salary deducted.</p>
                        </div>
                    </label>

                    @if($newHolidayIsOfficeOpen)
                        <div class="grid grid-cols-2 gap-2 pt-1">
                            <label class="block font-bold text-rose-900">Absent Salary Cut (Days)
                                <input type="number" wire:model="newHolidayAbsentCutDays" min="0.5" max="5" step="0.5" class="mt-1 w-full rounded-lg border border-rose-200 bg-white p-2 text-xs font-extrabold">
                            </label>
                            <label class="block font-bold text-rose-900">Audit / Reason Note
                                <input type="text" wire:model="newHolidayOverrideReason" placeholder="e.g. Tax Return Deadline" class="mt-1 w-full rounded-lg border border-rose-200 bg-white p-2 text-xs">
                            </label>
                        </div>
                    @endif
                </div>

                <button type="submit" class="w-full rounded-xl bg-[#17191F] py-2.5 text-xs font-extrabold text-[#FCDF02] hover:bg-slate-800">
                    + Save to Holiday &amp; Working Calendar
                </button>
            </form>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div class="border-b border-slate-100 bg-slate-50 px-4 py-3 flex items-center justify-between text-xs">
                <span class="font-extrabold text-slate-800">Active Calendar Entries ({{ collect($holidaysList ?? [])->reject(fn ($h) => !empty($h['is_working_day_exception']))->count() }})</span>
                <span class="text-[11px] text-slate-500">Click status button to toggle Office Open Override</span>
            </div>
            <div class="divide-y divide-slate-100 max-h-80 overflow-y-auto text-xs">
                @forelse(collect($holidaysList ?? [])->reject(fn ($h) => !empty($h['is_working_day_exception'])) as $h)
                    <div wire:key="hol-{{ $h['id'] }}" class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 hover:bg-slate-50">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-slate-900">{{ $h['title'] }}</span>
                                <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">{{ $h['display_date'] ?? $h['holiday_date'] }}</span>
                            </div>
                            @if(!empty($h['is_office_open_override']))
                                <p class="mt-0.5 text-[11px] font-bold text-rose-700">⚠️ OFFICE OPEN OVERRIDE: Absence incurs {{ $h['absent_deduction_days'] ?? 1 }} day(s) salary deduction {{ !empty($h['override_reason']) ? '(' . $h['override_reason'] . ')' : '' }}</p>
                            @else
                                <p class="mt-0.5 text-[10px] text-slate-400">Closed Holiday · Excluded from Leave Quota</p>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5">
                            @if(in_array($previewRole, ['partner', 'manager']))
                                <button type="button" wire:click="toggleHolidayOfficeOpenOverride({{ $h['id'] }})"
                                        class="rounded-lg px-2.5 py-1 text-[10px] font-extrabold transition {{ !empty($h['is_office_open_override']) ? 'bg-rose-600 text-white' : 'bg-emerald-50 text-emerald-800 border border-emerald-200' }}">
                                    {{ !empty($h['is_office_open_override']) ? 'OFFICE OPEN (Mandatory)' : 'CLOSED HOLIDAY' }}
                                </button>
                                <button type="button" wire:click="deleteHoliday({{ $h['id'] }})" class="rounded p-1 text-slate-400 hover:text-rose-600" title="Remove">✕</button>
                            @else
                                <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ !empty($h['is_office_open_override']) ? 'bg-rose-100 text-rose-800' : 'bg-emerald-50 text-emerald-800' }}">
                                    {{ !empty($h['is_office_open_override']) ? 'OFFICE OPEN (Mandatory)' : 'CLOSED HOLIDAY' }}
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="p-6 text-center text-slate-400">No holidays configured yet. (Working day exceptions are listed in the card above.)</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
