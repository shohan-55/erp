@php
    $activeNotifications = $notificationsList ?? [];
@endphp

<section>
    <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">IN-APP &amp; POPUP NOTIFICATIONS</p>
    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Your Updates &amp; Broadcasts</h2>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Managers and Partners can dispatch targeted popup notifications to a single member, selected members, or everyone in {{ $partnerUnit }}.</p>
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
    <div class="space-y-5">
        @if($previewRole !== 'student')
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
                <div class="flex items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h3 class="text-sm font-extrabold text-slate-900">Send Popup Notification (নোটিফিকেশন পাঠান)</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Appears automatically as a popup modal when the recipient opens their dashboard.</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-[10px] font-bold text-amber-900">Popup Enabled</span>
                </div>

                <form wire:submit.prevent="sendPracticeNotification" class="mt-4 space-y-4 text-xs">
                    <div>
                        <label class="block font-bold text-slate-700 mb-1.5">Recipient Scope (কাদের কাছে পাঠাবেন)</label>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach(['all' => 'All Members (সকলের কাছে)', 'single' => 'Single Person (নির্দিষ্ট একজন)', 'multiple' => 'Selected People (নির্দিষ্ট কয়েকজন)'] as $modeKey => $modeLabel)
                                <button type="button" wire:click="$set('notifTargetMode', '{{ $modeKey }}')"
                                        class="rounded-xl border p-2.5 text-center font-bold transition {{ $notifTargetMode === $modeKey ? 'border-amber-400 bg-amber-50 text-amber-950 ring-2 ring-amber-200' : 'border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100' }}">
                                    {{ $modeLabel }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if($notifTargetMode === 'single')
                        <label class="block font-bold text-slate-700">Select Member
                            <select wire:model="notifSingleUserId" class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs">
                                <option value="">— Choose a Team Member —</option>
                                @foreach($teamMembers as $tm)
                                    <option value="{{ $tm['id'] }}">{{ $tm['name'] }} ({{ $tm['role_label'] }})</option>
                                @endforeach
                            </select>
                        </label>
                    @elseif($notifTargetMode === 'multiple')
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 space-y-1.5 max-h-40 overflow-y-auto">
                            <p class="font-bold text-slate-700 mb-1">Check Recipients:</p>
                            @foreach($teamMembers as $tm)
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" wire:model="notifSelectedUserIds" value="{{ $tm['id'] }}" class="rounded border-slate-300 text-amber-600">
                                    <span>{{ $tm['name'] }} ({{ $tm['role_label'] }})</span>
                                </label>
                            @endforeach
                        </div>
                    @endif

                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block font-bold text-slate-700 sm:col-span-2">Notification Subject / Title
                            <input type="text" wire:model="notifTitle" placeholder="e.g. Urgent Audit Working Paper Submission" required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs">
                        </label>
                        <label class="block font-bold text-slate-700 sm:col-span-2">Message Body
                            <textarea wire:model="notifMessage" rows="2" placeholder="Write your instruction or announcement..." required class="mt-1 w-full rounded-xl border border-slate-200 bg-slate-50 p-2.5 text-xs"></textarea>
                        </label>
                    </div>

                    <div class="flex items-center justify-between pt-1">
                        <label class="inline-flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
                            <input type="checkbox" wire:model="notifShowPopup" class="rounded border-slate-300 text-amber-600">
                            <span>Show as Login Popup on Recipient's Dashboard</span>
                        </label>
                        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-2.5 text-xs font-extrabold text-[#17191F] hover:bg-[#F3D604]">
                            <i data-lucide="send" class="h-4 w-4"></i> Send Notification
                        </button>
                    </div>
                </form>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-5"><div><h3 class="text-sm font-extrabold">Notification Inbox</h3><p class="mt-1 text-xs text-slate-500">Practice notices and communications</p></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-[11px] font-bold text-emerald-800">ACTIVE</span></div>
            @forelse($activeNotifications as $note)
                <div wire:key="note-{{ $note['id'] }}" class="flex items-start gap-3 border-b border-slate-100 p-5 last:border-b-0 {{ in_array($note['id'], $readNotificationIds, true) ? 'bg-white' : 'bg-amber-50/40' }}">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-800"><i data-lucide="{{ $note['icon'] }}" class="h-5 w-5"></i></div>
                    <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold text-slate-800">{{ $note['title'] }}</h3>@if(!in_array($note['id'], $readNotificationIds, true))<span class="rounded-full bg-[#FCDF02] px-2 py-0.5 text-[10px] font-bold">Unread</span>@endif</div><p class="mt-1.5 text-xs leading-relaxed text-slate-500">{{ $note['message'] }}</p><p class="mt-2 text-[11px] font-medium text-slate-400">{{ $note['age'] }}</p></div>
                    <button type="button" wire:click="markPreviewRead('{{ $note['id'] }}')" @disabled(in_array($note['id'], $readNotificationIds, true))
                            class="shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-[11px] font-bold text-slate-700 hover:bg-slate-50 disabled:cursor-default disabled:opacity-50">{{ in_array($note['id'], $readNotificationIds, true) ? 'Read' : 'Mark Read' }}</button>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-400">
                    No notifications or broadcasts yet.
                </div>
            @endforelse
        </section>
    </div>
    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm"><div class="flex items-center gap-3"><i data-lucide="bell-ring" class="h-5 w-5 text-[#FCDF02]"></i><h3 class="text-sm font-extrabold">Delivery Protocols</h3></div><div class="mt-5 space-y-3">
            @foreach(['Instant Dashboard Login Popup','Single, Multi-Select, or All Broadcast','Strict cross-partner privacy boundaries','Multi-tier approval and correction alerts','Audit trail logging for all actions'] as $item)
                <div class="flex items-start gap-2 text-xs leading-relaxed text-slate-300"><i data-lucide="check" class="mt-0.5 h-4 w-4 shrink-0 text-[#FCDF02]"></i>{{ $item }}</div>
            @endforeach
        </div></section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm"><h3 class="text-sm font-extrabold">Privacy &amp; Security</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">Notifications are encrypted in transit and securely bound to your verified user session and role hierarchy.</p></section>
    </aside>
</div>