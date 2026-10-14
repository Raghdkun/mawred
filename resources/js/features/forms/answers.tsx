import { Download } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatHijri, formatNumber } from '@/lib/format';
import {
    type DateRangeValue,
    type FormField,
    type FormSchema,
    type MoneyValue,
    type StoredFile,
    allFields,
    hasValue,
    textOf,
} from './schema';
import { minorDigits } from './upload';

/** A submission's answers, labelled from the version it was made against. */
export function Answers({
    schema,
    answers,
    display,
    fileUrl,
}: {
    schema: FormSchema;
    answers: Record<string, unknown>;
    display: Record<string, string>;
    fileUrl: (fileId: string) => string;
}) {
    const { locale } = useLocale();

    return (
        <dl className="flex flex-col divide-y divide-border">
            {allFields(schema)
                .filter((field) => hasValue(field.type) && field.key in answers)
                .map((field) => (
                    <div
                        key={field.key}
                        className="grid gap-1 py-3 sm:grid-cols-[minmax(0,14rem)_minmax(0,1fr)] sm:gap-4"
                    >
                        <dt className="text-small text-muted-foreground">
                            {textOf(field.label, locale) || field.key}
                        </dt>
                        <dd className="text-sm">
                            <AnswerValue
                                field={field}
                                value={answers[field.key]}
                                display={display[field.key]}
                                fileUrl={fileUrl}
                            />
                        </dd>
                    </div>
                ))}
        </dl>
    );
}

function AnswerValue({
    field,
    value,
    display,
    fileUrl,
}: {
    field: FormField;
    value: unknown;
    display?: string;
    fileUrl: (fileId: string) => string;
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const optionLabel = (option: string) =>
        textOf(field.options?.find((o) => o.value === option)?.label, locale) || option;

    if (
        value === null ||
        value === undefined ||
        value === '' ||
        (Array.isArray(value) && value.length === 0)
    ) {
        return <span className="text-muted-foreground">—</span>;
    }

    switch (field.type) {
        case 'boolean':
            return <>{value ? t('answers.yes') : t('answers.no')}</>;
        case 'money': {
            const money = value as MoneyValue;
            return (
                <span className="tabular-nums">
                    {formatNumber(money.amount_minor / 10 ** minorDigits(money.currency), locale, {
                        style: 'currency',
                        currency: money.currency,
                    })}
                </span>
            );
        }
        case 'number':
        case 'calculated':
            return (
                <span className="tabular-nums">
                    {typeof value === 'number' ? formatNumber(value, locale) : String(value)}
                </span>
            );
        case 'date':
            return (
                <span className="flex flex-col">
                    {formatDate(String(value), locale, { dateStyle: 'long', timeZone: 'UTC' })}
                    <span className="text-caption text-muted-foreground">
                        {formatHijri(`${String(value)}T12:00:00Z`, locale)}
                    </span>
                </span>
            );
        case 'date_range': {
            const range = value as DateRangeValue;
            return (
                <>
                    {range.from
                        ? formatDate(range.from, locale, { dateStyle: 'medium', timeZone: 'UTC' })
                        : '—'}{' '}
                    –{' '}
                    {range.to
                        ? formatDate(range.to, locale, { dateStyle: 'medium', timeZone: 'UTC' })
                        : '—'}
                </>
            );
        }
        case 'select':
        case 'radio':
            return <>{optionLabel(String(value))}</>;
        case 'multiselect':
        case 'checkboxes':
            return <>{(value as string[]).map(optionLabel).join(locale === 'ar' ? '، ' : ', ')}</>;
        case 'file':
            return (
                <ul className="flex flex-col gap-1">
                    {(value as StoredFile[]).map((file) => (
                        <li key={file.id}>
                            <a
                                href={fileUrl(file.id)}
                                className="inline-flex items-center gap-1.5 text-link hover:underline"
                            >
                                <Download aria-hidden className="size-4" />
                                <bdi>{file.name}</bdi>
                            </a>
                        </li>
                    ))}
                </ul>
            );
        case 'email':
        case 'phone':
        case 'time':
            return <bdi dir="ltr">{String(value)}</bdi>;
        case 'employee':
        case 'department':
        case 'branch':
        case 'leave_type':
            return <>{display ?? String(value)}</>;
        default:
            return <span className="whitespace-pre-line">{String(value)}</span>;
    }
}
