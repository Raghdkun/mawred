import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { ModuleIcon } from '@/components/mw/module-icon';
import { type ModuleIconName } from '@/components/mw/module-icons.generated';
import { Field } from '@/components/mw/field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLocale } from '@/hooks/use-locale';
import { cn } from '@/lib/utils';
import { type Definition, type DefinitionFilter, emptyValue } from './definition';

type Dataset = App.Domain.Reports.Data.DatasetData;
type DatasetField = App.Domain.Reports.Data.DatasetFieldData;
type Update = (patch: Partial<Definition>) => void;

const ICONS: Record<App.Domain.Reports.Enums.ReportCategory, ModuleIconName> = {
    people: 'people',
    leave: 'leave',
    time: 'attendance',
    requests: 'requests',
    hiring: 'hiring',
    training: 'training',
    payroll: 'reports',
    admin: 'settings',
};

const NONE = '__none';

function StepTitle({ title, help }: { title: string; help?: string }) {
    return (
        <div className="mb-4">
            <h2 className="text-h4">{title}</h2>
            {help ? <p className="mt-1 text-small text-text-secondary">{help}</p> : null}
        </div>
    );
}

/** Step 1: the dataset, as cards. */
export function DatasetStep({
    datasets,
    value,
    onChange,
    error,
}: {
    datasets: Dataset[];
    value: string;
    onChange: (dataset: string) => void;
    error?: string;
}) {
    const { t } = useTranslation('reports');

    return (
        <fieldset>
            <legend className="sr-only">{t('builder.dataset.title')}</legend>
            <StepTitle
                title={t('builder.dataset.title')}
                help={value ? t('builder.dataset.change') : undefined}
            />
            <div className="grid gap-3 sm:grid-cols-2" role="radiogroup">
                {datasets.map((dataset) => (
                    <button
                        key={dataset.key}
                        type="button"
                        role="radio"
                        aria-checked={value === dataset.key}
                        onClick={() => onChange(dataset.key)}
                        className={cn(
                            'flex min-h-20 items-start gap-3 rounded-lg border bg-card p-4 text-start transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring motion-reduce:transition-none',
                            value === dataset.key
                                ? 'border-primary bg-primary/5'
                                : 'border-border hover:border-primary',
                        )}
                        data-dataset={dataset.key}
                    >
                        <ModuleIcon
                            name={ICONS[dataset.category]}
                            className="mt-0.5 size-5 shrink-0 text-text-secondary"
                        />
                        <span className="flex flex-col gap-1">
                            <span className="text-body font-medium">{dataset.title}</span>
                            <span className="text-small text-text-secondary">
                                {dataset.description}
                            </span>
                        </span>
                    </button>
                ))}
            </div>
            {error ? <p className="mt-2 text-small text-danger">{error}</p> : null}
        </fieldset>
    );
}

/** Step 2: the columns, in the order they're picked. */
export function FieldsStep({
    dataset,
    definition,
    update,
    error,
}: {
    dataset: Dataset;
    definition: Definition;
    update: Update;
    error?: string;
}) {
    const { t } = useTranslation('reports');
    const toggle = (key: string, on: boolean) =>
        update({
            columns: on
                ? [...definition.columns, key]
                : definition.columns.filter((c) => c !== key),
            sort: !on && definition.sort === key ? null : definition.sort,
        });

    return (
        <fieldset>
            <legend className="sr-only">{t('builder.fields.title')}</legend>
            <StepTitle title={t('builder.fields.title')} help={t('builder.fields.help')} />
            <ul className="grid gap-1 sm:grid-cols-2">
                {dataset.fields.map((field) => {
                    const id = `column-${field.key}`;
                    const position = definition.columns.indexOf(field.key);

                    return (
                        <li key={field.key}>
                            <label
                                htmlFor={id}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-md px-2 hover:bg-muted"
                            >
                                <Checkbox
                                    id={id}
                                    checked={position >= 0}
                                    onCheckedChange={(on) => toggle(field.key, on === true)}
                                    data-column={field.key}
                                />
                                <span className="flex-1 text-small">{field.label}</span>
                                {position >= 0 ? (
                                    <span className="text-caption text-text-secondary tabular-nums">
                                        {position + 1}
                                    </span>
                                ) : null}
                            </label>
                        </li>
                    );
                })}
            </ul>
            {error ? <p className="mt-2 text-small text-danger">{error}</p> : null}
        </fieldset>
    );
}

