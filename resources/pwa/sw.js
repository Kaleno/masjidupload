const CACHE = 'al-ihsan-static-v3';
const OFFLINE_URL = '/offline';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE)
            .then((cache) => cache.addAll([
                new Request(OFFLINE_URL, { cache: 'reload' }),
                '/icons/icon-192.png',
            ]))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(
                keys.filter((key) => key !== CACHE).map((key) => caches.delete(key)),
            ))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    const isStatic = url.pathname.startsWith('/build/')
        || url.pathname.startsWith('/icons/')
        || url.pathname.startsWith('/images/');

    if (isStatic) {
        event.respondWith(cacheFirst(request));

        return;
    }

    const wantsHtml = request.mode === 'navigate'
        || (request.headers.get('accept') ?? '').includes('text/html');

    if (wantsHtml) {
        event.respondWith(networkOrOfflinePage(request));
    }
});

async function cacheFirst(request) {
    const cache = await caches.open(CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        await cache.put(request, response.clone());
    }

    return response;
}

async function networkOrOfflinePage(request) {
    try {
        return await fetch(request);
    } catch (error) {
        const cache = await caches.open(CACHE);
        const offline = await cache.match(OFFLINE_URL);

        if (offline) {
            return offline;
        }

        throw error;
    }
}
