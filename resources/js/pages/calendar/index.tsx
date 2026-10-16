import { Head, Link, router } from '@inertiajs/react';
import { CalendarClock, ChevronLeft, ChevronRight, Plus, Ticket } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { EventDialog } from '@/features/calendar/event-dialog';
import { EventForm } from '@/features/calendar/event-form';
import {
    type CalendarEvent,
    localParts,
    rangeTimeLabel,
    toInstant,
} from '@/features/calendar/time';
import { type Holiday, type TimeAway, WeekGrid } from '@/features/calendar/week-grid';
import { addDays, daysBetween, parseDay } from '@/features/schedule/colors';
import { ShiftTime } from '@/features/schedule/shift-time';
import { dayLabel, rangeLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import book from '@/routes/book';
import bookingPages from '@/routes/booking-pages';
import calendarRoutes from '@/routes/calendar';

type Shift = App.Domain.Scheduling.Data.MyShiftData;
type Props = {
    view: 'week' | 'month';
    from: string;
    to: string;
    today: string;
    timezone: string;
    open: string | null;
    me: { id: string; name: string };
    events: CalendarEvent[];
    shifts: Shift[];
    timeAway: TimeAway[];
    holidays: Holiday[];
    can: { create: boolean; bookingPages: boolean };
};

/**
 * My calendar: a week time grid or a month on desktop, an agenda on phones; meetings, bookings,
 * shifts, time away and holidays together. Opening a meeting shows it; free time starts one.
 */
export default function CalendarPage({
    view,
    from,
    to,
    today,
    timezone,
    open,
    me,
    events,
    shifts,
    timeAway,
    holidays,
    can,
}: Props) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [selected, setSelected] = useState<CalendarEvent | null>(() =>
        open ? (events.find((e) => e.id === open) ?? null) : null,
    );
    const [editing, setEditing] = useState<{
        event: CalendarEvent | null;
        start?: string;
        end?: string;
    } | null>(null);
    const anchor = view === 'month' ? addDays(from, 10) : from;
    const visit = (query: Record<string, string>) =>
        router.get(
            calendarRoutes.index.url({ query: { view, date: anchor, ...query } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const step = (by: number) => {
        if (view === 'week') return visit({ date: addDays(from, by * 7) });
        const d = parseDay(anchor);
        d.setUTCMonth(d.getUTCMonth() + by);
        visit({ date: d.toISOString().slice(0, 10) });
    };
    const days = daysBetween(from, to);
    const eventsOn = (d: string) =>
        events.filter((e) => localParts(e.startsAt, timezone).date === d);
    const current = selected ? (events.find((e) => e.key === selected.key) ?? selected) : null;

    return (
        <>
            <Head title={t('title')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={book.index.url()}>
                                <Ticket aria-hidden className="size-4" />
                                {t('book')}
                            </Link>
                        </Button>
                        {can.bookingPages ? (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={bookingPages.index.url()}>{t('bookingPages')}</Link>
                            </Button>
                        ) : null}
                        {can.create ? (
                            <>
                                <Button asChild variant="outline" className="min-h-11">
                                    <Link href={calendarRoutes.find.url()}>
                                        <CalendarClock aria-hidden className="size-4" />
                                        {t('find')}
                                    </Link>
                                </Button>
                                <Button
                                    className="min-h-11"
                                    onClick={() => setEditing({ event: null })}
                                    data-new-meeting
                                >
                                    <Plus aria-hidden className="size-4" />
                                    {t('new')}
                                </Button>
                            </>
                        ) : null}
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-11"
                    aria-label={t('previous')}
                    onClick={() => step(-1)}
                >
                    <DirIcon>
                        <ChevronLeft aria-hidden className="size-4" />
                    </DirIcon>
                </Button>
                <h2 aria-live="polite" className="min-w-40 text-center text-h4">
                    {view === 'month'
                        ? formatDate(parseDay(anchor), locale, {
                              month: 'long',
                              year: 'numeric',
                              timeZone: 'UTC',
                          })
                        : rangeLabel(from, to, locale)}
                </h2>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="size-11"
                    aria-label={t('next')}
                    onClick={() => step(1)}
                >
                    <DirIcon>
                        <ChevronRight aria-hidden className="size-4" />
                    </DirIcon>
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="min-h-11"
                    onClick={() => visit({ date: today })}
                >
                    {t('today')}
                </Button>
                <ToggleGroup
                    type="single"
                    value={view}
                    onValueChange={(v) => v && visit({ view: v })}
                    className="ms-auto hidden md:flex"
                    aria-label={t('title')}
                >
                    <ToggleGroupItem value="week" className="min-h-11 px-4">
                        {t('views.week')}
                    </ToggleGroupItem>
                    <ToggleGroupItem value="month" className="min-h-11 px-4">
                        {t('views.month')}
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            <div className="hidden md:block">
                {view === 'week' ? (
                    <WeekGrid
                        from={from}
                        today={today}
                        timezone={timezone}
                        events={events}
                        shifts={shifts}
                        timeAway={timeAway}
                        holidays={holidays}
                        onOpen={setSelected}
                        onCreate={
                            can.create
                                ? (date, hour) => {
                                      const start = toInstant(
                                          date,
                                          `${String(hour).padStart(2, '0')}:00`,
                                          timezone,
                                      );
                                      setEditing({
                                          event: null,
                                          start,
                                          end: new Date(
                                              new Date(start).getTime() + 3_600_000,
                                          ).toISOString(),
                                      });
                                  }
                                : undefined
                        }
                    />
                ) : (
                    <ol
                        className="grid grid-cols-7 overflow-hidden rounded-lg border border-border bg-card"
                        data-month-grid
                    >
                        {days.map((d) => {
                            const dayEvents = eventsOn(d);

                            return (
                                <li
                                    key={d}
                                    aria-current={d === today ? 'date' : undefined}
                                    className={cn(
                                        'flex min-h-24 flex-col gap-0.5 border-e border-b border-border p-1 [&:nth-child(7n)]:border-e-0',
                                        d.slice(0, 7) !== anchor.slice(0, 7) &&
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
                                    {dayEvents.slice(0, 3).map((e) => (
                                        <button
                                            key={e.key}
                                            type="button"
                                            onClick={() => setSelected(e)}
                                            className="truncate rounded-sm bg-primary/10 px-1 text-start text-caption focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        >
                                            {
                                                rangeTimeLabel(
                                                    e.startsAt,
                                                    e.endsAt,
                                                    timezone,
                                                    locale,
                                                ).split('–')[0]
                                            }{' '}
                                            {e.title}
                                        </button>
                                    ))}
                                    {dayEvents.length > 3 ? (
                                        <span className="text-caption text-text-secondary">
                                            {t('more', {
                                                count: formatNumber(dayEvents.length - 3, locale),
                                            })}
                                        </span>
                                    ) : null}
                                </li>
                            );
                        })}
                    </ol>
                )}
            </div>

            <ol className="flex flex-col gap-3 md:hidden" data-agenda>
                {days.filter(
                    (d) =>
                        eventsOn(d).length > 0 ||
                        shifts.some((s) => s.date === d) ||
                        holidays.some((h) => h.date === d),
                ).length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('agendaEmpty')}
                    />
                ) : (
                    days
                        .filter(
                            (d) =>
                                eventsOn(d).length > 0 ||
                                shifts.some((s) => s.date === d) ||
                                holidays.some((h) => h.date === d),
                        )
                        .map((d) => (
                            <li
                                key={d}
                                className={cn(
                                    'rounded-lg border border-border bg-card p-3',
                                    d === today && 'border-primary',
                                )}
                            >
                                <h3 className="mb-2 text-small font-medium">
                                    {dayLabel(d, locale, {
                                        weekday: 'long',
                                        day: 'numeric',
                                        month: 'long',
                                    })}
                                </h3>
                                <ul className="flex flex-col gap-1.5 text-small">
                                    {holidays
                                        .filter((h) => h.date === d)
                                        .map((h) => (
                                            <li
                                                key={h.date}
                                                className="rounded-sm bg-info-bg px-2 py-1 text-info"
                                            >
                                                {localized(h.name, locale)}
                                            </li>
                                        ))}
                                    {shifts
                                        .filter((s) => s.date === d)
                                        .map((s) => (
                                            <li
                                                key={s.id}
                                                className="rounded-sm bg-muted px-2 py-1 text-text-secondary"
                                            >
                                                {t('kinds.shift')} ·{' '}
                                                <ShiftTime
                                                    startsAt={s.startsAt}
                                                    endsAt={s.endsAt}
                                                />
                                            </li>
                                        ))}
                                    {eventsOn(d).map((e) => (
                                        <li key={e.key}>
                                            <button
                                                type="button"
                                                onClick={() => setSelected(e)}
                                                className="flex min-h-11 w-full flex-col items-start rounded-md border-s-4 border-primary bg-primary/10 px-2 py-1 text-start"
                                            >
                                                <span className="font-medium">{e.title}</span>
                                                <span className="text-text-secondary">
                                                    {rangeTimeLabel(
                                                        e.startsAt,
                                                        e.endsAt,
                                                        timezone,
                                                        locale,
                                                    )}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </li>
                        ))
                )}
            </ol>

            {current ? (
                <EventDialog
                    event={current}
                    timezone={timezone}
                    onClose={() => setSelected(null)}
                    onEdit={() => {
                        setEditing({ event: current });
                        setSelected(null);
                    }}
                />
            ) : null}
            {editing ? (
                <EventForm
                    event={editing.event}
                    initial={
                        editing.start && editing.end
                            ? { start: editing.start, end: editing.end }
                            : undefined
                    }
                    timezone={timezone}
                    meId={me.id}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}
