import { Head, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { rangeLabel } from '@/features/leave/format';
import { BranchPicker, DepartmentPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import leave from '@/routes/leave';

type Blackout = App.Domain.Leave.Data.LeaveBlackoutData;
type Props = {
    blackouts: Blackout[];
    leaveTypes: Array<{ id: string; name: Record<string, string> }>;
};

/** Blackout periods (leave.configure): dates when new leave requests are refused (D65). */
export default function Blackouts({ blackouts, leaveTypes }: Props) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Blackout | 'new' | null>(null);
    const [deleting, setDeleting] = useState<Blackout | null>(null);

    return (
        <>
            <Head title={t('blackouts.title')} />
            <PageHeader
                title={t('blackouts.title')}
                description={t('blackouts.description')}
                actions={
                    <Button className="min-h-11" onClick={() => setEditing('new')}>
                        <Plus aria-hidden className="size-4" />
                        {t('blackouts.add')}
                    </Button>
                }
            />

            {blackouts.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('blackouts.emptyTitle')}
                    description={t('blackouts.emptyDescription')}
                />
            ) : (
                <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                    {blackouts.map((blackout) => (
                        <li
                            key={blackout.id}
                            data-blackout={blackout.id}
                            className="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:gap-4"
                        >
                            <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <span className="font-medium">
                                    {localized(blackout.name, locale)}
                                </span>
                                <span className="text-small text-text-secondary">
                                    {rangeLabel(blackout.startsOn, blackout.endsOn, locale)}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {[
                                        blackout.branchName
                                            ? localized(blackout.branchName, locale)
                                            : null,
                                        blackout.departmentName
                                            ? localized(blackout.departmentName, locale)
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || t('blackouts.everyone')}
                                    {' · '}
                                    {blackout.leaveTypes.length === 0
                                        ? t('blackouts.allTypes')
                                        : blackout.leaveTypes
                                              .map((type) => localized(type.name, locale))
                                              .join('، ')}
                                </span>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="min-h-11"
                                    onClick={() => setEditing(blackout)}
                                >
                                    {t('common:actions.edit')}
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    className="min-h-11 text-danger"
                                    onClick={() => setDeleting(blackout)}
                                >
                                    {t('blackouts.delete')}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {editing ? (
                <BlackoutDialog
                    blackout={editing === 'new' ? null : editing}
                    leaveTypes={leaveTypes}
                    onClose={() => setEditing(null)}
                />
            ) : null}

            <AlertDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('blackouts.deleteTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {t('blackouts.deleteDescription')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('common:actions.cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive-hover"
                            onClick={() =>
                                deleting &&
                                router.delete(leave.blackouts.destroy.url(deleting.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {t('blackouts.delete')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function BlackoutDialog({
    blackout,
    leaveTypes,
    onClose,
}: {
    blackout: Blackout | null;
    leaveTypes: Props['leaveTypes'];
    onClose: () => void;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const [branch, setBranch] = useState<PickerOption | null>(
        blackout?.branchId && blackout.branchName
            ? { id: blackout.branchId, label: localized(blackout.branchName, locale) }
            : null,
    );
    const [department, setDepartment] = useState<PickerOption | null>(
        blackout?.departmentId && blackout.departmentName
            ? { id: blackout.departmentId, label: localized(blackout.departmentName, locale) }
            : null,
    );
    const form = useForm({
        name: { en: blackout?.name.en ?? '', ar: blackout?.name.ar ?? '' },
        startsOn: blackout?.startsOn ?? '',
        endsOn: blackout?.endsOn ?? '',
        leaveTypeIds: blackout?.leaveTypes.map((type) => type.id) ?? ([] as string[]),
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            branchId: branch?.id ?? null,
            departmentId: department?.id ?? null,
        }));
        const options = { preserveScroll: true, onSuccess: onClose };

        if (blackout) {
            form.put(leave.blackouts.update.url(blackout.id), options);
        } else {
            form.post(leave.blackouts.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={blackout ? t('blackouts.edit') : t('blackouts.add')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="blackout-form" loading={form.processing}>
                        {t('blackouts.save')}
                    </Button>
                </>
            }
        >
            <form id="blackout-form" onSubmit={submit} noValidate className="flex flex-col gap-4">
                <BilingualInput
                    id="blackout-name"
                    label={t('blackouts.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="blackout-from"
                        label={t('blackouts.from')}
                        error={errors.startsOn}
                        required
                    >
                        <Input
                            id="blackout-from"
                            type="date"
                            value={form.data.startsOn}
                            onChange={(e) => form.setData('startsOn', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="blackout-to"
                        label={t('blackouts.to')}
                        error={errors.endsOn}
                        required
                    >
                        <Input
                            id="blackout-to"
                            type="date"
                            min={form.data.startsOn || undefined}
                            value={form.data.endsOn}
                            onChange={(e) => form.setData('endsOn', e.target.value)}
                        />
                    </Field>
                </div>
                <BranchPicker label={t('blackouts.branch')} value={branch} onChange={setBranch} />
                <DepartmentPicker
                    label={t('blackouts.department')}
                    value={department}
                    onChange={setDepartment}
                />
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-small font-medium">{t('blackouts.types')}</legend>
                    <p className="text-caption text-muted-foreground">{t('blackouts.typesHint')}</p>
                    {leaveTypes.map((type) => {
                        const checked = form.data.leaveTypeIds.includes(type.id);

                        return (
                            <div key={type.id} className="flex min-h-11 items-center gap-2">
                                <Checkbox
                                    id={`blackout-type-${type.id}`}
                                    checked={checked}
                                    onCheckedChange={(value) =>
                                        form.setData(
                                            'leaveTypeIds',
                                            value === true
                                                ? [...form.data.leaveTypeIds, type.id]
                                                : form.data.leaveTypeIds.filter(
                                                      (id) => id !== type.id,
                                                  ),
                                        )
                                    }
                                />
                                <Label htmlFor={`blackout-type-${type.id}`}>
                                    {localized(type.name, locale)}
                                </Label>
                            </div>
                        );
                    })}
                </fieldset>
            </form>
        </ResponsiveDialog>
    );
}
