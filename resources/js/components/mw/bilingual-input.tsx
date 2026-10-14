import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Field } from './field';

export type Bilingual = { en: string; ar: string };

/** English + Arabic text pair for translatable names; each input keeps its own direction. */
export function BilingualInput({
    id,
    label,
    value,
    onChange,
    errors,
    errorKey,
    requireArabic = false,
}: {
    id: string;
    label: string;
    value: Bilingual;
    onChange: (value: Bilingual) => void;
    errors: Partial<Record<string, string>>;
    errorKey: string;
    requireArabic?: boolean;
}) {
    const { t } = useTranslation('common');

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                id={`${id}-en`}
                label={`${label} (${t('languages.en')})`}
                error={errors[`${errorKey}.en`] ?? errors[errorKey]}
                required
            >
                <Input
                    id={`${id}-en`}
                    dir="ltr"
                    lang="en"
                    value={value.en}
                    onChange={(event) => onChange({ ...value, en: event.target.value })}
                    required
                />
            </Field>
            <Field
                id={`${id}-ar`}
                label={`${label} (${t('languages.ar')})`}
                error={errors[`${errorKey}.ar`]}
                required={requireArabic}
            >
                <Input
                    id={`${id}-ar`}
                    dir="rtl"
                    lang="ar"
                    value={value.ar}
                    onChange={(event) => onChange({ ...value, ar: event.target.value })}
                    required={requireArabic}
                />
            </Field>
        </div>
    );
}
