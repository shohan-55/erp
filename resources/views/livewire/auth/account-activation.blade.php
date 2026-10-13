<div class="min-h-screen w-full flex flex-col justify-between bg-slate-50 font-sans selection:bg-[#FCDF02] selection:text-slate-950 py-8 px-4 sm:px-6">
    <!-- Top Header & State Switcher -->
    <header class="w-full max-w-xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 mb-6">
        <a href="{{ url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-950 px-3 py-1.5 rounded-lg hover:bg-slate-200/60 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Firm Login</span>
        </a>

        <!-- Design Preview State Switcher Tabs -->
        <div class="flex items-center gap-1 p-1 bg-slate-200/80 rounded-xl border border-slate-300/80 text-[11px] font-semibold">
            <button
                type="button"
                wire:click="setPreviewState('valid')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'valid' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Valid Invitation
            </button>
            <button
                type="button"
                wire:click="setPreviewState('expired')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'expired' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Expired
            </button>
            <button
                type="button"
                wire:click="setPreviewState('used')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'used' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Already Used
            </button>
            <button
                type="button"
                wire:click="setPreviewState('completed')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'completed' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Complete
            </button>
        </div>
    </header>

    <!-- Main Card -->
    <main class="w-full max-w-xl mx-auto bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-xl relative overflow-hidden my-auto">
        <!-- Top Accent Strip -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-[#16181D]"></div>

        <!-- Brand Emblem -->
        <div class="flex items-center justify-center mb-6">
            @if($firm)
                <div class="flex flex-col items-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#16181D] p-2.5 shadow-md mb-2.5 flex items-center justify-center">
                        <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="w-full h-full object-contain">
                    </div>
                    <h2 class="text-sm font-bold text-slate-950 leading-tight">{{ $firm['name'] }}</h2>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">{{ $firm['designation'] }}</p>
                </div>
            @endif
        </div>

        @if($previewState === 'expired')
            <!-- ==============================================================
                 STATE: INVITATION EXPIRED
                 ============================================================== -->
            <div class="text-center py-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="clock" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-amber-100 text-amber-900 mb-2">
                    Invitation Expired
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Invitation Window Closed
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed max-w-md mx-auto">
                    This account activation link has expired (firm invitation tokens remain valid for {{ $invitedUser['expires_in'] }}). Please contact your supervising Partner or Audit Manager to request a new invitation link.
                </p>

                <div class="mt-6 space-y-3 max-w-sm mx-auto">
                    <a
                        href="{{ url('/c/' . ($firmSlug ?? 'rac') . '/login') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Return to Firm Sign In</span>
                    </a>

                    <button
                        type="button"
                        wire:click="setPreviewState('valid')"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition-colors"
                    >
                        <span>Return to Valid Invitation Preview</span>
                    </button>
                </div>
            </div>

        @elseif($previewState === 'used')
            <!-- ==============================================================
                 STATE: ALREADY ACTIVATED
                 ============================================================== -->
            <div class="text-center py-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-blue-50 border border-blue-200 text-blue-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="user-check" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-blue-100 text-blue-900 mb-2">
                    Already Activated
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Account Already Established
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed max-w-md mx-auto">
                    This practice invitation has already been accepted and activated. You can sign in directly to your assigned workspace with your established credentials.
                </p>

                <div class="mt-6 space-y-3 max-w-sm mx-auto">
                    <a
                        href="{{ url('/c/' . ($firmSlug ?? 'rac') . '/login') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        <span>Sign In to Your Workspace</span>
                    </a>

                    <button
                        type="button"
                        wire:click="setPreviewState('valid')"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition-colors"
                    >
                        <span>Return to Valid Invitation Preview</span>
                    </button>
                </div>
            </div>

        @elseif($previewState === 'completed')
            <!-- ==============================================================
                 STATE: ACTIVATION COMPLETED
                 ============================================================== -->
            <div class="text-center py-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="sparkles" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-emerald-100 text-emerald-900 mb-2">
                    Account Activated (Preview)
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Welcome to Rahman Anis & Co.
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed max-w-md mx-auto">
                    Welcome aboard, <strong class="text-slate-900">{{ $invitedUser['name'] }}</strong>! Your practice account setup has been simulated. You may now sign in to begin tracking attendance, log conveyance, and view assignments.
                </p>

                <div class="mt-6 space-y-3 max-w-sm mx-auto">
                    <a
                        href="{{ url('/c/' . ($firmSlug ?? 'rac') . '/login') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                        <span>Proceed to Practice Sign In</span>
                    </a>

                    <button
                        type="button"
                        wire:click="setPreviewState('valid')"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition-colors"
                    >
                        <span>Reset Preview Back to Form</span>
                    </button>
                </div>
            </div>

        @else
            <!-- ==============================================================
                 STATE: VALID INVITATION & ACCOUNT SETUP FORM
                 ============================================================== -->
            <div class="text-center mb-6">
                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-amber-50 text-amber-800 border border-amber-200 mb-2">
                    <i data-lucide="mail" class="w-3.5 h-3.5 text-amber-600"></i>
                    Official Firm Invitation
                </span>
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                    Activate Your Practice Account
                </h1>
                <p class="text-sm text-slate-600 mt-1.5">
                    Set up your secure password to complete your onboarding into the firm workspace.
                </p>
            </div>

            <!-- Invited Person Profile Badge -->
            <div class="mb-6 p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-[#16181D] to-slate-800 text-white font-bold text-sm flex items-center justify-center shadow-sm">
                        FT
                    </div>
                    <div>
                        <div class="font-bold text-sm text-slate-950">{{ $invitedUser['name'] }}</div>
                        <div class="text-xs text-slate-500 font-mono">{{ $invitedUser['email'] }}</div>
                    </div>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-900 border border-amber-300">
                        {{ $invitedUser['role'] }}
                    </span>
                    <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ $invitedUser['student_reg'] }}</div>
                </div>
            </div>

            <!-- Validation Error Alert -->
            @if($validationError)
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-2.5" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5"></i>
                    <div class="flex-1">
                        <span class="font-bold">Activation Warning:</span>
                        <p class="mt-0.5 leading-relaxed">{{ $validationError }}</p>
                    </div>
                </div>
            @endif

            <!-- Setup Password Form -->
            <form wire:submit.prevent="submitActivation" class="space-y-4" novalidate>
                <!-- Create Password -->
                <div>
                    <label for="activate-password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Create Password <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="lock" class="w-4 h-4"></i>
                        </div>
                        <input
                            type="{{ $showPassword ? 'text' : 'password' }}"
                            id="activate-password"
                            wire:model.live.debounce.150ms="password"
                            autocomplete="new-password"
                            placeholder="••••••••••••"
                            class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                            required
                        >
                        <button
                            type="button"
                            wire:click="togglePassword"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none"
                            aria-label="{{ $showPassword ? 'Hide password' : 'Show password' }}"
                        >
                            <i data-lucide="{{ $showPassword ? 'eye-off' : 'eye' }}" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Password Checklist -->
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1.5">
                    <div class="text-[10px] font-bold text-slate-700 uppercase tracking-wider mb-1">Practice Security Requirements</div>
                    <div class="grid grid-cols-2 gap-1.5 text-[11px]">
                        <div class="flex items-center gap-1.5 {{ $this->hasMinLength ? 'text-emerald-700 font-medium' : 'text-slate-500' }}">
                            <i data-lucide="{{ $this->hasMinLength ? 'check-circle' : 'circle' }}" class="w-3 h-3 {{ $this->hasMinLength ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            <span>Min 8 characters</span>
                        </div>
                        <div class="flex items-center gap-1.5 {{ $this->hasUppercase ? 'text-emerald-700 font-medium' : 'text-slate-500' }}">
                            <i data-lucide="{{ $this->hasUppercase ? 'check-circle' : 'circle' }}" class="w-3 h-3 {{ $this->hasUppercase ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            <span>Uppercase (A-Z)</span>
                        </div>
                        <div class="flex items-center gap-1.5 {{ $this->hasNumber ? 'text-emerald-700 font-medium' : 'text-slate-500' }}">
                            <i data-lucide="{{ $this->hasNumber ? 'check-circle' : 'circle' }}" class="w-3 h-3 {{ $this->hasNumber ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            <span>Number (0-9)</span>
                        </div>
                        <div class="flex items-center gap-1.5 {{ $this->hasSpecialChar ? 'text-emerald-700 font-medium' : 'text-slate-500' }}">
                            <i data-lucide="{{ $this->hasSpecialChar ? 'check-circle' : 'circle' }}" class="w-3 h-3 {{ $this->hasSpecialChar ? 'text-emerald-600' : 'text-slate-400' }}"></i>
                            <span>Special character</span>
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="activate-password-confirm" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                            Confirm Password <span class="text-rose-500">*</span>
                        </label>
                        @if(!empty($passwordConfirmation))
                            <span class="text-xs font-semibold {{ $this->isMatch ? 'text-emerald-600' : 'text-rose-500' }}">
                                {{ $this->isMatch ? '✓ Passwords match' : '✗ Does not match' }}
                            </span>
                        @endif
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="check-check" class="w-4 h-4"></i>
                        </div>
                        <input
                            type="{{ $showConfirmation ? 'text' : 'password' }}"
                            id="activate-password-confirm"
                            wire:model.live.debounce.150ms="passwordConfirmation"
                            autocomplete="new-password"
                            placeholder="••••••••••••"
                            class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                            required
                        >
                        <button
                            type="button"
                            wire:click="toggleConfirmation"
                            class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none"
                            aria-label="{{ $showConfirmation ? 'Hide password confirmation' : 'Show password confirmation' }}"
                        >
                            <i data-lucide="{{ $showConfirmation ? 'eye-off' : 'eye' }}" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- Terms & Audit Confidentiality Acknowledgment -->
                <div class="pt-1">
                    <label class="flex items-start gap-2.5 cursor-pointer select-none">
                        <input
                            type="checkbox"
                            wire:model.defer="termsAccepted"
                            class="mt-0.5 w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-[#FCDF02] focus:ring-offset-0"
                        >
                        <span class="text-xs text-slate-700 leading-relaxed">
                            I acknowledge and agree to comply with the <strong>Rahman Anis & Co. Professional Code of Ethics</strong> and strict ICAB audit client confidentiality guidelines.
                        </span>
                    </label>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/80 disabled:opacity-60 cursor-pointer"
                    >
                        <span wire:loading.remove>Complete Account Activation</span>
                        <span wire:loading class="inline-flex items-center gap-2">
                            <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                            <span>Activating Account...</span>
                        </span>
                        <i wire:loading.remove data-lucide="arrow-right" class="w-4 h-4"></i>
                    </button>
                </div>
            </form>
        @endif

        <!-- Footer Link -->
        <div class="mt-8 pt-5 border-t border-slate-100 text-center">
            <a href="{{ url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-950 hover:underline inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Already have active credentials? Sign In</span>
            </a>
        </div>
    </main>

    <!-- Global Footer -->
    <footer class="w-full max-w-xl mx-auto text-center text-xs text-slate-400 mt-8">
        <div>RA&C ERP SaaS • Invited Member Onboarding Preview</div>
    </footer>
</div>
