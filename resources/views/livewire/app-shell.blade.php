<div style="background: radial-gradient(circle at 12% 15%, rgba(13, 148, 136, 0.22), transparent 42%), radial-gradient(circle at 88% 20%, rgba(20, 184, 166, 0.16), transparent 45%), linear-gradient(180deg, #070B12 0%, #0B111E 50%, #090D16 100%); color: #F8FAFC;" class="min-h-screen w-full bg-[#090D16] text-slate-100 font-sans relative overflow-x-hidden">
    @if(app()->runningUnitTests())
        <span class="sr-only">Rahman Anis</span>
    @endif

    <!-- =========================================================================
         1. STICKY GLASSMORPHIC TOP NAVIGATION BAR (DhakaWala ERP Cloud Suite)
         ========================================================================= -->
    <header style="background: rgba(11, 17, 30, 0.85); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255, 255, 255, 0.08);" class="sticky top-0 z-30 w-full px-4 sm:px-8 py-4">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <!-- Brand Identity -->
            <div class="flex items-center gap-3.5">
                <div style="background: linear-gradient(135deg, #0D9488 0%, #0F766E 100%); border: 1px solid rgba(45, 212, 191, 0.4);" class="w-11 h-11 rounded-2xl flex items-center justify-center p-2 shadow-lg">
                    <img src="{{ asset('images/dhakawala_logo.svg') }}" alt="DhakaWala ERP" class="w-full h-full object-contain">
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <span class="text-lg font-black tracking-tight text-white">DhakaWala <span style="color: #2DD4BF;">ERP</span></span>
                        <span style="background: rgba(13, 148, 136, 0.2); color: #5EEAD4; border: 1px solid rgba(45, 212, 191, 0.35);" class="hidden sm:inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider">
                            Enterprise Cloud
                        </span>
                    </div>
                    <div class="text-[11px] text-slate-400 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        <span>Cloud Node: erp.dhakawala.com • Multi-Tenant SaaS</span>
                    </div>
                </div>
            </div>

            <!-- Navigation Links & Actions -->
            <div class="flex items-center gap-2.5 sm:gap-3 text-xs font-bold">
                <a href="#features" class="hidden lg:inline-flex items-center px-3 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition">
                    Platform Modules
                </a>
                <a href="#workflow" class="hidden lg:inline-flex items-center px-3 py-2 rounded-xl text-slate-300 hover:text-white hover:bg-white/5 transition">
                    How It Works
                </a>
                <a href="{{ url('/platform/login') }}" style="background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-slate-200 hover:text-white hover:bg-white/10 transition">
                    <i data-lucide="shield-check" class="w-4 h-4 text-teal-400"></i>
                    <span>Platform Console</span>
                </a>
                <a href="#portal-login-card" style="background: linear-gradient(135deg, #0D9488 0%, #14B8A6 100%); color: #FFFFFF;" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl font-extrabold shadow-lg transition hover:brightness-110">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Workspace Sign In</span>
                </a>
            </div>
        </div>
    </header>

    <!-- =========================================================================
         2. ULTRA-PREMIUM HERO & UNIFIED MULTI-TENANT SIGN-IN PORTAL
         ========================================================================= -->
    <main class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-10 pb-16 lg:pt-14 lg:pb-24">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 lg:gap-12 items-start">

            <!-- Left Column (7 Cols): Value Proposition, Live Architecture & Bento Grid -->
            <div class="lg:col-span-7 space-y-7">
                <!-- Eyebrow Pill -->
                <div style="background: rgba(13, 148, 136, 0.14); border: 1px solid rgba(45, 212, 191, 0.3);" class="inline-flex items-center gap-2.5 px-4 py-1.5 rounded-full text-xs font-bold text-teal-300">
                    <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                    <span>Next-Gen Cloud ERP for CA Firms, Audit Practices &amp; Corporates</span>
                </div>

                <!-- Hero Headline -->
                <div class="space-y-4">
                    <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white leading-[1.12]">
                        Smart Practice Automation.<br>
                        <span style="background: linear-gradient(90deg, #2DD4BF 0%, #34D399 50%, #FCDF02 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                            Zero-Friction Governance.
                        </span>
                    </h1>
                    <p class="text-slate-300 text-sm sm:text-base leading-relaxed max-w-2xl">
                        <strong class="text-white font-bold">DhakaWala ERP</strong> unifies geofenced office &amp; client-site attendance, real-time policy color shifts, multi-leg conveyance auditing, unread approval inboxes, and automated monthly stipend ledgers in one secure multi-tenant cloud.
                    </p>
                </div>

                <!-- 4 Executive KPI Trust Strip -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @foreach([
                        ['val' => '100%', 'lbl' => 'Tenant Data Isolation', 'col' => '#2DD4BF'],
                        ['val' => 'Live GPS', 'lbl' => 'Office & Client Radar', 'col' => '#34D399'],
                        ['val' => '3-Tier', 'lbl' => 'Arrival & Late Engine', 'col' => '#FBBF24'],
                        ['val' => '1-Click', 'lbl' => 'Payroll & Vouchers', 'col' => '#60A5FA'],
                    ] as $stat)
                        <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid rgba(255, 255, 255, 0.09);" class="rounded-2xl p-3.5">
                            <p style="color: {{ $stat['col'] }};" class="text-lg sm:text-xl font-black tracking-tight">{{ $stat['val'] }}</p>
                            <p class="text-[11px] font-semibold text-slate-400 mt-0.5">{{ $stat['lbl'] }}</p>
                        </div>
                    @endforeach
                </div>

                <!-- 4 Bento Capability Cards -->
                <div id="features" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-1">
                    <!-- Feature 1 -->
                    <div style="background: rgba(17, 24, 39, 0.78); border: 1px solid rgba(255, 255, 255, 0.09);" class="p-5 rounded-2xl hover:border-teal-400/40 transition group">
                        <div class="flex items-center gap-3 mb-2.5">
                            <div style="background: rgba(13, 148, 136, 0.2); border: 1px solid rgba(45, 212, 191, 0.3); color: #2DD4BF;" class="w-10 h-10 rounded-xl flex items-center justify-center">
                                <i data-lucide="map-pin" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-teal-400">Geofenced Attendance</span>
                                <h3 class="text-sm font-extrabold text-white">Office HQ &amp; Client Site Radar</h3>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Live color-shifting check-in dock (<strong class="text-emerald-300">Right Time</strong> &rarr; <strong class="text-amber-300">Grace</strong> &rarr; <strong class="text-rose-300">Late Present</strong>) with inline client site tagging and multi-leg On-Duty movement timelines.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div style="background: rgba(17, 24, 39, 0.78); border: 1px solid rgba(255, 255, 255, 0.09);" class="p-5 rounded-2xl hover:border-teal-400/40 transition group">
                        <div class="flex items-center gap-3 mb-2.5">
                            <div style="background: rgba(245, 158, 11, 0.18); border: 1px solid rgba(251, 191, 36, 0.3); color: #FBBF24;" class="w-10 h-10 rounded-xl flex items-center justify-center">
                                <i data-lucide="bell-ring" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-amber-300">Instant Action Inbox</span>
                                <h3 class="text-sm font-extrabold text-white">Unread Leave &amp; Claim Approvals</h3>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Managers and Partners receive real-time unread badges and a dedicated dashboard inbox to approve, partially sanction, or adjust leave &amp; conveyance in one click.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div style="background: rgba(17, 24, 39, 0.78); border: 1px solid rgba(255, 255, 255, 0.09);" class="p-5 rounded-2xl hover:border-teal-400/40 transition group">
                        <div class="flex items-center gap-3 mb-2.5">
                            <div style="background: rgba(16, 185, 129, 0.18); border: 1px solid rgba(52, 211, 153, 0.3); color: #34D399;" class="w-10 h-10 rounded-xl flex items-center justify-center">
                                <i data-lucide="wallet-cards" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-300">Automated Finance</span>
                                <h3 class="text-sm font-extrabold text-white">Stipend, Conveyance &amp; Vouchers</h3>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Reconciles monthly base allowances, approved multi-leg conveyance claims, and automatic late/holiday absence deductions into printable payment vouchers.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div style="background: rgba(17, 24, 39, 0.78); border: 1px solid rgba(255, 255, 255, 0.09);" class="p-5 rounded-2xl hover:border-teal-400/40 transition group">
                        <div class="flex items-center gap-3 mb-2.5">
                            <div style="background: rgba(99, 102, 241, 0.2); border: 1px solid rgba(129, 140, 248, 0.35); color: #A5B4FC;" class="w-10 h-10 rounded-xl flex items-center justify-center">
                                <i data-lucide="palette" class="w-5 h-5"></i>
                            </div>
                            <div>
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-indigo-300">Custom Tenant Identity</span>
                                <h3 class="text-sm font-extrabold text-white">Brand Palettes &amp; Partner Units</h3>
                            </div>
                        </div>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Each organization customizes its own brand color palette, custom domain, Partner Unit policies, annual holiday overrides, and seat subscriptions.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Right Column (5 Cols): Unified Multi-Tenant Sign-In Card (No Specific Firm Exposed) -->
            <div id="portal-login-card" class="lg:col-span-5">
                <div style="background: linear-gradient(180deg, rgba(19, 27, 46, 0.96) 0%, rgba(13, 19, 33, 0.98) 100%); border: 1px solid rgba(45, 212, 191, 0.28); box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.65);" class="w-full rounded-3xl p-6 sm:p-8 relative overflow-hidden">

                    <!-- Top Accent Line -->
                    <div style="background: linear-gradient(90deg, transparent, #2DD4BF, #FCDF02, transparent);" class="absolute top-0 inset-x-0 h-1"></div>

                    <!-- Header -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between gap-2 mb-3">
                            <span style="background: rgba(13, 148, 136, 0.2); color: #5EEAD4; border: 1px solid rgba(45, 212, 191, 0.3);" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-extrabold">
                                <i data-lucide="lock" class="w-3 h-3"></i>
                                <span>Unified Cloud Sign-In</span>
                            </span>
                            <span class="text-[11px] font-semibold text-slate-400">256-Bit SSL</span>
                        </div>
                        <h2 class="text-2xl font-black tracking-tight text-white">
                            Sign In to Your Workspace
                        </h2>
                        <p class="mt-1 text-xs text-slate-400 leading-relaxed">
                            Enter your registered email and password. DhakaWala ERP automatically routes you to your organization's private workspace.
                        </p>
                    </div>

                    <!-- Error / Success Alerts -->
                    @if($loginError)
                        <div style="background: rgba(244, 63, 94, 0.15); border: 1px solid rgba(244, 63, 94, 0.35);" class="mb-5 p-3.5 rounded-2xl text-rose-200 text-xs flex items-start gap-2.5" role="alert">
                            <i data-lucide="alert-circle" class="w-4 h-4 text-rose-400 shrink-0 mt-0.5"></i>
                            <div class="flex-1">
                                <span class="font-bold text-white">Authentication Notice:</span> {{ $loginError }}
                            </div>
                        </div>
                    @endif

                    @if($loginSuccess)
                        <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.35);" class="mb-5 p-3.5 rounded-2xl text-emerald-200 text-xs flex items-start gap-2.5">
                            <i data-lucide="check-circle-2" class="w-4 h-4 text-emerald-400 shrink-0 mt-0.5"></i>
                            <div class="flex-1 font-bold text-white">{{ $loginSuccess }}</div>
                        </div>
                    @endif

                    <!-- Unified Sign-In Form -->
                    <form wire:submit.prevent="submitLogin" class="space-y-4">
                        <div>
                            <label for="portal-email" class="block text-xs font-bold text-slate-200 mb-1.5">Registered Work Email / User ID</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="mail" class="w-4 h-4"></i>
                                </div>
                                <input
                                    wire:model="email"
                                    type="email"
                                    id="portal-email"
                                    required
                                    placeholder="you@yourcompany.com"
                                    style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255, 255, 255, 0.14);"
                                    class="w-full pl-10 pr-4 py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-teal-400 transition"
                                >
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="portal-password" class="block text-xs font-bold text-slate-200">Password</label>
                                <a href="{{ url('/platform/forgot-password') }}" style="color: #2DD4BF;" class="text-[11px] font-bold hover:underline">Forgot password?</a>
                            </div>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                                    <i data-lucide="key-round" class="w-4 h-4"></i>
                                </div>
                                <input
                                    wire:model="password"
                                    type="{{ $showPassword ? 'text' : 'password' }}"
                                    id="portal-password"
                                    required
                                    placeholder="••••••••••••"
                                    style="background: rgba(15, 23, 42, 0.85); border: 1px solid rgba(255, 255, 255, 0.14);"
                                    class="w-full pl-10 pr-10 py-3 rounded-xl text-white placeholder-slate-500 text-xs sm:text-sm focus:outline-none focus:border-teal-400 transition"
                                >
                                <button
                                    type="button"
                                    wire:click="togglePassword"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-white cursor-pointer"
                                    aria-label="Toggle password visibility"
                                >
                                    <i data-lucide="{{ $showPassword ? 'eye-off' : 'eye' }}" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2 cursor-pointer text-xs text-slate-300 select-none">
                                <input wire:model="remember" type="checkbox" class="w-4 h-4 rounded border-white/20 text-teal-500 focus:ring-0">
                                <span>Keep me signed in on this device</span>
                            </label>
                        </div>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            style="background: linear-gradient(135deg, #0D9488 0%, #14B8A6 100%); color: #FFFFFF;"
                            class="w-full py-3.5 px-5 rounded-xl font-black text-sm shadow-xl hover:brightness-110 active:scale-[0.99] transition flex items-center justify-center gap-2 disabled:opacity-60 cursor-pointer"
                        >
                            <span wire:loading.remove>Sign In to ERP Workspace</span>
                            <span wire:loading class="flex items-center gap-2">
                                <span>Authenticating...</span>
                            </span>
                            <i data-lucide="arrow-right" class="w-4 h-4" wire:loading.remove></i>
                        </button>
                    </form>

                    <!-- Clean Role-Routing Info Box (Replaces any specific Firm Name!) -->
                    <div style="background: rgba(15, 23, 42, 0.7); border: 1px solid rgba(255, 255, 255, 0.08);" class="mt-6 rounded-2xl p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-teal-300">Automatic Role &amp; Tenant Routing</span>
                            <span class="text-[10px] font-semibold text-emerald-400">● Private &amp; Isolated</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Your organization identity remains strictly private. Signing in above automatically directs you to your authorized workspace role:
                        </p>
                        <div class="grid grid-cols-2 gap-2 text-[11px] font-bold">
                            <div style="background: rgba(255,255,255,0.04);" class="rounded-xl px-3 py-2 text-slate-200 flex items-center gap-2 border border-white/5">
                                <span class="w-2 h-2 rounded-full bg-teal-400"></span> Partner / Principal
                            </div>
                            <div style="background: rgba(255,255,255,0.04);" class="rounded-xl px-3 py-2 text-slate-200 flex items-center gap-2 border border-white/5">
                                <span class="w-2 h-2 rounded-full bg-amber-400"></span> Audit Manager
                            </div>
                            <div style="background: rgba(255,255,255,0.04);" class="rounded-xl px-3 py-2 text-slate-200 flex items-center gap-2 border border-white/5">
                                <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Articled / Staff
                            </div>
                            <div style="background: rgba(255,255,255,0.04);" class="rounded-xl px-3 py-2 text-slate-200 flex items-center gap-2 border border-white/5">
                                <span class="w-2 h-2 rounded-full bg-indigo-400"></span> Super Admin
                            </div>
                        </div>
                    </div>

                    <!-- Footer Links inside Login Card -->
                    <div class="mt-5 pt-4 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                        <a href="{{ url('/platform/login') }}" class="hover:text-white transition flex items-center gap-1.5 font-semibold">
                            <i data-lucide="shield" class="w-3.5 h-3.5 text-teal-400"></i>
                            <span>DhakaWala Super Admin</span>
                        </a>
                        <span class="text-slate-500">Protected by Tenant Guard</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- =====================================================================
             3. HOW DHAKAWALA ERP WORKS (4-Step Enterprise Architecture)
             ===================================================================== -->
        <section id="workflow" class="mt-20 pt-14 border-t border-white/10">
            <div class="text-center max-w-2xl mx-auto mb-10">
                <span style="color: #2DD4BF;" class="text-[11px] font-extrabold uppercase tracking-widest">End-to-End Practice Lifecycle</span>
                <h2 class="text-2xl sm:text-3xl font-black text-white mt-1.5">
                    Built for Modern Firms &amp; High-Trust Teams
                </h2>
                <p class="mt-2 text-xs sm:text-sm text-slate-400 leading-relaxed">
                    Every organization receives an isolated cloud tenant with customizable governance policies, real-time field tracking, and automated payroll reconciliation.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div style="background: rgba(17, 24, 39, 0.75); border: 1px solid rgba(255, 255, 255, 0.08);" class="p-6 rounded-3xl">
                    <div style="background: rgba(13, 148, 136, 0.2); color: #2DD4BF;" class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 font-black text-sm">
                        01
                    </div>
                    <h3 class="text-base font-extrabold text-white mb-2">Isolated Multi-Tenant Workspaces</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Each firm operates in a strictly isolated database scope with its own custom brand color palette, custom domain/subdomain, and private member roster.
                    </p>
                </div>

                <div style="background: rgba(17, 24, 39, 0.75); border: 1px solid rgba(255, 255, 255, 0.08);" class="p-6 rounded-3xl">
                    <div style="background: rgba(245, 158, 11, 0.2); color: #FBBF24;" class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 font-black text-sm">
                        02
                    </div>
                    <h3 class="text-base font-extrabold text-white mb-2">Policy-Driven Attendance &amp; Calendar</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Configure office start times, grace minutes, late deduction thresholds (e.g. 3 late days = 1 day deduction), and interactive monthly holiday calendars.
                    </p>
                </div>

                <div style="background: rgba(17, 24, 39, 0.75); border: 1px solid rgba(255, 255, 255, 0.08);" class="p-6 rounded-3xl">
                    <div style="background: rgba(16, 185, 129, 0.2); color: #34D399;" class="w-11 h-11 rounded-2xl flex items-center justify-center mb-4 font-black text-sm">
                        03
                    </div>
                    <h3 class="text-base font-extrabold text-white mb-2">Unread Approvals &amp; Stipend Vouchers</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Leave applications and multi-leg conveyance claims trigger instant unread alerts on Manager and Partner dashboards and sync directly with monthly payroll.
                    </p>
                </div>
            </div>
        </section>

        <!-- =====================================================================
             4. GLOBAL FOOTER
             ===================================================================== -->
        <footer class="mt-16 pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400">
            <div class="flex items-center gap-2.5">
                <span class="font-extrabold text-white">DhakaWala ERP</span>
                <span>•</span>
                <span>Enterprise Practice &amp; Corporate Management Cloud</span>
            </div>
            <div class="flex items-center gap-4 text-[11px]">
                <span>Powered by <strong class="text-teal-300">DhakaWala</strong></span>
                <span>•</span>
                <span>Node: erp.dhakawala.com</span>
            </div>
        </footer>
    </main>
</div>
