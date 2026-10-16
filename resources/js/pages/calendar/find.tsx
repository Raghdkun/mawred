import { Head } from '@inertiajs/react';
import { useQuery } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { EventForm } from '@/features/calendar/event-form';
import { type Person, PeoplePicker } from '@/features/calendar/people-picker';
import { localParts, rangeTimeLabel, toInstant } from '@/features/calendar/time';
import { dayLabel } from '@/features/leave/format';
import { addDays } from '@/features/schedule/colors';
import { useLocale } from '@/hooks/use-locale';
import { postJson } from '@/lib/api';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import CalendarApi from '@/actions/App/Http/Controllers/Api/V1/Calendar/CalendarController';

type Block = { start: string; end: string; kind: string; title: string | null };
type Row = {
    user_id: string;
    name: string;
    working: Array<{ start: string; end: string }>;
    busy: Block[];
};
type Availability = { data: Row[]; meta: { suggestions: Array<{ start: string; end: string }> } };
type Props = { me: Person; timezone: string; today: string };

const FIRST_HOUR = 7;
const LAST_HOUR = 20;
const DAYS_AHEAD = 5;

/**
 * Meeting scheduler (handoff 27): pick people, see a day's free/busy grid (D76) with suggested
 * slot chips for the next days, and book from a chip. On phones the grid gives way to the list
 * of suggested slots. The time axis keeps LTR digits while the grid follows the reading order.
 */
