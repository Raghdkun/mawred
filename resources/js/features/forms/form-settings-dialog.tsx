import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import forms from '@/routes/forms';

type FormData = App.Domain.Forms.Data.FormData;

/** Create a form, or edit its name and public link (D53). The code is fixed once created. */
export function FormSettingsDialog({
    form,
    onClose,
}: {
    form: FormData | null;
    onClose: () => void;
}) {
    const { t } = useTranslation('forms');
    const data = useForm({
        code: form?.code ?? '',
        name: { en: form?.name.en ?? '', ar: form?.name.ar ?? '' },
        description: { en: form?.description.en ?? '', ar: form?.description.ar ?? '' },
        category: form?.category ?? '',
        isPublic: form?.isPublic ?? false,
        publicSlug: form?.publicSlug ?? '',
        successMessage: { en: form?.successMessage.en ?? '', ar: form?.successMessage.ar ?? '' },
        closesAt: form?.closesAt ?? '',
    });
    const errors = data.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        data.transform((values) => ({
            ...values,
            category: values.category || null,
            publicSlug: values.isPublic ? values.publicSlug || null : null,
            closesAt: values.closesAt || null,
        }));

        if (form) {
            data.put(forms.update.url(form.id), { preserveScroll: true, onSuccess: onClose });
        } else {
            data.post(forms.store.url());
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={form ? t('settings.title') : t('index.new')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="form-settings" loading={data.processing}>
                        {form ? t('common:actions.save') : t('common:actions.create')}
                    </Button>
                </>
            }
        >
            <form id="form-settings" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field
                    id="form-code"
                    label={t('settings.code')}
                    hint={t('settings.codeHint')}
                    error={errors.code}
                    required
                >
                    <Input
                        id="form-code"
                        dir="ltr"
                        value={data.data.code}
                        disabled={Boolean(form)}
                        onChange={(event) => data.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="form-name"
                    label={t('settings.name')}
                    value={data.data.name}
                    onChange={(name) => data.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <BilingualInput
                    id="form-description"
                    label={t('settings.description')}
                    value={data.data.description}
                    onChange={(description) => data.setData('description', description)}
                    errors={errors}
                    errorKey="description"
                />
                <Field
                    id="form-category"
                    label={t('settings.category')}
                    hint={t('settings.categoryHint')}
                    error={errors.category}
                >
                    <Input
                        id="form-category"
                        value={data.data.category}
                        onChange={(event) => data.setData('category', event.target.value)}
                    />
                </Field>
                {form ? (
                    <>
                        <SwitchField
                            id="form-public"
                            label={t('settings.public')}
                            hint={t('settings.publicHint')}
                            checked={data.data.isPublic}
                            onCheckedChange={(on) => data.setData('isPublic', on)}
                        />
                        {data.data.isPublic ? (
                            <>
                                <Field
                                    id="form-slug"
                                    label={t('settings.slug')}
                                    hint={t('settings.slugHint')}
                                    error={errors.publicSlug ?? errors.isPublic}
                                    required
                                >
                                    <Input
                                        id="form-slug"
                                        dir="ltr"
                                        value={data.data.publicSlug}
                                        onChange={(event) =>
                                            data.setData(
                                                'publicSlug',
                                                event.target.value
                                                    .toLowerCase()
                                                    .replace(/[^a-z0-9-]/g, '-'),
                                            )
                                        }
                                    />
                                </Field>
                                <BilingualInput
                                    id="form-success"
                                    label={t('settings.successMessage')}
                                    value={data.data.successMessage}
                                    onChange={(successMessage) =>
                                        data.setData('successMessage', successMessage)
                                    }
                                    errors={errors}
                                    errorKey="successMessage"
                                />
                                <Field
                                    id="form-closes"
                                    label={t('settings.closesAt')}
                                    hint={t('settings.closesAtHint')}
                                    error={errors.closesAt}
                                >
                                    <Input
                                        id="form-closes"
                                        type="date"
                                        dir="ltr"
                                        className="w-44"
                                        value={data.data.closesAt}
                                        onChange={(event) =>
                                            data.setData('closesAt', event.target.value)
                                        }
                                    />
                                </Field>
                            </>
                        ) : null}
                    </>
                ) : null}
            </form>
        </ResponsiveDialog>
    );
}
