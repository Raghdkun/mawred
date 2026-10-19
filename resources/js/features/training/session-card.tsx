import { router } from '@inertiajs/react';
import { CalendarClock, Check, MapPin, Users, Video } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Session = App.Domain.Training.Data.SessionData;

/** When a session is, where, seats left, and the learner's seat or waitlist place (D107). */
export function SessionWhen({ session }: { session: Session }) {
    const { locale } = useLocale();
    const day = formatDate(session.startsAt, locale, {
        dateStyle: 'full',
        timeZone: session.timezone,
    });
    const from = formatDate(session.startsAt, locale, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: session.timezone,
    });
    const to = formatDate(session.endsAt, locale, {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: session.timezone,
    });

    return (
        <span className="inline-flex flex-wrap items-center gap-1.5">
            <CalendarClock aria-hidden className="size-4 shrink-0" />
            {day} · <bdi>{from}</bdi> – <bdi>{to}</bdi>
        </span>
    );
}

export function SessionCard({
    courseId,
    session,
    canBook,
}: {
    courseId: string;
    session: Session;
    canBook: boolean;
}) {
    const { t } = useTranslation('training');
    const [busy, setBusy] = useState(false);
    const mine = session.mine;
    const full = session.capacity !== null && session.booked >= session.capacity;
    const left = session.capacity === null ? null : Math.max(0, session.capacity - session.booked);
    const done = () => setBusy(false);

    return (
        <article
            className={cn(
                'flex flex-col gap-3 rounded-lg border bg-card p-4',
                mine?.status === 'booked' ? 'border-primary' : 'border-border',
            )}
            data-session={session.id}
        >
            <div className="flex flex-col gap-1 text-small">
                <span className="font-medium">
                    <SessionWhen session={session} />
                </span>
                <span className="inline-flex items-center gap-1.5 text-text-secondary">
                    {session.mode === 'online' ? (
                        <Video aria-hidden className="size-4" />
                    ) : (
                        <MapPin aria-hidden className="size-4" />
                    )}
                    {session.mode === 'online' ? (
                        session.meetingUrl ? (
                            <a
                                href={session.meetingUrl}
                                target="_blank"
                                rel="noreferrer noopener"
                                className="text-primary hover:underline"
                            >
                                {t('sessions.joinOnline')}
                            </a>
                        ) : (
                            t('sessions.online')
                        )
                    ) : (
                        (session.location ?? t('sessions.onsite'))
                    )}
                </span>
                <span className="inline-flex items-center gap-1.5 text-text-secondary">
                    <Users aria-hidden className="size-4" />
                    {left === null
                        ? t('sessions.openSeats')
                        : t('sessions.seatsLeft', { count: left })}
                    {session.trainer ? ` · ${t('sessions.with', { name: session.trainer })}` : ''}
                </span>
            </div>
            {mine?.attended ? (
                <span className="inline-flex items-center gap-1.5 self-start rounded-full bg-success-bg px-2 py-0.5 text-caption text-success">
                    <Check aria-hidden className="size-3.5" />
                    {t('sessions.attended')}
                </span>
            ) : mine?.status === 'booked' ? (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-small font-medium text-primary">
                        {t('sessions.booked')}
                    </span>
                    <Button
                        variant="ghost"
                        className="min-h-11"
                        disabled={busy}
                        data-cancel-seat
                        onClick={() => {
                            setBusy(true);
                            router.delete(training.bookings.cancel.url(mine.id), {
                                preserveScroll: true,
                                onFinish: done,
                            });
                        }}
                    >
                        {t('sessions.release')}
                    </Button>
                </div>
            ) : mine?.status === 'waitlisted' ? (
                <div className="flex flex-wrap items-center gap-2">
                    <span className="text-small text-text-secondary">
                        {t('sessions.waitlisted', { position: mine.position ?? 1 })}
                    </span>
                    <Button
                        variant="ghost"
                        className="min-h-11"
                        disabled={busy}
                        onClick={() => {
                            setBusy(true);
                            router.delete(training.bookings.cancel.url(mine.id), {
                                preserveScroll: true,
                                onFinish: done,
                            });
                        }}
                    >
                        {t('sessions.leaveWaitlist')}
                    </Button>
                </div>
            ) : canBook ? (
                <Button
                    variant={full ? 'outline' : 'default'}
                    className="min-h-11 self-start"
                    disabled={busy}
                    data-book-session
                    onClick={() => {
                        setBusy(true);
                        router.post(
                            training.sessions.book.url([courseId, session.id]),
                            {},
                            { preserveScroll: true, onFinish: done },
                        );
                    }}
                >
                    {full ? t('sessions.joinWaitlist') : t('sessions.book')}
                </Button>
            ) : null}
        </article>
    );
}