export default function Find({ me, timezone, today }: Props) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [people, setPeople] = useState<Person[]>([]);
    const [duration, setDuration] = useState(30);
    const [day, setDay] = useState(today);
    const [booking, setBooking] = useState<{ start: string; end: string } | null>(null);
    const everyone = [me, ...people];
    const from = toInstant(day, '00:00', timezone);
    const to = toInstant(addDays(day, DAYS_AHEAD), '00:00', timezone);

    const query = useQuery({
        queryKey: ['calendar-availability', everyone.map((p) => p.id).join(','), duration, day],
        queryFn: () =>
            postJson<Availability>(CalendarApi.availability.url(), {
                people: everyone.map((p) => p.id),
                from,
                to,
                duration,
            }),
        enabled: people.length > 0,
        placeholderData: (previous) => previous,
    });
    const hours = Array.from({ length: LAST_HOUR - FIRST_HOUR }, (_, i) => FIRST_HOUR + i);
    const span = (LAST_HOUR - FIRST_HOUR) * 60;
    const position = (iso: string) => {
        const p = localParts(iso, timezone);
        const minutes = p.date < day ? 0 : p.date > day ? 24 * 60 : p.minutes;

        return (Math.min(Math.max(minutes - FIRST_HOUR * 60, 0), span) / span) * 100;
    };
    const onDay = (b: { start: string; end: string }) =>
        localParts(b.start, timezone).date <= day && localParts(b.end, timezone).date >= day;

    return (
        <>
            <Head title={t('finder.title')} />
            <PageHeader title={t('finder.title')} description={t('finder.description')} />

            <div className="mb-6 grid gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <PeoplePicker
                    label={t('finder.people')}
                    value={people}
                    onChange={setPeople}
                    exclude={[me.id]}
                />
                <Field id="finder-duration" label={t('finder.duration')}>
                    <Select value={String(duration)} onValueChange={(v) => setDuration(Number(v))}>
                        <SelectTrigger id="finder-duration" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[15, 30, 45, 60, 90, 120].map((m) => (
                                <SelectItem key={m} value={String(m)}>
                                    {t('finder.minutes', { count: formatNumber(m, locale) })}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            </div>

            {people.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('finder.pickPeople')}
                />
            ) : query.isPending ? (
                <div aria-busy className="flex flex-col gap-2">
                    <span className="sr-only">{t('finder.loading')}</span>
                    <Skeleton className="h-12 w-full" />
                    <Skeleton className="h-40 w-full" />
                </div>
            ) : query.isError ? (
                <PageState kind="error" title={t('finder.error')} />
            ) : (
                <>
                    <section aria-labelledby="suggestions" className="mb-6 flex flex-col gap-2">
                        <h2 id="suggestions" className="text-h4">
                            {t('finder.suggestions')}
                        </h2>
                        {query.data.meta.suggestions.length === 0 ? (
                            <p className="text-small text-text-secondary">
                                {t('finder.noSuggestions')}
                            </p>
                        ) : (
                            <ul className="flex flex-wrap gap-2" data-suggestions>
                                {query.data.meta.suggestions.map((s) => (
                                    <li key={s.start}>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={() => setBooking(s)}
                                            data-slot={s.start}
                                        >
                                            <span className="text-text-secondary">
                                                {formatDate(s.start, locale, {
                                                    weekday: 'short',
                                                    day: 'numeric',
                                                    month: 'short',
                                                    timeZone: timezone,
                                                })}
                                            </span>
                                            <span className="font-medium">
                                                {rangeTimeLabel(s.start, s.end, timezone, locale)}
                                            </span>
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>

                    <section aria-labelledby="grid-title" className="hidden flex-col gap-3 md:flex">
                        <div className="flex items-center gap-2">
                            <h2 id="grid-title" className="text-h4">
                                {dayLabel(day, locale, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                })}
                            </h2>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('previous')}
                                disabled={day <= today}
                                onClick={() => setDay(addDays(day, -1))}
                            >
                                <DirIcon>
                                    <ChevronLeft aria-hidden className="size-4" />
                                </DirIcon>
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11"
                                aria-label={t('next')}
                                onClick={() => setDay(addDays(day, 1))}
                            >
                                <DirIcon>
                                    <ChevronRight aria-hidden className="size-4" />
                                </DirIcon>
                            </Button>
                        </div>
                        <div
                            className="overflow-x-auto rounded-lg border border-border bg-card"
                            data-availability-grid
                        >
                            <div className="min-w-[48rem]">
                                <div className="grid grid-cols-[12rem_1fr] border-b border-border text-caption text-muted-foreground">
                                    <span />
                                    <div
                                        aria-label={t('finder.timeAxis')}
                                        role="img"
                                        className="relative h-7"
                                    >
                                        {hours.map((h) => (
                                            <span
                                                key={h}
                                                className="absolute top-1.5 -translate-x-1/2 tabular-nums rtl:translate-x-1/2"
                                                style={{
                                                    insetInlineStart: `${((h - FIRST_HOUR) / (LAST_HOUR - FIRST_HOUR)) * 100}%`,
                                                }}
                                            >
                                                <bdi dir="ltr">
                                                    {formatNumber(h, locale, {
                                                        minimumIntegerDigits: 2,
                                                    })}
                                                    :00
                                                </bdi>
                                            </span>
                                        ))}
                                    </div>
                                </div>
                                <ul>
                                    {query.data.data.map((row) => (
                                        <li
                                            key={row.user_id}
                                            className="grid grid-cols-[12rem_1fr] border-b border-border last:border-0"
                                            data-availability-row={row.user_id}
                                        >
                                            <span className="truncate px-3 py-3 text-small font-medium">
                                                {row.name}
                                            </span>
                                            <div
                                                className="relative h-12 bg-surface-sunken"
                                                aria-label={row.name}
                                                role="group"
                                            >
                                                {row.working.filter(onDay).map((w) => (
                                                    <span
                                                        key={w.start}
                                                        title={t('finder.working')}
                                                        className="absolute inset-y-1 rounded-sm bg-card"
                                                        style={{
                                                            insetInlineStart: `${position(w.start)}%`,
                                                            width: `${position(w.end) - position(w.start)}%`,
                                                        }}
                                                    />
                                                ))}
                                                {row.busy.filter(onDay).map((b) => (
                                                    <span
                                                        key={`${b.start}-${b.kind}`}
                                                        className={cn(
                                                            'absolute inset-y-1 truncate rounded-sm px-1 text-caption leading-10',
                                                            b.kind === 'leave'
                                                                ? 'bg-status-pending-bg text-status-pending-fg'
                                                                : 'bg-primary/20 text-foreground',
                                                        )}
                                                        style={{
                                                            insetInlineStart: `${position(b.start)}%`,
                                                            width: `${Math.max(position(b.end) - position(b.start), 0.5)}%`,
                                                        }}
                                                    >
                                                        {b.title ??
                                                            (b.kind === 'leave'
                                                                ? t('finder.leave')
                                                                : t('finder.busy'))}
                                                    </span>
                                                ))}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        </div>
                        <ul
                            className="flex flex-wrap gap-4 text-caption text-text-secondary"
                            aria-label={t('finder.title')}
                        >
                            <li className="flex items-center gap-1.5">
                                <span
                                    aria-hidden
                                    className="size-3 rounded-sm border border-border bg-card"
                                />
                                {t('finder.working')}
                            </li>
                            <li className="flex items-center gap-1.5">
                                <span aria-hidden className="size-3 rounded-sm bg-surface-sunken" />
                                {t('finder.off')}
                            </li>
                            <li className="flex items-center gap-1.5">
                                <span aria-hidden className="size-3 rounded-sm bg-primary/20" />
                                {t('finder.busy')}
                            </li>
                            <li className="flex items-center gap-1.5">
                                <span
                                    aria-hidden
                                    className="size-3 rounded-sm bg-status-pending-bg"
                                />
                                {t('finder.leave')}
                            </li>
                        </ul>
                    </section>
                </>
            )}

            {booking ? (
                <EventForm
                    event={null}
                    initial={{ ...booking, people }}
                    timezone={timezone}
                    meId={me.id}
                    onClose={() => setBooking(null)}
                />
            ) : null}
        </>
    );
}
