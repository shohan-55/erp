@props(['firm' => null])

{{-- The ~120 KB Tailwind utility bundle lives in public/css/brand-theme.css so
     browsers cache it; only the per-firm colour variables stay inline. --}}
@php
    $brandCssPath = public_path('css/brand-theme.css');
    $brandCssVersion = is_file($brandCssPath) ? filemtime($brandCssPath) : 1;
@endphp
<link rel="stylesheet" href="{{ asset('css/brand-theme.css') }}?v={{ $brandCssVersion }}" data-erp-brand-bundle>


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
.bg-\[\#11141B\] { background-color: #11141B; }
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
.from-\[\#11141C\] { --tw-gradient-from: #11141C; --tw-gradient-to: rgb(17 20 28 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[\#171B26\] { --tw-gradient-to: rgb(23 27 38 / 0); --tw-gradient-stops: var(--tw-gradient-from), #171B26, var(--tw-gradient-to); }
.to-\[\#0D1117\] { --tw-gradient-to: #0D1117; }
.from-\[\#17191F\] { --tw-gradient-from: #17191F; --tw-gradient-to: rgb(23 25 31 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[\#1F2430\] { --tw-gradient-to: rgb(31 36 48 / 0); --tw-gradient-stops: var(--tw-gradient-from), #1F2430, var(--tw-gradient-to); }
.to-\[\#17191F\] { --tw-gradient-to: #17191F; }
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
.file\:bg-\[\#16181D\]::file-selector-button { background-color: #16181D; }
.file\:text-\[\#FCDF02\]::file-selector-button { color: #FCDF02; }

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
.bg-\[\#090D16\] { background-color: #090D16; }
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


/* =====================================================================
   Global Interactive Button Feedback, Harmonic Wave Animation, & Top Progress Bar Engine
   ===================================================================== */
@keyframes dw-wave-equalizer {
    0%, 100% { transform: scaleY(0.38); opacity: 0.55; }
    50%      { transform: scaleY(1.08); opacity: 1; }
}
@keyframes dw-shimmer-glide {
    0%   { transform: translateX(-130%) skewX(-22deg); }
    100% { transform: translateX(230%) skewX(-22deg); }
}
@keyframes dw-busy-aura {
    0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--brand-primary, #0D9488) 42%, transparent), 0 4px 14px -3px rgba(15, 23, 42, 0.18); }
    50%      { box-shadow: 0 0 0 5px color-mix(in srgb, var(--brand-primary, #0D9488) 0%, transparent), 0 6px 18px -2px rgba(15, 23, 42, 0.26); }
}
@keyframes dw-progress-indeterminate {
    0%   { transform: translateX(-100%) scaleX(0.25); }
    50%  { transform: translateX(0%) scaleX(0.6); }
    100% { transform: translateX(100%) scaleX(0.25); }
}

/* Tactile Press Feedback on every interactive button */
button:not(:disabled),
[role="button"]:not(:disabled) {
    transition: transform 0.14s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.18s ease, background-color 0.15s ease, opacity 0.15s ease;
}
button:not(:disabled):active,
[role="button"]:not(:disabled):active {
    transform: scale(0.965) !important;
}

/* Action-Locked Busy State with Glassmorphic Shimmer Sweep & Luminous Aura */
button.dw-btn-busy,
a.dw-btn-busy {
    position: relative !important;
    overflow: hidden !important;
    pointer-events: none !important;
    cursor: wait !important;
    transform: translateY(-0.5px) !important;
    animation: dw-busy-aura 1.25s cubic-bezier(0.4, 0, 0.2, 1) infinite !important;
}
button.dw-btn-busy::after,
a.dw-btn-busy::after {
    content: "" !important;
    position: absolute !important;
    inset: 0 !important;
    width: 55% !important;
    height: 100% !important;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.38), transparent) !important;
    animation: dw-shimmer-glide 1.05s cubic-bezier(0.4, 0, 0.2, 1) infinite !important;
    pointer-events: none !important;
}

/* 3-Capsule Harmonic Wave Loader (Replaces Old Rotating Circle Spinner) */
button.dw-btn-busy > .dw-auto-spinner {
    display: inline-flex !important;
}
:where(.dw-inline-spinner) {
    display: inline-flex;
}
button.dw-btn-busy > .dw-auto-spinner,
.dw-inline-spinner {
    align-items: center;
    justify-content: center;
    gap: 2.5px;
    width: 16px;
    height: 14px;
    min-width: 16px;
    min-height: 14px;
    margin-right: 0.48rem;
    vertical-align: -0.15em;
    flex-shrink: 0;
    position: relative;
}
button.dw-btn-busy > .dw-auto-spinner > .dw-wave-bar {
    display: block;
    width: 3px;
    height: 13px;
    border-radius: 9999px;
    background-color: currentColor;
    transform-origin: center center;
    animation: dw-wave-equalizer 0.78s ease-in-out infinite;
}
button.dw-btn-busy > .dw-auto-spinner > .dw-wave-bar:nth-child(2) {
    animation-delay: 0.15s;
}
button.dw-btn-busy > .dw-auto-spinner > .dw-wave-bar:nth-child(3) {
    animation-delay: 0.30s;
}
/* Fallback harmonic wave bars for standalone .dw-inline-spinner spans */
.dw-inline-spinner::before,
.dw-inline-spinner::after {
    content: "";
    display: block;
    width: 3.5px;
    height: 13px;
    border-radius: 9999px;
    background-color: currentColor;
    animation: dw-wave-equalizer 0.78s ease-in-out infinite;
}
.dw-inline-spinner::after {
    animation-delay: 0.22s;
}
[wire\:loading],
[wire\:loading\.delay],
[wire\:loading\.inline-block],
[wire\:loading\.inline],
[wire\:loading\.block],
[wire\:loading\.flex],
[wire\:loading\.table],
[wire\:loading\.grid],
[wire\:loading\.inline-flex] {
    display: none;
}
.dw-inline-spinner[x-cloak],
.dw-inline-spinner[style*="display: none"],
.dw-inline-spinner[style*="display:none"],
[wire\:loading][style*="display: none"],
[wire\:loading][style*="display:none"] {
    display: none !important;
}

/* Hide existing leading icon inside busy button when auto-spinner is injected */
button.dw-btn-busy.dw-has-auto-spinner > svg:first-of-type,
button.dw-btn-busy.dw-has-auto-spinner > i[data-lucide]:first-of-type {
    display: none !important;
}

/* Top Global Progress Bar */
#dw-top-progress-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    z-index: 99999;
    pointer-events: none;
    overflow: hidden;
    background: rgba(15, 23, 42, 0.08);
    opacity: 0;
    transition: opacity 0.18s ease;
}
#dw-top-progress-bar.dw-active {
    opacity: 1;
}
#dw-top-progress-bar::after {
    content: "";
    display: block;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, var(--brand-primary, #0D9488), #FCDF02, var(--brand-accent, #14B8A6));
    box-shadow: 0 0 10px var(--brand-primary, #0D9488);
    transform-origin: left center;
    animation: dw-progress-indeterminate 1.05s cubic-bezier(0.4, 0, 0.2, 1) infinite;
}

/* Global Offline Alert Banner */
#dw-offline-banner {
    position: fixed;
    bottom: 16px;
    left: 50%;
    transform: translateX(-50%) translateY(220%);
    opacity: 0;
    visibility: hidden;
    z-index: 99998;
    background: #17191F;
    color: #FFFFFF;
    border: 2px solid #F43F5E;
    border-radius: 9999px;
    padding: 8px 18px;
    font-size: 12px;
    font-weight: 800;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
    display: flex;
    align-items: center;
    gap: 8px;
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease, visibility 0.2s ease;
    pointer-events: none;
}
#dw-offline-banner.dw-visible {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
    visibility: visible;
}

/* Complete Pre-Compiled Utility Supplement (Zero Missing Classes) */
.bg-\[\#090D16\] { background-color: #090D16; }
.bg-\[\#11141B\] { background-color: #11141B; }
.bg-\[\#11141C\] { background-color: #11141C; }
.bg-amber-400\/15 { background-color: rgb(251 191 36 / 0.15); }
.bg-black\/10 { background-color: rgb(0 0 0 / 0.1); }
.bg-emerald-50\/70 { background-color: rgb(236 253 245 / 0.7); }
.bg-emerald-500\/20 { background-color: rgb(16 185 129 / 0.2); }
.bg-emerald-500\/25 { background-color: rgb(16 185 129 / 0.25); }
.bg-indigo-50\/40 { background-color: rgb(238 242 255 / 0.4); }
.bg-indigo-50\/60 { background-color: rgb(238 242 255 / 0.6); }
.bg-indigo-50\/70 { background-color: rgb(238 242 255 / 0.7); }
.bg-indigo-500\/25 { background-color: rgb(99 102 241 / 0.25); }
.bg-rose-500\/10 { background-color: rgb(244 63 94 / 0.1); }
.bg-teal-50\/50 { background-color: rgb(240 253 250 / 0.5); }
.bg-teal-600 { background-color: #0d9488; }
.bg-teal-700 { background-color: #0f766e; }
.hover\:bg-teal-700:hover { background-color: #0f766e; }
.border-amber-300\/40 { border-color: rgb(252 211 77 / 0.4); }
.border-emerald-300 { border-color: #6ee7b7; }
.border-emerald-400\/40 { border-color: rgb(52 211 153 / 0.4); }
.border-indigo-300 { border-color: #a5b4fc; }
.border-indigo-400\/40 { border-color: rgb(129 140 248 / 0.4); }
.border-rose-400 { border-color: #fb7185; }
.border-rose-400\/30 { border-color: rgb(251 113 133 / 0.3); }
.border-teal-200 { border-color: #99f6e4; }
.border-teal-300 { border-color: #5eead4; }
.border-teal-400 { border-color: #2dd4bf; }
.border-t-2 { border-top-width: 2px; }
.focus\:border-indigo-400:focus { border-color: #818cf8; }
.focus\:ring-teal-400:focus { --tw-ring-color: #2dd4bf; }
.hover\:bg-emerald-500\/30:hover { background-color: rgb(16 185 129 / 0.3); }
.hover\:bg-white\/15:hover { background-color: rgb(255 255 255 / 0.15); }
.hover\:border-amber-200:hover { border-color: #fde68a; }
.hover\:text-emerald-950:hover { color: #022c22; }
.max-h-28 { max-height: 7rem; }
.ml-0\.5 { margin-left: 0.125rem; }
.mx-6 { margin-left: 1.5rem; margin-right: 1.5rem; }
.pb-5 { padding-bottom: 1.25rem; }
.pl-4 { padding-left: 1rem; }
.pt-2\.5 { padding-top: 0.625rem; }
.ring-rose-200 { --tw-ring-color: #fecdd3; }
.ring-emerald-200 { --tw-ring-color: #a7f3d0; }
.text-amber-100 { color: #fef3c7; }
.text-indigo-200 { color: #c7d2fe; }
.text-teal-600 { color: #0d9488; }
.text-teal-950 { color: #042f2e; }
@media (width >= 40rem) {
    .sm\:grid-cols-5 { grid-template-columns: repeat(5, minmax(0, 1fr)); }
}
.bg-gradient-to-r { background-image: linear-gradient(to right, var(--tw-gradient-stops)); }
.bg-gradient-to-br { background-image: linear-gradient(to bottom right, var(--tw-gradient-stops)); }
.from-\[\#11141C\] { --tw-gradient-from: #11141C; --tw-gradient-to: rgb(17 20 28 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[\#171B26\] { --tw-gradient-via: #171B26; --tw-gradient-to: rgb(23 27 38 / 0); --tw-gradient-stops: var(--tw-gradient-from), #171B26, var(--tw-gradient-to); }
.to-\[\#0D1117\] { --tw-gradient-to: #0D1117; }
.to-\[\#11141C\] { --tw-gradient-to: #11141C; }
.from-\[\#17191F\] { --tw-gradient-from: #17191F; --tw-gradient-to: rgb(23 25 31 / 0); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.via-\[\#1F2430\] { --tw-gradient-via: #1F2430; --tw-gradient-to: rgb(31 36 48 / 0); --tw-gradient-stops: var(--tw-gradient-from), #1F2430, var(--tw-gradient-to); }
.to-\[\#17191F\] { --tw-gradient-to: #17191F; }
.file\:bg-\[\#16181D\]::file-selector-button { background-color: #16181D; }
.file\:text-\[\#FCDF02\]::file-selector-button { color: #FCDF02; }


.bg-amber-50\/90 { background-color: rgb(255 251 235 / 0.9); }
.hover\:bg-\[\#FCDF02\]\/25:hover { background-color: rgb(252 223 2 / 0.25); }
.min-h-\[68px\] { min-height: 68px; }
@media (width >= 40rem) {
    .sm\:flex-wrap { flex-wrap: wrap; }
    .sm\:h-10 { height: 2.5rem; }
    .sm\:w-10 { width: 2.5rem; }
    .sm\:mb-3 { margin-bottom: 0.75rem; }
    .sm\:min-h-\[76px\] { min-height: 76px; }
    .sm\:px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
}
@media (width >= 64rem) {
    .lg\:text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
}

</style>

<script data-dw-global-feedback>
(function () {
    if (window.__dwFeedbackBound) return;
    window.__dwFeedbackBound = true;

    function ensureProgressElements() {
        if (!document.getElementById('dw-top-progress-bar')) {
            var bar = document.createElement('div');
            bar.id = 'dw-top-progress-bar';
            bar.setAttribute('aria-hidden', 'true');
            document.body.appendChild(bar);
        }
        if (!document.getElementById('dw-offline-banner')) {
            var off = document.createElement('div');
            off.id = 'dw-offline-banner';
            off.setAttribute('role', 'status');
            off.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:#F43F5E;"></span><span>Internet connection offline — please check your network connection</span>';
            document.body.appendChild(off);
        }
    }

    var activeTimers = new WeakMap();
    var barSafetyTimer = null;
    var inFlightRequests = 0;

    function startTopBar() {
        ensureProgressElements();
        var bar = document.getElementById('dw-top-progress-bar');
        if (bar) bar.classList.add('dw-active');
        clearTimeout(barSafetyTimer);
        barSafetyTimer = setTimeout(stopTopBar, 20000);
    }

    function stopTopBar() {
        if (inFlightRequests > 0) return;
        clearTimeout(barSafetyTimer);
        var bar = document.getElementById('dw-top-progress-bar');
        if (bar) bar.classList.remove('dw-active');
    }

    function markButtonBusy(btn, customTimeoutMs, isGps) {
        if (!btn || btn.disabled || btn.classList.contains('dw-btn-busy')) return;
        if (btn.hasAttribute('data-no-loading')) return;

        btn.classList.add('dw-btn-busy');
        if (isGps) {
            btn.setAttribute('data-dw-gps-pending', '1');
        }

        // Inject 3-capsule harmonic wave animation unless the button already has a visible .dw-inline-spinner
        var hasOwnSpinner = btn.querySelector('.dw-inline-spinner');
        if (!hasOwnSpinner) {
            var sp = document.createElement('span');
            sp.className = 'dw-auto-spinner';
            sp.setAttribute('aria-hidden', 'true');
            sp.innerHTML = '<span class="dw-wave-bar"></span><span class="dw-wave-bar"></span><span class="dw-wave-bar"></span>';
            btn.insertBefore(sp, btn.firstChild);
            btn.classList.add('dw-has-auto-spinner');
        }

        startTopBar();

        var prev = activeTimers.get(btn);
        if (prev) clearTimeout(prev);
        var t = setTimeout(function () {
            btn.removeAttribute('data-dw-gps-pending');
            clearButtonBusy(btn);
        }, customTimeoutMs || 20000);
        activeTimers.set(btn, t);
    }

    function clearButtonBusy(btn) {
        if (!btn) return;
        if (btn.getAttribute('data-dw-gps-pending') === '1' || inFlightRequests > 0) {
            return;
        }
        btn.classList.remove('dw-btn-busy', 'dw-has-auto-spinner');
        var sp = btn.querySelector(':scope > .dw-auto-spinner');
        if (sp) sp.remove();
        var prev = activeTimers.get(btn);
        if (prev) clearTimeout(prev);
    }

    function clearAllBusyButtonsIfIdle() {
        if (inFlightRequests > 0) return;
        document.querySelectorAll('.dw-btn-busy').forEach(function (btn) {
            if (btn.getAttribute('data-dw-gps-pending') !== '1') {
                clearButtonBusy(btn);
            }
        });
        if (!document.querySelector('.dw-btn-busy')) {
            stopTopBar();
        }
    }

    // 1. Instant capture on any click on a wire:click button or @click attendance button
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('button, a[wire\\:click]');
        if (!btn || btn.disabled) return;

        var attrs = btn.attributes;
        var isLivewireOrAction = false;
        var isGpsAction = false;
        for (var i = 0; i < attrs.length; i++) {
            var name = attrs[i].name;
            var val = attrs[i].value || '';
            if (name.indexOf('wire:click') === 0) {
                isLivewireOrAction = true;
            }
            if ((name.indexOf('@click') === 0 || name.indexOf('x-on:click') === 0) &&
                (val.indexOf('Gps') !== -1 || val.indexOf('$wire.') !== -1)) {
                isLivewireOrAction = true;
                isGpsAction = val.indexOf('Gps') !== -1;
            }
        }

        if (isLivewireOrAction) {
            markButtonBusy(btn, 20000, isGpsAction);
        }
    }, true);

    // 2. Instant capture on form submit (wire:submit / wire:submit.prevent)
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM') return;
        var submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
        if (submitBtn) {
            markButtonBusy(submitBtn, 20000, false);
        } else {
            startTopBar();
        }
    }, true);

    // 3. Hook into Livewire request lifecycle so animation stays active until action + DOM morph 100% finishes
    document.addEventListener('livewire:init', function () {
        if (!window.Livewire) return;

        if (!window.__dwToastListenerBound) {
            window.__dwToastListenerBound = true;
            Livewire.on('toast', function (data) {
                var payload = Array.isArray(data) ? data[0] : data;
                if (window.showToast && payload) {
                    window.showToast(payload.title || 'Notification', payload.message || '', payload.type || 'success');
                }
            });
        }

        if (typeof Livewire.hook === 'function') {
            Livewire.hook('request', function (_ref) {
                inFlightRequests++;
                // Once the Livewire request starts, any pending GPS acquisition has handed off to the server call
                document.querySelectorAll('[data-dw-gps-pending="1"]').forEach(function (b) {
                    b.removeAttribute('data-dw-gps-pending');
                });
                startTopBar();

                var finalizeRequest = function () {
                    inFlightRequests = Math.max(0, inFlightRequests - 1);
                    // Wait for DOM morph & next animation frame so button animation stays until UI update is complete
                    requestAnimationFrame(function () {
                        setTimeout(clearAllBusyButtonsIfIdle, 90);
                    });
                };

                _ref.succeed(function () {
                    finalizeRequest();
                });
                _ref.fail(function () {
                    inFlightRequests = Math.max(0, inFlightRequests - 1);
                    document.querySelectorAll('[data-dw-gps-pending="1"]').forEach(function (b) {
                        b.removeAttribute('data-dw-gps-pending');
                    });
                    clearAllBusyButtonsIfIdle();
                });
            });
        }
    });

    // 4. Online / Offline status indicator
    window.addEventListener('offline', function () {
        ensureProgressElements();
        var off = document.getElementById('dw-offline-banner');
        if (off) off.classList.add('dw-visible');
    });
    window.addEventListener('online', function () {
        var off = document.getElementById('dw-offline-banner');
        if (off) off.classList.remove('dw-visible');
    });

    // 5. Automatic Client-Side Image Compression for Receipts & Avatars
    // Compresses large mobile camera photos (5-10 MB) down to ~180-300 KB JPEG
    // before Livewire uploads them, making mobile data uploads 10x faster.
    document.addEventListener('change', function (e) {
        var input = e.target;
        if (!input || input.tagName !== 'INPUT' || input.type !== 'file') return;
        if (input.__dwCompressedPass) {
            input.__dwCompressedPass = false;
            return;
        }
        var file = input.files && input.files[0];
        if (!file) return;

        // Only compress raster images larger than 300 KB (skip PDFs and tiny icons)
        if (!/^image\/(jpeg|jpg|png|webp)$/i.test(file.type) || file.size <= 300 * 1024) {
            return;
        }

        // Prevent Livewire's listener from uploading the raw 8 MB image first
        e.stopImmediatePropagation();

        var isAvatar = (input.getAttribute('wire:model') || '').indexOf('Avatar') !== -1;
        var maxDim = isAvatar ? 600 : 1600;
        var quality = isAvatar ? 0.82 : 0.78;
        var origSizeKB = Math.round(file.size / 1024);

        startTopBar();

        var reader = new FileReader();
        reader.onload = function (ev) {
            var img = new Image();
            img.onload = function () {
                var w = img.width;
                var h = img.height;
                if (w > maxDim || h > maxDim) {
                    if (w > h) {
                        h = Math.round((h * maxDim) / w);
                        w = maxDim;
                    } else {
                        w = Math.round((w * maxDim) / h);
                        h = maxDim;
                    }
                }
                var canvas = document.createElement('canvas');
                canvas.width = w;
                canvas.height = h;
                var ctx = canvas.getContext('2d');
                ctx.fillStyle = '#FFFFFF';
                ctx.fillRect(0, 0, w, h);
                ctx.drawImage(img, 0, 0, w, h);

                canvas.toBlob(function (blob) {
                    stopTopBar();
                    if (!blob || blob.size >= file.size) {
                        input.__dwCompressedPass = true;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        return;
                    }
                    var newName = file.name.replace(/\.[^.]+$/, '') + '.jpg';
                    var compressedFile = new File([blob], newName, {
                        type: 'image/jpeg',
                        lastModified: Date.now()
                    });
                    var dt = new DataTransfer();
                    dt.items.add(compressedFile);
                    input.files = dt.files;
                    input.__dwCompressedPass = true;

                    var newSizeKB = Math.round(compressedFile.size / 1024);
                    if (window.showToast) {
                        window.showToast(
                            'Image Optimized for Fast Upload',
                            'Compressed from ' + origSizeKB + ' KB to ' + newSizeKB + ' KB',
                            'info'
                        );
                    }
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }, 'image/jpeg', quality);
            };
            img.onerror = function () {
                stopTopBar();
                input.__dwCompressedPass = true;
                input.dispatchEvent(new Event('change', { bubbles: true }));
            };
            img.src = ev.target.result;
        };
        reader.readAsDataURL(file);
    }, true);



    // 6. PWA Service Worker & Native 1-Click Mobile App Installation Engine
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('/sw.js?v=3', { scope: '/' }).then(function (reg) {
                if (reg.update) reg.update();
            }).catch(function () {});
        });
    }

    window.__dwDeferredPwaPrompt = null;
    function isStandaloneApp() {
        return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
            || window.navigator.standalone === true;
    }

    function isMobileOrTabletDevice() {
        var ua = navigator.userAgent || '';
        var isTouchUa = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet/i.test(ua)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
        var isSmallScreen = window.matchMedia && window.matchMedia('(max-width: 1023px)').matches;
        return isTouchUa && isSmallScreen || isSmallScreen;
    }

    function syncPwaButtons() {
        var installed = isStandaloneApp();
        var showOnDevice = !installed && isMobileOrTabletDevice();
        document.querySelectorAll('[data-dw-pwa-install]').forEach(function (btn) {
            if (!showOnDevice) {
                btn.style.setProperty('display', 'none', 'important');
                btn.classList.add('hidden');
            } else {
                btn.classList.remove('hidden');
                btn.style.display = btn.getAttribute('data-dw-pwa-display') || 'inline-flex';
            }
        });
    }
    window.addEventListener('resize', syncPwaButtons);

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        window.__dwDeferredPwaPrompt = e;
        syncPwaButtons();
    });

    window.addEventListener('appinstalled', function () {
        window.__dwDeferredPwaPrompt = null;
        window.closeDwPwaModal && window.closeDwPwaModal();
        document.querySelectorAll('[data-dw-pwa-install]').forEach(function (btn) {
            btn.style.display = 'none';
        });
        if (window.showToast) {
            window.showToast('App Installed Successfully', 'DhakaWala ERP shortcut added to your phone screen!', 'success');
        }
    });

    document.addEventListener('livewire:navigated', syncPwaButtons);
    document.addEventListener('DOMContentLoaded', syncPwaButtons);

    window.closeDwPwaModal = function () {
        var m = document.getElementById('dw-pwa-install-modal');
        if (m) m.remove();
    };

    window.showDwPwaGuideModal = function () {
        window.closeDwPwaModal();
        var isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        var modal = document.createElement('div');
        modal.id = 'dw-pwa-install-modal';
        modal.style.cssText = 'position:fixed;inset:0;z-index:99999;background:rgba(2,6,23,0.72);backdrop-filter:blur(6px);display:flex;align-items:center;justify-content:center;padding:16px;font-family:Inter,system-ui,sans-serif;';
        modal.onclick = function (ev) { if (ev.target === modal) window.closeDwPwaModal(); };

        var stepsHtml = isIOS
            ? '<div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;padding:14px;margin-top:12px;font-size:12px;color:#1E293B;line-height:1.6;">'
              + '<div style="font-weight:800;color:#0F172A;margin-bottom:6px;">📱 For iPhone / iPad (Safari):</div>'
              + '<div><strong>Step 1:</strong> Tap the <strong>Share (⬆️)</strong> button at the bottom of Safari.</div>'
              + '<div style="margin-top:4px;"><strong>Step 2:</strong> Scroll down and tap <strong>"Add to Home Screen"</strong>, then tap <strong>Add</strong>.</div>'
              + '</div>'
            : '<div style="background:#F8FAFC;border:1px solid #E2E8F0;border-radius:14px;padding:14px;margin-top:12px;font-size:12px;color:#1E293B;line-height:1.6;">'
              + '<div style="font-weight:800;color:#0F172A;margin-bottom:6px;">📲 For Android Mobile (Redmi / Samsung / Chrome):</div>'
              + '<div style="margin-bottom:6px;"><strong>Option A (Native Install):</strong> Tap the <strong>3-dot menu (⋮)</strong> at top-right &rarr; Tap <strong>"Install app"</strong> (or "Install and create shortcut" &rarr; select <strong>Install</strong>).</div>'
              + '<div><strong>Option B (Direct Shortcut):</strong> If your phone model prefers shortcuts, tap <strong>"Create shortcut"</strong> &rarr; Add to Home screen.</div>'
              + '<div style="margin-top:8px;padding-top:8px;border-top:1px dashed #CBD5E1;font-size:11px;color:#475569;">💡 Once added, an official app icon appears on your mobile home screen. Tap it anytime to open the ERP directly in full screen!</div>'
              + '</div>';

        var inlineIconSrc = 'data:image/png;base64,' + 'iVBORw0KGgoAAAANSUhEUgAAAMAAAADACAYAAABS3GwHAAAz5ElEQVR42u29eZBl11Xm+1tr73PuvZlZpZKsWZYseRCeJSTLRpYside4aRp6omkGg8AGOujXxAu6oeERTdPR3UTQA49+/aBtJmMbsLGNB7ANniU0WdY8lmapJFVJNVdlVWbe4Zy991rvj3NlGwfGg6bMqvNFKJQVykqdPPf71rTXWltOOuVFTo8eRym0fwU9egH06NELoEePXgA9evQC6NHjaEFcDw/x5PU7+k/iKMRpbzz9eX8GeT7KoD3he6wXQTxnAuhJ32M9iuFZF0BP/B7rWQjPmgC+GeKvh1iwx/rO/Z4tjjwrAvh6v1hP+B7rhTfPqAB64vfYaDx6xgTwdz10T/we65VTz4gAvtaD9sTv8WwK4Zngl/bk77He8bW49ExUGJ+WB/jbHqAnfo/n2hs8Hc5pT/4eG90bPB1PoD35exzNItCe/D2OZhHos/EgPXo8n8nxsyaAvq+nx0ZMkp8RAfShT48jMRTSZ/J/3KPHRguFviEB9KFPjyM1FNLnWnE9eqwnLxCfKSU919i987H+U19nOPnUM9edF/h6wojPldJ6wh/5+OrP6LkWxGlvPP2bNthxo73UHhtPEOvNM3wl/s5muK9W03Np/Xvi9yHSM5UA/128XZeLsXryHx0h0npA7F9Qj6M5LNKe/D2O5s9b10P835O/F8Ezia/m6t9VGdLeEvQ4mj9/7cnf42gWQb8efcPBHcjAMr56tdnjuK9cDThQ+vezQQTQW/9vGRmI0N6N3H7prH1HK/LkpeD3AGGjiuD54oP25N9Y5h9Qdw7Cfs3ldy3olW72WYfdDdDMPUQfCvUh0BHJ/wIShOW74ZMXt/kaq8KOgdnHsXLbucAURAHr39U6FUBv/b9lFJAI5Xbkrkvb2e+m6E0UFLcnTMNWcQ7cN/9Mey/Qe4AjLvQJwApsO7nk95jPtsfStqSmJbhpKl9A2Hqhe7ph/r29F1hvAuit/7cMc3wGa3fCF07Jsys9SJKaIWKGkkRtO6X8lYs99hJgbe4ENqQneC550nuAjYEgLlPY+qbc/LF5WVbPAqUQNYFNUcvapusK4a4TYHZrlwt4XxbtBbAhI5655faxY2uAI/sfwK7Ilh4gZERKQSThWXHLiDZU0oSSr3R49NuA8UbPB3oBHLWQp4SwBmQYfwFu+Y7p2kdE2gX1Am4NZo55oJTIbLaKliSlvc/hlpPx5Vv6itA6EkAf/3/TCnDQkwSt4PGXl/LHRdJ2VS/EKJg7Kc+/VWpUK9p2FfeD0qZPF2TPpTj3g8tGDIWeK770HmD9hkFz0h68G649frrvFnRmktq9eA6URohaIxLJs1U0K6oD1FuxZpvDLYbsPdx5AelfZy+ADYWu5u/2BXjkgtn4A7nKa4HWoUS8jVRao66IF+raoThCRFhAylpMzUeAXecDd8w/5z4U6gWwMUz//N9jfMdZlA+HMr1bAwUlENkEahBaVBJYopBBpniegit1XMLahxy7Ipo9eew8pOrdQC+ADcH/DIRCcxt696mT5Y9lpq1qiZiNyGlCyWPclNwGLEcsFcwnBJlgZZXiU4QU0uyKorrzePAHOwH0ZdFeAOsbRjenvcO57+yS3lnq2UQljUjMUDWCgrtiWcEi4hWWAt46nkFNKRYQH1FWnxArVyzC4Qb84Fd5mB69ANZj+CMOu5+IfPqk6a6bXRq0io57g0pCPCIlIjghJKw0RK1QH2AFsAlaJpBnqE50uvzpAve9BucJkNB7gV4A6zr0wdsvwm0Xzna/J/maxSYXQsqEHFmbNLgbWIt6QmjRkDFrcQqlHKItu8jNPkqeoRUMbJ+0k/ea+SMjoJmfDfReoBfAugt9AvhO5LFXlpU/yX54VxwONhEHM2waod1EFRVPY8iZgFKalmbaUIrjOBoroi6CGUrBckGk0WbtelS3vgybfrGvCPUCWI/W30HcfeUJ+NSxzf5bpPZjJE8PkFODDVukKmgKUCoqHeGlwkog6JBBvZmgiygjLCVidFRBUw0sMKhbma18PKP3XtYdjiG9CHoBrBcUkICXL4rc9fq1fR8owSchS4MOjOBDcnJKaRCpaGaZNjVkaxEdEBmRxy02bQnZqNVwK+QmgzvtuCCNirYPOtzjpazuBhTvc4FeAOsi9HEBDuCPnJ/Hv19k/6NK65Qyw7LhRSFXuCnFC6PBiGJg4jhgbSKYYynTNolZ4xRTRA2jEOsKMyG3y7E5/AlHH34VcCcisfcCvQDWAUSdfY+jHx9Od92ArEXxnKlDxFvDWkOpkOKog6NUYUAg4jkRBgELQggRzIkEKhcoLaSCmKJSU9lQ5NADHuSqE5qyfdSFQe69AHo8n4mv4uwS7vy2svIBk8lOrYeCesFTIrhAKpRZN+vuVpitjClNRkIEFXJqCMG7AqdUSBhhIqRUMDIpz9AYQAWVoKu7P10qHj0F2NaVRY9uL9AL4HnMfIHW5Il97p9fHD9xvw9YFJ+HM2nWgMBgUAOG5RZLDSqCZ2gPzxADGVQUKaSc8GyU1OApMwgDLLV4MtIkk6xQFkzUd4iuXLPJpk+uufv+r3iWXgA9nsvEl4BPblZueM304CdLtIWQUjWP3yscpWQj55YqCuKGosSgiEMcjHCLeFtwE1QD9WCAiOAu5FzQEAkhoApBBJsqVVPp5MBHko5ueq3I7B7cBffcC6DHcxn6BOARZNvZ7fIH8V37NdoA0RlmiZKli/NVKSljxQgSCRIIoYJQ4TqEMCS30E4ywQNeDEcQVXJpKNlxDGOGmVJLhYYFjBTywU9amT1+OiKGHL2TY70AnvuwB3dWsN2bKJ8/YbL9Wo+zgVRuBEl4WSMGpxTHEgRxxGdYSWCOiBNjwJqMtUYIkRgrrDhWWshOO5lQKUQXpDjWHIJmBXRKW4+J9UCn++/woLe9GBtfezS3SPQCeG75nwFFJrej95443f2+FMJhDRWkVnAU0YiZUXLpxsLMEAdxoZk1pOmM0ma8FEpJqASCVhTLoCBEqjDADFxyF20VSOkAqVnF2gbKDA0rOt37yWL20CXAjm7n0NGXEPcCeE6tv1TA2HjwFXn59832PBAqH+Ks4SQUIcSIEwlagXfHBCUFSjaigptANqI6gyqQG8NaiKFCvGt/iFITCIhnLDeoLRCrRcgj6mmAVlEJkicPuY8/J6R9O8BXukajXgA9nnnuO1AcX4XZzcFuPHH85LVWzTZrWauZjNdI7Qy1iE0TXoy2KeBOrCJmFZYdFSEQKK1R2oQYWPLOAxQjpUzQQC7MPUlBSLgVSEPISqClrg3aIYMS49quzxvy0BvxtQe6w7GjKxTqBfDcVX2i4Q/AA5eNH/rjVDcWpYJgmVBG+CyQ1gRJUCYNUiBooGRHEEQilruzq6qOiEOataiDZQcTsIAXh9LSTlpy6whOjJlgC3hRGgqlTQRXnIzKRKc7P1TIk5e6+WN0B2TWC6DHM5r4AlNlT2zX/tjzvvuCJcVDorQtA5Qo4DkTFAYxMhrUNFOnnRkhCBoqcoHihrmAVohW87KnIRqpQo0guGViiMQ4wErGc4OYoEGo4hKpqTGb4UCUkaTJbeIrdx8rZmMQxY+eilAvgGef//OlttObhOvPnT76SQthpFIrNm6JdaQYSE7EGtoCOXcHYZYCQQcUa+Y/S3AxUm5pmkLQ2J2mNQ25zeTWKSmjIt0AcDbUBcvCdLaHYgfJeT/ZlyluCDVKYUByKdd4bvccBNLRlAz3Anguqj7kL8LDl67tenfy1eUQCZTZCiogMeK5y49La1gW3AJpZkguCIJS044bSgYkEoISNUAWPDlVUCpRxAXxbkMEOB6cajgkihDjFJhheZWgawwrg3SAZu1xPE9DM76pYDdebM2h6xGp5s/eC6DH0w19RLEdp9C+z9P+u0JINSU1hJwpQSjZkChIrAg6IIrgBaoQKTnhTUueZkoyYlA8CaWB4IKlbg44tS0ihmuBGKAIoXRNc5NpS/JEUMGy4y3z7tHDmB0ixoJZIk13xGbnX5jlRy7GrdsqdxScEPcCePb4byDRmV6F3n3m2vZPWTy8olVw3AUICNCmzCxlmpQhBFQjWCEMAoPFAbWAurG4eUQVAkwNnxbyNOOlqwwNBzW5ZMwLWOlCoYnQrhbI2qW0olRhRBUqMMNKi4sTBgMkKhUJLQ9bzLcF2tXJ0cKNXgDPDgxQM39YePKitPKxknYe0jhZIjVTgiQEoZ02BFHqukLUSW1DzokQBEoXv+fSXQ1QZoUy7Q6+BvUIke57MMdaw5NTe0STEzSSvGBqBC1ES3hqye38BiUTxKRrnfCMVhXFAi5rcXrgSrPJA+d5tm2IxCP9bCD2XH22DIugut9I18Xxti/aoCRxHxKzga9hJCqNiDvuRhDHc0YFcioIhSAKcd7jk5xY1whQaMEdVQVx2qYgEpA6omKghUElJG9REVBDS+m+LoZSIz5EEaxkJM/AEuYqPttmo+lfLVl18hOydAbIvG279wA9vkEUYIKvXovfcvbyQ39k8fDuEEwpboToHadM8GxYkynTaXewJUJuM0EqtCipMUoB8QrPQk5G084QhaqOlJLJKRFD7HqA2gyjAeZCnrWUaUtpE6giVdV5nbaARgpQSkZKxqZr2DTBRIh5Gg7v/VxWuffltLOruhWNR24u0AvgmQ99gjv7kb1vmj75QZf924K2wnSc8NzQNmPUAyIBawveFiiKaNfSrNUAsuEuVLEimJJSgtidE2gO5EmBXDAzDIdgSHQ0OGkyxohIWEJ0CWSEeE3JEbcBsR5R3GjaKU6L5CmqUFWLxFwIQBVSmOz4iKfxvRe4++3zpYrWC6DH1818uyLk8k5mV9p427VerTk+jQQtqI/xDE0LUQeIDPAc8AJBatrWsNQ1wFklEAXNhUA36C44qk4IFbPGkVhRjQaYGy7eFYCahtK2iFZUOoASyDMoU6ckhaKUnHEKqgV3o6SEmFBFRUypVMVm95uk2xc9LQ8dEY7QduleAM9s6BPA71R55Dsm2/7YF8czldSQ8wxKg1jpSpkOzbggBIgVbt2EF0039KKjIToKSCjkbCgRLY44II4Xx3KNpYibUsVFKolYDgzrzdQCtCtYWUPFKVlxIqoBMSVgbFoYAUpxxaSQ82FKNixFSkp4mqhNbsgqT5wk7lvnXCm9AHp8rdAHYNV4YmTjjxbb/zipEbIWwghcHWJNagqkbsDdUgHLqApBHFLCU4Fi2KzFUqEUJ8SAhtglwy1oClQWkQTWelffdwVRkjldE7Tg880RGiMguBnuipdIO8tUMiCEQFUHGGRcEqlZI6cJgklzeCvsu+Y4W9ul3Q848pxAL4BnJvKhs/6rtwb++hWr937cwqGloN4gEihtpNIBGhQJ3RZ/x5GgBFc0QRwOIUYkFXzcULkgFqirrqtTgRA6T2ApkWdjSruGesbJXaudNvMKUYXIEpFNeAKRhhgK3raoCVEiJCfNCpSClExwI4SGoGuojalCjaZRmO66xnzywIuBZeTIG6LvBfD0kR0ypDvgzkvWHnlPKgf3VxKmXUBkgmQhZEGyE2KFaOysdZsgKkWdPG4JyZAYiRqxSYLWuq3P09SdGJdOBFYKdewuxytuIBAH85+b6fYJtYnUtOQ2Qfa5Z8l4Tl1lqDieDMkZaxLtakPTjim0iLV4e5ihuLg86LZ240JZPXSPUW6fnwt4L4Ae1tn+cqDY2pPG/rPatQ+qH3gk1lIxSw1uNVGMunJmkwneQpl0FR43iFpREqgrVRXRGLGcyckoMygJPDlqihUnTQvtxHELVHFAkBGxVJRWmU2hnQpqFYM46HjqT9X+A1EjQRR/KowR6dYnak1VDdEYKRqQEIkS8GbC5OABBrkK00OfLUHvvFgysyNtoVYvgKf37ooQjglSDinXHpO3/3W2/UncA8N6iE0bJBmzWUMJgoigqZDXWkQCOTtl3BCfanPGIXcz83EwwktAqcAV0YDEASI1XoTZWoNNG9pxQkuFpIAUwc1xo5sjkIhqxCyRS8JjhYZAccMlgAaSV2QPSKghRTTTDdDUEUaBdgbBd0k5eHXx2Vr7FSFfL4DeA4Abq9I+8tr05Lu9fWhfDBMl2AS3NWI08IDqgGq0CVFFEaIIITnDEAgaydlIawmfdG0J3kUyuEARIbnjSHd4pU5YqEELw0FNCIqlhLVNN0ZJpJk21FWFYHhOVDHiKG1xTBSj8yiGIFnJswS5ED2QJjPa7FgMpDDB6jVIMazsu9PL2nVvJE2vmi/Uyr0Aju6sV4Egun+nzz4TZvfvQNuA6AxtDc81RZRmNqUKgjUzSpswHPOCtQWbdWVPMyWEgGjAk0MJ8/4DQ2MiLnZbH5RCtVihg0K9WJEskFP3IBGhNJkgA4TYHYi1EwKFtJYIJVIHhWJEKsSs6xsqxiBUWDYgE4cDVCKhTSwFJ8RuIN/XHtO09xNVWX30PGAV/IhIiHsBPA0P4F6ugRvOmW3/U2/2HEC8W7GTGqOZzrprS7WimWZCjKgKeMLFcBx3p5QMGLGuiMNBR6kJDPMAIZBzQaYt2hZSW8AzWiCtGT51ysQhCZ4LxRqKJOKwa33IDtNZJgO5bZFpgybr5o4TpOmM2XRC8RbBETIxFoImmrVl8mSGtAVmEza7aFm9u/ihL2z21eW7Qaz3AEen8c+A4OmLIvsvmT72J2l2706JySV5QlthEEeEOMRTRsv89LXt1pMIoEg3tOIQYo2I0s5a2knTLcEik1YmmEekXiQnAXOGQ4WmpcwMawuxQO0Bm4FbIATFPZGbBkEYDhcZjhap64ioQnKsaRlWFVhBRFhcHM3PIxzMyE1DbhrqGFA3pGRkUJEsoXmq7fJnrFm+4/WdF9j49wz0AvimQx8BaJD9J9vqu/Lhu6/Rqh0RxBi44BQsTxmS0dRS4dQCNimU1tESIWu35MoMSiGiVKFCCogrZb64uUymFCvEwQANQ9JUsVShVjGMNVZmRKxbiUKNeiCaUEnsBmUaQaUGj4hHskcIkWwteItitJMEOUAGsYjYAC8Rc6VIorUpqCPB8eRSVre5zq7X9tCuhzgCrl7tBfDN8b8AEW9uga0vWbnroywsW9BQCDEQXbv1JNNMWl2BnLGmoTQJMadMc7eaxLULILzr/kxNBheiVGBgEQYLFQMDPTgmTA2SQiuUSWa22lAcNHTb4Cw5ygBvFW+8q/tnJVBBDpTGwBUPFcyrQNWgoooBcUWKUIchqRWsVAhD3GO3HyIUxBqiZEJwYglhsu9ql9kdr3N7qkVi465S6QXwTVZ9wO9C9pyadv6Zpe07tPYFQqtMVhKtQRq3hBQJdU2QbugkoN3JqmrX6FacUFWU4kRVZL4I19yxYuTU0kihSKSSIc24xacNtTuk0p0LtIKXSGlgujyhXZvirVNmkJOhooh1AsOdnFvEM+KBEIaUFLASu8jfjbYYMXanziXP7xcrgZAj3jomAuq0pUHyHsn7/1p8trvGOTz3BN4L4Ii3/hLx5Qlcfda+mz9nC9OBJp8wbWcsDrpXWS9UaO2UbMyathtzDF2pUzQiQcg5k1MmhNDVb6Sr3+dUUFGGMqBIIB63SFqoyLWQvZOgGkQC4jVp5lhSKqnxNnc9m1GRoEhwmjLFtOChQkTBjJIKliusVDTJutEddTREDKNYg0qhjl2jnbcVUiI5BdSVQTR0clibnTdlbbae7Ta7gw18CXcvgG848ZUK7EZk27dP73lf2rQmMYQBWjsWwYZOPQxARS6KWSBUNRaFYgnNTjHIHlGp8QI63/6gEqnrmhADFCc1LT5N+LQhqlFVgXphxMwDDIZQCWj60qQYDipVV10KGTcFC9Qq8xCsEMMirgMkLgBVdzIcu3BIJRC75XUEHM2CF8VdMIUSGtzWKJaRepEwPAGpliU98SmbHNh2HJ30NuSG6V4A31jiK+5Y5sljWb1qMH7wPjUxZnFG0xZiHNFOnbSaoHE8Cd5aNx3gkFtDghCiYO4EFM1OnmaCBNppw3TaIhqIg5oqDolF5hugM2mSyGszYjZK2+JeUIygQgh03zfNxGYIqxFbLaQVKONFSItEh3a6Sk4t5iBW4bmi0gU8V+RJ6cYwc5kP69RYI1RaM4iha40gYZJockMWo6rqsLLndkJ7z2vw6TVdWXTjzQ/3M8FfHwWIIvnGyENvWL79/XkwDVHNidWYaRsIFpAUKbmhrisUp7jjpbPuMqyxbN0lF053cuvdxjcxKBlUHJ9msinWtNSDbh+omTFwxQuU1FIPa6x1ihdkENHFBWgSvjJlEIcMj13CfUxyus1xvgDadA1uHmkTuLTdDfM+6IRWutNgN0cHAQYBl0A7aYmDgrjhCnHQVYsQpaSahTAT3/5xb+vjL6lP/M5lnC0IvpGqQ70Avn7iGx3fKzx+en7kfZYf2R5iOoYqzrCUCaVCcEJRotSk6RSJ0M2rd8MvIQZUAk1qqCSgQUhZUNHuVKF0kUNuE1Eqoio5JbIKGiOhDpRpi0bt1qagxFCTLbC6TxmnBcbpWHbtallpW0wGrK5l2nZGSQ2bN8Exm2qOOXbECS8YshTGLAal3qIMFyssKO00dYM2xWmToVZh7rhkSpriVUTyDGkVE0GigRVplu/LS6v3Ycecf5cONl+Ke543zPUCODLCH0wYP4hddfHaI1eVpXqLttMJrTW4g4oh3lAMQhBCnF9hOo+AVCHPElYyWtfYfN1JBch84EVDJFQKKrQTIw4WsDLDy4TBEIIKDBexUrHvcM32fc59O9Z4cm/LE09M2Lc241AWVpJzaK2Qy5cfPghEEVSchSXYtDRgSeDYgXLGCwe86IwRZ5xY8+LTFzn2mMQxw0gtLdmWoQKpNmFeoaXGJkIQw90xEiqKVqZ5z00SFs55hZ5y0XheQ7Uu6+4FsJGRgWi0VykPXHboM+9IemhaWVxCBwUp0pUUJWCWKd55fo1KpGtljrWSixFDIFSBNqfu7i8EKYKqEmole6YUo66GSGwJzFhcHJLLEvuX4bEDA+55fMKt9x3g4d0zlifO6thpPFLoJsa6cUuoB0M0F9wLospgEBF1cgOHxrBnNRMlEM24a1ehXLOLTXXk2GMDi0vKBeecyGvPqHjDOScw3LRMkG5on9zdV5B1AnoiMdeYG9XQtB3fVTav3XBiOXTmjWHLaW9ANk4u0Avgbzf8Pq9sTJWdJzSP/IGVXY9qyEsQGyqvKT6juJHahFadqfW52S3JKa0jVQCN5DYTgzAYDLq/k1vioCuNesqIOC5OmsxYGG5i5+HIA/dmHtoFV968nwd3tjQiJBNKLWgI1IPAACflFg+OhK6BrsmdZQYlFcOSkSzhFnACVaUMFSjGrEzZdOImcirsWgkcXm657fHHOa0OnLllyLnn1Fx44RKvedUmNo0mTGZjiigxrqIK2Ii2WWQwiGG884YyGJ79OraceB3EC2Fj7BPqBfB3JL4wuZFy22XL91ydl5aWoqwYAwk0yTCLeJUJI0VLt61BKqEUw4NR1XSTVwwIoe5KiDkjCDFGJFaU3BCCUdVDVpYDO/YNue7OGTc9vszd2yccbiCLMqwrFufJ9WTWdE11wWlTQVWJqiwujphMmm6zhHTXKgW6gziCYgajoEiItJZJpTCoA+2sC2lkmNjiSpQFVopx9zRyx5UrfOqGVc57+RYufE3FJd+xxOYXZBIrjIswqjcT2Uyb1qjK9lAe+zRx6eUX+7Eve0LgtI0ggl4Af2viKwocYnrnObMbf9dk18GQvKISZ6qZPAClBg+UdoZmo5SEpYAEJ0h37SLFcJsQwog2ZyqPXZ9D41Apg2qRSTvgjnsaPn3DKrc9POaxgw0lGh4j1TCgZnjOtNLtEXUzxLrkInsXXjnCbNzdG6wimDouStt2gjPrTpwVZ/PiiJXDh1kYDghVJOdCmwrSFkRAVbvJNC9IqNm7Ylxx25jrbjWuvanl7128wOvOPZbjT93EdDpF2E/yQhsmsHZHGey7U2Xz6dsIwxeyARrlegF8rfMRP/SwLV/zurUHri+bfItKUVqfoWERPBFISDbatRlS191mBit0NDPa7IS6JlqhjGeM6ppCjWtD/YLIuGzinscK7/3IPm58eI0DLtSjIbYQaUu37U2ZN82pYkASEBGigLkxqOtuRSLQti1RlFoDCSe7I+pgUGlAAa3g4OFlVIVMQUSZtDPEYTQcIUFoc9vdKVwSJhkZCWE0ZObwqQfXuPGxMa+6cj9v+xffxgXnHsukrFDXBWJE7ZCs7PqcxE0vPXvhlHPXgMV5Li69ADZG7P+U9d+Hb3vp6kOfMZKqBEFCSyVKnhnBHPcZgjMaREru+m3qGEG6jQ+xEggB00I7CehaotoUaerjuPl+5/2f38Pd28fsP+R4qChVxWpuGRZwD5gLJbVdrC10F18jVFW3VdqLEUWA7vsGVaAboenmDFSgUiXEiNF9PRShCF1rBk6azhhVFV6c4pnppOWEFyx1Wyuy0hSlyYnJdAxhSAqBXSWx+6HCvb+5le8850R++odfyRlnzpiyiowanR2+OS/uv/BkNr3kGpY2XTyvCMVeABuj5OnurLrsX8nLV7wk73rQat8syUCsQaiotOABikVEwUvuWoVdyNZ5ATfvBtBnChZZqAtZAnc/OuAD1xzg2ntXOTgVqqUFdGhIdqRtwQxijeZCrUIjTjIDhyqETnhmiAoxKM10RhVjl65351Pd2GVORA3UgyEgzEpGgekszQ+nu2eMIVJXQ8ZpQp5lQiWsjicMJFB5hVtAHIZDxa0muLM6nRIHFatEPn7THvYtN/zo953OORcM0C2ZuKCad1/ls+r0lyy+/O8p8qVGOekFsL75/1Sz220qd156+PaPlvrgaghswoITw4DSFtxn3eKqVrqhQM3EKn6peBRcKWLkWYs2LaPNx/P4MlyxdcxHr93LPfszHpVNdYWlhIsQBCpRBqEi5UxVB8SN0bBidZbmmaRgOTMYVIRQIUBV1ZSSu/uFq8C0aQmxImvoTqINxAqiwrTNLiEUt0LUwNJwQFsKhyZjUCVWQ9o26ax1DTgqLVUU6looGdo8pS3GsF6iwim5wWPg5m0THvz9rXzPI6dx+fe+imM2reh0dI/V8sDxpEtupqrOn7/gXgDrmPzt/I6iayXsvnTtrrenet/2qpJIydNuk4JIdyacC16EOoKrYRIopXQzvdm6yy1UGC1uYTbYwsfuXuFdn9zDw/sEqyL1QiADszKjojshnuVEjIGKgqvj3Q3ZBALDUNEYmAoWjGkqeDKCaudlgKqKPm6zi0ZbnSSm8+xTUovmHCMwABkNQ0SkO49Q6a5fMieKQ24JJdOaMxNxFfGqBTTYrM3MxDTUlWol3qYsErsN02PAdcS7Pv442x5Y4/9+6xs55WUTwuSewXT5vhNHJ75W3d1E1mca0AugM041AOHgyA7/uU1vuUGHs4wRcJt1q8krJ4bSVXFit3YwzwwJio4iZdIiY0e0xRY3c8ODI/7y2mU+df9eDpuwUMVukVWCUClaRZTuZscETLyQS2IUIzFlVJVpTrTZKYBqIMbaxYq4WXaEtZx0IuLHtk1YrCs56YTjdfOmTSwuLbG4tMSJJ57EaNMxDI/ZwmA43Lu0ZfOjMk/SB8MR4IzHY6oQGE/HNNNmMxpesXf7Dtm1Y4fs3bkDKUVXVyccWDnEwVku03ETcghlEMVjENm0OAyC+nFbjpfrH5nyi//rSn7hB96g5/+9PSW+4ObjLb/sOo2ji+ceNvQCWIfW3/EnBFmF2887dPOHiauTEMMizWzcXV5ddYlvyNrt2reaYk6ZjImxQgaKSIIwQhbP4Oo71nj7XzzOtjVj0zHHsphbmtyQitAmo45OyUKL4bmZJ7IOrqyWzoMEFDRi0poZuJvlWRujiJ918slxy+ZjOf30Mzn7FS/nuDNeOFlaHG4/+YwX7j39jDPYctxxLCwuUI0WncHwhUAANgNv+AbOPx4FJu3Kyv61g/tOnE5nywf2H8rbH3zw5PHa6tlbb70l7969M2577DH2LR9g3/JanuQcl4axsLgktx8a6y+98zp+Zvls/YGlUxerpQsutmNetVVDeGXvAdYfMoial11BnvyO6YPvNJ7YHYaDTeRs1KMFPAtaCaldBRuSsuM2puTCcDDEkpIOwXDpJLaujnjvp5a59s59TH2IjDKHJ1NSTlSDChUnaBeb5+Jo6bZDZOnapgfVgODFE8WaXCgUthQPp554Eue89jX64pe9lFdecJ688tzzrj7+pFPkmOOPr6irBLwaeBnw8r+T3aVkvmIz3FN5y1MIMUbgLIB682aO27w5A/E04LVvuigD1/0QnOjT8e4Du3b7vXfffczjDz9y7lWfvcLuu+u2sGvfHmaEcmhhE+/8yCPiScuP/pvzo736xQc1LGkvgPVn/R2kUll2ph8OszvuzNVUxUXQ0G1fzt6QE2CLXVw9lG6JFEYmIWEA8WQ+cuOEP/jMIzy8bCyMAoRCm7vQxh3S9KnmOUFdQYywMMRzt7MzO+VwadlSSjj7lBeGF73oLF534YW87Oxv2/GqC85bPeOVL9/PaHhsJ1ou/RoETyrzXf4CIvrUoAqAhBC+3uft83+602GRaGYFcO3aUC8GkNHi2ce/+CVc8uKXrABfuPzf/vyWbXdtPXjj1VeefssNV5756J0P8OgTe/mNP7uv1KM/Kz/47y88lbPOfgw4sxfA+oF1U17lRuHWN0xu+3jRQ8NAANUV8kxp1wpaKS6F4XCAqZBTpo5Dps2YUb3IrpUB771uFx+6cZmJKwuDAZoTKRWyB4o6MQSqoogaWYQWIVYVs2bmZngEXnLyieHc8y/gOy66eM9Fb7hox1mvfk0Kx28eAN8GnP43TyusgLjoly6uCAAhhOrpJkNPVWvmSauravgKcRRA3d28u6NsM3ARKrz43Nfw4nNf0/zIz/3cbTu2bp1+9rOfPeNTH/3w6f/9Dz/FIU576eX/+ddvHZ16fC+A9WH3fSYilXm6SWXPBWt3vl+mdz7IoESxABYU05bBMSPacUutSttOCXFACA5hghxzEp+6KfHhq57k5h0tcdNmFsVYm2WSybwleX54ZY6irEWjEYfkZTAr8rJjXqAXX3KRXPjd38XrLrzottNf9co1qvpE4HV/Q6lmJiImIIggXybls14d+Kqv41wc+pRAnhKFmbmqDoDzTn/1q/mpV796/1t+8sevv+ITn3rBZz/45y/6649/5vx/+K9+tA+BnmfMb3FJd+D1q1XGGbta89br08ikMmaEEhEPYF2vTb00wsoUcuh6/BUm+RTe/VcH+aMr97IqgYWFJUp2Vi0xaRswocxvcBwEJcaKXMxTxirg4rNfG17/xov4J2/5oT2vvvgNu6mrBng9QMm5EdUiIi4dy1RVlfXZVPYlUXSPiIObmbuqHj/acvzx33f55en7Lr987+H9B/cA5/UCeH4RwLNI9YZCuSmw6/zJre/L0ixXYbhIbK3bqRMUDSPa1ZaSExILKt2+nWLH8/t/eYD3X32QemnE5mxkK4xTYmbWXXsk3QXWiGKCH2pnvmTo5f/gzeEf/+N/woXf833XLZ15egTOAU6aeyUHPMQ42Mj1ZJCgKk/9SiYiFXDaMccfd1qfBK+D6AfEHQ4FxmaHPj1o7r8/D2QRCcJ4NmY4jARNSLXAiEVKkyiVo1qY5uP53T8/yPuu3cvC5gU8RA6urSBot81ZlRyFSgRS8mzim5PrD735H8j3X/5j00t+4J/dwmi08FQy+ZWeSbrbV+QIetcy/538KXHP/9wL4PlNfKlgejfcfMnhm9+XQquVzcuR9eIixRtKKoTpBHA0wTAcx337Im//zE6uumcNFpc4lGZMm4YZwihW3V5NNzCzbOIvGiyE7/3u75G3vO2tk1dddtmtbFp8EfAmvmzuv5L04Qh+5yLr9Rj4qBKAY/NrFncJj5/bPvCOEvfsjTJYwiYOkhiEAWnihFopjZCbCbr5ND5x84x3f24b9y07DYrprNuUYPPtsO4EF1sEf9niieG7//538f3/8qcOvvzii+9mGF/+JeI/dcuiSJD+DKYXwHPM/yJo5b78oNinL13demuulhFdTAQNzJrDSFUx3LSZdnUNEvjiWbzjr3byoS+ssZIGVNJQ2kIqIIPAoK6pYra1WfYX1ceEn3rLj/O9l//Qw2de+h17gHOZ1+vdvcytYOgp1wvg+UAW0Qom14h84XVrN/xpHq0QvK6xWSGGGYN6Ac/OrCmE4ZDh0iL/68P7eNcV+xktbsJz4UDuZm+DBKxkmtzk04cL8V+/5cf5nh99y8OvveSinYyqNwIvnYc6No8BeuL3Anj+4n53azJ+XSU7Tp09/ieL7ba9NsTENXf7dZoKKQH3hNhBmnA877k183ufexLCgJXVFYgBka6DMrjlJXf5R2/+B/FH/tXPPHj+P/lHe+nKmE8RPyMSNspqkF4AR3TVx01EQ8WBLbRXnL12+xdzjUcGSshTgghpWJNXZ9QF4uAl/MlfL/Obf/EAuR4Qs1NVQyaWQYrT4he++JXx//qlX+biH/mn17C0eB5w9pz4ZU78Pr7vBbAu+N8NuTC7Dh66bHzTu9vFQ9RtNiwaUGjdKKlFFsbIwln87kcO83ufeBwZVtTWXWBdB6GszPzkxWPkrT/7r+Sfv/Unrjnp1S8fAJd8RXIb6EOdXgDry/pLdGfscvAVPP6+kp7YG4c5IbMWWaiImxbJMydMnbo+nd/96JP83l/tQepIMKgl4D71ZkL+3tdfVv3rf/+rB8/+x995F3DZPLnN8/i+J34vgHVq/WVys9qtl+2/+ZN50NaBY2vC6gqSK9yFehiZ5WN5x4f28a7P7iMNB+Sc2FwvMptNynEawq/9+n+v/v7b3nYrLzjmNOAyN8uiKtKHOr0A1inmoY/dI9x/6eSO3y5xRWMVHBlPKe0ShAE2W0Pj8fzhJw7yzs9vZ7owoJbAggRW2ll602vPq/79r/7a8iv/2XffBbwGOA4o0rUG9+gFsG6t//wyu4MTDn9GVm+/3xfDAuoN+fAUQrdeJKXN/O8/3s57r9oHCwOOGy5AWrPBpPBzP/wT1Vt+5Vfv3PzKswZ8uf/e+3CnF8AGsP4E87xV5c4L9t3wUdeVEMumRCtO8hEDybRpyNs/upv3XbOPtHlILYodXs2vetGZ8Z/+2x9rfuhXfvUGKj0LOAP3lq6pS3rK9AJY54kvApKRu4+bPvqn2J7HfNOmBbEyhXbEaLCZmW7h9/7qSd59xX5k0whzp5qU/KbzL44//+u//vBZ/8eFTzyV6AKGzIfme/QCWOfkty5EOXydpqsvSfdcURZtKSSF2iKSoZEt/ObHH+ePrtjDiSccx/TwQba00X72Z38+/tgv/+Ld4dQXLAKXza1+dxtFj14AG0QAwVi7Wrn70gM3/mkeHmqjDl8ApYGiTBdP4rc/tJ0PX7uLLcduZnrocD5l6UT9H2//bT3vR77/JkbhvPk7Kb3VPzqwwa2bG1++v9cdWmfXy8u29xR9bLtKWmBy6CCVTclhyH/90GO864qdeF0zPTwul553cXz3Rz+j5/3kv7ieUXi9m+mXvUiPXgDrHvLUqGAGCcLhG8P0hhMP3XEN1TSqeWEgiuWT+YOPHuC9n99NDpWHSSk/9YNvDf/t/e+76vRLzrkXeCPuJvo3tij06EOg9Wz5RcEec+ywEM8xyzer3vmGQ3e83XTiqoMXkGf7WFw8kz/48C7ef91hFuvKN4da/s+3/pvwk//5V67VE4aXfaly1Lcx9ALYQJZ/fhtR3tmtNmGsuv2E9ok/r8sje0rItfhgjeCn8Qd/sYvfuXIHs+T2sk0n+3/4r/9j9sa3/eitRC6xUlrtrmvvyd8LYEMhAAjV67q9noevgesuOXT359KCnVBVNsVswHs/s4/3fH47e6chX3TmS+PvvON9nPDm8x8GLsE9aQh9otvnABsWNl9quxu2nr922wdKvWccZXIQ8SX+7DNT3v3ZPbS2OV1+8T+M7/jTjzx8wpvPvxM4Z962XPUff48NLICnllruf9CXP7vYbnvMhzlK7YG/vHaZt//5oxxabu3NF3xn9V/e884bT7zgVZvp1pBYH+/32OAC8OwuCuVGuPaS6a1fzKNZidWmIVc9sshvfexRJjPJb/uBy/VX3v7/PjB80QmvA06k26vZV3l6bOgcYH6TS7kDefjctXv/qDSP74yLYRM3PZ75tXfdx/J4If3HX/rF6gd/8eevZsvwTSWXEmIQ+k0MPTa4ALol+shYdNfJrHxokLc+ZqMYuP9Q4H++816braj/6r/75eoH/90v3MqWeA6gIYaNnu/06AUA8/p/gdlt8NCbDt/1iTIaz8LBfCq//d7bbN8u1Xf89h/wurf98FUM5bIvi6Ynf4+NLwCb3+rQwENvah98v/n2QzpdOIXf+P/ussfuUf0Pv/GbB1/30z+8lUouc/csoP1mhh5HigAcCIV9d4fZVW+YPvgFgp+gv/Unj/l1t0ztf/7OuycX/+QPbkW4xM2TaF/m7HGECMDxIkgwLzcG2fbGw7d9xKpmQf7k09v9qmum9vbf/2C84K3fdw/dAVcjKoP+o+1xpAjA52NYE2TvmXnvp73a1/CRj63wwY/tzr/x2x+ozv+J77sauHS+kKonf49vGBshPp5frzm+WfOtJ9m9X7AbbjigH/v82P/Tr7+3Ov8nvv9Wupld6xdS9TjSPEC3cMrT9ci2S9uH/rI8eNea/uEndpdf+Z8fDt/+w3//Kcuf+taGHuvaA5x86pnfStIr7hi284x2/8d54tY7+P2335//6Y/91/Dt3/9dV88tf9/XcwTiW+DLERcCGSAwuZZw6wun919bPvA7D5R//uO/Vv2LX/jZq6n1UiDRtzL3OAIFkIHg3t4isuuy2aNXtu/937f5Sy76l/Wlv/Svr6Li23FP9K0NPY7AHGC+2gRExi2zz9kf/spHbWnz5fUP/af/chWVXEb3H/tPr8fG8gDfRFwXzJpr4cmL3vcf35WHi28e/sR//2/7WBie7e6Fp64a6tHH/0eeB/CJlXSbhgNvuuXj/08TOWfwU7/2W3dxbFWZlU2qoW9t6LFxc4Cvp253phqW37TvgQ/6yr1x8AM/92tbOXl0JvAK1XDKPDzq45/e+h+hSbDkrbT3N49eexuvuehnHgynnfxCYPM89OkrPj02fgh08qlnsnvnY387/6kuo76U1//0pfDU1UP0F8311v8I8wDPxy/boyf/+g+BevQ4WgTQe4EezzcPvqYATnvj6X/jz09ev6MXQY8N8fl/NVe/msvrLgTqRdCT/6gMgXoR9OR/vhHX40v5WiXSHj3xn1MP8FzlAb036Mn/fMT/684D9N6gJ/66zwGeKy/wlS+v9wgbl/jP5Wf3rXDz63qA0954+nNO+m/EivSeobf03wh3n5Uk+Mnrd3xDP7zPE3qsZ+v/DYdAzzfZe/R4Nqz/t5QDPF+5QI8ezwYX9ekoqhdBj/VI/m8mYvmmPEAfCvU4UkKfpx0C9V6gx0YOfb5lAfShUI8jIfR5Wh6gF0GPI4H8TysE6kXQY6OTH0BOOuVF/mzEYX3C3OO5iPefLs+edhL8tR6g9wY91jv5nxEP8I0QvvcGPdYrp54xAXwjVr8XQo/1xqNnVAC9EHpsNN48KwL4ZnOAXhA94Z8vjjxrAuiT4R7PBJ5t4/isC6AXQo/1SPznXAC9GHqsx1D4eRFAL4ge6yX3WxcC6NHj+UK/HbpHL4AePXoB9OjRC6BHj14APXocNfj/AVTC5WLbQDtkAAAAAElFTkSuQmCC';

        modal.innerHTML = '<div style="width:100%;max-width:420px;background:#FFFFFF;border-radius:22px;border:1px solid #E2E8F0;padding:22px;box-shadow:0 25px 50px -12px rgba(0,0,0,0.45);">'
            + '<div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #F1F5F9;padding-bottom:14px;">'
            + '<div style="display:flex;align-items:center;gap:12px;">'
            + '<img src="{{ !empty($firm['slug']) ? url('/c/' . $firm['slug'] . '/pwa-icon-192.png') : url('/pwa-icon-192.png') }}" onerror="this.src=\'' + inlineIconSrc + '\'" alt="App Icon" style="width:48px;height:48px;border-radius:12px;border:1px solid #E2E8F0;background:#17191F;object-fit:contain;">'
            + '<div>'
            + '<span style="display:inline-block;background:#FEF3C7;color:#92400E;font-size:10px;font-weight:800;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:0.06em;">{{ addslashes($firm['app_name'] ?? (($firm['short_code'] ?? 'Practice') . ' ERP')) }}</span>'
            + '<h3 style="margin:3px 0 0;font-size:16px;font-weight:900;color:#0F172A;">Install {{ addslashes($firm['short_code'] ?? 'ERP') }} App</h3>'
            + '</div>'
            + '</div>'
            + '<button type="button" data-no-loading onclick="window.closeDwPwaModal()" style="background:#F1F5F9;border:none;border-radius:10px;width:32px;height:32px;font-weight:800;color:#475569;cursor:pointer;">✕</button>'
            + '</div>'
            + '<p style="margin:12px 0 0;font-size:12px;color:#475569;line-height:1.5;">Add a 1-tap shortcut app on your phone home screen to open and sign in to your workspace directly without typing the website address.</p>'
            + stepsHtml
            + '<div style="margin-top:16px;display:flex;justify-content:flex-end;gap:8px;">'
            + '<button type="button" data-no-loading onclick="window.closeDwPwaModal()" style="width:100%;background:#17191F;color:#FCDF02;border:none;border-radius:12px;padding:11px 16px;font-size:12px;font-weight:800;cursor:pointer;">Got It, Close</button>'
            + '</div>'
            + '</div>';
        document.body.appendChild(modal);
    };

    window.triggerDwPwaInstall = function () {
        if (isStandaloneApp()) {
            if (window.showToast) {
                window.showToast('App Active', 'You are already running the installed ERP app.', 'info');
            }
            return;
        }
        if (window.__dwDeferredPwaPrompt) {
            window.__dwDeferredPwaPrompt.prompt();
            window.__dwDeferredPwaPrompt.userChoice.then(function (choice) {
                window.__dwDeferredPwaPrompt = null;
                if (choice && choice.outcome === 'accepted') {
                    document.querySelectorAll('[data-dw-pwa-install]').forEach(function (btn) {
                        btn.style.display = 'none';
                    });
                }
            });
            return;
        }
        window.showDwPwaGuideModal();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureProgressElements);
    } else {
        ensureProgressElements();
    }
})();
</script>

