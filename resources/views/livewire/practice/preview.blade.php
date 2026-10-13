<div x-data="{ menuOpen: false }" class="min-h-screen bg-[#F6F7F9]">
    @if(!$firm)
        <x-auth-unknown-firm :firm-slug="$firmSlug" />
    @else
        <div x-show="menuOpen" x-cloak @click="menuOpen = false" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden" aria-hidden="true"></div>

        <aside :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-50 flex w-[270px] flex-col bg-[#17191F] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
               aria-label="RA&C practice navigation">
            <div class="flex items-center gap-3 border-b border-white/10 px-5 py-5">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-white/10 bg-white p-1.5">
                    <img src="{{ asset($firm['logo_url']) }}" alt="RA&C brand" class="h-full w-full object-contain">
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-extrabold tracking-tight">RA&C <span class="text-[#FCDF02]">ERP</span></div>
                    <div class="text-[10px] font-semibold uppercase tracking-[0.13em] text-slate-400">Practice Workspace</div>
                </div>
                <button type="button" @click="menuOpen = false" class="rounded-lg p-2 text-slate-300 hover:bg-white/10 lg:hidden" aria-label="Close navigation"><i data-lucide="x" class="h-4 w-4"></i></button>
            </div>

            <div class="px-4 pt-4">
                <div class="rounded-xl border border-amber-400/20 bg-[#FCDF02]/10 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[#FCDF02]">Preview Role · Not a Login</p>
                    <div class="mt-2 grid grid-cols-3 gap-1" aria-label="Design persona">
                        @foreach(['partner'=>'Partner','manager'=>'Manager','student'=>'Student'] as $role => $label)
                            <button type="button" wire:key="role-{{ $role }}" wire:click="setPreviewRole('{{ $role }}')" @click="menuOpen = false"
                                    class="rounded-lg px-1.5 py-2 text-[10px] font-bold transition {{ $previewRole === $role ? 'bg-[#FCDF02] text-[#17191F]' : 'bg-white/5 text-slate-300 hover:bg-white/10' }}"
                                    @if($previewRole === $role) aria-current="true" @endif>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="mt-3 rounded-xl border border-white/5 bg-white/5 px-3 py-2.5">
                    <div class="text-[11px] font-bold text-white">Partner Unit 01 <span class="text-slate-400">(Example)</span></div>
                    <div class="mt-1 text-[10px] text-slate-400">Home Partner ownership · isolated preview</div>
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
                <a href="{{ route('firm.login', ['firmSlug'=>$firmSlug]) }}" class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-[12px] font-medium text-slate-400 hover:bg-white/10 hover:text-white"><i data-lucide="log-in" class="h-[18px] w-[18px]"></i>Login Design (Phase 02)</a>
            </nav>

            <div class="border-t border-white/10 p-4">
                <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#FCDF02] text-xs font-extrabold text-[#17191F]">{{ strtoupper(substr($previewRole,0,1)) }}</div>
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold text-white">{{ $roleLabel }} Preview</p>
                        <p class="truncate text-[10px] text-slate-400">Fictional interface · No session</p>
                    </div>
                </div>
            </div>
        </aside>

        <div class="min-w-0 lg:pl-[270px]">
            <header class="sticky top-0 z-30 flex min-h-[76px] items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9">
                <button type="button" @click="menuOpen = true" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="h-5 w-5"></i></button>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-[11px] font-medium text-slate-400">RA&C ERP <span class="mx-1">/</span> Partner Unit 01 <span class="mx-1">/</span> <span class="text-amber-800">{{ $sectionLabel }}</span></div>
                    <h1 class="truncate text-lg font-extrabold tracking-tight sm:text-xl">{{ $sectionLabel }}</h1>
                </div>
                <div class="hidden rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-bold text-amber-900 sm:inline-flex">UI DESIGN · PHASE 04–10</div>
                <button type="button" wire:click="setSection('notifications')" class="relative rounded-xl border border-slate-200 p-2.5 text-slate-600 transition hover:bg-slate-50" aria-label="Open notifications">
                    <i data-lucide="bell" class="h-[18px] w-[18px]"></i>
                    <span class="absolute -right-1 -top-1 flex h-4 w-4 items-center justify-center rounded-full bg-amber-500 text-[10px] font-bold text-[#17191F]">3</span>
                </button>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-[#17191F] text-xs font-extrabold text-[#FCDF02]">{{ strtoupper(substr($previewRole,0,1)) }}</div>
            </header>

            <main class="mx-auto max-w-[1480px] space-y-6 px-4 py-6 sm:px-7 lg:px-9 lg:py-8">
                <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-[#FFFBE5] px-4 py-3 text-xs leading-relaxed text-amber-900 sm:text-[13px]" role="status">
                    <i data-lucide="flask-conical" class="mt-0.5 h-[18px] w-[18px] shrink-0"></i>
                    <div class="flex-1">
                        <strong>Design Prototype · No Live Data.</strong> All users, balances, attendance, approvals and payroll figures are fictional examples. Role changes are UI-only; security, real authentication and tenant isolation are not implemented.
                    </div>
                </div>

                @if($notice || $error)
                    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-xs {{ $error ? 'border-rose-200 bg-rose-50 text-rose-800' : 'border-slate-200 bg-white text-slate-700' }}" role="{{ $error ? 'alert' : 'status' }}">
                        <i data-lucide="{{ $error ? 'alert-circle' : 'info' }}" class="mt-0.5 h-4 w-4 shrink-0"></i>
                        <span class="flex-1">{{ $error ?: $notice }}</span>
                        <button type="button" wire:click="dismissNotice" class="rounded p-1 hover:bg-black/5" aria-label="Dismiss message"><i data-lucide="x" class="h-4 w-4"></i></button>
                    </div>
                @endif

                @include('livewire.practice.partials.'.$section)

                <footer class="flex flex-col gap-1 border-t border-slate-200 py-5 text-[11px] text-slate-400 sm:flex-row sm:justify-between">
                    <span>Rahman Anis & Co. · Practice UI Preview · Partner Unit 01</span>
                    <span>Laravel + Livewire · No database writes</span>
                </footer>
            </main>
        </div>

        @if($showPolicyModal || $showCorrectionModal || $showClaimReviewModal || $showLeaveReviewModal)
            <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4"
                 wire:click.self="closeDialogs" wire:keydown.escape.window="closeDialogs"
                 role="dialog" aria-modal="true" aria-labelledby="practice-dialog-title">
                <div class="w-full max-w-lg rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-[10px] font-extrabold uppercase tracking-widest text-amber-800">Design Simulation · Not a Transaction</p>
                            <h2 id="practice-dialog-title" class="mt-1 text-lg font-extrabold">{{ $showPolicyModal ? 'Company Policy Proposal' : ($showCorrectionModal ? 'Attendance Correction Review' : ($showLeaveReviewModal ? 'Leave Application Review' : 'Conveyance Claim Review')) }}</h2>
                        </div>
                        <button type="button" wire:click="closeDialogs" aria-label="Close dialog" class="rounded-lg p-2 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
                    </div>
                    <p class="mt-4 text-sm leading-relaxed text-slate-600">
                        @if($showPolicyModal)
                            A change to RA&C company-wide defaults requires individual agreement from all three Partners. The proposal, distinct voting identities, effective date and immutable finalized payroll must be validated in the future backend.
                        @elseif($showCorrectionModal)
                            A correction would require a reason, authorized reviewer and audit trail. This screen does not change timestamps, daily totals, attendance or payroll.
                        @elseif($showLeaveReviewModal)
                            An assigned Manager may review their own students' requests. Final approval follows the Home Partner's policy. This preview neither changes leave balances nor sends notifications.
                        @else
                            A claim review would require assigned-scope access, proof documents and duplicate-payment checks. This screen cannot approve, reimburse or pay any claim.
                        @endif
                    </p>
                    <div class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-3 text-xs leading-relaxed text-amber-900">No database records, policy votes, permissions or transactions will be created.</div>
                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" wire:click="closeDialogs" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold hover:bg-slate-50">Cancel</button>
                        <button type="button" wire:click="acknowledgeDialog('{{ $showPolicyModal ? 'policy' : ($showCorrectionModal ? 'correction' : ($showLeaveReviewModal ? 'leave' : 'claim')) }}')" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold hover:bg-[#F3D604]">Acknowledge Preview</button>
                    </div>
                </div>
            </div>
        @endif
    @endif
</div>