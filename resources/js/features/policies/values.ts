import { type TFunction } from 'i18next';
import { type Locale } from '@/i18n';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { weekdayName } from '@/lib/regions';
import { type PolicyKey, type PolicyValue } from './types';

export type Unit = 'days' | 'minutes' | 'hours' | 'months' | 'multiplier';

/** One input of a value editor. Server-side value classes stay the source of truth for rules. */
export type FieldSpec =
    | {
          name: string;
          kind: 'number';
          unit: Unit;
          step?: number;
          min?: number;
          max?: number;
          nullable?: boolean;
      }
    | { name: string; kind: 'select'; options: string[]; segmented?: boolean }
    | { name: string; kind: 'switch' }
    | { name: string; kind: 'time' }
    | { name: string; kind: 'weekdays' }
    | { name: string; kind: 'tenure' }
    | { name: string; kind: 'multipliers'; fields: string[] };

/** Editors by the key's `editor` id (the value class, e.g. GraceValue → `grace`). */
export const EDITORS: Record<string, FieldSpec[]> = {
    leave_rule: [
        { name: 'entitlement', kind: 'number', unit: 'days', step: 0.5, min: 0, max: 3650 },
        { name: 'accrual', kind: 'select', options: ['monthly', 'yearly_upfront', 'none'] },
        { name: 'proRata', kind: 'select', options: ['daily', 'monthly', 'none'] },
        { name: 'rounding', kind: 'select', options: ['half', 'whole', 'none'], segmented: true },
        { name: 'eligibleAfterDays', kind: 'number', unit: 'days', min: 0, max: 3650 },
        {
            name: 'carryOverMax',
            kind: 'number',
            unit: 'days',
            step: 0.5,
            min: 0,
            max: 3650,
            nullable: true,
        },
        {
            name: 'carryOverExpiresMonths',
            kind: 'number',
            unit: 'months',
            min: 1,
            max: 60,
            nullable: true,
        },
        {
            name: 'maxBalance',
            kind: 'number',
            unit: 'days',
            step: 0.5,
            min: 0,
            max: 3650,
            nullable: true,
        },
        { name: 'tenureSteps', kind: 'tenure' },
    ],
    hourly_permission: [
        { name: 'minutesPerMonth', kind: 'number', unit: 'minutes', min: 0, max: 10000 },
        { name: 'maxMinutesPerRequest', kind: 'number', unit: 'minutes', min: 0, max: 1440 },
    ],
    weekend: [{ name: 'days', kind: 'weekdays' }],
    work_hours: [
        { name: 'startsAt', kind: 'time' },
        { name: 'endsAt', kind: 'time' },
        { name: 'breakMinutes', kind: 'number', unit: 'minutes', min: 0, max: 480 },
    ],
    overtime: [
        { name: 'enabled', kind: 'switch' },
        {
            name: 'multipliers',
            kind: 'multipliers',
            fields: ['weekday', 'weekend', 'holiday', 'night'],
        },
        { name: 'nightStartsAt', kind: 'time' },
        { name: 'nightEndsAt', kind: 'time' },
        { name: 'dailyThresholdMinutes', kind: 'number', unit: 'minutes', min: 0, max: 600 },
        { name: 'roundingMinutes', kind: 'number', unit: 'minutes', min: 1, max: 60 },
        {
            name: 'monthlyCapMinutes',
            kind: 'number',
            unit: 'minutes',
            min: 0,
            max: 44640,
            nullable: true,
        },
        { name: 'requiresApproval', kind: 'switch' },
    ],
    grace: [
        { name: 'lateMinutes', kind: 'number', unit: 'minutes', min: 0, max: 240 },
        { name: 'earlyLeaveMinutes', kind: 'number', unit: 'minutes', min: 0, max: 240 },
        { name: 'countFrom', kind: 'select', options: ['scheduled', 'grace_end'], segmented: true },
    ],
    geofence_mode: [
        { name: 'mode', kind: 'select', options: ['block', 'flag', 'off'], segmented: true },
    ],
    rest: [
        { name: 'minRestHours', kind: 'number', unit: 'hours', min: 0, max: 24 },
        { name: 'maxConsecutiveDays', kind: 'number', unit: 'days', min: 1, max: 31 },
        { name: 'maxWeeklyHours', kind: 'number', unit: 'hours', min: 1, max: 168 },
    ],
    probation: [
        { name: 'probationDays', kind: 'number', unit: 'days', min: 0, max: 365 },
        { name: 'extensionDays', kind: 'number', unit: 'days', min: 0, max: 365 },
        { name: 'employeeNoticeDays', kind: 'number', unit: 'days', min: 0, max: 365 },
        { name: 'employerNoticeDays', kind: 'number', unit: 'days', min: 0, max: 365 },
    ],
};

/** Editor sections in display order (handoff 17's section nav). */
export const SECTIONS = [
    'leave',
    'work_week',
    'overtime',
    'attendance',
    'rest',
    'employment',
] as const;

export function keyLabel(key: PolicyKey, t: TFunction, locale: Locale): string {
    if (key.leaveType) {
        return t('keys.leaveType', { name: localized(key.leaveType.name, locale) });
    }

    return t(`keys.${key.id}`);
}

const num = (value: unknown): number => (typeof value === 'number' ? value : Number(value ?? 0));

/** Weekday ISO numbers starting from a given first day (handoff 17). */
export function weekFrom(start: number): number[] {
    return Array.from({ length: 7 }, (_, i) => ((start - 1 + i) % 7) + 1);
}

/** A one-line, human summary of a value (lists, explainer, mobile read-only view). */
export function summarize(
    editor: string,
    value: PolicyValue | null,
    t: TFunction,
    locale: Locale,
): string {
    if (!value) {
        return t('summary.notSet');
    }

    const n = (v: unknown, digits = 2) =>
        formatNumber(num(v), locale, { maximumFractionDigits: digits });

    switch (editor) {
        case 'leave_rule':
            return t('summary.leave', {
                entitlement: n(value.entitlement),
                accrual: t(`options.accrual.${String(value.accrual)}`),
            });
        case 'grace':
            return t('summary.grace', {
                late: n(value.lateMinutes),
                early: n(value.earlyLeaveMinutes),
            });
        case 'overtime':
            return value.enabled === false
                ? t('summary.off')
                : t('summary.overtime', { weekday: n(value.weekday), weekend: n(value.weekend) });
        case 'work_hours':
            return t('summary.hours', {
                starts: String(value.startsAt),
                ends: String(value.endsAt),
                breakMinutes: n(value.breakMinutes),
            });
        case 'rest':
            return t('summary.rest', {
                hours: n(value.minRestHours),
                days: n(value.maxConsecutiveDays),
            });
        case 'hourly_permission':
            return t('summary.hourly', { minutes: n(value.minutesPerMonth) });
        case 'probation':
            return t('summary.probation', { days: n(value.probationDays) });
        case 'geofence_mode':
            return t(`options.mode.${String(value.mode)}`);
        case 'weekend': {
            const days = Array.isArray(value.days) ? (value.days as number[]) : [];

            return days.length === 0
                ? t('summary.noWeekend')
                : days
                      .map((day) => weekdayName(day, locale, 'long'))
                      .join(locale === 'ar' ? '، ' : ', ');
        }
        default:
            return '';
    }
}

/** Fill a value with the key's defaults so every editor field has something to show. */
export function withDefaults(key: PolicyKey, value: PolicyValue | null): PolicyValue {
    return { ...key.default, ...(value ?? {}) };
}

export function sameValue(a: PolicyValue | null, b: PolicyValue | null): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}
