import { Head, router } from '@inertiajs/react';
import { QrCode } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import training from '@/routes/training';

type Props = {
    code: string;
    session: { course: string; startsAt: string; timezone: string; location: string | null } | null;
};

/**
 * Checking in from the trainer's QR (D101, handoff 34): the phone camera opens this page; one tap
 * confirms. Codes rotate every 30 seconds, so an old link asks for a fresh scan.
 */
export default function CheckIn({ code, session }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <>
            <Head title={t('checkIn.title')} />
            <div className="mx-auto flex max-w-md flex-col gap-6 py-6">
                {!session ? (
                    <PageState
                        kind="error"
                        title={t('checkIn.expired')}
                        description={t('checkIn.expiredHelp')}
                    />
                ) : (
                    <section
                        className="flex flex-col items-center gap-4 rounded-lg bg-card p-6 text-center shadow-sm"
                        data-check-in
                    >
                        <span className="inline-flex size-14 items-center justify-center rounded-full bg-primary/10 text-primary">
                            <QrCode aria-hidden className="size-7" />
                        </span>
                        <h1 className="text-h3">{session.course}</h1>
                        <p className="text-small text-text-secondary">
                            {formatDate(session.startsAt, locale, {
                                dateStyle: 'full',
                                timeStyle: 'short',
                                timeZone: session.timezone,
                            })}
                            {session.location ? ` · ${session.location}` : ''}
                        </p>
                        {error ? (
                            <p
                                role="alert"
                                className="rounded-md bg-danger-bg p-3 text-small text-danger"
                            >
                                {error}
                            </p>
                        ) : null}
                        <Button
                            className="min-h-11 w-full"
                            disabled={busy}
                            data-confirm-check-in
                            onClick={() => {
                                setBusy(true);
                                router.post(
                                    training.checkIn.confirm.url(),
                                    { code },
                                    {
                                        onError: (e) =>
                                            setError(e.error ?? Object.values(e)[0] ?? null),
                                        onFinish: () => setBusy(false),
                                    },
                                );
                            }}
                        >
                            {t('checkIn.confirm')}
                        </Button>
                    </section>
                )}
            </div>
        </>
    );
}
