import { router, usePage } from '@inertiajs/react';
import { Languages, LogOut, Moon, Sun } from 'lucide-react';
import { useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandShortcut,
} from '@/components/ui/command';
import { useAccess } from '@/hooks/use-access';
import { useAppearance } from '@/hooks/use-appearance';
import { useLocale } from '@/hooks/use-locale';
import { namespacesForPage, prepareLocale } from '@/i18n';
import { logout } from '@/routes';
import locale from '@/routes/locale';
import { hrefOf, navigation } from './navigation';
import { NavIcon } from './nav-icon';

/**
 * ⌘K / Ctrl+K command palette (brief §17): navigation and quick actions. Global search across
 * employees, candidates and requests joins as those modules ship (ADR-0013).
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
        action();
    };

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
            onOpenChange={onOpenChange}
            title={t('palette.title')}
            description={t('palette.description')}
        >
            <CommandInput placeholder={t('searchPlaceholder')} />
            <CommandList>
                <CommandEmpty>{t('palette.empty')}</CommandEmpty>
                <CommandGroup heading={t('palette.pages')}>
                    {navigation(page.props.setup.complete)
                        .flatMap((group) => group.items)
                        .filter((item) => !item.permission || can(item.permission))
                        .map((item) => (
                            <CommandItem
                                key={item.key}
                                value={t(item.label)}
                                onSelect={() => run(() => router.visit(hrefOf(item.href)))}
                            >
                                <NavIcon icon={item.icon} className="size-4" />
                                {t(item.label)}
                            </CommandItem>
                        ))}
                </CommandGroup>
                <CommandGroup heading={t('palette.actions')}>
                    <CommandItem
                        value={t('palette.switchLanguage')}
                        onSelect={() => run(() => void switchLanguage())}
                    >
                        <Languages aria-hidden />
                        {t('palette.switchLanguage')}
                    </CommandItem>
                    <CommandItem
                        value={t('palette.toggleTheme')}
                        onSelect={() =>
                            run(() =>
                                updateAppearance(resolvedAppearance === 'dark' ? 'light' : 'dark'),
                            )
                        }
                    >
                        {resolvedAppearance === 'dark' ? <Sun aria-hidden /> : <Moon aria-hidden />}
                        {t('palette.toggleTheme')}
                    </CommandItem>
                    <CommandItem
                        value={t('menu.signOut')}
                        onSelect={() => run(() => router.post(logout.url()))}
                    >
                        <LogOut aria-hidden className="rtl:-scale-x-100" />
                        {t('menu.signOut')}
                        <CommandShortcut />
                    </CommandItem>
                </CommandGroup>
            </CommandList>
        </CommandDialog>
    );
}
