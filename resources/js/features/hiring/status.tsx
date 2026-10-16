import { useTranslation } from 'react-i18next';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { minorDigits } from '@/features/forms/upload';
import { type Locale } from '@/i18n';
import { formatNumber } from '@/lib/format';

/** Requisition statuses in the workflow status palette (D84). */
const REQUISITION_TONES: Record<string, WorkflowStatus> = {
    pending: 'pending',
    open: 'approved',
    on_hold: 'escalated',
    filled: 'in-review',
    closed: 'cancelled',
    rejected: 'rejected',
    cancelled: 'cancelled',
};

export function RequisitionStatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const { t } = useTranslation('hiring');

    return (
        <StatusBadge
            status={REQUISITION_TONES[status] ?? 'draft'}
            label={t(`requisitionStatus.${status}`)}
            className={className}
        />
    );
}

const POSTING_TONES: Record<string, WorkflowStatus> = {
    draft: 'draft',
    published: 'approved',
    closed: 'cancelled',
};

export function PostingStatusBadge({ status, className }: { status: string; className?: string }) {
    const { t } = useTranslation('hiring');

    return (
        <StatusBadge
            status={POSTING_TONES[status] ?? 'draft'}
            label={t(`postingStatus.${status}`)}
            className={className}
        />
    );
}

const APPLICATION_TONES: Record<string, WorkflowStatus> = {
    active: 'in-review',
    hired: 'approved',
    rejected: 'rejected',
    withdrawn: 'cancelled',
};

export function ApplicationStatusBadge({
    status,
    className,
}: {
    status: string;
    className?: string;
}) {
    const { t } = useTranslation('hiring');

    return (
        <StatusBadge
            status={APPLICATION_TONES[status] ?? 'draft'}
            label={t(`applicationStatus.${status}`)}
            className={className}
        />
    );
}

/** An amount in minor units of its currency, in the UI language's digits. */
export function formatMinor(minor: number, currency: string, locale: Locale): string {
    return formatNumber(minor / 10 ** minorDigits(currency), locale, {
        style: 'currency',
        currency,
        maximumFractionDigits: 0,
    });
}

/** Whole days from an instant to `now` (never negative). */
export function daysSince(iso: string, now: number): number {
    return Math.max(0, Math.floor((now - new Date(iso).getTime()) / 86_400_000));
}

export const REJECTION_REASONS = [
    'not_qualified',
    'knockout',
    'better_candidate',
    'salary',
    'no_show',
    'withdrew',
    'position_closed',
    'other',
] as const;
