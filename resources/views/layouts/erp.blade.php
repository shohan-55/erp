<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'ERP Workspace') · {{ $company->brand_name ?: $company->name }}</title>
@php($brandProfile = \App\Support\BrandIdentity::profile($company,$workspace))
@vite(['resources/css/app.css','resources/js/app.js'])
<style id="erp-dynamic-brand-palette">
:root{--erp-brand-accent:{{ $brandProfile->accent_color }};--erp-brand-on-accent:{{ \App\Support\BrandIdentity::onAccent($brandProfile->accent_color) }};--erp-brand-sidebar:{{ $brandProfile->sidebar_color }}}
.erp-branded-workspace aside.erp-brand-sidebar{background:var(--erp-brand-sidebar)!important}
.erp-branded-workspace .erp-brand-active,.erp-branded-workspace [class~="bg-[#FCDF02]"]{background:var(--erp-brand-accent)!important;color:var(--erp-brand-on-accent)!important}
.erp-branded-workspace [class~="text-[#FCDF02]"]{color:var(--erp-brand-accent)!important}
.erp-branded-workspace .erp-brand-accent-border{border-color:var(--erp-brand-accent)!important}
</style>
<style>[x-cloak]{display:none!important}</style>@livewireStyles
<style id="erp-corporate-workspace-style">{!! file_get_contents(public_path('css/erp-corporate-workspace.css')) !!}</style>
@stack('styles')</head>
<body class="erp-branded-workspace min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<div x-data="{ open:false }" class="min-h-screen">
<div x-show="open" x-cloak @click="open=false" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"></div>
<aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="erp-brand-sidebar fixed inset-y-0 left-0 z-50 flex w-[262px] flex-col bg-[#17191F] text-white shadow-xl transition-transform lg:translate-x-0">
<div class="flex items-center gap-3 border-b border-white/10 px-5 py-5"><div class="flex h-11 w-11 items-center justify-center rounded-xl p-1.5" style="background-color: {{ $brandProfile->accent_color }};color:{{ \App\Support\BrandIdentity::onAccent($brandProfile->accent_color) }}">@if($brandProfile->logo_asset_id)<img src="{{ route('erp.brand.asset',['firmSlug'=>$firmSlug,'id'=>$brandProfile->logo_asset_id]) }}" alt="" class="h-full object-contain">@elseif($company->brand_logo_asset)<img src="{{ asset($company->brand_logo_asset) }}" alt="" class="h-full object-contain">@else<span class="text-[11px] font-extrabold">{{ mb_strtoupper(mb_substr($brandProfile->public_name, 0, 2)) }}</span>@endif</div><div class="min-w-0"><p class="truncate text-sm font-extrabold">{{ $brandProfile->public_name }}</p><p class="text-[10px] text-slate-400">{{ $workspace->name }}</p></div><button type="button" @click="open=false" class="ml-auto lg:hidden" aria-label="Close navigation">×</button></div>
<div class="border-b border-white/10 p-4"><p class="text-[11px] font-bold uppercase tracking-widest text-[#FCDF02]">{{ ucfirst($actor->role) }} Workspace</p><p class="mt-1 truncate text-xs text-slate-300">{{ auth()->user()->name }}</p></div>
@php($studentFeatures = \App\Support\StudentFeatureAccess::enabledMap($actor))
<nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="ERP Navigation">
@foreach([
['label'=>'Dashboard','name'=>'erp.dashboard','icon'=>'layout-dashboard'],
['label'=>'Workforce Calendar','name'=>'erp.calendar.index','icon'=>'calendar-days'],
['label'=>'Attendance','name'=>'erp.attendance','icon'=>'calendar-check-2'],
['label'=>'Leave Requests','name'=>'erp.leaves','icon'=>'calendar-days'],
['label'=>'Conveyance','name'=>'erp.claims','icon'=>'receipt'],
['label'=>'Payroll','name'=>'erp.payroll','icon'=>'wallet-cards'],
['label'=>'Policy & Governance','name'=>'erp.policies','icon'=>'shield-check'],
['label'=>'Smart HR Policy Center','name'=>'erp.hr_policies.index','icon'=>'sliders-horizontal'],
['label'=>'Annual Holiday Planner','name'=>'erp.annual_holidays.index','icon'=>'calendar-range'],
['label'=>'Company Brand Studio','name'=>'erp.brand.index','icon'=>'palette'],
['label'=>'Document Template Studio','name'=>'erp.official_templates.index','icon'=>'file-pen-line'],
['label'=>'Official Certificates','name'=>'erp.staff_certificates.index','icon'=>'file-badge-2'],
['label'=>'Notifications','name'=>'erp.notifications','icon'=>'bell'],
['label'=>'Settings / Security','name'=>'erp.security','icon'=>'settings-2'],
] as $item)
@if($actor->role === 'manager' && in_array($item['name'],['erp.payroll','erp.staff_certificates.index'],true))
@continue
@endif
@if($actor->role === 'student' && in_array($item['name'],['erp.hr_policies.index','erp.annual_holidays.index','erp.brand.index','erp.official_templates.index'],true))
@continue
@endif
@php($featureKey = \App\Support\StudentFeatureAccess::featureForRoute($item['name']))
@if($actor->role !== 'student' || ($item['name'] !== 'erp.hr_policies.index' && ($featureKey === null || ($studentFeatures[$featureKey] ?? false))))
<a href="{{ route($item['name'], ['firmSlug'=>$firmSlug]) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs($item['name']) ? 'erp-brand-active bg-[#FCDF02] text-[#17191F]' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
<i data-lucide="{{ $item['icon'] }}" class="h-4 w-4"></i>{{ $item['label'] }}</a>
@endif
@endforeach
</nav>
@if($actor->role === 'partner' || ($actor->role === 'manager' && $actor->can_manage_student_features))
<a href="{{ route('erp.permissions.index', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-[#FCDF02] hover:bg-white/10"><i data-lucide="shield-check" class="h-4 w-4"></i>Student Permissions</a>
@endif
@if(\App\Support\WorkspaceAccess::canManage($actor))
<a href="{{ route('erp.workspace_access.index', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-[#FCDF02] hover:bg-white/10"><i data-lucide="shield-user" class="h-4 w-4"></i>Company Access Control</a>
@endif
@if($actor->role === 'partner')
<a href="{{ route('erp.custom_roles.index', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-[#FCDF02] hover:bg-white/10"><i data-lucide="shield" class="h-4 w-4"></i>Custom Role Studio</a>
@endif
@if(\App\Support\WorkspaceAccess::allows($actor, 'organization.view'))
<a href="{{ route('erp.organization.index', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"><i data-lucide="network" class="h-4 w-4"></i>Organization Structure</a>
<a href="{{ route('erp.team', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"><i data-lucide="users-round" class="h-4 w-4"></i>Team & Accounts</a>
@endif
<a href="{{ rtrim(config('app.url'), '/') . '/account/companies' }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl border border-white/15 px-4 py-3 text-sm font-semibold text-[#FCDF02] hover:bg-white/10"><i data-lucide="building-2" class="h-4 w-4"></i>My Companies</a>
@if(request()->attributes->get('tenant.workspace_count', 1) > 1)
<a href="{{ route('erp.workspaces.index', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl border border-white/15 px-4 py-3 text-sm font-semibold text-[#FCDF02] hover:bg-white/10"><i data-lucide="network" class="h-4 w-4"></i>Switch Partner Workspace</a>
@endif
<div class="border-t border-white/10 p-4"><form action="{{ route('erp.logout',['firmSlug'=>$firmSlug]) }}" method="POST">@csrf<button class="flex w-full items-center gap-2 rounded-xl border border-white/15 px-4 py-3 text-left text-xs font-bold text-slate-200 hover:bg-white/10"><i data-lucide="log-out" class="h-4 w-4"></i>Sign Out</button></form></div>
</aside>
<div class="min-w-0 lg:pl-[262px]">
<header class="sticky top-0 z-30 flex min-h-[72px] items-center gap-3 border-b border-slate-200 bg-white px-5 sm:px-8">
<button type="button" @click="open=true" class="rounded-lg p-2 lg:hidden" aria-label="Open navigation"><i data-lucide="menu" class="h-5 w-5"></i></button>
<div class="min-w-0 flex-1"><p class="text-[11px] text-slate-400">{{ $company->name }} / {{ $workspace->name }}</p><h1 class="truncate text-lg font-extrabold">@yield('title', 'ERP Workspace')</h1></div>
<span class="hidden rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800 sm:inline-flex">Authenticated Session</span>
</header>
<main class="mx-auto max-w-[1400px] space-y-5 px-4 py-6 sm:px-8">
@if(session('success'))<div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</div>@endif
@if($errors->any())<div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800"><strong>Please review:</strong><ul class="mt-2 list-inside list-disc">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>@endif
@yield('content')
<footer class="border-t border-slate-200 pt-4 text-[11px] text-slate-500">{{ $company->name }} · {{ $workspace->name }} · Access is restricted to your authorized workspace.</footer>
</main></div></div>
@livewireScripts
@stack('scripts')
<script id="erp-corporate-workspace-script">{!! file_get_contents(public_path('js/erp-corporate-workspace.js')) !!}</script>
<dialog class="erp-ux-confirm" data-corporate-confirm-dialog aria-labelledby="erp-ux-confirm-title" aria-describedby="erp-ux-confirm-description">
  <p class="text-xs font-black tracking-widest text-amber-800">✦ CONFIRM BEFORE CHANGING</p>
  <h3 id="erp-ux-confirm-title">Review this action</h3>
  <p id="erp-ux-confirm-description" data-corporate-confirm-label>Are you sure you want to proceed?</p>
  <div class="erp-ux-confirm__buttons"><button type="button" data-corporate-cancel>Cancel</button><button type="button" data-corporate-confirm-go>Yes, proceed →</button></div>
</dialog>
</body></html>