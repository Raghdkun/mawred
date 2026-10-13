import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { Illustration, type IllustrationName } from './illustration';

type Kind = 'empty' | 'no-results' | 'loading' | 'error' | 'offline' | 'denied' | 'not-found';

const ILLUSTRATIONS: Record<Exclude<Kind, 'loading' | 'empty'>, IllustrationName> = {
    'no-results': 'empty-search',
    error: 'system-500',
    offline: 'system-offline',
    denied: 'system-403',
    'not-found': 'system-404',
};

/**
 * The five page states every screen has (brief §17): empty, loading, error, offline and
 * permission-denied, plus no-results and not-found, using the illustration set.
 */
export function PageState({
    kind,
    illustration,
    title,
    description,
    action,
    className,
}: {
    kind: Kind;
    /** Required for `empty`: the module's empty-state illustration. */
    illustration?: IllustrationName;
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
}) {
    const { t } = useTranslation('common');

    if (kind === 'loading') {
        return (
            <div role="status" aria-live="polite" className={cn('flex flex-col gap-3', className)}>
                <span className="sr-only">{t('states.loading')}</span>
                <Skeleton className="h-6 w-1/3" />
                <Skeleton className="h-4 w-2/3" />
                <Skeleton className="h-4 w-1/2" />
                <Skeleton className="mt-4 h-32 w-full" />
            </div>
        );
    }

    const image = illustration ?? (kind === 'empty' ? 'empty-search' : ILLUSTRATIONS[kind]);

    return (
        <div
            role={kind === 'error' || kind === 'denied' ? 'alert' : 'status'}
            className={cn(
                'mx-auto flex max-w-md flex-col items-center gap-4 px-4 py-12 text-center',
                className,
            )}
        >
            <Illustration name={image} className="h-40 w-auto" />
            <div className="flex flex-col gap-1.5">
                <h2 className="text-h4 text-foreground">{title ?? t(`states.${kind}.title`)}</h2>
                <p className="text-small text-muted-foreground">
                    {description ?? t(`states.${kind}.description`)}
                </p>
            </div>
            {action}
        </div>
    );
}