/** Step 3: filters, one row each: field, condition, value. */
export function FiltersStep({
    dataset,
    definition,
    update,
    errors,
}: {
    dataset: Dataset;
    definition: Definition;
    update: Update;
    errors: Record<string, string[]>;
}) {
    const { t } = useTranslation('reports');
    const { dir } = useLocale();
    const fields = dataset.fields;
    const set = (index: number, filter: DefinitionFilter) =>
        update({ filters: definition.filters.map((f, i) => (i === index ? filter : f)) });

    return (
        <section aria-labelledby="filters-title">
            <div className="mb-4">
                <h2 id="filters-title" className="text-h4">
                    {t('builder.filters.title')}
                </h2>
                <p className="mt-1 text-small text-text-secondary">{t('builder.filters.help')}</p>
            </div>
            {definition.filters.length === 0 ? (
                <p className="mb-4 text-small text-text-secondary">{t('builder.filters.none')}</p>
            ) : null}
            <ol className="flex flex-col gap-3">
                {definition.filters.map((filter, index) => {
                    const field = fields.find((f) => f.key === filter.field);
                    const error =
                        errors[`filters.${index}.value`]?.[0] ?? errors[`filters.${index}`]?.[0];

                    return (
                        <li
                            key={index}
                            className="rounded-lg border border-border bg-card p-3"
                            data-filter-row={index}
                        >
                            <div className="grid grid-cols-[1fr_1fr_auto] items-end gap-3">
                                <Field
                                    id={`filter-field-${index}`}
                                    label={t('builder.filters.field')}
                                >
                                    <Select
                                        dir={dir}
                                        value={filter.field}
                                        onValueChange={(key) => {
                                            const next = fields.find((f) => f.key === key);
                                            const op = next?.ops[0] ?? 'is';
                                            set(index, { field: key, op, value: emptyValue(op) });
                                        }}
                                    >
                                        <SelectTrigger
                                            id={`filter-field-${index}`}
                                            className="min-h-11 w-full"
                                            data-filter-field
                                        >
                                            <SelectValue
                                                placeholder={t('builder.filters.choose')}
                                            />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {fields.map((f) => (
                                                <SelectItem key={f.key} value={f.key}>
                                                    {f.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    id={`filter-op-${index}`}
                                    label={t('builder.filters.condition')}
                                >
                                    <Select
                                        dir={dir}
                                        value={filter.op}
                                        onValueChange={(op) => {
                                            const next = op as App.Domain.Reports.Enums.FilterOp;
                                            set(index, {
                                                ...filter,
                                                op: next,
                                                value: emptyValue(next),
                                            });
                                        }}
                                    >
                                        <SelectTrigger
                                            id={`filter-op-${index}`}
                                            className="min-h-11 w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {(field?.ops ?? []).map((op) => (
                                                <SelectItem key={op} value={op}>
                                                    {t(`builder.filters.ops.${op}`)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={t('builder.filters.remove')}
                                    onClick={() =>
                                        update({
                                            filters: definition.filters.filter(
                                                (_, i) => i !== index,
                                            ),
                                        })
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </div>
                            {field ? (
                                <div className="mt-3">
                                    <FilterValueInput
                                        index={index}
                                        field={field}
                                        filter={filter}
                                        onChange={(value) => set(index, { ...filter, value })}
                                    />
                                </div>
                            ) : null}
                            {error ? (
                                <p className="mt-2 text-small text-danger" role="alert">
                                    {error}
                                </p>
                            ) : null}
                        </li>
                    );
                })}
            </ol>
            <Button
                type="button"
                variant="outline"
                className="mt-3 min-h-11"
                disabled={definition.filters.length >= 10 || fields.length === 0}
                onClick={() => {
                    const first = fields[0];

                    if (first) {
                        const op = first.ops[0] ?? 'is';
                        update({
                            filters: [
                                ...definition.filters,
                                { field: first.key, op, value: emptyValue(op) },
                            ],
                        });
                    }
                }}
                data-add-filter
            >
                <Plus aria-hidden />
                {t('builder.filters.add')}
            </Button>
        </section>
    );
}

function FilterValueInput({
    index,
    field,
    filter,
    onChange,
}: {
    index: number;
    field: DatasetField;
    filter: DefinitionFilter;
    onChange: (value: DefinitionFilter['value']) => void;
}) {
    const { t } = useTranslation('reports');
    const id = `filter-value-${index}`;

    if (filter.op === 'in') {
        const chosen = Array.isArray(filter.value) ? filter.value : [];

        return (
            <fieldset>
                <legend className="mb-1 text-small font-medium">
                    {t('builder.filters.value')}
                </legend>
                <ul className="flex flex-wrap gap-x-4 gap-y-1">
                    {field.options.map((option) => (
                        <li key={option.value}>
                            <label className="flex min-h-11 items-center gap-2 text-small">
                                <Checkbox
                                    checked={chosen.includes(option.value)}
                                    onCheckedChange={(on) =>
                                        onChange(
                                            on === true
                                                ? [...chosen, option.value]
                                                : chosen.filter((v) => v !== option.value),
                                        )
                                    }
                                    data-filter-option={option.value}
                                />
                                {option.label}
                            </label>
                        </li>
                    ))}
                </ul>
            </fieldset>
        );
    }

    if (filter.op === 'between') {
        const range =
            typeof filter.value === 'object' && !Array.isArray(filter.value)
                ? filter.value
                : { from: '', to: '' };

        return (
            <div className="grid grid-cols-2 gap-3">
                <Field id={`${id}-from`} label={t('builder.filters.from')}>
                    <Input
                        id={`${id}-from`}
                        type="date"
                        className="min-h-11"
                        value={range.from}
                        onChange={(e) => onChange({ ...range, from: e.target.value })}
                    />
                </Field>
                <Field id={`${id}-to`} label={t('builder.filters.to')}>
                    <Input
                        id={`${id}-to`}
                        type="date"
                        className="min-h-11"
                        value={range.to}
                        onChange={(e) => onChange({ ...range, to: e.target.value })}
                    />
                </Field>
            </div>
        );
    }

    const numeric = filter.op === 'last_days' || field.type !== 'text';

    return (
        <Field
            id={id}
            label={
                filter.op === 'last_days' ? t('builder.filters.days') : t('builder.filters.value')
            }
            className="sm:max-w-xs"
        >
            <Input
                id={id}
                type={numeric ? 'number' : 'text'}
                inputMode={numeric ? 'decimal' : undefined}
                className="min-h-11"
                value={
                    typeof filter.value === 'string' || typeof filter.value === 'number'
                        ? String(filter.value)
                        : ''
                }
                onChange={(e) =>
                    onChange(filter.op === 'last_days' ? Number(e.target.value) : e.target.value)
                }
                data-filter-value
            />
        </Field>
    );
}

/** Step 4: group the rows (and what to count or add up), or list them. */
export function GroupStep({
    dataset,
    definition,
    update,
    errors,
}: {
    dataset: Dataset;
    definition: Definition;
    update: Update;
    errors: Record<string, string[]>;
}) {
    const { t } = useTranslation('reports');
    const { dir } = useLocale();
    const groupable = dataset.fields.filter((f) => f.groupable);
    const measurable = dataset.fields.filter((f) => f.measurable);

    return (
        <section aria-labelledby="group-title" className="flex flex-col gap-4">
            <div>
                <h2 id="group-title" className="text-h4">
                    {t('builder.group.title')}
                </h2>
                <p className="mt-1 text-small text-text-secondary">{t('builder.group.help')}</p>
            </div>
            <Field
                id="group-by"
                label={t('builder.group.by')}
                error={errors.groupBy?.[0]}
                className="sm:max-w-sm"
            >
                <Select
                    dir={dir}
                    value={definition.groupBy ?? NONE}
                    onValueChange={(key) =>
                        update(
                            key === NONE
                                ? {
                                      groupBy: null,
                                      chart: 'none',
                                      sort: null,
                                      measure: 'count',
                                      measureField: null,
                                  }
                                : {
                                      groupBy: key,
                                      chart: definition.chart === 'none' ? 'bar' : definition.chart,
                                      sort: 'measure',
                                      direction: 'desc',
                                  },
                        )
                    }
                >
                    <SelectTrigger id="group-by" className="min-h-11 w-full" data-group-by>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value={NONE}>{t('builder.group.none')}</SelectItem>
                        {groupable.map((f) => (
                            <SelectItem key={f.key} value={f.key}>
                                {f.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            {definition.groupBy ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="measure" label={t('builder.group.measure')}>
                        <Select
                            dir={dir}
                            value={definition.measure}
                            onValueChange={(measure) =>
                                update({
                                    measure: measure as App.Domain.Reports.Enums.Measure,
                                    measureField:
                                        measure === 'count'
                                            ? null
                                            : (definition.measureField ??
                                              measurable[0]?.key ??
                                              null),
                                })
                            }
                        >
                            <SelectTrigger id="measure" className="min-h-11 w-full" data-measure>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="count">
                                    {t('builder.group.measures.count')}
                                </SelectItem>
                                {measurable.length > 0 ? (
                                    <>
                                        <SelectItem value="sum">
                                            {t('builder.group.measures.sum')}
                                        </SelectItem>
                                        <SelectItem value="avg">
                                            {t('builder.group.measures.avg')}
                                        </SelectItem>
                                    </>
                                ) : null}
                            </SelectContent>
                        </Select>
                    </Field>
                    {definition.measure !== 'count' ? (
                        <Field
                            id="measure-field"
                            label={t('builder.group.measureField')}
                            error={errors.measureField?.[0]}
                        >
                            <Select
                                dir={dir}
                                value={definition.measureField ?? undefined}
                                onValueChange={(key) => update({ measureField: key })}
                            >
                                <SelectTrigger id="measure-field" className="min-h-11 w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {measurable.map((f) => (
                                        <SelectItem key={f.key} value={f.key}>
                                            {f.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}
                </div>
            ) : null}
        </section>
    );
}

/** Step 5: chart, sort, name and description. */
export function VisualizeStep({
    dataset,
    definition,
    update,
    name,
    onName,
    description,
    onDescription,
    errors,
}: {
    dataset: Dataset;
    definition: Definition;
    update: Update;
    name: string;
    onName: (name: string) => void;
    description: string;
    onDescription: (description: string) => void;
    errors: Record<string, string>;
}) {
    const { t } = useTranslation('reports');
    const { dir } = useLocale();
    const grouped = definition.groupBy !== null;
    const sortable = grouped
        ? [
              {
                  key: definition.groupBy ?? '',
                  label: dataset.fields.find((f) => f.key === definition.groupBy)?.label ?? '',
              },
              { key: 'measure', label: t('builder.visualize.sortMeasure') },
          ]
        : definition.columns.map((key) => ({
              key,
              label: dataset.fields.find((f) => f.key === key)?.label ?? key,
          }));

    return (
        <section aria-labelledby="visualize-title" className="flex flex-col gap-5">
            <h2 id="visualize-title" className="text-h4">
                {t('builder.visualize.title')}
            </h2>
            <div className="flex flex-col gap-1.5">
                <span id="chart-label" className="text-small font-medium">
                    {t('builder.visualize.chart')}
                </span>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    dir={dir}
                    aria-labelledby="chart-label"
                    value={definition.chart}
                    onValueChange={(chart) =>
                        chart && update({ chart: chart as Definition['chart'] })
                    }
                    className="self-start"
                >
                    {(['none', 'bar', 'line'] as const).map((chart) => (
                        <ToggleGroupItem
                            key={chart}
                            value={chart}
                            disabled={chart !== 'none' && !grouped}
                            className="min-h-11 px-4"
                            data-chart={chart}
                        >
                            {t(`builder.visualize.charts.${chart}`)}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
                {!grouped ? (
                    <p className="text-caption text-text-secondary">
                        {t('builder.visualize.chartNeedsGroup')}
                    </p>
                ) : null}
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field id="sort" label={t('builder.visualize.sort')}>
                    <Select
                        dir={dir}
                        value={definition.sort ?? sortable[0]?.key}
                        onValueChange={(sort) => update({ sort })}
                    >
                        <SelectTrigger id="sort" className="min-h-11 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {sortable.map((s) => (
                                <SelectItem key={s.key} value={s.key}>
                                    {s.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field id="direction" label={t('builder.visualize.order')}>
                    <Select
                        dir={dir}
                        value={definition.direction}
                        onValueChange={(direction) =>
                            update({ direction: direction as Definition['direction'] })
                        }
                    >
                        <SelectTrigger id="direction" className="min-h-11 w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="asc">
                                {t('builder.visualize.direction.asc')}
                            </SelectItem>
                            <SelectItem value="desc">
                                {t('builder.visualize.direction.desc')}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
            </div>
            <Field
                id="report-name"
                label={t('builder.visualize.name')}
                error={errors.name}
                required
            >
                <Input
                    id="report-name"
                    className="min-h-11"
                    maxLength={120}
                    value={name}
                    onChange={(e) => onName(e.target.value)}
                    data-report-name
                />
            </Field>
            <Field
                id="report-description"
                label={t('builder.visualize.description')}
                error={errors.description}
            >
                <Textarea
                    id="report-description"
                    maxLength={300}
                    rows={2}
                    value={description}
                    onChange={(e) => onDescription(e.target.value)}
                />
            </Field>
        </section>
    );
}
