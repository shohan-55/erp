<section>
    <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-amber-800">WORKSPACE CONFIGURATION · PHASE 10</p>
    <h2 class="mt-1 text-2xl font-extrabold tracking-tight">Workspace Settings</h2>
    <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500">Future settings for an individual Partner workspace. No organization, credential, account or policy changes can be made in this design preview.</p>
</section>
<div class="grid gap-5 lg:grid-cols-2">
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-[#17191F] text-[#FCDF02]"><i data-lucide="building-2" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Organization Identity</h3><p class="mt-1 text-xs text-slate-500">Read-only sample fields · firm-managed later</p></div></div>
        <dl class="mt-6 space-y-4">
            @foreach([
                ['Firm Name','Rahman Anis & Co.'],
                ['Practice Workspace','Partner Unit 01 (Example)'],
                ['Application URL','erp.dhakawala.com/c/rac'],
                ['Region','Asia/Dhaka'],
                ['Current Persona',$roleLabel.' (Preview)']
            ] as $field)
                <div class="flex flex-col justify-between gap-1 border-b border-slate-100 pb-3 text-xs sm:flex-row sm:items-center"><dt class="font-semibold text-slate-500">{{ $field[0] }}</dt><dd class="break-all font-bold text-slate-800">{{ $field[1] }}</dd></div>
            @endforeach
        </dl>
    </section>
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="users" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Roles & Team Management</h3><p class="mt-1 text-xs text-slate-500">Access governed by assigned organization and Partner</p></div></div>
        <div class="mt-5 space-y-3">
            @foreach([
                ['Partner / Principal','Own workspace policy and team oversight'],
                ['Manager','Only assigned student team'],
                ['Student','Own check-in, leave, claim and allowance records']
            ] as $row)
                <div class="rounded-xl border border-slate-100 bg-slate-50 p-4"><p class="text-xs font-extrabold">{{ $row[0] }}</p><p class="mt-1 text-xs leading-relaxed text-slate-500">{{ $row[1] }} (planned)</p></div>
            @endforeach
        </div>
    </section>
    <section class="rounded-2xl border border-[#E8EAF0] bg-white p-5 shadow-sm sm:p-6">
        <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-amber-50 text-amber-800"><i data-lucide="calendar-cog" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Attendance Defaults</h3><p class="mt-1 text-xs text-slate-500">Company Default Policy with controlled Partner Override</p></div></div>
        <div class="mt-5 grid grid-cols-2 gap-3">
            @foreach([['Office Start','10:00'],['Office End','18:00'],['Grace Period','10 mins'],['Weekly Holiday','Friday, Saturday']] as $row)
                <div class="rounded-xl bg-slate-50 p-4"><p class="text-[11px] font-semibold text-slate-500">{{ $row[0] }}</p><p class="mt-2 text-sm font-extrabold">{{ $row[1] }}</p></div>
            @endforeach
        </div>
        <button type="button" wire:click="setSection('policies')" class="mt-5 inline-flex items-center gap-1 text-xs font-bold text-amber-800 hover:underline">Open Policy Framework →</button>
    </section>
    <section class="rounded-2xl border border-[#E8EAF0] bg-[#17191F] p-5 text-white shadow-sm sm:p-6">
        <div class="flex items-start gap-3"><div class="flex h-11 w-11 shrink-0 self-start items-center justify-center rounded-xl bg-white/10 text-[#FCDF02]"><i data-lucide="shield-check" class="h-5 w-5"></i></div><div><h3 class="text-sm font-extrabold">Privacy & Support</h3><p class="mt-1 text-xs text-slate-300">Sensitive records remain private to authorized tenant roles.</p></div></div>
        <div class="mt-5 space-y-3 text-xs leading-relaxed text-slate-300">
            <p>Future assistance will require a scope-limited, read-only, expiring grant approved by the firm. Each access must be recorded.</p>
            <p>No active grants or real authorization exist in this UI preview.</p>
            <p>Two-factor authentication, account recovery and session controls will be implemented and audited as part of the functional backend.</p>
        </div>
        <div class="mt-5 inline-flex rounded-full border border-[#FCDF02]/25 bg-[#FCDF02]/10 px-3 py-2 text-[11px] font-semibold text-[#FCDF02]">Settings not editable in Design Preview</div>
    </section>
</div>