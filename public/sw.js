const CACHE_NAME = 'megawifi-v9';
const STATIC_CACHE = 'megawifi-static-v9';

// Static assets to pre-cache
const STATIC_ASSETS = [
    '/manifest-balance.json',
    '/icons/icon-72.png',
    '/icons/icon-96.png',
    '/icons/icon-128.png',
    '/icons/icon-144.png',
    '/icons/icon-152.png',
    '/icons/icon-192.png',
    '/icons/icon-384.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    '/icons/icon-maskable-512.png',
    '/favicon.ico'
];

// Pages to cache for offline access
const OFFLINE_PAGES = [
    '/check-balance'
];

// Dynamic pages - always fetch from network
const DYNAMIC_ROUTES = [
    '/usermanager',
    '/hotspot',
    '/dashboard',
    '/routers',
    '/api/',
    '/admin/',
    '/wireguard',
    '/reseller',
    '/livewire'
];

// Install event
self.addEventListener('install', event => {
    console.log('[SW] Installing v8...');
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => {
                console.log('[SW] Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            }),
            caches.open(CACHE_NAME).then(cache => {
                console.log('[SW] Caching offline pages');
                return cache.addAll(OFFLINE_PAGES);
            })
        ]).catch(err => console.log('[SW] Cache error:', err))
    );
    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', event => {
    console.log('[SW] Activating v8...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => {
                        // Only delete OUR old caches (megawifi-v* / megawifi-static-v*)
                        // Never touch IPTV caches (megawifi-iptv-*)
                        if (name === CACHE_NAME || name === STATIC_CACHE) return false;
                        if (name.startsWith('megawifi-iptv-')) return false;
                        if (name.startsWith('megawifi-balance-')) return false;
                        if (name.startsWith('megawifi-v') || name.startsWith('megawifi-static-v')) return true;
                        return false;
                    })
                    .map(name => {
                        console.log('[SW] Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        })
    );
    self.clients.claim();
});

// Fetch event
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (!url.protocol.startsWith('http')) return;

    // Don't intercept IPTV routes - handled by sw-iptv.js
    if (url.pathname.startsWith('/iptv')) return;
    if (url.pathname.startsWith('/check-balance')) return;

    const isDynamic = DYNAMIC_ROUTES.some(route => url.pathname.includes(route));

    if (isDynamic) {
        event.respondWith(networkFirst(request));
        return;
    }

    const isStatic = url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|ttf|svg|webp)$/);
    if (isStatic) {
        event.respondWith(cacheFirst(request));
        return;
    }

    event.respondWith(networkFirst(request));
});

async function networkFirst(request) {
    try {
        const response = await fetch(request);
        if (response.ok && request.url.includes(self.location.origin)) {
            const cache = await caches.open(CACHE_NAME);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        if (request.mode === 'navigate') return caches.match('/dashboard');
        throw error;
    }
}

async function cacheFirst(request) {
    const cached = await caches.match(request);
    if (cached) return cached;
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        throw error;
    }
}

self.addEventListener('message', event => {
    if (event.data === 'skipWaiting') self.skipWaiting();
    if (event.data === 'clearCache') {
        // Only clear our own caches, not IPTV
        caches.keys().then(names => 
            names.filter(n => !n.startsWith('megawifi-iptv-'))
                 .forEach(name => caches.delete(name))
        );
    }
});

console.log('[SW] Service Worker v5 loaded');
