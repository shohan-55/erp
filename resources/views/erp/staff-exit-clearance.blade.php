@extends('layouts.erp')
@section('title','Staff Exit Clearance')
@section('content')
<section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
<p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PARTNER HR GOVERNANCE</p>
<h2 class="mt-2 text-2xl font-extrabold">Staff Exit Clearance · Membership #{{ $member->id }}</h2>
<p class="mt-2 max-w-3xl text-sm text-slate-600">This private checklist is read-only. The same checks run again inside the final, serialized Partner decision. No payroll, leave, file, audit or attendance history is erased during offboarding.</p>
<a href="{{ route('erp.staff',['firmSlug'=>$firmSlug]) }}" class="mt-4 inline-block rounded-lg border border-slate-200 px-4 py-2 text-xs font-bold">Back to Staff Management</a>
<div class="mt-5 grid gap-3 md:grid-cols-3">
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Current Access</p><p class="mt-2 text-lg font-extrabold">{{ $member->active ? 'Active' : 'Inactive' }}</p></div>
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Employment State</p><p class="mt-2 text-lg font-extrabold">{{ ucfirst($profile->employment_status ?? 'Not profiled') }}</p></div>
<div class="rounded-xl bg-slate-50 p-4"><p class="text-xs text-slate-500">Existing Privilege Edges</p><p class="mt-2 text-lg font-extrabold">{{ $grantCount }} grants{{ $hasCustomRole ? ' + custom role' : '' }}</p></div>
</div>
@if($issues)
<div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
<h3 class="text-sm font-extrabold text-amber-950">Exit Blockers · {{ count($issues) }}</h3>
<ul class="mt-3 list-disc space-y-2 pl-5 text-xs text-amber-950">
@foreach($issues as $key=>$issue)<li><strong>{{ ucwords(str_replace('_',' ',$key)) }}:</strong> {{ $issue }}</li>@endforeach
</ul></div>
@else
<div class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-950">
No outstanding workflow blocker detected in this workspace at the time of the preview. The exit endpoint still rechecks everything under lock.</div>
@endif
@if($transferred)
<div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs font-bold text-rose-950">A completed transfer moved this identity to a different Partner membership. The original source membership may never rejoin here.</div>
@endif
<div class="mt-5 rounded-xl border border-slate-200 p-4">
<h3 class="text-sm font-extrabold">Rejoining & Permissions</h3>
<p class="mt-2 text-xs text-slate-600">Exit permanently revokes local Manager/Moderator access grants and removes the live custom-role assignment. Every former authorization remains traceable in the tenant audit trail, but a returning Manager receives <strong>no old elevated rights</strong>. The Partner must explicitly grant new permissions after rejoin. Transferred source memberships cannot rejoin.</p>
</div>
@if($member->active && !$member->platform_suspended_at)
<form method="POST" action="{{ route('erp.staff.lifecycle',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}"
 data-corporate-confirm="Confirm offboarding? All current Manager delegation and custom-role access will be revoked, and the staff member will lose this workspace access."
 class="mt-5 space-y-3">@csrf
<input type="hidden" name="action" value="exit">
<label class="block text-xs font-bold">Business Exit Reason
<textarea name="reason" required minlength="15" maxlength="1000" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
</label>
<button type="submit" @disabled(count($issues)>0)
class="rounded-xl px-5 py-3 text-sm font-extrabold {{ $issues ? 'bg-slate-200 text-slate-600' : 'bg-rose-800 text-white' }}">Record Audited Staff Exit</button>
</form>
@elseif(!$member->active && ($profile->employment_status ?? null)==='exited' && !$transferred && !$member->platform_suspended_at)
<form method="POST" action="{{ route('erp.staff.lifecycle',['firmSlug'=>$firmSlug,'id'=>$member->id]) }}"
 data-corporate-confirm="Rejoin without restoring any previous Manager delegation or custom-role access?"
 class="mt-5 space-y-3">@csrf
<input type="hidden" name="action" value="rejoin">
<label class="block text-xs font-bold">Reason for Rejoining
<textarea name="reason" required minlength="15" maxlength="1000" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 p-3 text-sm"></textarea>
</label>
<button class="rounded-xl bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Record Audited Rejoin</button>
</form>
@endif
</section>
@endsection
