import { router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { Section } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import people from '@/routes/people';
import { today } from './types';

type Allowance = App.Domain.People.Data.AllowanceData;

const TYPES = ['housing', 'transport', 'food', 'phone', 'education', 'other'] as const;
const digitsOf = (currency: string) =>
    new Intl.NumberFormat('en', { style: 'currency', currency }).resolvedOptions()
        .maximumFractionDigits ?? 2;

/**
 * Allowances on the employee record (D116): monthly amounts by type with their dates. Shown to
 * people who can read salary; added and changed by those who can write it.
 */
export function AllowancesSection({
    employeeId,
    allowances,
    currency,
    canWrite,
}: {
    employeeId: string;
    allowances: Allowance[];
    currency: string;
    canWrite: boolean;
}) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Allowance | 'new' | null>(null);
    const day = today();
    const amount = (a: Allowance) =>
        a.amount
            ? formatNumber(a.amount.minor / 10 ** digitsOf(a.amount.currency), locale, {
                  style: 'currency',
                  currency: a.amount.currency,
              })
            : '—';

    return (
        <Section
            title={t('allowances.title')}
            description={t('allowances.help')}
            actions={
                canWrite ? (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => setEditing('new')}
                        data-add-allowance
                    >
                        <Plus aria-hidden />
                        {t('allowances.add')}
                    </Button>
                ) : null
            }
        >
            {allowances.length === 0 ? (
                <p className="text-small text-muted-foreground">{t('allowances.empty')}</p>
            ) : (
                <ul
                    className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                    data-allowances
                >
                    {allowances.map((a) => {
                        const current =
                            a.effectiveFrom <= day &&
                            (a.effectiveTo === null || a.effectiveTo >= day);

                        return (
                            <li key={a.id} className="flex items-center gap-3 p-3">
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <span className="text-body font-medium">
                                        {a.type === 'other' && a.label
                                            ? a.label
                                            : t(`allowances.types.${a.type}`)}
                                    </span>
                                    <span className="text-caption text-text-secondary">
                                        {formatDate(a.effectiveFrom, locale, {
                                            dateStyle: 'medium',
                                        })}
                                        {' – '}
                                        {a.effectiveTo
                                            ? formatDate(a.effectiveTo, locale, {
                                                  dateStyle: 'medium',
                                              })
                                            : t('allowances.ongoing')}
                                        {current ? '' : ` · ${t('allowances.notCurrent')}`}
                                    </span>
                                </div>
                                <span className="text-body tabular-nums">{amount(a)}</span>
                                {canWrite ? (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-11"
                                        aria-label={t('allowances.edit')}
                                        onClick={() => setEditing(a)}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                ) : null}
                            </li>
                        );
                    })}
                </ul>
            )}
            {editing ? (
                <AllowanceDialog
                    employeeId={employeeId}
                    allowance={editing === 'new' ? null : editing}
                    currency={currency}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </Section>
    );
}

function AllowanceDialog({
    employeeId,
    allowance,
    currency,
    onClose,
}: {
    employeeId: string;
    allowance: Allowance | null;
    currency: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const [type, setType] = useState(allowance?.type ?? 'housing');
    const [label, setLabel] = useState(allowance?.label ?? '');
    const [value, setValue] = useState(
        allowance?.amount
            ? String(allowance.amount.minor / 10 ** digitsOf(allowance.amount.currency))
            : '',
    );
    const [code, setCode] = useState(allowance?.amount?.currency ?? currency);
    const [from, setFrom] = useState(allowance?.effectiveFrom ?? today());
    const [to, setTo] = useState(allowance?.effectiveTo ?? '');
    const [note, setNote] = useState(allowance?.note ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const submit = () => {
        setSending(true);
        const payload = {
            type,
            label: type === 'other' ? label : null,
            amount: value,
            currency: code,
            effectiveFrom: from,
            effectiveTo: to || null,
            note: note || null,
        };
        const options = {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: onClose,
            onFinish: () => setSending(false),
        };

        if (allowance) {
            router.put(people.allowances.update.url([employeeId, allowance.id]), payload, options);
        } else {
            router.post(people.allowances.store.url(employeeId), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={allowance ? t('allowances.edit') : t('allowances.add')}
            description={t('allowances.dialogHelp')}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={sending || value === ''}
                        onClick={submit}
                        data-save-allowance
                    >
                        {t('allowances.save')}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="allowance-type" label={t('allowances.type')}>
                        <Select value={type} onValueChange={setType}>
                            <SelectTrigger id="allowance-type" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {TYPES.map((k) => (
                                    <SelectItem key={k} value={k}>
                                        {t(`allowances.types.${k}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    {type === 'other' ? (
                        <Field
                            id="allowance-label"
                            label={t('allowances.label')}
                            error={errors.label}
                            required
                        >
                            <Input
                                id="allowance-label"
                                className="min-h-11"
                                maxLength={80}
                                value={label}
                                onChange={(e) => setLabel(e.target.value)}
                            />
                        </Field>
                    ) : null}
                </div>
                <div className="grid gap-4 sm:grid-cols-[2fr_1fr]">
                    <Field
                        id="allowance-amount"
                        label={t('allowances.amount')}
                        error={errors.amount}
                        required
                    >
                        <Input
                            id="allowance-amount"
                            type="number"
                            inputMode="decimal"
                            min={0}
                            step="0.01"
                            className="min-h-11"
                            value={value}
                            onChange={(e) => setValue(e.target.value)}
                        />
                    </Field>
                    <Field
                        id="allowance-currency"
                        label={t('allowances.currency')}
                        error={errors.currency}
                    >
                        <Input
                            id="allowance-currency"
                            dir="ltr"
                            maxLength={3}
                            className="min-h-11 uppercase"
                            value={code}
                            onChange={(e) => setCode(e.target.value.toUpperCase())}
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="allowance-from"
                        label={t('allowances.from')}
                        error={errors.effectiveFrom}
                        required
                    >
                        <Input
                            id="allowance-from"
                            type="date"
                            className="min-h-11"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </Field>
                    <Field
                        id="allowance-to"
                        label={t('allowances.to')}
                        hint={t('allowances.toHint')}
                        error={errors.effectiveTo}
                    >
                        <Input
                            id="allowance-to"
                            type="date"
                            className="min-h-11"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </Field>
                </div>
                <label className="flex flex-col gap-1 text-small">
                    {t('allowances.note')}
                    <Textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        rows={2}
                        maxLength={500}
                    />
                </label>
            </div>
        </ResponsiveDialog>
    );
}
