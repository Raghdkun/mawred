import { X } from 'lucide-react';
import { type ComponentType } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';

export type Named = { id: string; name: string };

type SinglePicker = ComponentType<{
    value: PickerOption | null;
    onChange: (option: PickerOption | null) => void;
    label: string;
    className?: string;
}>;

/**
 * Several choices from a search-first picker: pick one at a time, chips below with a remove
 * button each.
 */
export function MultiPicker({
    picker: Picker,
    label,
    value,
    onChange,
    testId,
}: {
    picker: SinglePicker;
    label: string;
    value: Named[];
    onChange: (value: Named[]) => void;
    testId?: string;
}) {
    const { t } = useTranslation('training');

    return (
        <div className="flex flex-col gap-2" data-multi={testId}>
            <Picker
                value={null}
                label={label}
                onChange={(option) => {
                    if (option && !value.some((v) => v.id === option.id)) {
                        onChange([...value, { id: option.id, name: option.label }]);
                    }
                }}
            />
            {value.length > 0 ? (
                <ul className="flex flex-wrap gap-2" aria-label={label}>
                    {value.map((item) => (
                        <li
                            key={item.id}
                            className="inline-flex items-center gap-1 rounded-full bg-muted ps-3 text-small"
                        >
                            {item.name}
                            <button
                                type="button"
                                className="inline-flex size-11 items-center justify-center rounded-full hover:bg-border/60"
                                aria-label={t('remove', { name: item.name })}
                                onClick={() => onChange(value.filter((v) => v.id !== item.id))}
                            >
                                <X aria-hidden className="size-4" />
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
