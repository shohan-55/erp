<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="min-h-full overflow-x-hidden bg-[#F6F7F9]">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="{{ !empty($firm['slug']) ? url('/c/' . $firm['slug'] . '/manifest.webmanifest') : url('/manifest.webmanifest') }}">
    <link rel="icon" type="image/png" href="{{ !empty($firm['slug']) ? url('/c/' . $firm['slug'] . '/pwa-icon-192.png') : url('/pwa-icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ !empty($firm['slug']) ? url('/c/' . $firm['slug'] . '/pwa-icon-192.png') : url('/pwa-icon-192.png') }}">
    <meta name="theme-color" content="{{ $firm['primary_color'] ?? '#0D9488' }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $title ?? 'DhakaWala ERP — Practice Management' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <x-brand-theme-style :firm="$firm ?? null" />
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen overflow-x-hidden bg-[#F6F7F9] font-sans text-[#20232A] antialiased">
    {{ $slot }}
    <div id="toast-container" aria-live="polite" aria-atomic="true"></div>
    @livewireScripts
</body>
</html>