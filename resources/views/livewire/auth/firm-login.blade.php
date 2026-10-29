<div class="min-h-screen w-full flex flex-col lg:flex-row bg-slate-50 font-sans selection:bg-[#FCDF02] selection:text-slate-950 overflow-x-hidden">
    @if(!$firm)
        <x-auth-unknown-firm :firm-slug="$firmSlug" />
    @else
        <x-brand-theme-style :firm="$firm" />
        <!-- ==================================================================
             LEFT PANEL — BRANDED CORPORATE EXPERIENCE (Desktop)
             ================================================================== -->
        <div class="hidden lg:flex lg:w-[48%] xl:w-[45%] relative bg-[#16181D] text-white flex-col justify-between p-10 xl:p-14 overflow-hidden border-r border-slate-800 shadow-2xl">
            <!-- Subtle Architectural Background Mesh & Radial Accents -->
            <div class="absolute inset-0 opacity-[0.03] pointer-events-none" style="background-image: radial-gradient(#FCDF02 1px, transparent 1px); background-size: 24px 24px;"></div>
            <div class="absolute -top-32 -left-32 w-96 h-96 bg-[#FCDF02]/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-32 -right-32 w-96 h-96 bg-[#F5AD16]/10 rounded-full blur-3xl pointer-events-none"></div>

            <!-- Top Brand Header -->
            <div class="relative z-10">
                <div class="flex items-center gap-4">
                    <div class="w-12 h-12 rounded-xl bg-white/5 border border-white/10 p-2 shadow-inner flex items-center justify-center flex-shrink-0 overflow-hidden">
                        @if(!empty($firm['logo_url']) && file_exists(public_path($firm['logo_url'])))
                            <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="w-full h-full object-contain">
                        @else
                            <span class="text-[#FCDF02] font-extrabold text-sm">{{ strtoupper($firm['initials'] ?? 'CA') }}</span>
                        @endif
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-base font-bold tracking-tight text-white">{{ $firm['name'] }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-[#FCDF02]/15 text-[#FCDF02] border border-[#FCDF02]/30 uppercase tracking-wider">
                                {{ $firm['short_code'] }}
                            </span>
                        </div>
                        <p class="text-xs font-medium text-slate-400 uppercase tracking-widest">{{ $firm['designation'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Central Hero Narrative & Practice Capabilities -->
            <div class="relative z-10 my-auto py-8">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-medium bg-white/5 border border-white/10 text-slate-300 mb-6 backdrop-blur">
                    <span class="w-2 h-2 rounded-full bg-[#FCDF02] animate-pulse"></span>
                    <span>Central Cloud Practice SaaS • Version 1.0</span>
                </div>

                <h1 class="text-3xl xl:text-4xl font-extrabold tracking-tight text-white leading-tight mb-4">
                    Your Practice.<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-[#FCDF02]">
                        One Connected Workspace.
                    </span>
                </h1>

                <p class="text-slate-300 text-sm xl:text-base leading-relaxed mb-8 max-w-lg font-normal">
                    {{ $firm['description'] }}
                </p>

                <!-- Practice Value Drivers -->
                <div class="space-y-3.5 max-w-lg">
                    @foreach($firm['features'] as $feature)
                        <div class="flex items-start gap-3 text-xs xl:text-sm text-slate-300 group">
                            <div class="w-5 h-5 rounded-md bg-[#FCDF02]/10 border border-[#FCDF02]/30 flex items-center justify-center text-[#FCDF02] flex-shrink-0 mt-0.5 group-hover:bg-[#FCDF02] group-hover:text-[#16181D] transition-colors">
                                <i data-lucide="check" class="w-3.5 h-3.5"></i>
                            </div>
                            <span class="leading-normal">{{ $feature }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Bottom Corporate Credentials & Privacy Commitment -->
            <div class="relative z-10 pt-6 border-t border-slate-800/80">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <i data-lucide="shield-check" class="w-4 h-4 text-[#FCDF02]"></i>
                        <span class="font-medium text-slate-300">Privacy-First Architecture</span>
                    </div>
                    <span class="text-[11px] font-mono text-slate-400">{{ $firm['icab_reg'] }}</span>
                </div>
                <p class="text-[11px] text-slate-400 leading-relaxed">
                    {{ $firm['office'] }}
                </p>
            </div>
        </div>

        <!-- ==================================================================
             RIGHT PANEL — LOGIN FORM CANVAS (Desktop & Mobile)
             ================================================================== -->
        <div class="flex-1 w-full max-w-full flex flex-col justify-between bg-white px-4 sm:px-8 md:px-12 lg:px-12 xl:px-20 py-6 sm:py-10 overflow-x-hidden">
            <!-- Navigation Header Bar -->
            <div class="flex items-center justify-between w-full mb-6 gap-2">
                <!-- Mobile Only Firm Brand Header -->
                <div class="flex items-center gap-2.5 lg:hidden min-w-0">
                    <div class="w-9 h-9 rounded-xl bg-slate-900 p-1.5 flex items-center justify-center shadow-md shrink-0 overflow-hidden">
                        @if(!empty($firm['logo_url']) && file_exists(public_path($firm['logo_url'])))
                            <img src="{{ asset($firm['logo_url']) }}" alt="{{ $firm['name'] }}" class="w-full h-full object-contain">
                        @else
                            <span class="text-[#FCDF02] font-extrabold text-xs">{{ strtoupper($firm['initials'] ?? 'CA') }}</span>
                        @endif
                    </div>
                    <div class="truncate">
                        <div class="font-bold text-xs sm:text-sm text-slate-950 leading-tight truncate">{{ $firm['name'] }}</div>
                        <div class="text-[10px] sm:text-[11px] font-medium text-slate-500 uppercase tracking-wider truncate">{{ $firm['designation'] }}</div>
                    </div>
                </div>

                <!-- Desktop Left Spacer for perfect alignment -->
                <div class="hidden lg:block">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        {{ $firm['firm_status'] }}
                    </span>
                </div>

                <!-- Navigation Links -->
                <div class="flex items-center gap-1 sm:gap-2.5 text-xs font-medium shrink-0">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-1 text-slate-600 hover:text-slate-950 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition-colors" title="Workspace Shell">
                        <i data-lucide="layout-dashboard" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Shell</span>
                    </a>
                    <a href="{{ url('/platform/login') }}" class="inline-flex items-center gap-1 text-slate-600 hover:text-slate-950 px-2 py-1.5 rounded-lg hover:bg-slate-100 transition-colors" title="Platform Super Admin">
                        <i data-lucide="shield" class="w-3.5 h-3.5"></i>
                        <span class="hidden sm:inline">Platform</span>
                    </a>
                </div>
            </div>

            <!-- Login Container -->
            <div class="w-full max-w-md mx-auto my-auto py-4">
                <!-- Form Header -->
                <div class="mb-7">
                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-900 border border-amber-200 mb-3">
                        <i data-lucide="lock" class="w-3 h-3 text-amber-600"></i>
                        <span>Secure Practice Portal</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-950">
                        Sign In to Your Account
                    </h2>
                    <p class="mt-2 text-sm text-slate-600">
                        Enter your registered firm email or User ID to access your authorized practice workspace.
                    </p>
                </div>

                <!-- Validation Error Alert -->
                @if($validationError)
                    <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-900 text-sm flex items-start gap-3 shadow-sm" role="alert">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-600 flex-shrink-0 mt-0.5"></i>
                        <div class="flex-1">
                            <p class="font-semibold text-rose-950">Authentication Warning</p>
                            <p class="text-xs text-rose-800 mt-0.5 leading-relaxed">{{ $validationError }}</p>
                        </div>
                        <button type="button" wire:click="$set('validationError', null)" class="text-rose-500 hover:text-rose-800" aria-label="Dismiss error">
                            <i data-lucide="x" class="w-4 h-4"></i>
                        </button>
                    </div>
                @endif

                <!-- Authenticated Alert -->
                @if($simulatedSuccessMessage)
                    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-950 text-sm shadow-sm" role="status">
                        <div class="flex items-start gap-3">
                            <div class="w-6 h-6 rounded-full bg-emerald-500 text-white flex items-center justify-center flex-shrink-0 mt-0.5">
                                <i data-lucide="check" class="w-4 h-4"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-bold text-emerald-950">Signed In</p>
                                <p class="text-xs text-emerald-800 mt-1 leading-relaxed">{{ $simulatedSuccessMessage }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Login Form -->
                <form wire:submit.prevent="submitLogin" class="space-y-5" novalidate>
                    <!-- Email / User ID Input -->
                    <div>
                        <label for="login-email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">
                            Practice Email or User ID <span class="text-rose-500">*</span>
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                            </div>
                            <input
                                type="text"
                                id="login-email"
                                wire:model.defer="email"
                                autocomplete="username"
                                placeholder="name@{{ $firm['custom_domain'] ?? ($firm['domain'] ?? ($firmSlug . '.dhakawala.com')) }} or User ID"
                                class="w-full pl-10 pr-4 py-2.5 text-sm rounded-xl border border-slate-300 bg-white text-slate-900 placeholder:text-slate-400 focus:outline-none focus:border-[#FCDF02] focus:ring-2 focus:ring-[#FCDF02]/40 transition-all font-sans"
                                required
                            >
                        </div>
                    </div>

                    <!-- Password Input with Visibility Toggle -->
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="login-password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                                Password <span class="text-rose-500">*</span>
                            </label>
                            <a href="{{ url('/c/' . $firmSlug . '/forgot-password') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800 hover:underline">
                                Forgot password?
                            </a>
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                <i data-lucide="lock" class="w-4 h-4"></i>
                            </div>
                            <input
                                type="{{ $showPassword ? 'text' : 'password' }}"
                                id="login-password"
                                wire:model.defer="password"
                                autocomplete="current-password"
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

                    <!-- Remember Me & Policy Note -->
                    <div class="flex items-start justify-between gap-3 pt-1">
                        <label class="flex items-start gap-2.5 cursor-pointer select-none">
                            <input
                                type="checkbox"
                                wire:model.defer="remember"
                                class="mt-0.5 w-4 h-4 rounded border-slate-300 text-amber-600 focus:ring-[#FCDF02] focus:ring-offset-0 transition-colors"
                            >
                            <span class="text-xs text-slate-700 leading-tight">
                                <span class="font-medium text-slate-900">Remember this workstation</span><br>
                                <span class="text-[11px] text-slate-500">For 30 days on secure firm hardware</span>
                            </span>
                        </label>
                    </div>

                    <!-- Primary Submit Button -->
                    <div class="pt-2">
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-bold text-sm bg-[#FCDF02] hover:bg-[#F0D400] active:bg-[#E2C700] text-[#16181D] shadow-md hover:shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/80 disabled:opacity-60 cursor-pointer"
                        >
                            <span wire:loading.remove>Sign In to Practice Workspace</span>
                            <span wire:loading class="inline-flex items-center gap-2">
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                <span>Signing In...</span>
                            </span>
                            <i wire:loading.remove data-lucide="arrow-right" class="w-4 h-4"></i>
                        </button>
                    </div>
                </form>

                <!-- Invitation Link Callout -->
                <div class="mt-6 p-3 rounded-xl bg-slate-50 border border-slate-200 text-center">
                    <p class="text-xs text-slate-600">
                        First time at {{ $firm['name'] }}?
                        <a href="{{ url('/c/' . $firmSlug . '/activate') }}" class="font-bold text-amber-700 hover:text-amber-800 hover:underline">
                            Activate your invited account
                        </a>
                    </p>
                </div>
            </div>

            <!-- Footer Compliance Notice -->
            <div class="w-full pt-6 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 text-center sm:text-left">
                <div class="flex items-center justify-center sm:justify-start gap-2">
                    <i data-lucide="lock" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                    <span>Authorized Practice Personnel Only • ICAB Compliant Access</span>
                </div>
                <div class="flex items-center justify-center gap-3 text-[11px]">
                    <a href="{{ url('/') }}" class="hover:text-slate-900 transition-colors">Workspace Shell</a>
                    <span>•</span>
                    <a href="{{ url('/platform/login') }}" class="hover:text-slate-900 transition-colors">Platform Super Admin</a>
                </div>
            </div>
        </div>
    @endif
</div>
