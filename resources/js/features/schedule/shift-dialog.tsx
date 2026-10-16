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
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import roster from '@/routes/roster';
import { ShiftTime } from './shift-time';

export type RosterShift = App.Domain.Scheduling.Data.RosterShiftData;
export type ShiftTemplate = App.Domain.Scheduling.Data.ShiftTemplateData;
export type Claim = { id: string; employeeId: string; nameEn: string; nameAr: string | null };

const CUSTOM = 'custom';

/**
 * Add or edit one shift (handoff 22). Saving keeps it a draft until the roster is published.
 * Open shifts also list who asked for them, with a button to give it to one of them (D73).
 */
export function ShiftDialog({
    open,
    onClose,
    branchId,
    date,
    employeeId,
    title,
    shift,
    templates,
    claims,
}: {
    open: boolean;
    onClose: () => void;
    branchId: string;
    date: string;
    employeeId: string | null;
    title: string;
    shift: RosterShift | null;
    templates: ShiftTemplate[];
    claims: Claim[];
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const [template, setTemplate] = useState(
        shift ? (shift.templateId ?? CUSTOM) : (templates[0]?.id ?? CUSTOM),
    );
    const chosen = templates.find((tpl) => tpl.id === template);
    const [startsAt, setStartsAt] = useState(shift?.startsAt ?? '09:00');
    const [endsAt, setEndsAt] = useState(shift?.endsAt ?? '17:00');
    const [breakMinutes, setBreakMinutes] = useState(String(shift?.breakMinutes ?? 0));
    const [note, setNote] = useState(shift?.note ?? '');
    const [autoAssign, setAutoAssign] = useState(shift?.autoAssign ?? false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    const payload = () => ({
        date,
        employeeId,
        templateId: template === CUSTOM ? null : template,
        startsAt: template === CUSTOM ? startsAt : null,
        endsAt: template === CUSTOM ? endsAt : null,
        breakMinutes: template === CUSTOM ? Number(breakMinutes) || 0 : null,
        note: note.trim() === '' ? null : note,
        autoAssign: employeeId === null && autoAssign,
    });
    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: () => setBusy(true),
        onFinish: () => setBusy(false),
        onError: (e: Record<string, string>) => setErrors(e),
        onSuccess: onClose,
    };
    const save = () =>
        shift
            ? router.put(roster.shifts.update.url(shift.id), payload(), options)
            : router.post(roster.shifts.store.url(branchId), payload(), options);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title={title}
            footer={
                <>
                    {shift ? (
                        <Button
                            type="button"
                            variant="ghost"
                            className="me-auto text-danger"
                            onClick={() =>
                                router.delete(roster.shifts.destroy.url(shift.id), { ...options })
                            }
                        >
                            {t('dialog.delete')}
                        </Button>
                    ) : null}
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="button" loading={busy} onClick={save} data-save-shift>
                        {t('dialog.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="shift-template" label={t('dialog.template')} error={errors.templateId}>
                    <Select value={template} onValueChange={setTemplate}>
                        <SelectTrigger id="shift-template" className="min-h-11">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {templates.map((tpl) => (
                                <SelectItem key={tpl.id} value={tpl.id}>
                                    {localized(tpl.name, locale)} · {tpl.startsAt}–{tpl.endsAt}
                                </SelectItem>
                            ))}
                            <SelectItem value={CUSTOM}>{t('dialog.custom')}</SelectItem>
                        </SelectContent>
                    </Select>
                </Field>
                {template === CUSTOM ? (
                    <div className="grid grid-cols-3 gap-3">
                        <Field id="shift-starts" label={t('dialog.starts')} error={errors.startsAt}>
                            <Input
                                id="shift-starts"
                                type="time"
                                dir="ltr"
                                value={startsAt}
                                onChange={(e) => setStartsAt(e.target.value)}
                            />
                        </Field>
                        <Field id="shift-ends" label={t('dialog.ends')} error={errors.endsAt}>
                            <Input
                                id="shift-ends"
                                type="time"
                                dir="ltr"
                                value={endsAt}
                                onChange={(e) => setEndsAt(e.target.value)}
                            />
                        </Field>
                        <Field
                            id="shift-break"
                            label={t('dialog.break')}
                            error={errors.breakMinutes}
                        >
                            <Input
                                id="shift-break"
                                inputMode="numeric"
                                value={breakMinutes}
                                onChange={(e) => setBreakMinutes(e.target.value)}
                            />
                        </Field>
                    </div>
                ) : chosen ? (
                    <p className="text-small text-text-secondary">
                        <ShiftTime startsAt={chosen.startsAt} endsAt={chosen.endsAt} /> ·{' '}
                        {t('shift.break', { minutes: chosen.breakMinutes })}
                    </p>
                ) : null}
                <Field id="shift-note" label={t('dialog.note')} error={errors.note}>
                    <Textarea
                        id="shift-note"
                        value={note}
                        maxLength={300}
                        onChange={(e) => setNote(e.target.value)}
                    />
                </Field>
                {employeeId === null ? (
                    <div className="flex min-h-11 items-center gap-2">
                        <Checkbox
                            id="shift-auto"
                            checked={autoAssign}
                            onCheckedChange={(v) => setAutoAssign(v === true)}
                        />
                        <Label htmlFor="shift-auto">{t('dialog.autoAssign')}</Label>
                    </div>
                ) : null}
                {shift && employeeId === null ? (
                    <section
                        aria-labelledby="shift-claims"
                        className="flex flex-col gap-2 border-t border-border pt-4"
                    >
                        <h3 id="shift-claims" className="text-small font-semibold">
                            {t('dialog.claimsTitle')}
                        </h3>
                        {claims.length === 0 ? (
                            <p className="text-small text-muted-foreground">
                                {t('dialog.noClaims')}
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {claims.map((claim) => (
                                    <li
                                        key={claim.id}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <span>
                                            {locale === 'ar' && claim.nameAr
                                                ? claim.nameAr
                                                : claim.nameEn}
                                        </span>
                                        <Button
                                            type="button"
                                            size="sm"
                                            className="min-h-11"
                                            onClick={() =>
                                                router.post(
                                                    roster.claims.assign.url(claim.id),
                                                    {},
                                                    options,
                                                )
                                            }
                                        >
                                            {t('dialog.assign')}
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
