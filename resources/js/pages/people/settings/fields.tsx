import { Head, router, useForm } from '@inertiajs/react';
import { Lock, Pencil, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
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
import { PeopleSettingsTabs } from '@/features/people/settings-tabs';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import people from '@/routes/people';

type Option = { value: string; label: { en: string; ar?: string } };
type CustomField = {
    id: string;
    key: string;
    label: Record<string, string>;
    help: Record<string, string>;
    type: string;
    options: Option[];
    section: 'personal' | 'job';
    isRequired: boolean;
    isSensitive: boolean;
    isActive: boolean;
    sortOrder: number;
};

/** People settings → Custom fields: extra employee fields, optionally sensitive (field permissions). */
export default function CustomFieldsSettings({
    fields,
    types,
}: {
    fields: CustomField[];
    types: string[];
}) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<CustomField | 'new' | null>(null);

    return (
        <>
            <Head title={t('settings.fields.title')} />
            <PageHeader
                title={t('settings.title')}
                description={t('settings.description')}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus aria-hidden />
                        {t('settings.fields.new')}
                    </Button>
                }
            />
            <PeopleSettingsTabs />
            {fields.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-search"
                    title={t('settings.fields.empty')}
                    description={t('settings.fields.emptyHelp')}
                />
            ) : (
                <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                    {fields.map((field) => (
                        <li
                            key={field.id}
                            className="flex min-h-14 flex-wrap items-center gap-3 border-b border-border px-4 py-3 last:border-b-0"
                        >
                            {field.isSensitive ? (
                                <Lock
                                    aria-label={t('settings.fields.sensitive')}
                                    className="size-4 text-text-secondary"
                                />
                            ) : null}
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm font-medium">
                                    {localized(field.label, locale)}
                                </span>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {field.key}
                                </bdi>
                            </span>
                            <Badge variant="secondary">
                                {t(`settings.fields.types.${field.type}`)}
                            </Badge>
                            <Badge variant="outline">
                                {t(`settings.fields.sections.${field.section}`)}
                            </Badge>
                            {!field.isActive ? (
                                <Badge variant="outline">{t('common:inactive')}</Badge>
                            ) : null}
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => setEditing(field)}
                                aria-label={`${t('common:actions.edit')}: ${localized(field.label, locale)}`}
                            >
                                <Pencil aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
            {editing ? (
                <FieldDialog
                    field={editing === 'new' ? null : editing}
                    types={types}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function FieldDialog({
    field,
    types,
    onClose,
}: {
    field: CustomField | null;
    types: string[];
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const form = useForm({
        key: field?.key ?? '',
        label: { en: field?.label.en ?? '', ar: field?.label.ar ?? '' },
        help: { en: field?.help.en ?? '', ar: field?.help.ar ?? '' },
        type: field?.type ?? 'text',
        options: (field?.options ?? []).map((option) => ({
            value: option.value,
            label: { en: option.label.en, ar: option.label.ar ?? '' },
        })),
        section: field?.section ?? 'personal',
        isRequired: field?.isRequired ?? false,
        isSensitive: field?.isSensitive ?? false,
        isActive: field?.isActive ?? true,
        sortOrder: field?.sortOrder ?? 0,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const hasOptions = form.data.type === 'select' || form.data.type === 'multiselect';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, options: hasOptions ? data.options : null }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (field) {
            form.put(people.settings.fields.update.url(field.id), options);
        } else {
            form.post(people.settings.fields.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={field ? t('settings.fields.edit') : t('settings.fields.new')}
            className="sm:max-w-2xl"
            footer={
                <>
                    {field && field.isActive ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() =>
                                router.delete(people.settings.fields.destroy.url(field.id), {
                                    onSuccess: onClose,
                                })
                            }
                        >
                            {t('common:actions.archive')}
                        </Button>
                    ) : null}
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="field-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form id="field-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <BilingualInput
                    id="field-label"
                    label={t('settings.fields.label')}
                    value={form.data.label}
                    onChange={(label) => form.setData('label', label)}
                    errors={errors}
                    errorKey="label"
                />
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field
                        id="field-key"
                        label={t('settings.fields.key')}
                        error={errors.key}
                        hint={field ? t('settings.fields.keyFixed') : t('settings.fields.keyHint')}
                        required
                    >
                        <Input
                            id="field-key"
                            dir="ltr"
                            value={form.data.key}
                            disabled={Boolean(field)}
                            onChange={(event) =>
                                form.setData(
                                    'key',
                                    event.target.value.toLowerCase().replace(/[^a-z0-9_]/g, '_'),
                                )
                            }
                        />
                    </Field>
                    <Field
                        id="field-type"
                        label={t('settings.fields.type')}
                        error={errors.type}
                        required
                    >
                        <Select
                            value={form.data.type}
                            onValueChange={(type) => form.setData('type', type)}
                        >
                            <SelectTrigger id="field-type">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {types.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {t(`settings.fields.types.${type}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="field-section"
                        label={t('settings.fields.section')}
                        error={errors.section}
                    >
                        <Select
                            value={form.data.section}
                            onValueChange={(section) =>
                                form.setData('section', section as 'personal' | 'job')
                            }
                        >
                            <SelectTrigger id="field-section">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="personal">
                                    {t('settings.fields.sections.personal')}
                                </SelectItem>
                                <SelectItem value="job">
                                    {t('settings.fields.sections.job')}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                </div>
                {hasOptions ? (
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('settings.fields.options')}
                        </legend>
                        {form.data.options.map((option, index) => (
                            <div key={index} className="flex items-end gap-2">
                                <Input
                                    aria-label={t('settings.fields.optionValue')}
                                    dir="ltr"
                                    className="w-28"
                                    value={option.value}
                                    onChange={(event) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((o, i) =>
                                                i === index
                                                    ? { ...o, value: event.target.value }
                                                    : o,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('settings.fields.optionEn')}
                                    dir="ltr"
                                    lang="en"
                                    value={option.label.en}
                                    onChange={(event) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((o, i) =>
                                                i === index
                                                    ? {
                                                          ...o,
                                                          label: {
                                                              ...o.label,
                                                              en: event.target.value,
                                                          },
                                                      }
                                                    : o,
                                            ),
                                        )
                                    }
                                />
                                <Input
                                    aria-label={t('settings.fields.optionAr')}
                                    dir="rtl"
                                    lang="ar"
                                    value={option.label.ar}
                                    onChange={(event) =>
                                        form.setData(
                                            'options',
                                            form.data.options.map((o, i) =>
                                                i === index
                                                    ? {
                                                          ...o,
                                                          label: {
                                                              ...o.label,
                                                              ar: event.target.value,
                                                          },
                                                      }
                                                    : o,
                                            ),
                                        )
                                    }
                                />
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('common:actions.remove')}
                                    onClick={() =>
                                        form.setData(
                                            'options',
                                            form.data.options.filter((_, i) => i !== index),
                                        )
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </div>
                        ))}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="self-start"
                            onClick={() =>
                                form.setData('options', [
                                    ...form.data.options,
                                    { value: '', label: { en: '', ar: '' } },
                                ])
                            }
                        >
                            <Plus aria-hidden />
                            {t('settings.fields.addOption')}
                        </Button>
                        {errors.options ? (
                            <p className="text-caption text-danger">{errors.options}</p>
                        ) : null}
                    </fieldset>
                ) : null}
                {(['isRequired', 'isSensitive', 'isActive'] as const).map((key) => (
                    <SwitchField
                        key={key}
                        id={`field-${key}`}
                        label={t(`settings.fields.${key}`)}
                        hint={t(`settings.fields.${key}Help`)}
                        checked={form.data[key]}
                        onCheckedChange={(checked) => form.setData(key, checked)}
                        disabled={key === 'isSensitive' && Boolean(field)}
                    />
                ))}
            </form>
        </ResponsiveDialog>
    );
}
