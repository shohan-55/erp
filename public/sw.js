// DhakaWala ERP PWA Service Worker (v3 - Network-First, Livewire-Safe, Chrome Installable)
const CACHE_NAME = 'dhakawala-erp-pwa-v3';
const PRECACHE_ASSETS = [
    '/manifest.webmanifest',
    '/pwa-icon-192.png',
    '/pwa-icon-512.png',
    '/images/pwa-icon-192.png',
    '/images/pwa-icon-512.png'
];

self.addEventListener('install', function (event) {
    self.skipWaiting();
    event.waitUntil(
        caches.open(CACHE_NAME).then(function (cache) {
            return cache.addAll(PRECACHE_ASSETS).catch(function () {});
        })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys().then(function (keys) {
            return Promise.all(
                keys.filter(function (key) {
                    return key !== CACHE_NAME;
                }).map(function (key) {
                    return caches.delete(key);
                })
            );
        }).then(function () {
            return self.clients.claim();
        })
    );
});

// Non-empty network-first fetch listener strictly required by Chrome's PWA installability algorithm
self.addEventListener('fetch', function (event) {
    if (event.request.method !== 'GET') {
        return;
    }

    event.respondWith(
        fetch(event.request)
            .then(function (networkResponse) {
                return networkResponse;
            })
            .catch(function () {
                return caches.match(event.request).then(function (cached) {
                    return cached || new Response('Offline', { status: 503, statusText: 'Offline' });
                });
            })
    );
});
