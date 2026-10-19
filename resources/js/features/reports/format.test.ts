import { type TFunction } from 'i18next';
import { describe, expect, it } from 'vitest';
import { formatValue, isNumeric } from './format';

const t = ((key: string, values: Record<string, string>) =>
    key === 'minutes'
        ? `${values.hours}h ${values.minutes}m`
        : key === 'hoursOnly'
          ? `${values.hours}h`
          : `${values.minutes}m`) as unknown as TFunction;

describe('formatValue', () => {
    it('shows minutes as hours and minutes, dropping a zero part', () => {
        expect(formatValue(405, 'minutes', 'en', t)).toBe('6h 45m');
        expect(formatValue(45, 'minutes', 'en', t)).toBe('45m');
        expect(formatValue(120, 'minutes', 'en', t)).toBe('2h');
        expect(formatValue(-30, 'minutes', 'en', t)).toBe('-30m');
    });

    it('formats percentages and decimals, and leaves blanks as a dash', () => {
        expect(formatValue(12.5, 'percent', 'en', t)).toBe('12.5%');
        expect(formatValue(2.256, 'decimal', 'en', t)).toBe('2.26');
        expect(formatValue(null, 'number', 'en', t)).toBe('—');
    });

    it('keeps calendar dates on their day whatever the time zone', () => {
        expect(formatValue('2026-03-01', 'date', 'en', t)).toBe('Mar 1, 2026');
    });

    it('aligns only numbers at the end', () => {
        expect(isNumeric('minutes')).toBe(true);
        expect(isNumeric('date')).toBe(false);
    });
});
