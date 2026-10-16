import { router, usePage } from '@inertiajs/react';
import {
    Building2,
    Languages,
    LoaderCircle,
    LogOut,
    Moon,
    Network,
    Sun,
    UserRound,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
    CommandDialog,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { useGlobalSearch } from '@/features/search/use-global-search';
import { useAccess } from '@/hooks/use-access';
import { useAppearance } from '@/hooks/use-appearance';
import { useLocale } from '@/hooks/use-locale';
import { namespacesForPage, prepareLocale } from '@/i18n';
import { logout } from '@/routes';
import locale from '@/routes/locale';
import { canSee, hrefOf, navigation } from './navigation';
import { NavIcon } from './nav-icon';

const RESULT_ICONS: Record<string, typeof UserRound> = {
    employees: UserRound,
    branches: Building2,
    departments: Network,
};

/**
 * ⌘K / Ctrl+K command palette (brief §17): navigation, quick actions and global search
 * (ADR-0013). Pages and actions filter locally; people and places come from the server, already
 * scoped to the viewer, so cmdk's own filtering is off.
 */
export function CommandPalette({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('shell');
    const page = usePage();
    const { can } = useAccess();
    const { locale: current } = useLocale();
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const [search, setSearch] = useState('');
    const results = useGlobalSearch(search, open);
    const needle = search.trim().toLocaleLowerCase();
    const matches = (label: string) => needle === '' || label.toLocaleLowerCase().includes(needle);

    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
                event.preventDefault();
                onOpenChange(!open);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, [open, onOpenChange]);

    const run = (action: () => void) => {
        onOpenChange(false);
        setSearch('');
        action();
    };

    const pages = navigation(page.props.setup.complete)
        .flatMap((group) => group.items)
        .filter(
            (item) =>
                canSee(item, can, page.props.interviews?.panel ?? false) && matches(t(item.label)),
        );

    const actions = [
        {
            key: 'language',
            label: t('palette.switchLanguage'),
            icon: <Languages aria-hidden />,
            onSelect: () => void switchLanguage(),
        },
        {
            key: 'theme',
            label: t('palette.toggleTheme'),
            icon: resolvedAppearance === 'dark' ? <Sun aria-hidden /> : <Moon aria-hidden />,
            onSelect: () => updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark'),
        },
        {
            key: 'sign-out',
            label: t('menu.signOut'),
            icon: <LogOut aria-hidden className="rtl:-scale-x-100" />,
            onSelect: () => router.post(logout.url()),
        },
    ].filter((action) => matches(action.label));

    const groups = needle.length >= 2 ? (results.data ?? []) : [];
    const searching = needle.length >= 2 && results.isFetching;
    const nothing = pages.length === 0 && actions.length === 0 && groups.length === 0 && !searching;

    const switchLanguage = async () => {
        const next = current === 'ar' ? 'en' : 'ar';
        await prepareLocale(next, namespacesForPage(page.component));
        router.patch(
            locale.update.url(),
            { locale: next },
            { preserveScroll: true, preserveState: true },
        );
    };

    return (
        <CommandDialog
            open={open}
            onOpenChange={(next) => {
                onOpenChange(next);
                if (!next) {
                    setSearch('');
                }
            }}
            shouldFilter={false}
            title={t('palette.title')}
            description={t('palette.description')}
        >
            <CommandInput
                placeholder={t('searchPlaceholder')}
                value={search}
                onValueChange={setSearch}
            />
            <CommandList>
                {nothing ? (
                    <div role="status" className="py-6 text-center text-sm text-muted-foreground">
                        {results.isError ? t('palette.searchFailed') : t('palette.empty')}
                    </div>
                ) : null}
                {searching && groups.length === 0 ? (
                    <div
                        role="status"
                        className="flex items-center justify-center gap-2 py-4 text-sm text-muted-foreground"
                    >
                        <LoaderCircle aria-hidden className="size-4 animate-spin" />
                        {t('palette.searching')}
                    </div>
                ) : null}
                {groups.map((group) => {
                    const Icon = RESULT_ICONS[group.type] ?? UserRound;

                    return (
                        <CommandGroup key={group.type} heading={t(`palette.groups.${group.type}`)}>
                            {group.items.map((hit) => (
                                <CommandItem
                                    key={`${hit.type}:${hit.id}`}
                                    value={`${hit.type}:${hit.id}`}
                                    onSelect={() => run(() => router.visit(hit.url))}
                                >
                                    <Icon aria-hidden className="size-4" />
                                    <span className="flex min-w-0 flex-col">
                                        <bdi className="truncate">{hit.title}</bdi>
                                        {hit.subtitle ? (
                                            <bdi className="truncate text-small text-muted-foreground">
                                                {hit.subtitle}
                                            </bdi>
                                        ) : null}
                                    </span>
                                    {hit.status ? (
                                        <span className="ms-auto rounded-full border border-border px-2 text-caption whitespace-nowrap text-muted-foreground">
                                            {t(`palette.status.${hit.status}`)}
                                        </span>
                                    ) : null}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    );
                })}
                {pages.length > 0 ? (
                    <CommandGroup heading={t('palette.pages')}>
                        {pages.map((item) => (
                            <CommandItem
                                key={item.key}
                                value={`page:${item.key}`}
                                onSelect={() => run(() => router.visit(hrefOf(item.href)))}
                            >
                                <NavIcon icon={item.icon} className="size-4" />
                                {t(item.label)}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                ) : null}
                {actions.length > 0 ? (
                    <CommandGroup heading={t('palette.actions')}>
                        {actions.map((action) => (
                            <CommandItem
                                key={action.key}
                                value={`action:${action.key}`}
                                onSelect={() => run(action.onSelect)}
                            >
                                {action.icon}
                                {action.label}
                            </CommandItem>
                        ))}
                    </CommandGroup>
                ) : null}
            </CommandList>
        </CommandDialog>
    );
}
