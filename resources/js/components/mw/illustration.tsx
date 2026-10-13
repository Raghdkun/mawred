import { cn } from '@/lib/utils';

/**
 * Illustrations from design/illustrations/current, light and dark (switches with the theme
 * class, not the media query, so it follows the appearance setting).
 */
const files = import.meta.glob<string>('../../../../design/illustrations/current/svg/*/*.svg', {
    eager: true,
    query: '?url',
    import: 'default',
});

export type IllustrationName =
    | 'empty-candidates'
    | 'empty-employees'
    | 'empty-inbox'
    | 'empty-integrations'
    | 'empty-policies'
    | 'empty-reports'
    | 'empty-requests'
    | 'empty-schedule'
    | 'empty-search'
    | 'empty-trainings'
    | 'scene-login-hero'
    | 'scene-onboarding-1'
    | 'scene-onboarding-2'
    | 'scene-onboarding-3'
    | 'scene-setup-all-set'
    | 'scene-setup-welcome'
    | 'success-approved'
    | 'success-connected'
    | 'success-submitted'
    | 'system-403'
    | 'system-404'
    | 'system-500'
    | 'system-maintenance'
    | 'system-offline'
    | 'people-lineup'
    | 'certificate-frame';

function url(name: IllustrationName, theme: 'light' | 'dark'): string | undefined {
    return files[`../../../../design/illustrations/current/svg/${theme}/mw-${name}.svg`];
}

export function Illustration({
    name,
    className,
    alt = '',
    priority = false,
}: {
    name: IllustrationName;
    className?: string;
    /** Decorative by default; pass alt text when the image carries meaning. */
    alt?: string;
    priority?: boolean;
}) {
    const loading = priority ? 'eager' : 'lazy';

    return (
        <>
            <img
                src={url(name, 'light')}
                alt={alt}
                loading={loading}
                className={cn('block dark:hidden', className)}
            />
            <img
                src={url(name, 'dark')}
                alt={alt}
                loading={loading}
                aria-hidden
                className={cn('hidden dark:block', className)}
            />
        </>
    );
}
