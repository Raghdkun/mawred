import type {} from 'altcha/types/react';
import { useEffect, useRef } from 'react';
import { useLocale } from '@/hooks/use-locale';

/**
 * ALTCHA proof-of-work (D10): a self-hosted, cookie-free check that runs in the browser. Loaded
 * only on public pages. Reports the payload once verified, or null when it resets/expires.
 */
export function AltchaField({
    challengeUrl,
    onChange,
}: {
    challengeUrl: string;
    onChange: (payload: string | null) => void;
}) {
    const { locale } = useLocale();
    const ref = useRef<HTMLElement>(null);

    useEffect(() => {
        void import('altcha');
        void import('altcha/i18n/ar');
    }, []);

    useEffect(() => {
        const element = ref.current;

        if (!element) return;

        const listener = (event: Event) => {
            const detail = (event as CustomEvent<{ state: string; payload?: string }>).detail;
            onChange(detail.state === 'verified' && detail.payload ? detail.payload : null);
        };

        element.addEventListener('statechange', listener);

        return () => element.removeEventListener('statechange', listener);
    }, [onChange]);

    return <altcha-widget ref={ref} challenge={challengeUrl} language={locale} name="altcha" />;
}
