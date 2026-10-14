import { useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import people from '@/routes/people';
import {
    emptyPlacement,
    PlacementFields,
    placementPayload,
    type PlacementValue,
} from './placement-fields';

type Catalog = App.Domain.Organization.Data.CatalogEntryData;

/** "Add employee" (screen 7): the essentials; everything else is edited on the profile. */
export function AddEmployeeDialog({
    open,
    onOpenChange,
    employmentTypes,
    grades,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employmentTypes: Catalog[];
    grades: Catalog[];
}) {
    const { t } = useTranslation('people');
    const [placement, setPlacement] = useState<PlacementValue>(emptyPlacement);
    const form = useForm({
        nameEn: '',
        nameAr: '',
        preferredName: '',
        employeeNumber: '',
        workEmail: '',
        workPhone: '',
        hireDate: new Date().toISOString().slice(0, 10),
        probationEndsOn: '',
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...Object.fromEntries(
                Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]),
            ),
            placement: placementPayload(placement),
        }));
        form.post(people.store.url(), { onSuccess: () => onOpenChange(false) });
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('add.title')}
            description={t('add.description')}
            className="sm:max-w-3xl"
            footer={
                <>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="add-employee-form" loading={form.processing}>
                        {t('add.submit')}
                    </Button>
                </>
            }
        >
            <form
                id="add-employee-form"
                onSubmit={submit}
                className="flex flex-col gap-5"
                noValidate
            >
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="add-name-en"
                        label={t('fields.nameEn')}
                        error={errors.nameEn}
                        required
                    >
                        <Input
                            id="add-name-en"
                            dir="ltr"
                            lang="en"
                            value={form.data.nameEn}
                            onChange={(event) => form.setData('nameEn', event.target.value)}
                        />
                    </Field>
                    <Field id="add-name-ar" label={t('fields.nameAr')} error={errors.nameAr}>
                        <Input
                            id="add-name-ar"
                            dir="rtl"
                            lang="ar"
                            value={form.data.nameAr}
                            onChange={(event) => form.setData('nameAr', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="add-preferred"
                        label={t('fields.preferredName')}
                        error={errors.preferredName}
                    >
                        <Input
                            id="add-preferred"
                            value={form.data.preferredName}
                            onChange={(event) => form.setData('preferredName', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="add-number"
                        label={t('fields.employeeNumber')}
                        error={errors.employeeNumber}
                        hint={t('hints.employeeNumber')}
                    >
                        <Input
                            id="add-number"
                            dir="ltr"
                            value={form.data.employeeNumber}
                            onChange={(event) =>
                                form.setData('employeeNumber', event.target.value.toUpperCase())
                            }
                            placeholder={t('hints.auto')}
                        />
                    </Field>
                    <Field id="add-email" label={t('fields.workEmail')} error={errors.workEmail}>
                        <Input
                            id="add-email"
                            type="email"
                            dir="ltr"
                            autoComplete="off"
                            value={form.data.workEmail}
                            onChange={(event) => form.setData('workEmail', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="add-phone"
                        label={t('fields.workPhone')}
                        error={errors.workPhone}
                        hint={t('hints.phone')}
                    >
                        <Input
                            id="add-phone"
                            type="tel"
                            dir="ltr"
                            value={form.data.workPhone}
                            onChange={(event) => form.setData('workPhone', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="add-hire"
                        label={t('fields.hireDate')}
                        error={errors.hireDate}
                        required
                    >
                        <Input
                            id="add-hire"
                            type="date"
                            dir="ltr"
                            value={form.data.hireDate}
                            onChange={(event) => form.setData('hireDate', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="add-probation"
                        label={t('fields.probationEndsOn')}
                        error={errors.probationEndsOn}
                    >
                        <Input
                            id="add-probation"
                            type="date"
                            dir="ltr"
                            value={form.data.probationEndsOn}
                            onChange={(event) =>
                                form.setData('probationEndsOn', event.target.value)
                            }
                        />
                    </Field>
                </div>
                <fieldset className="flex flex-col gap-3">
                    <legend className="mb-2 text-body font-semibold text-foreground">
                        {t('sections.placement')}
                    </legend>
                    <PlacementFields
                        value={placement}
                        onChange={setPlacement}
                        errors={errors}
                        errorPrefix="placement."
                        employmentTypes={employmentTypes}
                        grades={grades}
                    />
                </fieldset>
            </form>
        </ResponsiveDialog>
    );
}
