/* ============================================================
   GéoMonkey — Service Worker v1.2
   Stratégies :
   - Navigations : network-first, fallback page offline
     (jamais de cache sur /admin, /newsletter, /webhook, profiler)
   - /images/ et /build/ (AssetMapper versionné) : cache-first
   - Vidéos : non cachées (trop lourdes, inutiles hors ligne)
   ============================================================ */
'use strict';

const VERSION = 'gm-sw-1.2.0';
const CACHE_PAGES = 'gm-pages-' + VERSION;
const CACHE_STATIC = 'gm-static-' + VERSION;
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_PAGES)
            .then((cache) => cache.addAll([OFFLINE_URL]))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => !key.endsWith(VERSION)).map((key) => caches.delete(key))
            ))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return; // CDN Bootstrap, Stripe : laisse le navigateur gérer
    }

    // Zones dynamiques/sensibles : le SW ne s'en mêle pas
    if (url.pathname.startsWith('/admin')
        || url.pathname.startsWith('/newsletter')
        || url.pathname.startsWith('/webhook')
        || url.pathname.startsWith('/_profiler')
        || url.pathname.startsWith('/_wdt')
        || url.pathname.startsWith('/connexion')
        || url.pathname.startsWith('/inscription')) {
        return;
    }

    // 1) Navigations : network-first, fallback cache puis offline
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(CACHE_PAGES).then((cache) => cache.put(request, copy));
                    return response;
                })
                .catch(() =>
                    caches.match(request).then((cached) => cached || caches.match(OFFLINE_URL))
                )
        );
        return;
    }

    // 2) Assets versionnés AssetMapper : cache-first
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) =>
                cached || fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_STATIC).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
            )
        );
        return;
    }

    // 3) Images du site : cache-first (revues à chaque nouvelle version)
    if (url.pathname.startsWith('/images/')) {
        event.respondWith(
            caches.match(request).then((cached) =>
                cached || fetch(request).then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CACHE_STATIC).then((cache) => cache.put(request, copy));
                    }
                    return response;
                })
            )
        );
    }
    // Tout le reste (dont /videos/) : réseau normal, pas de cache
});
