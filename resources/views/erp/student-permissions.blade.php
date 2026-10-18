@extends('layouts.erp')
@section('title','Student Feature Permissions')
@section('content')
<section class="space-y-2">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PARTNER WORKSPACE SECURITY</p>
<h2 class="text-2xl font-extrabold">Student Dashboard & Feature Permissions</h2>
<p class="max-w-3xl text-sm text-slate-600">Control the grids and modules each Student can access. A hidden feature is also blocked at its direct URL and action endpoints. Changes take effect on the next request. Each Partner workspace is independent.</p>
</section>

@if($actor->role === 'partner')
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<h3 class="text-sm font-extrabold">Manager Delegation</h3>
<p class="mt-1 text-xs text-slate-500">Managers have no editing powers by default. When delegated, a Manager can further restrict features for assigned Students, but never override a Partner's restriction.</p>
<div class="mt-4 grid gap-3 md:grid-cols-2">
@forelse($managers as $manager)
<div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 p-4">
<div><strong class="text-sm">{{ $manager->name }}</strong><p class="text-xs text-slate-500">{{ $manager->can_manage_student_features ? 'Delegated permission' : 'Read-only authority' }}</p></div>
<form action="{{ route('erp.permissions.delegate',['firmSlug'=>$firmSlug,'memberId'=>$manager->id]) }}" method="POST">@csrf
<input type="hidden" name="enabled" value="{{ $manager->can_manage_student_features ? '0' : '1' }}">
<button type="submit" class="rounded-lg px-4 py-2 text-xs font-extrabold {{ $manager->can_manage_student_features ? 'bg-rose-100 text-rose-900' : 'bg-[#FCDF02] text-slate-950' }}">{{ $manager->can_manage_student_features ? 'Revoke' : 'Allow Editing' }}</button>
</form>
</div>
@empty
<p class="text-xs text-slate-500">No active Managers in your workspace.</p>
@endforelse
</div>
</section>
@endif

<section class="space-y-4">
<h3 class="text-base font-extrabold">{{ $actor->role === 'partner' ? 'Student Access by Membership' : 'Your Assigned Students' }}</h3>
@forelse($students as $student)
@php($studentControls = ($controls[$student->id] ?? collect())->keyBy('feature_key'))
<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
<div class="mb-4 flex flex-wrap items-start justify-between gap-2 border-b border-slate-100 pb-4">
<div><h4 class="text-base font-extrabold">{{ $student->name }}</h4><p class="text-xs text-slate-500">{{ $student->email }}</p></div>
<span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold">Student #{{ $student->id }}</span>
</div>
<div class="grid gap-3 md:grid-cols-2">
@foreach($features as $key => $label)
@php($control = $studentControls->get($key))
@php($partnerAllowed = !$control || (bool) $control->partner_enabled)
@php($managerAllowed = !$control || (bool) $control->manager_enabled)
@php($effective = $partnerAllowed && $managerAllowed)
<div class="rounded-xl border border-slate-200 p-4">
<div class="mb-3 flex flex-wrap items-center justify-between gap-2">
<span class="text-sm font-bold">{{ $label }}</span>
<span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $effective ? 'bg-emerald-50 text-emerald-800' : 'bg-rose-50 text-rose-800' }}">{{ $effective ? 'Active' : 'Disabled' }}</span>
</div>
@if($actor->role === 'manager' && !$partnerAllowed)
<p class="text-xs text-rose-700">Partner restriction · Only the Partner can reactivate</p>
@else
<form action="{{ route('erp.permissions.student',['firmSlug'=>$firmSlug,'memberId'=>$student->id]) }}" method="POST">@csrf
<input type="hidden" name="feature_key" value="{{ $key }}">
<input type="hidden" name="enabled" value="{{ ($actor->role === 'partner' ? $partnerAllowed : $managerAllowed) ? '0' : '1' }}">
<button type="submit" class="w-full rounded-lg px-3 py-2 text-xs font-extrabold {{ ($actor->role === 'partner' ? $partnerAllowed : $managerAllowed) ? 'bg-slate-100 text-slate-800 hover:bg-slate-200' : 'bg-[#FCDF02] text-slate-900' }}">{{ ($actor->role === 'partner' ? $partnerAllowed : $managerAllowed) ? 'Disable' : 'Enable' }} {{ $actor->role === 'partner' ? 'Partner Access' : 'Student Grid' }}</button>
</form>
@endif
@if($actor->role === 'partner' && !$managerAllowed)
<p class="mt-2 text-xs text-amber-800">Manager also restricted this feature.</p>
@endif
</div>
@endforeach
</div>
</article>
@empty
<div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">No active Students assigned to your authorized team.</div>
@endforelse
</section>
@endsection
