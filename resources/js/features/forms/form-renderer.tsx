import { zodResolver } from '@hookform/resolvers/zod';
import { type ReactNode, useEffect, useMemo, useState } from 'react';
import { Controller, useForm, useWatch } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useFormLocale } from './form-locale';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { buildZodSchema, defaultValues } from './build-zod-schema';
import { FieldInput } from './field-input';
import { isRequired, resolveForm } from './resolve';
import {
    type FormField,
    type FormRuleContext,
    type FormSchema,
    acceptsInput,
    allFields,
    textOf,
} from './schema';

type Props = {
    schema: FormSchema;
    context: FormRuleContext;
    uploadUrl: string;
    onSubmit: (values: Record<string, unknown>) => void;
    submitting?: boolean;
    /** Server errors keyed by field key (or `key.part`), e.g. from a 422. */
    serverErrors?: Record<string, string>;
    submitLabel?: string;
    /** Rendered above the submit button (e.g. the ALTCHA widget on public forms). */
    footer?: ReactNode;
    disabled?: boolean;
    /** Answers to start from (e.g. a returned request being corrected). */
    initialValues?: Record<string, unknown>;
};

/**
 * Renders an MFS v1 form (ADR-0008): pages with a stepper, sections, fields. Visibility and
 * calculated values follow `resolveForm`, validation `buildZodSchema`; the server re-checks
 * everything and its errors map back onto fields.
 */
export function FormRenderer({
    schema,
    context,
    uploadUrl,
    onSubmit,
    submitting,
    serverErrors,
    submitLabel,
    footer,
    disabled,
    initialValues,
}: Props) {
    const { t } = useTranslation('forms');
    const locale = useFormLocale();
    const fields = useMemo(() => allFields(schema), [schema]);
    const resolver = useMemo(
        () => zodResolver(buildZodSchema(schema, context, t)),
        [schema, context, t],
    );
    const form = useForm<Record<string, unknown>>({
        defaultValues: { ...defaultValues(schema, context), ...pickInputs(initialValues, fields) },
        resolver,
        mode: 'onTouched',
    });
    const [page, setPage] = useState(0);
    const values = useWatch({ control: form.control });
    const { values: resolved, hidden } = resolveForm(fields, values, context);
    const scope = { ...context, field: resolved };
    const pages = schema.pages;
    const last = page === pages.length - 1;

    useEffect(() => {
        for (const [key, message] of Object.entries(serverErrors ?? {})) {
            const field = key.replace(/^values\./, '').split('.')[0];

            if (field && fields.some((candidate) => candidate.key === field)) {
                form.setError(field, { type: 'server', message });
            }
        }
    }, [serverErrors, fields, form]);

    const pageKeys = (index: number) =>
        pages[index]?.sections.flatMap((section) =>
            section.fields
                .filter((f) => acceptsInput(f.type) && !hidden.includes(f.key))
                .map((f) => f.key),
        ) ?? [];

    const next = async () => {
        if (await form.trigger(pageKeys(page))) {
            setPage((current) => Math.min(current + 1, pages.length - 1));
        }
    };

    return (
        <form
            noValidate
            onSubmit={form.handleSubmit(onSubmit, (errors) => {
                const first = pages.findIndex((_, index) =>
                    pageKeys(index).some((key) => key in errors),
                );
                if (first >= 0) setPage(first);
            })}
            className="flex flex-col gap-6"
        >
            {pages.length > 1 ? (
                <ol aria-label={t('renderer.steps')} className="flex flex-wrap gap-2">
                    {pages.map((p, index) => (
                        <li key={p.key}>
                            <span
                                aria-current={index === page ? 'step' : undefined}
                                className={cn(
                                    'inline-flex h-8 items-center gap-2 rounded-full border px-3 text-small',
                                    index === page
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-text-secondary',
                                )}
                            >
                                <span className="tabular-nums">
                                    {formatNumber(index + 1, locale)}
                                </span>
                                {textOf(p.title, locale)}
                            </span>
                        </li>
                    ))}
                </ol>
            ) : null}

            {pages.map((p, index) => (
                <div key={p.key} hidden={index !== page} className="flex flex-col gap-6">
                    {p.sections.map((section) => (
                        <fieldset key={section.key} className="flex flex-col gap-5">
                            {section.title?.en || section.title?.ar ? (
                                <legend className="mb-1 text-h4">
                                    {textOf(section.title, locale)}
                                </legend>
                            ) : null}
                            {section.description ? (
                                <p className="text-small text-text-secondary">
                                    {textOf(section.description, locale)}
                                </p>
                            ) : null}
                            {section.fields
                                .filter((field) => !hidden.includes(field.key))
                                .map((field) => (
                                    <FieldBlock
                                        key={field.key}
                                        field={field}
                                        required={isRequired(field, scope)}
                                    >
                                        {(ids) =>
                                            field.type === 'calculated' ? (
                                                <output
                                                    id={ids.input}
                                                    className="text-body font-semibold tabular-nums"
                                                >
                                                    {resolved[field.key] === null ||
                                                    resolved[field.key] === undefined
                                                        ? '—'
                                                        : typeof resolved[field.key] === 'number'
                                                          ? formatNumber(
                                                                resolved[field.key] as number,
                                                                locale,
                                                            )
                                                          : String(resolved[field.key])}
                                                </output>
                                            ) : field.type === 'note' ? null : (
                                                <Controller
                                                    name={field.key}
                                                    control={form.control}
                                                    render={({ field: control, fieldState }) => (
                                                        <>
                                                            <FieldInput
                                                                field={field}
                                                                id={ids.input}
                                                                value={control.value}
                                                                onChange={(value) => {
                                                                    control.onChange(value);
                                                                    form.clearErrors(field.key);
                                                                }}
                                                                invalid={Boolean(fieldState.error)}
                                                                describedBy={
                                                                    [
                                                                        field.help
                                                                            ? ids.help
                                                                            : null,
                                                                        fieldState.error
                                                                            ? ids.error
                                                                            : null,
                                                                    ]
                                                                        .filter(Boolean)
                                                                        .join(' ') || undefined
                                                                }
                                                                defaultCurrency={
                                                                    context.requester?.currency ??
                                                                    null
                                                                }
                                                                uploadUrl={uploadUrl}
                                                                disabled={disabled}
                                                            />
                                                            {fieldState.error?.message ? (
                                                                <p
                                                                    id={ids.error}
                                                                    role="alert"
                                                                    className="text-caption text-danger"
                                                                >
                                                                    {fieldState.error.message}
                                                                </p>
                                                            ) : null}
                                                        </>
                                                    )}
                                                />
                                            )
                                        }
                                    </FieldBlock>
                                ))}
                        </fieldset>
                    ))}
                </div>
            ))}

            {last ? footer : null}

            <div className="flex flex-wrap items-center gap-3">
                {page > 0 ? (
                    <Button type="button" variant="outline" onClick={() => setPage(page - 1)}>
                        {t('renderer.back')}
                    </Button>
                ) : null}
                {last ? (
                    <Button type="submit" size="lg" loading={submitting} disabled={disabled}>
                        {submitLabel ?? t('renderer.submit')}
                    </Button>
                ) : (
                    <Button type="button" size="lg" onClick={() => void next()}>
                        {t('renderer.next')}
                    </Button>
                )}
            </div>
        </form>
    );
}

