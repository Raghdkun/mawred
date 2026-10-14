import { Clock } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { intlLocale } from '@/lib/format';
import { cn } from '@/lib/utils';

/** Time left on a task's SLA (handoff 13): neutral, "due soon" under a day, overdue in the escalated colour. */
export function SlaBadge({
    dueAt,
    overdue,
    className,
}: {
    dueAt: string | null;
    overdue: boolean;
    className?: string;
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [now] = useState(() => Date.now());

    if (!dueAt) {
        return null;
    }

    const hours = (new Date(dueAt).getTime() - now) / 3_600_000;
    const relative = new Intl.RelativeTimeFormat(intlLocale(locale), { numeric: 'auto' });
    const text =
        Math.abs(hours) < 24
            ? relative.format(Math.round(hours), 'hour')
            : relative.format(Math.round(hours / 24), 'day');
    const soon = !overdue && hours < 24;

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1 rounded-full border px-2 text-caption whitespace-nowrap',
                overdue
                    ? 'border-status-escalated-border bg-status-escalated-bg text-status-escalated-fg'
                    : soon
                      ? 'border-status-pending-border bg-status-pending-bg text-status-pending-fg'
                      : 'border-border text-text-secondary',
                className,
            )}
        >
            <Clock aria-hidden className="size-3" />
            {overdue ? t('sla.overdue', { when: text }) : t('sla.due', { when: text })}
        </span>
    );
}
