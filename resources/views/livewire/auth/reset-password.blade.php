@if(!$isPlatform && !$firm)
    <x-auth-unknown-firm :firm-slug="$firmSlug" />
@else
<div class="min-h-screen w-full flex flex-col justify-between bg-slate-50 font-sans selection:bg-[#FCDF02] selection:text-slate-950 py-8 px-4 sm:px-6">
    <!-- Top Header & State Switcher Bar -->
    <header class="w-full max-w-xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 mb-6">
        <a href="{{ $isPlatform ? url('/platform/login') : url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-950 px-3 py-1.5 rounded-lg hover:bg-slate-200/60 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to {{ $isPlatform ? 'Platform Login' : 'Practice Login' }}</span>
        </a>

        <!-- Design Review State Switcher Tabs -->
        <div class="flex items-center gap-1 p-1 bg-slate-200/80 rounded-xl border border-slate-300/80 text-[11px] font-semibold">
            <button
                type="button"
                wire:click="setPreviewState('form')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'form' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Active Form
            </button>
            <button
                type="button"
                wire:click="setPreviewState('mismatch')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'mismatch' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Mismatch
            </button>
            <button
                type="button"
                wire:click="setPreviewState('expired')"
                class="px-2.5 py-1 rounded-lg transition-all {{ $previewState === 'expired' ? 'bg-white text-slate-950 shadow-sm' : 'text-slate-600 hover:text-slate-950' }}"
            >
                Expired Link
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
            @if(!$isPlatform && $firm)
                <div class="flex flex-col items-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#16181D] p-2.5 shadow-md mb-2.5 flex items-center justify-center">
                        <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="w-full h-full object-contain">
                    </div>
                    <h2 class="text-sm font-bold text-slate-950 leading-tight">{{ $firm['name'] }}</h2>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">{{ $firm['designation'] }}</p>
                </div>
            @else
                <div class="flex flex-col items-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-900 text-[#FCDF02] flex items-center justify-center shadow-md mb-2.5 text-xl font-black">
                        <i data-lucide="shield" class="w-7 h-7"></i>
                    </div>
                    <h2 class="text-sm font-bold text-slate-950">DhakaWala ERP Platform</h2>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">Platform Super Admin Recovery</p>
                </div>
            @endif
        </div>

        @if($previewState === 'expired')
            <!-- ==============================================================
                 STATE: EXPIRED OR INVALID RESET LINK
                 ============================================================== -->
            <div class="text-center py-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="link-2-off" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-rose-100 text-rose-900 mb-2">
                    Security Token Expired
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Reset Link No Longer Valid
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed max-w-md mx-auto">
                    This password reset link has either expired (links remain active for 60 minutes) or has already been used. Please request a new recovery link.
                </p>

                <div class="mt-6 space-y-3 max-w-sm mx-auto">
                    <a
                        href="{{ $isPlatform ? url('/platform/forgot-password') : url('/c/' . ($firmSlug ?? 'rac') . '/forgot-password') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <i data-lucide="rotate-ccw" class="w-4 h-4"></i>
                        <span>Request New Password Reset Link</span>
                    </a>

                    <button
                        type="button"
                        wire:click="setPreviewState('form')"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition-colors"
                    >
                        <span>Return to Active Form Preview</span>
                    </button>
                </div>
            </div>

        @elseif($previewState === 'completed')
            <!-- ==============================================================
                 STATE: PASSWORD RESET COMPLETE
                 ============================================================== -->
            <div class="text-center py-4">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="check-circle-2" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-emerald-100 text-emerald-900 mb-2">
                    Password Successfully Updated
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Your Password Has Been Reset
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed max-w-md mx-auto">
                    Your new credentials are now active for this practice workspace. You may now sign in using your updated password.
                </p>

                <div class="mt-6 space-y-3 max-w-sm mx-auto">
                    <a
                        href="{{ $isPlatform ? url('/platform/login') : url('/c/' . ($firmSlug ?? 'rac') . '/login') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <i data-lucide="log-in" class="w-4 h-4"></i>
                        <span>Sign In to {{ $isPlatform ? 'Platform' : 'Firm' }} Workspace</span>
                    </a>

                    <button
                        type="button"
                        wire:click="setPreviewState('form')"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-950 hover:bg-slate-100 transition-colors"
                    >
                        <span>Reset Preview Back to Form</span>
                    </button>
                </div>
            </div>

        @else
            <!-- ==============================================================
                 STATE: ACTIVE FORM / MISMATCH PREVIEW
                 ============================================================== -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                    Set a New Password
                </h1>
                <p class="text-sm text-slate-600 mt-1.5">
                    Choose a strong, compliant password to protect your practice account.
                </p>
            </div>

            <!-- Validation Error Alert -->
            @if($validationError)
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-start gap-2.5" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 flex-shrink-0 mt-0.5"></i>
                    <div class="flex-1">
                        <span class="font-bold">Password Validation Error:</span>
                        <p class="mt-0.5 leading-relaxed">{{ $validationError }}</p>
                    </div>
                </div>
            @endif

            <!-- Form with Client-Side Password Evaluation (No Keystroke Network Transmissions) -->
            <div x-data="{
                password: '',
                passwordConfirmation: '',
                showPassword: false,
                showConfirmation: false,
                get hasMinLength() { return this.password.length >= 8; },
                get hasUppercase() { return /[A-Z]/.test(this.password); },
                get hasNumber() { return /[0-9]/.test(this.password); },
                get hasSpecialChar() { return /[^A-Za-z0-9]/.test(this.password); },
                get isMatch() { return this.password.length > 0 && this.password === this.passwordConfirmation; },
                get verifiedCount() {
                    return (this.hasMinLength ? 1 : 0) + (this.hasUppercase ? 1 : 0) + (this.hasNumber ? 1 : 0) + (this.hasSpecialChar ? 1 : 0);
                }
            }">
                <form wire:submit.prevent="submitReset" class="space-y-5" novalidate>
                    <!-- New Password Input -->
                    <div>
                        <label for="reset-password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            New Password <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                id="reset-password"
                                x-model="password"
                                wire:model="password"
                                autocomplete="new-password"
                                placeholder="••••••••••••"
                                class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                                required
                            >
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none"
                                :aria-label="showPassword ? 'Hide password' : 'Show password'"
                            >
                                <i data-lucide="eye" x-show="!showPassword" class="w-4 h-4"></i>
                                <i data-lucide="eye-off" x-show="showPassword" class="w-4 h-4" style="display: none;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Client-Side Real-Time Password Strength & Policy Requirements -->
                    <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2">
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Security Policy Requirements</span>
                            <span class="text-[11px] text-slate-500 font-mono" x-text="`${verifiedCount}/4 Verified (Local Validation)`">
                                0/4 Verified (Local Validation)
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                            <div class="flex items-center gap-2" :class="hasMinLength ? 'text-emerald-700 font-medium' : 'text-slate-500'">
                                <i data-lucide="check-circle" x-show="hasMinLength" class="w-3.5 h-3.5 text-emerald-600" style="display: none;"></i>
                                <i data-lucide="circle" x-show="!hasMinLength" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>At least 8 characters</span>
                            </div>

                            <div class="flex items-center gap-2" :class="hasUppercase ? 'text-emerald-700 font-medium' : 'text-slate-500'">
                                <i data-lucide="check-circle" x-show="hasUppercase" class="w-3.5 h-3.5 text-emerald-600" style="display: none;"></i>
                                <i data-lucide="circle" x-show="!hasUppercase" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>One uppercase letter (A-Z)</span>
                            </div>

                            <div class="flex items-center gap-2" :class="hasNumber ? 'text-emerald-700 font-medium' : 'text-slate-500'">
                                <i data-lucide="check-circle" x-show="hasNumber" class="w-3.5 h-3.5 text-emerald-600" style="display: none;"></i>
                                <i data-lucide="circle" x-show="!hasNumber" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>One numeric digit (0-9)</span>
                            </div>

                            <div class="flex items-center gap-2" :class="hasSpecialChar ? 'text-emerald-700 font-medium' : 'text-slate-500'">
                                <i data-lucide="check-circle" x-show="hasSpecialChar" class="w-3.5 h-3.5 text-emerald-600" style="display: none;"></i>
                                <i data-lucide="circle" x-show="!hasSpecialChar" class="w-3.5 h-3.5 text-slate-400"></i>
                                <span>One special symbol (!@#$%)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Password Input -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="reset-password-confirm" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Confirm New Password <span class="text-rose-500">*</span>
                            </label>
                            <template x-if="passwordConfirmation.length > 0">
                                <span class="text-xs font-semibold" :class="isMatch ? 'text-emerald-600' : 'text-rose-500'" x-text="isMatch ? '✓ Passwords match' : '✗ Does not match'"></span>
                            </template>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="check-check" class="w-4 h-4"></i>
                            </div>
                            <input
                                :type="showConfirmation ? 'text' : 'password'"
                                id="reset-password-confirm"
                                x-model="passwordConfirmation"
                                wire:model="passwordConfirmation"
                                autocomplete="new-password"
                                placeholder="••••••••••••"
                                class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                                required
                            >
                            <button
                                type="button"
                                @click="showConfirmation = !showConfirmation"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-700 focus:outline-none"
                                :aria-label="showConfirmation ? 'Hide password confirmation' : 'Show password confirmation'"
                            >
                                <i data-lucide="eye" x-show="!showConfirmation" class="w-4 h-4"></i>
                                <i data-lucide="eye-off" x-show="showConfirmation" class="w-4 h-4" style="display: none;"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/80 disabled:opacity-60 cursor-pointer"
                        >
                            <span wire:loading.remove>Save New Password & Continue</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                <span>Simulating Password Update...</span>
                            </span>
                            <i wire:loading.remove data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <!-- Footer Link -->
        <div class="mt-8 pt-5 border-t border-slate-100 text-center">
            <a href="{{ $isPlatform ? url('/platform/login') : url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-950 hover:underline inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Return to Sign In</span>
            </a>
        </div>
    </main>

    <!-- Global Footer -->
    <footer class="w-full max-w-xl mx-auto text-center text-xs text-slate-400 mt-8">
        <div>RA&C ERP SaaS • Enterprise Password Management Preview</div>
    </footer>
</div>
@endif
