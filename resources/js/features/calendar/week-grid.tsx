import { useTranslation } from 'react-i18next';
import { addDays, minutes as timeMinutes, parseDay } from '@/features/schedule/colors';
import { dayLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import { type CalendarEvent, localParts, timeLabel } from './time';

type Shift = App.Domain.Scheduling.Data.MyShiftData;
export type TimeAway = { from: string; to: string; kind: string; status: string };
export type Holiday = { date: string; name: Record<string, string> };

const FIRST_HOUR = 6;
const LAST_HOUR = 22;
const HOUR_PX = 48;

/** Lanes so overlapping meetings sit side by side. */
function lanes(events: Array<{ event: CalendarEvent; start: number; end: number }>) {
    const sorted = [...events].sort((a, b) => a.start - b.start);
    const ends: number[] = [];

    return sorted
        .map((item) => {
            let lane = ends.findIndex((end) => end <= item.start);

            if (lane === -1) {
                lane = ends.length;
                ends.push(item.end);
            } else {
                ends[lane] = item.end;
            }

            return { ...item, lane };
        })
        .map((item, _, all) => ({
            ...item,
            lanes:
                Math.max(
                    ...all
                        .filter((o) => o.start < item.end && o.end > item.start)
                        .map((o) => o.lane),
                ) + 1,
        }));
}

/**
 * The week (handoff: calendar cells) as a time grid: an all-day row for holidays and time away,
 * shifts as shaded working time, meetings placed by time. The time axis keeps its digits LTR
 * while the day columns follow the reading direction. Clicking free time starts a meeting.
 */
export function WeekGrid({
    from,
    today,
    timezone,
    events,
    shifts,
    timeAway,
    holidays,
    onOpen,
    onCreate,
}: {
    from: string;
    today: string;
    timezone: string;
    events: CalendarEvent[];
    shifts: Shift[];
    timeAway: TimeAway[];
    holidays: Holiday[];
    onOpen: (event: CalendarEvent) => void;
    onCreate?: (date: string, hour: number) => void;
}) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const days = Array.from({ length: 7 }, (_, i) => addDays(from, i));
    const hours = Array.from({ length: LAST_HOUR - FIRST_HOUR }, (_, i) => FIRST_HOUR + i);
    const columns = { gridTemplateColumns: `4rem repeat(7, minmax(7rem, 1fr))` };
    const top = (minutes: number) =>
        ((Math.max(minutes, FIRST_HOUR * 60) - FIRST_HOUR * 60) / 60) * HOUR_PX;

    return (
        <div className="overflow-x-auto rounded-lg border border-border bg-card" data-week-grid>
            <div className="min-w-max">
                <div
                    className="sticky top-0 z-10 grid border-b border-border bg-muted text-caption"
                    style={columns}
                >
                    <span aria-hidden />
                    {days.map((d) => (
                        <span
                            key={d}
                            className={cn(
                                'flex flex-col items-center py-2',
                                d === today && 'font-semibold text-foreground',
                            )}
                        >
                            <span>
                                {formatDate(parseDay(d), locale, {
                                    weekday: 'short',
                                    timeZone: 'UTC',
                                })}
                            </span>
                            <span
                                className={cn(
                                    'inline-flex size-7 items-center justify-center rounded-full tabular-nums',
                                    d === today && 'bg-primary text-primary-foreground',
                                )}
                            >
                                {formatNumber(parseDay(d).getUTCDate(), locale)}
                            </span>
                        </span>
                    ))}
                </div>

                <div className="grid border-b border-border text-caption" style={columns}>
                    <span className="px-1 py-1.5 text-center text-muted-foreground">
                        {t('allDay')}
                    </span>
                    {days.map((d) => {
                        const holiday = holidays.find((h) => h.date === d);
                        const away = timeAway.filter(
                            (a) => d >= a.from && d <= a.to && a.kind !== 'hourly',
                        );

                        return (
                            <div
                                key={d}
                                className="flex min-h-8 flex-col gap-0.5 border-s border-border p-0.5"
                            >
                                {holiday ? (
                                    <span className="truncate rounded-sm bg-info-bg px-1 text-info">
                                        {localized(holiday.name, locale)}
                                    </span>
                                ) : null}
                                {away.map((a, i) => (
                                    <span
                                        key={i}
                                        className={cn(
                                            'truncate rounded-sm px-1',
                                            a.status === 'pending'
                                                ? 'border border-dashed border-status-pending-border bg-status-pending-bg text-status-pending-fg'
                                                : 'bg-surface-sunken text-text-secondary',
                                        )}
                                    >
                                        {t('kinds.leave')}
                                    </span>
                                ))}
                            </div>
                        );
                    })}
                </div>

                <div className="grid" style={columns}>
                    <div
                        dir="ltr"
                        aria-hidden
                        className="relative"
                        style={{ height: hours.length * HOUR_PX }}
                    >
                        {hours.map((h) => (
                            <span
                                key={h}
                                className="absolute end-1 -translate-y-1/2 text-caption text-muted-foreground tabular-nums"
                                style={{ top: (h - FIRST_HOUR) * HOUR_PX }}
                            >
                                {formatNumber(h, locale, { minimumIntegerDigits: 2 })}:
                                {formatNumber(0, locale, { minimumIntegerDigits: 2 })}
                            </span>
                        ))}
                    </div>
                    {days.map((d) => {
                        const dayEvents = events
                            .map((event) => {
                                const s = localParts(event.startsAt, timezone);
                                const e = localParts(event.endsAt, timezone);

                                return s.date === d
                                    ? {
                                          event,
                                          start: s.minutes,
                                          end:
                                              e.date === d
                                                  ? Math.max(e.minutes, s.minutes + 15)
                                                  : 24 * 60,
                                      }
                                    : null;
                            })
                            .filter(
                                (x): x is { event: CalendarEvent; start: number; end: number } =>
                                    x !== null,
                            );

                        return (
                            <div
                                key={d}
                                data-day={d}
                                className="relative border-s border-border"
                                style={{ height: hours.length * HOUR_PX }}
                            >
                                {hours.map((h) => (
                                    <button
                                        key={h}
                                        type="button"
                                        tabIndex={onCreate ? 0 : -1}
                                        aria-label={`${t('new')} · ${dayLabel(d, locale, { weekday: 'long', day: 'numeric', month: 'long' })} ${String(h).padStart(2, '0')}:00`}
                                        onClick={() => onCreate?.(d, h)}
                                        className="absolute inset-x-0 border-t border-border/60 hover:bg-muted/60 focus-visible:z-10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        style={{ top: (h - FIRST_HOUR) * HOUR_PX, height: HOUR_PX }}
                                    />
                                ))}
                                {shifts
                                    .filter((s) => s.date === d)
                                    .map((s) => {
                                        const start = timeMinutes(s.startsAt);
                                        const end =
                                            timeMinutes(s.endsAt) <= start
                                                ? 24 * 60
                                                : timeMinutes(s.endsAt);

                                        return (
                                            <span
                                                key={s.id}
                                                aria-hidden
                                                className="pointer-events-none absolute inset-x-0 border-s-2 border-primary/40 bg-primary/5"
                                                style={{
                                                    top: top(start),
                                                    height: top(end) - top(start),
                                                }}
                                            />
                                        );
                                    })}
                                {lanes(dayEvents).map(
                                    ({ event, start, end, lane, lanes: count }) => (
                                        <button
                                            key={event.key}
                                            type="button"
                                            onClick={() => onOpen(event)}
                                            data-event={event.id}
                                            className={cn(
                                                'absolute z-[5] flex overflow-hidden rounded-sm border-s-4 px-1.5 py-0.5 text-start text-caption leading-tight shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                end - start < 45
                                                    ? 'flex-row items-center gap-1'
                                                    : 'flex-col',
                                                event.myResponse === 'declined'
                                                    ? 'border-border bg-muted text-muted-foreground line-through'
                                                    : 'border-primary bg-primary/10 text-foreground',
                                                event.myResponse === 'pending' && 'border-dashed',
                                            )}
                                            style={{
                                                top: top(start),
                                                height: Math.max(20, top(end) - top(start) - 2),
                                                insetInlineStart: `calc(${(lane / count) * 100}% + 2px)`,
                                                width: `calc(${100 / count}% - 4px)`,
                                            }}
                                        >
                                            <span className="truncate font-medium">
                                                {event.title}
                                            </span>
                                            <span className="truncate text-text-secondary">
                                                {timeLabel(event.startsAt, timezone, locale)}
                                            </span>
                                        </button>
                                    ),
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
