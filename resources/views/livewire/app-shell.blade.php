<div class="rac-shell min-h-screen bg-[#F8FAFC]">

    <!-- Mobile Drawer Backdrop -->
    @if($mobileMenuOpen)
        <div wire:click="closeMobileMenu" class="fixed inset-0 z-40 bg-slate-950/60 backdrop-blur-sm lg:hidden transition-opacity"></div>
    @endif

    <!-- =========================================================================
         1. SIDEBAR NAVIGATION (Dark Charcoal Corporate Obsidian)
         ========================================================================= -->
    <aside class="rac-sidebar {{ $sidebarCollapsed ? 'collapsed' : '' }} {{ $mobileMenuOpen ? 'mobile-open' : '' }}" aria-label="Sidebar Navigation">
        
        <!-- Firm Brand Header -->
        <div class="rac-sidebar-brand">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 shrink-0 rounded-lg bg-[#22252E] border border-white/10 flex items-center justify-center p-1.5 shadow-sm">
                    <img src="{{ asset('images/rac_logo.svg') }}" alt="Rahman Anis & Co. Emblem" class="w-full h-full object-contain">
                </div>
                <div class="rac-sidebar-brand-text truncate">
                    <span class="text-sm font-bold text-white tracking-tight leading-tight">Rahman Anis & Co.</span>
                    <span class="text-[11px] font-medium text-[#FCDF02] tracking-wider uppercase">Chartered Accountants</span>
                </div>
            </div>
        </div>

        <!-- Tenant / Multi-Firm Context Pill -->
        <div class="px-3.5 py-2.5 mx-3 mt-3 mb-1 rounded-lg bg-[#1C1E25] border border-white/5 flex items-center justify-between {{ $sidebarCollapsed ? 'hidden lg:hidden' : '' }}">
            <div class="flex items-center gap-2 truncate">
                <i data-lucide="building-2" class="w-3.5 h-3.5 text-[#F5AD16] shrink-0"></i>
                <div class="truncate">
                    <div class="text-[10px] text-slate-400 font-medium uppercase tracking-wider">Tenant Node</div>
                    <div class="text-xs font-semibold text-slate-200 truncate">erp.dhakawala.com</div>
                </div>
            </div>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-amber-500/10 text-[#F5AD16] border border-amber-500/20 font-semibold">SAAS</span>
        </div>

        <!-- Role Preview Switcher Badge in Sidebar -->
        <div class="px-3 my-2 {{ $sidebarCollapsed ? 'hidden' : '' }}">
            <div class="p-2.5 rounded-lg bg-[#22252E]/70 border border-white/5">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-[10px] uppercase tracking-wider text-slate-400 font-semibold">Preview Role:</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded font-medium bg-[#FCDF02] text-[#16181D]">
                        {{ $user['badge'] }}
                    </span>
                </div>
                <div class="grid grid-cols-2 gap-1 text-[11px]">
                    <button wire:click="setRole('partner')" type="button" class="px-2 py-1 rounded text-left transition-colors {{ $currentRole === 'partner' ? 'bg-[#FCDF02] text-[#16181D] font-semibold' : 'text-slate-300 hover:bg-white/5' }}">
                        Principal
                    </button>
                    <button wire:click="setRole('manager')" type="button" class="px-2 py-1 rounded text-left transition-colors {{ $currentRole === 'manager' ? 'bg-[#FCDF02] text-[#16181D] font-semibold' : 'text-slate-300 hover:bg-white/5' }}">
                        Manager
                    </button>
                    <button wire:click="setRole('student')" type="button" class="px-2 py-1 rounded text-left transition-colors {{ $currentRole === 'student' ? 'bg-[#FCDF02] text-[#16181D] font-semibold' : 'text-slate-300 hover:bg-white/5' }}">
                        Student
                    </button>
                    <button wire:click="setRole('admin')" type="button" class="px-2 py-1 rounded text-left transition-colors {{ $currentRole === 'admin' ? 'bg-[#FCDF02] text-[#16181D] font-semibold' : 'text-slate-300 hover:bg-white/5' }}">
                        Super Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="flex-1 overflow-y-auto px-2 py-2 space-y-1" aria-label="Main Navigation">
            
            <div class="px-3 pt-2 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 {{ $sidebarCollapsed ? 'hidden' : '' }}">
                Phase 01 Views
            </div>

            <button wire:click="setActiveTab('dashboard')" type="button" class="rac-nav-item w-full {{ $activeTab === 'dashboard' ? 'active' : '' }}">
                <i data-lucide="layout-dashboard" class="w-4 h-4 shrink-0"></i>
                <span class="rac-nav-text">Dashboard Shell</span>
            </button>

            <button wire:click="setActiveTab('components')" type="button" class="rac-nav-item w-full {{ $activeTab === 'components' ? 'active' : '' }}">
                <i data-lucide="component" class="w-4 h-4 shrink-0"></i>
                <span class="rac-nav-text">UI Components</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.2 rounded font-semibold bg-[#F5AD16]/20 text-[#F5AD16]">22 Lib</span>
            </button>

            <button wire:click="setActiveTab('tokens')" type="button" class="rac-nav-item w-full {{ $activeTab === 'tokens' ? 'active' : '' }}">
                <i data-lucide="palette" class="w-4 h-4 shrink-0"></i>
                <span class="rac-nav-text">Design Tokens</span>
            </button>

            <button wire:click="setActiveTab('architecture')" type="button" class="rac-nav-item w-full {{ $activeTab === 'architecture' ? 'active' : '' }}">
                <i data-lucide="layers" class="w-4 h-4 shrink-0"></i>
                <span class="rac-nav-text">Architecture Spec</span>
            </button>

            <!-- Phase 02: Authentication & Recovery Experience -->
            <div class="px-3 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 {{ $sidebarCollapsed ? 'hidden' : '' }}">
                Phase 02 Auth Portal
            </div>

            <a href="{{ url('/c/rac/login') }}" class="rac-nav-item w-full text-slate-300 hover:text-white" title="RA&C Firm Login">
                <i data-lucide="log-in" class="w-4 h-4 shrink-0 text-[#FCDF02]"></i>
                <span class="rac-nav-text">Firm Login (RA&C)</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded font-semibold bg-[#FCDF02]/20 text-[#FCDF02]">P02</span>
            </a>

            <a href="{{ url('/platform/login') }}" class="rac-nav-item w-full text-slate-300 hover:text-white" title="Platform Super Admin">
                <i data-lucide="shield" class="w-4 h-4 shrink-0 text-amber-400"></i>
                <span class="rac-nav-text">Platform Admin</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded font-semibold bg-amber-400/20 text-amber-300">P02</span>
            </a>

            <a href="{{ url('/c/rac/forgot-password') }}" class="rac-nav-item w-full text-slate-300 hover:text-white" title="Account Recovery">
                <i data-lucide="key" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text">Account Recovery</span>
            </a>

            <a href="{{ url('/c/rac/activate') }}" class="rac-nav-item w-full text-slate-300 hover:text-white" title="Invited Member Activation">
                <i data-lucide="user-plus" class="w-4 h-4 shrink-0 text-emerald-400"></i>
                <span class="rac-nav-text">Member Activation</span>
            </a>

            <!-- Planned ERP Modules (Taxonomy placeholders with Phase Badges) -->
            <div class="px-3 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500 {{ $sidebarCollapsed ? 'hidden' : '' }}">
                Future ERP Modules
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Scheduled for Phase 02">
                <i data-lucide="calendar-check" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Attendance</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">P02</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Scheduled for Phase 03">
                <i data-lucide="file-text" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Leave Applications</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">P03</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Scheduled for Phase 04">
                <i data-lucide="receipt" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Conveyance Claims</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">P04</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Scheduled for Phase 05">
                <i data-lucide="wallet" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Payroll & Stipend</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">P05</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Scheduled for Phase 06">
                <i data-lucide="shield-check" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Policy & Approvals</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">P06</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Notifications Center">
                <i data-lucide="bell" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Notifications</span>
                <span class="rac-nav-badge ml-auto text-[10px] px-1.5 py-0.5 rounded-full bg-[#EF4444] text-white font-bold">3</span>
            </div>

            <div class="rac-nav-item opacity-75 cursor-default hover:bg-transparent" title="Firm & System Settings">
                <i data-lucide="settings" class="w-4 h-4 shrink-0 text-slate-400"></i>
                <span class="rac-nav-text text-slate-300">Firm Settings</span>
            </div>
        </nav>

        <!-- Sidebar Collapse Button (Desktop Only) -->
        <div class="hidden lg:flex items-center justify-between px-3.5 py-2.5 border-t border-white/5 text-slate-400">
            <button wire:click="toggleSidebar" type="button" class="flex items-center gap-2 text-xs hover:text-white transition-colors w-full p-1 rounded hover:bg-white/5">
                <i data-lucide="{{ $sidebarCollapsed ? 'chevrons-right' : 'chevrons-left' }}" class="w-4 h-4 shrink-0"></i>
                <span class="{{ $sidebarCollapsed ? 'hidden' : 'block' }}">Collapse Sidebar</span>
            </button>
        </div>

        <!-- Sidebar Bottom User Profile Card -->
        <div class="p-3 border-t border-white/10 bg-[#121316]">
            <div class="flex items-center gap-3">
                <div class="rac-avatar w-9 h-9 shrink-0 bg-[#FCDF02] text-[#16181D] font-bold text-xs ring-2 ring-white/10">
                    {{ $user['initials'] }}
                    <span class="rac-avatar-dot rac-avatar-online"></span>
                </div>
                <div class="rac-profile-details min-w-0 flex-1 truncate">
                    <div class="text-xs font-semibold text-white truncate">{{ $user['name'] }}</div>
                    <div class="text-[11px] text-slate-400 truncate">{{ $user['role_title'] }}</div>
                </div>
                <button wire:click="triggerToast('info')" type="button" class="text-slate-400 hover:text-white p-1 rounded hover:bg-white/10 transition-colors" title="User Menu Preview">
                    <i data-lucide="log-out" class="w-4 h-4"></i>
                </button>
            </div>
        </div>
    </aside>

    <!-- =========================================================================
         2. MAIN CONTAINER & TOP HEADER
         ========================================================================= -->
    <div class="rac-main-container {{ $sidebarCollapsed ? 'sidebar-collapsed' : '' }}">
        
        <!-- Top Application Header -->
        <header class="rac-header">
            
            <!-- Left Side: Mobile Toggle, Desktop Toggle, Breadcrumb & Title -->
            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                <!-- Mobile Drawer Trigger -->
                <button wire:click="toggleMobileMenu" type="button" class="lg:hidden p-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shrink-0" aria-label="Toggle Navigation Drawer">
                    <i data-lucide="menu" class="w-5 h-5"></i>
                </button>

                <!-- Page Breadcrumbs & Title -->
                <div>
                    <nav class="flex items-center gap-1.5 text-[11px] sm:text-xs text-slate-500 font-medium" aria-label="Breadcrumb">
                        <span class="hover:text-slate-800 transition-colors">RA&C Central ERP</span>
                        <i data-lucide="chevron-right" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                        <span class="text-slate-700 capitalize">{{ $activeTab }}</span>
                    </nav>
                    <h1 class="text-xs sm:text-base lg:text-lg font-bold text-slate-900 tracking-tight leading-tight mt-0.5 truncate max-w-[130px] sm:max-w-none">
                        @if($activeTab === 'dashboard')
                            <span class="sm:hidden">Overview</span>
                            <span class="hidden sm:inline">Partner Executive Overview</span>
                        @elseif($activeTab === 'components')
                            <span class="sm:hidden">UI Library</span>
                            <span class="hidden sm:inline">Design System Component Library</span>
                        @elseif($activeTab === 'tokens')
                            <span class="sm:hidden">Tokens</span>
                            <span class="hidden sm:inline">RA&C Official Brand Tokens & Guidelines</span>
                        @else
                            <span class="sm:hidden">Blueprint</span>
                            <span class="hidden sm:inline">Architecture & Multi-Tenant Blueprint</span>
                        @endif
                    </h1>
                </div>
            </div>

            <!-- Center: Global Quick Search Input -->
            <div class="hidden lg:flex items-center max-w-xs xl:max-w-sm w-full mx-4">
                <div class="relative w-full">
                    <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                    <input id="global-search-input" type="text" placeholder="Search clients, articled students, vouchers..." 
                           class="w-full text-xs pl-9 pr-12 py-2 rounded-lg bg-slate-100/80 hover:bg-slate-100 focus:bg-white border border-slate-200 focus:border-[#F5AD16] focus:outline-none focus:ring-2 focus:ring-[#F5AD16]/20 transition-all text-slate-800 placeholder-slate-400">
                    <kbd class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-white text-slate-500 border border-slate-200 shadow-2xs font-mono">Ctrl+K</kbd>
                </div>
            </div>

            <!-- Right Side: Workspace Context, Notifications, Role Badge & User Avatar -->
            <div class="flex items-center gap-1.5 sm:gap-2.5 shrink-0">
                
                <!-- Firm Office Selector -->
                <div class="hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-100/90 border border-slate-200 text-xs">
                    <i data-lucide="map-pin" class="w-3.5 h-3.5 text-[#D98A00]"></i>
                    <span class="font-medium text-slate-700">EDB Trade Center</span>
                </div>

                <!-- Live Role Switcher Dropdown in Header -->
                <div class="hidden sm:block relative" x-data="{ open: false }">
                    <button @click="open = !open" type="button" class="flex items-center gap-1 sm:gap-1.5 text-xs font-semibold px-2 sm:px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 transition-colors" title="Switch Design Preview Role">
                        <span class="w-2 h-2 rounded-full bg-[#10B981] shrink-0"></span>
                        <span class="hidden sm:inline">Preview:</span>
                        <span class="truncate max-w-[80px] sm:max-w-none">{{ $user['badge'] }}</span>
                        <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-slate-400 shrink-0"></i>
                    </button>
                    
                    <div x-show="open" @click.outside="open = false" x-transition class="absolute right-0 mt-1 w-56 bg-white rounded-lg shadow-lg border border-slate-200 py-1 z-50 text-xs" style="display: none;">
                        <div class="px-3 py-1.5 border-b border-slate-100 font-semibold text-slate-500 text-[10px] uppercase">
                            Switch Design Preview Role
                        </div>
                        @foreach($roles as $key => $r)
                            <button wire:click="setRole('{{ $key }}')" @click="open = false" type="button" 
                                    class="w-full text-left px-3 py-2 hover:bg-slate-50 flex items-center justify-between {{ $currentRole === $key ? 'font-semibold text-slate-900 bg-amber-50/50' : 'text-slate-700' }}">
                                <div>
                                    <div class="text-xs">{{ $r['badge'] }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $r['name'] }}</div>
                                </div>
                                @if($currentRole === $key)
                                    <i data-lucide="check" class="w-3.5 h-3.5 text-[#D98A00]"></i>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Notifications Button with Pulse Badge -->
                <button wire:click="triggerToast('warning')" type="button" class="relative p-2 rounded-lg text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors shrink-0" title="3 unread notifications">
                    <i data-lucide="bell" class="w-4 h-4"></i>
                    <span class="absolute top-1.5 right-1.5 w-2 h-2 rounded-full bg-[#EF4444] ring-2 ring-white"></span>
                </button>

                <!-- User Avatar Pill -->
                <div class="flex items-center gap-2 pl-1.5 sm:pl-2 border-l border-slate-200 shrink-0">
                    <div class="rac-avatar w-8 h-8 bg-[#16181D] text-[#FCDF02] font-bold text-xs ring-1 ring-slate-200">
                        {{ $user['initials'] }}
                    </div>
                    <div class="hidden xl:block text-left">
                        <div class="text-xs font-semibold text-slate-900 leading-tight truncate max-w-[120px]">{{ $user['name'] }}</div>
                        <div class="text-[10px] text-slate-500 truncate max-w-[120px]">{{ $user['badge'] }}</div>
                    </div>
                    <a href="{{ url('/c/rac/login') }}" class="p-1.5 rounded-lg text-slate-500 hover:text-rose-600 hover:bg-rose-50 transition-colors shrink-0 ml-1" title="Sign Out to Practice Login">
                        <i data-lucide="log-out" class="w-4 h-4"></i>
                    </a>
                </div>
            </div>
        </header>

        <!-- =========================================================================
             3. MAIN CONTENT AREA
             ========================================================================= -->
        <main class="rac-content">
            
            <!-- Prototype Demo Safety Notice Banner -->
            <div class="mb-5 p-3 sm:p-3.5 rounded-xl bg-gradient-to-r from-amber-500/10 via-amber-50/70 to-yellow-500/10 border border-amber-300/40 text-amber-950 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2.5 shadow-2xs">
                <div class="flex items-center gap-2.5">
                    <span class="px-2 py-0.5 rounded font-bold text-[10px] tracking-wider uppercase bg-[#FCDF02] text-[#16181D] border border-amber-400 shrink-0">
                        Design Prototype
                    </span>
                    <p class="text-xs text-slate-700 leading-tight">
                        <strong class="font-semibold text-slate-900">Phase 01 UI Foundation Review:</strong>
                        All role switches, sign-offs, and stats are non-destructive UI simulations. Live databases, attendance, and payroll are intentionally deferred to future phases.
                    </p>
                </div>
                <div class="flex items-center gap-2 self-end sm:self-center shrink-0 text-[11px] text-amber-800 font-mono">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>Sandbox Safe</span>
                </div>
            </div>

            <!-- Phase 01 Interactive Tab Bar -->
            <div class="rac-tabs">
                <button wire:click="setActiveTab('dashboard')" type="button" class="rac-tab-item {{ $activeTab === 'dashboard' ? 'active' : '' }}">
                    <i data-lucide="layout-grid" class="w-4 h-4"></i>
                    <span>Dashboard Shell</span>
                </button>
                <button wire:click="setActiveTab('components')" type="button" class="rac-tab-item {{ $activeTab === 'components' ? 'active' : '' }}">
                    <i data-lucide="boxes" class="w-4 h-4"></i>
                    <span>Component Library (22)</span>
                </button>
                <button wire:click="setActiveTab('tokens')" type="button" class="rac-tab-item {{ $activeTab === 'tokens' ? 'active' : '' }}">
                    <i data-lucide="palette" class="w-4 h-4"></i>
                    <span>Design Tokens</span>
                </button>
                <button wire:click="setActiveTab('architecture')" type="button" class="rac-tab-item {{ $activeTab === 'architecture' ? 'active' : '' }}">
                    <i data-lucide="network" class="w-4 h-4"></i>
                    <span>Architecture & Spec</span>
                </button>
            </div>

            <!-- =====================================================================
                 TAB 1: DASHBOARD SHELL PREVIEW
                 ===================================================================== -->
            @if($activeTab === 'dashboard')
                <div class="space-y-6">
                    
                    <!-- Welcome Banner with Brand Identity & Partner Unit -->
                    <div class="bg-gradient-to-r from-[#16181D] via-[#1C1E25] to-[#22252E] rounded-xl p-5 lg:p-6 text-white border border-white/5 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-[#FCDF02] p-2 flex items-center justify-center shrink-0 shadow-sm">
                                <img src="{{ asset('images/rac_logo.svg') }}" alt="Logo" class="w-full h-full object-contain">
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-xs px-2 py-0.5 rounded bg-white/10 text-[#FCDF02] font-semibold uppercase tracking-wider">
                                        {{ $user['partner_group'] ?? $user['partner_unit'] ?? 'Partner Unit 01' }}
                                    </span>
                                    <span class="text-xs text-slate-400">• {{ date('l, d F Y') }}</span>
                                </div>
                                <h2 class="text-lg lg:text-xl font-bold text-white mt-1">
                                    Welcome back, {{ $user['name'] }}
                                </h2>
                                <p class="text-xs text-slate-300 mt-0.5">
                                    CA Practice Management & Central SaaS Operational Hub • Rahman Anis & Co.
                                </p>
                            </div>
                        </div>

                        <!-- Action Triggers -->
                        <div class="flex items-center gap-2.5 shrink-0">
                            <button wire:click="openModal" type="button" class="rac-btn rac-btn-secondary rac-btn-sm">
                                <i data-lucide="filter" class="w-3.5 h-3.5"></i>
                                <span>Filter Workspace</span>
                            </button>
                            <button wire:click="openConfirmModal" type="button" class="rac-btn rac-btn-primary rac-btn-sm">
                                <i data-lucide="check-circle" class="w-3.5 h-3.5"></i>
                                <span>Review Sign-offs (7)</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 Summary KPI Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        
                        <!-- Card 1: Articled Students -->
                        <div class="rac-summary-card rac-summary-card-accent">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Active Students</span>
                                    <div class="text-2xl font-bold text-slate-900 mt-1">28</div>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-amber-50 text-[#D98A00] flex items-center justify-center">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                                <span class="font-semibold text-emerald-600 flex items-center gap-0.5">
                                    <i data-lucide="trending-up" class="w-3.5 h-3.5"></i> 100%
                                </span>
                                <span class="text-slate-400">ICAB registered for Unit 01</span>
                            </div>
                        </div>

                        <!-- Card 2: Attendance Today -->
                        <div class="rac-summary-card rac-summary-card-accent">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Present Today</span>
                                    <div class="text-2xl font-bold text-slate-900 mt-1">26 / 28</div>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center">
                                    <i data-lucide="user-check" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                                <span class="rac-badge rac-badge-success text-[10px] py-0 px-1.5">92.8% Attendance</span>
                                <span class="text-slate-400">• 2 on client audit</span>
                            </div>
                        </div>

                        <!-- Card 3: Pending Approvals -->
                        <div class="rac-summary-card rac-summary-card-accent">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Pending Sign-offs</span>
                                    <div class="text-2xl font-bold text-slate-900 mt-1">7 Requests</div>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                                <span class="rac-badge rac-badge-warning text-[10px] py-0 px-1.5">3 Leave / 4 Conveyance</span>
                            </div>
                        </div>

                        <!-- Card 4: Monthly Stipend Volume -->
                        <div class="rac-summary-card rac-summary-card-accent">
                            <div class="flex items-start justify-between">
                                <div>
                                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">Monthly Stipend</span>
                                    <div class="text-2xl font-bold text-slate-900 mt-1">৳ 324,500</div>
                                </div>
                                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                                    <i data-lucide="banknote" class="w-5 h-5"></i>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                                <span class="text-slate-500 font-medium">Fiscal Month: October 2026</span>
                            </div>
                        </div>
                    </div>

                    <!-- Two Column Section: Recent Attendance Logs & Pending Sign-offs Table -->
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        
                        <!-- Left 2 Cols: Representative Attendance & Audit Log Table -->
                        <div class="lg:col-span-2 rac-table-container">
                            <div class="rac-card-header">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Today's Articled Student Attendance Logs</h3>
                                    <p class="text-xs text-slate-500">Real-time attendance stream under Partner Unit 01</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="rac-badge rac-badge-neutral text-[11px]">28 Total Enrolled</span>
                                    <button wire:click="triggerToast('info')" type="button" class="rac-btn rac-btn-secondary rac-btn-sm text-xs">
                                        <i data-lucide="download" class="w-3.5 h-3.5"></i> Export
                                    </button>
                                </div>
                            </div>

                            <div class="rac-table-scroll">
                                <table class="rac-table">
                                    <thead>
                                        <tr>
                                            <th>Student & Reg No</th>
                                            <th>Designation</th>
                                            <th>Time In</th>
                                            <th>Audit Client Location</th>
                                            <th>Status</th>
                                            <th class="text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-2.5">
                                                    <div class="rac-avatar w-7 h-7 bg-slate-100 text-slate-700 text-[11px] font-semibold">ZS</div>
                                                    <div>
                                                        <div class="font-medium text-slate-900 text-xs">Zubair Hossain Siddique</div>
                                                        <div class="text-[11px] text-slate-400">ICAB Reg: 4128</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-slate-600">Senior Articled Clerk</td>
                                            <td class="text-xs font-mono text-slate-700">09:12 AM</td>
                                            <td class="text-xs text-slate-700">Disari Industries Ltd. (Tejgaon)</td>
                                            <td><span class="rac-badge rac-badge-success"><span class="rac-badge-dot"></span> Verified On-Site</span></td>
                                            <td class="text-right">
                                                <button wire:click="triggerToast('info')" type="button" class="text-xs text-[#D98A00] font-semibold hover:underline">View Log</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-2.5">
                                                    <div class="rac-avatar w-7 h-7 bg-slate-100 text-slate-700 text-[11px] font-semibold">FA</div>
                                                    <div>
                                                        <div class="font-medium text-slate-900 text-xs">Farhana Akhter</div>
                                                        <div class="text-[11px] text-slate-400">ICAB Reg: 4410</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-slate-600">Articled Student (2nd Year)</td>
                                            <td class="text-xs font-mono text-slate-700">09:28 AM</td>
                                            <td class="text-xs text-slate-700">EDB Trade Center (Karwan Bazar)</td>
                                            <td><span class="rac-badge rac-badge-success"><span class="rac-badge-dot"></span> Head Office</span></td>
                                            <td class="text-right">
                                                <button wire:click="triggerToast('info')" type="button" class="text-xs text-[#D98A00] font-semibold hover:underline">View Log</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-2.5">
                                                    <div class="rac-avatar w-7 h-7 bg-slate-100 text-slate-700 text-[11px] font-semibold">MR</div>
                                                    <div>
                                                        <div class="font-medium text-slate-900 text-xs">Mahmudur Rahman</div>
                                                        <div class="text-[11px] text-slate-400">ICAB Reg: 4621</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-slate-600">Junior Articled Clerk</td>
                                            <td class="text-xs font-mono text-slate-700">09:44 AM</td>
                                            <td class="text-xs text-slate-700">Jamuna Bank PLC. (Banani Branch)</td>
                                            <td><span class="rac-badge rac-badge-warning"><span class="rac-badge-dot"></span> Geo-radius Pending</span></td>
                                            <td class="text-right">
                                                <button wire:click="triggerToast('warning')" type="button" class="text-xs text-amber-600 font-semibold hover:underline">Verify</button>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td>
                                                <div class="flex items-center gap-2.5">
                                                    <div class="rac-avatar w-7 h-7 bg-slate-100 text-slate-700 text-[11px] font-semibold">SA</div>
                                                    <div>
                                                        <div class="font-medium text-slate-900 text-xs">Shafiul Alam</div>
                                                        <div class="text-[11px] text-slate-400">ICAB Reg: 4305</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-xs text-slate-600">Audit Associate</td>
                                            <td class="text-xs font-mono text-slate-700">—</td>
                                            <td class="text-xs text-slate-500">Approved Medical Leave</td>
                                            <td><span class="rac-badge rac-badge-info"><span class="rac-badge-dot"></span> Sick Leave</span></td>
                                            <td class="text-right">
                                                <button wire:click="triggerToast('info')" type="button" class="text-xs text-slate-500 font-semibold hover:underline">Details</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="rac-pagination">
                                <span>Showing 4 of 28 records</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" class="rac-page-btn" disabled>Prev</button>
                                    <button type="button" class="rac-page-btn active">1</button>
                                    <button type="button" class="rac-page-btn">2</button>
                                    <button type="button" class="rac-page-btn">Next</button>
                                </div>
                            </div>
                        </div>

                        <!-- Right 1 Col: Pending Partner Sign-offs Queue -->
                        <div class="rac-card">
                            <div class="rac-card-header">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Sign-off Approvals</h3>
                                    <p class="text-xs text-slate-500">Requires Partner Sign-off</p>
                                </div>
                                <span class="rac-badge rac-badge-brand text-[10px]">7 Pending</span>
                            </div>

                            <div class="divide-y divide-slate-100">
                                
                                <!-- Item 1 -->
                                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">CONVEYANCE</span>
                                                <span class="text-xs font-semibold text-slate-900 truncate">৳ 1,850</span>
                                            </div>
                                            <div class="text-xs text-slate-600 mt-1">Audit Visit: Disari Industries Ltd.</div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">By Zubair Hossain • 12 Oct 2026</div>
                                        </div>
                                        <div class="flex flex-col gap-1 shrink-0">
                                            <button wire:click="triggerToast('success')" type="button" class="rac-btn rac-btn-primary rac-btn-sm text-[11px] py-1 px-2">
                                                Sign-off
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Item 2 -->
                                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-blue-100 text-blue-800">EXAM LEAVE</span>
                                                <span class="text-xs font-semibold text-slate-900">4 Days (18–22 Oct)</span>
                                            </div>
                                            <div class="text-xs text-slate-600 mt-1">ICAB Certificate Level Exams</div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">By Farhana Akhter • 11 Oct 2026</div>
                                        </div>
                                        <div class="flex flex-col gap-1 shrink-0">
                                            <button wire:click="triggerToast('success')" type="button" class="rac-btn rac-btn-primary rac-btn-sm text-[11px] py-1 px-2">
                                                Sign-off
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Item 3 -->
                                <div class="p-3.5 hover:bg-slate-50 transition-colors">
                                    <div class="flex items-start justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-[10px] font-bold px-1.5 py-0.2 rounded bg-amber-100 text-amber-800">CONVEYANCE</span>
                                                <span class="text-xs font-semibold text-slate-900">৳ 940</span>
                                            </div>
                                            <div class="text-xs text-slate-600 mt-1">NBR / Large Taxpayer Unit (LTU)</div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">By Mahmudur Rahman • 13 Oct 2026</div>
                                        </div>
                                        <div class="flex flex-col gap-1 shrink-0">
                                            <button wire:click="triggerToast('success')" type="button" class="rac-btn rac-btn-primary rac-btn-sm text-[11px] py-1 px-2">
                                                Sign-off
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="p-3 bg-slate-50 border-t border-slate-100 text-center">
                                <button wire:click="openConfirmModal" type="button" class="text-xs font-semibold text-[#D98A00] hover:text-[#B45309]">
                                    Review All 7 Pending Requests →
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- =====================================================================
                 TAB 2: DESIGN SYSTEM COMPONENT LIBRARY (All 22 Components)
                 ===================================================================== -->
            @if($activeTab === 'components')
                <div class="space-y-8">
                    
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl bg-white border border-slate-200 shadow-xs">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">RA&C Design System Component Matrix</h2>
                            <p class="text-xs text-slate-500">Live preview of all 22 required enterprise SaaS components built with Tailwind CSS & Blade</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button wire:click="toggleSkeletons" type="button" class="rac-btn rac-btn-secondary rac-btn-sm text-xs">
                                <i data-lucide="sparkles" class="w-3.5 h-3.5"></i>
                                <span>{{ $showSkeletons ? 'Hide Skeleton Mode' : 'Toggle Skeleton State' }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- 1. Buttons Matrix -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">1. Button Styles & States (High Contrast Yellow, Neutral, Destructive)</h3>
                            <span class="text-xs text-slate-400">Accessible Focus & States</span>
                        </div>
                        <div class="rac-card-body space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <button wire:click="triggerToast('success')" type="button" class="rac-btn rac-btn-primary">
                                    <i data-lucide="check" class="w-4 h-4"></i> Primary Button
                                </button>
                                <button wire:click="triggerToast('info')" type="button" class="rac-btn rac-btn-secondary">
                                    <i data-lucide="copy" class="w-4 h-4"></i> Secondary Button
                                </button>
                                <button wire:click="openConfirmModal" type="button" class="rac-btn rac-btn-destructive">
                                    <i data-lucide="trash-2" class="w-4 h-4"></i> Destructive Button
                                </button>
                                <button wire:click="triggerToast('info')" type="button" class="rac-btn rac-btn-icon border border-slate-200" title="Settings">
                                    <i data-lucide="settings" class="w-4 h-4"></i>
                                </button>
                                <button type="button" class="rac-btn rac-btn-primary" disabled>
                                    Disabled State
                                </button>
                            </div>
                            <div class="flex flex-wrap items-center gap-3 pt-3 border-t border-slate-100">
                                <span class="text-xs text-slate-500 font-medium mr-2">Sizing Scale:</span>
                                <button type="button" class="rac-btn rac-btn-primary rac-btn-sm">Small (.rac-btn-sm)</button>
                                <button type="button" class="rac-btn rac-btn-primary">Default Size</button>
                                <button type="button" class="rac-btn rac-btn-primary rac-btn-lg">Large (.rac-btn-lg)</button>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Form Inputs & Controls -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">2. Form Fields, Dropdowns, Date Picker & Validation Messages</h3>
                            <span class="text-xs text-slate-400">Enterprise Form Controls</span>
                        </div>
                        <div class="rac-card-body">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                                
                                <div class="rac-form-group">
                                    <label class="rac-form-label">Client Legal Name</label>
                                    <input type="text" value="Disari Industries (Pvt.) Ltd." class="rac-input">
                                    <span class="rac-form-hint">Corporate registered entity title</span>
                                </div>

                                <div class="rac-form-group">
                                    <label class="rac-form-label">Engagement Category</label>
                                    <select class="rac-select">
                                        <option>Statutory Audit (FY 2025-26)</option>
                                        <option>Corporate Tax Return & Assessment</option>
                                        <option>Special Investigation & Due Diligence</option>
                                        <option>Internal Control Advisory</option>
                                    </select>
                                    <span class="rac-form-hint">Governed by ICAB guidelines</span>
                                </div>

                                <div class="rac-form-group">
                                    <label class="rac-form-label">Audit Period Cut-off Date</label>
                                    <input type="date" value="2026-06-30" class="rac-date">
                                    <span class="rac-form-hint">Financial year closing date</span>
                                </div>

                                <div class="rac-form-group">
                                    <label class="rac-form-label">Search / Filter Box</label>
                                    <div class="relative">
                                        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2"></i>
                                        <input type="text" placeholder="Type student registration number..." class="rac-input pl-9">
                                    </div>
                                    <span class="rac-form-hint">Searchable across current partner unit</span>
                                </div>

                                <div class="rac-form-group">
                                    <label class="rac-form-label">Input with Error State</label>
                                    <input type="text" value="INVALID_VOUCHER_123" class="rac-input border-red-500 focus:border-red-500 focus:ring-red-200">
                                    <span class="rac-form-error">
                                        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Voucher code format not recognized.
                                    </span>
                                </div>

                                <div class="rac-form-group">
                                    <label class="rac-form-label">Input with Success State</label>
                                    <input type="text" value="mrzillur@gmail.com" class="rac-input border-emerald-500 focus:border-emerald-500 focus:ring-emerald-200">
                                    <span class="text-[11px] text-emerald-600 flex items-center gap-1 font-medium">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i> Authorized auditor email verified.
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 3. Status Badges & Avatars -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        
                        <!-- Badges -->
                        <div class="rac-card">
                            <div class="rac-card-header">
                                <h3 class="text-sm font-bold text-slate-900">3. Status Badge Components</h3>
                                <span class="text-xs text-slate-400">Semantic & Brand Tokens</span>
                            </div>
                            <div class="rac-card-body space-y-3">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rac-badge rac-badge-success"><span class="rac-badge-dot"></span> Approved / Active</span>
                                    <span class="rac-badge rac-badge-warning"><span class="rac-badge-dot"></span> Pending Sign-off</span>
                                    <span class="rac-badge rac-badge-danger"><span class="rac-badge-dot"></span> Rejected / Flagged</span>
                                    <span class="rac-badge rac-badge-info"><span class="rac-badge-dot"></span> Processing Audit</span>
                                    <span class="rac-badge rac-badge-neutral"><span class="rac-badge-dot"></span> Draft Voucher</span>
                                    <span class="rac-badge rac-badge-brand"><span class="rac-badge-dot"></span> Partner Tier 01</span>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Avatars -->
                        <div class="rac-card">
                            <div class="rac-card-header">
                                <h3 class="text-sm font-bold text-slate-900">4. Profile Avatars & Presence Dots</h3>
                                <span class="text-xs text-slate-400">Single & Initials</span>
                            </div>
                            <div class="rac-card-body flex items-center gap-4">
                                <div class="rac-avatar w-12 h-12 bg-[#FCDF02] text-[#16181D] font-bold text-sm shadow-sm">
                                    MH
                                    <span class="rac-avatar-dot rac-avatar-online"></span>
                                </div>
                                <div class="rac-avatar w-10 h-10 bg-slate-900 text-white font-bold text-xs">
                                    TA
                                    <span class="rac-avatar-dot rac-avatar-online"></span>
                                </div>
                                <div class="rac-avatar w-8 h-8 bg-amber-100 text-amber-800 font-bold text-xs">
                                    ZS
                                    <span class="rac-avatar-dot rac-avatar-away"></span>
                                </div>
                                <div class="rac-avatar w-7 h-7 bg-slate-200 text-slate-700 font-semibold text-[10px]">
                                    FA
                                </div>
                                <div class="text-xs text-slate-500 pl-2 border-l border-slate-200">
                                    Dynamic presence indicator (online, away, on-site)
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Interactive Dialogs & Toasts -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">5. Interactive Modals, Confirmations & Toast Notifications</h3>
                            <span class="text-xs text-slate-400">Live Client-side Triggers</span>
                        </div>
                        <div class="rac-card-body space-y-4">
                            <div class="flex flex-wrap items-center gap-3">
                                <button wire:click="openModal" type="button" class="rac-btn rac-btn-secondary">
                                    <i data-lucide="layers" class="w-4 h-4"></i> Open Modal Dialog
                                </button>
                                <button wire:click="openConfirmModal" type="button" class="rac-btn rac-btn-secondary text-amber-700">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i> Open Confirmation Alert
                                </button>
                                <button wire:click="triggerToast('success')" type="button" class="rac-btn rac-btn-secondary text-emerald-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i> Trigger Success Toast
                                </button>
                                <button wire:click="triggerToast('warning')" type="button" class="rac-btn rac-btn-secondary text-amber-700">
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-amber-600"></i> Trigger Warning Toast
                                </button>
                                <button wire:click="triggerToast('danger')" type="button" class="rac-btn rac-btn-secondary text-red-700">
                                    <i data-lucide="alert-octagon" class="w-4 h-4 text-red-600"></i> Trigger Danger Toast
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- 5. Skeleton Loading State Demonstration -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">6. Skeleton Shimmer Loading Component</h3>
                            <span class="text-xs text-slate-400">Used during async data fetching</span>
                        </div>
                        <div class="rac-card-body space-y-4">
                            @if($showSkeletons)
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="p-4 border border-slate-200 rounded-lg space-y-3">
                                        <div class="rac-skeleton h-4 w-1/3"></div>
                                        <div class="rac-skeleton h-8 w-1/2"></div>
                                        <div class="rac-skeleton h-3 w-3/4"></div>
                                    </div>
                                    <div class="p-4 border border-slate-200 rounded-lg space-y-3">
                                        <div class="rac-skeleton h-4 w-1/3"></div>
                                        <div class="rac-skeleton h-8 w-1/2"></div>
                                        <div class="rac-skeleton h-3 w-3/4"></div>
                                    </div>
                                    <div class="p-4 border border-slate-200 rounded-lg space-y-3">
                                        <div class="rac-skeleton h-4 w-1/3"></div>
                                        <div class="rac-skeleton h-8 w-1/2"></div>
                                        <div class="rac-skeleton h-3 w-3/4"></div>
                                    </div>
                                </div>
                            @else
                                <div class="text-xs text-slate-600">
                                    Click <strong>"Toggle Skeleton State"</strong> above to preview the shimmer placeholders for cards, headers, and table rows.
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- 6. Empty State Component -->
                    <div class="rac-empty-state">
                        <div class="rac-empty-icon">
                            <i data-lucide="inbox" class="w-6 h-6"></i>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900">No Discrepancies Found in Audit Sample</h4>
                        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                            All statutory balance confirmation letters for Disari Industries Ltd. have been reconciled with Jamuna Bank PLC. records.
                        </p>
                        <div class="mt-4">
                            <button wire:click="triggerToast('info')" type="button" class="rac-btn rac-btn-secondary rac-btn-sm">
                                <i data-lucide="plus" class="w-3.5 h-3.5"></i> Add New Audit Sample
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- =====================================================================
                 TAB 3: DESIGN TOKENS & SYSTEM
                 ===================================================================== -->
            @if($activeTab === 'tokens')
                <div class="space-y-8">
                    
                    <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-xs">
                        <h2 class="text-base font-bold text-slate-900">RA&C Official Brand Identity & Design Tokens</h2>
                        <p class="text-xs text-slate-500">
                            Derived directly from the official Rahman Anis & Co. letterhead artwork and corporate guidelines.
                        </p>
                    </div>

                    <!-- Brand Color Swatches -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">Official Corporate Color Palette</h3>
                            <span class="text-xs font-mono text-slate-400">Tokens & Hex Values</span>
                        </div>
                        <div class="rac-card-body">
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                                
                                <!-- Brand Yellow -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <div class="h-20 bg-[#FCDF02] flex items-end p-2.5">
                                        <span class="text-xs font-bold text-[#16181D]">Brand Yellow</span>
                                    </div>
                                    <div class="p-3 bg-white text-xs space-y-1">
                                        <div class="font-mono font-bold text-slate-900">#FCDF02</div>
                                        <div class="text-[11px] text-slate-500">Primary Brand Accent</div>
                                        <div class="text-[10px] text-emerald-600 font-medium">Text: Dark Charcoal</div>
                                    </div>
                                </div>

                                <!-- Golden Amber -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <div class="h-20 bg-[#F5AD16] flex items-end p-2.5">
                                        <span class="text-xs font-bold text-[#16181D]">Golden Amber</span>
                                    </div>
                                    <div class="p-3 bg-white text-xs space-y-1">
                                        <div class="font-mono font-bold text-slate-900">#F5AD16</div>
                                        <div class="text-[11px] text-slate-500">Logo Facet & Glow</div>
                                        <div class="text-[10px] text-slate-500 font-medium">Warm Corporate Gold</div>
                                    </div>
                                </div>

                                <!-- Dark Charcoal / Sidebar -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <div class="h-20 bg-[#16181D] flex items-end p-2.5">
                                        <span class="text-xs font-bold text-white">Dark Charcoal</span>
                                    </div>
                                    <div class="p-3 bg-white text-xs space-y-1">
                                        <div class="font-mono font-bold text-slate-900">#16181D</div>
                                        <div class="text-[11px] text-slate-500">Sidebar & Logo Arch</div>
                                        <div class="text-[10px] text-slate-500 font-medium">Enterprise Slate</div>
                                    </div>
                                </div>

                                <!-- Pure White -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <div class="h-20 bg-white border-b border-slate-100 flex items-end p-2.5">
                                        <span class="text-xs font-bold text-slate-900">Pure White</span>
                                    </div>
                                    <div class="p-3 bg-white text-xs space-y-1">
                                        <div class="font-mono font-bold text-slate-900">#FFFFFF</div>
                                        <div class="text-[11px] text-slate-500">Cards & Surface Panels</div>
                                        <div class="text-[10px] text-slate-500 font-medium">High Contrast Canvas</div>
                                    </div>
                                </div>

                                <!-- Canvas Light Gray -->
                                <div class="border border-slate-200 rounded-xl overflow-hidden shadow-2xs">
                                    <div class="h-20 bg-[#F8FAFC] border-b border-slate-200 flex items-end p-2.5">
                                        <span class="text-xs font-bold text-slate-900">Canvas Gray</span>
                                    </div>
                                    <div class="p-3 bg-white text-xs space-y-1">
                                        <div class="font-mono font-bold text-slate-900">#F8FAFC</div>
                                        <div class="text-[11px] text-slate-500">Application Canvas</div>
                                        <div class="text-[10px] text-slate-500 font-medium">Subtle Soft Gray</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Typography Scale -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">Typography Scale (Font: Inter)</h3>
                            <span class="text-xs text-slate-400">Strict Hierarchy</span>
                        </div>
                        <div class="rac-card-body divide-y divide-slate-100">
                            <div class="py-2.5 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400 w-36 font-mono">Page Title (24px)</span>
                                <span class="text-2xl font-bold text-slate-900 flex-1">Rahman Anis & Co., Chartered Accountants</span>
                            </div>
                            <div class="py-2.5 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400 w-36 font-mono">Section Title (18px)</span>
                                <span class="text-lg font-bold text-slate-900 flex-1">Statutory Audit & Assurance Services</span>
                            </div>
                            <div class="py-2.5 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400 w-36 font-mono">Card Header (16px)</span>
                                <span class="text-base font-semibold text-slate-900 flex-1">Articled Student Monthly Stipend Schedule</span>
                            </div>
                            <div class="py-2.5 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400 w-36 font-mono">Body Regular (14px)</span>
                                <span class="text-sm text-slate-700 flex-1">Independent auditor report confirmation under ICAB standards.</span>
                            </div>
                            <div class="py-2.5 flex items-baseline justify-between">
                                <span class="text-xs text-slate-400 w-36 font-mono">Caption / Meta (12px)</span>
                                <span class="text-xs text-slate-500 flex-1">EDB Trade Center (14th Floor), 93 Kazi Nazrul Islam Avenue, Dhaka-1215</span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- =====================================================================
                 TAB 4: ARCHITECTURE & MULTI-TENANT BLUEPRINT
                 ===================================================================== -->
            @if($activeTab === 'architecture')
                <div class="space-y-6">
                    
                    <div class="p-5 rounded-xl bg-white border border-slate-200 shadow-xs">
                        <h2 class="text-base font-bold text-slate-900">RA&C ERP SaaS — System Architecture (Phase 01)</h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Standard Laravel Monolith with Livewire, Blade Components, Filament Admin compatibility, and multi-tenant CA firm isolation.
                        </p>
                    </div>

                    <!-- Multi-Tenant Hierarchy Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        
                        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-2">
                                <span class="w-6 h-6 rounded-full bg-slate-900 text-white flex items-center justify-center text-[10px]">T1</span>
                                <span>Platform Super Admin</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Global SaaS operator on <code>erp.dhakawala.com</code>. Manages CA firm tenants, subscriptions, and system audits.
                            </p>
                        </div>

                        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-2">
                                <span class="w-6 h-6 rounded-full bg-[#D98A00] text-white flex items-center justify-center text-[10px]">T2</span>
                                <span>CA Firm / Partners</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                E.g. Rahman Anis & Co. Configurable partner units (3 initial Partners: Mohitul Haq, Anisur Rahman, etc.).
                            </p>
                        </div>

                        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-2">
                                <span class="w-6 h-6 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px]">T3</span>
                                <span>Audit Managers</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Assigned to specific Partner Units. Oversees engagement field teams, first-level approval for leaves & vouchers.
                            </p>
                        </div>

                        <div class="p-4 rounded-xl bg-white border border-slate-200 shadow-2xs">
                            <div class="flex items-center gap-2 text-xs font-bold text-slate-900 mb-2">
                                <span class="w-6 h-6 rounded-full bg-emerald-600 text-white flex items-center justify-center text-[10px]">T4</span>
                                <span>Articled Students</span>
                            </div>
                            <p class="text-xs text-slate-500">
                                Mobile-first attendance check-ins, ICAB leave applications, conveyance claim voucher submissions.
                            </p>
                        </div>
                    </div>

                    <!-- Deliverable Checklist -->
                    <div class="rac-card">
                        <div class="rac-card-header">
                            <h3 class="text-sm font-bold text-slate-900">Phase 01 Deliverables & Verification Checklist</h3>
                            <span class="rac-badge rac-badge-success">Phase 01 Complete</span>
                        </div>
                        <div class="rac-card-body space-y-3">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Initialized Laravel Monolith with Livewire & Tailwind CSS</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Preserved genuine RA&C Letterhead branding & vector SVG logo</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Built Dark Charcoal Sidebar with collapse & mobile drawer</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Built Responsive Top Header with Search, Role Switcher & User Avatar</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Created 22 Reusable UI Components in Tailwind & Blade</span>
                                </div>
                                <div class="flex items-center gap-2 text-slate-700">
                                    <i data-lucide="check-circle" class="w-4 h-4 text-emerald-600"></i>
                                    <span>Compiles cleanly via Laravel Vite without errors</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </main>

        <!-- Subtle Enterprise Footer -->
        <footer class="py-4 px-6 border-t border-slate-200 bg-white/70 text-xs text-slate-500 flex flex-col sm:flex-row items-center justify-between gap-2 mt-auto">
            <div class="flex items-center gap-2">
                <span class="font-bold text-slate-800">RA&C Central ERP SaaS</span>
                <span>•</span>
                <span>Rahman Anis & Co., Chartered Accountants</span>
            </div>
            <div class="flex items-center gap-3 text-[11px] text-slate-400">
                <span>Domain: erp.dhakawala.com</span>
                <span>•</span>
                <span>Laravel 13 & Livewire 4</span>
                <span>•</span>
                <span>Phase 01 Foundation</span>
            </div>
        </footer>
    </div>

    <!-- =========================================================================
         4. MODAL DIALOGS (Accessible native HTML5 dialogs with blur backdrop)
         ========================================================================= -->
    @if($isModalOpen)
        <div wire:keydown.escape.window="closeModal" 
             wire:click.self="closeModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
             role="dialog" 
             aria-modal="true" 
             aria-labelledby="filter-modal-title">
            <div wire:click.stop class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-lg w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div>
                        <h3 id="filter-modal-title" class="text-sm font-bold text-slate-900">[Design Preview] Filter Workspace Engagement</h3>
                        <p class="text-xs text-slate-500">Contextual query simulation for Partner Unit 01</p>
                    </div>
                    <button wire:click="closeModal" type="button" class="text-slate-400 hover:text-slate-700 p-1 rounded-md hover:bg-slate-100 transition-colors" aria-label="Close dialog">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>
                </div>
                <div class="p-5 space-y-4">
                    <div class="p-3 rounded-lg bg-amber-50/70 border border-amber-200/70 text-xs text-amber-900 flex items-start gap-2">
                        <i data-lucide="info" class="w-4 h-4 text-amber-600 shrink-0 mt-0.5"></i>
                        <span>Prototype Notice: Filter criteria selections in Phase 01 demonstrate UI layout and responsiveness only. No database queries are executed.</span>
                    </div>
                    <div class="rac-form-group">
                        <label class="rac-form-label">Client Industry Sector</label>
                        <select class="rac-select">
                            <option>Textiles & Garments (Disari, Natural Denim)</option>
                            <option>Commercial Banks & Financial Institutions</option>
                            <option>Real Estate & Development (REHAB)</option>
                        </select>
                    </div>
                    <div class="rac-form-group">
                        <label class="rac-form-label">Articled Student Batch</label>
                        <input type="text" placeholder="e.g. 2025/2026 Intake" class="rac-input">
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button wire:click="closeModal" type="button" class="rac-btn rac-btn-secondary rac-btn-sm">Cancel</button>
                    <button wire:click="closeModal" type="button" class="rac-btn rac-btn-primary rac-btn-sm">Apply Filters (Preview)</button>
                </div>
            </div>
        </div>
    @endif

    <!-- Confirmation Modal Dialog -->
    @if($isConfirmModalOpen)
        <div wire:keydown.escape.window="closeConfirmModal" 
             wire:click.self="closeConfirmModal"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs"
             role="dialog" 
             aria-modal="true" 
             aria-labelledby="confirm-modal-title">
            <div wire:click.stop class="bg-white rounded-xl shadow-xl border border-slate-200 max-w-md w-full overflow-hidden animate-in fade-in zoom-in-95 duration-150">
                <div class="p-5">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-full bg-amber-50 text-[#D98A00] flex items-center justify-center shrink-0">
                            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        </div>
                        <div>
                            <h3 id="confirm-modal-title" class="text-sm font-bold text-slate-900">[Design Preview] Confirm Sign-off Simulation</h3>
                            <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                                You are previewing the sign-off dialog for Partner Unit 01. In this design prototype, no live financial records, attendance logs, or database transactions are modified.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3.5 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button wire:click="closeConfirmModal" type="button" class="rac-btn rac-btn-secondary rac-btn-sm">Cancel</button>
                    <button wire:click="closeConfirmModal" type="button" class="rac-btn rac-btn-primary rac-btn-sm">Simulate Sign-off</button>
                </div>
            </div>
        </div>
    @endif

</div>
