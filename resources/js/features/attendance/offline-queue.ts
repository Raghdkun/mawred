/**
 * Offline punch queue (D70): check-ins tapped without a connection are kept in IndexedDB with
 * the device time they were tapped, and sent later with the device time they were sent so the
 * server can correct for a wrong phone clock.
 */
export type QueuedPunch = {
    id: string;
    kind: 'in' | 'out';
    tapped_at: string;
    latitude?: number;
    longitude?: number;
    accuracy?: number;
    code?: string;
    reason?: string;
    /** The session's XSRF token when it was queued, so the service worker can send it (D70). */
    xsrf?: string;
};

/** The background-sync tag the service worker sends queued punches under. */
export const SYNC_TAG = 'mawred-punches';

const DB = 'mawred-attendance';
const STORE = 'punches';

function open(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB, 1);
        request.onupgradeneeded = () => request.result.createObjectStore(STORE, { keyPath: 'id' });
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('IndexedDB unavailable'));
    });
}

async function tx<T>(
    mode: IDBTransactionMode,
    run: (store: IDBObjectStore) => IDBRequest<T>,
): Promise<T> {
    const db = await open();

    return new Promise((resolve, reject) => {
        const request = run(db.transaction(STORE, mode).objectStore(STORE));
        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error ?? new Error('IndexedDB failed'));
    });
}

export async function queuePunch(punch: QueuedPunch): Promise<void> {
    await tx('readwrite', (store) => store.put(punch));
}

export async function queuedPunches(): Promise<QueuedPunch[]> {
    try {
        const all = await tx<QueuedPunch[]>(
            'readonly',
            (store) => store.getAll() as IDBRequest<QueuedPunch[]>,
        );

        return all.sort((a, b) => a.tapped_at.localeCompare(b.tapped_at));
    } catch {
        return [];
    }
}

export async function forgetPunch(id: string): Promise<void> {
    await tx('readwrite', (store) => store.delete(id));
}

/** A failure that means "no connection" rather than "the server said no". */
export function isNetworkError(error: unknown): boolean {
    return error instanceof TypeError || (typeof navigator !== 'undefined' && !navigator.onLine);
}

/**
 * Sends one queued punch with the time it was tapped and the time it was sent. Works from the
 * page and from the service worker (no `window`), so a punch can go out after the tab closed.
 * Resolves `sent`, `rejected` (the server said no; drop it) or throws on no connection.
 */
export async function sendQueuedPunch(
    url: string,
    punch: QueuedPunch,
    token: string | null,
): Promise<'sent' | 'rejected'> {
    const { id: _id, xsrf: _xsrf, tapped_at: tappedAt, ...rest } = punch;
    const headers = new Headers({
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    });

    if (token) {
        headers.set('X-XSRF-TOKEN', token);
    }

    const response = await fetch(url, {
        method: 'POST',
        headers,
        credentials: 'same-origin',
        body: JSON.stringify({ ...rest, tapped_at: tappedAt, sent_at: new Date().toISOString() }),
    });

    return response.ok ? 'sent' : 'rejected';
}
