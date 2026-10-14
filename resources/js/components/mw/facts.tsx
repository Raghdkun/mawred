import { type ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Label / value pairs for detail screens (two columns from sm up). */
export function Facts({
    items,
    className,
}: {
    items: Array<[string, ReactNode]>;
    className?: string;
}) {
    return (
        <dl className={cn('grid gap-x-6 gap-y-4 sm:grid-cols-2', className)}>
            {items.map(([label, value]) => (
                <div key={label} className="flex min-w-0 flex-col gap-0.5">
                    <dt className="text-caption text-muted-foreground">{label}</dt>
                    <dd className="text-sm break-words text-foreground">{value ?? '—'}</dd>
                </div>
            ))}
        </dl>
    );
}
