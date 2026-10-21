/**
 * Registers Mawred's service worker (production builds only) and tells the page when a new
 * version is waiting, so the shell can offer "Refresh" instead of updating under someone's feet.
 */
type Listener = (registration: ServiceWorkerRegistration) => void;

const listeners = new Set<Listener>();
let waiting: ServiceWorkerRegistration | null = null;

function announce(registration: ServiceWorkerRegistration): void {
    waiting = registration;
    listeners.forEach((listener) => listener(registration));
}

export function onUpdateReady(listener: Listener): () => void {
    listeners.add(listener);

    if (waiting) {
        listener(waiting);
    }

    return () => listeners.delete(listener);
}

/** Switch to the waiting version and reload once it has taken over. */
export function applyUpdate(registration: ServiceWorkerRegistration): void {
    navigator.serviceWorker.addEventListener('controllerchange', () => window.location.reload(), {
        once: true,
    });
    registration.waiting?.postMessage({ type: 'SKIP_WAITING' });
}

export function registerServiceWorker(): void {
    if (!import.meta.env.PROD || !('serviceWorker' in navigator)) {
        return;
    }

    void navigator.serviceWorker
        .register('/sw.js', { scope: '/' })
        .then((registration) => {
            // A version already waiting from an earlier visit.
            if (registration.waiting && navigator.serviceWorker.controller) {
                announce(registration);
            }

            registration.addEventListener('updatefound', () => {
                const worker = registration.installing;

                worker?.addEventListener('statechange', () => {
                    // Only an update, not the very first install, needs a refresh.
                    if (worker.state === 'installed' && navigator.serviceWorker.controller) {
                        announce(registration);
                    }
                });
            });

            // Look for a new version when the person comes back to the app.
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    void registration.update().catch(() => undefined);
                }
            });
        })
        .catch(() => undefined);
}
