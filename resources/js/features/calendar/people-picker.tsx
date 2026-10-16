import { X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker } from '@/components/mw/async-picker';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';

export type Person = { id: string; name: string };
type ApiPerson = {
    id: string;
    name: string;
    name_ar: string | null;
    position: Record<string, string> | null;
};

/** Pick colleagues to invite or check (handoff 27): search-first, chips for the chosen ones. */
export function PeoplePicker({
    value,
    onChange,
    label,
    exclude = [],
}: {
    value: Person[];
    onChange: (people: Person[]) => void;
    label: string;
    exclude?: string[];
}) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();

    return (
        <div className="flex flex-col gap-2">
            <AsyncPicker<ApiPerson>
                endpoint="calendar/people"
                queryKey="calendar-people"
                label={label}
                placeholder={t('finder.addPerson')}
                value={null}
                toOption={(p) => ({
                    id: p.id,
                    label: locale === 'ar' && p.name_ar ? p.name_ar : p.name,
                    description: p.position
                        ? (p.position[locale] ?? p.position.en ?? undefined)
                        : undefined,
                })}
                onChange={(option) => {
                    if (
                        option &&
                        !value.some((p) => p.id === option.id) &&
                        !exclude.includes(option.id)
                    ) {
                        onChange([...value, { id: option.id, name: option.label }]);
                    }
                }}
            />
            {value.length > 0 ? (
                <ul className="flex flex-wrap gap-2" aria-label={label}>
                    {value.map((person) => (
                        <li
                            key={person.id}
                            className="flex items-center gap-1 rounded-full border border-border bg-muted ps-3 pe-1 text-small"
                        >
                            {person.name}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-8 rounded-full"
                                aria-label={t('finder.remove', { name: person.name })}
                                onClick={() => onChange(value.filter((p) => p.id !== person.id))}
                            >
                                <X aria-hidden className="size-3.5" />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
