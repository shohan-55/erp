<section class="flex flex-col justify-between gap-3 lg:flex-row lg:items-end">
    <div>
        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">LEAVE MANAGEMENT · PHASE 08</p>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Leave Requests</h2>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Student applications, Manager review and Partner oversight within the employee's Home Partner. All balances and requests below are illustrative.</p>
    </div>
    @if($previewRole !== 'student')
        <button type="button" wire:click="openDialog('leave')" class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3 text-xs font-extrabold hover:bg-[#F3D604]"><i data-lucide="clipboard-check" class="h-4 w-4"></i> Review Leave (Preview)</button>
    @endif
</section>

<div class="grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,1fr)]">
    <div class="space-y-5">
        @if($previewRole === 'student')
            <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-7">
                <div class="flex items-center justify-between gap-3"><h3 class="text-base font-extrabold">New Leave Application</h3><span class="rounded-full bg-amber-50 px-3 py-1 text-[11px] font-bold text-amber-800">Prototype Form</span></div>
                <p class="mt-2 text-xs text-slate-500">You can validate this sample form. Submitting does not create a request or notify anyone.</p>
                <form wire:submit.prevent="submitLeaveDemo" class="mt-6 space-y-4" novalidate>
                    <label class="block text-xs font-bold text-slate-700">Leave Category
                        <select wire:model="leaveType" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                            <option value="annual">Annual Leave</option><option value="sick">Sick Leave</option><option value="emergency">Emergency Leave</option>
                        </select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-bold text-slate-700">Start Date
                            <input type="date" wire:model="leaveStart" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                        <label class="block text-xs font-bold text-slate-700">End Date
                            <input type="date" wire:model="leaveEnd" required class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400">
                        </label>
                    </div>
                    <label class="block text-xs font-bold text-slate-700">Reason
                        <textarea wire:model="leaveReason" rows="3" minlength="5" maxlength="500" placeholder="Enter a brief reason for the example application" class="mt-2 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm outline-none focus:border-amber-400"></textarea>
                    </label>
                    <button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#FCDF02] px-4 py-3.5 text-sm font-extrabold text-[#17191F] hover:bg-[#F3D604]"><i data-lucide="send" class="h-4 w-4"></i> Validate Sample Application</button>
                </form>
            </section>
        @else
            <section class="overflow-hidden rounded-2xl border border-[#E8EAF0] bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-5"><h3 class="text-sm font-extrabold">Example Leave Review Queue</h3><p class="mt-1 text-xs text-slate-500">No requests were actually submitted. Partner Unit 01 only.</p></div>
                @php
                    $leaveQueue = [
                        ['name'=>'Sample Student 01', 'manager'=>'Sample Manager A', 'type'=>'Annual Leave', 'period'=>'14–15 Oct 2026', 'status'=>'Awaiting Manager'],
                        ['name'=>'Sample Student 02', 'manager'=>'Sample Manager A', 'type'=>'Sick Leave', 'period'=>'19 Oct 2026', 'status'=>'Awaiting Partner'],
                        ['name'=>'Sample Student 03', 'manager'=>'Sample Manager B', 'type'=>'Emergency Leave', 'period'=>'20 Oct 2026', 'status'=>'In Review']
                    ];
                    $leaveQueue = $previewRole === 'manager'
                        ? array_values(array_filter($leaveQueue, fn($q)=>$q['manager'] === 'Sample Manager A')) : $leaveQueue;
                @endphp
                <div class="divide-y divide-slate-100">
                    @foreach($leaveQueue as $request)
                        <div wire:key="leave-{{ $loop->index }}" class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0"><p class="text-sm font-bold text-slate-800">{{ $request['name'] }}</p><p class="mt-1 text-xs text-slate-500">{{ $request['type'] }} · {{ $request['period'] }}</p></div>
                            <div class="flex flex-wrap items-center gap-2"><span class="rounded-full bg-amber-50 px-2.5 py-1 text-[11px] font-bold text-amber-800">{{ $request['status'] }} · Demo</span><button type="button" wire:click="openDialog('leave')" class="rounded-lg border border-slate-200 px-3 py-2 text-xs font-bold hover:bg-slate-50">Review UI</button></div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-extrabold">{{ $previewRole === 'student' ? 'My Example Requests' : 'Decision Workflow' }}</h3><i data-lucide="calendar-days" class="h-5 w-5 text-amber-800"></i></div>
            @if($previewRole === 'student')
                <div class="flex items-center justify-between gap-3 rounded-xl border border-slate-100 bg-slate-50 p-4"><div><p class="text-sm font-bold">Annual Leave · Sample</p><p class="mt-1 text-xs text-slate-500">14–15 Oct 2026 · 2 illustrative days</p></div><span class="rounded-full bg-amber-100 px-2 py-1 text-[11px] font-bold text-amber-900">Pending · Demo</span></div>
            @else
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach([['1','Student applies'],['2','Assigned Manager reviews'],['3','Home Partner finalizes']] as $step)
                        <div class="rounded-xl bg-slate-50 p-4"><span class="text-2xl font-extrabold text-amber-700">{{ $step[0] }}</span><p class="mt-2 text-xs font-bold text-slate-700">{{ $step[1] }}</p></div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>

    <aside class="space-y-5">
        <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm">
            <h3 class="text-sm font-extrabold">Leave Policy Overview</h3>
            <p class="mt-2 text-xs leading-relaxed text-slate-300">Entitlements and approval chains are configurable by Company Default and permitted Partner Override, subject to versioned rules.</p>
            <div class="mt-5 space-y-3">
                <div class="flex justify-between border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">Home Partner</span><strong>Unit 01 (Example)</strong></div>
                <div class="flex justify-between border-b border-white/10 pb-3 text-xs"><span class="text-slate-400">Annual Balance</span><strong>Not configured</strong></div>
                <div class="flex justify-between text-xs"><span class="text-slate-400">Approval</span><strong>Role-scoped (planned)</strong></div>
            </div>
            <p class="mt-5 text-[11px] leading-relaxed text-amber-200">No statutory leave entitlement, balance or financial deduction is implied by this preview.</p>
        </section>
        <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm">
            <div class="flex items-start gap-3"><div class="rounded-xl bg-amber-50 p-2.5 text-amber-800"><i data-lucide="shield-check" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Partner-wise Privacy</h3><p class="mt-2 text-xs leading-relaxed text-slate-500">A Manager may review only assigned students. A Student can access only personal records. Real enforcement requires future tenant-aware authorization and database policies.</p></div></div>
        </section>
    </aside>
</div>