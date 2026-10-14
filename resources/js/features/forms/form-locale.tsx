import { createContext, useContext } from 'react';
import { useLocale } from '@/hooks/use-locale';
import { type Locale } from '@/i18n';

const FormLocaleContext = createContext<Locale | null>(null);

/** Lets the builder preview a form in EN or AR whatever the UI language (handoff 14). */
export const FormLocaleProvider = FormLocaleContext.Provider;

export function useFormLocale(): Locale {
    const override = useContext(FormLocaleContext);
    const { locale } = useLocale();

    return override ?? locale;
}
