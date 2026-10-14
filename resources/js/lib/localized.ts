/** Picks a translatable value for the UI language, falling back to English, then anything set. */
export function localized(
    value: Record<string, string> | null | undefined,
    locale: string,
): string {
    if (!value) {
        return '';
    }

    return value[locale] || value.en || Object.values(value).find(Boolean) || '';
}

/** A person's name in the UI language: preferred name, else Arabic in Arabic, else English. */
export function personName(
    person: { nameEn: string; nameAr: string | null; preferredName: string | null },
    locale: string,
): string {
    if (person.preferredName) {
        return person.preferredName;
    }

    return locale === 'ar' && person.nameAr ? person.nameAr : person.nameEn;
}
