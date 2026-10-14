import { Head, Link, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { moduleIcons } from '@/components/mw/module-icons.generated';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import forms from '@/routes/forms';
import requestTypes from '@/routes/request-types';
import workflows from '@/routes/workflows';

type RequestType = App.Domain.Requests.Data.RequestTypeData;
type Option = { id: string; code: string; name: Record<string, string>; published: boolean };

const CATEGORIES = [
    'leave',
    'time',
    'finance',
    'documents',
    'people',
    'assets',
    'training',
] as const;
const selectClass =
    'h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/** Request types (settings): what people can ask for, and the form and workflow behind each. */
export default function RequestTypes({
    types,
    forms: formOptions,
    workflows: workflowOptions,
}: {
    types: RequestType[];
    forms: Option[];
    workflows: Option[];
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<RequestType | 'new' | null>(null);

    return (
        <>
            <Head title={t('types.pageTitle')} />
            <PageHeader
                title={t('types.title')}
                description={t('types.description')}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus aria-hidden />
                        {t('types.new')}
                    </Button>
                }
            />

            {types.length === 0 ? (
                <PageState kind="empty" illustration="empty-requests" title={t('types.empty')} />
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {types.map((type) => (
                        <li
                            key={type.id}
                            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 items-center gap-3">
                                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <RequestTypeIcon icon={type.icon} className="size-4" />
                                </span>
                                <span className="flex min-w-0 flex-col">
                                    <span className="truncate text-sm font-medium">
                                        {localized(type.name, locale)}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        <bdi dir="ltr">{type.code}</bdi> ·{' '}
                                        {t(`categories.${type.category}`)}
                                    </span>
                                </span>
                            </span>
                            <span className="flex flex-wrap items-center gap-2 md:w-72">
                                {!type.isActive ? (
                                    <Badge variant="outline">{t('types.inactive')}</Badge>
                                ) : !type.available ? (
                                    <Badge variant="outline">
                                        {t('types.later', {
                                            phase: formatNumber(type.availableInPhase, locale),
                                        })}
                                    </Badge>
                                ) : !type.ready ? (
                                    <Badge
                                        variant="outline"
                                        className="border-danger-border text-danger"
                                    >
                                        {t('types.notReady')}
                                    </Badge>
                                ) : (
                                    <Badge>{t('types.live')}</Badge>
                                )}
                                {type.isSensitive ? (
                                    <Badge variant="secondary">{t('types.private')}</Badge>
                                ) : null}
                            </span>
                            <span className="flex items-center gap-1 text-small">
                                {type.formId ? (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={forms.builder.url(type.formId)}>
                                            {t('types.form')}
                                        </Link>
                                    </Button>
                                ) : null}
                                {type.workflowId ? (
                                    <Button variant="ghost" size="sm" asChild>
                                        <Link href={workflows.builder.url(type.workflowId)}>
                                            {t('types.workflow')}
                                        </Link>
                                    </Button>
                                ) : null}
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={t('types.edit', {
                                        name: localized(type.name, locale),
                                    })}
                                    onClick={() => setEditing(type)}
                                >
                                    <Pencil aria-hidden />
                                </Button>
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {editing ? (
                <TypeDialog
                    type={editing === 'new' ? null : editing}
                    sortOrder={editing === 'new' ? (types.length + 1) * 10 : 0}
                    forms={formOptions}
                    workflows={workflowOptions}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function TypeDialog({
    type,
    sortOrder,
    forms: formOptions,
    workflows: workflowOptions,
    onClose,
}: {
    type: RequestType | null;
    sortOrder: number;
    forms: Option[];
    workflows: Option[];
    onClose: () => void;
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const form = useForm({
        code: type?.code ?? '',
        name: { en: type?.name.en ?? '', ar: type?.name.ar ?? '' },
        description: { en: type?.description.en ?? '', ar: type?.description.ar ?? '' },
        category: type?.category ?? 'time',
        icon: type?.icon ?? 'requests',
        isSensitive: type?.isSensitive ?? false,
        isActive: type?.isActive ?? true,
        sortOrder: sortOrder,
        formId: type?.formId ?? '',
        workflowId: type?.workflowId ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };

        if (type) {
            form.put(requestTypes.update.url(type.id), options);
        } else {
            form.post(requestTypes.store.url(), options);
        }
    };

    const option = (item: Option) =>
        `${localized(item.name, locale) || item.code}${item.published ? '' : ` (${t('types.unpublished')})`}`;

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={type ? localized(type.name, locale) : t('types.new')}
            className="sm:max-w-2xl"
        >
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Field id="type-code" label={t('types.code')} error={form.errors.code} required>
                    <Input
                        id="type-code"
                        dir="ltr"
                        value={form.data.code}
                        disabled={type !== null}
                        onChange={(event) => form.setData('code', event.target.value)}
                        required
                    />
                </Field>
                <BilingualInput
                    id="type-name"
                    label={t('types.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={form.errors}
                    errorKey="name"
                />
                <div className="grid gap-4 sm:grid-cols-2">
                    {(['en', 'ar'] as const).map((locale) => (
                        <Field
                            key={locale}
                            id={`type-description-${locale}`}
                            label={t(`types.description${locale === 'en' ? 'En' : 'Ar'}`)}
                        >
                            <Input
                                id={`type-description-${locale}`}
                                dir={locale === 'ar' ? 'rtl' : 'ltr'}
                                lang={locale}
                                value={form.data.description[locale]}
                                onChange={(event) =>
                                    form.setData('description', {
                                        ...form.data.description,
                                        [locale]: event.target.value,
                                    })
                                }
                            />
                        </Field>
                    ))}
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="type-category"
                        label={t('types.category')}
                        error={form.errors.category}
                    >
                        <select
                            id="type-category"
                            className={selectClass}
                            value={form.data.category}
                            onChange={(event) => form.setData('category', event.target.value)}
                        >
                            {CATEGORIES.map((category) => (
                                <option key={category} value={category}>
                                    {t(`categories.${category}`)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="type-icon" label={t('types.icon')} error={form.errors.icon}>
                        <select
                            id="type-icon"
                            className={selectClass}
                            value={form.data.icon}
                            onChange={(event) => form.setData('icon', event.target.value)}
                        >
                            {Object.keys(moduleIcons).map((icon) => (
                                <option key={icon} value={icon}>
                                    {icon}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="type-form"
                        label={t('types.form')}
                        error={form.errors.formId}
                        required
                    >
                        <select
                            id="type-form"
                            className={selectClass}
                            value={form.data.formId}
                            onChange={(event) => form.setData('formId', event.target.value)}
                            required
                        >
                            <option value="" disabled>
                                —
                            </option>
                            {formOptions.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {option(item)}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="type-workflow"
                        label={t('types.workflow')}
                        error={form.errors.workflowId}
                        required
                    >
                        <select
                            id="type-workflow"
                            className={selectClass}
                            value={form.data.workflowId}
                            onChange={(event) => form.setData('workflowId', event.target.value)}
                            required
                        >
                            <option value="" disabled>
                                —
                            </option>
                            {workflowOptions.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {option(item)}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
                <div className="flex flex-col gap-3">
                    {(['isActive', 'isSensitive'] as const).map((key) => (
                        <div key={key} className="flex items-start justify-between gap-3">
                            <div className="flex flex-col gap-0.5">
                                <Label htmlFor={`type-${key}`}>{t(`types.${key}`)}</Label>
                                <p className="text-caption text-muted-foreground">
                                    {t(`types.${key}Help`)}
                                </p>
                            </div>
                            <Switch
                                id={`type-${key}`}
                                checked={form.data[key]}
                                onCheckedChange={(value) => form.setData(key, value)}
                            />
                        </div>
                    ))}
                </div>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {t('types.save')}
                    </Button>
                </div>
            </form>
        </ResponsiveDialog>
    );
}
