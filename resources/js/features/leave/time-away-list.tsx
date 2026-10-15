import { Link } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import {
    AlertDialog,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { ApiError, postJson } from '@/lib/api';
import requests from '@/routes/requests';
import LeaveApi from '@/actions/App/Http/Controllers/Api/V1/Leave/LeaveController';
import { amountLabel, type LeaveEvent, rangeLabel } from './format';

const STATUS: Record<string, WorkflowStatus> = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    cancelled: 'cancelled',
};

/** Someone's time away for a year, newest first; HR can cancel approved leave. */
export function TimeAwayList({
    events,
    canCancel,
    onChanged,
}: {
    events: LeaveEvent[];
    canCancel: boolean;
    onChanged: () => void;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const [cancelling, setCancelling] = useState<LeaveEvent | null>(null);
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    if (events.length === 0) {
        return <p className="text-small text-muted-foreground">{t('timeAway.empty')}</p>;
    }

    const cancel = async () => {
        if (!cancelling) return;
        setBusy(true);
        setError(null);

        try {
            await postJson(LeaveApi.cancel.url(cancelling.id), { reason });
            toast.success(t('timeAway.cancelled'));
            setCancelling(null);
            setReason('');
            onChanged();
        } catch (e) {
            setError(e instanceof ApiError ? e.message : t('common:states.error.title'));
        } finally {
            setBusy(false);
        }
    };

    return (
        <>
            <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                {events.map((event) => (
                    <li
                        key={event.id}
                        data-leave-event={event.id}
                        className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:gap-4"
                    >
                        <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                            <span className="font-medium">
                                {event.typeName ?? t(`timeAway.kind.${event.kind}`)}
                                {event.halfDay
                                    ? ` · ${t(`timeAway.halfDay.${event.halfDay}`)}`
                                    : null}
                            </span>
                            <span className="text-small text-text-secondary">
                                {rangeLabel(event.from, event.to, locale)}
                                {event.fromTime && event.toTime ? (
                                    <>
                                        {' · '}
                                        <bdi dir="ltr">
                                            {event.fromTime}–{event.toTime}
                                        </bdi>
                                    </>
                                ) : null}
                                {' · '}
                                {event.kind === 'hourly'
                                    ? amountLabel(t, event.minutes ?? 0, 'minutes', locale)
                                    : amountLabel(t, event.amount, 'days', locale)}
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <StatusBadge
                                status={STATUS[event.status] ?? 'pending'}
                                label={t(`timeAway.status.${event.status}`)}
                            />
                            {event.requestId ? (
                                <Button asChild variant="ghost" size="sm" className="min-h-11">
                                    <Link href={requests.show.url(event.requestId)}>
                                        {t('timeAway.openRequest')}
                                    </Link>
                                </Button>
                            ) : null}
                            {canCancel && event.status === 'approved' ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="min-h-11"
                                    onClick={() => setCancelling(event)}
                                >
                                    {t('timeAway.cancel')}
                                </Button>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ul>

            <AlertDialog
                open={cancelling !== null}
                onOpenChange={(open) => !open && setCancelling(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('timeAway.cancelTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('timeAway.cancelDescription')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="leave-cancel-reason">{t('timeAway.cancelReason')}</Label>
                        <Textarea
                            id="leave-cancel-reason"
                            value={reason}
                            maxLength={300}
                            aria-invalid={error ? true : undefined}
                            aria-describedby={error ? 'leave-cancel-error' : undefined}
                            onChange={(e) => setReason(e.target.value)}
                        />
                        {error ? (
                            <p
                                id="leave-cancel-error"
                                role="alert"
                                className="text-small text-danger"
                            >
                                {error}
                            </p>
                        ) : null}
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('timeAway.keep')}</AlertDialogCancel>
                        <Button
                            type="button"
                            variant="destructive"
                            loading={busy}
                            disabled={reason.trim() === ''}
                            onClick={cancel}
                        >
                            {t('timeAway.cancelConfirm')}
                        </Button>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
