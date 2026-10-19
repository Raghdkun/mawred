import { useTranslation } from 'react-i18next';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { cn } from '@/lib/utils';

const ENROLLMENT: Record<string, WorkflowStatus> = {
    requested: 'pending',
    enrolled: 'in-review',
    completed: 'approved',
    rejected: 'rejected',
    cancelled: 'cancelled',
};

const COURSE: Record<string, WorkflowStatus> = {
    draft: 'draft',
    published: 'approved',
    archived: 'cancelled',
};

export function EnrollmentStatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const { t } = useTranslation('training');

    return (
        <StatusBadge
            status={ENROLLMENT[status] ?? 'draft'}
            label={t(`enrollmentStatus.${status}`)}
            className={className}
        />
    );
}

export function CourseStatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation('training');

    return (
        <StatusBadge
            status={COURSE[status] ?? 'draft'}
            label={t(`courseStatus.${status}`)}
            className={className}
        />
    );
}

/** "Due 12 Nov" or, past the date, an overdue chip in the danger colour (D100). */
export function DueChip({
    dueOn,
    overdue,
    label,
    className,
}: {
    dueOn: string;
    overdue: boolean;
    label: string;
    className?: string;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full border px-2 py-0.5 text-caption',
                overdue
                    ? 'border-status-rejected-border bg-status-rejected-bg text-status-rejected-fg'
                    : 'border-border text-text-secondary',
                className,
            )}
            data-due={dueOn}
        >
            {label}
        </span>
    );
}
