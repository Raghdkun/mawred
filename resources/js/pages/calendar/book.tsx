import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Clock } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { rangeTimeLabel, localParts } from '@/features/calendar/time';
import { dayLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import book from '@/routes/book';

type Page = {
    id: string;
    title: Record<string, string>;
    description: Record<string, string>;
    location: string | null;
    durationMinutes: number;
    owner: string;
    active: boolean;
};
type Slot = { start: string; end: string };
type Props = { pages: Page[]; page: Page | null; slots: Slot[]; timezone: string };

/** Booking (D77): the pages open to me, then a page's open times grouped by day, and booking one. */
export default function Book({ pages, page, slots, timezone }: Props) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [chosen, setChosen] = useState<Slot | null>(null);
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const byDay = new Map<string, Slot[]>();

    for (const slot of slots) {
        const d = localParts(slot.start, timezone).date;
        byDay.set(d, [...(byDay.get(d) ?? []), slot]);
    }

    if (!page) {
        return (
            <>
                <Head title={t('booking.title')} />
                <PageHeader title={t('booking.title')} description={t('booking.description')} />
                {pages.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('booking.empty')}
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {pages.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-col gap-2 rounded-lg border border-border bg-card p-4"
                                data-booking-page={p.id}
                            >
                                <span className="font-medium">{localized(p.title, locale)}</span>
                                <span className="text-small text-text-secondary">
                                    {t('booking.with', { name: p.owner })} ·{' '}
                                    {t('booking.length', {
                                        count: formatNumber(p.durationMinutes, locale),
                                    })}
                                </span>
                                {localized(p.description, locale) ? (
                                    <p className="line-clamp-3 text-small">
                                        {localized(p.description, locale)}
                                    </p>
                                ) : null}
                                <Button
                                    asChild
                                    variant="outline"
                                    className="mt-auto min-h-11 self-start"
                                >
                                    <Link href={book.show.url(p.id)}>{t('booking.open')}</Link>
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </>
        );
    }

    return (
        <>
            <Head title={localized(page.title, locale)} />
            <Link
                href={book.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-link hover:underline"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('booking.back')}
            </Link>
            <PageHeader
                title={localized(page.title, locale)}
                description={`${t('booking.with', { name: page.owner })} · ${t('booking.length', { count: formatNumber(page.durationMinutes, locale) })}${page.location ? ` · ${page.location}` : ''}`}
            />
            {localized(page.description, locale) ? (
                <p className="mb-4 max-w-2xl text-small whitespace-pre-line">
                    {localized(page.description, locale)}
                </p>
            ) : null}

            <h2 className="mb-2 text-h4">{t('booking.slots')}</h2>
            {slots.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('booking.noSlots')}
                />
            ) : (
                <ol className="flex flex-col gap-4">
                    {[...byDay.entries()].map(([d, daySlots]) => (
                        <li key={d}>
                            <h3 className="mb-2 text-small font-medium">
                                {dayLabel(d, locale, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                })}
                            </h3>
                            <ul className="flex flex-wrap gap-2">
                                {daySlots.map((s) => (
                                    <li key={s.start}>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            className="min-h-11"
                                            onClick={() => setChosen(s)}
                                            data-book-slot={s.start}
                                        >
                                            <Clock aria-hidden className="size-4" />
                                            {rangeTimeLabel(s.start, s.end, timezone, locale)}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ol>
            )}

            {chosen ? (
                <ResponsiveDialog
                    open
                    onOpenChange={(open) => !open && setChosen(null)}
                    title={localized(page.title, locale)}
                    description={`${dayLabel(localParts(chosen.start, timezone).date, locale, { weekday: 'long', day: 'numeric', month: 'long' })} · ${rangeTimeLabel(chosen.start, chosen.end, timezone, locale)}`}
                    footer={
                        <Button
                            type="button"
                            loading={busy}
                            data-confirm-booking
                            onClick={() =>
                                router.post(
                                    book.store.url(page.id),
                                    { start: chosen.start, note: note.trim() || null },
                                    {
                                        onStart: () => setBusy(true),
                                        onFinish: () => setBusy(false),
                                    },
                                )
                            }
                        >
                            {t('booking.confirm', {
                                time: rangeTimeLabel(chosen.start, chosen.end, timezone, locale),
                            })}
                        </Button>
                    }
                >
                    <Field id="booking-note" label={t('booking.note')}>
                        <Textarea
                            id="booking-note"
                            value={note}
                            maxLength={500}
                            onChange={(e) => setNote(e.target.value)}
                        />
                    </Field>
                </ResponsiveDialog>
            ) : null}
        </>
    );
}
