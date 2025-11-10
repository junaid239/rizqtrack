/**
 * RizqTrack Service Worker
 * Provides offline support and caching for PWA functionality
 */

const CACHE_NAME = 'rizqtrack-v1.6.1'; // Incremented to force cache refresh
const urlsToCache = [
    '/wp-content/plugins/rizqtrack/assets/css/style.css',
    // Removed app.js from cache - it must always be fetched fresh
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

// Fetch event - NETWORK FIRST strategy with cache fallback
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

    // CRITICAL: Skip caching app.js completely - always fetch fresh
    if (event.request.url.includes('/assets/js/app.js')) {
        event.respondWith(
            fetch(event.request, {
                cache: 'no-store' // Force no browser cache
            }).catch(() => {
                // If offline and absolutely no cache, return error
                return new Response('', { status: 503, statusText: 'Service Unavailable' });
            })
        );
        return;
    }

    // NETWORK FIRST: Try network first, fallback to cache if offline
    event.respondWith(
        fetch(event.request)
            .then(response => {
                // Check if valid response
                if (!response || response.status !== 200 || response.type === 'error') {
                    // Try cache if network fails
                    return caches.match(event.request).then(cachedResponse => {
                        return cachedResponse || response;
                    });
                }

                // Clone the response
                const responseToCache = response.clone();

                // Cache CSS and image files for offline use (but NOT JS)
                if (event.request.url.match(/\.(css|png|jpg|jpeg|svg|gif)$/) &&
                    !event.request.url.includes('/assets/js/')) {
                    caches.open(CACHE_NAME)
                        .then(cache => {
                            cache.put(event.request, responseToCache);
                            console.log('[Service Worker] Updated cache:', event.request.url);
                        });
                }

                return response;
            })
            .catch(() => {
                // Network failed completely - serve from cache
                return caches.match(event.request)
                    .then(cachedResponse => {
                        if (cachedResponse) {
                            console.log('[Service Worker] Serving from cache (offline):', event.request.url);
                            return cachedResponse;
                        }
                        // No cache available
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
