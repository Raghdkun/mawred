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
};

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
