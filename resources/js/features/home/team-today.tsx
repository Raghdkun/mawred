import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { formatValue } from '@/features/reports/format';
import { useInitials } from '@/hooks/use-initials';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { KpiCard } from './kpi-card';
import { useCountUp } from './use-count-up';

type Team = App.Domain.Reports.Data.ManagerDashboardData;

function Count({ value, label, kind }: { value: number; label: string; kind: string }) {
    const shown = useCountUp(value);
    const { locale } = useLocale();

    return (
        <div className="flex flex-col gap-0.5 rounded-md bg-muted/60 p-3" data-team-count={kind}>
            <dt className="text-caption text-text-secondary">{label}</dt>
            <dd className="text-h3 tabular-nums">{formatNumber(shown, locale)}</dd>
        </div>
    );
}

/**
 * A manager's team today (handoff 5, D123): the availability strip (in, late, away, not in yet,
 * and who's away), lateness alerts, and two KPI cards.
 */
export function TeamToday({ team }: { team: Team }) {
    const { t } = useTranslation('home');
    const { t: tr } = useTranslation('reports');
    const { locale } = useLocale();
    const getInitials = useInitials();

    return (
        <div className="flex flex-col gap-6" data-team-today>
            <section
                aria-labelledby="team-today"
                className="rounded-lg border border-border bg-card p-5 shadow-sm"
            >
                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 id="team-today" className="text-h4">
                        {t('team.title')}
                    </h2>
                    <span className="text-small text-text-secondary">
                        {t('team.date', {
                            date: formatDate(`${team.date}T00:00:00Z`, locale, {
                                dateStyle: 'full',
                                timeZone: 'UTC',
                            }),
                        })}
                    </span>
                </div>
                <dl className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <Count kind="in" value={team.today.in} label={t('team.in')} />
                    <Count kind="late" value={team.today.late} label={t('team.late')} />
                    <Count kind="away" value={team.today.away} label={t('team.away')} />
                    <Count kind="notIn" value={team.today.notIn} label={t('team.notIn')} />
                </dl>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                    <div>
                        <h3 className="mb-2 text-small font-medium">{t('team.awayTitle')}</h3>
                        {team.away.length === 0 ? (
                            <p className="text-small text-text-secondary">{t('team.awayEmpty')}</p>
                        ) : (
                            <ul className="flex flex-col gap-2" data-team-away>
                                {team.away.map((person) => (
                                    <li key={person.id} className="flex items-center gap-2">
                                        <Avatar className="size-8">
                                            <AvatarFallback className="text-caption">
                                                {getInitials(person.name)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <span className="flex min-w-0 flex-col">
                                            <span className="truncate text-small">
                                                <bdi>{person.name}</bdi>
                                            </span>
                                            {person.until ? (
                                                <span className="text-caption text-text-secondary">
                                                    {t('team.until', {
                                                        date: formatDate(
                                                            `${person.until}T00:00:00Z`,
                                                            locale,
                                                            {
                                                                day: 'numeric',
                                                                month: 'short',
                                                                timeZone: 'UTC',
                                                            },
                                                        ),
                                                    })}
                                                </span>
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                    <div>
                        <h3 className="mb-2 text-small font-medium">{t('team.lateTitle')}</h3>
                        {team.late.length === 0 ? (
                            <p className="text-small text-text-secondary">{t('team.lateEmpty')}</p>
                        ) : (
                            <ul className="flex flex-col gap-2" data-team-late>
                                {team.late.map((person) => (
                                    <li
                                        key={person.id}
                                        className="flex items-center justify-between gap-2 rounded-md border border-warning-border bg-warning-bg px-3 py-2 text-small"
                                    >
                                        <bdi className="truncate">{person.name}</bdi>
                                        <span className="shrink-0 text-warning">
                                            {t('team.lateBy', {
                                                minutes: formatValue(
                                                    person.minutes,
                                                    'minutes',
                                                    locale,
                                                    tr,
                                                ),
                                            })}
                                            {person.firstIn ? (
                                                <>
                                                    {' · '}
                                                    <bdi>
                                                        {t('team.arrived', {
                                                            time: formatDate(
                                                                person.firstIn,
                                                                locale,
                                                                {
                                                                    hour: 'numeric',
                                                                    minute: '2-digit',
                                                                    timeZone: person.timezone,
                                                                },
                                                            ),
                                                        })}
                                                    </bdi>
                                                </>
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
                {team.attendanceHref ? (
                    <Link
                        href={team.attendanceHref}
                        className="mt-3 inline-flex min-h-11 items-center text-small text-link hover:underline"
                    >
                        {t('team.board')}
                    </Link>
                ) : null}
            </section>
            <ul className="grid gap-3 sm:grid-cols-2">
                {team.kpis.map((kpi) => (
                    <li key={kpi.key}>
                        <KpiCard kpi={kpi} />
                    </li>
                ))}
            </ul>
        </div>
    );
}
