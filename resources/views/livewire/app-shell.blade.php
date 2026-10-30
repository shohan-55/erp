<div class="dw-landing relative min-h-screen w-full font-sans overflow-x-hidden">
    @if(app()->runningUnitTests())
        <span class="sr-only">Rahman Anis</span>
    @endif

    <!-- =====================================================================
         MASTHEAD
         ===================================================================== -->
    <header class="sticky top-0 z-40 w-full border-b backdrop-blur-xl" style="border-color: var(--dw-line); background: var(--dw-nav);">
        <div class="mx-auto flex h-[68px] max-w-[1180px] items-center justify-between gap-4 px-5 sm:px-8">

            <a href="{{ url('/') }}" class="flex items-center gap-3" aria-label="DhakaWala ERP home">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px] p-1.5" style="background: var(--dw-accent-soft); border: 1px solid var(--dw-accent-line);">
                    <img src="{{ asset('images/dhakawala_logo.svg') }}" alt="" width="22" height="22" class="h-full w-full object-contain">
                </span>
                <span class="flex flex-col leading-none">
                    <span class="text-[15px] font-bold tracking-[-0.02em]" style="color: var(--dw-ink);">
                        DhakaWala <span style="color: var(--dw-accent);">ERP</span>
                    </span>
                    <span class="dw-mono dw-mono-sm mt-1" style="color: var(--dw-muted);">Practice Cloud</span>
                </span>
            </a>

            <nav class="hidden items-center gap-1 lg:flex" aria-label="Primary">
                <a href="#features" class="rounded-lg px-3 py-2 text-[13px] font-medium transition-colors" style="color: var(--dw-ink-soft);">Capabilities</a>
                <a href="#workflow" class="rounded-lg px-3 py-2 text-[13px] font-medium transition-colors" style="color: var(--dw-ink-soft);">How it works</a>
                <a href="#portal-login-card" class="rounded-lg px-3 py-2 text-[13px] font-medium transition-colors" style="color: var(--dw-ink-soft);">Access</a>
            </nav>

            <div class="flex items-center gap-2.5">
                <div class="dw-theme-toggle" role="group" aria-label="Colour theme" wire:ignore>
                    <button type="button" data-dw-theme-value="light" aria-pressed="false" title="Light theme" aria-label="Switch to light theme">
                        <i data-lucide="sun" class="h-3.5 w-3.5"></i>
                    </button>
                    <button type="button" data-dw-theme-value="dark" aria-pressed="true" title="Dark theme" aria-label="Switch to dark theme">
                        <i data-lucide="moon" class="h-3.5 w-3.5"></i>
                    </button>
                </div>
                <a href="#portal-login-card" class="dw-btn dw-btn-primary dw-btn-sm">
                    <span>Sign in</span>
                    <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i>
                </a>
            </div>
        </div>
    </header>

    <!-- =====================================================================
         HERO
         ===================================================================== -->
    <section class="relative overflow-hidden">
        <div class="dw-glow" aria-hidden="true"></div>
        <div class="dw-grid-lines" aria-hidden="true"></div>

        <div class="relative mx-auto max-w-[1180px] px-5 pb-16 pt-14 sm:px-8 sm:pt-20 lg:pb-24">
            <div class="grid items-center gap-12 lg:grid-cols-12 lg:gap-10">

                <div class="lg:col-span-6">
                    <span class="dw-rise dw-eyebrow inline-flex items-center gap-2">
                        <span class="h-1 w-1 rounded-full" style="background: var(--dw-accent);"></span>
                        For chartered accountancy practices
                    </span>

                    <h1 class="dw-rise dw-d1 dw-display mt-5 text-[38px] sm:text-[52px] lg:text-[56px]" style="color: var(--dw-ink);">
                        Every hour,<br>
                        every taka,<br>
                        <span style="color: var(--dw-accent);">accounted for.</span>
                    </h1>

                    <p class="dw-rise dw-d2 dw-body mt-6 max-w-[30rem] text-[15px] sm:text-base">
                        Geofenced attendance, multi-leg conveyance, leave approvals and monthly
                        stipend ledgers — running inside one isolated workspace per firm, with
                        an audit trail behind every change.
                    </p>

                    <div class="dw-rise dw-d3 mt-8 flex flex-wrap items-center gap-3">
                        <a href="#portal-login-card" class="dw-btn dw-btn-primary">
                            Open your workspace
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </a>
                        <a href="#workflow" class="dw-btn dw-btn-ghost">
                            See how it works
                        </a>
                    </div>

                    <div class="dw-rise dw-d4 mt-8 flex flex-wrap items-center gap-x-5 gap-y-2">
                        <span class="dw-mono dw-mono-sm flex items-center gap-1.5" style="color: var(--dw-muted);">
                            <i data-lucide="lock" class="h-3 w-3"></i> 256-bit TLS
                        </span>
                        <span class="dw-mono dw-mono-sm flex items-center gap-1.5" style="color: var(--dw-muted);">
                            <i data-lucide="layers" class="h-3 w-3"></i> Tenant-isolated
                        </span>
                        <span class="dw-mono dw-mono-sm flex items-center gap-1.5" style="color: var(--dw-muted);">
                            <i data-lucide="fingerprint" class="h-3 w-3"></i> Audit-trailed
                        </span>
                    </div>
                </div>

                <!-- Product shot: the attendance register, rendered in CSS -->
                <div class="dw-rise dw-d2 lg:col-span-6">
                    <div class="dw-shot mx-auto max-w-[470px]">
                        <div class="dw-shot-chrome">
                            <span class="dw-shot-dot"></span>
                            <span class="dw-shot-dot"></span>
                            <span class="dw-shot-dot"></span>
                            <span class="dw-mono dw-mono-sm ml-2.5" style="color: var(--dw-muted);">erp.dhakawala.com</span>
                            <span class="dw-mono dw-mono-sm ml-auto flex items-center gap-1.5" style="color: var(--dw-accent);">
                                <span class="h-1.5 w-1.5 rounded-full" style="background: var(--dw-accent);"></span> Live
                            </span>
                        </div>

                        <div class="px-4 pb-3 pt-3.5" style="border-bottom: 1px solid var(--dw-line);">
                            <div class="flex items-baseline justify-between">
                                <span class="text-[12.5px] font-semibold" style="color: var(--dw-ink);">Attendance · Fri, 31 Oct</span>
                                <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">Unit 01</span>
                            </div>
                            <div class="mt-2.5 flex items-center gap-2 rounded-lg px-2.5 py-2" style="background: var(--dw-accent-soft); border: 1px solid var(--dw-accent-line);">
                                <i data-lucide="calendar-clock" class="h-3.5 w-3.5 shrink-0" style="color: var(--dw-accent);"></i>
                                <span class="dw-mono dw-mono-sm shrink-0" style="color: var(--dw-accent);">Shifted</span>
                                <span class="truncate text-[10.5px]" style="color: var(--dw-ink-soft);">12:00 – 18:00 · Hartal declared</span>
                            </div>
                        </div>

                        <div>
                            <div class="dw-shot-row">
                                <span class="dw-avatar" style="background: var(--dw-emerald-soft); color: var(--dw-emerald);">AR</span>
                                <span class="flex min-w-0 items-baseline gap-2">
                                    <span class="truncate text-[12px] font-medium" style="color: var(--dw-ink);">Arif Hossain</span>
                                    <span class="dw-mono dw-mono-sm shrink-0" style="color: var(--dw-muted);">09:58</span>
                                </span>
                                <span class="dw-status dw-status-ok">Right time</span>
                            </div>
                            <div class="dw-shot-row">
                                <span class="dw-avatar" style="background: var(--dw-accent-soft); color: var(--dw-accent);">SR</span>
                                <span class="flex min-w-0 items-baseline gap-2">
                                    <span class="truncate text-[12px] font-medium" style="color: var(--dw-ink);">Sadia Rahman</span>
                                    <span class="dw-mono dw-mono-sm shrink-0" style="color: var(--dw-muted);">11:42</span>
                                </span>
                                <span class="dw-status dw-status-excused">Late · excused</span>
                            </div>
                            <div class="dw-shot-row">
                                <span class="dw-avatar" style="background: var(--dw-rose-soft); color: var(--dw-rose);">MH</span>
                                <span class="flex min-w-0 items-baseline gap-2">
                                    <span class="truncate text-[12px] font-medium" style="color: var(--dw-ink);">Mahin Hasan</span>
                                    <span class="dw-mono dw-mono-sm shrink-0" style="color: var(--dw-muted);">11:55</span>
                                </span>
                                <span class="dw-status dw-status-late">Late present</span>
                            </div>
                            <div class="dw-shot-row">
                                <span class="dw-avatar" style="background: var(--dw-blue-soft); color: var(--dw-blue);">NF</span>
                                <span class="flex min-w-0 items-baseline gap-2">
                                    <span class="truncate text-[12px] font-medium" style="color: var(--dw-ink);">Nusrat Ferdous</span>
                                    <span class="dw-mono dw-mono-sm shrink-0" style="color: var(--dw-muted);">On duty</span>
                                </span>
                                <span class="dw-status dw-status-duty">Client site</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between px-4 py-2.5" style="background: var(--dw-bg-elev); border-top: 1px solid var(--dw-line);">
                            <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">4 present · 1 excused</span>
                            <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">1 late day counted</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         PILLARS
         ===================================================================== -->
    <section class="mx-auto max-w-[1180px] px-5 sm:px-8">
        <div class="grid gap-px overflow-hidden rounded-2xl sm:grid-cols-2 lg:grid-cols-4" style="background: var(--dw-line); border: 1px solid var(--dw-line);">
            @foreach([
                ['icon' => 'building-2',   'k' => 'One tenant per firm',    'v' => 'Separate data scope, roster and brand palette.'],
                ['icon' => 'clock',        'k' => 'Policy, not guesswork',  'v' => 'Start time, grace window and weekly offs per firm.'],
                ['icon' => 'file-text',    'k' => 'Audit trail',            'v' => 'Who changed what, on which date, and why.'],
                ['icon' => 'shield-check', 'k' => 'Payroll-safe',           'v' => 'An excused late arrival can never deduct.'],
            ] as $pillar)
                <div class="p-5" style="background: var(--dw-surface);">
                    <i data-lucide="{{ $pillar['icon'] }}" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                    <p class="mt-3 text-[13px] font-semibold" style="color: var(--dw-ink);">{{ $pillar['k'] }}</p>
                    <p class="mt-1.5 text-[12.5px] leading-relaxed" style="color: var(--dw-muted);">{{ $pillar['v'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <!-- =====================================================================
         CAPABILITIES
         ===================================================================== -->
    <section id="features" class="mx-auto max-w-[1180px] scroll-mt-24 px-5 pt-20 sm:px-8 sm:pt-28">
        <div class="max-w-[42rem]">
            <span class="dw-eyebrow">Capabilities</span>
            <h2 class="dw-section-title mt-3 text-[26px] sm:text-[34px]" style="color: var(--dw-ink);">
                Built around how a practice actually runs.
            </h2>
            <p class="dw-body mt-3 max-w-[34rem] text-[14.5px]">
                Not a generic HR tool with a CA logo on it. Every screen maps to a decision a
                partner, a manager or an articled student has to make before the month closes.
            </p>
        </div>

        <div class="mt-10 grid gap-4 md:grid-cols-6">

            <!-- Working day exceptions -->
            <article class="dw-card dw-card-hover md:col-span-4 p-6 sm:p-7">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <span class="dw-mono" style="color: var(--dw-accent);">Attendance</span>
                        <h3 class="mt-2.5 text-[19px] font-semibold tracking-[-0.02em]" style="color: var(--dw-ink);">
                            One date, different hours — without rewriting the policy.
                        </h3>
                    </div>
                    <span class="hidden h-9 w-9 shrink-0 items-center justify-center rounded-[10px] sm:flex" style="background: var(--dw-accent-soft); border: 1px solid var(--dw-accent-line);">
                        <i data-lucide="calendar-clock" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                    </span>
                </div>

                <p class="dw-body mt-3 max-w-[34rem] text-[13.5px]">
                    A hartal is called overnight. A storm floods the road. Eid eve closes early.
                    Declare the exception for that one date — shift the start, widen the grace
                    window, or switch lateness off entirely — preview exactly who it affects,
                    then save. The standing policy, and every month you already approved,
                    stays exactly as it was.
                </p>

                <div class="mt-5 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-lg px-3 py-2.5" style="background: var(--dw-surface-2); border: 1px solid var(--dw-line);">
                        <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">Standard day</span>
                        <p class="dw-mono mt-1.5 text-[11px] tracking-normal" style="color: var(--dw-muted); text-decoration: line-through;">09:00 — 18:00</p>
                    </div>
                    <div class="rounded-lg px-3 py-2.5" style="background: var(--dw-accent-soft); border: 1px solid var(--dw-accent-line);">
                        <span class="dw-mono dw-mono-sm" style="color: var(--dw-accent);">31 Oct · hartal</span>
                        <p class="dw-mono mt-1.5 text-[11px] tracking-normal" style="color: var(--dw-ink);">12:00 — 18:00</p>
                    </div>
                </div>

                <p class="dw-mono dw-mono-sm mt-4" style="color: var(--dw-muted);">
                    Preview impact · Partner only · Audited
                </p>
            </article>

            <!-- Geofenced attendance -->
            <article class="dw-card dw-card-hover md:col-span-2 p-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px]" style="background: var(--dw-surface-2); border: 1px solid var(--dw-line);">
                    <i data-lucide="map-pin" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                </span>
                <h3 class="mt-3.5 text-[15px] font-semibold tracking-[-0.01em]" style="color: var(--dw-ink);">Geofenced check-in</h3>
                <p class="dw-body mt-2 text-[12.5px]">
                    Office and client-site check-in with the arrival classified on the spot —
                    right time, inside grace, or late present.
                </p>
            </article>

            <!-- Approval inbox -->
            <article class="dw-card dw-card-hover md:col-span-2 p-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px]" style="background: var(--dw-surface-2); border: 1px solid var(--dw-line);">
                    <i data-lucide="inbox" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                </span>
                <h3 class="mt-3.5 text-[15px] font-semibold tracking-[-0.01em]" style="color: var(--dw-ink);">Approval inbox</h3>
                <p class="dw-body mt-2 text-[12.5px]">
                    Leave and conveyance claims arrive as unread badges on the manager's and
                    partner's dashboards until someone acts.
                </p>
            </article>

            <!-- Conveyance -->
            <article class="dw-card dw-card-hover md:col-span-2 p-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px]" style="background: var(--dw-surface-2); border: 1px solid var(--dw-line);">
                    <i data-lucide="route" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                </span>
                <h3 class="mt-3.5 text-[15px] font-semibold tracking-[-0.01em]" style="color: var(--dw-ink);">Multi-leg conveyance</h3>
                <p class="dw-body mt-2 text-[12.5px]">
                    Each leg of a client visit is logged and approved separately, so a route is
                    never claimed twice and never quietly dropped.
                </p>
            </article>

            <!-- Payroll -->
            <article class="dw-card dw-card-hover md:col-span-2 p-6">
                <span class="flex h-9 w-9 items-center justify-center rounded-[10px]" style="background: var(--dw-surface-2); border: 1px solid var(--dw-line);">
                    <i data-lucide="receipt" class="h-4 w-4" style="color: var(--dw-accent);"></i>
                </span>
                <h3 class="mt-3.5 text-[15px] font-semibold tracking-[-0.01em]" style="color: var(--dw-ink);">Stipend &amp; vouchers</h3>
                <p class="dw-body mt-2 text-[12.5px]">
                    Approved claims and computed deductions settle into a printable payment
                    voucher for the month — reviewed, then approved, then paid.
                </p>
            </article>
        </div>
    </section>

    <!-- =====================================================================
         HOW IT WORKS
         ===================================================================== -->
    <section id="workflow" class="mx-auto max-w-[1180px] scroll-mt-24 px-5 pt-20 sm:px-8 sm:pt-28">
        <div class="grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <span class="dw-eyebrow">How it works</span>
                <h2 class="dw-section-title mt-3 text-[26px] sm:text-[32px]" style="color: var(--dw-ink);">
                    Onboarded in an afternoon.
                </h2>
                <p class="dw-body mt-3 text-[14px]">
                    No migration project, no consultant. A firm is live once its roster,
                    its policy and its holiday calendar are in.
                </p>
            </div>

            <div class="lg:col-span-8">
                <div class="grid gap-px overflow-hidden rounded-2xl sm:grid-cols-3" style="background: var(--dw-line); border: 1px solid var(--dw-line);">
                    @foreach([
                        ['n' => '01', 't' => 'Create the firm', 'd' => 'An isolated tenant, its own brand palette and a private member roster.'],
                        ['n' => '02', 't' => 'Set the policy',  'd' => 'Office hours, grace minutes, weekly off days, holiday calendar.'],
                        ['n' => '03', 't' => 'Run the month',   'd' => 'Attendance, approvals and vouchers close out into payroll.'],
                    ] as $step)
                        <div class="flex flex-col p-6" style="background: var(--dw-surface);">
                            <span class="dw-mono" style="color: var(--dw-accent);">{{ $step['n'] }}</span>
                            <h3 class="mt-6 text-[14.5px] font-semibold" style="color: var(--dw-ink);">{{ $step['t'] }}</h3>
                            <p class="dw-body mt-2 text-[12.5px]">{{ $step['d'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         SIGN IN
         ===================================================================== -->
    <section id="portal-login-card" class="mx-auto max-w-[1180px] scroll-mt-24 px-5 pt-20 sm:px-8 sm:pt-28">
        <div class="dw-card overflow-hidden rounded-[20px] lg:grid lg:grid-cols-2">

            <!-- Left: what signing in does -->
            <div class="dw-split p-7 sm:p-10" style="background: var(--dw-surface-2);">
                <span class="dw-eyebrow">Workspace access</span>
                <h2 class="dw-section-title mt-3 text-[24px] sm:text-[28px]" style="color: var(--dw-ink);">
                    One door for every firm.
                </h2>
                <p class="dw-body mt-3 max-w-[26rem] text-[13.5px]">
                    Enter the email your firm registered. The portal resolves the tenant and the
                    role, then opens the right workspace — no firm list is exposed on this page.
                </p>

                <div class="mt-7 space-y-2.5">
                    @foreach([
                        ['c' => 'var(--dw-accent)',  't' => 'Partner / Principal', 'd' => 'Policy, payroll and firm settings'],
                        ['c' => 'var(--dw-amber)',   't' => 'Audit Manager',        'd' => 'Attendance, approvals, field teams'],
                        ['c' => 'var(--dw-emerald)', 't' => 'Articled / Staff',     'd' => 'Check-in, claims, own register'],
                        ['c' => 'var(--dw-blue)',    't' => 'Super Admin',          'd' => 'Platform console across tenants'],
                    ] as $role)
                        <div class="flex items-center gap-3">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $role['c'] }};"></span>
                            <span class="text-[12.5px] font-medium" style="color: var(--dw-ink);">{{ $role['t'] }}</span>
                            <span class="ml-auto hidden text-right text-[11.5px] sm:block" style="color: var(--dw-muted);">{{ $role['d'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="mt-7 flex flex-wrap items-center gap-x-5 gap-y-2 pt-5" style="border-top: 1px solid var(--dw-line);">
                    <a href="{{ url('/platform/login') }}" class="dw-mono dw-mono-sm inline-flex items-center gap-1.5 transition-opacity hover:opacity-70" style="color: var(--dw-ink-soft);">
                        <i data-lucide="shield" class="h-3 w-3"></i> Platform console
                    </a>
                    <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">Tenant guard active</span>
                </div>
            </div>

            <!-- Right: the form -->
            <div class="p-7 sm:p-10">
                <h3 class="text-[17px] font-semibold tracking-[-0.01em]" style="color: var(--dw-ink);">Sign in</h3>
                <p class="dw-body mt-1.5 text-[12.5px]">
                    Use your registered work email and password.
                </p>

                @if($loginError)
                    <div class="mt-5 flex items-start gap-2.5 rounded-lg p-3 text-[12px]" style="background: var(--dw-rose-soft); border: 1px solid var(--dw-rose); color: var(--dw-rose);" role="alert">
                        <i data-lucide="alert-circle" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                        <span><strong class="font-semibold">Sign-in failed.</strong> {{ $loginError }}</span>
                    </div>
                @endif

                @if($loginSuccess)
                    <div class="mt-5 flex items-start gap-2.5 rounded-lg p-3 text-[12px]" style="background: var(--dw-emerald-soft); border: 1px solid var(--dw-emerald); color: var(--dw-emerald);">
                        <i data-lucide="check-circle-2" class="mt-0.5 h-3.5 w-3.5 shrink-0"></i>
                        <span class="font-medium">{{ $loginSuccess }}</span>
                    </div>
                @endif

                <form wire:submit.prevent="submitLogin" class="mt-5 space-y-4">
                    <div>
                        <label for="portal-email" class="dw-label mb-1.5">Work email or user ID</label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3" style="color: var(--dw-muted);">
                                <i data-lucide="mail" class="h-4 w-4"></i>
                            </span>
                            <input
                                wire:model="email"
                                type="email"
                                id="portal-email"
                                required
                                autocomplete="username"
                                placeholder="you@yourfirm.com"
                                class="dw-input"
                            >
                        </div>
                    </div>

                    <div>
                        <div class="mb-1.5 flex items-baseline justify-between">
                            <label for="portal-password" class="dw-label">Password</label>
                            <a href="{{ url('/platform/forgot-password') }}" class="text-[11.5px] font-medium transition-opacity hover:opacity-70" style="color: var(--dw-accent);">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3" style="color: var(--dw-muted);">
                                <i data-lucide="key-round" class="h-4 w-4"></i>
                            </span>
                            <input
                                wire:model="password"
                                type="{{ $showPassword ? 'text' : 'password' }}"
                                id="portal-password"
                                required
                                autocomplete="current-password"
                                placeholder="••••••••••••"
                                class="dw-input pr-11"
                            >
                            <button
                                type="button"
                                wire:click="togglePassword"
                                class="absolute inset-y-0 right-0 flex cursor-pointer items-center pr-3 transition-opacity hover:opacity-70"
                                style="color: var(--dw-muted);"
                                aria-label="Toggle password visibility"
                            >
                                <i data-lucide="{{ $showPassword ? 'eye-off' : 'eye' }}" class="h-4 w-4"></i>
                            </button>
                        </div>
                    </div>

                    <label class="flex cursor-pointer select-none items-center gap-2.5 pt-0.5 text-[12.5px]" style="color: var(--dw-ink-soft);">
                        <input wire:model="remember" type="checkbox" class="h-3.5 w-3.5 rounded" style="accent-color: var(--dw-accent);">
                        <span>Keep me signed in on this device</span>
                    </label>

                    <button
                        type="submit"
                        wire:loading.attr="disabled"
                        class="dw-btn dw-btn-primary w-full disabled:cursor-wait disabled:opacity-60"
                    >
                        <span wire:loading.remove class="flex items-center gap-2">
                            Sign in to workspace
                            <i data-lucide="arrow-right" class="h-4 w-4"></i>
                        </span>
                        <span wire:loading class="flex items-center gap-2">Authenticating…</span>
                    </button>
                </form>

                <p class="dw-mono dw-mono-sm mt-5 flex items-center gap-1.5" style="color: var(--dw-muted);">
                    <i data-lucide="lock" class="h-3 w-3"></i> TLS 1.2+ · HttpOnly session cookies
                </p>
            </div>
        </div>
    </section>

    <!-- =====================================================================
         FOOTER
         ===================================================================== -->
    <footer class="mx-auto mt-20 max-w-[1180px] px-5 pb-10 sm:px-8">
        <div class="flex flex-col gap-6 pt-8 sm:flex-row sm:items-center sm:justify-between" style="border-top: 1px solid var(--dw-line);">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg p-1.5" style="background: var(--dw-accent-soft); border: 1px solid var(--dw-accent-line);">
                    <img src="{{ asset('images/dhakawala_logo.svg') }}" alt="" width="16" height="16" class="h-full w-full object-contain">
                </span>
                <span class="text-[13px] font-semibold" style="color: var(--dw-ink);">DhakaWala ERP</span>
                <span class="hidden text-[12px] sm:inline" style="color: var(--dw-muted);">Enterprise practice &amp; corporate management cloud</span>
            </div>

            <div class="flex flex-wrap items-center gap-x-5 gap-y-2">
                <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">erp.dhakawala.com</span>
                <a href="{{ url('/platform/login') }}" class="dw-mono dw-mono-sm transition-opacity hover:opacity-70" style="color: var(--dw-ink-soft);">Platform console</a>
                <span class="dw-mono dw-mono-sm" style="color: var(--dw-muted);">© {{ date('Y') }} DhakaWala</span>
            </div>
        </div>
    </footer>
</div>
