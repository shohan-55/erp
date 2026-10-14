@extends('layouts.erp')
@section('title','Workspace Dashboard')
@section('content')
<section><p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRACTICE OPERATIONS</p><h2 class="mt-2 text-2xl font-extrabold sm:text-3xl">Welcome, {{ auth()->user()->name }}</h2><p class="mt-2 text-sm text-slate-500">Live statistics for {{ $workspace->name }}. {{ $actor->role === 'manager' ? 'Your assigned student team only.' : ($actor->role === 'student' ? 'Your own records only.' : 'Your own Partner workspace only.') }}</p></section>
<section class="grid grid-cols-2 gap-3 xl:grid-cols-4" aria-label="Live workspace metrics">
@foreach([
['label'=>'Accessible Team','value'=>$stats['members'],'icon'=>'users'],
['label'=>'Checked in Today','value'=>$stats['checked_in_today'],'icon'=>'calendar-check-2'],
['label'=>'Leave for Review','value'=>$stats['pending_leaves'],'icon'=>'calendar-days'],
['label'=>'Pending Claims','value'=>$stats['pending_claims'],'icon'=>'receipt']
] as $item)
<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6"><div class="flex items-start justify-between gap-2"><p class="text-xs font-semibold text-slate-500">{{ $item['label'] }}</p><i data-lucide="{{ $item['icon'] }}" class="h-5 w-5 text-amber-700"></i></div><p class="mt-5 text-3xl font-extrabold">{{ $item['value'] }}</p></div>
@endforeach
</section>
<section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(260px,1fr)]">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="flex items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h3 class="text-sm font-extrabold">Recent Attendance</h3><p class="mt-1 text-xs text-slate-500">Server-recorded sessions</p></div><a href="{{ route('erp.attendance',['firmSlug'=>$firmSlug]) }}" class="text-xs font-bold text-amber-800 hover:underline">View records →</a></div>
<div class="divide-y divide-slate-100">
@forelse($recent as $item)
<div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 text-sm"><div><strong>{{ $item->member_name }}</strong><p class="mt-1 text-xs text-slate-500">{{ $item->work_date }} · {{ ucfirst($item->work_mode) }}</p></div><span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $item->ended_at ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900' }}">{{ $item->ended_at ? 'Checked out' : 'Session active' }}</span></div>
@empty<p class="p-7 text-sm text-slate-500">No attendance sessions yet.</p>@endforelse
</div></div>
<aside class="space-y-4"><div class="rounded-2xl bg-[#17191F] p-6 text-white"><h3 class="text-base font-extrabold">Office Policy</h3><p class="mt-3 text-sm text-slate-300">Office start: {{ substr($company->office_start,0,5) }} · End: {{ substr($company->office_end,0,5) }}</p><p class="mt-2 text-sm text-slate-300">Grace: {{ $company->grace_minutes }} minutes</p><p class="mt-2 text-xs text-amber-200">Time zone: {{ $company->timezone }} · No automatic payroll late penalty</p></div>
<a href="{{ route('erp.notifications',['firmSlug'=>$firmSlug]) }}" class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-5 text-sm font-bold shadow-sm"><span>Unread Notifications</span><span class="rounded-full bg-[#FCDF02] px-3 py-1">{{ $stats['unread_notifications'] }}</span></a></aside>
</section>
@endsection