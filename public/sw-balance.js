const CACHE_NAME = 'megawifi-balance-v2';
const STATIC_CACHE = 'megawifi-balance-static-v2';

const STATIC_ASSETS = [
    '/manifest-balance.json',
    '/icons/balance-icon.svg',
    '/icons/balance-icon-72.png',
    '/icons/balance-icon-96.png',
    '/icons/balance-icon-128.png',
    '/icons/balance-icon-144.png',
    '/icons/balance-icon-152.png',
    '/icons/balance-icon-192.png',
    '/icons/balance-icon-384.png',
    '/icons/balance-icon-512.png',
    '/icons/balance-maskable-192.png',
    '/icons/balance-maskable-512.png',
    '/favicon.ico'
];

const OFFLINE_PAGES = [
    '/check-balance'
];

self.addEventListener('install', event => {
    console.log('[Balance-SW] Installing v1...');
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => {
                return cache.addAll(STATIC_ASSETS);
            }),
            caches.open(CACHE_NAME).then(cache => {
                return cache.addAll(OFFLINE_PAGES);
            })
        ]).catch(err => console.log('[Balance-SW] Cache error:', err))
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    console.log('[Balance-SW] Activating v1...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => {
                        if (name === CACHE_NAME || name === STATIC_CACHE) return false;
                        if (name.startsWith('megawifi-balance-')) return true;
                        return false;
                    })
                    .map(name => {
                        console.log('[Balance-SW] Deleting old cache:', name);
                        return caches.delete(name);
                    })
            );
        })
    );
    self.clients.claim();
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;
    if (!url.protocol.startsWith('http')) return;

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
        if (request.mode === 'navigate') return caches.match('/check-balance');
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
        caches.keys().then(names =>
            names.filter(n => n.startsWith('megawifi-balance-'))
                 .forEach(name => caches.delete(name))
        );
    }
});
