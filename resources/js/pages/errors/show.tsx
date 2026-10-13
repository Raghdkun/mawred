import { Head, Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Illustration, type IllustrationName } from '@/components/mw/illustration';
import { Button } from '@/components/ui/button';

const ILLUSTRATIONS: Record<number, IllustrationName> = {
    403: 'system-403',
    404: 'system-404',
    419: 'system-403',
    429: 'system-maintenance',
    500: 'system-500',
    503: 'system-maintenance',
};

/** Error pages with the system illustrations (brief §17 page states). */
export default function ErrorPage({
    status,
    requestId,
}: {
    status: number;
    requestId: string | null;
}) {
    const { t } = useTranslation('errors');
    const key = String(status in ILLUSTRATIONS ? status : 500);

    return (
        <>
            <Head title={t(`${key}.title`)} />
            <div className="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-5 px-4 py-12 text-center">
                <Illustration
                    name={ILLUSTRATIONS[status] ?? 'system-500'}
                    priority
                    className="h-48 w-auto"
                />
                <p className="text-overline text-muted-foreground" dir="ltr">
                    {status}
                </p>
                <h1 className="text-h2">{t(`${key}.title`)}</h1>
                <p className="text-body text-muted-foreground">{t(`${key}.description`)}</p>
                <div className="flex flex-wrap justify-center gap-2">
                    {status === 419 ? (
                        <Button onClick={() => window.location.reload()}>
                            {t('common:actions.retry')}
                        </Button>
                    ) : (
                        <Button asChild>
                            <Link href="/">{t('common:actions.goHome')}</Link>
                        </Button>
                    )}
                </div>
                {requestId ? (
                    <p className="text-caption text-muted-foreground">
                        {t('requestId', { id: '' })}
                        <bdi dir="ltr" className="font-mono">
                            {requestId}
                        </bdi>
                    </p>
                ) : null}
            </div>
        </>
    );
}
