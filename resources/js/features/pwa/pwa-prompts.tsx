import { Download, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { applyUpdate, onUpdateReady } from './service-worker';

type InstallEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: string }>;
};

const DISMISSED = 'mawred:install-dismissed';

function dismissedRecently(): boolean {
    try {
        const at = Number(localStorage.getItem(DISMISSED) ?? 0);

        return Date.now() - at < 30 * 24 * 60 * 60 * 1000;
    } catch {
        return false;
    }
}

/**
 * The shell's PWA prompts: a toast with "Refresh" when a new version is ready, and an "Install
 * Mawred" card when the browser offers installation (hidden for 30 days after "Not now").
 */
export function PwaPrompts() {
    const { t } = useTranslation('shell');
    const [install, setInstall] = useState<InstallEvent | null>(null);

    useEffect(
        () =>
            onUpdateReady((registration) => {
                toast.info(t('pwa.updateReady'), {
                    id: 'pwa-update',
                    duration: Infinity,
                    action: { label: t('pwa.refresh'), onClick: () => applyUpdate(registration) },
                });
            }),
        [t],
    );

    useEffect(() => {
        const offer = (event: Event) => {
            event.preventDefault();

            if (!dismissedRecently()) {
                setInstall(event as InstallEvent);
            }
        };
        const installed = () => setInstall(null);
        window.addEventListener('beforeinstallprompt', offer);
        window.addEventListener('appinstalled', installed);

        return () => {
            window.removeEventListener('beforeinstallprompt', offer);
            window.removeEventListener('appinstalled', installed);
        };
    }, []);

    if (!install) {
        return null;
    }

    const dismiss = () => {
        try {
            localStorage.setItem(DISMISSED, String(Date.now()));
        } catch {
            // Private mode: it just shows again next time.
        }

        setInstall(null);
    };

    return (
        <div
            role="region"
            aria-label={t('pwa.installTitle')}
            className="fixed inset-x-4 bottom-24 z-40 flex items-center gap-3 rounded-lg border border-border bg-card p-3 shadow-lg md:inset-x-auto md:end-6 md:bottom-6 md:max-w-sm"
            data-install-prompt
        >
            <Download aria-hidden className="size-5 shrink-0 text-primary" />
            <div className="flex min-w-0 flex-1 flex-col">
                <span className="text-small font-medium">{t('pwa.installTitle')}</span>
                <span className="text-caption text-text-secondary">{t('pwa.installHelp')}</span>
            </div>
            <Button
                className="min-h-11"
                onClick={() => {
                    void install.prompt().finally(() => setInstall(null));
                }}
            >
                {t('pwa.install')}
            </Button>
            <Button
                variant="ghost"
                size="icon"
                className="size-11"
                aria-label={t('pwa.notNow')}
                onClick={dismiss}
            >
                <X aria-hidden />
            </Button>
        </div>
    );
}
