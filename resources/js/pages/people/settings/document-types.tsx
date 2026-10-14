import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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
import { PeopleSettingsTabs } from '@/features/people/settings-tabs';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import people from '@/routes/people';

type DocumentType = {
    id: string;
    code: string;
    name: Record<string, string>;
    requiresExpiry: boolean;
    reminderDays: number[];
    isActive: boolean;
};

/** People settings → Document types, with the expiry reminder thresholds. */
export default function DocumentTypesSettings({ types }: { types: DocumentType[] }) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<DocumentType | 'new' | null>(null);

    return (
        <>
            <Head title={t('settings.documentTypes.title')} />
            <PageHeader
                title={t('settings.title')}
                description={t('settings.description')}
                actions={
                    <Button onClick={() => setEditing('new')}>
                        <Plus aria-hidden />
                        {t('settings.documentTypes.new')}
                    </Button>
                }
            />
            <PeopleSettingsTabs />
            {types.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-inbox"
                    title={t('settings.documentTypes.empty')}
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
                            {type.requiresExpiry ? (
                                <span className="text-caption text-text-secondary">
                                    {t('settings.documentTypes.remindersAt', {
                                        days: type.reminderDays.join(locale === 'ar' ? '، ' : ', '),
                                    })}
                                </span>
                            ) : null}
                            {!type.isActive ? (
                                <Badge variant="outline">{t('common:inactive')}</Badge>
                            ) : null}
                            <Button
                                variant="ghost"
                                size="icon-sm"
                                onClick={() => setEditing(type)}
                                aria-label={`${t('common:actions.edit')}: ${localized(type.name, locale)}`}
                            >
                                <Pencil aria-hidden />
                            </Button>
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

function TypeDialog({ type, onClose }: { type: DocumentType | null; onClose: () => void }) {
    const { t } = useTranslation('people');
    const form = useForm({
        code: type?.code ?? '',
        name: { en: type?.name.en ?? '', ar: type?.name.ar ?? '' },
        requiresExpiry: type?.requiresExpiry ?? false,
        reminderDays: (type?.reminderDays ?? [30, 7]).join(', '),
        isActive: type?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            reminderDays: data.reminderDays
                .split(/[,،\s]+/)
                .map((value) => Number(value))
                .filter((value) => Number.isInteger(value) && value >= 0),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (type) {
            form.put(people.settings.documentTypes.update.url(type.id), options);
        } else {
            form.post(people.settings.documentTypes.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={type ? t('settings.documentTypes.edit') : t('settings.documentTypes.new')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="document-type-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form
                id="document-type-form"
                onSubmit={submit}
                className="flex flex-col gap-4"
                noValidate
            >
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field
                    id="type-code"
                    label={t('settings.documentTypes.code')}
                    error={errors.code}
                    required
                >
                    <Input
                        id="type-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="type-name"
                    label={t('settings.documentTypes.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <SwitchField
                    id="settings-documentTypes-requiresExpiry"
                    label={t('settings.documentTypes.requiresExpiry')}
                    checked={form.data.requiresExpiry}
                    onCheckedChange={(checked) => form.setData('requiresExpiry', checked)}
                />
                <Field
                    id="type-reminders"
                    label={t('settings.documentTypes.reminders')}
                    error={errors.reminderDays}
                    hint={t('settings.documentTypes.remindersHint')}
                >
                    <Input
                        id="type-reminders"
                        dir="ltr"
                        value={form.data.reminderDays}
                        onChange={(event) => form.setData('reminderDays', event.target.value)}
                    />
                </Field>
                <SwitchField
                    id="common-active"
                    label={t('common:active')}
                    checked={form.data.isActive}
                    onCheckedChange={(checked) => form.setData('isActive', checked)}
                />
            </form>
        </ResponsiveDialog>
    );
}
