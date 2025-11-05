/**
 * RizqTrack Service Worker
 * Provides offline support and caching for PWA functionality
 */

const CACHE_NAME = 'rizqtrack-v1.0.1';
const urlsToCache = [
    '/wp-content/plugins/rizqtrack/assets/css/style.css',
    '/wp-content/plugins/rizqtrack/assets/js/app.js',
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js'
];

// Install event - cache resources
self.addEventListener('install', event => {
    console.log('[Service Worker] Installing...');

    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => {
                console.log('[Service Worker] Caching app shell');
                return cache.addAll(urlsToCache);
            })
            .catch(err => {
                console.error('[Service Worker] Cache failed:', err);
            })
    );

    // Force the waiting service worker to become the active service worker
    self.skipWaiting();
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('[Service Worker] Activating...');

    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[Service Worker] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );

    // Claim clients immediately
    return self.clients.claim();
});

// Fetch event - serve from cache, fallback to network
self.addEventListener('fetch', event => {
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin) &&
        !event.request.url.includes('cdn.jsdelivr.net')) {
        return;
    }

    // Skip admin-ajax.php requests (these should always go to network)
    if (event.request.url.includes('admin-ajax.php')) {
        event.respondWith(
            fetch(event.request)
                .catch(() => {
                    return new Response(
                        JSON.stringify({
                            success: false,
                            message: 'You are offline. Please try again when connected.'
                        }),
                        {
                            headers: { 'Content-Type': 'application/json' }
                        }
                    );
                })
        );
        return;
    }

    event.respondWith(
        caches.match(event.request)
            .then(response => {
                // Cache hit - return response
                if (response) {
                    console.log('[Service Worker] Serving from cache:', event.request.url);
                    return response;
                }

                // Clone the request
                const fetchRequest = event.request.clone();

                return fetch(fetchRequest).then(response => {
                    // Check if valid response
                    if (!response || response.status !== 200 || response.type === 'error') {
                        return response;
                    }

                    // Clone the response
                    const responseToCache = response.clone();

                    // Cache CSS, JS, and image files
                    if (event.request.url.match(/\.(css|js|png|jpg|jpeg|svg|gif)$/)) {
                        caches.open(CACHE_NAME)
                            .then(cache => {
                                cache.put(event.request, responseToCache);
                            });
                    }

                    return response;
                }).catch(() => {
                    // Network failed, return offline page or error
                    return new Response(
                        '<h1>You are offline</h1><p>Please check your internet connection.</p>',
                        {
                            headers: { 'Content-Type': 'text/html' }
                        }
                    );
                });
            })
    );
});

// Background sync for offline transactions (future enhancement)
self.addEventListener('sync', event => {
    console.log('[Service Worker] Background sync:', event.tag);

    if (event.tag === 'sync-transactions') {
        event.waitUntil(
            // Could implement offline transaction sync here
            Promise.resolve()
        );
    }
});

// Push notifications (future enhancement)
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'New budget alert!',
        icon: '/wp-content/plugins/rizqtrack/assets/icons/icon-192x192.png',
        badge: '/wp-content/plugins/rizqtrack/assets/icons/icon-72x72.png',
        vibrate: [200, 100, 200]
    };

    event.waitUntil(
        self.registration.showNotification('RizqTrack', options)
    );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
    event.notification.close();

    event.waitUntil(
        clients.openWindow('/')
    );
});
