import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Field } from '@/components/mw/field';
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
import { ApiError, postJson } from '@/lib/api';
import LeaveApi from '@/actions/App/Http/Controllers/Api/V1/Leave/LeaveController';
import { type LeaveBalance } from './format';

/** HR corrects a balance (opening balances, mistakes) with a reason (audited). */
export function AdjustDialog({
    open,
    onOpenChange,
    employeeId,
    year,
    balances,
    onSaved,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employeeId: string;
    year: number;
    balances: LeaveBalance[];
    onSaved: () => void;
}) {
    const { t } = useTranslation('leave');
    const [type, setType] = useState(balances[0]?.typeId ?? '');
    const [amount, setAmount] = useState('');
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const save = async () => {
        setBusy(true);
        setErrors({});

        try {
            await postJson(LeaveApi.adjust.url(employeeId), {
                leaveType: type,
                year,
                amount: Number(amount),
                note,
            });
            toast.success(t('adjust.saved'));
            setAmount('');
            setNote('');
            onOpenChange(false);
            onSaved();
        } catch (e) {
            if (e instanceof ApiError) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(e.details).map(([key, messages]) => [
                            key,
                            messages.join(' '),
                        ]),
                    ),
                );
                if (Object.keys(e.details).length === 0) setErrors({ amount: e.message });
            }
        } finally {
            setBusy(false);
        }
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('adjust.title')}
            description={t('adjust.description')}
            footer={
                <Button
                    type="button"
                    loading={busy}
                    disabled={!type || amount.trim() === '' || note.trim() === ''}
                    onClick={save}
                >
                    {t('adjust.save')}
                </Button>
            }
        >
            <div className="flex flex-col gap-4">
                <Field id="leave-adjust-type" label={t('adjust.type')} error={errors.leaveType}>
                    <Select value={type} onValueChange={setType}>
                        <SelectTrigger id="leave-adjust-type" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {balances.map((balance) => (
                                <SelectItem key={balance.typeId} value={balance.typeId}>
                                    {balance.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    id="leave-adjust-amount"
                    label={t('adjust.amount')}
                    hint={t('adjust.amountHint')}
                    error={errors.amount}
                >
                    <Input
                        id="leave-adjust-amount"
                        inputMode="decimal"
                        className="w-40 tabular-nums"
                        value={amount}
                        onChange={(e) => setAmount(e.target.value)}
                    />
                </Field>
                <Field id="leave-adjust-note" label={t('adjust.note')} error={errors.note}>
                    <Textarea
                        id="leave-adjust-note"
                        value={note}
                        maxLength={300}
                        onChange={(e) => setNote(e.target.value)}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}
