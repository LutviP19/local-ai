const CACHE_NAME = 'php-app-v1';

// Daftar aset yang akan di-cache secara permanen
const ASSETS_TO_CACHE = [
    './',
    './index.php',
    './assets/css/github-dark.min.css',
    './assets/css/app.css', // Sesuaikan path CSS Anda
    './assets/js/app.js',   // Sesuaikan path JS Anda
    './assets/js/htmx.min.js',
    './assets/js/alpine.min.js',
    './assets/js/tailwindcss.js',
    './assets/js/marked.min.js',
    './assets/js/highlight.min.js',
];

// 1. Install Event: Simpan aset dasar
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(ASSETS_TO_CACHE);
        })
    );
    self.skipWaiting();
});

// 2. Activate Event: Bersihkan cache lama
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cache) => {
                    if (cache !== CACHE_NAME) {
                        return caches.delete(cache);
                    }
                })
            );
        })
    );
});

// 3. Fetch Event: Strategi "Stale-While-Revalidate"
// Mengambil dari cache dulu agar cepat, lalu update di background
self.addEventListener('fetch', (event) => {
    // 1. Lewati request API & Ollama agar tidak masuk cache
    if (event.request.url.includes('api.php') || event.request.url.includes('api-debug.php') || event.request.url.includes(':11434')) {
        return;
    }

    event.respondWith(
        caches.match(event.request).then((cachedResponse) => {
            
            // 2. Siapkan Network Fetch sebagai fallback/update
            const fetchPromise = fetch(event.request).then((networkResponse) => {
                // VALIDASI: Pastikan respon valid sebelum di-cache
                if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
                    return networkResponse;
                }

                // CLONE DI SINI: Sebelum dikembalikan ke browser
                const responseToCache = networkResponse.clone();
                
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(event.request, responseToCache);
                });

                return networkResponse;
            }).catch(() => {
                // Jika offline dan tidak ada di cache, bisa return fallback di sini
            });

            // 3. Kembalikan Cache jika ada, jika tidak tunggu Network
            return cachedResponse || fetchPromise;
        })
    );
});