import { useTranslation } from 'react-i18next';
import { isOvernight } from './colors';

/** "07:00–15:00", digits kept LTR; overnight shifts say so (ADR-0004). */
export function ShiftTime({
    startsAt,
    endsAt,
    className,
}: {
    startsAt: string;
    endsAt: string;
    className?: string;
}) {
    const { t } = useTranslation('schedule');

    return (
        <span className={className}>
            <bdi dir="ltr" className="tabular-nums">
                {startsAt}–{endsAt}
            </bdi>
            {isOvernight(startsAt, endsAt) ? (
                <span className="sr-only"> ({t('shift.overnight')})</span>
            ) : null}
            {isOvernight(startsAt, endsAt) ? (
                <span aria-hidden className="ms-0.5 align-super text-[0.65em]">
                    +1
                </span>
            ) : null}
        </span>
    );
}
