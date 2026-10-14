import { Head, Link, router, usePoll } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { SuccessRipple } from '@/components/mw/success-ripple';
import { Button } from '@/components/ui/button';
import { Progress } from '@/components/ui/progress';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    DesktopOnly,
    ImportStepper,
    type ImportStep,
} from '@/features/people/imports/import-stepper';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import people from '@/routes/people';

type Import = App.Domain.People.Data.ImportData;

type Props = {
    import: Import;
    samples: Array<Array<string | null>>;
    rowErrors: Array<{ row: number; errors: Record<string, string[]> }>;
    fields: Record<string, string>;
    requiredForCreate: string[];
};

const IGNORE = '__ignore';

function stepOf(status: string): ImportStep {
    switch (status) {
        case 'uploaded':
            return 'map';
        case 'validating':
        case 'validated':
        case 'failed':
            return 'validate';
        default:
            return 'confirm';
    }
}

/** Import wizard (handoff 9): map columns, dry-run validation, then import with live progress. */
export default function ImportShow({
    import: item,
    samples,
    rowErrors,
    fields,
    requiredForCreate,
}: Props) {
    const { t } = useTranslation('imports');
    const { locale } = useLocale();
    const [mapping, setMapping] = useState<Record<string, string>>(
        item.mapping as Record<string, string>,
    );
    const [remapping, setRemapping] = useState(item.status === 'uploaded');
    const busy = item.status === 'validating' || item.status === 'committing';
    const n = (value: number) => formatNumber(value, locale);

    usePoll(1500, { only: ['import', 'rowErrors'] }, { autoStart: busy });

    const mapped = Object.values(mapping);
    const missingRequired = requiredForCreate.filter((field) => !mapped.includes(field));

    const saveMapping = () =>
        router.put(
            people.imports.mapping.url(item.id),
            { mapping },
            { preserveScroll: true, onSuccess: () => setRemapping(false) },
        );

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader title={t('title')} description={item.fileName} />
            <DesktopOnly />

            <div className="hidden flex-col gap-6 md:flex">
                <ImportStepper current={remapping ? 'map' : stepOf(item.status)} />

                {remapping ? (
                    <section className="flex flex-col gap-4">
                        <p className="text-small text-text-secondary">{t('map.help')}</p>
                        <div className="overflow-x-auto rounded-lg border border-border bg-card">
                            <table className="w-full text-sm">
                                <thead className="bg-surface-sunken text-caption text-text-secondary">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-3 py-2.5 text-start font-medium"
                                        >
                                            {t('map.column')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2.5 text-start font-medium"
                                        >
                                            {t('map.sample')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2.5 text-start font-medium"
                                        >
                                            {t('map.field')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {item.headers.map((header, index) => (
                                        <tr key={index} className="border-t border-border">
                                            <td className="px-3 py-2 font-medium">
                                                <bdi>
                                                    {header ??
                                                        t('map.unnamed', { number: index + 1 })}
                                                </bdi>
                                            </td>
                                            <td className="max-w-56 truncate px-3 py-2 text-text-secondary">
                                                {samples
                                                    .map((row) => row[index])
                                                    .filter(Boolean)
                                                    .map((value, i) => (
                                                        <bdi key={i} className="me-2">
                                                            {value}
                                                        </bdi>
                                                    ))}
                                            </td>
                                            <td className="w-72 px-3 py-2">
                                                <Select
                                                    value={mapping[String(index)] ?? IGNORE}
                                                    onValueChange={(value) => {
                                                        const next = { ...mapping };
                                                        if (value === IGNORE) {
                                                            delete next[String(index)];
                                                        } else {
                                                            for (const key of Object.keys(next)) {
                                                                if (next[key] === value)
                                                                    delete next[key];
                                                            }
                                                            next[String(index)] = value;
                                                        }
                                                        setMapping(next);
                                                    }}
                                                >
                                                    <SelectTrigger
                                                        aria-label={t('map.fieldFor', {
                                                            column: header ?? index + 1,
                                                        })}
                                                    >
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-80">
                                                        <SelectItem value={IGNORE}>
                                                            {t('map.ignore')}
                                                        </SelectItem>
                                                        {Object.entries(fields).map(
                                                            ([field, label]) => (
                                                                <SelectItem
                                                                    key={field}
                                                                    value={field}
                                                                >
                                                                    {label}
                                                                    {requiredForCreate.includes(
                                                                        field,
                                                                    )
                                                                        ? ' *'
                                                                        : ''}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {missingRequired.length ? (
                            <p className="rounded-md bg-warning-bg p-3 text-small text-warning">
                                {t('map.missing', {
                                    fields: missingRequired
                                        .map((field) => fields[field])
                                        .join('، '),
                                })}
                            </p>
                        ) : null}
                        <div className="flex justify-between gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => router.delete(people.imports.destroy.url(item.id))}
                            >
                                {t('cancel')}
                            </Button>
                            <Button onClick={saveMapping}>{t('map.validate')}</Button>
                        </div>
                    </section>
                ) : null}

                {!remapping && busy ? (
                    <section
                        role="status"
                        aria-live="polite"
                        className="flex flex-col gap-3 rounded-lg bg-card p-6 shadow-sm"
                    >
                        <p className="text-body font-semibold text-foreground">
                            {item.status === 'validating'
                                ? t('progress.validating')
                                : t('progress.importing')}
                        </p>
                        {item.status === 'committing' && item.validRows > 0 ? (
                            <>
                                <Progress
                                    value={(item.processedRows / item.validRows) * 100}
                                    aria-label={t('progress.label')}
                                />
                                <p className="text-small text-text-secondary">
                                    {t('progress.rows', {
                                        done: n(item.processedRows),
                                        total: n(item.validRows),
                                    })}
                                </p>
                            </>
                        ) : (
                            <Progress
                                value={undefined}
                                aria-label={t('progress.label')}
                                className="animate-pulse"
                            />
                        )}
                    </section>
                ) : null}

                {!remapping && item.status === 'validated' ? (
                    <section className="flex flex-col gap-4">
                        <dl className="grid gap-3 sm:grid-cols-4">
                            {[
                                ['total', item.totalRows],
                                ['create', item.toCreate],
                                ['update', item.toUpdate],
                                ['invalid', item.invalidRows],
                            ].map(([key, value]) => (
                                <div key={key} className="rounded-lg bg-card p-4 shadow-sm">
                                    <dt className="text-caption text-muted-foreground">
                                        {t(`summary.${key}`)}
                                    </dt>
                                    <dd
                                        className={
                                            key === 'invalid' && Number(value) > 0
                                                ? 'text-h3 text-danger'
                                                : 'text-h3 text-foreground'
                                        }
                                    >
                                        {n(Number(value))}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <RowErrors item={item} rowErrors={rowErrors} fields={fields} />
                        <div className="flex flex-wrap justify-between gap-2">
                            <div className="flex gap-2">
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        router.delete(people.imports.destroy.url(item.id))
                                    }
                                >
                                    {t('cancel')}
                                </Button>
                                <Button variant="outline" onClick={() => setRemapping(true)}>
                                    {t('map.again')}
                                </Button>
                            </div>
                            <Button
                                onClick={() =>
                                    router.post(
                                        people.imports.commit.url(item.id),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                                disabled={item.validRows === 0}
                            >
                                {t('confirm.submit', { count: item.validRows })}
                            </Button>
                        </div>
                    </section>
                ) : null}

                {!remapping && item.status === 'completed' ? (
                    <section className="flex flex-col items-center gap-4 rounded-lg bg-card p-8 text-center shadow-sm">
                        <SuccessRipple label={t('done.title')} />
                        <p className="text-h4 text-foreground">{t('done.title')}</p>
                        <p className="text-small text-text-secondary">
                            {t('done.summary', {
                                created: n(item.createdRows),
                                updated: n(item.updatedRows),
                                failed: n(item.failedRows),
                            })}
                        </p>
                        <RowErrors item={item} rowErrors={rowErrors} fields={fields} />
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={people.imports.create.url()}>{t('done.another')}</Link>
                            </Button>
                            <Button asChild>
                                <Link href={people.index.url()}>{t('done.directory')}</Link>
                            </Button>
                        </div>
                    </section>
                ) : null}

                {!remapping && item.status === 'failed' ? (
                    <PageState
                        kind="error"
                        title={t('failed.title')}
                        description={item.failure ?? undefined}
                        action={
                            item.validRows > 0 && item.processedRows > 0 ? (
                                <Button
                                    onClick={() => router.post(people.imports.commit.url(item.id))}
                                >
                                    {t('failed.resume')}
                                </Button>
                            ) : (
                                <Button onClick={() => setRemapping(true)}>{t('map.again')}</Button>
                            )
                        }
                    />
                ) : null}

                {item.status === 'cancelled' ? (
                    <PageState kind="not-found" title={t('cancelled')} />
                ) : null}
            </div>
        </>
    );
}

function RowErrors({
    item,
    rowErrors,
    fields,
}: {
    item: Import;
    rowErrors: Props['rowErrors'];
    fields: Record<string, string>;
}) {
    const { t } = useTranslation('imports');

    if (!rowErrors.length) {
        return null;
    }

    return (
        <section className="flex w-full flex-col gap-3 text-start">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-body font-semibold text-foreground">{t('errors.title')}</h2>
                {item.hasErrorReport ? (
                    <Button variant="outline" size="sm" asChild>
                        <a href={people.imports.errors.url(item.id)}>
                            <Download aria-hidden />
                            {t('errors.download')}
                        </a>
                    </Button>
                ) : null}
            </div>
            <div className="max-h-96 overflow-auto rounded-lg border border-border bg-card">
                <table className="w-full text-sm">
                    <thead className="sticky top-0 bg-surface-sunken text-caption text-text-secondary">
                        <tr>
                            <th scope="col" className="w-20 px-3 py-2 text-start font-medium">
                                {t('errors.row')}
                            </th>
                            <th scope="col" className="px-3 py-2 text-start font-medium">
                                {t('errors.problem')}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {rowErrors.map(({ row, errors }) => (
                            <tr key={row} className="border-t border-border align-top">
                                <td className="px-3 py-2 tabular-nums">{row}</td>
                                <td className="px-3 py-2">
                                    <ul className="flex flex-col gap-0.5">
                                        {Object.entries(errors).flatMap(([field, messages]) =>
                                            messages.map((message, i) => (
                                                <li key={`${field}-${i}`}>
                                                    <span className="font-medium">
                                                        {fields[field] ??
                                                            fields[field.replace(/\..*$/, '')] ??
                                                            field}
                                                        :
                                                    </span>{' '}
                                                    {message}
                                                </li>
                                            )),
                                        )}
                                    </ul>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
