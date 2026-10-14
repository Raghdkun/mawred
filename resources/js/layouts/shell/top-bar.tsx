import { Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { LanguageToggle } from '@/components/mw/language-toggle';
import { MawredLogo } from '@/components/mw/mawred-logo';
import { NotificationBell } from './notification-bell';
import { ProfileMenu } from './profile-menu';

/**
 * Top bar (DSNavigation): ⌘K search, language, notifications, profile. 64 px on desktop; on
 * phones it is the top app bar with the Mawred symbol and icon-only actions.
 */
export function TopBar({ onOpenPalette }: { onOpenPalette: () => void }) {
    const { t } = useTranslation('shell');
    const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);

    return (
        <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border bg-background/95 px-4 backdrop-blur-sm supports-[backdrop-filter]:bg-background/80 md:h-16 md:px-6">
            <MawredLogo variant="symbol" className="size-8 md:hidden" />

            <button
                type="button"
                onClick={onOpenPalette}
                aria-label={t('searchPlaceholder')}
                aria-keyshortcuts="Meta+K Control+K"
                className="hidden h-10 w-full max-w-sm items-center gap-2 rounded-md border border-input bg-card px-3 text-start text-sm text-muted-foreground transition-colors duration-150 ease-soft hover:border-input-hover focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:flex"
            >
                <Search aria-hidden className="size-4" />
                <span className="flex-1 truncate">{t('searchPlaceholder')}</span>
                <kbd
                    dir="ltr"
                    className="rounded-xs border border-border bg-card px-1.5 py-0.5 font-mono text-[11px] text-muted-foreground"
                >
                    {isMac ? '⌘K' : 'Ctrl K'}
                </kbd>
            </button>

            <div className="ms-auto flex items-center gap-1">
                <button
                    type="button"
                    onClick={onOpenPalette}
                    aria-label={t('searchPlaceholder')}
                    className="inline-flex size-10 items-center justify-center rounded-md text-text-secondary hover:bg-muted md:hidden"
                >
                    <Search aria-hidden className="size-5" />
                </button>
                <span className="hidden md:inline-flex">
                    <LanguageToggle />
                </span>
                <span className="md:hidden">
                    <LanguageToggle compact />
                </span>
                <NotificationBell />
                <ProfileMenu />
            </div>
        </header>
    );
}
