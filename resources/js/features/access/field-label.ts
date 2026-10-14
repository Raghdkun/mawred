import type { TFunction } from 'i18next';

/** Field labels: built-in sensitive keys come from i18n; custom fields carry their own labels. */
export function fieldLabel(
    field: string,
    labels: Record<string, Record<string, string>>,
    locale: string,
    t: TFunction,
): string {
    const custom = labels[field];

    if (custom) {
        return custom[locale] ?? custom.en ?? field;
    }

    return t(`fields.names.${field}`, { defaultValue: field });
}
