import { type ReactNode } from 'react';
import { cn } from '@/lib/utils';

/** Page title block: h1, optional description and actions at the reading end. */
export function PageHeader({
    title,
    description,
    actions,
    className,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between',
                className,
            )}
        >
            <div className="flex min-w-0 flex-col gap-1">
                <h1 className="text-h2 text-foreground">{title}</h1>
                {description ? (
                    <p className="text-body text-muted-foreground">{description}</p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">{actions}</div>
            ) : null}
        </div>
    );
}

/** A titled card section inside a page (settings, detail screens). */
export function Section({
    title,
    description,
    children,
    actions,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    actions?: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight sm:p-6',
                className,
            )}
        >
            <div className="mb-5 flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="text-h4 text-foreground">{title}</h2>
                    {description ? (
                        <p className="text-small text-muted-foreground">{description}</p>
                    ) : null}
                </div>
                {actions}
            </div>
            {children}
        </section>
    );
}
