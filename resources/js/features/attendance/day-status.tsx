import { useTranslation } from 'react-i18next';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';

const PALETTE: Record<string, WorkflowStatus> = {
    scheduled: 'draft',
    present: 'approved',
    late: 'pending',
    absent: 'rejected',
    incomplete: 'returned',
    on_leave: 'in-review',
    off: 'draft',
};

/** An attendance day's status in the shared status palette, always with its word (never colour alone). */
export function DayStatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation('attendance');

    return (
        <StatusBadge
            status={PALETTE[status] ?? 'draft'}
            label={t(`status.${status}`)}
            className={className}
        />
    );
}
