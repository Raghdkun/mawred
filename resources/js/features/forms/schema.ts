import type { FormDataConvertible } from '@inertiajs/core';
import { type RuleNode } from '@/lib/rules';

/** Mawred Form Schema v1 (`mawred.form/v1`, ADR-0008, D51). Mirrors app/Domain/Forms/Support. */
export const FORM_FORMAT = 'mawred.form/v1';

export const FIELD_TYPES = [
    'text',
    'textarea',
    'number',
    'money',
    'email',
    'phone',
    'date',
    'date_range',
    'time',
    'select',
    'multiselect',
    'radio',
    'checkboxes',
    'boolean',
    'file',
    'employee',
    'department',
    'branch',
    'leave_type',
    'calculated',
    'note',
] as const;

export type FieldType = (typeof FIELD_TYPES)[number];

export type Bilingual = { en?: string | null; ar?: string | null };

export type FieldOption = { value: string; label: Bilingual };

export type FieldValidation = {
    minLength?: number;
    maxLength?: number;
    min?: number | string | RuleNode;
    max?: number | string | RuleNode;
    integer?: boolean;
    minItems?: number;
    maxItems?: number;
    maxDays?: number;
    maxFiles?: number;
    maxSizeKb?: number;
    accept?: Array<'pdf' | 'image' | 'word' | 'excel'>;
};

export type FormField = {
    key: string;
    type: FieldType;
    label: Bilingual;
    help?: Bilingual | null;
    placeholder?: Bilingual | null;
    required?: boolean | RuleNode;
    visible?: RuleNode | null;
    default?: unknown;
    calc?: RuleNode;
    options?: FieldOption[];
    currency?: string | null;
    validation?: FieldValidation;
};

export type FormSection = {
    key: string;
    title?: Bilingual | null;
    description?: Bilingual | null;
    fields: FormField[];
};

export type FormPage = { key: string; title?: Bilingual | null; sections: FormSection[] };

export type FormSchema = { format: typeof FORM_FORMAT; pages: FormPage[] };

export const PHONE_PATTERN = /^\+[1-9]\d{6,14}$/;

export const FILE_ACCEPT: Record<string, string[]> = {
    pdf: ['application/pdf'],
    image: ['image/jpeg', 'image/png', 'image/webp', 'image/heic'],
    word: [
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ],
    excel: [
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
    ],
};

/** Fields that take input (not display-only, not calculated). */
export const acceptsInput = (type: FieldType) => type !== 'note' && type !== 'calculated';
export const hasValue = (type: FieldType) => type !== 'note';
export const hasOptions = (type: FieldType) =>
    ['select', 'multiselect', 'radio', 'checkboxes'].includes(type);
export const isMulti = (type: FieldType) => type === 'multiselect' || type === 'checkboxes';
export const isPicker = (type: FieldType) =>
    ['employee', 'department', 'branch', 'leave_type'].includes(type);

export function allFields(schema: FormSchema): FormField[] {
    return schema.pages.flatMap((page) => page.sections.flatMap((section) => section.fields));
}

export function blankSchema(): FormSchema {
    return {
        format: FORM_FORMAT,
        pages: [
            {
                key: 'page_1',
                title: { en: '' },
                sections: [{ key: 'section_1', title: null, fields: [] }],
            },
        ],
    };
}

/** A UploadedFile reference as stored on a submission. */
export type StoredFile = { id: string; name: string; size: number; mime: string };

export type MoneyValue = { amount_minor: number; currency: string };
export type DateRangeValue = { from: string | null; to: string | null };

/** Schema text in one locale, falling back to English (schema text may hold nulls). */
export function textOf(text: Bilingual | null | undefined, locale: string): string {
    if (!text) {
        return '';
    }

    const value = locale === 'ar' ? text.ar || text.en : text.en || text.ar;

    return value ?? '';
}

/** What a form is filled in against (FormContext.php): the requester and `ctx` (today, calendar). */
export type FormRuleContext = {
    requester: Record<string, string | null> | null;
    ctx: {
        today?: string;
        calendar?: { weekend?: number[]; holidays?: string[] };
        balances?: Record<string, number>;
    };
};

/**
 * Inertia's payload type can't express arbitrary JSON (rule literals are `unknown`). Schemas and
 * answers are plain JSON, so they cross the router boundary through this one cast.
 */
export function asPayload(value: unknown): Record<string, FormDataConvertible> {
    return value as Record<string, FormDataConvertible>;
}
