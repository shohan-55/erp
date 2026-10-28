<div class="min-h-screen w-full bg-[#111317] text-slate-100 font-sans selection:bg-[#FCDF02] selection:text-slate-950 relative overflow-x-hidden">
    
    <!-- Ambient Background Gradients -->
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-amber-500/10 rounded-full blur-[120px] pointer-events-none"></div>
    <div class="absolute top-1/3 -right-40 w-[30rem] h-[30rem] bg-amber-400/10 rounded-full blur-[140px] pointer-events-none"></div>
    <div class="absolute -bottom-40 left-1/4 w-[35rem] h-[35rem] bg-blue-600/5 rounded-full blur-[150px] pointer-events-none"></div>

    <!-- =========================================================================
         1. TOP NAVIGATION BAR (DhakaWala ERP Global Identity)
         ========================================================================= -->
    <header class="relative z-20 w-full border-b border-white/10 bg-[#16181F]/90 backdrop-blur-md px-4 sm:px-8 py-3.5 flex items-center justify-between">
        <!-- DhakaWala Brand -->
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-[#1E222B] to-[#111317] border border-[#FCDF02]/30 flex items-center justify-center p-1.5 shadow-lg">
                <img src="{{ asset('images/dhakawala_logo.svg') }}" alt="DhakaWala ERP Emblem" class="w-full h-full object-contain">
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <span class="text-base font-extrabold tracking-tight text-white">DhakaWala <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-amber-400">ERP</span></span>
                    <span class="hidden sm:inline-flex px-2 py-0.5 rounded text-[10px] font-bold bg-[#FCDF02]/15 text-[#FCDF02] border border-[#FCDF02]/30 uppercase tracking-wider">
                        Production Cloud
                    </span>
                </div>
                <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Node: erp.dhakawala.com</span>
                </div>
            </div>
        </div>

        <!-- Navigation Actions -->
        <div class="flex items-center gap-2 sm:gap-3 text-xs font-semibold">
            <a href="{{ url('/c/rac/login') }}" class="hidden md:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">
                <i data-lucide="building-2" class="w-3.5 h-3.5 text-[#FCDF02]"></i>
                <span>CA Firm Portals</span>
            </a>
            <a href="{{ url('/platform/login') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">
                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-amber-400"></i>
                <span class="hidden sm:inline">Platform Admin</span>
            </a>
            <a href="{{ url('/c/rac/activate') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#FCDF02] hover:bg-[#f0d400] text-[#16181D] font-bold transition-all shadow-sm">
                <i data-lucide="key" class="w-3.5 h-3.5"></i>
                <span>Activate Account</span>
            </a>
        </div>
    </header>

    <!-- =========================================================================
         2. HERO & UNIFIED ACCESS SECTION
         ========================================================================= -->
    <main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 lg:py-16">
        
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-14 items-center">
            
            <!-- Left Column: Software Narrative & Value Proposition -->
            <div class="lg:col-span-7 space-y-6">
                
                <!-- Status Pill -->
                <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-white/5 border border-white/10 backdrop-blur text-xs font-medium text-slate-300">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                    <span>Enterprise SaaS for Chartered Accountants & Professional Firms</span>
                </div>

                <!-- Main Headline -->
                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-[1.15]">
                    Your Practice.<br>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-amber-300">
                        Intelligent Cloud Governance.
                    </span>
                </h1>

                <!-- Sub-narrative -->
                <p class="text-slate-300 text-sm sm:text-base leading-relaxed max-w-2xl font-normal">
                    <strong class="text-white font-semibold">DhakaWala ERP</strong> provides dedicated enterprise cloud software and practice automation services to Chartered Accountancy (CA) and audit firms across Bangladesh. Engineered for ICAB compliance, real-time geofenced student tracking, and multi-tier financial governance.
                </p>

                <!-- Core Capability Pillars Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                    
                    <!-- Feature 1: Geofenced Attendance -->
                    <div class="p-4 rounded-2xl bg-[#1C1F28]/90 border border-white/10 hover:border-amber-400/30 transition-all group">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-[#FCDF02] group-hover:scale-105 transition-transform">
                                <i data-lucide="map-pin" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-sm font-bold text-white">Geofenced GPS Attendance</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Verified on-site audit tracking at client locations (Bangladesh Bank, NBR, corporates) with live GPS capture.
                        </p>
                    </div>

                    <!-- Feature 2: ICAB Stipend Ledger -->
                    <div class="p-4 rounded-2xl bg-[#1C1F28]/90 border border-white/10 hover:border-amber-400/30 transition-all group">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-[#FCDF02] group-hover:scale-105 transition-transform">
                                <i data-lucide="wallet-cards" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-sm font-bold text-white">ICAB Allowance & Stipends</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Automated monthly stipend calculations, attendance deduction rules, and conveyance rollups.
                        </p>
                    </div>

                    <!-- Feature 3: Conveyance Claims -->
                    <div class="p-4 rounded-2xl bg-[#1C1F28]/90 border border-white/10 hover:border-amber-400/30 transition-all group">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-[#FCDF02] group-hover:scale-105 transition-transform">
                                <i data-lucide="receipt" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-sm font-bold text-white">Multi-tier Conveyance Audit</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Articled clerk travel submissions with voucher uploads, manager verification, and partner sign-off.
                        </p>
                    </div>

                    <!-- Feature 4: Multi-Partner Governance -->
                    <div class="p-4 rounded-2xl bg-[#1C1F28]/90 border border-white/10 hover:border-amber-400/30 transition-all group">
                        <div class="flex items-center gap-3 mb-2">
                            <div class="w-9 h-9 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-[#FCDF02] group-hover:scale-105 transition-transform">
                                <i data-lucide="shield-check" class="w-4 h-4"></i>
                            </div>
                            <h3 class="text-sm font-bold text-white">Multi-Partner Governance</h3>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Isolated Partner Units with customizable office timings, grace periods, and manager permission scopes.
                        </p>
                    </div>
                </div>

                <!-- Trust Callout -->
                <div class="pt-2 flex items-center gap-3 text-xs text-slate-400">
                    <i data-lucide="lock" class="w-4 h-4 text-emerald-400 flex-shrink-0"></i>
                    <span>Zero-Ambient-Access Guarantee: Practice audit records and financial logs are tenant-isolated.</span>
                </div>
            </div>

            <!-- Right Column: Production Unified Login Console -->
            <div class="lg:col-span-5">
                <div class="w-full bg-[#1A1D26]/95 border border-white/15 rounded-3xl p-6 sm:p-8 shadow-2xl backdrop-blur-xl relative">
                    
                    <!-- Decorative Top Highlight -->
                    <div class="absolute top-0 inset-x-8 h-[2px] bg-gradient-to-r from-transparent via-[#FCDF02] to-transparent"></div>

                    <!-- Sign-in Header -->
                    <div class="mb-6">
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full text-[11px] font-semibold bg-amber-500/10 text-amber-300 border border-amber-500/20 mb-2">
                            <i data-lucide="lock" class="w-3 h-3 text-[#FCDF02]"></i>
                            <span>Secure Workspace Portal</span>
                        </div>
                        <h2 class="text-xl sm:text-2xl font-extrabold tracking-tight text-white">
                            Sign In to DhakaWala ERP
                        </h2>
                        <p class="mt-1 text-xs text-slate-400">
                            Enter your credentials to access your firm workspace or platform console.
                        </p>
                    </div>

                    <!-- Error Alert -->
                    @if($loginError)
                        <div class="mb-5 p-3.5 rounded-xl bg-rose-500/15 border border-rose-500/30 text-rose-200 text-xs flex items-start gap-2.5 shadow-sm" role="alert">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="font-bold text-rose-100">Sign-in Notice:</span> {{ $loginError }}
                            </div>
                        </div>
                    @endif

                    @if($loginSuccess)
                        <div class="mb-5 p-3.5 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-emerald-200 text-xs flex items-start gap-2.5 shadow-sm">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                            <div class="flex-1 font-semibold text-emerald-100">{{ $loginSuccess }}</div>
                        </div>
                    @endif

                    <!-- Sign-In Form -->
                    <form wire:submit.prevent="submitLogin" class="space-y-4">
                        
                        <!-- Email Input -->
                        <div>
                            <label for="portal-email" class="block text-xs font-semibold text-slate-300 mb-1.5">Work Email / User ID</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </div>
                                <input
                                    wire:model="email"
                                    type="email"
                                    id="portal-email"
                                    required
                                    placeholder="name@rahmananis.com or admin@dhakawala.com"
                                    class="w-full pl-10 pr-4 py-2.5 rounded-xl bg-[#222632] border border-white/10 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-[#FCDF02] focus:ring-1 focus:ring-[#FCDF02] transition-colors"
                                >
                            </div>
                        </div>

                        <!-- Password Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="portal-password" class="block text-xs font-semibold text-slate-300">Password</label>
                                <a href="{{ url('/platform/forgot-password') }}" class="text-[11px] text-[#FCDF02] hover:underline">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                    <i data-lucide="lock" class="w-4 h-4"></i>
                                </div>
                                <input
                                    wire:model="password"
                                    type="{{ $showPassword ? 'text' : 'password' }}"
                                    id="portal-password"
                                    required
                                    placeholder="••••••••"
                                    class="w-full pl-10 pr-10 py-2.5 rounded-xl bg-[#222632] border border-white/10 text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-[#FCDF02] focus:ring-1 focus:ring-[#FCDF02] transition-colors"
                                >
                                <button
                                    type="button"
                                    wire:click="togglePassword"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white"
                                    aria-label="Toggle password visibility"
                                >
                                    <i data-lucide="{{ $showPassword ? 'eye-off' : 'eye' }}" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-400 select-none">
                                <input wire:model="remember" type="checkbox" class="w-4 h-4 rounded bg-[#222632] border-white/10 text-amber-500 focus:ring-0 focus:ring-offset-0">
                                <span>Remember this session</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-[#FCDF02] via-[#F5AD16] to-[#FCDF02] text-[#16181D] font-extrabold text-sm shadow-lg hover:shadow-amber-500/20 hover:brightness-105 active:scale-[0.99] transition-all flex items-center justify-center gap-2 disabled:opacity-60"
                        >
                            <span wire:loading.remove>Sign In to ERP Workspace</span>
                            <span wire:loading class="flex items-center gap-2">
                                <i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i>
                                <span>Verifying credentials...</span>
                            </span>
                            <i data-lucide="arrow-right" class="w-4 h-4" wire:loading.remove></i>
                        </button>
                    </form>

                    <!-- Divider -->
                    <div class="my-6 relative flex items-center justify-center">
                        <div class="border-t border-white/10 w-full"></div>
                        <span class="bg-[#1A1D26] px-3 text-[11px] font-semibold text-slate-400 uppercase tracking-wider absolute">
                            CA Firm Workspaces
                        </span>
                    </div>

                    <!-- Direct Client Firm Launchpad -->
                    <div class="space-y-2">
                        <a href="{{ url('/c/rac/login') }}" class="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-white p-1 flex items-center justify-center shrink-0">
                                    <img src="{{ asset('images/rac_logo.svg') }}" alt="Rahman Anis & Co." class="w-full h-full object-contain">
                                </div>
                                <div class="truncate">
                                    <div class="text-xs font-bold text-white group-hover:text-[#FCDF02] transition-colors truncate">Rahman Anis & Co.</div>
                                    <div class="text-[10px] text-slate-400 truncate">Chartered Accountants • Client Practice Node</div>
                                </div>
                            </div>
                            <span class="text-xs text-[#FCDF02] font-semibold flex items-center gap-1 shrink-0">
                                <span>Launch</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"></i>
                            </span>
                        </a>

                        <a href="{{ url('/c/sample-alpha/login') }}" class="flex items-center justify-between p-3 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 transition-all group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg bg-[#222632] border border-white/10 flex items-center justify-center text-amber-400 font-extrabold text-xs shrink-0">
                                    SA
                                </div>
                                <div class="truncate">
                                    <div class="text-xs font-bold text-white group-hover:text-[#FCDF02] transition-colors truncate">Sample Practice Alpha</div>
                                    <div class="text-[10px] text-slate-400 truncate">Chartered Accountants • Practice Node</div>
                                </div>
                            </div>
                            <span class="text-xs text-slate-400 font-semibold flex items-center gap-1 shrink-0 group-hover:text-white transition-colors">
                                <span>Launch</span>
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform"></i>
                            </span>
                        </a>
                    </div>

                    <!-- Bottom Quick Links -->
                    <div class="mt-6 pt-4 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                        <a href="{{ url('/platform/login') }}" class="hover:text-white transition-colors flex items-center gap-1">
                            <i data-lucide="shield" class="w-3.5 h-3.5 text-amber-400"></i>
                            <span>Platform Operator Console</span>
                        </a>
                        <a href="{{ url('/c/rac/activate') }}" class="hover:text-white transition-colors flex items-center gap-1">
                            <i data-lucide="user-check" class="w-3.5 h-3.5 text-emerald-400"></i>
                            <span>Activate Account</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- =====================================================================
             3. HOW DHAKAWALA ERP SERVICES CA FIRMS (Architecture Overview)
             ===================================================================== -->
        <section class="mt-20 pt-12 border-t border-white/10">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <span class="text-[11px] font-bold text-[#FCDF02] uppercase tracking-widest">Client Service Architecture</span>
                <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-1">
                    How DhakaWala ERP Powers CA Firms
                </h2>
                <p class="mt-2 text-xs sm:text-sm text-slate-400 leading-relaxed">
                    DhakaWala provides end-to-end cloud infrastructure, GPS validation engines, and automated stipend accounting as a managed SaaS solution for audit and accounting practitioners.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Service 1 -->
                <div class="p-6 rounded-2xl bg-[#161820] border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-amber-400/10 text-[#FCDF02] flex items-center justify-center mb-4">
                        <i data-lucide="building" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">Multi-Tenant Firm Workspaces</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Each CA firm operates within its dedicated isolated practice workspace with unique sub-routing (<code class="text-amber-300">/c/{firmSlug}</code>), branded identity, and private employee rosters.
                    </p>
                </div>

                <!-- Service 2 -->
                <div class="p-6 rounded-2xl bg-[#161820] border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-amber-400/10 text-[#FCDF02] flex items-center justify-center mb-4">
                        <i data-lucide="user-check" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">ICAB Articled Student Compliance</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Built around Institute of Chartered Accountants of Bangladesh (ICAB) registration regulations, tracking student registration numbers, articleship levels, leave limits, and stipends.
                    </p>
                </div>

                <!-- Service 3 -->
                <div class="p-6 rounded-2xl bg-[#161820] border border-white/10">
                    <div class="w-10 h-10 rounded-xl bg-amber-400/10 text-[#FCDF02] flex items-center justify-center mb-4">
                        <i data-lucide="shield-check" class="w-5 h-5"></i>
                    </div>
                    <h3 class="text-base font-bold text-white mb-2">Partner Unit Governance</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Empowers each Senior Partner to autonomously define office start times, late grace windows, manager conveyance caps, and approval policies for their assigned audit groups.
                    </p>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             4. GLOBAL FOOTER
             ===================================================================== -->
        <footer class="mt-20 pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <div class="flex items-center gap-2">
                <span class="font-bold text-slate-300">DhakaWala ERP</span>
                <span>•</span>
                <span>Enterprise Practice Management Cloud</span>
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <span>Operated by <strong class="text-slate-300">DhakaWala</strong></span>
                <span>•</span>
                <span>Production Node: erp.dhakawala.com</span>
            </div>
        </footer>
    </main>
</div>
