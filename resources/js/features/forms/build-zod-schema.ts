import { type TFunction } from 'i18next';
import { z } from 'zod';
import { evaluate, type RuleContext } from '@/lib/rules';
import { isRequired, resolveForm } from './resolve';
import {
    type DateRangeValue,
    type FormField,
    type FormRuleContext,
    type FormSchema,
    type MoneyValue,
    PHONE_PATTERN,
    acceptsInput,
    allFields,
    hasOptions,
    isMulti,
} from './schema';

const DATE = /^\d{4}-\d{2}-\d{2}$/;
const TIME = /^([01]\d|2[0-3]):[0-5]\d$/;
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const isBlank = (value: unknown) =>
    value === null ||
    value === undefined ||
    value === '' ||
    (Array.isArray(value) && value.length === 0);

/** A date bound: a literal date, or a rule over `ctx` such as today. */
function bound(value: unknown, context: RuleContext): string | null {
    if (value === undefined || value === null) {
        return null;
    }

    try {
        const result = typeof value === 'string' ? value : evaluate(value, context);

        return typeof result === 'string' && DATE.test(result) ? result : null;
    } catch {
        return null;
    }
}

/**
 * The message for one field's value, or null when it is fine. Mirrors FormRulesBuilder; the
 * server stays authoritative.
 */
export function fieldError(
    field: FormField,
    value: unknown,
    required: boolean,
    context: RuleContext,
    t: TFunction,
): string | null {
    const v = field.validation ?? {};

    if (field.type === 'boolean') {
        return required && value !== true ? t('validation.accept') : null;
    }

    if (isBlank(value)) {
        return required ? t('validation.required') : null;
    }

    const options = (field.options ?? []).map((option) => option.value);

    switch (field.type) {
        case 'text':
        case 'textarea': {
            const text = String(value).trim();
            const max = v.maxLength ?? (field.type === 'text' ? 255 : 5000);

            if (text.length > max) return t('validation.maxLength', { max });
            if (v.minLength && text.length < v.minLength)
                return t('validation.minLength', { min: v.minLength });

            return null;
        }
        case 'number': {
            const number = Number(value);

            if (!Number.isFinite(number)) return t('validation.number');
            if (v.integer && !Number.isInteger(number)) return t('validation.integer');
            if (typeof v.min === 'number' && number < v.min)
                return t('validation.min', { min: v.min });
            if (typeof v.max === 'number' && number > v.max)
                return t('validation.max', { max: v.max });

            return null;
        }
        case 'money': {
            const money = value as MoneyValue;

            if (!Number.isInteger(money.amount_minor)) return t('validation.number');
            if (typeof v.min === 'number' && money.amount_minor < v.min)
                return t('validation.tooLow');
            if (typeof v.max === 'number' && money.amount_minor > v.max)
                return t('validation.tooHigh');

            return null;
        }
        case 'email':
            return EMAIL.test(String(value)) ? null : t('validation.email');
        case 'phone':
            return PHONE_PATTERN.test(String(value)) ? null : t('validation.phone');
        case 'date': {
            const min = bound(v.min, context);
            const max = bound(v.max, context);
            const date = String(value);

            if (!DATE.test(date)) return t('validation.date');
            if (min && date < min) return t('validation.dateMin', { date: min });
            if (max && date > max) return t('validation.dateMax', { date: max });

            return null;
        }
        case 'date_range': {
            const range = value as DateRangeValue;
            const min = bound(v.min, context);
            const max = bound(v.max, context);

            if (!range.from || !range.to) return required ? t('validation.required') : null;
            if (!DATE.test(range.from) || !DATE.test(range.to)) return t('validation.date');
            if (range.to < range.from) return t('validation.rangeOrder');
            if (min && range.from < min) return t('validation.dateMin', { date: min });
            if (max && range.to > max) return t('validation.dateMax', { date: max });

            const days =
                (Date.parse(`${range.to}T00:00:00Z`) - Date.parse(`${range.from}T00:00:00Z`)) /
                    86_400_000 +
                1;

            return v.maxDays && days > v.maxDays
                ? t('validation.maxDays', { days: v.maxDays })
                : null;
        }
        case 'time':
            return TIME.test(String(value)) ? null : t('validation.time');
        case 'select':
        case 'radio':
            return options.includes(String(value)) ? null : t('validation.option');
        case 'multiselect':
        case 'checkboxes': {
            const items = value as string[];

            if (items.some((item) => !options.includes(item))) return t('validation.option');
            if (v.minItems && items.length < v.minItems)
                return t('validation.minItems', { count: v.minItems });
            if (v.maxItems && items.length > v.maxItems)
                return t('validation.maxItems', { count: v.maxItems });

            return null;
        }
        case 'file':
            return (value as unknown[]).length > (v.maxFiles ?? 1)
                ? t('validation.maxFiles', { count: v.maxFiles ?? 1 })
                : null;
        default:
            return null;
    }
}

/**
 * buildZodSchema (ADR-0008): one zod schema for the whole form. Visibility, conditional
 * required-ness and calculated values come from the same resolution as the server, so hidden
 * fields never block submitting.
 */
export function buildZodSchema(schema: FormSchema, context: FormRuleContext, t: TFunction) {
    const fields = allFields(schema);

    return z.record(z.string(), z.unknown()).superRefine((input, issue) => {
        const { values, hidden } = resolveForm(fields, input, context);
        const scope = { ...context, field: values };

        for (const field of fields) {
            if (!acceptsInput(field.type) || hidden.includes(field.key)) {
                continue;
            }

            const message = fieldError(
                field,
                values[field.key],
                isRequired(field, scope),
                scope,
                t,
            );

            if (message) {
                issue.addIssue({ code: 'custom', path: [field.key], message });
            }
        }
    });
}

/** Initial values: literal defaults, or rules over `requester`/`ctx` (e.g. today). */
export function defaultValues(
    schema: FormSchema,
    context: FormRuleContext,
): Record<string, unknown> {
    const values: Record<string, unknown> = {};

    for (const field of allFields(schema)) {
        if (!acceptsInput(field.type)) {
            continue;
        }

        let value: unknown = null;

        if (field.default !== undefined && field.default !== null) {
            try {
                value =
                    typeof field.default === 'object' && !Array.isArray(field.default)
                        ? evaluate(field.default, context)
                        : field.default;
            } catch {
                value = null;
            }
        }

        values[field.key] = value ?? emptyValue(field);
    }

    return values;
}

export function emptyValue(field: FormField): unknown {
    if (isMulti(field.type) || field.type === 'file') return [];
    if (field.type === 'boolean') return false;
    if (field.type === 'date_range') return { from: null, to: null };
    if (hasOptions(field.type)) return null;

    return null;
}
