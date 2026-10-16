import { Head, router } from '@inertiajs/react';
import { CalendarCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import careers from '@/routes/careers';

type Props = {
    interview: {
        title: string;
        job: string;
        durationMinutes: number;
        mode: string;
        timezone: string;
        status: string;
        startsAt: string | null;
    };
    slots: string[];
    open: boolean;
    token: string;
};

/**
 * Picking an interview time (D89): times the whole panel is free over the next two weeks, grouped
 * by day in the interview's time zone. No account needed; the link is the authority.
 */
export default function InterviewBooking({ interview, slots, open, token }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [picked, setPicked] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);
    const tz = interview.timezone;
    const day = (iso: string) => formatDate(iso, 'en', { year: 'numeric', month: '2-digit', day: '2-digit', timeZone: tz });
    const days = [...new Set(slots.map(day))];

    if (interview.status === 'scheduled' && interview.startsAt) {
        return (
            <div className="mx-auto flex w-full max-w-[640px] flex-col items-center gap-4 px-4 py-12 text-center" data-booked>
                <Head title={interview.title} />
                <Illustration name="success-submitted" className="w-56" />
                <h1 className="text-h2">{t('booking.booked')}</h1>
                <p className="text-body text-text-secondary">
                    {formatDate(interview.startsAt, locale, { dateStyle: 'full', timeStyle: 'short', timeZone: tz })} ({tz})
                </p>
                <p className="text-small text-text-secondary">{t('booking.bookedHelp')}</p>
            </div>
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 px-4 py-8">
            <Head title={interview.title} />
            <header className="flex flex-col gap-1">
                <h1 className="text-h2">{t('booking.title')}</h1>
                <p className="text-body text-text-secondary">
                    {interview.job} · {interview.title} · {t('interviews.minutes', { count: interview.durationMinutes })} · {t(`interviewMode.${interview.mode}`)}
                </p>
                <p className="text-caption text-text-secondary">{t('booking.timezone', { zone: tz })}</p>
            </header>
            {!open ? (
                <PageState kind="empty" illustration="empty-schedule" title={t('booking.closed')} />
            ) : slots.length === 0 ? (
                <PageState kind="empty" illustration="empty-schedule" title={t('booking.noSlots')} description={t('booking.noSlotsHelp')} />
            ) : (
                <>
                    <div className="flex flex-col gap-4" data-slots>
                        {days.map((d) => {
                            const daySlots = slots.filter((s) => day(s) === d);

                            return (
                                <section key={d} aria-label={formatDate(daySlots[0]!, locale, { dateStyle: 'full', timeZone: tz })} className="flex flex-col gap-2">
                                    <h2 className="text-small font-medium">{formatDate(daySlots[0]!, locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: tz })}</h2>
                                    <ul className="flex flex-wrap gap-2">
                                        {daySlots.map((s) => (
                                            <li key={s}>
                                                <button
                                                    type="button"
                                                    aria-pressed={picked === s}
                                                    onClick={() => setPicked(s)}
                                                    className={cn(
                                                        'inline-flex min-h-11 items-center rounded-md border px-4 text-small tabular-nums',
                                                        picked === s ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:bg-muted',
                                                    )}
                                                    data-booking-slot={s}
                                                >
                                                    <bdi dir="ltr">{formatDate(s, locale, { hour: '2-digit', minute: '2-digit', timeZone: tz })}</bdi>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            );
                        })}
                    </div>
                    {error ? (
                        <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                            {error}
                        </p>
                    ) : null}
                    <Button
                        className="min-h-11 self-start"
                        disabled={!picked || sending}
                        data-confirm-slot
                        onClick={() => {
                            if (!picked) return;
                            setSending(true);
                            router.post(careers.interviews.book.url(token), { start: picked }, {
                                onError: (e) => setError(e.error ?? e.start ?? null),
                                onFinish: () => setSending(false),
                            });
                        }}
                    >
                        <CalendarCheck aria-hidden />
                        {picked
                            ? t('booking.confirm', { when: formatDate(picked, locale, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: tz }) })
                            : t('booking.pick')}
                    </Button>
                </>
            )}
        </div>
    );
}
