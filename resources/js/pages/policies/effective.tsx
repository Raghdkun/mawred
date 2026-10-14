import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Input } from '@/components/ui/input';
import { EmployeePicker } from '@/features/organization/pickers';
import { ExplainerChip } from '@/features/policies/explainer';
import { PoliciesTabs } from '@/features/policies/policies-tabs';
import { type PolicyKey, type ResolvedValue } from '@/features/policies/types';
import { SECTIONS, keyLabel, summarize } from '@/features/policies/values';
import { useLocale } from '@/hooks/use-locale';
import policies from '@/routes/policies';

type Props = {
    date: string;
    employee: PickerOption | null;
    keys: PolicyKey[];
    values: ResolvedValue[];
};

/**
 * Effective policy for one employee on a date (handoff 19): each rule with a source explainer;
 * overridden values carry the orange dot. Table on desktop, cards on phones.
 */
export default function EffectivePolicy({ date, employee, keys, values }: Props) {
    const { t } = useTranslation('policies');

    const reload = (params: { employee?: string | null; date?: string }) =>
        router.get(
            policies.effective.url(),
            Object.fromEntries(
                Object.entries({ employee: employee?.id ?? null, date, ...params }).filter(
                    ([, value]) => value,
                ),
            ),
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('effective.pageTitle')} />
            <PageHeader title={t('title')} description={t('description')} />
            <PoliciesTabs />

            <div className="mb-6 flex flex-wrap items-end gap-3">
                <EmployeePicker
                    value={employee}
                    onChange={(option) => reload({ employee: option?.id ?? null })}
                    label={t('effective.employee')}
                    className="w-full sm:w-80"
                />
                <Field id="effective-date" label={t('effective.date')}>
                    <Input
                        id="effective-date"
                        type="date"
                        dir="ltr"
                        className="w-44"
                        value={date}
                        onChange={(event) =>
                            event.target.value && reload({ date: event.target.value })
                        }
                    />
                </Field>
            </div>

            {!employee ? (
                <PageState
                    kind="empty"
                    illustration="empty-policies"
                    title={t('effective.choose')}
                    description={t('effective.chooseHelp')}
                />
            ) : (
                <div className="flex flex-col gap-6">
                    {SECTIONS.map((section) => {
                        const rows = values.filter((value) => value.section === section);
                        return rows.length === 0 ? null : (
                            <section
                                key={section}
                                aria-labelledby={`effective-${section}`}
                                className="flex flex-col gap-2"
                            >
                                <h2 id={`effective-${section}`} className="text-h4">
                                    {t(`sections.${section}`)}
                                </h2>
                                <ValueRows rows={rows} keys={keys} />
                            </section>
                        );
                    })}
                </div>
            )}
        </>
    );
}

function ValueRows({ rows, keys }: { rows: ResolvedValue[]; keys: PolicyKey[] }) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();

    return (
        <div className="md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
            <div
                aria-hidden
                className="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_minmax(0,1fr)] gap-4 border-b border-border px-4 py-2.5 text-caption font-medium text-muted-foreground md:grid"
            >
                <span>{t('effective.rule')}</span>
                <span>{t('effective.value')}</span>
                <span>{t('effective.source')}</span>
            </div>
            <ul className="flex flex-col gap-2 md:gap-0">
                {rows.map((row) => {
                    const key = keys.find((candidate) => candidate.id === row.key);
                    const editor = key?.editor ?? '';

                    return (
                        <li
                            key={row.key}
                            className="grid gap-2 rounded-lg bg-card p-4 shadow-sm md:grid-cols-[minmax(0,1.2fr)_minmax(0,1.5fr)_minmax(0,1fr)] md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="text-sm font-medium">
                                {key ? keyLabel(key, t, locale) : row.key}
                            </span>
                            <span className="text-small tabular-nums">
                                {summarize(editor, row.value, t, locale)}
                            </span>
                            <span className="min-w-0">
                                <ExplainerChip resolved={row} editor={editor} />
                            </span>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
