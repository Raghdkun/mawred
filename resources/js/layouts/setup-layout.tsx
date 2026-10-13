import { type ReactNode } from 'react';
import { LanguageToggle } from '@/components/mw/language-toggle';
import { MawredLogo } from '@/components/mw/mawred-logo';
import { useFlashToast } from '@/hooks/use-flash-toast';

/** Chrome-less frame for the setup wizard, the being-set-up page and error pages. */
export default function SetupLayout({ children }: { children: ReactNode }) {
    useFlashToast();

    return (
        <div className="flex min-h-svh flex-col bg-background">
            <header className="flex h-16 items-center justify-between gap-4 border-b border-border px-4 md:px-8">
                <MawredLogo className="h-7 w-auto" />
                <LanguageToggle />
            </header>
            <main id="main" className="flex flex-1 flex-col">
                {children}
            </main>
        </div>
    );
}
