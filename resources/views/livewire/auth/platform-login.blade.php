<div class="min-h-screen w-full flex flex-col justify-between bg-slate-950 text-slate-100 font-sans selection:bg-[#FCDF02] selection:text-slate-950">
    <!-- Top Global Header -->
    <header class="w-full border-b border-slate-800/80 bg-slate-900/60 backdrop-blur px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 via-[#FCDF02] to-amber-300 flex items-center justify-center text-slate-950 shadow-md font-extrabold text-base">
                D
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="font-extrabold text-sm tracking-tight text-white">ERP SaaS Platform</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400/10 text-amber-300 border border-amber-400/20 uppercase tracking-wider">
                        Infrastructure
                    </span>
                </div>
                <div class="text-[11px] text-slate-400">Powered by DhakaWala Central Cloud</div>
            </div>
        </div>

        <div class="flex items-center gap-3 text-xs">
            <a href="{{ url('/c/rac/login') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-300 hover:text-white bg-slate-800/80 hover:bg-slate-700 border border-slate-700 transition-colors">
                <i data-lucide="building" class="w-3.5 h-3.5 text-[#FCDF02]"></i>
                <span>CA Firm Login (RA&C)</span>
            </a>
            <a href="{{ url('/c/rac/login') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-400 hover:text-white transition-colors">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i>
                <span>CA Firm Portal</span>
            </a>
        </div>
    </header>

    <!-- Main Platform Console Card -->
    <main class="flex-1 flex items-center justify-center p-4 sm:p-6 my-auto">
        <div class="w-full max-w-lg bg-slate-900/90 border border-slate-800 rounded-3xl p-6 sm:p-10 shadow-2xl backdrop-blur relative overflow-hidden">
            <!-- Decorative Accent Lines -->
            <div class="absolute -top-24 -right-24 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-24 -left-24 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10">
                <!-- Platform Shield Header -->
                <div class="text-center mb-8">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-[#FCDF02] shadow-inner">
                        <i data-lucide="shield-check" class="w-7 h-7"></i>
                    </div>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-slate-800 text-slate-300 border border-slate-700 mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                        Central Node: cluster-01.dhakawala.com
                    </span>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-white">
                        Platform Administration
                    </h1>
                    <p class="text-slate-400 text-xs sm:text-sm mt-1.5 max-w-sm mx-auto">
                        Authenticate with authorized Super Admin credentials to manage platform tenants, telemetry and cluster health.
                    </p>
                </div>

                <!-- Privacy Callout Note -->
                <div class="mb-6 p-3.5 rounded-xl bg-slate-800/70 border border-slate-700/80 text-xs text-slate-300 flex items-start gap-3">
                    <i data-lucide="lock" class="w-4 h-4 text-amber-400 flex-shrink-0 mt-0.5"></i>
                    <div class="leading-relaxed">
                        <strong class="text-white">Privacy-First Architecture:</strong> Platform Super Admins do not possess ambient access to CA Firm tenant payroll, student records, or audit documentation.
                    </div>
                </div>

                <!-- Validation Error Alert -->
                @if($validationError)
                    <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-800 text-rose-200 text-xs flex items-start gap-3" role="alert">
                        <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400 flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 leading-relaxed">
                            <span class="font-bold text-rose-100">Access Denied:</span> {{ $validationError }}
                        </div>
                        <button type="button" wire:click="$set('validationError', null)" class="text-rose-400 hover:text-white" aria-label="Dismiss error">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                @endif

                <!-- Simulated Success Alert -->
                @if($simulatedSuccessMessage)
                    <div class="mb-6 p-4 rounded-xl bg-emerald-950/80 border border-emerald-800 text-emerald-200 text-xs flex items-start gap-3" role="status">
                        <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1 leading-relaxed">
                            <span class="font-bold text-emerald-100">Authenticated:</span> {{ $simulatedSuccessMessage }}
                        </div>
                    </div>
                @endif

                <!-- Login Form -->
                <form wire:submit.prevent="submitLogin" class="space-y-4" novalidate>
                    <!-- Email Input -->
                    <div>
                        <label for="platform-email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Platform Operator Email <span class="text-rose-400">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="shield" class="w-4 h-4"></i>
                            </div>
                            <input
                                type="email"
                                id="platform-email"
                                wire:model.defer="email"
                                autocomplete="username"
                                placeholder="operator@dhakawala.com"
                                class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-700 bg-slate-800/90 text-white placeholder:text-slate-500 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                                required
                            >
                        </div>
                    </div>

                    <!-- Password Input with Toggle -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="platform-password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                Root Password <span class="text-rose-400">*</span>
                            </label>
                            <a href="{{ url('/platform/forgot-password') }}" class="text-xs font-semibold text-amber-400 hover:text-amber-300 hover:underline">
                                Recover root key?
                            </a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="key" class="w-4 h-4"></i>
                            </div>
                            <input
                                type="{{ $showPassword ? 'text' : 'password' }}"
                                id="platform-password"
                                wire:model.defer="password"
                                autocomplete="current-password"
                                placeholder="••••••••••••••••"
                                class="w-full pl-10 pr-11 py-2.5 text-sm rounded-xl border border-slate-700 bg-slate-800/90 text-white placeholder:text-slate-500 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                                required
                            >
                            <button
                                type="button"
                                wire:click="togglePassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-200 focus:outline-none"
                                aria-label="{{ $showPassword ? 'Hide password' : 'Show password' }}"
                            >
                                <i data-lucide="{{ $showPassword ? 'eye-off' : 'eye' }}" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me Option -->
                    <div class="flex items-center justify-between pt-1">
                        <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-300">
                            <input
                                type="checkbox"
                                wire:model.defer="remember"
                                class="w-4 h-4 rounded border-slate-700 bg-slate-800 text-amber-500 focus:ring-[#FCDF02] focus:ring-offset-0"
                            >
                            <span>Retain platform session for 8 hours</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] active:bg-[#E2C700] text-[#16181D] shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/80 disabled:opacity-60 cursor-pointer"
                        >
                            <span wire:loading.remove>Authenticate Platform Operator</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                <span>Verifying Cluster Credentials...</span>
                            </span>
                            <i wire:loading.remove data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Platform Footer -->
    <footer class="w-full border-t border-slate-800/80 px-6 py-4 text-center text-xs text-slate-500">
        <div>ERP SaaS Platform Engine • © 2026 DhakaWala Technology Ltd. • All Rights Reserved</div>
        <div class="text-[11px] text-slate-600 mt-1">Tenant Data Segregation • TLS 1.3 End-to-End Cryptography • Zero-Trust Cluster</div>
    </footer>
</div>
