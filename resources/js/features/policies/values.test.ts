import { type TFunction } from 'i18next';
import { describe, expect, it } from 'vitest';
import { sameValue, summarize, weekFrom, withDefaults } from './values';

const t = ((key: string, values?: Record<string, unknown>) =>
    values ? `${key}:${JSON.stringify(values)}` : key) as unknown as TFunction;

describe('policy values', () => {
    it('starts the week on any day and wraps around', () => {
        expect(weekFrom(1)).toEqual([1, 2, 3, 4, 5, 6, 7]);
        expect(weekFrom(7)).toEqual([7, 1, 2, 3, 4, 5, 6]);
    });

    it('summarises each editor, and says when nothing is set', () => {
        expect(summarize('grace', null, t, 'en')).toBe('summary.notSet');
        expect(summarize('grace', { lateMinutes: 15, earlyLeaveMinutes: 0 }, t, 'en')).toBe(
            'summary.grace:{"late":"15","early":"0"}',
        );
        expect(summarize('overtime', { enabled: false }, t, 'en')).toBe('summary.off');
        expect(summarize('geofence_mode', { mode: 'flag' }, t, 'en')).toBe('options.mode.flag');
        expect(summarize('weekend', { days: [] }, t, 'en')).toBe('summary.noWeekend');
        expect(summarize('weekend', { days: [5, 6] }, t, 'en')).toBe('Friday, Saturday');
    });

    it('fills missing fields from the key default and compares values', () => {
        const key = {
            id: 'attendance.grace',
            section: 'attendance',
            editor: 'grace',
            default: { lateMinutes: 15, earlyLeaveMinutes: 0, countFrom: 'scheduled' },
            branchFallback: false,
            affects: [],
            leaveType: null,
        };

        expect(withDefaults(key, { lateMinutes: 5 })).toEqual({
            lateMinutes: 5,
            earlyLeaveMinutes: 0,
            countFrom: 'scheduled',
        });
        expect(sameValue({ a: 1 }, { a: 1 })).toBe(true);
        expect(sameValue({ a: 1 }, null)).toBe(false);
    });
});
