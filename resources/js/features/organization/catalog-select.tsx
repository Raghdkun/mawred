import { useTranslation } from 'react-i18next';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';

const NONE = '__none';

/** Select over a short reference list (cost centres, grades, types…) with an optional "none". */
export function CatalogSelect({
    id,
    value,
    onChange,
    options,
    allowNone = true,
}: {
    id: string;
    value: string | null;
    onChange: (value: string | null) => void;
    options: Array<{ id: string; code: string; name: Record<string, string> }>;
    allowNone?: boolean;
}) {
    const { t } = useTranslation('common');
    const { locale } = useLocale();

    return (
        <Select
            value={value ?? NONE}
            onValueChange={(next) => onChange(next === NONE ? null : next)}
        >
            <SelectTrigger id={id}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent className="max-h-80">
                {allowNone ? <SelectItem value={NONE}>{t('none')}</SelectItem> : null}
                {options.map((option) => (
                    <SelectItem key={option.id} value={option.id}>
                        {localized(option.name, locale)} · {option.code}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
