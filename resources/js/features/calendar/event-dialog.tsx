import { router } from '@inertiajs/react';
import { CalendarPlus, Lock, MapPin, Repeat } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import calendarRoutes from '@/routes/calendar';
import { type Scope, ScopeChoice } from './event-form';
import { type CalendarEvent, rangeTimeLabel } from './time';

const PALETTE: Record<string, WorkflowStatus> = {
    accepted: 'approved',
    tentative: 'pending',
    declined: 'rejected',
    pending: 'draft',
};

/**
 * One meeting (handoff 27): when, where, who and their replies, your reply (D75), "add to my
 * calendar (.ics)", and edit or cancel for the organiser (one, the rest, or all, D78).
 */
export function EventDialog({
    event,
    timezone,
    onClose,
    onEdit,
}: {
    event: CalendarEvent;
    timezone: string;
    onClose: () => void;
    onEdit: () => void;
}) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    const [cancelling, setCancelling] = useState(false);
    const [scope, setScope] = useState<Scope>('this');
    const reply = (response: string) =>
        router.post(
            calendarRoutes.respond.url(event.id),
            { response },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={event.title}
            description={`${formatDate(event.startsAt, locale, { weekday: 'long', day: 'numeric', month: 'long', timeZone: timezone })} · ${rangeTimeLabel(event.startsAt, event.endsAt, timezone, locale)}`}
            footer={
                event.isOrganizer && event.kind === 'meeting' ? (
                    cancelling ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCancelling(false)}
                            >
                                {t('event.keep')}
                            </Button>
                            <Button
                                type="button"
                                variant="destructive"
                                data-confirm-cancel
                                onClick={() =>
                                    router.delete(calendarRoutes.destroy.url(event.id), {
                                        data: {
                                            scope: event.repeats ? scope : 'all',
                                            occurrence: event.startsAt,
                                        },
                                        preserveScroll: true,
                                        onSuccess: onClose,
                                    })
                                }
                            >
                                {t('event.cancel')}
                            </Button>
                        </>
                    ) : (
                        <>
                            <Button
                                type="button"
                                variant="ghost"
                                className="me-auto text-danger"
                                onClick={() => setCancelling(true)}
                            >
                                {t('event.cancel')}
                            </Button>
                            <Button type="button" variant="outline" onClick={onEdit}>
                                {t('event.edit')}
                            </Button>
                        </>
                    )
                ) : undefined
            }
        >
            <div className="flex flex-col gap-4 text-small">
                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-text-secondary">
                    <li>{t('event.organizer', { name: event.organizer })}</li>
                    {event.location ? (
                        <li className="flex items-center gap-1">
                            <MapPin aria-hidden className="size-3.5" />
                            {event.location}
                        </li>
                    ) : null}
                    {event.repeats ? (
                        <li className="flex items-center gap-1">
                            <Repeat aria-hidden className="size-3.5" />
                            {t('event.repeats')}
                        </li>
                    ) : null}
                    {event.private ? (
                        <li className="flex items-center gap-1">
                            <Lock aria-hidden className="size-3.5" />
                            {t('event.private')}
                        </li>
                    ) : null}
                    {event.kind === 'booking' ? <li>{t('event.booking')}</li> : null}
                </ul>
                {event.description ? (
                    <p className="whitespace-pre-line">{event.description}</p>
                ) : null}

                {!event.isOrganizer && event.myResponse ? (
                    <section aria-labelledby="my-reply" className="flex flex-col gap-2">
                        <h3 id="my-reply" className="font-semibold">
                            {t('event.yourReply')}
                        </h3>
                        <ToggleGroup
                            type="single"
                            value={event.myResponse}
                            onValueChange={(v) => v && reply(v)}
                            aria-labelledby="my-reply"
                            className="justify-start"
                        >
                            {(['accepted', 'tentative', 'declined'] as const).map((r) => (
                                <ToggleGroupItem
                                    key={r}
                                    value={r}
                                    className="min-h-11 px-4"
                                    data-reply={r}
                                >
                                    {t(`event.reply.${r}`)}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                    </section>
                ) : null}

                <section aria-labelledby="event-people" className="flex flex-col gap-2">
                    <h3 id="event-people" className="font-semibold">
                        {t('event.people')}
                    </h3>
                    <ul className="flex flex-col gap-1.5">
                        {event.participants.map((p) => (
                            <li key={p.id} className="flex items-center justify-between gap-2">
                                <span>{p.name}</span>
                                <StatusBadge
                                    status={PALETTE[p.response] ?? 'draft'}
                                    label={t(`event.reply.${p.response}`)}
                                />
                            </li>
                        ))}
                    </ul>
                </section>

                <Button asChild variant="outline" className="min-h-11 self-start">
                    <a href={calendarRoutes.ics.url(event.id)} download>
                        <CalendarPlus aria-hidden className="size-4" />
                        {t('event.ics')}
                    </a>
                </Button>

                {cancelling ? (
                    <div
                        className="flex flex-col gap-2 rounded-md bg-danger-bg p-3 text-danger"
                        role="alert"
                    >
                        <p className="font-medium">{t('event.cancelTitle')}</p>
                        <p>{t('event.cancelHelp')}</p>
                        {event.repeats ? <ScopeChoice value={scope} onChange={setScope} /> : null}
                    </div>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
