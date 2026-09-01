// Jasapedia service worker — static-only caching. Never cache transactional/API responses.
const CACHE = 'jasapedia-static-v1';
const ASSETS = [
    '/branding/logo.svg',
    '/branding/favicon.svg',
    '/branding/favicon-32x32.png',
    '/branding/favicon-192x192.png',
    '/branding/icon-512x512.png',
    '/manifest.webmanifest',
];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k)))),
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.method !== 'GET' || url.pathname.startsWith('/api/') || url.pathname.startsWith('/admin')) {
        return; // network-only for transactional paths
    }

    // Cache-first for branding assets, network-first for everything else.
    if (url.pathname.startsWith('/branding/')) {
        event.respondWith(
            caches.match(event.request).then((hit) => hit || fetch(event.request)),
        );
    }
});
