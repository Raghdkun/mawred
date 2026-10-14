import { describe, expect, it } from 'vitest';
import { localized, personName } from './localized';

describe('localized', () => {
    it('falls back from the UI language to English to anything set', () => {
        expect(localized({ en: 'Riyadh', ar: 'الرياض' }, 'ar')).toBe('الرياض');
        expect(localized({ en: 'Riyadh', ar: '' }, 'ar')).toBe('Riyadh');
        expect(localized({ ar: 'الرياض' }, 'en')).toBe('الرياض');
        expect(localized(null, 'en')).toBe('');
    });

    it('prefers the preferred name, then the Arabic name in Arabic', () => {
        const person = { nameEn: 'Sara Haddad', nameAr: 'سارة حداد', preferredName: null };
        expect(personName(person, 'ar')).toBe('سارة حداد');
        expect(personName(person, 'en')).toBe('Sara Haddad');
        expect(personName({ ...person, preferredName: 'Sasa' }, 'ar')).toBe('Sasa');
    });
});
