import { usePage } from '@inertiajs/react';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { type NavItem } from './navigation';

/** The count beside a nav item (open approvals). Hidden at zero; the label carries it for screen readers. */
export function NavBadge({ item, className }: { item: NavItem; className?: string }) {
    const { locale } = useLocale();
    const { approvals } = usePage().props;
    const count = item.badge === 'approvals' ? (approvals?.open ?? 0) : 0;

    if (count === 0) {
        return null;
    }

    return (
        <span
            aria-hidden
            className={cn(
                'flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] leading-none font-semibold text-primary-foreground tabular-nums',
                className,
            )}
        >
            {formatNumber(Math.min(count, 99), locale)}
        </span>
    );
}

export function useNavBadgeCount(item: NavItem): number {
    const { approvals } = usePage().props;

    return item.badge === 'approvals' ? (approvals?.open ?? 0) : 0;
}
