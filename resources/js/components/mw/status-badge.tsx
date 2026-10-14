import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

/** The 8 workflow states (tokens.css status palette, DSData badge). */
export type WorkflowStatus =
    | 'draft'
    | 'pending'
    | 'in-review'
    | 'approved'
    | 'rejected'
    | 'cancelled'
    | 'escalated'
    | 'returned';

const STYLES: Record<WorkflowStatus, string> = {
    draft: 'bg-status-draft-bg text-status-draft-fg border-status-draft-border',
    pending: 'bg-status-pending-bg text-status-pending-fg border-status-pending-border',
    'in-review': 'bg-status-in-review-bg text-status-in-review-fg border-status-in-review-border',
    approved: 'bg-status-approved-bg text-status-approved-fg border-status-approved-border',
    rejected: 'bg-status-rejected-bg text-status-rejected-fg border-status-rejected-border',
    cancelled: 'bg-status-cancelled-bg text-status-cancelled-fg border-status-cancelled-border',
    escalated: 'bg-status-escalated-bg text-status-escalated-fg border-status-escalated-border',
    returned: 'bg-status-returned-bg text-status-returned-fg border-status-returned-border',
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: WorkflowStatus;
    /** Overrides the default label (e.g. "Completed" in the approved colours). */
    label?: string;
    className?: string;
}) {
    const { t } = useTranslation('common');

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full border ps-2 pe-2.5 text-caption font-medium whitespace-nowrap',
                STYLES[status],
                className,
            )}
        >
            <span aria-hidden className="size-1.5 rounded-full bg-current" />
            {label ?? t(`status.${status}`)}
        </span>
    );
}

export type IntegrationHealth = 'healthy' | 'degraded' | 'disconnected' | 'not-configured';

const HEALTH: Record<IntegrationHealth, { badge: string; dot: string }> = {
    healthy: { badge: 'bg-health-healthy-bg text-health-healthy-fg', dot: 'bg-health-healthy-dot' },
    degraded: {
        badge: 'bg-health-degraded-bg text-health-degraded-fg',
        dot: 'bg-health-degraded-dot',
    },
    disconnected: {
        badge: 'bg-health-disconnected-bg text-health-disconnected-fg',
        dot: 'bg-health-disconnected-dot',
    },
    'not-configured': {
        badge: 'bg-health-not-configured-bg text-health-not-configured-fg',
        dot: 'bg-health-not-configured-dot',
    },
};

export function HealthBadge({
    health,
    className,
}: {
    health: IntegrationHealth;
    className?: string;
}) {
    const { t } = useTranslation('common');

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center gap-1.5 rounded-full px-2.5 text-caption font-medium',
                HEALTH[health].badge,
                className,
            )}
        >
            <span aria-hidden className={cn('size-2 rounded-full', HEALTH[health].dot)} />
            {t(`health.${health}`)}
        </span>
    );
}
