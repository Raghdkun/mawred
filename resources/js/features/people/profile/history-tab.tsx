import { Plus } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { Section } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { type Locale } from '@/i18n';
import { localized } from '@/lib/localized';
import people from '@/routes/people';
import { type HistoryEntry, type ProfileProps, today } from './types';

const KINDS = ['branch', 'department', 'position', 'manager', 'employment_type', 'grade'] as const;

function money(minor: number, currency: string, locale: Locale): string {
    const digits =
        new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions()
            .maximumFractionDigits ?? 2;

    return formatNumber(minor / 10 ** digits, locale, { style: 'currency', currency });
}

/** Employment timeline (screen 8 History): hires, placement changes, salary rows, terminations. */
export function HistoryTab({
    profile,
    history,
    historyNames,
    defaultCurrency,
    can,
}: Pick<ProfileProps, 'profile' | 'history' | 'historyNames' | 'defaultCurrency' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [recording, setRecording] = useState(false);
    const name = (id: unknown) =>
        typeof id === 'string' ? localized(historyNames[id], locale) || '—' : '—';

    const describe = (entry: HistoryEntry) => {
        const changes = entry.changes as Record<string, unknown>;

        if (entry.event === 'placement_changed') {
            const from = (changes.from ?? {}) as Record<string, unknown>;
            const to = (changes.to ?? {}) as Record<string, unknown>;

            return KINDS.filter((kind) => from[kind] !== to[kind] && (from[kind] || to[kind])).map(
                (kind) => (
                    <li key={kind}>
                        {t(`history.kinds.${kind}`)}: {from[kind] ? name(from[kind]) : '—'} →{' '}
                        {to[kind] ? name(to[kind]) : '—'}
                    </li>
                ),
            );
        }

        if (entry.event === 'hired') {
            return KINDS.filter((kind) => changes[kind]).map((kind) => (
                <li key={kind}>
                    {t(`history.kinds.${kind}`)}: {name(changes[kind])}
                </li>
            ));
        }

        return null;
    };

    return (
        <Section
            title={t('history.title')}
            actions={
                can.salary.write ? (
                    <Button size="sm" variant="outline" onClick={() => setRecording(true)}>
                        <Plus aria-hidden />
                        {t('history.recordSalary')}
                    </Button>
                ) : null
            }
        >
            {history.length === 0 ? (
                <p className="text-small text-muted-foreground">{t('history.empty')}</p>
            ) : (
                <ol className="relative flex flex-col gap-5 border-s border-border ps-6">
                    {history
                        .filter((entry) => entry.event !== 'salary_changed' || can.salary.read)
                        .map((entry) => (
                            <li key={entry.id} className="relative">
                                <span
                                    aria-hidden
                                    className="absolute -start-[1.95rem] top-1.5 size-3 rounded-full border-2 border-card bg-primary"
                                />
                                <p className="text-sm font-medium">
                                    {t(`history.events.${entry.event}`)}
                                </p>
                                <p className="text-caption text-muted-foreground">
                                    {formatDate(entry.effectiveOn, locale, { dateStyle: 'medium' })}
                                </p>
                                {entry.salary ? (
                                    <p className="mt-1 text-sm tabular-nums">
                                        {money(entry.salary.minor, entry.salary.currency, locale)}
                                    </p>
                                ) : null}
                                <ul className="mt-1 text-small text-text-secondary">
                                    {describe(entry)}
                                </ul>
                                {entry.note ? (
                                    <p className="mt-1 text-small text-text-secondary">
                                        {entry.note}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                </ol>
            )}
            {recording ? (
                <SalaryDialog
                    employeeId={profile.summary.id}
                    currency={defaultCurrency}
                    onClose={() => setRecording(false)}
                />
            ) : null}
        </Section>
    );
}

function SalaryDialog({
    employeeId,
    currency,
    onClose,
}: {
    employeeId: string;
    currency: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const [data, setData] = useState({ amount: '', currency, effectiveOn: today(), note: '' });
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.post(
            people.salaries.store.url(employeeId),
            { ...data, note: data.note || null },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onClose,
            },
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('history.recordSalary')}
            description={t('history.salaryHelp')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {errors.salary || errors.error ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-bg p-3 text-small text-danger sm:col-span-2"
                    >
                        {errors.salary ?? errors.error}
                    </p>
                ) : null}
                <Field
                    id="salary-amount"
                    label={t('history.amount')}
                    error={errors.amount}
                    required
                >
                    <Input
                        id="salary-amount"
                        type="number"
                        min={0}
                        step="0.01"
                        dir="ltr"
                        value={data.amount}
                        onChange={(event) => setData({ ...data, amount: event.target.value })}
                    />
                </Field>
                <Field
                    id="salary-currency"
                    label={t('history.currency')}
                    error={errors.currency}
                    required
                >
                    <Input
                        id="salary-currency"
                        dir="ltr"
                        maxLength={3}
                        value={data.currency}
                        onChange={(event) =>
                            setData({ ...data, currency: event.target.value.toUpperCase() })
                        }
                    />
                </Field>
                <Field
                    id="salary-from"
                    label={t('history.effectiveOn')}
                    error={errors.effectiveOn}
                    required
                >
                    <Input
                        id="salary-from"
                        type="date"
                        dir="ltr"
                        value={data.effectiveOn}
                        onChange={(event) => setData({ ...data, effectiveOn: event.target.value })}
                    />
                </Field>
                <Field
                    id="salary-note"
                    label={t('history.note')}
                    error={errors.note}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="salary-note"
                        value={data.note}
                        onChange={(event) => setData({ ...data, note: event.target.value })}
                        maxLength={500}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}
