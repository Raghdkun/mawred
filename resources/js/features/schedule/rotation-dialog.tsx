import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import roster from '@/routes/roster';
import { type Person } from './roster-grid';

type Rotation = { id: string; name: Record<string, string>; length: number };

/** Fill a date range for some people from a rotation pattern, as drafts. */
export function RotationDialog({
    open,
    onClose,
    branchId,
    rotations,
    people,
    from,
    to,
}: {
    open: boolean;
    onClose: () => void;
    branchId: string;
    rotations: Rotation[];
    people: Person[];
    from: string;
    to: string;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const [rotation, setRotation] = useState(rotations[0]?.id ?? '');
    const [chosen, setChosen] = useState<string[]>([]);
    const [range, setRange] = useState({ from, to });
    const [busy, setBusy] = useState(false);
    const editable = people.filter((p) => p.canEdit);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title={t('rotationDialog.title')}
            footer={
                <Button
                    type="button"
                    loading={busy}
                    disabled={!rotation || chosen.length === 0}
                    onClick={() =>
                        router.post(
                            roster.rotation.url(branchId),
                            { rotation, people: chosen, from: range.from, to: range.to },
                            {
                                preserveScroll: true,
                                onStart: () => setBusy(true),
                                onFinish: () => setBusy(false),
                                onSuccess: onClose,
                            },
                        )
                    }
                >
                    {t('rotationDialog.apply')}
                </Button>
            }
        >
            {rotations.length === 0 ? (
                <p className="text-small text-text-secondary">{t('rotationDialog.none')}</p>
            ) : (
                <div className="flex flex-col gap-4">
                    <Field id="rotation-pattern" label={t('rotationDialog.rotation')}>
                        <Select value={rotation} onValueChange={setRotation}>
                            <SelectTrigger id="rotation-pattern" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {rotations.map((r) => (
                                    <SelectItem key={r.id} value={r.id}>
                                        {localized(r.name, locale)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field id="rotation-from" label={t('rotationDialog.from')}>
                            <Input
                                id="rotation-from"
                                type="date"
                                value={range.from}
                                onChange={(e) => setRange({ ...range, from: e.target.value })}
                            />
                        </Field>
                        <Field id="rotation-to" label={t('rotationDialog.to')}>
                            <Input
                                id="rotation-to"
                                type="date"
                                value={range.to}
                                min={range.from}
                                onChange={(e) => setRange({ ...range, to: e.target.value })}
                            />
                        </Field>
                    </div>
                    <fieldset className="flex max-h-64 flex-col gap-1 overflow-y-auto">
                        <legend className="mb-1 text-small font-medium">
                            {t('rotationDialog.people')}
                        </legend>
                        {editable.map((p) => (
                            <div key={p.id} className="flex min-h-11 items-center gap-2">
                                <Checkbox
                                    id={`rotation-person-${p.id}`}
                                    checked={chosen.includes(p.id)}
                                    onCheckedChange={(v) =>
                                        setChosen(
                                            v === true
                                                ? [...chosen, p.id]
                                                : chosen.filter((id) => id !== p.id),
                                        )
                                    }
                                />
                                <Label htmlFor={`rotation-person-${p.id}`}>
                                    {locale === 'ar' && p.nameAr ? p.nameAr : p.nameEn}
                                </Label>
                            </div>
                        ))}
                    </fieldset>
                </div>
            )}
        </ResponsiveDialog>
    );
}
