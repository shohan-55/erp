@extends('layouts.erp')
@section('title','Team & Accounts')
@section('content')
<section><p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">HOME PARTNER · ACCOUNT MANAGEMENT</p><h2 class="mt-1 text-2xl font-extrabold">Manage Your Practice Team</h2><p class="mt-2 text-sm text-slate-500">Invite Managers or Students to {{ $workspace->name }}. They must accept the one-time email invitation before accessing the ERP. Only an authorized Partner can manage these accounts.</p></section>
<section class="grid items-start gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(290px,1fr)]">
<div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"><div class="border-b border-slate-100 p-5"><h3 class="text-sm font-extrabold">Workspace Members</h3><p class="mt-1 text-xs text-slate-500">Staff belonging to your Home Partner only</p></div>
<div class="divide-y divide-slate-100">
@forelse($members as $member)
<article class="p-5"><div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm font-bold">{{ $member->member_name }}</p><p class="mt-1 text-xs text-slate-500">{{ $member->email }}</p><p class="mt-1 text-[11px] text-slate-400">Role: {{ ucfirst($member->role) }} · Member #{{ $member->id }}</p></div><span class="rounded-full px-3 py-1 text-xs font-bold {{ $member->platform_suspended_at ? 'bg-rose-50 text-rose-800' : ($member->active ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800') }}">{{ $member->platform_suspended_at ? 'Suspended by Platform' : ($member->active ? 'Active' : 'Invitation Pending') }}</span></div>
@if(!$member->active && !$member->platform_suspended_at)
<form class="mt-3" action="{{ route('erp.team.resend',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" method="POST">@csrf<button class="rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold hover:bg-slate-50">Resend Invitation Email</button></form>
@endif
@if($member->role==='student')
<form action="{{ route('erp.team.update_student',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}" method="POST" class="mt-4 grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]">@csrf
<label class="text-xs font-bold text-slate-600">Assigned Manager<select name="manager_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-xs"><option value="">No Manager</option>@foreach($managers as $manager)<option value="{{ $manager->id }}" @selected($member->manager_membership_id==$manager->id)>{{ $manager->member_name }}</option>@endforeach</select></label>
<label class="text-xs font-bold text-slate-600">Monthly Allowance BDT<input type="number" name="monthly_allowance" min="0" max="500000" step="0.01" value="{{ $member->monthly_allowance }}" required class="mt-2 w-full rounded-lg border border-slate-300 p-2.5 text-xs"></label>
<button class="rounded-lg bg-slate-900 px-4 py-2.5 text-xs font-bold text-white">Save</button>
</form>@endif
</article>
@empty<p class="p-7 text-sm text-slate-500">No staff memberships found.</p>@endforelse
</div></div>
<aside class="space-y-5">
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Invite Manager or Student</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Only activate accounts by verifying the invitee's email and setting a strong password. SMTP must be configured for delivery.</p>
<form action="{{ route('erp.team.invite',['firmSlug'=>$firmSlug]) }}" method="POST" class="mt-5 space-y-3">@csrf
<label class="block text-xs font-bold text-slate-600">Full Name<input name="name" type="text" required minlength="3" maxlength="255" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Email Address<input name="email" type="email" required maxlength="254" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<label class="block text-xs font-bold text-slate-600">Role<select name="role" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="student">Student</option><option value="manager">Manager</option></select></label>
<label class="block text-xs font-bold text-slate-600">Assign to Manager (Students only)<select name="manager_membership_id" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"><option value="">None</option>@foreach($managers as $manager)<option value="{{ $manager->id }}">{{ $manager->member_name }}</option>@endforeach</select></label>
<label class="block text-xs font-bold text-slate-600">Student Monthly Allowance BDT<input name="monthly_allowance" type="number" step="0.01" min="0" max="500000" value="0" class="mt-2 w-full rounded-lg border border-slate-300 p-3 text-sm"></label>
<button type="submit" class="w-full rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Send Private Invitation</button>
</form></section>
<section class="rounded-2xl bg-[#17191F] p-5 text-white"><h3 class="text-sm font-extrabold">Partner Boundaries</h3><p class="mt-3 text-xs leading-relaxed text-slate-300">You cannot assign a Manager from another Partner, view another Partner's membership, or activate a person without their one-time invitation acceptance. New Partner workspaces are provisioned by an authorized platform operator.</p></section>
</aside>
</section>
@endsection