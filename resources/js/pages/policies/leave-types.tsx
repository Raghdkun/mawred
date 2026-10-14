import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SwitchField } from '@/components/mw/switch-field';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { ConfirmDialog } from '@/features/policies/confirm-dialog';
import { PoliciesTabs } from '@/features/policies/policies-tabs';
import { type LeaveType } from '@/features/policies/types';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import leaveTypeRoutes from '@/routes/policies/leave-types';

const ANYONE = 'anyone';

/** Leave type catalogue (D48). Entitlement and accrual live in policy sets as `leave.type.<CODE>`. */
export default function LeaveTypes({
    types,
    can,
}: {
    types: LeaveType[];
    can: { configure: boolean };
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<LeaveType | 'new' | null>(null);

    return (
        <>
            <Head title={t('leaveTypes.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('leaveTypes.rulesHint')}
                actions={
                    can.configure ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            {t('leaveTypes.new')}
                        </Button>
                    ) : null
                }
            />
            <PoliciesTabs />
            {types.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-policies"
                    title={t('leaveTypes.empty')}
                />
            ) : (
                <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                    {types.map((type) => (
                        <li
                            key={type.id}
                            className="flex min-h-14 flex-wrap items-center gap-3 border-b border-border px-4 py-3 last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm font-medium">
                                    {localized(type.name, locale)}
                                </span>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {type.code}
                                </bdi>
                            </span>
                            <span className="flex flex-wrap items-center gap-1.5">
                                <Badge variant="outline">
                                    {t(`leaveTypes.units.${type.unit}`)}
                                </Badge>
                                <Badge variant="outline">
                                    {type.isPaid ? t('leaveTypes.isPaid') : t('leaveTypes.unpaid')}
                                </Badge>
                                {type.eligibleGender ? (
                                    <Badge variant="outline">
                                        {t(`leaveTypes.${type.eligibleGender}`)}
                                    </Badge>
                                ) : null}
                                {!type.isActive ? (
                                    <Badge variant="outline">{t('common:inactive')}</Badge>
                                ) : null}
                            </span>
                            {can.configure ? (
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => setEditing(type)}
                                    aria-label={`${t('common:actions.edit')}: ${localized(type.name, locale)}`}
                                >
                                    <Pencil aria-hidden />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            {editing ? (
                <TypeDialog
                    type={editing === 'new' ? null : editing}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function TypeDialog({ type, onClose }: { type: LeaveType | null; onClose: () => void }) {
    const { t } = useTranslation('policies');
    const [archiving, setArchiving] = useState(false);
    const form = useForm({
        code: type?.code ?? '',
        name: { en: type?.name.en ?? '', ar: type?.name.ar ?? '' },
        unit: type?.unit ?? 'days',
        isPaid: type?.isPaid ?? true,
        allowsHalfDays: type?.allowsHalfDays ?? true,
        eligibleGender: type?.eligibleGender ?? null,
        requiresAttachment: type?.requiresAttachment ?? false,
        sortOrder: type?.sortOrder ?? 100,
        isActive: type?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (type) {
            form.put(leaveTypeRoutes.update.url(type.id), options);
        } else {
            form.post(leaveTypeRoutes.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={type ? t('leaveTypes.edit') : t('leaveTypes.new')}
            footer={
                <>
                    {type ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() => setArchiving(true)}
                        >
                            {t('leaveTypes.archive')}
                        </Button>
                    ) : null}
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="leave-type-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form id="leave-type-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field
                    id="leave-code"
                    label={t('leaveTypes.code')}
                    hint={t('leaveTypes.codeHint')}
                    error={errors.code}
                    required
                >
                    <Input
                        id="leave-code"
                        dir="ltr"
                        value={form.data.code}
                        disabled={Boolean(type)}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="leave-name"
                    label={t('leaveTypes.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <div className="flex flex-col gap-1.5">
                    <span id="leave-unit-label" className="text-sm font-medium">
                        {t('leaveTypes.unit')}
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        aria-labelledby="leave-unit-label"
                        value={form.data.unit}
                        onValueChange={(unit) => unit && form.setData('unit', unit)}
                        className="justify-start"
                    >
                        <ToggleGroupItem value="days" className="px-3">
                            {t('leaveTypes.units.days')}
                        </ToggleGroupItem>
                        <ToggleGroupItem value="hours" className="px-3">
                            {t('leaveTypes.units.hours')}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <SwitchField
                    id="leave-paid"
                    label={t('leaveTypes.isPaid')}
                    checked={form.data.isPaid}
                    onCheckedChange={(checked) => form.setData('isPaid', checked)}
                />
                {form.data.unit === 'days' ? (
                    <SwitchField
                        id="leave-half"
                        label={t('leaveTypes.allowsHalfDays')}
                        checked={form.data.allowsHalfDays}
                        onCheckedChange={(checked) => form.setData('allowsHalfDays', checked)}
                    />
                ) : null}
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="leave-gender">{t('leaveTypes.eligibleGender')}</Label>
                    <Select
                        value={form.data.eligibleGender ?? ANYONE}
                        onValueChange={(value) =>
                            form.setData('eligibleGender', value === ANYONE ? null : value)
                        }
                    >
                        <SelectTrigger id="leave-gender" className="w-48">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ANYONE}>{t('leaveTypes.anyone')}</SelectItem>
                            <SelectItem value="female">{t('leaveTypes.female')}</SelectItem>
                            <SelectItem value="male">{t('leaveTypes.male')}</SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <SwitchField
                    id="leave-attachment"
                    label={t('leaveTypes.requiresAttachment')}
                    checked={form.data.requiresAttachment}
                    onCheckedChange={(checked) => form.setData('requiresAttachment', checked)}
                />
                <Field id="leave-order" label={t('leaveTypes.sortOrder')} error={errors.sortOrder}>
                    <Input
                        id="leave-order"
                        type="number"
                        dir="ltr"
                        className="w-28"
                        value={form.data.sortOrder}
                        onChange={(event) => form.setData('sortOrder', Number(event.target.value))}
                    />
                </Field>
                <SwitchField
                    id="leave-active"
                    label={t('common:active')}
                    checked={form.data.isActive}
                    onCheckedChange={(checked) => form.setData('isActive', checked)}
                />
            </form>
            {type ? (
                <ConfirmDialog
                    open={archiving}
                    onOpenChange={setArchiving}
                    title={t('leaveTypes.archive')}
                    description={t('leaveTypes.archiveConfirm')}
                    confirmLabel={t('common:actions.archive')}
                    onConfirm={() =>
                        router.delete(leaveTypeRoutes.destroy.url(type.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        })
                    }
                />
            ) : null}
        </ResponsiveDialog>
    );
}
