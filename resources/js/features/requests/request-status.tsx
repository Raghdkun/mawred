import { useTranslation } from 'react-i18next';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';

type RequestStatus = App.Domain.Requests.Enums.RequestStatus;

const STATUS: Record<RequestStatus, WorkflowStatus> = {
    in_review: 'in-review',
    returned: 'returned',
    approved: 'approved',
    rejected: 'rejected',
    completed: 'approved',
    cancelled: 'cancelled',
};

/** A request's status in the shared workflow palette; "completed" reads as done. */
export function RequestStatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation('requests');
    const known = status in STATUS ? (status as RequestStatus) : 'in_review';

    return (
        <StatusBadge
            status={STATUS[known]}
            label={known === 'completed' ? t('status.completed') : undefined}
            className={className}
        />
    );
}

export const REQUEST_STATUSES: RequestStatus[] = [
    'in_review',
    'returned',
    'approved',
    'rejected',
    'completed',
    'cancelled',
];
