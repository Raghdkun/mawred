import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

/** Employment status on the shared status palette (DSData badge). Active people get no badge in lists. */
const STYLES: Record<string, string> = {
    pending: 'bg-status-pending-bg text-status-pending-fg border-status-pending-border',
    active: 'bg-status-approved-bg text-status-approved-fg border-status-approved-border',
    suspended: 'bg-status-escalated-bg text-status-escalated-fg border-status-escalated-border',
    terminated: 'bg-status-cancelled-bg text-status-cancelled-fg border-status-cancelled-border',
};

export function EmployeeStatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation('people');

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full border ps-2 pe-2.5 text-caption font-medium whitespace-nowrap',
                STYLES[status] ?? STYLES.active,
                className,
            )}
        >
            <span aria-hidden className="size-1.5 rounded-full bg-current" />
            {t(`statuses.${status}`)}
        </span>
    );
}
