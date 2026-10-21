import { usePage } from '@inertiajs/react';
import { motion, useReducedMotion } from 'motion/react';
import { type ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useFlashToast } from '@/hooks/use-flash-toast';
import { useLocale } from '@/hooks/use-locale';
import { pageTransition } from '@/lib/motion';
import { PwaPrompts } from '@/features/pwa/pwa-prompts';
import { BottomTabBar } from './shell/bottom-tab-bar';
import { CommandPalette } from './shell/command-palette';
import { ImpersonationBanner } from './shell/impersonation-banner';
import { Sidebar } from './shell/sidebar';
import { TopBar } from './shell/top-bar';

/**
 * App shell (handoff screen 3). Sidebar on the reading-start side (right in Arabic), top bar,
 * bottom tab bar on phones, ⌘K palette, impersonation banner, page-enter motion.
 */
export default function AppShellLayout({ children }: { children: ReactNode }) {
    const { t } = useTranslation('shell');
    const { url } = usePage();
    const { dir } = useLocale();
    const reduced = useReducedMotion() ?? false;
    const [paletteOpen, setPaletteOpen] = useState(false);
    const transition = pageTransition(dir, reduced);

    useFlashToast();

    return (
        <div className="min-h-svh bg-background">
            <a
                href="#main"
                className="sr-only z-[60] rounded-md bg-primary px-4 py-2 text-primary-foreground focus:not-sr-only focus:fixed focus:start-4 focus:top-4"
            >
                {t('skipToContent')}
            </a>
            <ImpersonationBanner />
            <div className="flex">
                <Sidebar />
                <div className="flex min-w-0 flex-1 flex-col">
                    <TopBar onOpenPalette={() => setPaletteOpen(true)} />
                    <motion.main
                        id="main"
                        key={url.split('?')[0]}
                        tabIndex={-1}
                        initial="initial"
                        animate="animate"
                        variants={transition.variants}
                        transition={transition.transition}
                        className="mx-auto w-full max-w-[1280px] flex-1 px-4 pt-6 pb-28 focus:outline-none md:px-6 md:pb-10"
                    >
                        {children}
                    </motion.main>
                </div>
            </div>
            <BottomTabBar />
            <PwaPrompts />
            <CommandPalette open={paletteOpen} onOpenChange={setPaletteOpen} />
        </div>
    );
}
