import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { hrefOf, isActive, mobileTabs } from './navigation';
import { NavIcon } from './nav-icon';

/**
 * Bottom tab bar below 768 px (DSNavigation): 72 px bar, 44 px targets, labels always visible.
 * The centred "New request" action joins in Phase 3 with the request center.
 */
export function BottomTabBar() {
    const { t } = useTranslation('shell');
    const { currentUrl } = useCurrentUrl();
    const path = new URL(currentUrl, 'http://x').pathname;

    return (
        <nav
            aria-label={t('nav.main')}
            className="fixed inset-x-0 bottom-0 z-40 flex h-[72px] items-end justify-around border-t border-border bg-card px-2 pb-[max(10px,env(safe-area-inset-bottom))] md:hidden"
        >
            {mobileTabs().map((item) => {
                const active = isActive(item, path);

                return (
                    <Link
                        key={item.key}
                        href={hrefOf(item.href)}
                        aria-current={active ? 'page' : undefined}
                        className={cn(
                            'flex min-h-11 w-16 flex-col items-center justify-end gap-[3px] text-[11px] text-muted-foreground',
                            active && 'font-medium text-primary',
                        )}
                    >
                        <NavIcon icon={item.icon} />
                        <span>{t(item.label)}</span>
                    </Link>
                );
            })}
        </nav>
    );
}
