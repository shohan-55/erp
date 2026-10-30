<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Colour theme: resolved before first paint so there is no flash of
         the wrong palette. Stored choice wins, otherwise the OS preference. -->
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('dw-theme');
                var theme = (stored === 'light' || stored === 'dark')
                    ? stored
                    : ((window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) ? 'light' : 'dark');
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>

    <title>{{ $title ?? 'DhakaWala ERP — Enterprise Practice Management Platform' }}</title>

    <!-- Google Font: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Favicon: DhakaWala ERP Official Brand Emblem -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/dhakawala_logo.svg') }}">

    <!-- Laravel Vite Bundler -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <x-brand-theme-style />
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased selection:bg-[#FCDF02] selection:text-slate-950 font-sans">
    
    <!-- Livewire Root Content -->
    {{ $slot }}

    <!-- Toast Notification Container -->
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

    @livewireScripts

    <!-- Livewire to Native Toast Bridge -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('toast', (data) => {
                const payload = Array.isArray(data) ? data[0] : data;
                if (window.showToast) {
                    window.showToast(payload.title, payload.message, payload.type);
                }
            });
        });
    </script>
</body>
</html>
