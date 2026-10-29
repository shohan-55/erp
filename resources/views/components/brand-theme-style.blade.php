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
</style>
