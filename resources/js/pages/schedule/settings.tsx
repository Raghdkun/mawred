import { Head, router, useForm } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SHIFT_COLORS, shiftColor } from '@/features/schedule/colors';
import { type ShiftTemplate } from '@/features/schedule/shift-dialog';
import { ShiftTime } from '@/features/schedule/shift-time';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import schedule from '@/routes/schedule';

type Rotation = { id: string; name: Record<string, string>; days: Array<string | null> };
type Props = { templates: ShiftTemplate[]; rotations: Rotation[] };

const OFF = 'off';

/** Shift templates and rotation patterns (scheduling.configure). */
export default function ScheduleSettings({ templates, rotations }: Props) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const [template, setTemplate] = useState<ShiftTemplate | 'new' | null>(null);
    const [rotation, setRotation] = useState<Rotation | 'new' | null>(null);

    return (
        <>
            <Head title={t('settings.title')} />
            <PageHeader title={t('settings.title')} description={t('settings.description')} />

            <section aria-labelledby="templates-title" className="mb-8 flex flex-col gap-3">
                <div className="flex items-center justify-between gap-2">
                    <h2 id="templates-title" className="text-h4">
                        {t('settings.templates')}
                    </h2>
                    <Button className="min-h-11" onClick={() => setTemplate('new')}>
                        <Plus aria-hidden className="size-4" />
                        {t('settings.addTemplate')}
                    </Button>
                </div>
                {templates.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('settings.emptyTemplates')}
                    />
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {templates.map((tpl) => (
                            <li key={tpl.id}>
                                <button
                                    type="button"
                                    onClick={() => setTemplate(tpl)}
                                    data-template={tpl.code}
                                    className={cn(
                                        'flex w-full flex-col items-start gap-1 rounded-lg border-s-4 p-4 text-start focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        shiftColor(tpl.color).block,
                                        !tpl.isActive && 'opacity-60',
                                    )}
                                >
                                    <span className="font-medium">
                                        {localized(tpl.name, locale)}
                                    </span>
                                    <span className="text-small text-text-secondary">
                                        <bdi dir="ltr">{tpl.code}</bdi> ·{' '}
                                        <ShiftTime startsAt={tpl.startsAt} endsAt={tpl.endsAt} /> ·{' '}
                                        {t('shift.break', { minutes: tpl.breakMinutes })}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <section aria-labelledby="rotations-title" className="flex flex-col gap-3">
                <div className="flex items-center justify-between gap-2">
                    <h2 id="rotations-title" className="text-h4">
                        {t('settings.rotations')}
                    </h2>
                    <Button
                        variant="outline"
                        className="min-h-11"
                        disabled={templates.length === 0}
                        onClick={() => setRotation('new')}
                    >
                        <Plus aria-hidden className="size-4" />
                        {t('settings.addRotation')}
                    </Button>
                </div>
                {rotations.length === 0 ? (
                    <p className="text-small text-muted-foreground">
                        {t('settings.emptyRotations')}
                    </p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                        {rotations.map((r) => (
                            <li key={r.id}>
                                <button
                                    type="button"
                                    onClick={() => setRotation(r)}
                                    className="flex min-h-14 w-full flex-wrap items-center gap-3 p-4 text-start hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    <span className="font-medium">{localized(r.name, locale)}</span>
                                    <span className="flex flex-wrap gap-1" aria-hidden>
                                        {r.days.map((d, i) => {
                                            const tpl = templates.find((x) => x.id === d);

                                            return (
                                                <span
                                                    key={i}
                                                    className={cn(
                                                        'size-3 rounded-sm',
                                                        tpl
                                                            ? shiftColor(tpl.color).swatch
                                                            : 'border border-border bg-card',
                                                    )}
                                                />
                                            );
                                        })}
                                    </span>
                                    <span className="sr-only">
                                        {r.days
                                            .map((d) =>
                                                d
                                                    ? localized(
                                                          templates.find((x) => x.id === d)?.name ??
                                                              {},
                                                          locale,
                                                      )
                                                    : t('settings.off'),
                                            )
                                            .join(', ')}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            {template ? (
                <TemplateDialog
                    template={template === 'new' ? null : template}
                    onClose={() => setTemplate(null)}
                />
            ) : null}
            {rotation ? (
                <RotationEditor
                    rotation={rotation === 'new' ? null : rotation}
                    templates={templates}
                    onClose={() => setRotation(null)}
                />
            ) : null}
        </>
    );
}

function TemplateDialog({
    template,
    onClose,
}: {
    template: ShiftTemplate | null;
    onClose: () => void;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const form = useForm({
        code: template?.code ?? '',
        name: { en: template?.name.en ?? '', ar: template?.name.ar ?? '' },
        startsAt: template?.startsAt ?? '09:00',
        endsAt: template?.endsAt ?? '17:00',
        breakMinutes: template?.breakMinutes ?? 60,
        color: template?.color ?? 1,
        branchId: template?.branchId ?? null,
        isActive: template?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (template) {
            form.put(schedule.templates.update.url(template.id), options);
        } else {
            form.post(schedule.templates.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={template ? t('settings.editTemplate') : t('settings.addTemplate')}
            footer={
                <>
                    {template ? (
                        <Button
                            variant="ghost"
                            className="me-auto text-danger"
                            onClick={() =>
                                router.delete(schedule.templates.destroy.url(template.id), {
                                    preserveScroll: true,
                                    onSuccess: onClose,
                                })
                            }
                        >
                            {t('settings.remove')}
                        </Button>
                    ) : null}
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="template-form" loading={form.processing}>
                        {t('settings.save')}
                    </Button>
                </>
            }
        >
            <form id="template-form" onSubmit={submit} noValidate className="flex flex-col gap-4">
                <Field id="template-code" label={t('settings.code')} error={errors.code} required>
                    <Input
                        id="template-code"
                        dir="ltr"
                        disabled={template !== null}
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="w-40"
                    />
                </Field>
                <BilingualInput
                    id="template-name"
                    label={t('settings.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <div className="grid grid-cols-3 gap-3">
                    <Field
                        id="template-starts"
                        label={t('dialog.starts')}
                        error={errors.startsAt}
                        required
                    >
                        <Input
                            id="template-starts"
                            type="time"
                            dir="ltr"
                            value={form.data.startsAt}
                            onChange={(e) => form.setData('startsAt', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="template-ends"
                        label={t('dialog.ends')}
                        error={errors.endsAt}
                        required
                    >
                        <Input
                            id="template-ends"
                            type="time"
                            dir="ltr"
                            value={form.data.endsAt}
                            onChange={(e) => form.setData('endsAt', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="template-break"
                        label={t('dialog.break')}
                        error={errors.breakMinutes}
                    >
                        <Input
                            id="template-break"
                            inputMode="numeric"
                            value={String(form.data.breakMinutes)}
                            onChange={(e) =>
                                form.setData('breakMinutes', Number(e.target.value) || 0)
                            }
                        />
                    </Field>
                </div>
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-small font-medium">{t('settings.color')}</legend>
                    <div
                        role="radiogroup"
                        aria-label={t('settings.color')}
                        className="flex flex-wrap gap-2"
                    >
                        {SHIFT_COLORS.map((c) => (
                            <button
                                key={c}
                                type="button"
                                role="radio"
                                aria-checked={form.data.color === c}
                                aria-label={t('settings.colorN', { n: formatNumber(c, locale) })}
                                onClick={() => form.setData('color', c)}
                                className={cn(
                                    'size-11 rounded-md border-2 border-transparent p-1.5 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                    form.data.color === c && 'border-foreground',
                                )}
                            >
                                <span
                                    className={cn(
                                        'block size-full rounded-sm',
                                        shiftColor(c).swatch,
                                    )}
                                />
                            </button>
                        ))}
                    </div>
                </fieldset>
                <SwitchField
                    id="template-active"
                    label={t('settings.active')}
                    checked={form.data.isActive}
                    onCheckedChange={(v) => form.setData('isActive', v)}
                />
            </form>
        </ResponsiveDialog>
    );
}

function RotationEditor({
    rotation,
    templates,
    onClose,
}: {
    rotation: Rotation | null;
    templates: ShiftTemplate[];
    onClose: () => void;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const form = useForm({
        name: { en: rotation?.name.en ?? '', ar: rotation?.name.ar ?? '' },
        days: rotation?.days ?? ([templates[0]?.id ?? null, null] as Array<string | null>),
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (rotation) {
            form.put(schedule.rotations.update.url(rotation.id), options);
        } else {
            form.post(schedule.rotations.store.url(), options);
        }
    };
    const setDay = (index: number, value: string | null) =>
        form.setData(
            'days',
            form.data.days.map((d, i) => (i === index ? value : d)),
        );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={rotation ? t('settings.editRotation') : t('settings.addRotation')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="rotation-form" loading={form.processing}>
                        {t('settings.save')}
                    </Button>
                </>
            }
        >
            <form id="rotation-form" onSubmit={submit} noValidate className="flex flex-col gap-4">
                <BilingualInput
                    id="rotation-name"
                    label={t('settings.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-small font-medium">{t('settings.days')}</legend>
                    <ol className="flex flex-col gap-2">
                        {form.data.days.map((day, index) => (
                            <li key={index} className="flex items-center gap-2">
                                <span className="w-16 shrink-0 text-small text-text-secondary">
                                    {t('settings.day', { n: formatNumber(index + 1, locale) })}
                                </span>
                                <Select
                                    value={day ?? OFF}
                                    onValueChange={(v) => setDay(index, v === OFF ? null : v)}
                                >
                                    <SelectTrigger
                                        aria-label={t('settings.day', {
                                            n: formatNumber(index + 1, locale),
                                        })}
                                        className="min-h-11 flex-1"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {templates.map((tpl) => (
                                            <SelectItem key={tpl.id} value={tpl.id}>
                                                {localized(tpl.name, locale)} · {tpl.startsAt}–
                                                {tpl.endsAt}
                                            </SelectItem>
                                        ))}
                                        <SelectItem value={OFF}>{t('settings.off')}</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    disabled={form.data.days.length <= 1}
                                    aria-label={t('settings.removeDay', {
                                        n: formatNumber(index + 1, locale),
                                    })}
                                    onClick={() =>
                                        form.setData(
                                            'days',
                                            form.data.days.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <X aria-hidden className="size-4" />
                                </Button>
                            </li>
                        ))}
                    </ol>
                    {errors.days ? <p className="text-small text-danger">{errors.days}</p> : null}
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11 self-start"
                        disabled={form.data.days.length >= 56}
                        onClick={() => form.setData('days', [...form.data.days, null])}
                    >
                        <Plus aria-hidden className="size-4" />
                        {t('settings.addDay')}
                    </Button>
                </fieldset>
            </form>
        </ResponsiveDialog>
    );
}
