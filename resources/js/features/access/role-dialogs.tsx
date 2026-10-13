import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
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
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import accessRoles from '@/routes/access/roles';

type Role = App.Domain.Access.Data.RoleSummaryData;

function BilingualNameFields({
    data,
    setData,
    errors,
}: {
    data: { label: { en: string; ar: string } };
    setData: (key: 'label', value: { en: string; ar: string }) => void;
    errors: Record<string, string>;
}) {
    const { t } = useTranslation('access');

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="label-en">{t('details.labelEn')}</Label>
                <Input
                    id="label-en"
                    dir="ltr"
                    lang="en"
                    value={data.label.en}
                    onChange={(event) =>
                        setData('label', { ...data.label, en: event.target.value })
                    }
                    required
                />
                <InputError message={errors['label.en']} />
            </div>
            <div className="flex flex-col gap-1.5">
                <Label htmlFor="label-ar">{t('details.labelAr')}</Label>
                <Input
                    id="label-ar"
                    dir="rtl"
                    lang="ar"
                    value={data.label.ar}
                    onChange={(event) =>
                        setData('label', { ...data.label, ar: event.target.value })
                    }
                    required
                />
                <InputError message={errors['label.ar']} />
            </div>
        </div>
    );
}

export function EditRoleDialog({
    role,
    homeLayouts,
    open,
    onOpenChange,
}: {
    role: Role;
    homeLayouts: string[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('access');
    const form = useForm({
        label: { en: role.label.en ?? '', ar: role.label.ar ?? '' },
        description: { en: role.description.en ?? '', ar: role.description.ar ?? '' },
        requiresTwoFactor: role.requiresTwoFactor,
        homeLayout: role.homeLayout,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.patch(accessRoles.update.url(role.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>{t('details.edit')}</DialogTitle>
                    </DialogHeader>
                    <BilingualNameFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                    />
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="description-en">{t('details.descriptionEn')}</Label>
                            <Textarea
                                id="description-en"
                                dir="ltr"
                                lang="en"
                                rows={3}
                                value={form.data.description.en}
                                onChange={(event) =>
                                    form.setData('description', {
                                        ...form.data.description,
                                        en: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="description-ar">{t('details.descriptionAr')}</Label>
                            <Textarea
                                id="description-ar"
                                dir="rtl"
                                lang="ar"
                                rows={3}
                                value={form.data.description.ar}
                                onChange={(event) =>
                                    form.setData('description', {
                                        ...form.data.description,
                                        ar: event.target.value,
                                    })
                                }
                            />
                        </div>
                    </div>
                    <div className="flex items-start justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="requires-2fa">{t('details.requiresTwoFactor')}</Label>
                            <p className="text-small text-muted-foreground">
                                {t('details.requiresTwoFactorHint')}
                            </p>
                        </div>
                        <Switch
                            id="requires-2fa"
                            checked={form.data.requiresTwoFactor}
                            disabled={role.isSuperAdmin}
                            onCheckedChange={(checked) =>
                                form.setData('requiresTwoFactor', checked)
                            }
                        />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="home-layout">{t('details.homeLayout')}</Label>
                        <select
                            id="home-layout"
                            value={form.data.homeLayout}
                            onChange={(event) => form.setData('homeLayout', event.target.value)}
                            className="h-10 rounded-md border border-input bg-card px-3 text-sm hover:border-input-hover focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none"
                        >
                            {homeLayouts.map((layout) => (
                                <option key={layout} value={layout}>
                                    {t(`details.homeLayouts.${layout}`)}
                                </option>
                            ))}
                        </select>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {t('details.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function CloneRoleDialog({
    role,
    roleLabel,
    open,
    onOpenChange,
}: {
    role: Role;
    roleLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('access');
    const form = useForm({ label: { en: '', ar: '' } });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(accessRoles.clone.url(role.id), { onSuccess: () => onOpenChange(false) });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-xl">
                <form onSubmit={submit} className="flex flex-col gap-5">
                    <DialogHeader>
                        <DialogTitle>{t('details.cloneTitle', { role: roleLabel })}</DialogTitle>
                        <DialogDescription>{t('details.cloneDescription')}</DialogDescription>
                    </DialogHeader>
                    <BilingualNameFields
                        data={form.data}
                        setData={form.setData}
                        errors={form.errors}
                    />
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button type="submit" loading={form.processing}>
                            {t('details.clone')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function DeleteRoleDialog({
    role,
    roleLabel,
    open,
    onOpenChange,
}: {
    role: Role;
    roleLabel: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('access');
    const form = useForm({});

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {t('details.deleteTitle', { role: roleLabel })}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {t('details.deleteDescription')}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                {form.errors && 'error' in form.errors ? (
                    <InputError message={(form.errors as Record<string, string>).error} />
                ) : null}
                <AlertDialogFooter>
                    <AlertDialogCancel>{t('common:actions.cancel')}</AlertDialogCancel>
                    <AlertDialogAction
                        className="bg-destructive text-destructive-foreground hover:bg-destructive-hover"
                        onClick={(event) => {
                            event.preventDefault();
                            form.delete(accessRoles.destroy.url(role.id), {
                                onSuccess: () => onOpenChange(false),
                            });
                        }}
                    >
                        {t('details.delete')}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
