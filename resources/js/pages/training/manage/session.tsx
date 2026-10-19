import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, QrCode } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { SessionWhen } from '@/features/training/session-card';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import training from '@/routes/training';

type Props = {
    course: App.Domain.Training.Data.CourseRefData;
    session: App.Domain.Training.Data.SessionData;
    roster: App.Domain.Training.Data.RosterRowData[];
};

/** A session's roster for the trainer (D101, D107): seats and waitlist, attendance ticks, and the check-in QR. */
export default function SessionRoster({ course, session, roster }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const seated = roster.filter((r) => r.status === 'booked');
    const waiting = roster.filter((r) => r.status === 'waitlisted');

    return (
        <>
            <Head title={`${t('roster.title')} · ${course.title}`} />
            <Link
                href={training.manage.courses.edit.url(course.id, { query: { tab: 'sessions' } })}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {course.title}
            </Link>
            <PageHeader
                title={t('roster.title')}
                description={undefined}
                actions={
                    session.status === 'scheduled' ? (
                        <Button asChild className="min-h-11">
                            <Link
                                href={training.manage.sessions.display.url(session.id)}
                                data-show-qr
                            >
                                <QrCode aria-hidden />
                                {t('roster.showQr')}
                            </Link>
                        </Button>
                    ) : null
                }
            />
            <p className="mb-4 text-small text-text-secondary">
                <SessionWhen session={session} />
            </p>
            <p className="mb-6 text-small text-text-secondary" data-roster-counts>
                {t('roster.counts', {
                    booked: formatNumber(session.booked, locale),
                    attended: formatNumber(session.attended, locale),
                    waiting: formatNumber(session.waitlisted, locale),
                })}
            </p>
            {roster.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-trainings"
                    title={t('roster.empty')}
                    description={t('roster.emptyHelp')}
                />
            ) : (
                <div className="flex flex-col gap-6">
                    <ul
                        className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                        aria-label={t('roster.seated')}
                    >
                        {seated.map((row) => {
                            const id = `attended-${row.id}`;

                            return (
                                <li
                                    key={row.id}
                                    className="flex items-center gap-3 p-3"
                                    data-roster-row={row.id}
                                >
                                    <Checkbox
                                        id={id}
                                        checked={row.attendedAt !== null}
                                        onCheckedChange={(on) =>
                                            router.put(
                                                training.manage.bookings.attendance.url(row.id),
                                                { attended: on === true },
                                                { preserveScroll: true },
                                            )
                                        }
                                        className="size-5"
                                        data-attended
                                    />
                                    <label
                                        htmlFor={id}
                                        className="flex min-h-11 min-w-0 flex-1 cursor-pointer flex-col justify-center"
                                    >
                                        <span className="text-body">{row.name}</span>
                                        <span className="text-caption text-text-secondary">
                                            {row.attendedAt
                                                ? t(
                                                      row.checkIn === 'qr'
                                                          ? 'roster.byQr'
                                                          : 'roster.byHand',
                                                      {
                                                          time: formatDate(row.attendedAt, locale, {
                                                              timeStyle: 'short',
                                                              timeZone: session.timezone,
                                                          }),
                                                      },
                                                  )
                                                : t('roster.notYet')}
                                        </span>
                                    </label>
                                </li>
                            );
                        })}
                    </ul>
                    {waiting.length > 0 ? (
                        <section className="flex flex-col gap-2">
                            <h2 className="text-h4">{t('roster.waitlist')}</h2>
                            <ol className="list-decimal ps-6 text-small text-text-secondary">
                                {waiting.map((row) => (
                                    <li key={row.id}>{row.name}</li>
                                ))}
                            </ol>
                        </section>
                    ) : null}
                </div>
            )}
        </>
    );
}
