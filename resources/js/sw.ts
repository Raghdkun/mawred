/// <reference lib="webworker" />
import PunchesApi from '@/actions/App/Http/Controllers/Api/V1/Attendance/PunchesController';
import {
    forgetPunch,
    queuedPunches,
    sendQueuedPunch,
    SYNC_TAG,
} from '@/features/attendance/offline-queue';

/**
 * Mawred's service worker (D70, D128): installable app, offline check-in, push notifications and
 * background sending of queued punches. Only the app shell, built assets and the check-in page
 * are cached; HR data is never cached (shared-device risk, architecture §PWA).
 */
const sw = self as unknown as ServiceWorkerGlobalScope;

/** The built CSS to precache; vite-plugin-pwa replaces `self.__WB_MANIFEST` at build time. */
const MANIFEST = (
    self as unknown as { __WB_MANIFEST: Array<{ url: string; revision: string | null }> }
).__WB_MANIFEST;

const PRECACHE = 'mawred-precache';
const ASSETS = 'mawred-assets';
const CHECK_IN = 'mawred-check-in';
const KEEP = new Set([PRECACHE, ASSETS, CHECK_IN]);
const MAX_ASSETS = 300;

sw.addEventListener('install', (event) => {
    const urls = MANIFEST.map((entry) => entry.url);
    event.waitUntil(caches.open(PRECACHE).then((cache) => cache.addAll(urls)));
});

sw.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((names) =>
                Promise.all(
                    names.filter((name) => !KEEP.has(name)).map((name) => caches.delete(name)),
                ),
            )
            .then(() => sw.clients.claim()),
    );
});

// The page asks a waiting worker to take over when the person taps "Refresh" on the update toast.
sw.addEventListener('message', (event) => {
    if ((event.data as { type?: string } | null)?.type === 'SKIP_WAITING') {
        void sw.skipWaiting();
    }
});

async function trim(cacheName: string, max: number): Promise<void> {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();

    for (const request of keys.slice(0, Math.max(0, keys.length - max))) {
        await cache.delete(request);
    }
}

async function cacheFirst(request: Request): Promise<Response> {
    const cached = (await caches.match(request)) ?? null;

    if (cached) {
        return cached;
    }

    const response = await fetch(request);

    if (response.ok) {
        const cache = await caches.open(ASSETS);
        await cache.put(request, response.clone());
        void trim(ASSETS, MAX_ASSETS);
    }

    return response;
}

async function networkFirst(request: Request, timeoutMs: number): Promise<Response> {
    const cache = await caches.open(CHECK_IN);

    try {
        const response = await Promise.race([
            fetch(request),
            new Promise<never>((_, reject) =>
                setTimeout(() => reject(new Error('timeout')), timeoutMs),
            ),
        ]);

        if (response.ok) {
            await cache.put(request, response.clone());
        }

        return response;
    } catch (error) {
        const cached = await cache.match(request);

        if (cached) {
            return cached;
        }

        throw error;
    }
}

sw.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== sw.location.origin) {
        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(cacheFirst(request));
    } else if (request.mode === 'navigate' && url.pathname === '/attendance') {
        event.respondWith(networkFirst(request, 4000));
    }
});

// Push notifications (D128): the same title and text as the in-app notice, opening its page.
sw.addEventListener('push', (event) => {
    let payload: { title?: string; body?: string | null; url?: string | null; tag?: string } = {};

    try {
        payload = (event.data?.json() as typeof payload | undefined) ?? {};
    } catch {
        payload = { title: event.data?.text() };
    }

    event.waitUntil(
        sw.registration.showNotification(payload.title ?? 'Mawred', {
            body: payload.body ?? undefined,
            tag: payload.tag,
            icon: '/icons/pwa-icon-192.png',
            badge: '/icons/pwa-icon-192.png',
            data: { url: payload.url ?? '/' },
        }),
    );
});

sw.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL(
        (event.notification.data as { url?: string } | null)?.url ?? '/',
        sw.location.origin,
    );

    // Only open pages of this app, whatever the payload says.
    if (target.origin !== sw.location.origin) {
        return;
    }

    event.waitUntil(
        sw.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
            const open = windows.find(
                (client) => new URL(client.url).origin === sw.location.origin,
            );

            return open
                ? open.navigate(target.href).then((client) => client?.focus())
                : sw.clients.openWindow(target.href);
        }),
    );
});

// Background sync (D70): send check-ins tapped offline once the phone is online again.
sw.addEventListener('sync', ((
    event: Event & { tag: string; waitUntil: (p: Promise<unknown>) => void },
) => {
    if (event.tag !== SYNC_TAG) {
        return;
    }

    event.waitUntil(
        (async () => {
            for (const punch of await queuedPunches()) {
                // A network error rejects, so the browser retries the sync later.
                await sendQueuedPunch(PunchesApi.store.url(), punch, punch.xsrf ?? null);
                await forgetPunch(punch.id);
            }

            for (const client of await sw.clients.matchAll({ type: 'window' })) {
                client.postMessage({ type: 'punches-synced' });
            }
        })(),
    );
}) as EventListener);
