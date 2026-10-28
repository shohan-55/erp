@if(!$isPlatform && !$firm)
    <x-auth-unknown-firm :firm-slug="$firmSlug" />
@else
<div class="min-h-screen w-full flex flex-col justify-between bg-slate-50 font-sans selection:bg-[#FCDF02] selection:text-slate-950 py-8 px-4 sm:px-6">
    <!-- Top Header -->
    <header class="w-full max-w-lg mx-auto flex items-center justify-between mb-8">
        <a href="{{ $isPlatform ? url('/platform/login') : url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="inline-flex items-center gap-2 text-xs font-semibold text-slate-600 hover:text-slate-950 px-3 py-1.5 rounded-lg hover:bg-slate-200/60 transition-colors">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to {{ $isPlatform ? 'Platform Login' : 'Practice Login' }}</span>
        </a>

        <div class="text-xs text-slate-500 font-medium">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-semibold text-[11px]">
                <i data-lucide="shield" class="w-3 h-3 text-amber-600"></i>
                Account Recovery Preview
            </span>
        </div>
    </header>

    <!-- Main Card -->
    <main class="w-full max-w-lg mx-auto bg-white border border-slate-200 rounded-3xl p-6 sm:p-10 shadow-xl relative overflow-hidden my-auto">
        <!-- Top Accent Bar -->
        <div class="absolute top-0 left-0 right-0 h-1.5 bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-[#16181D]"></div>

        <!-- Brand Emblem -->
        <div class="flex items-center justify-center mb-6">
            @if(!$isPlatform && $firm)
                <div class="flex flex-col items-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#16181D] p-2.5 shadow-md mb-3 flex items-center justify-center">
                        <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="w-full h-full object-contain">
                    </div>
                    <h2 class="text-sm font-bold text-slate-950 leading-tight">{{ $firm['name'] }}</h2>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">{{ $firm['designation'] }}</p>
                </div>
            @else
                <div class="flex flex-col items-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-slate-900 text-[#FCDF02] flex items-center justify-center shadow-md mb-3 text-xl font-black">
                        <i data-lucide="shield" class="w-7 h-7"></i>
                    </div>
                    <h2 class="text-sm font-bold text-slate-950">DhakaWala ERP Platform</h2>
                    <p class="text-[11px] font-medium text-slate-500 uppercase tracking-widest">Platform Super Admin Recovery</p>
                </div>
            @endif
        </div>

        @if(!$submitted)
            <!-- ==============================================================
                 STATE 1: RECOVERY REQUEST FORM
                 ============================================================== -->
            <div class="text-center mb-6">
                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950">
                    Forgot your password?
                </h1>
                <p class="text-sm text-slate-600 mt-2 leading-relaxed">
                    Enter your registered email address to request password recovery.
                </p>
            </div>

            <!-- Privacy Policy Callout -->
            <div class="mb-6 p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-600 flex items-start gap-2.5">
                <i data-lucide="info" class="w-4 h-4 text-amber-600 flex-shrink-0 mt-0.5"></i>
                <div class="leading-relaxed">
                    <span class="font-semibold text-slate-900">Privacy Safeguard:</span> For security purposes, recovery responses do not confirm or deny whether an account exists in our practice records.
                </div>
            </div>

            <!-- Validation Error Alert -->
            @if($validationError)
                <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-xs flex items-center gap-2.5" role="alert">
                    <i data-lucide="alert-circle" class="w-4 h-4 text-rose-600 flex-shrink-0"></i>
                    <span>{{ $validationError }}</span>
                </div>
            @endif

            <!-- Form -->
            <form wire:submit.prevent="submitRecovery" class="space-y-4" novalidate>
                <div>
                    <label for="recovery-email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                        Registered Email Address <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                            <i data-lucide="mail" class="w-4 h-4"></i>
                        </div>
                        <input
                            type="email"
                            id="recovery-email"
                            wire:model.defer="email"
                            autocomplete="email"
                            placeholder="{{ $isPlatform ? 'operator@dhakawala.com' : 'partner@' . ($firm['custom_domain'] ?? ($firmSlug ? $firmSlug . '.dhakawala.com' : 'practice.com')) }}"
                            class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                            required
                        >
                    </div>
                </div>

                <button
                    type="submit"
                    wire:loading.attr="disabled"
                    class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/80 disabled:opacity-60 cursor-pointer"
                >
                    <span wire:loading.remove>Submit Password Recovery Request</span>
                    <span wire:loading class="inline-flex items-center gap-2">
                        <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                        <span>Processing Recovery Request...</span>
                    </span>
                    <i wire:loading.remove data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>

            <!-- Reviewer Quick-Fill & State Helper -->
            <div class="mt-6 pt-5 border-t border-slate-100 flex items-center justify-between text-xs">
                <button
                    type="button"
                    wire:click="fillDemoEmail"
                    class="text-amber-700 hover:text-amber-900 font-semibold hover:underline inline-flex items-center gap-1"
                >
                    <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                    <span>Fill Sample Email</span>
                </button>

                <button
                    type="button"
                    wire:click="$set('submitted', true)"
                    class="text-slate-600 hover:text-slate-900 font-medium hover:underline inline-flex items-center gap-1"
                >
                    <span>Preview Confirmation State →</span>
                </button>
            </div>
        @else
            <!-- ==============================================================
                 STATE 2: CONFIRMATION / PRIVACY-FIRST RESPONSE
                 ============================================================== -->
            <div class="text-center py-2">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center shadow-inner">
                    <i data-lucide="mail-check" class="w-8 h-8"></i>
                </div>

                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-emerald-100 text-emerald-900 mb-2">
                    Request Processed
                </span>

                <h1 class="text-2xl font-extrabold tracking-tight text-slate-950 mt-1">
                    Check your email
                </h1>

                <p class="text-sm text-slate-600 mt-3 leading-relaxed">
                    If an active account is associated with <strong class="text-slate-900">{{ $email ?: 'your email address' }}</strong>, password recovery instructions have been sent.
                </p>

                <div class="mt-5 p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 text-left leading-relaxed">
                    <p class="font-bold text-slate-900 mb-1">Security Instructions</p>
                    <p>Single-use cryptographically signed reset tokens are dispatched directly to your registered inbox to protect account privacy.</p>
                </div>

                <div class="mt-6 space-y-2.5">
                    <a
                        href="{{ $isPlatform ? url('/platform/reset-password') : url('/c/' . ($firmSlug ?? 'rac') . '/reset-password') }}"
                        class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-md transition-all"
                    >
                        <span>Set New Password</span>
                        <i data-lucide="arrow-right" class="w-4 h-4"></i>
                    </a>

                    <button
                        type="button"
                        wire:click="resetForm"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 hover:text-slate-950 hover:bg-slate-100 border border-slate-200 transition-colors"
                    >
                        <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i>
                        <span>Try Another Email Address</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- Card Footer -->
        <div class="mt-8 pt-5 border-t border-slate-100 text-center">
            <a href="{{ $isPlatform ? url('/platform/login') : url('/c/' . ($firmSlug ?? 'rac') . '/login') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-950 hover:underline inline-flex items-center gap-1">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>Return to Sign In</span>
            </a>
        </div>
    </main>

    <footer class="w-full max-w-lg mx-auto text-center text-xs text-slate-400 mt-8">
        <div>DhakaWala ERP SaaS • Privacy-Preserving Account Recovery</div>
    </footer>
</div>
@endif
