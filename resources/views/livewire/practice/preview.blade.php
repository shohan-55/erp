<div x-data="{ menuOpen: false }" class="min-h-screen bg-[#F6F7F9]">
    @if(!$firm)
        <x-auth-unknown-firm :firm-slug="$firmSlug" />
    @else
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
                    <div class="rounded-2xl border border-white/10 bg-gradient-to-br from-white/10 to-white/5 p-3.5 shadow-inner">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#FCDF02] text-xs font-black text-[#17191F] shadow-sm">
                                {{ strtoupper(substr(Auth::user()->name ?? $previewRole, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-bold text-white">{{ Auth::user()->name }}</p>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                                    <span class="truncate text-[10px] font-semibold text-amber-300">{{ $roleLabel }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-3 flex items-center justify-between border-t border-white/10 pt-2 text-[10px] text-slate-400">
                            <span class="truncate">{{ $partnerUnit }}</span>
                            <span class="rounded bg-white/10 px-1.5 py-0.5 font-mono text-[9px] text-emerald-300">SECURE NODE</span>
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
                <div class="mt-3 rounded-xl border border-white/5 bg-white/5 px-3 py-2.5">
                    <div class="text-[11px] font-bold text-white">{{ $partnerUnit }}</div>
                    <div class="mt-1 text-[10px] text-slate-400">{{ $firm['name'] ?? 'Practice' }} • Isolated Scope</div>
                </div>
            </div>

            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-5" aria-label="Workspace modules">
                <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-widest text-slate-500">Practice</p>
                @foreach([
                    ['key'=>'dashboard','title'=>'Dashboard','icon'=>'layout-dashboard'],
                    ['key'=>'attendance','title'=>'Attendance','icon'=>'calendar-check-2'],
                    ['key'=>'leave','title'=>'Leave Requests','icon'=>'calendar-days'],
                    ['key'=>'conveyance','title'=>'Conveyance Claims','icon'=>'receipt'],
                    ['key'=>'payroll','title'=>'Payroll & Allowance','icon'=>'wallet-cards'],
                    ['key'=>'team','title'=>'Team & Members','icon'=>'users'],
                    ['key'=>'notifications','title'=>'Notifications','icon'=>'bell'],
                    ['key'=>'policies','title'=>'Policy & Governance','icon'=>'shield-check'],
                    ['key'=>'settings','title'=>'Workspace Settings','icon'=>'settings-2']
                ] as $item)
                    <button type="button" wire:key="nav-{{ $item['key'] }}" wire:click="setSection('{{ $item['key'] }}')" @click="menuOpen = false" aria-label="{{ $item['title'] }}"
                            @if($section === $item['key']) aria-current="page" @endif
                            class="flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-left text-[13px] font-semibold transition {{ $section === $item['key'] ? 'bg-[#FCDF02] text-[#17191F] shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white' }}">
                        <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                        <span class="flex-1">{{ $item['title'] }}</span>
                        @if($item['key'] === 'notifications') <span class="rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $section === 'notifications' ? 'bg-[#17191F] text-[#FCDF02]' : 'bg-amber-500/20 text-amber-200' }}">3</span> @endif
                    </button>
                @endforeach
                <div class="my-4 border-t border-white/10"></div>
                <a href="{{ url('/logout') }}" class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-[12px] font-medium text-slate-400 hover:bg-white/10 hover:text-white"><i data-lucide="log-out" class="h-[18px] w-[18px]"></i>Switch Account / Sign Out</a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FCDF02] text-xs font-extrabold text-[#17191F]">{{ strtoupper(substr(Auth::user()->name ?? $previewRole,0,1)) }}</div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-bold text-white">{{ Auth::user()->name ?? $roleLabel }}</p>
                        <p class="truncate text-[10px] text-slate-400">{{ Auth::user()->email ?? ($roleLabel . ' • ' . ($firm['name'] ?? 'Practice')) }}</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-[270px]">
            <header class="sticky top-0 z-30 flex min-h-[76px] items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9">
                <button type="button" @click="menuOpen = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="h-5 w-5"></i></button>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-[11px] font-medium text-slate-400">{{ $firm ? $firm['name'] : 'Practice' }} <span class="mx-1">/</span> {{ $partnerUnit === 'unit_02' ? 'Partner Unit 02' : 'Partner Unit 01' }} <span class="mx-1">/</span> <span class="text-amber-800">{{ $sectionLabel }}</span></div>
                    <h1 class="truncate text-lg font-extrabold tracking-tight sm:text-xl">{{ $sectionLabel }}</h1>
                </div>
                <div class="hidden items-center gap-1.5 rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-[11px] font-bold text-emerald-900 sm:inline-flex">
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>LIVE PRACTICE NODE</span>
                </div>
                <button type="button" wire:click="setSection('notifications')" class="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50" aria-label="Open notifications">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-[#17191F]">3</span>
                </button>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-[#17191F] text-xs font-extrabold text-[#FCDF02]">{{ strtoupper(substr(Auth::user()->name ?? $previewRole,0,1)) }}</div>
            </header>

            <main class="mx-auto max-w-[1480px] space-y-6 px-4 py-6 sm:px-7 lg:px-9 lg:py-8">
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
                                            <option value="On time">On time</option>
                                            <option value="Late">Late</option>
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
    @endif
</div>