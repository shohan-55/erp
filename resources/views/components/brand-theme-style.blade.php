@props(['firm' => null])
@php
    $primary     = $firm['primary_color'] ?? '#0D9488';
    $hover       = $firm['hover_color']   ?? '#0F766E';
    $accent      = $firm['accent_color']  ?? '#14B8A6';
    $lightAccent = $firm['light_accent']  ?? '#2DD4BF';
    $softBg      = $firm['soft_bg']       ?? '#F0FDFA';
    $softBorder  = $firm['soft_border']   ?? '#99F6E4';
    $darkText    = $firm['dark_text']     ?? '#115E59';
    $onPrimary   = $firm['on_primary']    ?? '#FFFFFF';
@endphp
<style id="erp-dynamic-brand-theme">
    :root {
        --brand-primary: {{ $primary }};
        --brand-hover: {{ $hover }};
        --brand-accent: {{ $accent }};
        --brand-light-accent: {{ $lightAccent }};
        --brand-soft-bg: {{ $softBg }};
        --brand-soft-border: {{ $softBorder }};
        --brand-dark-text: {{ $darkText }};
        --brand-on-primary: {{ $onPrimary }};
    }

    /* Primary Solid Buttons & Badges */
    .bg-\[\#FCDF02\] {
        background-color: var(--brand-primary) !important;
        color: var(--brand-on-primary) !important;
    }
    .bg-\[\#FCDF02\]:hover,
    .hover\:bg-\[\#F3D604\]:hover,
    .hover\:bg-\[\#F0D400\]:hover,
    .hover\:bg-\[\#f0d400\]:hover {
        background-color: var(--brand-hover) !important;
        color: var(--brand-on-primary) !important;
    }

    /* Brand Text Highlights (High-contrast on dark sidebars/headers) */
    .text-\[\#FCDF02\],
    .text-amber-300,
    .text-amber-400,
    .group:hover .group-hover\:text-\[\#FCDF02\] {
        color: var(--brand-light-accent) !important;
    }

    /* Dark Brand Text on Light Backgrounds */
    .text-\[\#B08400\],
    .text-\[\#B08300\],
    .text-\[\#916800\],
    .text-\[\#977000\],
    .text-\[\#A57600\],
    .text-\[\#A57A00\],
    .text-amber-700,
    .text-amber-800,
    .text-amber-900,
    .text-amber-950 {
        color: var(--brand-dark-text) !important;
    }

    /* Soft Tinted Backgrounds */
    .bg-amber-50,
    .bg-amber-50\/40,
    .bg-amber-50\/50,
    .bg-amber-50\/60,
    .bg-amber-50\/70,
    .bg-amber-50\/80,
    .bg-amber-100,
    .bg-amber-100\/80,
    .bg-\[\#FFFBE5\] {
        background-color: var(--brand-soft-bg) !important;
    }

    /* Translucent Brand Tints on Dark Surfaces */
    .bg-\[\#FCDF02\]\/10,
    .bg-\[\#FCDF02\]\/15,
    .bg-amber-400\/10,
    .bg-amber-400\/20,
    .bg-amber-500\/10,
    .bg-amber-500\/20 {
        background-color: color-mix(in srgb, var(--brand-light-accent) 16%, transparent) !important;
    }

    /* Brand Borders & Rings */
    .border-\[\#FCDF02\],
    .border-amber-200,
    .border-amber-200\/70,
    .border-amber-200\/80,
    .border-amber-300,
    .border-amber-400 {
        border-color: var(--brand-soft-border) !important;
    }
    .border-\[\#FCDF02\]\/20,
    .border-\[\#FCDF02\]\/30,
    .border-amber-400\/20,
    .border-amber-400\/30,
    .border-amber-500\/20 {
        border-color: color-mix(in srgb, var(--brand-light-accent) 32%, transparent) !important;
    }
    .focus\:border-\[\#FCDF02\]:focus,
    .focus\:border-amber-400:focus,
    .focus-within\:border-amber-400:focus-within {
        border-color: var(--brand-primary) !important;
    }
    .focus\:ring-\[\#FCDF02\]:focus,
    .focus\:ring-\[\#FCDF02\]\/40:focus,
    .focus\:ring-amber-200:focus,
    .ring-amber-200,
    .ring-amber-300 {
        --tw-ring-color: var(--brand-soft-border) !important;
    }

    /* Brand Gradients (Hero Text, Buttons & Top Bars) */
    .from-\[\#FCDF02\],
    .from-amber-500 {
        --tw-gradient-from: var(--brand-light-accent) var(--tw-gradient-from-position) !important;
        --tw-gradient-to: rgb(255 255 255 / 0) var(--tw-gradient-to-position) !important;
        --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to) !important;
    }
    .via-\[\#F5AD16\],
    .via-\[\#FCDF02\] {
        --tw-gradient-to: rgb(255 255 255 / 0) var(--tw-gradient-to-position) !important;
        --tw-gradient-stops: var(--tw-gradient-from), var(--brand-primary) var(--tw-gradient-via-position), var(--tw-gradient-to) !important;
    }
    .to-\[\#FCDF02\],
    .to-amber-300,
    .to-amber-400 {
        --tw-gradient-to: var(--brand-accent) var(--tw-gradient-to-position) !important;
    }

    /* Primary Gradient Submit Button */
    button.bg-gradient-to-r.from-\[\#FCDF02\] {
        background-image: linear-gradient(to right, var(--brand-primary), var(--brand-accent), var(--brand-primary)) !important;
        color: var(--brand-on-primary) !important;
    }

    ::selection {
        background-color: var(--brand-primary);
        color: var(--brand-on-primary);
    }

/* =====================================================================
   Phase 08–09 Ultra-Premium UI & Grid Utilities (cPanel Pre-Compiled)
   ===================================================================== */
@keyframes erp-tw-ping {
    75%, 100% { transform: scale(2); opacity: 0; }
}
.animate-ping { animation: erp-tw-ping 1s cubic-bezier(0, 0, 0.2, 1) infinite; }
.duration-700 { transition-duration: 700ms; }
.drop-shadow-sm { filter: drop-shadow(0 1px 1px rgb(0 0 0 / 0.05)); }
.shadow-2xs { box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.04); }
.hover\:shadow-md:hover { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
.hover\:scale-\[1\.01\]:hover { transform: scale(1.01); }
.object-cover { object-fit: cover; }
.items-baseline { align-items: baseline; }
.items-end { align-items: flex-end; }
.inset-x-0 { left: 0px; right: 0px; }
.left-1\/3 { left: 33.333333%; }
.-right-16 { right: -4rem; }
.-right-20 { right: -5rem; }
.-top-16 { top: -4rem; }
.-top-20 { top: -5rem; }
.-bottom-20 { bottom: -5rem; }
.opacity-20 { opacity: 0.2; }
.z-\[75\] { z-index: 75; }
.z-\[85\] { z-index: 85; }
.z-\[95\] { z-index: 95; }

/* Grid Columns & Spanning */
.grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
.grid-cols-7 { grid-template-columns: repeat(7, minmax(0, 1fr)); }
.gap-3\.5 { gap: 0.875rem; }
.space-y-0\.5 > :not([hidden]) ~ :not([hidden]) { margin-top: 0.125rem; }
.space-x-1\.5 > :not([hidden]) ~ :not([hidden]) { margin-left: 0.375rem; }
.divide-slate-200 > :not([hidden]) ~ :not([hidden]) { border-color: #e2e8f0; }

/* Sizing & Spacing */
.h-20 { height: 5rem; }
.w-20 { width: 5rem; }
.h-24 { height: 6rem; }
.w-24 { width: 6rem; }
.w-28 { width: 7rem; }
.h-56 { height: 14rem; }
.w-56 { width: 14rem; }
.h-72 { height: 18rem; }
.w-72 { width: 18rem; }
.max-h-40 { max-height: 10rem; }
.max-h-52 { max-height: 13rem; }
.max-h-72 { max-height: 18rem; }
.max-h-80 { max-height: 20rem; }
.min-w-\[820px\] { min-width: 820px; }
.p-0\.5 { padding: 0.125rem; }
.p-7 { padding: 1.75rem; }
.py-0\.2 { padding-top: 0.05rem; padding-bottom: 0.05rem; }
.pb-3\.5 { padding-bottom: 0.875rem; }
.mb-0\.5 { margin-bottom: 0.125rem; }
.ml-1\.5 { margin-left: 0.375rem; }
.mt-3\.5 { margin-top: 0.875rem; }
.text-\[8px\] { font-size: 8px; line-height: 1.15; }

/* Backgrounds & Opacities */
.bg-\[#11141B\] { background-color: #11141B; }
.bg-amber-200 { background-color: #fde68a; }
.bg-amber-300 { background-color: #fcd34d; }
.bg-emerald-200 { background-color: #a7f3d0; }
.bg-emerald-50\/40 { background-color: rgb(236 253 245 / 0.4); }
.bg-emerald-700 { background-color: #047857; }
.hover\:bg-emerald-100:hover { background-color: #d1fae5; }
.hover\:bg-emerald-200:hover { background-color: #a7f3d0; }
.hover\:bg-emerald-800:hover { background-color: #065f46; }
.bg-indigo-200 { background-color: #c7d2fe; }
.bg-indigo-300 { background-color: #a5b4fc; }
.hover\:bg-indigo-100:hover { background-color: #e0e7ff; }
.bg-rose-200 { background-color: #fecdd3; }
.bg-rose-400 { background-color: #fb7185; }
.bg-rose-50\/35 { background-color: rgb(255 241 242 / 0.35); }
.bg-rose-50\/60 { background-color: rgb(255 241 242 / 0.6); }
.bg-rose-500\/20 { background-color: rgb(244 63 94 / 0.2); }
.bg-rose-500\/25 { background-color: rgb(244 63 94 / 0.25); }
.bg-slate-300 { background-color: #cbd5e1; }
.bg-slate-50\/60 { background-color: rgb(248 250 252 / 0.6); }
.bg-slate-50\/80 { background-color: rgb(248 250 252 / 0.8); }
.bg-slate-950\/65 { background-color: rgb(2 6 23 / 0.65); }
.bg-slate-950\/75 { background-color: rgb(2 6 23 / 0.75); }
.hover\:bg-slate-900:hover { background-color: #0f172a; }
.bg-teal-100 { background-color: #ccfbf1; }
.bg-black\/20 { background-color: rgb(0 0 0 / 0.2); }
.bg-black\/25 { background-color: rgb(0 0 0 / 0.25); }
.bg-black\/30 { background-color: rgb(0 0 0 / 0.3); }
.bg-white\/15 { background-color: rgb(255 255 255 / 0.15); }
.bg-white\/20 { background-color: rgb(255 255 255 / 0.2); }
.bg-white\/70 { background-color: rgb(255 255 255 / 0.7); }
.hover\:bg-white\/20:hover { background-color: rgb(255 255 255 / 0.2); }
.bg-current { background-color: currentColor; }

/* Text Colors */
.text-indigo-900 { color: #312e81; }
.text-indigo-950 { color: #1e1b4b; }
.text-rose-300 { color: #fda4af; }
.text-teal-800 { color: #115e59; }
.text-teal-900 { color: #134e4a; }
.text-white\/80 { color: rgb(255 255 255 / 0.8); }
.text-white\/90 { color: rgb(255 255 255 / 0.9); }

/* Borders */
.border-2 { border-width: 2px; }
.border-l-4 { border-left-width: 4px; }
.border-l-rose-500 { border-left-color: #f43f5e; }
.border-l-amber-500 { border-left-color: #f59e0b; }
.border-amber-100 { border-color: #fef3c7; }
.border-amber-200\/60 { border-color: rgb(253 230 138 / 0.6); }
.border-amber-200\/80 { border-color: rgb(253 230 138 / 0.8); }
.border-amber-300\/90 { border-color: rgb(252 211 77 / 0.9); }
.border-black\/10 { border-color: rgb(0 0 0 / 0.1); }
.border-emerald-400 { border-color: #34d399; }
.border-emerald-600 { border-color: #059669; }
.border-indigo-500 { border-color: #6366f1; }
.border-indigo-600 { border-color: #4f46e5; }
.border-rose-200\/60 { border-color: rgb(254 205 211 / 0.6); }
.border-rose-300 { border-color: #fda4af; }
.border-rose-400\/40 { border-color: rgb(251 113 133 / 0.4); }
.border-slate-200\/70 { border-color: rgb(226 232 240 / 0.7); }
.border-slate-200\/80 { border-color: rgb(226 232 240 / 0.8); }
.border-slate-200\/90 { border-color: rgb(226 232 240 / 0.9); }
.border-slate-900 { border-color: #0f172a; }
.border-white\/20 { border-color: rgb(255 255 255 / 0.2); }
.border-white\/25 { border-color: rgb(255 255 255 / 0.25); }
.border-white\/30 { border-color: rgb(255 255 255 / 0.3); }
.focus\:border-white:focus { border-color: #ffffff; }
.last\:border-0:last-child { border-width: 0px; }

/* Gradients */
.bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-stops)); }
.from-\[#11141C\] { --tw-gradient-from: #11141C; --tw-gradient-to: rgb(17 20 28 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[#171B26\] { --tw-gradient-to: rgb(23 27 38 / 0); --tw-gradient-stops: var(--tw-gradient-from), #171B26, var(--tw-gradient-to); }
.to-\[#0D1117\] { --tw-gradient-to: #0D1117; }
.from-\[#17191F\] { --tw-gradient-from: #17191F; --tw-gradient-to: rgb(23 25 31 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[#1F2430\] { --tw-gradient-to: rgb(31 36 48 / 0); --tw-gradient-stops: var(--tw-gradient-from), #1F2430, var(--tw-gradient-to); }
.to-\[#17191F\] { --tw-gradient-to: #17191F; }
.from-emerald-500 { --tw-gradient-from: #10b981; --tw-gradient-to: rgb(16 185 129 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.to-teal-600 { --tw-gradient-to: #0d9488; }
.from-blue-500 { --tw-gradient-from: #3b82f6; --tw-gradient-to: rgb(59 130 246 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.to-indigo-600 { --tw-gradient-to: #4f46e5; }
.to-orange-600 { --tw-gradient-to: #ea580c; }
.from-purple-500 { --tw-gradient-from: #a855f7; --tw-gradient-to: rgb(168 85 247 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.from-emerald-50\/90 { --tw-gradient-from: rgb(236 253 245 / 0.9); --tw-gradient-to: rgb(236 253 245 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-teal-50\/60 { --tw-gradient-to: rgb(240 253 250 / 0); --tw-gradient-stops: var(--tw-gradient-from), rgb(240 253 250 / 0.6), var(--tw-gradient-to); }
.from-indigo-50\/80 { --tw-gradient-from: rgb(238 242 255 / 0.8); --tw-gradient-to: rgb(238 242 255 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-slate-50 { --tw-gradient-to: rgb(248 250 252 / 0); --tw-gradient-stops: var(--tw-gradient-from), #f8fafc, var(--tw-gradient-to); }
.to-white { --tw-gradient-to: #ffffff; }

/* File Inputs */
.file\:mr-2::file-selector-button { margin-right: 0.5rem; }
.file\:mr-3::file-selector-button { margin-right: 0.75rem; }
.file\:py-1::file-selector-button { padding-top: 0.25rem; padding-bottom: 0.25rem; }
.file\:py-1\.5::file-selector-button { padding-top: 0.375rem; padding-bottom: 0.375rem; }
.file\:px-2\.5::file-selector-button { padding-left: 0.625rem; padding-right: 0.625rem; }
.file\:px-3::file-selector-button { padding-left: 0.75rem; padding-right: 0.75rem; }
.file\:rounded-lg::file-selector-button { border-radius: 0.5rem; }
.file\:border-0::file-selector-button { border-width: 0px; }
.file\:text-xs::file-selector-button { font-size: 0.75rem; line-height: 1rem; }
.file\:text-\[11px\]::file-selector-button { font-size: 11px; }
.file\:font-bold::file-selector-button { font-weight: 700; }
.file\:bg-amber-100::file-selector-button { background-color: #fef3c7; }
.file\:text-amber-900::file-selector-button { color: #78350f; }
.file\:bg-\[#16181D\]::file-selector-button { background-color: #16181D; }
.file\:text-\[#FCDF02\]::file-selector-button { color: #FCDF02; }

/* Responsive Breakpoints */
@media (min-width: 640px) {
    .sm\:grid-cols-12 { grid-template-columns: repeat(12, minmax(0, 1fr)); }
    .sm\:col-span-1 { grid-column: span 1 / span 1; }
    .sm\:col-span-3 { grid-column: span 3 / span 3; }
    .sm\:h-20 { height: 5rem; }
    .sm\:w-44 { width: 11rem; }
    .sm\:p-2 { padding: 0.5rem; }
}
@media (min-width: 768px) {
    .md\:flex { display: flex; }
}
@media (min-width: 1024px) {
    .lg\:col-span-3 { grid-column: span 3 / span 3; }
    .lg\:col-span-6 { grid-column: span 6 / span 6; }
    .lg\:items-center { align-items: center; }
    .lg\:divide-x > :not([hidden]) ~ :not([hidden]) { border-left-width: 1px; border-top-width: 0px; }
    .lg\:divide-y-0 > :not([hidden]) ~ :not([hidden]) { border-top-width: 0px; }
    .lg\:grid-cols-\[minmax\(0\,1\.25fr\)_minmax\(270px\,1fr\)\] { grid-template-columns: minmax(0, 1.25fr) minmax(270px, 1fr); }
    .lg\:grid-cols-\[minmax\(0\,1\.55fr\)_minmax\(280px\,1fr\)\] { grid-template-columns: minmax(0, 1.55fr) minmax(280px, 1fr); }
    .lg\:grid-cols-\[minmax\(0\,1\.5fr\)_minmax\(280px\,1fr\)\] { grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr); }
    .lg\:grid-cols-\[minmax\(0\,1\.6fr\)_minmax\(260px\,1fr\)\] { grid-template-columns: minmax(0, 1.6fr) minmax(260px, 1fr); }
    .lg\:grid-cols-\[minmax\(0\,1\.6fr\)_minmax\(280px\,1fr\)\] { grid-template-columns: minmax(0, 1.6fr) minmax(280px, 1fr); }
    .lg\:grid-cols-\[minmax\(0\,1fr\)_minmax\(0\,1\.35fr\)\] { grid-template-columns: minmax(0, 1fr) minmax(0, 1.35fr); }
}
@media (min-width: 1280px) {
    .xl\:grid-cols-4 { grid-template-columns: repeat(4, minmax(0, 1fr)); }
    .xl\:items-center { align-items: center; }
    .xl\:justify-between { justify-content: space-between; }
    .xl\:grid-cols-\[minmax\(0\,1\.35fr\)_minmax\(340px\,1fr\)\] { grid-template-columns: minmax(0, 1.35fr) minmax(340px, 1fr); }
    .xl\:grid-cols-\[minmax\(0\,1\.5fr\)_minmax\(280px\,1fr\)\] { grid-template-columns: minmax(0, 1.5fr) minmax(280px, 1fr); }
    .xl\:grid-cols-\[minmax\(0\,1\.65fr\)_minmax\(300px\,1fr\)\] { grid-template-columns: minmax(0, 1.65fr) minmax(300px, 1fr); }
    .xl\:grid-cols-\[minmax\(0\,1\.7fr\)_minmax\(300px\,1fr\)\] { grid-template-columns: minmax(0, 1.7fr) minmax(300px, 1fr); }
}


/* Phase 10 Landing Page & Modal Utilities */
.bg-\[#090D16\] { background-color: #090D16; }
.bg-teal-400 { background-color: #2dd4bf; }
.bg-indigo-400 { background-color: #818cf8; }
.text-teal-300 { color: #5eead4; }
.text-teal-400 { color: #2dd4bf; }
.text-teal-500 { color: #14b8a6; }
.text-indigo-300 { color: #a5b4fc; }
.border-white\/5 { border-color: rgb(255 255 255 / 0.05); }
.focus\:border-teal-400:focus { border-color: #2dd4bf; }
.hover\:border-teal-400\/40:hover { border-color: rgb(45 212 191 / 0.4); }
.hover\:bg-white\/5:hover { background-color: rgb(255 255 255 / 0.05); }
.hover\:brightness-110:hover { filter: brightness(1.1); }
.leading-\[1\.12\] { line-height: 1.12; }
.italic { font-style: italic; }
.pt-10 { padding-top: 2.5rem; }
.pt-14 { padding-top: 3.5rem; }
.pb-16 { padding-bottom: 4rem; }
.mt-16 { margin-top: 4rem; }
.space-y-7 > :not([hidden]) ~ :not([hidden]) { margin-top: 1.75rem; }
@media (min-width: 640px) {
    .sm\:text-5xl { font-size: 3rem; line-height: 1.08; }
}
@media (min-width: 1024px) {
    .lg\:inline-flex { display: inline-flex; }
    .lg\:gap-12 { gap: 3rem; }
    .lg\:pt-14 { padding-top: 3.5rem; }
    .lg\:pb-24 { padding-bottom: 6rem; }
}

</style>
