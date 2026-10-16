import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeftRight, ChevronLeft, ChevronRight, LayoutGrid } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { addDays, daysBetween, parseDay, shiftColor, weekdayIso } from '@/features/schedule/colors';
import { ShiftTime } from '@/features/schedule/shift-time';
import { dayLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import roster from '@/routes/roster';
import requests from '@/routes/requests';
import schedule from '@/routes/schedule';

type Shift = App.Domain.Scheduling.Data.MyShiftData;
type Props = {
    employeeId: string | null;
    today: string;
    month: string;
    shifts: Shift[];
    openShifts: Shift[];
    swapTypeId: string | null;
    can: { plan: boolean };
};

/**
 * My schedule (handoff 23): an agenda of my published shifts grouped by day, with a month view
 * beside it on desktop (toggle on phones), open shifts to ask for, and swap requests.
 */
export default function MySchedule({
    employeeId,
    today,
    month,
    shifts,
    openShifts,
    swapTypeId,
    can,
}: Props) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const [view, setView] = useState<'agenda' | 'month'>('agenda');
    const first = `${month}-01`;
    const go = (target: string) =>
        router.get(
            schedule.mine.url({ query: { month: target } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const shift = (by: number) => {
        const d = parseDay(first);
        d.setUTCMonth(d.getUTCMonth() + by);
        go(d.toISOString().slice(0, 7));
    };
    const byDay = new Map<string, Shift[]>();

    for (const s of shifts) {
        byDay.set(s.date, [...(byDay.get(s.date) ?? []), s]);
    }

    if (!employeeId) {
        return (
            <>
                <Head title={t('mine.title')} />
                <PageHeader title={t('mine.title')} />
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('mine.title')}
                    description={t('mine.noEmployee')}
                />
            </>
        );
    }

    return (
        <>
            <Head title={t('mine.title')} />
            <PageHeader
                title={t('mine.title')}
                description={t('mine.description')}
                actions={
                    can.plan ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={roster.show.url()}>
                                <LayoutGrid aria-hidden className="size-4" />
                                {t('mine.planner')}
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex flex-wrap items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11"
                        aria-label={t('mine.previous')}
                        onClick={() => shift(-1)}
                    >
                        <DirIcon>
                            <ChevronLeft aria-hidden className="size-4" />
                        </DirIcon>
                    </Button>
                    <h2 aria-live="polite" className="min-w-36 text-center text-h4">
                        {formatDate(parseDay(first), locale, {
                            month: 'long',
                            year: 'numeric',
                            timeZone: 'UTC',
                        })}
                    </h2>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11"
                        aria-label={t('mine.next')}
                        onClick={() => shift(1)}
                    >
                        <DirIcon>
                            <ChevronRight aria-hidden className="size-4" />
                        </DirIcon>
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="ms-2 min-h-11"
                        onClick={() => go(today.slice(0, 7))}
                    >
                        {t('mine.today')}
                    </Button>
                </div>
                <ToggleGroup
                    type="single"
                    value={view}
                    onValueChange={(v) => v && setView(v as 'agenda' | 'month')}
                    className="lg:hidden"
                    aria-label={t('mine.title')}
                >
                    <ToggleGroupItem value="agenda" className="min-h-11 px-4">
                        {t('mine.agenda')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="month" className="min-h-11 px-4">
                        {t('mine.month')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div className="grid gap-6 lg:grid-cols-12">
                <section
                    aria-label={t('mine.month')}
                    className={cn('lg:col-span-7', view === 'agenda' && 'hidden lg:block')}
                >
                    <MonthGrid first={first} today={today} byDay={byDay} />
                </section>

                <section
                    aria-label={t('mine.agenda')}
                    className={cn(
                        'flex flex-col gap-3 lg:col-span-5',
                        view === 'month' && 'hidden lg:flex',
                    )}
                    data-schedule-agenda
                >
                    {shifts.length === 0 ? (
                        <PageState
                            kind="empty"
                            illustration="empty-schedule"
                            title={t('mine.empty')}
                            description={t('mine.emptyHelp')}
                        />
                    ) : (
                        <ol className="flex flex-col gap-3">
                            {[...byDay.entries()].map(([date, dayShifts]) => (
                                <li
                                    key={date}
                                    className={cn(
                                        'rounded-lg border border-border bg-card p-3',
                                        date === today && 'border-primary',
                                    )}
                                >
                                    <h3 className="mb-2 text-small font-medium">
                                        {dayLabel(date, locale, {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                        })}
                                    </h3>
                                    <ul className="flex flex-col gap-2">
                                        {dayShifts.map((s) => (
                                            <li
                                                key={s.id}
                                                data-my-shift={s.id}
                                                className={cn(
                                                    'flex flex-wrap items-center gap-3 rounded-md border-s-4 p-3',
                                                    shiftColor(s.color).block,
                                                )}
                                            >
                                                <div className="flex min-w-0 flex-1 flex-col">
                                                    <span className="font-medium">
                                                        {s.name
                                                            ? localized(s.name, locale)
                                                            : t('shift.custom')}
                                                    </span>
                                                    <span className="text-small text-text-secondary">
                                                        <ShiftTime
                                                            startsAt={s.startsAt}
                                                            endsAt={s.endsAt}
                                                        />{' '}
                                                        · {localized(s.branch, locale)}
                                                    </span>
                                                </div>
                                                {swapTypeId && date >= today ? (
                                                    <Button
                                                        asChild
                                                        variant="ghost"
                                                        size="sm"
                                                        className="min-h-11"
                                                    >
                                                        <Link
                                                            href={requests.create.url(swapTypeId)}
                                                        >
                                                            <ArrowLeftRight
                                                                aria-hidden
                                                                className="size-4"
                                                            />
                                                            {t('mine.swap')}
                                                        </Link>
                                                    </Button>
                                                ) : null}
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                        </ol>
                    )}

                    <section aria-labelledby="open-shifts" className="mt-3 flex flex-col gap-2">
                        <h2 id="open-shifts" className="text-h4">
                            {t('open.title')}
                        </h2>
                        <p className="text-small text-text-secondary">{t('open.help')}</p>
                        {openShifts.length === 0 ? (
                            <p className="text-small text-muted-foreground">{t('open.empty')}</p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {openShifts.map((s) => (
                                    <li
                                        key={s.id}
                                        data-open-shift={s.id}
                                        className="flex flex-wrap items-center gap-3 rounded-md border border-dashed border-border bg-card p-3"
                                    >
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="font-medium">
                                                {dayLabel(s.date, locale, {
                                                    weekday: 'short',
                                                    day: 'numeric',
                                                    month: 'short',
                                                })}
                                            </span>
                                            <span className="text-small text-text-secondary">
                                                <ShiftTime
                                                    startsAt={s.startsAt}
                                                    endsAt={s.endsAt}
                                                />
                                                {s.name ? ` · ${localized(s.name, locale)}` : ''} ·{' '}
                                                {localized(s.branch, locale)}
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant={s.claimed ? 'outline' : 'default'}
                                            className="min-h-11"
                                            disabled={s.claimed}
                                            onClick={() =>
                                                router.post(
                                                    schedule.claim.url(s.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {s.claimed ? t('open.claimed') : t('open.claim')}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                </section>
            </div>
        </>
    );
}

function MonthGrid({
    first,
    today,
    byDay,
}: {
    first: string;
    today: string;
    byDay: Map<string, Shift[]>;
}) {
    const { locale } = useLocale();
    const firstDate = parseDay(first);
    const last = new Date(Date.UTC(firstDate.getUTCFullYear(), firstDate.getUTCMonth() + 1, 0))
        .toISOString()
        .slice(0, 10);
    const start = addDays(first, -(weekdayIso(first) % 7));
    const end = addDays(last, 6 - (weekdayIso(last) % 7));
    const days = daysBetween(start, end);

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card">
            <div
                aria-hidden
                className="grid grid-cols-7 border-b border-border bg-muted text-caption text-text-secondary"
            >
                {days.slice(0, 7).map((d) => (
                    <span key={d} className="px-1 py-2 text-center">
                        {formatDate(parseDay(d), locale, { weekday: 'short', timeZone: 'UTC' })}
                    </span>
                ))}
            </div>
            <ol className="grid grid-cols-7">
                {days.map((d) => (
                    <li
                        key={d}
                        aria-current={d === today ? 'date' : undefined}
                        className={cn(
                            'flex min-h-20 flex-col gap-1 border-e border-b border-border p-1 [&:nth-child(7n)]:border-e-0',
                            d.slice(0, 7) !== first.slice(0, 7) &&
                                'bg-surface-sunken text-muted-foreground',
                        )}
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'inline-flex size-6 items-center justify-center rounded-full text-caption tabular-nums',
                                d === today && 'bg-primary text-primary-foreground',
                            )}
                        >
                            {formatNumber(parseDay(d).getUTCDate(), locale)}
                        </span>
                        <span className="sr-only">
                            {dayLabel(d, locale, {
                                weekday: 'long',
                                day: 'numeric',
                                month: 'long',
                            })}
                        </span>
                        {(byDay.get(d) ?? []).map((s) => (
                            <span
                                key={s.id}
                                className={cn(
                                    'truncate rounded-sm border-s-2 px-1 text-caption',
                                    shiftColor(s.color).block,
                                )}
                            >
                                <ShiftTime startsAt={s.startsAt} endsAt={s.endsAt} />
                            </span>
                        ))}
                    </li>
                ))}
            </ol>
        </div>
    );
}
