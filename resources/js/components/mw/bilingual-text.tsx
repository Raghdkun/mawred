import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export type Pair = { en: string; ar: string };

/**
 * An English + Arabic pair that may be optional or multi-line (postings, careers texts). Each
 * side keeps its own direction; an empty Arabic side falls back to English on the server.
 */
export function BilingualText({
    id,
    label,
    value,
    onChange,
    errors,
    errorKey,
    required = false,
    multiline = false,
    maxLength,
    hint,
}: {
    id: string;
    label: string;
    value: Pair;
    onChange: (value: Pair) => void;
    errors: Partial<Record<string, string>>;
    errorKey: string;
    required?: boolean;
    multiline?: boolean;
    maxLength?: number;
    hint?: string;
}) {
    const { t } = useTranslation('common');
    const side = (lang: 'en' | 'ar') => {
        const props = {
            id: `${id}-${lang}`,
            dir: lang === 'ar' ? 'rtl' : 'ltr',
            lang,
            value: value[lang],
            maxLength,
            required: required && lang === 'en',
        } as const;

        return multiline ? (
            <Textarea
                {...props}
                rows={8}
                onChange={(e) => onChange({ ...value, [lang]: e.target.value })}
            />
        ) : (
            <Input {...props} onChange={(e) => onChange({ ...value, [lang]: e.target.value })} />
        );
    };

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                id={`${id}-en`}
                label={`${label} (${t('languages.en')})`}
                error={errors[`${errorKey}.en`] ?? errors[errorKey]}
                required={required}
                hint={hint}
            >
                {side('en')}
            </Field>
            <Field
                id={`${id}-ar`}
                label={`${label} (${t('languages.ar')})`}
                error={errors[`${errorKey}.ar`]}
            >
                {side('ar')}
            </Field>
        </div>
    );
}
