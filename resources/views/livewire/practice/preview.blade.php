<div x-data="{ menuOpen: false }" class="min-h-screen bg-[#F6F7F9]">
    @if(!$firm)
        <x-auth-unknown-firm :firm-slug="$firmSlug" />
    @else
        <x-brand-theme-style :firm="$firm" />
        <div x-show="menuOpen" x-cloak @click="menuOpen = false" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-hidden="true"></div>

        <aside :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col bg-[#17191F] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
               aria-label="Practice navigation">
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white p-1.5 overflow-hidden">
                    @if(!empty($firm['logo_url']) && file_exists(public_path($firm['logo_url'])))
                        <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="h-full w-full object-contain">
                    @else
                        <span class="text-amber-500 font-extrabold text-sm">{{ strtoupper($firm['initials'] ?? 'CA') }}</span>
                    @endif
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-extrabold tracking-tight">{{ $firm['short_code'] ?? 'CA Practice' }} <span class="text-[#FCDF02]">Workspace</span></div>
                    <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-400">Powered by DhakaWala ERP</div>
                </div>
                <button type="button" @click="menuOpen = false" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 lg:hidden" aria-label="Close navigation"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            <div class="px-4 pt-4">
                @if(Auth::check() && !Auth::user()->isPlatformAdmin())
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-3.5 shadow-inner">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-[#FCDF02]">Practice Scope</span>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 px-2.5 py-0.5 text-[9px] font-bold text-emerald-300">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> Active
                            </span>
                        </div>
                        <div class="mt-2.5">
                            <p class="truncate text-xs font-black text-white">{{ $partnerUnit }}</p>
                            <p class="mt-0.5 truncate text-[10px] text-slate-400">{{ $firm['name'] ?? 'Practice' }} • {{ $firm['designation'] ?? 'Chartered Accountants' }}</p>
                        </div>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-400/20 bg-[#FCDF02]/10 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[10px] font-bold uppercase tracking-widest text-[#FCDF02]">Practice Role</p>
                            @if(Auth::check() && Auth::user()->isPlatformAdmin())
                                <span class="rounded bg-amber-400/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-200">Admin View</span>
                            @endif
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-1" aria-label="Practice persona">
                            @foreach(['partner'=>'Partner','manager'=>'Manager','student'=>'Student'] as $role => $label)
                                <button type="button" wire:key="role-{{ $role }}" wire:click="setPreviewRole('{{ $role }}')" @click="menuOpen = false"
                                        class="rounded-lg px-1.5 py-2 text-[10px] font-bold transition {{ $previewRole === $role ? 'bg-[#FCDF02] text-[#17191F]' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}"
                                        @if($previewRole === $role) aria-current="true" @endif>{{ $label }}</button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Workspace modules">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Practice Modules</p>
                @php $enabledMods = $firm['enabled_modules'] ?? []; @endphp
                @foreach(array_filter([
                    ['key'=>'dashboard','title'=>'Dashboard','icon'=>'layout-dashboard'],
                    ['key'=>'attendance','title'=>'Attendance','icon'=>'calendar-check-2'],
                    ['key'=>'leave','title'=>'Leave Requests','icon'=>'calendar-days'],
                    ['key'=>'conveyance','title'=>'Conveyance Claims','icon'=>'receipt'],
                    ['key'=>'payroll','title'=>'Payroll & Allowance','icon'=>'wallet-cards'],
                    ['key'=>'team','title'=>'Team & Members','icon'=>'users'],
                    ['key'=>'notifications','title'=>'Notifications','icon'=>'bell'],
                    ['key'=>'policies','title'=>'Policy & Governance','icon'=>'shield-check'],
                    ['key'=>'settings','title'=>'Workspace Settings','icon'=>'settings-2']
                ], fn($it) => ($enabledMods[$it['key']] ?? true) !== false) as $item)
                    <button type="button" wire:key="nav-{{ $item['key'] }}" wire:click="setSection('{{ $item['key'] }}')" @click="menuOpen = false" aria-label="{{ $item['title'] }}"
                            @if($section === $item['key']) aria-current="page" @endif
                            class="flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-left text-[13px] font-semibold transition {{ $section === $item['key'] ? 'bg-[#FCDF02] text-[#17191F] shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                        <span class="flex-1">{{ $item['title'] }}</span>
                        @if($item['key'] === 'dashboard' && ($totalPendingApprovalsCount ?? 0) > 0 && $previewRole !== 'student')
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black {{ $section === 'dashboard' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-rose-500 text-white shadow-sm' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current animate-ping"></span>{{ $totalPendingApprovalsCount }}
                            </span>
                        @elseif($item['key'] === 'leave' && ($pendingLeaveCount ?? 0) > 0 && $previewRole !== 'student')
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black {{ $section === 'leave' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-rose-500 text-white shadow-sm animate-pulse' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-white"></span>{{ $pendingLeaveCount }} New
                            </span>
                        @elseif($item['key'] === 'conveyance' && ($pendingClaimCount ?? 0) > 0 && $previewRole !== 'student')
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-black {{ $section === 'conveyance' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-amber-400 text-[#17191F] shadow-sm animate-pulse' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-[#17191F]"></span>{{ $pendingClaimCount }} New
                            </span>
                        @elseif($item['key'] === 'attendance' && ($pendingCorrectionCount ?? 0) > 0 && $previewRole !== 'student')
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-black {{ $section === 'attendance' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-rose-500/25 text-rose-200' }}">
                                {{ $pendingCorrectionCount }} Fix
                            </span>
                        @elseif($item['key'] === 'notifications')
                            @php $unreadCnt = count(array_filter($notificationsList ?? [], fn($n) => !in_array($n['id'], $readNotificationIds ?? [], true))); @endphp
                            @if($unreadCnt > 0)
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $section === 'notifications' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-amber-500/20 text-amber-200' }}">{{ $unreadCnt }}</span>
                            @endif
                        @endif
                    </button>
                @endforeach
            </nav>

            <div wire:key="sidebar-active-user-card" class="border-t border-white/10 p-3">
                <div class="flex items-center gap-3 rounded-2xl bg-white/5 p-3 hover:bg-white/10 transition">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCDF02] text-xs font-black text-[#17191F] shadow-sm overflow-hidden">
                        @if(!empty(Auth::user()->avatar_url ?? $myAvatarUrl))
                            <img src="{{ Auth::user()->avatar_url ?? $myAvatarUrl }}" alt="Avatar" class="h-full w-full object-cover">
                        @else
                            {{ strtoupper(substr(Auth::user()->name ?? $previewRole, 0, 1)) }}
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-white">{{ Auth::user()->name ?? $roleLabel }}</p>
                        <div class="mt-0.5 flex items-center gap-1.5">
                            <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                            <span class="truncate text-[10px] font-semibold text-amber-300">{{ $roleLabel }}</span>
                        </div>
                        <p class="truncate text-[10px] text-slate-400">{{ Auth::user()->email ?? ($roleLabel . ' • ' . ($firm['name'] ?? 'Practice')) }}</p>
                    </div>
                    <a href="{{ url('/logout') }}" title="Sign Out" class="rounded-xl p-2 text-slate-400 hover:bg-rose-500/20 hover:text-rose-300 transition">
                        <i data-lucide="log-out" class="h-4 w-4"></i>
                    </a>
                </div>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-[270px]">
            <header class="sticky top-0 z-30 flex min-h-[76px] items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9">
                <button type="button" @click="menuOpen = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="h-5 w-5"></i></button>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-[11px] font-medium text-slate-400">{{ $firm ? $firm['name'] : 'Practice' }} <span class="mx-1">/</span> {{ $partnerUnit }} <span class="mx-1">/</span> <span class="text-amber-800">{{ $sectionLabel }}</span></div>
                    <h1 class="truncate text-lg font-extrabold tracking-tight sm:text-xl">{{ $sectionLabel }}</h1>
                </div>
                @if($previewRole !== 'student' && (($pendingLeaveCount ?? 0) > 0 || ($pendingClaimCount ?? 0) > 0))
                    <div class="hidden md:flex items-center gap-2">
                        @if(($pendingLeaveCount ?? 0) > 0)
                            <button type="button" wire:click="setSection('leave')" class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-extrabold text-rose-800 shadow-2xs hover:bg-rose-100 transition cursor-pointer" title="View Unread Leave Applications">
                                <span class="h-2 w-2 rounded-full bg-rose-600 animate-ping"></span>
                                <span>📩 {{ $pendingLeaveCount }} Leave Request{{ $pendingLeaveCount > 1 ? 's' : '' }}</span>
                            </button>
                        @endif
                        @if(($pendingClaimCount ?? 0) > 0)
                            <button type="button" wire:click="setSection('conveyance')" class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-1.5 text-[11px] font-extrabold text-amber-950 shadow-2xs hover:bg-amber-100 transition cursor-pointer" title="View Unread Conveyance Claims">
                                <span class="h-2 w-2 rounded-full bg-amber-500 animate-ping"></span>
                                <span>🧾 {{ $pendingClaimCount }} Conveyance Claim{{ $pendingClaimCount > 1 ? 's' : '' }}</span>
                            </button>
                        @endif
                    </div>
                @endif
                <div class="hidden items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-900 sm:inline-flex">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>PRODUCTION ACTIVE</span>
                </div>
                <button type="button" wire:click="setSection('notifications')" class="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50" aria-label="Open notifications">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    @php $hdrUnread = count(array_filter($notificationsList ?? [], fn($n) => !in_array($n['id'], $readNotificationIds ?? [], true))); @endphp @if($hdrUnread > 0)<span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-[#17191F]">{{ $hdrUnread }}</span>@endif
                </button>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-[#17191F] text-xs font-extrabold text-[#FCDF02] overflow-hidden">@if(!empty(Auth::user()->avatar_url ?? $myAvatarUrl))<img src="{{ Auth::user()->avatar_url ?? $myAvatarUrl }}" alt="Avatar" class="h-full w-full object-cover">@else{{ strtoupper(substr(Auth::user()->name ?? $previewRole,0,1)) }}@endif</div>
            </header>

            <main class="mx-auto max-w-[1480px] space-y-6 px-4 py-6 sm:px-7 lg:px-9 lg:py-8">
                @if(!empty($subscriptionAlert) && $previewRole === 'partner')
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border-2 border-amber-400 bg-amber-50 px-5 py-3.5 text-xs text-amber-950 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="rounded-lg bg-[#17191F] px-2.5 py-1 text-[10px] font-extrabold text-[#FCDF02]">DHAKAWALA BILLING ({{ $subscriptionAlert['status'] }})</span>
                            <span class="font-bold">Subscription Renewal Notice: {{ $subscriptionAlert['cycle'] }} Fee ৳{{ number_format($subscriptionAlert['fee'], 0) }} · Expires {{ $subscriptionAlert['expires_at'] }}.</span>
                        </div>
                        <span class="text-[11px] font-semibold text-amber-900">Contact DhakaWala Support to settle invoice</span>
                    </div>
                @endif
                @if($notice || $error)
                    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-xs {{ $error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-700' }}" role="{{ $error ? 'alert' : 'status' }}">
                        <i data-lucide="{{ $error ? 'alert-circle' : 'info' }}" class="mt-0.5 h-4 w-4 shrink-0"></i>
                        <span class="flex-1">{{ $error ?: $notice }}</span>
                        <button type="button" wire:click="dismissNotice" class="rounded p-1 hover:bg-black/5" aria-label="Dismiss message"><i data-lucide="x" class="h-4 w-4"></i></button>
                    </div>
                @endif

                @include('livewire.practice.partials.'.$section)

                <footer class="flex flex-col gap-1 border-t border-slate-200 py-5 text-[11px] text-slate-400 sm:flex-row sm:justify-between">
                    <span>{{ $firm['name'] ?? 'CA Practice' }} • {{ $firm['designation'] ?? 'Chartered Accountants' }} • Powered by DhakaWala ERP</span>
                    <span>All Systems Live & Secure</span>
                </footer>
            </main>
        </div>

        @if($showPolicyModal || $showCorrectionModal || $showClaimReviewModal || $showLeaveReviewModal)
            <div class="fixed inset-0 z-[70] overflow-y-auto bg-slate-950/60 p-4 sm:p-6 backdrop-blur-sm"
                 wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs"
                 role="dialog" aria-modal="true" aria-labelledby="practice-dialog-title">
                <div class="flex min-h-full items-center justify-center">
                    <div class="w-full max-w-lg my-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Practice Governance Workflow</p>
                                <h2 id="practice-dialog-title" class="mt-1 text-lg font-extrabold">{{ $showPolicyModal ? 'Company Policy Proposal' : ($showCorrectionModal ? 'Attendance Correction Review' : ($showLeaveReviewModal ? 'Leave Application Review' : 'Conveyance Claim Review')) }}</h2>
                            </div>
                            <button type="button" wire:click="closeDialogs" aria-label="Close dialog" class="rounded-lg p-2 hover:bg-slate-100 transition"><i data-lucide="x" class="h-4 w-4"></i></button>
                        </div>
                        <p class="mt-4 text-sm leading-relaxed text-slate-600">
                            @if($showPolicyModal)
                                Company policy updates require partner authorization. Adjustments to office hours, grace periods, and conveyance thresholds will apply across active scopes.
                            @elseif($showCorrectionModal)
                                Attendance adjustments and proxy entries require authorized partner/manager verification with recorded audit remarks.
                            @elseif($showLeaveReviewModal)
                                Supervisor review of student leave request. Upon approval, balances and attendance schedules are synchronized.
                            @else
                                Review and audit supporting voucher receipts for client site audit engagement conveyance claim.
                            @endif
                        </p>

                        @if($showCorrectionModal)
                            <div class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-xs">
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block font-bold text-slate-700">Select Student
                                        <select wire:model="selectedStudentCode" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                            @foreach($allRows as $sRow)
                                                <option value="{{ $sRow['id'] }}">{{ $sRow['id'] }} — {{ $sRow['name'] }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <label class="block font-bold text-slate-700">Action Type
                                        <select wire:model="editModeType" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                            <option value="edit">Edit Late / In-Out Time</option>
                                            <option value="proxy">Give Proxy Attendance</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="grid grid-cols-3 gap-2">
                                    <label class="block font-bold text-slate-700">Check-in Time
                                        <input type="text" wire:model="editCheckinTime" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs" placeholder="10:00">
                                    </label>
                                    <label class="block font-bold text-slate-700">Check-out Time
                                        <input type="text" wire:model="editCheckoutTime" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs" placeholder="18:00">
                                    </label>
                                    <label class="block font-bold text-slate-700">Status
                                        <select wire:model="editStatus" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs">
                                            <option value="Right Time">Right Time (&le; {{ $officeStart }})</option>
                                            <option value="On time">On time</option>
                                            <option value="Late">Late (Grace Window)</option>
                                            <option value="Late Present">Late Present (&gt; {{ $graceEndFormatted ?? '09:40' }})</option>
                                            <option value="Incomplete">Incomplete</option>
                                        </select>
                                    </label>
                                </div>
                                <label class="block font-bold text-slate-700">Location Tag (In/Out)
                                    <input type="text" wire:model="editLocationName" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs" placeholder="EDB Trade Centre, Karwan Bazar, Dhaka">
                                </label>
                                <label class="block font-bold text-slate-700">Audit Remarks / Reason
                                    <input type="text" wire:model="editRemarks" class="mt-1 w-full rounded-lg border border-slate-200 bg-white p-2 text-xs" placeholder="e.g., Partner approved late waiver / proxy">
                                </label>
                            </div>
                        @endif
                        <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3 text-xs leading-relaxed text-slate-600">All submissions are cryptographically associated with your identity and audited for compliance.</div>
                        <div class="mt-5 flex justify-end gap-2">
                            <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold hover:bg-slate-50 transition">Cancel</button>
                            <button type="button" wire:click="acknowledgeDialog('{{ $showPolicyModal ? 'policy' : ($showCorrectionModal ? 'correction' : ($showLeaveReviewModal ? 'leave' : 'claim')) }}')" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#16181D] hover:bg-[#F3D604] transition">Confirm & Save</button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if($showAddNewClientModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-in fade-in duration-200" role="dialog" aria-modal="true" aria-labelledby="add-client-title">
                <div class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2.5">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 text-amber-900">
                                <i data-lucide="building-2" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <h3 id="add-client-title" class="text-base font-extrabold text-slate-900">Add New Audit Client</h3>
                                <p class="text-xs text-slate-500">Register new client for attendance &amp; conveyance</p>
                            </div>
                        </div>
                        <button type="button" wire:click="closeDialogs" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                            <i data-lucide="x" class="h-5 w-5"></i>
                        </button>
                    </div>

                    <form wire:submit.prevent="saveNewClientSite" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700">Client / Company Name <span class="text-rose-500">*</span></label>
                            <input type="text" wire:model="newClientName" placeholder="e.g. Grameenphone Ltd., Summit Power" required autofocus class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm outline-none focus:border-amber-400 focus:bg-white">
                        </div>

                        <div>
                            <label class="block text-xs font-bold text-slate-700">Client Site Location / Address (Optional)</label>
                            <input type="text" wire:model="newClientLocation" placeholder="e.g. Gulshan-2, Dhaka or CEPZ, Chittagong" class="mt-1.5 w-full rounded-xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-sm outline-none focus:border-amber-400 focus:bg-white">
                        </div>

                        <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-3 text-xs leading-relaxed text-amber-950">
                            This client will be saved to your firm's client register and will immediately be available in both Client Site Check-in and Conveyance Claims.
                        </div>

                        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
                            <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold hover:bg-slate-50 transition">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604] transition">
                                <i data-lucide="plus" class="h-4 w-4"></i>
                                Save &amp; Select Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
   
        @if($activePopupNotification)
            <div class="fixed inset-0 z-[85] flex items-center justify-center p-4 bg-slate-950/65 backdrop-blur-sm" role="dialog" aria-modal="true" aria-labelledby="popup-notif-title">
                <div class="w-full max-w-md rounded-2xl border-2 border-[#FCDF02] bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]">
                                <i data-lucide="bell-ring" class="h-5 w-5"></i>
                            </div>
                            <div>
                                <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">PRACTICE POPUP NOTICE</p>
                                <h3 id="popup-notif-title" class="mt-0.5 text-base font-extrabold text-slate-900">{{ $activePopupNotification["title"] ?? "Important Notice" }}</h3>
                            </div>
                        </div>
                        <button type="button" wire:click="dismissPopupNotification" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100">
                            <i data-lucide="x" class="h-4 w-4"></i>
                        </button>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-700">{{ $activePopupNotification["message"] ?? "" }}</p>
                    <p class="mt-3 text-[11px] font-semibold text-slate-400">From: {{ $activePopupNotification["sender_name"] ?? "Practice Management" }} · {{ $activePopupNotification["age"] ?? "Just now" }}</p>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="dismissPopupNotification" class="inline-flex items-center gap-1.5 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                            <i data-lucide="check" class="h-4 w-4"></i> Mark Read &amp; Dismiss
                        </button>
                    </div>
                </div>
            </div>
        @endif

        @if($showCheckinCelebration && $celebrationData)
            <div class="fixed inset-0 z-[95] flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-md"
                 wire:click.self="dismissCelebration" role="dialog" aria-modal="true">
                <style>
                    @keyframes erp-pop-in {
                        0% { transform: scale(0.75); opacity: 0; }
                        70% { transform: scale(1.04); opacity: 1; }
                        100% { transform: scale(1); opacity: 1; }
                    }
                    @keyframes erp-check-draw {
                        0% { stroke-dashoffset: 100; }
                        100% { stroke-dashoffset: 0; }
                    }
                    @keyframes erp-pulse-ring {
                        0% { transform: scale(0.8); opacity: 0.8; }
                        100% { transform: scale(1.85); opacity: 0; }
                    }
                    .erp-anim-card { animation: erp-pop-in 0.45s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
                    .erp-anim-check { stroke-dasharray: 100; stroke-dashoffset: 100; animation: erp-check-draw 0.65s 0.2s ease-out forwards; }
                    .erp-anim-ring { animation: erp-pulse-ring 1.6s cubic-bezier(0, 0, 0.2, 1) infinite; }
                </style>
                <div class="erp-anim-card relative w-full max-w-md overflow-hidden rounded-3xl border-2 border-white/20 bg-[#11141B] p-7 text-center text-white shadow-2xl">
                    @php
                        $cTheme = $celebrationData['badge_theme'] ?? 'emerald';
                        $ringHex = $cTheme === 'emerald' ? '#10B981' : ($cTheme === 'amber' ? '#F59E0B' : '#F43F5E');
                    @endphp
                    <div class="relative mx-auto mb-5 flex h-24 w-24 items-center justify-center">
                        <span class="erp-anim-ring absolute inset-0 rounded-full" style="border: 3px solid {{ $ringHex }};"></span>
                        <div class="relative z-10 flex h-20 w-20 items-center justify-center rounded-full shadow-lg" style="background-color: {{ $ringHex }};">
                            <svg class="h-11 w-11 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                <polyline class="erp-anim-check" points="20 6 9 17 4 12"></polyline>
                            </svg>
                        </div>
                    </div>

                    <span class="inline-flex items-center gap-1.5 rounded-full px-3.5 py-1 text-xs font-black uppercase tracking-wider" style="background-color: {{ $ringHex }}25; color: {{ $ringHex }}; border: 1px solid {{ $ringHex }}60;">
                        ✨ {{ $celebrationData['status'] ?? 'Right Time' }}
                    </span>

                    <h3 class="mt-3 text-2xl font-black tracking-tight text-white">{{ $celebrationData['title'] ?? 'Attendance Verified!' }}</h3>
                    <p class="mt-1 text-xs text-slate-300">{{ $celebrationData['subtitle'] ?? '' }}</p>

                    <div class="mt-5 rounded-2xl border border-white/10 bg-white/5 p-4 text-left text-xs space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Recorded Time</span>
                            <strong class="font-mono text-sm text-white">{{ $celebrationData['time'] ?? '' }}</strong>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400">Duty Mode</span>
                            <strong class="text-white">{{ $celebrationData['mode'] ?? 'Office' }}</strong>
                        </div>
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-slate-400 shrink-0">GPS Location</span>
                            <strong class="text-right text-emerald-300">{{ $celebrationData['location'] ?? 'Verified' }}</strong>
                        </div>
                    </div>

                    <button type="button" wire:click="dismissCelebration" class="mt-6 w-full rounded-2xl bg-[#FCDF02] py-3 text-xs font-black uppercase tracking-wider text-[#17191F] shadow-lg transition hover:brightness-105">
                        Continue to Workspace
                    </button>
                </div>
            </div>
        @endif
    @endif
</div>