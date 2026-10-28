<section>
    <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">IN-APP NOTIFICATIONS</p>
    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Your Updates</h2>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Practice announcements, attendance reminders, approval decisions and payroll notices.</p>
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(260px,1fr)]">
    <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h3 class="text-sm font-extrabold">Notification Inbox</h3><p class="mt-1 text-xs text-slate-500">Practice notices and communications</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800">ACTIVE</span></div>
        @foreach([
            ['id'=>'demo-note-1','icon'=>'clock-3','title'=>'Attendance Policy Reminder','message'=>'A missed check-out requires a correction request rather than counting a full workday.','age'=>'Today'],
            ['id'=>'demo-note-2','icon'=>'calendar-days','title'=>'Leave Workflow Notification','message'=>'Submitted leave applications route directly through assigned Manager and Home Partner approval chains.','age'=>'Yesterday'],
            ['id'=>'demo-note-3','icon'=>'file-check-2','title'=>'Monthly Payroll Notice','message'=>'The monthly stipend register locks effective policy versions upon partner sign-off.','age'=>'This month']
        ] as $note)
            <div wire:key="note-{{ $note['id'] }}" class="flex items-start gap-3 border-b border-slate-100 p-5 last:border-b-0 {{ in_array($note['id'], $readNotificationIds, true) ? 'bg-white' : 'bg-amber-50/40' }}">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800"><i data-lucide="{{ $note['icon'] }}" class="h-5 w-5"></i></div>
                <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold text-slate-800">{{ $note['title'] }}</h3>@if(!in_array($note['id'], $readNotificationIds, true))<span class="rounded-full bg-[#FCDF02] px-2 py-0.5 text-[10px] font-bold">Unread</span>@endif</div><p class="mt-1.5 text-xs leading-relaxed text-slate-500">{{ $note['message'] }}</p><p class="mt-2 text-[11px] font-medium text-slate-400">{{ $note['age'] }}</p></div>
                <button type="button" wire:click="markPreviewRead('{{ $note['id'] }}')" @disabled(in_array($note['id'], $readNotificationIds, true))
                        class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-50 disabled:cursor-default disabled:opacity-50">{{ in_array($note['id'], $readNotificationIds, true) ? 'Read' : 'Mark Read' }}</button>
            </div>
        @endforeach
    </section>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm"><div class="flex items-center gap-3"><i data-lucide="bell-ring" class="h-5 w-5 text-[#FCDF02]"></i><h3 class="text-sm font-extrabold">Delivery Protocols</h3></div><div class="mt-5 space-y-3">
            @foreach(['In-app real-time alerts','Strict cross-partner privacy boundaries','Multi-tier approval and correction alerts','Confidential financial notices without broad exposure','Audit trail logging for all actions'] as $item)
                <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-300"><i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-[#FCDF02]"></i>{{ $item }}</div>
            @endforeach
        </div></section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Privacy & Security</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Notifications are encrypted in transit and securely bound to your verified user session and role hierarchy.</p></section>
    </aside>
</div>