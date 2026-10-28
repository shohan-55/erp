@props(['firmSlug' => ''])

<div class="min-h-screen w-full flex items-center justify-center p-6 bg-slate-900 text-white font-sans">
    <div class="max-w-md w-full bg-slate-800/80 border border-slate-700/80 rounded-2xl p-8 text-center shadow-2xl backdrop-blur">
        <div class="w-16 h-16 mx-auto mb-5 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400">
            <i data-lucide="building" class="w-8 h-8"></i>
        </div>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-amber-500/10 text-amber-300 border border-amber-500/20 mb-3">
            Tenant Workspace Unavailable
        </span>
        <h1 class="text-2xl font-bold tracking-tight text-white mb-2">Firm Node Not Found</h1>
        <p class="text-slate-400 text-sm leading-relaxed mb-6">
            The practice identifier <code class="px-2 py-0.5 rounded bg-slate-900 text-amber-300 text-xs font-mono">/c/{{ $firmSlug }}</code> has not been provisioned or is currently inactive on this SaaS cluster.
        </p>
        <div class="space-y-3">
            <a href="{{ url('/') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-semibold text-sm bg-[#FCDF02] hover:bg-[#F0D400] text-[#16181D] shadow-lg transition-all focus:outline-none focus:ring-2 focus:ring-[#FCDF02]/60">
                <i data-lucide="home" class="w-4 h-4"></i>
                <span>Return to DhakaWala ERP Portal</span>
            </a>
            <a href="{{ url('/platform/login') }}" class="w-full inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl font-medium text-sm text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700/80 border border-slate-700 transition-all">
                <i data-lucide="shield" class="w-4 h-4"></i>
                <span>Platform Super Admin Portal</span>
            </a>
        </div>
        <div class="mt-8 pt-6 border-t border-slate-700/60 text-xs text-slate-500">
            DhakaWala ERP SaaS • Cluster: <span class="text-slate-400">erp.dhakawala.com</span>
        </div>
    </div>
</div>
