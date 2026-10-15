import { Head, Link } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { dayLabel, type LeaveEvent } from '@/features/leave/format';
import { BranchPicker, DepartmentPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import leave from '@/routes/leave';

type Props = {
    today: string;
    branch: { id: string; name: Record<string, string> } | null;
    can: { filter: boolean; configure: boolean };
};

type CalendarResponse = {
    data: LeaveEvent[];
    meta: {
        from: string;
        to: string;
        holidays: App.Domain.Leave.Data.HolidayMarkData[];
        weekend: number[];
    };
};

const MAX_CHIPS = 3;

function iso(date: Date): string {
    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-${String(date.getUTCDate()).padStart(2, '0')}`;
}

function parse(date: string): Date {
    const [y, m, d] = date.split('-').map(Number);

    return new Date(Date.UTC(y ?? 1970, (m ?? 1) - 1, d ?? 1));
}

/** ISO weekday (1 = Monday … 7 = Sunday). */
function isoDay(date: Date): number {
    return date.getUTCDay() === 0 ? 7 : date.getUTCDay();
}

function addDays(date: Date, days: number): Date {
    return new Date(date.getTime() + days * 86_400_000);
}

/**
 * Leave calendar (handoff 24): who is away, with holidays and weekends. Desktop shows the month
 * grid (weeks start after the branch weekend); phones get an agenda of the days with something
 * on. "+N more" opens the day in a sheet.
 */
export default function LeaveCalendar({ today, branch, can }: Props) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const [month, setMonth] = useState(() => today.slice(0, 7));
    const [branchFilter, setBranchFilter] = useState<PickerOption | null>(
        branch ? { id: branch.id, label: localized(branch.name, locale) } : null,
    );
    const [department, setDepartment] = useState<PickerOption | null>(null);
    const [openDay, setOpenDay] = useState<string | null>(null);

    const first = parse(`${month}-01`);
    const last = addDays(
        new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + 1, 1)),
        -1,
    );

    const query = useQuery({
        queryKey: ['leave-calendar', month, branchFilter?.id, department?.id],
        queryFn: ({ signal }) =>
            api<CalendarResponse>('leave/calendar', {
                params: {
                    from: iso(first),
                    to: iso(last),
                    branch: branchFilter?.id,
                    department: department?.id,
                },
                signal,
            }),
        placeholderData: (previous) => previous,
    });

    const weekend = query.data?.meta.weekend ?? [6, 7];
    const weekStart = (Math.max(...weekend) % 7) + 1;
    const gridStart = addDays(first, -((isoDay(first) - weekStart + 7) % 7));
    const weeks = Math.ceil(
        ((first.getTime() - gridStart.getTime()) / 86_400_000 + last.getUTCDate()) / 7,
    );
    const days = Array.from({ length: weeks * 7 }, (_, index) => addDays(gridStart, index));

    const byDay = useMemo(() => {
        const map = new Map<string, LeaveEvent[]>();

        for (const event of query.data?.data ?? []) {
            for (let day = parse(event.from); iso(day) <= event.to; day = addDays(day, 1)) {
                const key = iso(day);
                map.set(key, [...(map.get(key) ?? []), event]);
            }
        }

        return map;
    }, [query.data]);
    const holidays = useMemo(
        () =>
            new Map(
                (query.data?.meta.holidays ?? []).map((h) => [h.date, localized(h.name, locale)]),
            ),
        [query.data, locale],
    );

    const shift = (by: number) => {
        const next = new Date(Date.UTC(first.getUTCFullYear(), first.getUTCMonth() + by, 1));
        setMonth(iso(next).slice(0, 7));
    };
    const name = (event: LeaveEvent) =>
        locale === 'ar' && event.employeeNameAr ? event.employeeNameAr : event.employeeName;
    const inMonth = (date: Date) => date.getUTCMonth() === first.getUTCMonth();
    const agendaDays = days.filter(
        (d) => inMonth(d) && (byDay.has(iso(d)) || holidays.has(iso(d))),
    );

    return (
        <>
            <Head title={t('calendar.title')} />
            <PageHeader
                title={t('calendar.title')}
                description={t('calendar.description')}
                actions={
                    can.configure ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={leave.blackouts.index.url()}>{t('links.blackouts')}</Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="flex flex-wrap items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11"
                        aria-label={t('calendar.previous')}
                        onClick={() => shift(-1)}
                    >
                        <DirIcon>
                            <ChevronLeft aria-hidden className="size-4" />
                        </DirIcon>
                    </Button>
                    <h2 aria-live="polite" className="min-w-32 text-center text-h4 sm:min-w-44">
                        {formatDate(first, locale, {
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
                        aria-label={t('calendar.next')}
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
                        onClick={() => setMonth(today.slice(0, 7))}
                    >
                        {t('calendar.today')}
                    </Button>
                </div>
                {can.filter ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:w-[32rem]">
                        <BranchPicker
                            label={t('calendar.branch')}
                            placeholder={t('calendar.allBranches')}
                            value={branchFilter}
                            onChange={setBranchFilter}
                        />
                        <DepartmentPicker
                            label={t('calendar.department')}
                            placeholder={t('calendar.allDepartments')}
                            value={department}
                            onChange={setDepartment}
                        />
                    </div>
                ) : null}
            </div>

            <Legend />

            {query.isPending ? (
                <Skeleton className="h-[480px] w-full" />
            ) : query.isError ? (
                <PageState kind="error" />
            ) : (
                <>
                    <div
                        className="hidden overflow-hidden rounded-lg border border-border bg-card md:block"
                        data-leave-grid
                    >
                        <div
                            aria-hidden
                            className="grid grid-cols-7 border-b border-border bg-muted text-caption text-text-secondary"
                        >
                            {days.slice(0, 7).map((d) => (
                                <span key={iso(d)} className="px-2 py-2 text-center">
                                    {formatDate(d, locale, { weekday: 'short', timeZone: 'UTC' })}
                                </span>
                            ))}
                        </div>
                        <ol className="grid grid-cols-7">
                            {days.map((d) => {
                                const key = iso(d);
                                const events = byDay.get(key) ?? [];
                                const holiday = holidays.get(key);
                                const off = weekend.includes(isoDay(d));

                                return (
                                    <li
                                        key={key}
                                        data-day={key}
                                        aria-current={key === today ? 'date' : undefined}
                                        className={cn(
                                            'flex min-h-28 flex-col gap-1 border-e border-b border-border p-1.5 [&:nth-child(7n)]:border-e-0',
                                            !inMonth(d) &&
                                                'bg-surface-sunken text-muted-foreground',
                                            inMonth(d) && off && 'bg-muted',
                                            holiday && 'bg-info-bg',
                                        )}
                                    >
                                        <span className="flex items-center justify-between gap-1">
                                            <span
                                                className={cn(
                                                    'inline-flex size-7 items-center justify-center rounded-full text-small tabular-nums',
                                                    key === today &&
                                                        'bg-primary font-semibold text-primary-foreground',
                                                )}
                                                aria-hidden
                                            >
                                                {formatNumber(d.getUTCDate(), locale)}
                                            </span>
                                            <span className="sr-only">
                                                {dayLabel(key, locale, {
                                                    weekday: 'long',
                                                    day: 'numeric',
                                                    month: 'long',
                                                })}
                                            </span>
                                            {holiday ? (
                                                <span className="truncate text-caption text-info">
                                                    {holiday}
                                                </span>
                                            ) : null}
                                        </span>
                                        {inMonth(d)
                                            ? events
                                                  .slice(0, MAX_CHIPS)
                                                  .map((event) => (
                                                      <Chip
                                                          key={event.id}
                                                          event={event}
                                                          label={name(event)}
                                                      />
                                                  ))
                                            : null}
                                        {inMonth(d) && events.length > MAX_CHIPS ? (
                                            <button
                                                type="button"
                                                onClick={() => setOpenDay(key)}
                                                className="min-h-6 rounded-sm px-1 text-start text-caption font-medium text-link hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {t('calendar.more', {
                                                    count: formatNumber(
                                                        events.length - MAX_CHIPS,
                                                        locale,
                                                    ),
                                                })}
                                            </button>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ol>
                    </div>

                    <div className="md:hidden" data-leave-agenda>
                        {agendaDays.length === 0 ? (
                            <PageState
                                kind="empty"
                                illustration="empty-schedule"
                                title={t('calendar.empty')}
                            />
                        ) : (
                            <ol className="flex flex-col gap-3">
                                {agendaDays.map((d) => {
                                    const key = iso(d);
                                    const events = byDay.get(key) ?? [];

                                    return (
                                        <li
                                            key={key}
                                            className="rounded-lg border border-border bg-card p-3"
                                        >
                                            <h3 className="mb-2 flex items-center justify-between gap-2 text-small font-medium">
                                                <span>
                                                    {dayLabel(key, locale, {
                                                        weekday: 'long',
                                                        day: 'numeric',
                                                        month: 'long',
                                                    })}
                                                </span>
                                                {holidays.has(key) ? (
                                                    <span className="text-caption text-info">
                                                        {holidays.get(key)}
                                                    </span>
                                                ) : null}
                                            </h3>
                                            <ul className="flex flex-col gap-1">
                                                {events.map((event) => (
                                                    <li key={event.id}>
                                                        <Chip
                                                            event={event}
                                                            label={name(event)}
                                                            detailed
                                                        />
                                                    </li>
                                                ))}
                                            </ul>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </div>

                    {query.data.data.length === 0 ? (
                        <p className="mt-3 hidden text-small text-muted-foreground md:block">
                            {t('calendar.empty')}
                        </p>
                    ) : null}
                </>
            )}

            <ResponsiveDialog
                open={openDay !== null}
                onOpenChange={(open) => !open && setOpenDay(null)}
                title={
                    openDay
                        ? t('calendar.moreTitle', {
                              date: dayLabel(openDay, locale, {
                                  weekday: 'long',
                                  day: 'numeric',
                                  month: 'long',
                              }),
                          })
                        : ''
                }
            >
                <ul className="flex flex-col gap-1">
                    {(openDay ? (byDay.get(openDay) ?? []) : []).map((event) => (
                        <li key={event.id}>
                            <Chip event={event} label={name(event)} detailed />
                        </li>
                    ))}
                </ul>
            </ResponsiveDialog>
        </>
    );
}

function Chip({
    event,
    label,
    detailed = false,
}: {
    event: LeaveEvent;
    label: string;
    detailed?: boolean;
}) {
    const { t } = useTranslation('leave');
    const pending = event.status === 'pending';
    const kind = event.typeName ?? t(`timeAway.kind.${event.kind}`);

    return (
        <span
            data-leave-chip={event.id}
            title={`${label} · ${kind}`}
            className={cn(
                'flex min-w-0 items-center gap-1.5 rounded-sm px-1.5 py-0.5 text-caption',
                pending
                    ? 'border border-dashed border-status-pending-border bg-status-pending-bg text-status-pending-fg'
                    : 'bg-primary/10 text-foreground',
                detailed && 'min-h-9 text-small',
            )}
        >
            <span className="truncate font-medium">{label}</span>
            {detailed ? (
                <span className="truncate text-text-secondary">
                    · {kind}
                    {event.halfDay ? ` · ${t(`timeAway.halfDay.${event.halfDay}`)}` : null}
                    {pending ? ` · ${t('timeAway.status.pending')}` : null}
                </span>
            ) : (
                <span className="sr-only">
                    {kind}
                    {pending ? `, ${t('timeAway.status.pending')}` : null}
                </span>
            )}
        </span>
    );
}

function Legend() {
    const { t } = useTranslation('leave');
    const items: Array<[string, string]> = [
        ['bg-primary/10', t('calendar.legend.approved')],
        [
            'border border-dashed border-status-pending-border bg-status-pending-bg',
            t('calendar.legend.pending'),
        ],
        ['bg-info-bg border border-info-border', t('calendar.legend.holiday')],
        ['bg-muted border border-border', t('calendar.legend.weekend')],
    ];

    return (
        <ul
            aria-label={t('calendar.legend.title')}
            className="mb-3 flex flex-wrap gap-x-4 gap-y-2 text-caption text-text-secondary"
        >
            {items.map(([swatch, label]) => (
                <li key={label} className="flex items-center gap-1.5">
                    <span aria-hidden className={cn('size-3 rounded-sm', swatch)} />
                    {label}
                </li>
            ))}
        </ul>
    );
}
