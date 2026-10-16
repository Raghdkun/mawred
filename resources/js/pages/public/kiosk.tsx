import { Head } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import kiosk from '@/routes/kiosk';

type Props = { name: string; branch: Record<string, string>; token: string };
type Code = { code: string; svg: string; refreshIn: number };

/**
 * The kiosk screen (D70): a QR code that changes every 20 seconds, with the time. It polls for the
 * next code; people scan it from Check in → Scan kiosk code.
 */
export default function KioskScreen({ name, branch, token }: Props) {
    const { t } = useTranslation('attendance');
    const { locale } = useLocale();
    const [code, setCode] = useState<Code | null>(null);
    const [failed, setFailed] = useState(false);
    const [now, setNow] = useState(() => new Date());
    const [left, setLeft] = useState(0);

    useEffect(() => {
        let timer: number | undefined;
        const load = async () => {
            try {
                const response = await fetch(kiosk.code.url(token), { headers: { Accept: 'application/json' }, cache: 'no-store' });
                if (!response.ok) throw new Error(String(response.status));
                const body = (await response.json()) as Code;
                setCode(body);
                setLeft(body.refreshIn);
                setFailed(false);
                timer = window.setTimeout(load, body.refreshIn * 1000);
            } catch {
                setFailed(true);
                timer = window.setTimeout(load, 5000);
            }
        };
        void load();

        return () => window.clearTimeout(timer);
    }, [token]);

    useEffect(() => {
        const tick = window.setInterval(() => {
            setNow(new Date());
            setLeft((s) => Math.max(0, s - 1));
        }, 1000);

        return () => window.clearInterval(tick);
    }, []);

    return (
        <>
            <Head title={t('kiosk.title')} />
            <div className="flex flex-1 flex-col items-center justify-center gap-6 p-6 text-center">
                <div className="flex flex-col gap-1">
                    <h1 className="text-h2">{t('kiosk.title')}</h1>
                    <p className="text-body text-text-secondary">
                        {localized(branch, locale)} · {name}
                    </p>
                </div>
                <div className="rounded-xl bg-card p-6 shadow-md" data-kiosk-code={code?.code ?? ''}>
                    {code ? (
                        <div aria-label={t('kiosk.title')} role="img" className="size-72 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: code.svg }} />
                    ) : (
                        <div className="size-72 animate-pulse rounded-md bg-muted motion-reduce:animate-none" />
                    )}
                </div>
                <p className="text-h3 tabular-nums" aria-live="off">
                    {formatDate(now, locale, { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                </p>
                <p className="text-small text-text-secondary">{failed ? t('kiosk.error') : t('kiosk.refresh', { seconds: formatNumber(left, locale) })}</p>
                <p className="max-w-md text-small text-text-secondary">{t('kiosk.help')}</p>
            </div>
        </>
    );
}
