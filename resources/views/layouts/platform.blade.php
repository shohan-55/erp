<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full bg-[#F7F8FA]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'DhakaWala ERP SaaS — Platform Preview' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-[#F7F8FA] text-[#171A21] font-sans antialiased">
    {{ $slot }}
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('toast', (event) => {
                const payload = Array.isArray(event) ? event[0] : event;
                if (window.showToast) window.showToast(payload.title, payload.message, payload.type);
            });
        });
    </script>
</body>
</html>
