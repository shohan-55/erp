<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 overflow-x-hidden">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'DhakaWala ERP — Enterprise Practice Authentication' }}</title>

    <!-- Google Font: Inter & JetBrains Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">

    <!-- Favicon: DhakaWala ERP Official Brand Emblem -->
    <link rel="icon" type="image/svg+xml" href="{{ asset('images/dhakawala_logo.svg') }}">

    <!-- Laravel Vite Bundler -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <x-brand-theme-style :firm="$firm ?? null" />
</head>
<body class="min-h-full w-full overflow-x-hidden bg-slate-50 text-slate-900 antialiased selection:bg-[#FCDF02] selection:text-slate-950 font-sans">

    <!-- Authentication Shell Content -->
    {{ $slot }}

    <!-- Dynamic Toast Notification Container -->
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
