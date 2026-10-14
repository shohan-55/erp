<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>@yield('title', 'ERP Workspace') · {{ $company->name }}</title>
@vite(['resources/css/app.css','resources/js/app.js'])
<style>[x-cloak]{display:none!important}</style>@livewireStyles</head>
<body class="min-h-screen bg-[#F6F7F9] font-sans text-[#17191F]">
<div x-data="{ open:false }" class="min-h-screen">
<div x-show="open" x-cloak @click="open=false" class="fixed inset-0 z-40 bg-slate-950/60 lg:hidden"></div>
<aside :class="open ? 'translate-x-0' : '-translate-x-full'" class="fixed inset-y-0 left-0 z-50 flex w-[262px] flex-col bg-[#17191F] text-white shadow-xl transition-transform lg:translate-x-0">
<div class="flex items-center gap-3 border-b border-white/10 px-5 py-5"><div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white p-1.5"><img src="{{ asset('images/rac_logo.svg') }}" alt="" class="h-full object-contain"></div><div class="min-w-0"><p class="truncate text-sm font-extrabold">{{ $company->name }}</p><p class="text-[10px] text-slate-400">{{ $workspace->name }}</p></div><button type="button" @click="open=false" class="ml-auto lg:hidden" aria-label="Close navigation">×</button></div>
<div class="border-b border-white/10 p-4"><p class="text-[11px] font-bold uppercase tracking-widest text-[#FCDF02]">{{ ucfirst($actor->role) }} Workspace</p><p class="mt-1 truncate text-xs text-slate-300">{{ auth()->user()->name }}</p></div>
<nav class="flex-1 space-y-1 overflow-y-auto p-3" aria-label="ERP Navigation">
@foreach([
['label'=>'Dashboard','name'=>'erp.dashboard','icon'=>'layout-dashboard'],
['label'=>'Attendance','name'=>'erp.attendance','icon'=>'calendar-check-2'],
['label'=>'Leave Requests','name'=>'erp.leaves','icon'=>'calendar-days'],
['label'=>'Conveyance','name'=>'erp.claims','icon'=>'receipt'],
['label'=>'Payroll','name'=>'erp.payroll','icon'=>'wallet-cards'],
['label'=>'Policy & Governance','name'=>'erp.policies','icon'=>'shield-check'],
['label'=>'Notifications','name'=>'erp.notifications','icon'=>'bell'],
] as $item)
<a href="{{ route($item['name'], ['firmSlug'=>$firmSlug]) }}" class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold transition {{ request()->routeIs($item['name']) ? 'bg-[#FCDF02] text-[#17191F]' : 'text-slate-300 hover:bg-white/10 hover:text-white' }}">
<i data-lucide="{{ $item['icon'] }}" class="h-4 w-4"></i>{{ $item['label'] }}</a>
@endforeach
</nav>
@if($actor->role === 'partner')
<a href="{{ route('erp.team', ['firmSlug'=>$firmSlug]) }}" class="mx-3 mb-3 flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-white/10 hover:text-white"><i data-lucide="users-round" class="h-4 w-4"></i>Team & Accounts</a>
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
</body></html>