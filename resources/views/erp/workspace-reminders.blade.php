@extends('layouts.erp')
@section('title','Workspace Reminder Rules')
@section('content')
<section>
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRIVATE · PARTNER CONTROL</p>
    <h2 class="mt-2 text-2xl font-extrabold">Notification Rules</h2>
    <p class="mt-2 text-sm text-slate-600">Opt-in reminders for unfinished engagement milestones assigned to active staff of this independent workspace. Sends inbox notifications only. No external emails, payroll actions, or automatic changes to milestone evidence.</p>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="font-extrabold">Engagement Milestone Deadlines</h3>
    <form method="POST" action="{{ route('erp.reminder_rules.save',['firmSlug'=>$firmSlug]) }}" class="mt-4 grid gap-3 md:grid-cols-2">
        @csrf
        <label class="text-sm font-bold">Daily reminder check
            <select name="active" required class="mt-1 block w-full rounded-lg border border-slate-300 p-3">
                <option value="0" @selected(!($rule->active ?? false))>Disabled (default)</option>
                <option value="1" @selected((bool)($rule->active ?? false))>Enabled — private inbox only</option>
            </select>
        </label>
        <label class="text-sm font-bold">Days before due date (0–30)
            <input type="number" name="days_before" required min="0" max="30" class="mt-1 block w-full rounded-lg border border-slate-300 p-3" value="{{ old('days_before',$rule->days_before ?? 3) }}">
        </label>
        <label class="text-sm font-bold md:col-span-2">Reason for change
            <textarea name="reason" required minlength="15" maxlength="500" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 p-3" placeholder="Explain who needs these reminders and why the rule is appropriate.">{{ old('reason') }}</textarea>
        </label>
        <div class="md:col-span-2"><button class="rounded-lg bg-[#FCDF02] px-5 py-3 text-sm font-extrabold">Save Audited Rule</button></div>
    </form>
    <p class="mt-4 text-xs text-slate-500">One upcoming and one overdue reminder at most per milestone, due date and rule. Overdue items older than 30 days are excluded from automatic reminders. Delivery depends on the configured daily Laravel scheduler and only happens after the rule is enabled. Changes do not erase existing notifications.</p>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="font-extrabold">Latest Delivery Records</h3>
    <div class="mt-3 overflow-x-auto"><table class="min-w-full text-left text-xs">
        <thead class="bg-slate-50"><tr><th class="px-3 py-2">Milestone</th><th class="px-3 py-2">Recipient Membership</th><th class="px-3 py-2">Due On</th><th class="px-3 py-2">Kind</th><th class="px-3 py-2">Delivered UTC</th></tr></thead>
        <tbody class="divide-y divide-slate-100">
            @forelse($recent as $entry)
                <tr><td class="px-3 py-2">#{{ $entry->milestone_id }}</td><td class="px-3 py-2">#{{ $entry->recipient_membership_id }}</td><td class="px-3 py-2">{{ $entry->due_on }}</td><td class="px-3 py-2">{{ ucfirst($entry->kind) }}</td><td class="px-3 py-2">{{ $entry->created_at }}</td></tr>
            @empty <tr><td colspan="5" class="p-4 text-slate-500">No reminder deliveries yet.</td></tr>
            @endforelse
        </tbody>
    </table></div>
</section>
@endsection
