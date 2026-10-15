import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { amountLabel, type LeaveBalance, type LeaveEvent, rangeLabel } from './format';

export type RequestLeave = { event: LeaveEvent; balance: LeaveBalance | null } | null;

/**
 * What a time-away request takes, on the request and in the approvals inbox: dates, working
 * days, and the requester's balance now (the hold is already counted while it is pending).
 */
export function LeaveSummary({
    leave,
    className,
}: {
    leave: NonNullable<RequestLeave>;
    className?: string;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const { event, balance } = leave;
    const unit = event.kind === 'hourly' ? 'minutes' : (balance?.unit ?? 'days');
    const amount = event.kind === 'hourly' ? (event.minutes ?? 0) : event.amount;

    return (
        <section
            aria-label={t('quote.title')}
            data-leave-summary
            className={cn('rounded-md bg-muted p-4 text-small', className)}
        >
            <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5">
                <dt className="text-text-secondary">
                    {event.typeName ?? t(`timeAway.kind.${event.kind}`)}
                </dt>
                <dd className="text-end">
                    {rangeLabel(event.from, event.to, locale)}
                    {event.halfDay ? ` · ${t(`timeAway.halfDay.${event.halfDay}`)}` : null}
                </dd>
                <dt className="text-text-secondary">{t('quote.takes')}</dt>
                <dd className="text-end font-medium tabular-nums">
                    {amountLabel(t, amount, unit, locale)}
                </dd>
                {balance ? (
                    <>
                        <dt className="text-text-secondary">{t('statement.summary.available')}</dt>
                        <dd
                            className={cn(
                                'text-end tabular-nums',
                                balance.available < 0 && 'text-danger',
                            )}
                        >
                            {balance.available < 0 ? '−' : ''}
                            {amountLabel(t, Math.abs(balance.available), balance.unit, locale)}
                        </dd>
                    </>
                ) : null}
            </dl>
        </section>
    );
}
