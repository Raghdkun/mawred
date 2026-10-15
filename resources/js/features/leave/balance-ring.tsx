import { cn } from '@/lib/utils';

/**
 * Progress ring for a balance (handoff 4): the filled arc is what is still available. Decorative;
 * the card states the numbers in text.
 */
export function BalanceRing({
    value,
    total,
    size = 56,
    className,
}: {
    value: number;
    total: number;
    size?: number;
    className?: string;
}) {
    const stroke = 6;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const share = total > 0 ? Math.min(1, Math.max(0, value / total)) : 0;

    return (
        <svg
            aria-hidden
            width={size}
            height={size}
            viewBox={`0 0 ${size} ${size}`}
            className={cn('shrink-0 -rotate-90 rtl:-scale-x-100', className)}
        >
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                strokeWidth={stroke}
                className="stroke-muted"
            />
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                strokeWidth={stroke}
                strokeLinecap="round"
                strokeDasharray={circumference}
                strokeDashoffset={circumference * (1 - share)}
                className="stroke-primary transition-[stroke-dashoffset] duration-500 ease-soft motion-reduce:transition-none"
            />
        </svg>
    );
}