function FieldBlock({
    field,
    required,
    children,
}: {
    field: FormField;
    required: boolean;
    children: (ids: { input: string; help: string; error: string }) => ReactNode;
}) {
    const { t } = useTranslation('forms');
    const locale = useFormLocale();
    const ids = {
        input: `field-${field.key}`,
        help: `field-${field.key}-help`,
        error: `field-${field.key}-error`,
    };

    if (field.type === 'note') {
        return (
            <div className="flex flex-col gap-1">
                <p className="text-body font-semibold">{textOf(field.label, locale)}</p>
                {field.help ? (
                    <p className="text-small whitespace-pre-line text-text-secondary">
                        {textOf(field.help, locale)}
                    </p>
                ) : null}
            </div>
        );
    }

    const labelled = [
        'radio',
        'checkboxes',
        'multiselect',
        'boolean',
        'date_range',
        'file',
        'calculated',
    ].includes(field.type);

    return (
        <div className="flex flex-col gap-1.5">
            {labelled ? (
                <span id={`${ids.input}-label`} className="text-sm font-medium">
                    {textOf(field.label, locale)}
                    {required ? <span className="text-danger"> *</span> : null}
                    {required ? <span className="sr-only"> ({t('renderer.required')})</span> : null}
                </span>
            ) : (
                <label htmlFor={ids.input} className="text-sm font-medium">
                    {textOf(field.label, locale)}
                    {required ? (
                        <span aria-hidden className="text-danger">
                            {' '}
                            *
                        </span>
                    ) : null}
                    {required ? <span className="sr-only"> ({t('renderer.required')})</span> : null}
                </label>
            )}
            {field.help ? (
                <p id={ids.help} className="text-caption text-muted-foreground">
                    {textOf(field.help, locale)}
                </p>
            ) : null}
            {children(ids)}
        </div>
    );
}

/** Only answers to fields that take input, so stale keys from an older version are dropped. */
function pickInputs(
    values: Record<string, unknown> | undefined,
    fields: ReturnType<typeof allFields>,
): Record<string, unknown> {
    if (!values) return {};

    return Object.fromEntries(
        fields
            .filter(
                (field) =>
                    acceptsInput(field.type) &&
                    values[field.key] !== undefined &&
                    values[field.key] !== null,
            )
            .map((field) => [field.key, values[field.key]]),
    );
}
