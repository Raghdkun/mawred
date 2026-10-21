import { router } from '@inertiajs/react';
import { BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Section } from '@/components/mw/page-header';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/api';
import notifications from '@/routes/notifications';

type State = 'loading' | 'unsupported' | 'unconfigured' | 'denied' | 'off' | 'on';

function keyBytes(base64: string): Uint8Array<ArrayBuffer> {
    const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4))
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const raw = atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        bytes[i] = raw.charCodeAt(i);
    }

    return bytes;
}

async function registration(): Promise<ServiceWorkerRegistration | null> {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return null;
    }

    return (await navigator.serviceWorker.getRegistration('/')) ?? null;
}

/**
 * Push notifications on this browser or phone (D128): one switch per device. Turning it on asks
 * the browser's permission, subscribes with Mawred's public key and tells the server; turning it
 * off unsubscribes. Which categories push is chosen in the table above.
 */
export function PushCard({ publicKey, devices }: { publicKey: string | null; devices: number }) {
    const { t } = useTranslation('notifications');
    const [state, setState] = useState<State>('loading');
    const [busy, setBusy] = useState(false);

    useEffect(() => {
        void (async () => {
            const reg = await registration();

            if (!reg) {
                setState('unsupported');
            } else if (!publicKey) {
                setState('unconfigured');
            } else if (Notification.permission === 'denied') {
                setState('denied');
            } else {
                setState((await reg.pushManager.getSubscription()) ? 'on' : 'off');
            }
        })();
    }, [publicKey]);

    const turnOn = async () => {
        const reg = await registration();

        if (!reg || !publicKey) {
            return;
        }

        setBusy(true);

        try {
            if ((await Notification.requestPermission()) !== 'granted') {
                setState('denied');

                return;
            }

            const subscription = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: keyBytes(publicKey),
            });
            const json = subscription.toJSON();
            await postJson(notifications.push.subscribe.url(), {
                endpoint: json.endpoint,
                keys: json.keys,
                contentEncoding:
                    (PushManager as unknown as { supportedContentEncodings?: string[] })
                        .supportedContentEncodings?.[0] ?? 'aes128gcm',
            });
            setState('on');
            router.reload({ only: ['push', 'matrix'] });
        } finally {
            setBusy(false);
        }
    };

    const turnOff = async () => {
        const subscription = await (await registration())?.pushManager.getSubscription();
        setBusy(true);

        try {
            if (subscription) {
                await postJson(notifications.push.unsubscribe.url(), {
                    endpoint: subscription.endpoint,
                });
                await subscription.unsubscribe();
            }

            setState('off');
            router.reload({ only: ['push', 'matrix'] });
        } finally {
            setBusy(false);
        }
    };

    return (
        <Section title={t('push.title')} description={t('push.help')}>
            <div className="flex flex-wrap items-center gap-3" data-push-card={state}>
                <BellRing aria-hidden className="size-5 shrink-0 text-text-secondary" />
                <p className="flex-1 text-small" aria-live="polite">
                    {t(`push.state.${state}`)}
                    {devices > 0 ? ` ${t('push.devices', { count: devices })}` : ''}
                </p>
                {state === 'off' ? (
                    <Button
                        className="min-h-11"
                        onClick={() => void turnOn()}
                        disabled={busy}
                        data-push-on
                    >
                        {t('push.turnOn')}
                    </Button>
                ) : state === 'on' ? (
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => void turnOff()}
                        disabled={busy}
                        data-push-off
                    >
                        {t('push.turnOff')}
                    </Button>
                ) : null}
            </div>
        </Section>
    );
}
