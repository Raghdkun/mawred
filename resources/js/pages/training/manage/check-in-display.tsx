import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import training from '@/routes/training';

type Props = {
    course: App.Domain.Training.Data.CourseRefData;
    session: App.Domain.Training.Data.SessionData;
};
type Code = { url: string; svg: string; refreshIn: number; attended: number };

/**
 * The check-in QR the trainer puts on the screen (D101): it changes every 30 seconds so a photo
 * of it is useless later, and the count of people checked in updates as they scan.
 */
export default function CheckInDisplay({ course, session }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [code, setCode] = useState<Code | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        let timer: ReturnType<typeof setTimeout>;
        let alive = true;

        const load = async () => {
            try {
                const response = await fetch(training.manage.sessions.code.url(session.id), {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) throw new Error(String(response.status));

                const body = (await response.json()) as Code;

                if (!alive) return;

                setCode(body);
                setFailed(false);
                timer = setTimeout(load, Math.max(1, body.refreshIn) * 1000);
            } catch {
                if (!alive) return;

                setFailed(true);
                timer = setTimeout(load, 5000);
            }
        };

        void load();

        return () => {
            alive = false;
            clearTimeout(timer);
        };
    }, [session.id]);

    return (
        <>
            <Head title={t('display.title')} />
            <Link
                href={training.manage.sessions.show.url(session.id)}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('roster.title')}
            </Link>
            <div
                className="mx-auto flex max-w-xl flex-col items-center gap-6 text-center"
                data-check-in-display
            >
                <div className="flex flex-col gap-1">
                    <h1 className="text-h2">{course.title}</h1>
                    <p className="text-body text-text-secondary">{t('display.help')}</p>
                </div>
                <div
                    className="flex aspect-square w-full max-w-sm items-center justify-center rounded-lg bg-white p-4 shadow-sm [&_svg]:h-full [&_svg]:w-full"
                    role="img"
                    aria-label={t('display.qr')}
                    // The SVG comes from our own QR renderer (no user content).
                    dangerouslySetInnerHTML={code ? { __html: code.svg } : undefined}
                />
                {failed ? (
                    <p role="alert" className="text-small text-danger">
                        {t('display.offline')}
                    </p>
                ) : null}
                <p className="text-h3 tabular-nums" aria-live="polite" data-checked-in-count>
                    {t('display.count', {
                        count: code?.attended ?? session.attended,
                        formatted: formatNumber(code?.attended ?? session.attended, locale),
                    })}
                </p>
            </div>
        </>
    );
}
