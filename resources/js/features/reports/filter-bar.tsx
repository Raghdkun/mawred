import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { Field } from '@/components/mw/field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BranchPicker, DepartmentPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import reports from '@/routes/reports';

type Filter = App.Domain.Reports.Data.ReportFilterData;
export type FilterValues = Record<string, string | number | null>;

const ALL = '__all';

/**
 * The viewer's filter bar (handoff 36): one control per filter the report declares. Showing the
 * report puts the filters in the address, so a view can be bookmarked or shared.
 */
export function FilterBar({
    reportKey,
    filters,
    values,
    selected,
    errors,
}: {
    reportKey: string;
    filters: Filter[];
    values: FilterValues;
    selected: { branch: PickerOption | null; department: PickerOption | null };
    errors: Record<string, string>;
}) {
    const { t } = useTranslation('reports');
    const { dir } = useLocale();
    const [draft, setDraft] = useState<Record<string, string>>(() =>
        Object.fromEntries(
            Object.entries(values)
                .filter(([key, value]) => value !== null && key !== 'today')
                .map(([key, value]) => [key, String(value)]),
        ),
    );
    const [branch, setBranch] = useState<PickerOption | null>(selected.branch);
    const [department, setDepartment] = useState<PickerOption | null>(selected.department);
    const set = (key: string, value: string) => setDraft((all) => ({ ...all, [key]: value }));

    const apply = () => {
        const query: Record<string, string> = {};

        for (const [key, value] of Object.entries(draft)) {
            if (value !== '' && key !== 'branch' && key !== 'department') {
                query[key] = value;
            }
        }

        if (branch) {
            query.branch = branch.id;
        }

        if (department) {
            query.department = department.id;
        }

        router.get(reports.show.url(reportKey), query, { preserveScroll: true });
    };

    const control = (filter: Filter) => {
        switch (filter.type) {
            case 'period':
                return (
                    <div key="period" className="grid grid-cols-2 gap-3">
                        <Field id="filter-from" label={t('filters.from')} error={errors.from}>
                            <Input
                                id="filter-from"
                                type="date"
                                className="min-h-11"
                                value={draft.from ?? ''}
                                onChange={(e) => set('from', e.target.value)}
                                data-filter="from"
                            />
                        </Field>
                        <Field id="filter-to" label={t('filters.to')} error={errors.to}>
                            <Input
                                id="filter-to"
                                type="date"
                                className="min-h-11"
                                value={draft.to ?? ''}
                                onChange={(e) => set('to', e.target.value)}
                                data-filter="to"
                            />
                        </Field>
                    </div>
                );
            case 'month':
                return (
                    <Field
                        key="month"
                        id="filter-month"
                        label={t('filters.month')}
                        error={errors.month}
                    >
                        <Input
                            id="filter-month"
                            type="month"
                            className="min-h-11"
                            value={draft.month ?? ''}
                            onChange={(e) => set('month', e.target.value)}
                            data-filter="month"
                        />
                    </Field>
                );
            case 'year':
                return (
                    <Field
                        key="year"
                        id="filter-year"
                        label={t('filters.year')}
                        error={errors.year}
                    >
                        <Input
                            id="filter-year"
                            type="number"
                            inputMode="numeric"
                            min={2000}
                            max={2100}
                            className="min-h-11"
                            value={draft.year ?? ''}
                            onChange={(e) => set('year', e.target.value)}
                            data-filter="year"
                        />
                    </Field>
                );
            case 'as_of':
                return (
                    <Field
                        key="as_of"
                        id="filter-as-of"
                        label={t('filters.asOf')}
                        error={errors.as_of}
                    >
                        <Input
                            id="filter-as-of"
                            type="date"
                            className="min-h-11"
                            value={draft.as_of ?? ''}
                            onChange={(e) => set('as_of', e.target.value)}
                            data-filter="as_of"
                        />
                    </Field>
                );
            case 'days':
                return (
                    <Field
                        key="days"
                        id="filter-days"
                        label={t('filters.days')}
                        error={errors.days}
                    >
                        <Input
                            id="filter-days"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            max={365}
                            className="min-h-11"
                            value={draft.days ?? ''}
                            onChange={(e) => set('days', e.target.value)}
                            data-filter="days"
                        />
                    </Field>
                );
            case 'branch':
                return (
                    <Field
                        key="branch"
                        id="filter-branch"
                        label={t('filters.branch')}
                        error={errors.branch}
                        required={filter.required}
                    >
                        <BranchPicker
                            label={t('filters.branch')}
                            placeholder={
                                filter.required ? t('filters.branch') : t('filters.anyBranch')
                            }
                            value={branch}
                            onChange={setBranch}
                            className="min-h-11"
                        />
                    </Field>
                );
            case 'department':
                return (
                    <Field
                        key="department"
                        id="filter-department"
                        label={t('filters.department')}
                        error={errors.department}
                    >
                        <DepartmentPicker
                            label={t('filters.department')}
                            placeholder={t('filters.anyDepartment')}
                            value={department}
                            onChange={setDepartment}
                            className="min-h-11"
                        />
                    </Field>
                );
            case 'choice':
                return (
                    <Field
                        key={filter.key}
                        id={`filter-${filter.key}`}
                        label={filter.label ?? filter.key}
                        error={errors[filter.key]}
                    >
                        <Select
                            dir={dir}
                            value={draft[filter.key] || ALL}
                            onValueChange={(value) => set(filter.key, value === ALL ? '' : value)}
                        >
                            <SelectTrigger
                                id={`filter-${filter.key}`}
                                className="min-h-11 w-full"
                                data-filter={filter.key}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>{t('filters.any')}</SelectItem>
                                {filter.options.map((option) => (
                                    <SelectItem key={option.value} value={option.value}>
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                );
        }
    };

    return (
        <form
            role="search"
            aria-label={t('filters.label')}
            className="flex flex-col gap-4 rounded-lg border border-border bg-card p-4"
            onSubmit={(event) => {
                event.preventDefault();
                apply();
            }}
            data-filter-bar
        >
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">{filters.map(control)}</div>
            <div className="flex flex-wrap justify-end gap-2">
                <Button type="submit" className="min-h-11" data-apply-filters>
                    {t('filters.apply')}
                </Button>
            </div>
        </form>
    );
}
