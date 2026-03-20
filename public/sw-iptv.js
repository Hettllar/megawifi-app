const CACHE_NAME = 'megawifi-iptv-v14';
const STATIC_CACHE = 'megawifi-iptv-static-v7';

const STATIC_ASSETS = [
    '/manifest-iptv.json',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
    '/icons/icon-maskable-192.png',
    'https://cdn.tailwindcss.com',
    'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/hls.js@latest'
];

const OFFLINE_PAGES = ['/iptv'];

self.addEventListener('install', event => {
    console.log('[IPTV-SW] Installing v3...');
    event.waitUntil(
        Promise.all([
            caches.open(STATIC_CACHE).then(cache => {
                return cache.addAll(STATIC_ASSETS).catch(err => {
                    console.log('[IPTV-SW] Some static assets failed to cache:', err);
                });
            }),
            caches.open(CACHE_NAME).then(cache => {
                return cache.addAll(OFFLINE_PAGES).catch(err => {
                    console.log('[IPTV-SW] Offline pages cache error:', err);
                });
            })
        ])
    );
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    console.log('[IPTV-SW] Activating v3...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames
                    .filter(name => name.startsWith('megawifi-iptv-') && name !== CACHE_NAME && name !== STATIC_CACHE)
                    .map(name => {
                        console.log('[IPTV-SW] Deleting old cache:', name);
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

    // Never cache stream URLs
    if (url.pathname.includes('/iptv/stream/') || url.pathname.includes('/iptv/channels') || url.pathname.includes('/iptv/hls-proxy') || pathname.includes('/iptv/stream-proxy')) {
        return;
    }

    // Static assets - cache first
    const isStatic = url.pathname.match(/\.(css|js|png|jpg|jpeg|gif|ico|woff|woff2|ttf|svg|webp|json)$/) ||
                     url.hostname !== self.location.hostname;
    if (isStatic) {
        event.respondWith(cacheFirst(request));
        return;
    }

    // Pages - network first
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
        if (request.mode === 'navigate') return caches.match('/iptv');
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
        caches.keys().then(names => names.filter(n => n.startsWith('megawifi-iptv-')).forEach(name => caches.delete(name)));
    }
});

console.log('[IPTV-SW] Service Worker v1 loaded');
