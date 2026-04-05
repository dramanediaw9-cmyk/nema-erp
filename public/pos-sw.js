const SHELL_CACHE = 'nema-pos-shell-v2';
const RUNTIME_CACHE = 'nema-pos-runtime-v2';
const DB_NAME = 'nema-erp-pos-offline';
const STORE_NAME = 'queue_store';
const SYNC_TAG = 'nema-pos-sync';

const scopeUrl = new URL(self.registration.scope);
const OFFLINE_URL = new URL('pos-offline.html', scopeUrl).toString();
const MANIFEST_URL = new URL('pos-manifest.webmanifest', scopeUrl).toString();
const ICON_URLS = [
    new URL('icons/pos-192.png', scopeUrl).toString(),
    new URL('icons/pos-512.png', scopeUrl).toString(),
    new URL('icons/pos-maskable-512.png', scopeUrl).toString(),
];

const openOfflineDatabase = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
            db.createObjectStore(STORE_NAME, { keyPath: 'key' });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const getQueueRecords = async () => {
    const db = await openOfflineDatabase();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readonly');
        const request = transaction.objectStore(STORE_NAME).getAll();
        request.onsuccess = () => resolve(Array.isArray(request.result) ? request.result : []);
        request.onerror = () => reject(request.error);
        transaction.oncomplete = () => db.close();
    });
};

const putQueueRecord = async (key, value) => {
    const db = await openOfflineDatabase();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).put({ key, value });
        transaction.oncomplete = () => {
            db.close();
            resolve(true);
        };
        transaction.onerror = () => reject(transaction.error);
    });
};

const deleteQueueRecord = async (key) => {
    const db = await openOfflineDatabase();
    return new Promise((resolve, reject) => {
        const transaction = db.transaction(STORE_NAME, 'readwrite');
        transaction.objectStore(STORE_NAME).delete(key);
        transaction.oncomplete = () => {
            db.close();
            resolve(true);
        };
        transaction.onerror = () => reject(transaction.error);
    });
};

const notifyClients = async () => {
    const clients = await self.clients.matchAll({ includeUncontrolled: true, type: 'window' });
    for (const client of clients) {
        client.postMessage({ type: 'pos-queue-updated' });
    }
};

const extractErrorMessage = (data, fallback) => {
    if (data && typeof data.message === 'string' && data.message.trim() !== '') {
        return data.message;
    }
    if (data && data.errors && typeof data.errors === 'object') {
        const first = Object.values(data.errors).flat().find(Boolean);
        if (first) {
            return first;
        }
    }
    return fallback;
};

const syncQueuedTickets = async () => {
    const records = await getQueueRecords();

    for (const record of records) {
        const queue = Array.isArray(record.value) ? record.value : [];
        if (!queue.length) {
            await deleteQueueRecord(record.key);
            continue;
        }

        const remaining = [];

        for (const entry of queue) {
            try {
                const response = await fetch(entry.store_url || new URL('point-de-vente/vente', scopeUrl).toString(), {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': entry.csrf_token || '',
                    },
                    body: JSON.stringify(entry.payload || {}),
                });

                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    remaining.push({
                        ...entry,
                        last_error: extractErrorMessage(data, 'Impossible de synchroniser un ticket hors ligne.'),
                    });
                }
            } catch (error) {
                remaining.push({
                    ...entry,
                    last_error: error?.message || 'Impossible de synchroniser un ticket hors ligne.',
                });
            }
        }

        if (remaining.length) {
            await putQueueRecord(record.key, remaining);
        } else {
            await deleteQueueRecord(record.key);
        }
    }

    await notifyClients();
};

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        const cache = await caches.open(SHELL_CACHE);
        await cache.addAll([OFFLINE_URL, MANIFEST_URL, ...ICON_URLS]);
        await self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((key) => ![SHELL_CACHE, RUNTIME_CACHE].includes(key)).map((key) => caches.delete(key)));
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);
    if (url.origin !== self.location.origin) {
        return;
    }

    const isPosNavigation = request.mode === 'navigate' && url.pathname.includes('/point-de-vente');
    const isStaticAsset = request.destination === 'image'
        || url.pathname.includes('/build/')
        || url.pathname.includes('/icons/')
        || url.pathname.includes('/media/')
        || url.pathname.endsWith('.webmanifest');

    if (isPosNavigation) {
        event.respondWith((async () => {
            const cache = await caches.open(RUNTIME_CACHE);
            try {
                const response = await fetch(request);
                cache.put(request, response.clone());
                return response;
            } catch (error) {
                const cached = await cache.match(request);
                return cached || caches.match(OFFLINE_URL);
            }
        })());
        return;
    }

    if (isStaticAsset) {
        event.respondWith((async () => {
            const cache = await caches.open(RUNTIME_CACHE);
            const cached = await cache.match(request);
            const networkPromise = fetch(request)
                .then((response) => {
                    cache.put(request, response.clone());
                    return response;
                })
                .catch(() => cached);
            return cached || networkPromise;
        })());
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === SYNC_TAG) {
        event.waitUntil(syncQueuedTickets());
    }
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
        return;
    }
    if (event.data?.type === 'nema-pos-sync-now') {
        event.waitUntil(syncQueuedTickets());
    }
});
