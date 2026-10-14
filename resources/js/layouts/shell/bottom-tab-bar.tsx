import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn } from '@/lib/utils';
import { NavBadge, useNavBadgeCount } from './nav-badge';
import { hrefOf, isActive, mobileTabs, type NavItem } from './navigation';
import { NavIcon } from './nav-icon';

/**
 * Bottom tab bar below 768 px (DSNavigation): 72 px bar, 44 px targets, labels always visible.
 * The centred "New request" action is raised and filled.
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
                        {item.primary ? (
                            <span className="-mt-5 flex size-12 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-md">
                                <NavIcon icon={item.icon} />
                            </span>
                        ) : (
                            <span className="relative flex">
                                <NavIcon icon={item.icon} />
                                <NavBadge
                                    item={item}
                                    className="absolute -end-2.5 -top-2 h-4 min-w-4 px-1 text-[10px]"
                                />
                            </span>
                        )}
                        <span>{t(item.label)}</span>
                        <TabCount item={item} />
                    </Link>
                );
            })}
        </nav>
    );
}

function TabCount({ item }: { item: NavItem }) {
    const { t } = useTranslation('shell');
    const count = useNavBadgeCount(item);

    return count > 0 ? <span className="sr-only">{t('nav.openCount', { count })}</span> : null;
}
