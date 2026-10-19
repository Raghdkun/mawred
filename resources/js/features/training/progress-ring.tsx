import { cn } from '@/lib/utils';

/**
 * Progress ring for learning (handoff 34): the arc is the share done. The percentage in the
 * middle is for sighted users; the ring itself is labelled for assistive tech.
 */
export function ProgressRing({
    value,
    size = 56,
    label,
    className,
}: {
    value: number;
    size?: number;
    label: string;
    className?: string;
}) {
    const stroke = size < 48 ? 5 : 6;
    const radius = (size - stroke) / 2;
    const circumference = 2 * Math.PI * radius;
    const share = Math.min(1, Math.max(0, value / 100));

    return (
        <span
            role="img"
            aria-label={label}
            className={cn('relative inline-flex shrink-0 items-center justify-center', className)}
            style={{ width: size, height: size }}
        >
            <svg
                aria-hidden
                width={size}
                height={size}
                viewBox={`0 0 ${size} ${size}`}
                className="absolute inset-0 -rotate-90 rtl:-scale-x-100"
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
                    className={cn(
                        'transition-[stroke-dashoffset] duration-500 ease-soft motion-reduce:transition-none',
                        value >= 100 ? 'stroke-success' : 'stroke-primary',
                    )}
                />
            </svg>
            <span aria-hidden className="text-caption font-semibold tabular-nums">
                {Math.round(value)}%
            </span>
        </span>
    );
}
