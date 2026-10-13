<div x-data="{ mobileOpen: false }" class="min-h-screen bg-[#F7F8FA] text-[#20232A]">
    {{-- The route is deliberately unauthenticated but contains public demo data only. --}}
    <div x-show="mobileOpen" x-cloak class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"
         @click="mobileOpen = false" aria-hidden="true"></div>

    <aside :class="mobileOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-50 flex w-[268px] flex-col bg-[#17191F] text-white shadow-xl transition-transform duration-200 lg:translate-x-0"
           aria-label="Platform preview navigation">
        <div class="flex items-center gap-3 border-b border-white/10 px-5 py-6">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#FCDF02] text-[#16181D]">
                <i data-lucide="layers-3" class="h-6 w-6" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[15px] font-extrabold tracking-tight">DhakaWala <span class="text-[#FCDF02]">ERP</span></p>
                <p class="text-[11px] font-medium tracking-wider text-slate-400">PLATFORM CONSOLE</p>
            </div>
            <button class="ml-auto rounded-lg p-2 text-slate-400 hover:bg-white/10 lg:hidden" type="button"
                    @click="mobileOpen = false" aria-label="Close navigation">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>

        <div class="px-4 pt-5">
            <div class="flex items-center gap-2 rounded-xl border border-[#FCDF02]/20 bg-[#FCDF02]/10 px-3 py-2.5">
                <i data-lucide="shield-check" class="h-4 w-4 shrink-0 text-[#FCDF02]"></i>
                <div class="min-w-0">
                    <div class="text-[11px] font-semibold text-white">Platform Operator Preview</div>
                    <div class="text-[10px] text-slate-400">No authenticated session</div>
                </div>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-6" aria-label="Main sections">
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Workspace</p>
            @foreach ([
                ['id'=>'overview', 'label'=>'Overview', 'icon'=>'layout-dashboard'],
                ['id'=>'firms', 'label'=>'Organizations', 'icon'=>'building-2'],
                ['id'=>'support', 'label'=>'Support Access', 'icon'=>'life-buoy'],
                ['id'=>'platform', 'label'=>'Platform Status', 'icon'=>'activity'],
            ] as $item)
                <button type="button" wire:key="nav-{{ $item['id'] }}"
                        wire:click="setSection('{{ $item['id'] }}')"
                        @click="mobileOpen = false"
                        @if($section === $item['id']) aria-current="page" @endif
                        class="flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-left text-[13px] font-semibold transition-colors {{ $section === $item['id'] ? 'bg-[#FCDF02] text-[#16181D] shadow-sm' : 'text-slate-400 hover:bg-white/10 hover:text-white' }}">
                    <i data-lucide="{{ $item['icon'] }}" class="h-[18px] w-[18px] shrink-0"></i>
                    <span>{{ $item['label'] }}</span>
                    @if($section === $item['id'])
                        <i data-lucide="chevron-right" class="ml-auto h-4 w-4"></i>
                    @endif
                </button>
            @endforeach
            <div class="my-5 border-t border-white/10"></div>
            <p class="px-3 pb-2 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-500">Preview Navigation</p>
            <a href="{{ route('platform.login') }}" class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-[13px] font-medium text-slate-400 hover:bg-white/10 hover:text-white">
                <i data-lucide="log-in" class="h-[18px] w-[18px]"></i> Platform Login UI
            </a>
            <a href="{{ route('firm.login', ['firmSlug'=>'rac']) }}" class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-[13px] font-medium text-slate-400 hover:bg-white/10 hover:text-white">
                <i data-lucide="arrow-up-right" class="h-[18px] w-[18px]"></i> RA&C Firm Login UI
            </a>
        </nav>

        <div class="border-t border-white/10 p-4">
            <div class="flex items-center gap-3 rounded-xl bg-white/5 p-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#F5AD16]/20 text-xs font-extrabold text-[#FCDF02]">DW</div>
                <div class="min-w-0">
                    <div class="text-xs font-bold text-white">Platform Design Team</div>
                    <div class="text-[11px] text-slate-400">Non-privileged UI prototype</div>
                </div>
            </div>
        </div>
    </aside>

    <div class="min-w-0 lg:pl-[268px]">
        <header class="sticky top-0 z-30 flex min-h-[76px] items-center gap-3 border-b border-[#E8EAF0] bg-white/95 px-4 backdrop-blur sm:px-7 lg:px-9">
            <button type="button" @click="mobileOpen = true"
                    class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 lg:hidden" aria-label="Open navigation">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
            <div class="min-w-0 flex-1">
                <div class="text-[11px] font-semibold text-slate-400">ERP SaaS / Platform / <span class="text-[#B08300]">{{ ucfirst($section) }}</span></div>
                <h1 class="truncate text-lg font-extrabold tracking-tight text-[#20232A] sm:text-xl">
                    @switch($section)
                        @case('firms') Organizations @break
                        @case('support') Support Access Governance @break
                        @case('platform') Platform Status @break
                        @default Platform Overview
                    @endswitch
                </h1>
            </div>
            <div class="hidden items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-[11px] font-bold text-amber-900 sm:flex">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> PHASE 03 · DESIGN PREVIEW
            </div>
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-slate-200 bg-slate-50 text-xs font-extrabold text-slate-600" aria-label="Preview operator avatar">DW</div>
        </header>

        <main class="mx-auto max-w-[1480px] space-y-6 px-4 py-6 sm:px-7 lg:px-9 lg:py-8">
            <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-[#FFFBE5] px-4 py-3.5 text-[#785500]" role="status">
                <i data-lucide="flask-conical" class="mt-0.5 h-[18px] w-[18px] shrink-0"></i>
                <div class="flex-1 text-xs leading-relaxed sm:text-[13px]">
                    <strong>Design Prototype — No Live Access.</strong>
                    All organizations, seat totals, support requests and platform indicators shown here are illustrative sample data. No authentication, tenant provisioning, monitoring, data access or financial operations are active.
                </div>
            </div>

            @if($previewNotice)
                <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700" role="status">
                    <i data-lucide="info" class="mt-0.5 h-4 w-4 text-amber-700"></i>
                    <span class="flex-1">{{ $previewNotice }}</span>
                    <button type="button" wire:click="dismissNotice" aria-label="Dismiss notice" class="rounded p-1 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>
            @endif

            @if($section === 'overview')
                <section class="flex flex-col justify-between gap-4 xl:flex-row xl:items-end">
                    <div class="space-y-1.5">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#B08400]">CENTRAL OPERATIONS</p>
                        <h2 class="text-2xl font-extrabold tracking-tight text-[#20232A] sm:text-3xl">One platform. Clear boundaries.</h2>
                        <p class="max-w-2xl text-sm leading-relaxed text-slate-500">Manage the SaaS platform without automatically viewing a firm's private records, attendance, payroll or documents.</p>
                    </div>
                    <button wire:click="openProvisionPreview" type="button"
                            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-bold text-[#16181D] shadow-sm transition hover:bg-[#F3D604]">
                        <i data-lucide="plus" class="h-4 w-4"></i> New Organization <span class="text-[10px] opacity-65">(Preview)</span>
                    </button>
                </section>

                <section class="grid grid-cols-2 gap-3 lg:grid-cols-4 lg:gap-4" aria-label="Illustrative platform metrics">
                    @foreach ([
                        ['title'=>'Demo Organizations', 'value'=>$metrics['firms'], 'icon'=>'building-2', 'detail'=>'Presentation records only'],
                        ['title'=>'Demo Workspaces', 'value'=>$metrics['workspaces'], 'icon'=>'network', 'detail'=>'No private workspace access'],
                        ['title'=>'Demo Seats', 'value'=>$metrics['seats'], 'icon'=>'users', 'detail'=>'Illustrative allocation'],
                        ['title'=>'Pending Previews', 'value'=>$metrics['pending'], 'icon'=>'hourglass', 'detail'=>'No real activation requests'],
                    ] as $metric)
                        <div class="relative overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white p-4 shadow-sm sm:p-5" wire:key="metric-{{ $metric['title'] }}">
                            <div class="mb-4 flex items-center justify-between gap-2">
                                <span class="text-[11px] font-semibold leading-tight text-slate-500 sm:text-xs">{{ $metric['title'] }}</span>
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-800">
                                    <i data-lucide="{{ $metric['icon'] }}" class="h-[18px] w-[18px]"></i>
                                </div>
                            </div>
                            <p class="text-3xl font-extrabold tracking-tight text-[#20232A]">{{ $metric['value'] }}</p>
                            <p class="mt-2 text-[10px] leading-relaxed text-slate-400 sm:text-[11px]">{{ $metric['detail'] }}</p>
                        </div>
                    @endforeach
                </section>

                <div class="grid grid-cols-1 gap-5 xl:grid-cols-[minmax(0,1.7fr)_minmax(300px,1fr)]">
                    <section class="min-w-0 overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-5">
                            <div>
                                <h3 class="text-[15px] font-extrabold">Organization Directory</h3>
                                <p class="mt-1 text-xs text-slate-500">Platform metadata only · demo records</p>
                            </div>
                            <button type="button" wire:click="setSection('firms')" class="inline-flex items-center gap-1 text-xs font-bold text-[#916800] hover:underline">Open directory <i data-lucide="arrow-up-right" class="h-3.5 w-3.5"></i></button>
                        </div>
                        <div class="divide-y divide-slate-100">
                            @foreach($demoFirms as $firm)
                                <button type="button" wire:key="firm-summary-{{ $firm['id'] }}"
                                        wire:click="selectFirm('{{ $firm['id'] }}')"
                                        class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-slate-50">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#17191F] text-xs font-extrabold text-[#FCDF02]">{{ $firm['initials'] }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-bold text-slate-800">{{ $firm['name'] }}</p>
                                        <p class="truncate text-xs text-slate-500">{{ $firm['kind'] }} · {{ $firm['plan'] }} demo tier</p>
                                    </div>
                                    <span class="hidden rounded-full px-2.5 py-1 text-[10px] font-bold sm:inline-flex {{ $firm['status'] === 'preview-active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">
                                        {{ $firm['status'] === 'preview-active' ? 'Demo Active' : 'Demo Pending' }}
                                    </span>
                                    <i data-lucide="chevron-right" class="h-4 w-4 shrink-0 text-slate-400"></i>
                                </button>
                            @endforeach
                        </div>
                    </section>

                    <div class="space-y-5">
                        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-[15px] font-extrabold">Privacy Architecture</h3>
                                <div class="rounded-xl bg-[#FFFBE5] p-2.5 text-amber-800"><i data-lucide="shield" class="h-5 w-5"></i></div>
                            </div>
                            <p class="text-xs leading-relaxed text-slate-500">Platform administration and firm workspace access are separate responsibilities. These are design requirements, not implemented enforcement.</p>
                            <div class="mt-4 space-y-3">
                                @foreach ([
                                    'No default access to firm payroll or documents',
                                    'Firm-scoped, approved, read-only support access',
                                    'Time-limited access with audit logging',
                                ] as $rule)
                                    <div class="flex items-start gap-2.5 text-xs text-slate-700">
                                        <i data-lucide="check-circle-2" class="mt-0.5 h-4 w-4 shrink-0 text-[#A57600]"></i>
                                        <span>{{ $rule }} <span class="text-slate-400">(planned)</span></span>
                                    </div>
                                @endforeach
                            </div>
                            <button wire:click="setSection('support')" type="button" class="mt-5 inline-flex items-center gap-1 text-xs font-bold text-[#916800] hover:underline">Review support governance <i data-lucide="arrow-right" class="h-3.5 w-3.5"></i></button>
                        </section>
                        <section class="rounded-2xl border border-[#E8EAF0] bg-[#20232A] p-5 text-white shadow-sm">
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-extrabold">Platform Health</h3>
                                <i data-lucide="activity" class="h-5 w-5 text-[#FCDF02]"></i>
                            </div>
                            <p class="text-xs leading-relaxed text-slate-300">Monitoring and infrastructure integrations have not been connected.</p>
                            <span class="mt-4 inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[11px] font-semibold text-amber-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-300"></span> Status unavailable in preview
                            </span>
                        </section>
                    </div>
                </div>

            @elseif($section === 'firms')
                <section class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#B08400]">TENANT DIRECTORY</p>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Organizations</h2>
                        <p class="mt-1 text-sm text-slate-500">Search fictional organization metadata. No tenant database is connected.</p>
                    </div>
                    <button type="button" wire:click="openProvisionPreview" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-bold hover:bg-[#F3D604]">
                        <i data-lucide="building-2" class="h-4 w-4"></i> Provision Firm (Preview)
                    </button>
                </section>
                <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                    <div class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                        <label class="relative block w-full sm:max-w-sm">
                            <span class="sr-only">Search organizations</span>
                            <i data-lucide="search" class="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400"></i>
                            <input type="search" wire:model.live.debounce.300ms="firmSearch" placeholder="Search firm name or slug..."
                                   class="w-full rounded-xl border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-3 text-sm outline-none transition focus:border-amber-400 focus:ring-2 focus:ring-amber-200">
                        </label>
                        <label class="flex items-center gap-2 text-xs font-semibold text-slate-600">
                            <span>Status</span>
                            <select wire:model.change="statusFilter" class="min-w-44 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-amber-400">
                                <option value="all">All demo states</option>
                                <option value="preview-active">Demo Active</option>
                                <option value="preview-pending">Demo Pending</option>
                            </select>
                        </label>
                    </div>
                    <div class="hidden overflow-x-auto md:block">
                        <table class="w-full min-w-[660px] text-left text-sm">
                            <thead class="bg-slate-50 text-[11px] uppercase tracking-wider text-slate-500">
                                <tr><th class="px-5 py-3 font-bold">Organization</th><th class="px-4 py-3 font-bold">Preview Status</th><th class="px-4 py-3 font-bold">Workspaces</th><th class="px-4 py-3 font-bold">Seats</th><th class="px-4 py-3 font-bold">Plan</th><th class="px-5 py-3 text-right font-bold">Details</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($filteredFirms as $firm)
                                    <tr wire:key="firm-row-{{ $firm['id'] }}" class="hover:bg-slate-50">
                                        <td class="px-5 py-4"><div class="font-bold text-slate-800">{{ $firm['name'] }}</div><div class="mt-0.5 text-xs text-slate-400">/c/{{ $firm['slug'] }} · {{ $firm['region'] }}</div></td>
                                        <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[11px] font-bold {{ $firm['status']==='preview-active' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' }}">{{ $firm['status']==='preview-active' ? 'Demo Active' : 'Demo Pending' }}</span></td>
                                        <td class="px-4 py-4 font-semibold">{{ $firm['workspaces'] }} <span class="text-xs font-normal text-slate-400">demo</span></td>
                                        <td class="px-4 py-4 font-semibold">{{ $firm['seats'] }} <span class="text-xs font-normal text-slate-400">demo</span></td>
                                        <td class="px-4 py-4 text-slate-600">{{ $firm['plan'] }}</td>
                                        <td class="px-5 py-4 text-right"><button type="button" wire:click="selectFirm('{{ $firm['id'] }}')" class="rounded-lg px-3 py-2 text-xs font-bold text-[#977000] hover:bg-amber-50">View summary</button></td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No demo organizations match those filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="divide-y divide-slate-100 md:hidden">
                        @forelse($filteredFirms as $firm)
                            <div wire:key="mobile-firm-{{ $firm['id'] }}" class="p-4">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#17191F] text-xs font-bold text-[#FCDF02]">{{ $firm['initials'] }}</div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-slate-800">{{ $firm['name'] }}</p>
                                        <p class="mt-1 text-xs text-slate-500">/c/{{ $firm['slug'] }} · {{ $firm['plan'] }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-800">Demo</span>
                                </div>
                                <div class="mt-3 flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500">
                                    <span>{{ $firm['workspaces'] }} workspaces · {{ $firm['seats'] }} seats</span>
                                    <button wire:click="selectFirm('{{ $firm['id'] }}')" type="button" class="font-bold text-[#916800]">View summary →</button>
                                </div>
                            </div>
                        @empty
                            <p class="p-10 text-center text-sm text-slate-500">No demo organizations match those filters.</p>
                        @endforelse
                    </div>
                </section>

            @elseif($section === 'support')
                <section class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-end">
                    <div>
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#B08400]">PRIVACY BY DESIGN</p>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Access requires permission.</h2>
                        <p class="mt-1 max-w-2xl text-sm text-slate-500">Conceptual support access workflow. Platform operators have no automatic access to firm payroll, documents or individual records.</p>
                    </div>
                    <button wire:click="openSupportPreview" type="button" class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-sm font-bold hover:bg-[#F3D604]">
                        <i data-lucide="clipboard-list" class="h-4 w-4"></i> Request Access (Preview)
                    </button>
                </section>
                <section class="grid gap-4 lg:grid-cols-3">
                    @foreach ([
                        ['icon'=>'file-key', 'step'=>'01', 'title'=>'Scope request', 'detail'=>'Specify a firm, limited read-only purpose and required duration.'],
                        ['icon'=>'user-check', 'step'=>'02', 'title'=>'Firm approval', 'detail'=>'An authorized firm representative must independently approve.'],
                        ['icon'=>'history', 'step'=>'03', 'title'=>'Audit & expiry', 'detail'=>'Access is logged, time-limited and automatically expires.'],
                    ] as $flow)
                        <div class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm" wire:key="flow-{{ $flow['step'] }}">
                            <div class="flex items-center justify-between">
                                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-900"><i data-lucide="{{ $flow['icon'] }}" class="h-5 w-5"></i></div>
                                <span class="text-2xl font-extrabold text-slate-200">{{ $flow['step'] }}</span>
                            </div>
                            <h3 class="mt-4 text-sm font-extrabold">{{ $flow['title'] }} <span class="font-medium text-slate-400">(planned)</span></h3>
                            <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $flow['detail'] }}</p>
                        </div>
                    @endforeach
                </section>
                <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-5">
                        <h3 class="text-[15px] font-extrabold">Illustrative Support Queue</h3>
                        <p class="mt-1 text-xs text-slate-500">Fictional examples — no support request has been submitted or authorized.</p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        @foreach($demoSupportRequests as $request)
                            <div wire:key="support-{{ $request['id'] }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="text-[11px] font-extrabold text-[#A57A00]">{{ $request['id'] }}</span>
                                        <span class="text-[11px] text-slate-400">{{ $request['firm'] }}</span>
                                    </div>
                                    <p class="mt-1 text-sm font-bold text-slate-800">{{ $request['subject'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Scope: {{ $request['scope'] }}</p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-900">{{ $request['status'] }} · Demo</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
                <div class="rounded-xl border border-slate-200 bg-slate-100 p-4 text-xs leading-relaxed text-slate-600">
                    <strong>Security boundary:</strong> An infrastructure administrator may technically have server/database capabilities. This future approval workflow is an application-level governance control; it is not a claim of cryptographic isolation.
                </div>

            @elseif($section === 'platform')
                <section>
                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#B08400]">OPERATIONAL READINESS</p>
                    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Platform Status</h2>
                    <p class="mt-1 max-w-2xl text-sm text-slate-500">Design placeholders for future infrastructure observability and subscription administration. Nothing here is reading production services.</p>
                </section>
                <section class="grid gap-4 sm:grid-cols-2">
                    @foreach ([
                        ['title'=>'Application services', 'icon'=>'server', 'desc'=>'Laravel uptime, errors and application releases'],
                        ['title'=>'Queue & scheduler', 'icon'=>'workflow', 'desc'=>'Background jobs, retries and scheduled tasks'],
                        ['title'=>'Database & backups', 'icon'=>'database', 'desc'=>'Backup completion and restore-check metadata'],
                        ['title'=>'Registrar & provider APIs', 'icon'=>'plug-zap', 'desc'=>'Third-party dependency checks and latency'],
                    ] as $service)
                        <div wire:key="service-{{ $service['title'] }}" class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]"><i data-lucide="{{ $service['icon'] }}" class="h-5 w-5"></i></div>
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold text-slate-600">Not connected</span>
                            </div>
                            <h3 class="mt-4 text-sm font-extrabold">{{ $service['title'] }}</h3>
                            <p class="mt-1.5 text-xs text-slate-500">{{ $service['desc'] }}</p>
                            <p class="mt-4 text-[11px] font-semibold text-amber-800">Monitoring integration planned</p>
                        </div>
                    @endforeach
                </section>
            @endif

            <footer class="flex flex-col gap-1 border-t border-slate-200 pb-2 pt-5 text-[11px] text-slate-400 sm:flex-row sm:justify-between">
                <span>DhakaWala ERP SaaS · Platform Console · Phase 03 Design Prototype</span>
                <span>Laravel + Livewire · No live tenant access</span>
            </footer>
        </main>
    </div>

    @if($selectedFirm)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4"
             wire:click.self="closeFirmDetail" wire:keydown.escape.window="closeFirmDetail"
             role="dialog" aria-modal="true" aria-labelledby="firm-preview-title">
            <div class="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div><span class="text-[10px] font-bold uppercase tracking-widest text-amber-800">Metadata Only · Demo</span><h2 id="firm-preview-title" class="mt-1 text-lg font-extrabold">{{ $selectedFirm['name'] }}</h2></div>
                    <button type="button" wire:click="closeFirmDetail" aria-label="Close organization preview" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>
                <div class="grid grid-cols-2 gap-3 p-6 text-sm">
                    @foreach([
                        ['Organization slug', $selectedFirm['slug']],
                        ['Display plan', $selectedFirm['plan'].' (Demo)'],
                        ['Workspaces', $selectedFirm['workspaces'].' (Demo)'],
                        ['Seat allocation', $selectedFirm['seats'].' (Demo)'],
                        ['Location label', $selectedFirm['region']],
                        ['Provision state', $selectedFirm['status'].' (Demo)'],
                    ] as $field)
                        <div class="rounded-xl bg-slate-50 p-3">
                            <p class="text-[11px] text-slate-500">{{ $field[0] }}</p>
                            <p class="mt-1 font-bold text-slate-800">{{ $field[1] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-slate-100 bg-amber-50 px-6 py-4 text-xs leading-relaxed text-amber-900">This preview intentionally contains no student records, salary amounts, payroll, attendance, or firm documents.</div>
            </div>
        </div>
    @endif

    @if($showProvisionPreview)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4"
             wire:click.self="closePreviews" wire:keydown.escape.window="closePreviews"
             role="dialog" aria-modal="true" aria-labelledby="provision-title">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div><span class="text-[11px] font-bold uppercase tracking-wider text-[#977000]">Workflow Preview</span><h2 id="provision-title" class="mt-1 text-lg font-extrabold">Create an organization</h2></div>
                    <button type="button" wire:click="closePreviews" aria-label="Close preview" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-slate-500">Future onboarding will validate firm identity, assign a slug, invite an authorized administrator and enforce tenant isolation.</p>
                <div class="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-xs leading-relaxed text-slate-600">Demo-only form specification · No tenant can be created in Phase 03.</div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closePreviews" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold hover:bg-slate-50">Cancel</button>
                    <button type="button" wire:click="acknowledgePreview('provision')" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-bold text-[#16181D] hover:bg-[#F3D604]">Acknowledge Preview</button>
                </div>
            </div>
        </div>
    @endif

    @if($showSupportPreview)
        <div class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/60 p-4"
             wire:click.self="closePreviews" wire:keydown.escape.window="closePreviews"
             role="dialog" aria-modal="true" aria-labelledby="support-title">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div><span class="text-[11px] font-bold uppercase tracking-wider text-[#977000]">Privacy-first Access</span><h2 id="support-title" class="mt-1 text-lg font-extrabold">Read-only support request</h2></div>
                    <button type="button" wire:click="closePreviews" aria-label="Close preview" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100"><i data-lucide="x" class="h-4 w-4"></i></button>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-slate-500">A real support request will require an explicit firm scope, reason, duration, approver and immutable access log. Approval must come from the tenant, never from the platform operator alone.</p>
                <div class="mt-4 rounded-xl border border-dashed border-amber-300 bg-amber-50 p-4 text-xs leading-relaxed text-amber-900">Design preview only. No access request will be sent and no private firm data will be exposed.</div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" wire:click="closePreviews" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold hover:bg-slate-50">Cancel</button>
                    <button type="button" wire:click="acknowledgePreview('support')" class="rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-bold text-[#16181D] hover:bg-[#F3D604]">Acknowledge Preview</button>
                </div>
            </div>
        </div>
    @endif
</div>
