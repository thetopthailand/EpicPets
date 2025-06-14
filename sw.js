// Service Worker for SmileCraft Minecraft Homepage
const CACHE_NAME = 'smilecraft-v1.0.0';
const STATIC_CACHE = 'smilecraft-static-v1';
const DYNAMIC_CACHE = 'smilecraft-dynamic-v1';

// Resources to cache immediately
const STATIC_ASSETS = [
    '/',
    '/index.html',
    '/styles.css',
    '/script.js',
    'https://cdn.tailwindcss.com',
    'https://smilecraft.unaux.com/logo.png'
];

// API endpoints that should be cached with network-first strategy
const API_ENDPOINTS = [
    'https://api.mcsrvstat.us/3/hypixel.net'
];

// Install event - cache static assets
self.addEventListener('install', event => {
    console.log('Service Worker: Installing...');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('Service Worker: Caching static assets');
                return cache.addAll(STATIC_ASSETS);
            })
            .then(() => {
                console.log('Service Worker: Static assets cached successfully');
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('Service Worker: Failed to cache static assets', error);
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
    console.log('Service Worker: Activating...');
    
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                            console.log('Service Worker: Deleting old cache', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('Service Worker: Activated successfully');
                return self.clients.claim();
            })
    );
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests with appropriate strategies
    if (isStaticAsset(request.url)) {
        event.respondWith(cacheFirstStrategy(request));
    } else if (isAPIRequest(request.url)) {
        event.respondWith(networkFirstStrategy(request));
    } else if (isImageRequest(request.url)) {
        event.respondWith(cacheFirstWithFallback(request));
    } else {
        event.respondWith(staleWhileRevalidateStrategy(request));
    }
});

// Caching Strategies

// Cache First - Good for static assets that rarely change
async function cacheFirstStrategy(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Cache First Strategy failed:', error);
        return new Response('Offline - Resource not available', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Network First - Good for API calls that need fresh data
async function networkFirstStrategy(request) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.log('Network failed, trying cache:', error);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            // Add a header to indicate this is cached data
            const response = cachedResponse.clone();
            response.headers.set('X-Served-From', 'cache');
            return response;
        }
        
        // Return a custom offline response for API calls
        return new Response(JSON.stringify({
            online: false,
            error: 'Network unavailable',
            cached: false,
            timestamp: Date.now()
        }), {
            status: 200,
            headers: {
                'Content-Type': 'application/json',
                'X-Served-From': 'offline'
            }
        });
    }
}

// Stale While Revalidate - Good for resources that can be slightly stale
async function staleWhileRevalidateStrategy(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(error => {
        console.log('Network request failed:', error);
        return cachedResponse;
    });
    
    return cachedResponse || fetchPromise;
}

// Cache First with Fallback - Good for images
async function cacheFirstWithFallback(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        // Return a placeholder image for failed image requests
        return new Response(
            '<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><rect width="100" height="100" fill="#374151"/><text x="50" y="50" text-anchor="middle" dy=".3em" fill="#9CA3AF">Image</text></svg>',
            {
                headers: {
                    'Content-Type': 'image/svg+xml',
                    'X-Served-From': 'fallback'
                }
            }
        );
    }
}

// Helper Functions

function isStaticAsset(url) {
    return STATIC_ASSETS.some(asset => url.includes(asset)) ||
           url.includes('.css') ||
           url.includes('.js') ||
           url.includes('tailwindcss.com');
}

function isAPIRequest(url) {
    return API_ENDPOINTS.some(endpoint => url.includes(endpoint)) ||
           url.includes('api.mcsrvstat.us');
}

function isImageRequest(url) {
    return url.includes('.png') ||
           url.includes('.jpg') ||
           url.includes('.jpeg') ||
           url.includes('.gif') ||
           url.includes('.webp') ||
           url.includes('.svg');
}

// Background Sync for offline actions
self.addEventListener('sync', event => {
    if (event.tag === 'server-status-sync') {
        event.waitUntil(syncServerStatus());
    }
});

async function syncServerStatus() {
    try {
        const response = await fetch('https://api.mcsrvstat.us/3/hypixel.net');
        if (response.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put('https://api.mcsrvstat.us/3/hypixel.net', response.clone());
            
            // Notify all clients about the update
            const clients = await self.clients.matchAll();
            clients.forEach(client => {
                client.postMessage({
                    type: 'SERVER_STATUS_UPDATED',
                    data: response.json()
                });
            });
        }
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// Push notifications (for future use)
self.addEventListener('push', event => {
    if (!event.data) return;
    
    const data = event.data.json();
    const options = {
        body: data.body || 'SmileCraft server update',
        icon: 'https://smilecraft.unaux.com/logo.png',
        badge: 'https://smilecraft.unaux.com/logo.png',
        vibrate: [200, 100, 200],
        data: data.data || {},
        actions: [
            {
                action: 'open',
                title: 'Open SmileCraft',
                icon: 'https://smilecraft.unaux.com/logo.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: 'https://smilecraft.unaux.com/logo.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification(data.title || 'SmileCraft', options)
    );
});

// Handle notification clicks
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'open') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

// Message handling for communication with main thread
self.addEventListener('message', event => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'GET_CACHE_STATUS':
            getCacheStatus().then(status => {
                event.ports[0].postMessage(status);
            });
            break;
            
        case 'CLEAR_CACHE':
            clearAllCaches().then(() => {
                event.ports[0].postMessage({ success: true });
            });
            break;
            
        case 'FORCE_UPDATE':
            forceUpdate().then(() => {
                event.ports[0].postMessage({ success: true });
            });
            break;
    }
});

async function getCacheStatus() {
    const cacheNames = await caches.keys();
    const status = {};
    
    for (const cacheName of cacheNames) {
        const cache = await caches.open(cacheName);
        const keys = await cache.keys();
        status[cacheName] = keys.length;
    }
    
    return status;
}

async function clearAllCaches() {
    const cacheNames = await caches.keys();
    await Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
    );
}

async function forceUpdate() {
    await clearAllCaches();
    const cache = await caches.open(STATIC_CACHE);
    await cache.addAll(STATIC_ASSETS);
}

// Performance monitoring
self.addEventListener('fetch', event => {
    const start = performance.now();
    
    event.respondWith(
        handleRequest(event.request).then(response => {
            const duration = performance.now() - start;
            
            // Log slow requests
            if (duration > 1000) {
                console.warn(`Slow request: ${event.request.url} took ${duration}ms`);
            }
            
            return response;
        })
    );
});

async function handleRequest(request) {
    // This is where the main fetch logic would go
    // For now, just pass through to the existing strategies
    return fetch(request);
}

console.log('Service Worker: Loaded successfully');

