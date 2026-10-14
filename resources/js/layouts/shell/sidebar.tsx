import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { MawredLogo } from '@/components/mw/mawred-logo';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useAccess } from '@/hooks/use-access';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { NavBadge, useNavBadgeCount } from './nav-badge';
import { hrefOf, isActive, navigation, type NavItem } from './navigation';
import { NavIcon } from './nav-icon';

/**
 * Sidebar (DSNavigation): 260 px at ≥ 1280, 72 px with tooltips between 768 and 1280, hidden
 * below 768 where the bottom tab bar takes over. Sits on the reading-start side.
 */
export function Sidebar() {
    const { t } = useTranslation('shell');
    const { company, setup } = usePage().props;
    const { locale } = useLocale();
    const { can } = useAccess();
    const { currentUrl } = useCurrentUrl();
    const path = new URL(currentUrl, 'http://x').pathname;
    const companyName = company.name?.[locale] ?? company.name?.en;

    return (
        <aside
            aria-label={t('nav.main')}
            className="sticky top-0 hidden h-svh w-[72px] shrink-0 flex-col gap-1 border-e border-sidebar-border bg-sidebar px-3 py-4 md:flex xl:w-[260px]"
        >
            <div className="flex h-10 items-center gap-2 px-1 xl:px-3">
                {company.logoUrl ? (
                    <img
                        src={company.logoUrl}
                        alt={companyName ?? t('companyLogo')}
                        className="max-h-8 max-w-full object-contain"
                    />
                ) : companyName ? (
                    <span className="hidden truncate text-h4 text-foreground xl:inline">
                        {companyName}
                    </span>
                ) : (
                    <MawredLogo className="hidden h-7 w-auto xl:block" />
                )}
                {!company.logoUrl ? (
                    <MawredLogo variant="symbol" className="mx-auto size-8 xl:hidden" />
                ) : null}
            </div>

            <nav className="mt-4 flex flex-1 flex-col gap-1 overflow-y-auto">
                {navigation(setup.complete).map((group) => {
                    const items = group.items.filter(
                        (item) => !item.permission || can(item.permission),
                    );

                    if (items.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group.key} className="flex flex-col gap-1">
                            <p className="hidden px-3 pt-3 pb-1 text-overline text-muted-foreground uppercase xl:block">
                                {t(group.label)}
                            </p>
                            <span
                                aria-hidden
                                className="mx-auto my-2 h-px w-6 bg-sidebar-border xl:hidden"
                            />
                            {items.map((item) => {
                                const active = isActive(item, path);
                                const link = (
                                    <Link
                                        href={hrefOf(item.href)}
                                        prefetch
                                        aria-current={active ? 'page' : undefined}
                                        className={cn(
                                            'flex h-10 items-center justify-center gap-3 rounded-md px-3 text-sm text-text-secondary transition-colors duration-150 ease-soft hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-sidebar-ring focus-visible:outline-none xl:justify-start',
                                            active &&
                                                'bg-sidebar-accent font-medium text-sidebar-accent-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground',
                                        )}
                                    >
                                        <span className="relative flex">
                                            <NavIcon icon={item.icon} />
                                            <NavBadge
                                                item={item}
                                                className="absolute -end-2.5 -top-2 h-4 min-w-4 px-1 text-[10px] xl:hidden"
                                            />
                                        </span>
                                        <span className="sr-only xl:not-sr-only xl:flex-1 xl:truncate">
                                            {t(item.label)}
                                        </span>
                                        <NavBadge item={item} className="hidden xl:flex" />
                                        <BadgeLabel item={item} />
                                    </Link>
                                );

                                return (
                                    <Tooltip key={item.key}>
                                        <TooltipTrigger asChild>{link}</TooltipTrigger>
                                        <TooltipContent
                                            side={locale === 'ar' ? 'left' : 'right'}
                                            className="xl:hidden"
                                        >
                                            {t(item.label)}
                                        </TooltipContent>
                                    </Tooltip>
                                );
                            })}
                        </div>
                    );
                })}
            </nav>

            <div className="mt-auto flex items-center justify-center gap-2 px-1 pt-3 text-caption text-muted-foreground xl:justify-start xl:px-3">
                <span className="hidden xl:inline">{t('poweredBy')}</span>
                <MawredLogo variant="symbol" className="size-5" />
                <span className="hidden font-medium text-text-secondary xl:inline">
                    {t('mawred')}
                </span>
            </div>
        </aside>
    );
}

function BadgeLabel({ item }: { item: NavItem }) {
    const { t } = useTranslation('shell');
    const count = useNavBadgeCount(item);

    return count > 0 ? <span className="sr-only">{t('nav.openCount', { count })}</span> : null;
}
