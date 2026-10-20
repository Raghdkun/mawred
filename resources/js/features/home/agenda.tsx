import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PageState } from '@/components/mw/page-state';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import calendarRoutes from '@/routes/calendar';

type Item = App.Domain.Reports.Data.EmployeeDashboardData['agenda'][number];

/** The next seven days (handoff 4): shifts, meetings, interviews, training, time away, holidays. */
export function Agenda({ items, today }: { items: Item[]; today: string | null }) {
    const { t } = useTranslation('home');
    const { locale } = useLocale();
    const days = [...new Set(items.map((i) => i.date))];
    const tomorrow = today
        ? new Date(Date.parse(`${today}T00:00:00Z`) + 86_400_000).toISOString().slice(0, 10)
        : null;
    const dayLabel = (date: string) =>
        date === today
            ? t('agendaItems.today')
            : date === tomorrow
              ? t('agendaItems.tomorrow')
              : formatDate(`${date}T00:00:00Z`, locale, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'short',
                    timeZone: 'UTC',
                });
    const time = (item: Item) => {
        if (!item.startsAt) {
            return item.kind === 'leave' && item.endsAt
                ? t('agendaItems.until', {
                      date: formatDate(`${item.endsAt}T00:00:00Z`, locale, {
                          day: 'numeric',
                          month: 'short',
                          timeZone: 'UTC',
                      }),
                  })
                : t('agendaItems.allDay');
        }

        const options = {
            hour: 'numeric',
            minute: '2-digit',
            ...(item.timezone ? { timeZone: item.timezone } : {}),
        } as const;

        return `${formatDate(item.startsAt, locale, options)}–${item.endsAt ? formatDate(item.endsAt, locale, options) : ''}`;
    };

    return (
        <section
            aria-labelledby="agenda"
            className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight"
            data-agenda
        >
            <div className="mb-3 flex items-center justify-between gap-2">
                <h2 id="agenda" className="text-h4">
                    {t('agenda.title')}
                </h2>
                <Link
                    href={calendarRoutes.index.url()}
                    className="inline-flex min-h-11 items-center text-small text-link hover:underline"
                >
                    {t('agendaItems.calendar')}
                </Link>
            </div>
            {items.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('agenda.emptyTitle')}
                    description={t('agenda.emptyDescription')}
                    className="py-6"
                />
            ) : (
                <ol className="flex flex-col gap-4">
                    {days.map((day) => (
                        <li key={day}>
                            <h3 className="mb-1 text-caption font-medium text-text-secondary uppercase">
                                {dayLabel(day)}
                            </h3>
                            <ul className="flex flex-col divide-y divide-border">
                                {items
                                    .filter((i) => i.date === day)
                                    .map((item) => (
                                        <li
                                            key={item.key}
                                            className="flex items-center gap-3 py-2"
                                            data-agenda-item={item.kind}
                                        >
                                            <span className="w-28 shrink-0 text-small text-text-secondary tabular-nums">
                                                <bdi>{time(item)}</bdi>
                                            </span>
                                            <span className="flex min-w-0 flex-col">
                                                <span className="truncate text-small font-medium">
                                                    <bdi>
                                                        {item.title ??
                                                            t(`agendaItems.${item.kind}`)}
                                                    </bdi>
                                                </span>
                                                {item.title ? (
                                                    <span className="text-caption text-text-secondary">
                                                        {t(`agendaItems.${item.kind}`)}
                                                    </span>
                                                ) : null}
                                            </span>
                                        </li>
                                    ))}
                            </ul>
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
