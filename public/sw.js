// DhakaWala ERP Lightweight PWA Service Worker (Network-first, safe for Livewire CSRF)
self.addEventListener('install', function (event) {
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', function (event) {
    // Pass-through network fetch so Livewire requests and dynamic CSRF tokens are never stale
    return;
});
