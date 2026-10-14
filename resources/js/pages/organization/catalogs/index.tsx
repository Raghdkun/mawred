import { Head, Link, router, useForm } from '@inertiajs/react';
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
import { OrganizationTabs } from '@/features/organization/organization-tabs';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import catalogRoutes from '@/routes/organization/catalogs';

type Entry = App.Domain.Organization.Data.CatalogEntryData;
type Kind = 'employment-types' | 'grades' | 'cost-centers';

type Props = {
    kind: Kind;
    kinds: Kind[];
    entries: Entry[];
    can: { create: boolean; update: boolean; delete: boolean };
};

/** Organization → Types & grades (handoff 39): employment types, grades and cost centres. */
export default function CatalogsIndex({ kind, kinds, entries, can }: Props) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Entry | 'new' | null>(null);

    return (
        <>
            <Head title={t(`catalogs.${kind}.title`)} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            {t(`catalogs.${kind}.new`)}
                        </Button>
                    ) : null
                }
            />
            <OrganizationTabs />

            <nav aria-label={t('catalogs.label')} className="mb-4 flex flex-wrap gap-2">
                {kinds.map((candidate) => (
                    <Link
                        key={candidate}
                        href={catalogRoutes.index.url(candidate)}
                        aria-current={candidate === kind ? 'page' : undefined}
                        className={cn(
                            'inline-flex h-9 items-center rounded-full border border-border px-4 text-sm transition-colors duration-150 ease-soft hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            candidate === kind &&
                                'border-primary bg-primary text-primary-foreground hover:bg-primary',
                        )}
                    >
                        {t(`catalogs.${candidate}.title`)}
                    </Link>
                ))}
            </nav>

            {entries.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-search"
                    title={t(`catalogs.${kind}.empty`)}
                />
            ) : (
                <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                    {entries.map((entry) => (
                        <li
                            key={entry.id}
                            className="flex min-h-14 items-center gap-4 border-b border-border px-4 py-3 last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm font-medium">
                                    {localized(entry.name, locale)}
                                </span>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {entry.code}
                                </bdi>
                            </span>
                            {kind === 'grades' ? (
                                <span className="text-small text-text-secondary">
                                    {t('catalogs.rankValue', { rank: entry.rank })}
                                </span>
                            ) : null}
                            {!entry.isActive ? (
                                <Badge variant="outline">{t('common:inactive')}</Badge>
                            ) : null}
                            {can.update ? (
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    onClick={() => setEditing(entry)}
                                    aria-label={`${t('common:actions.edit')}: ${localized(entry.name, locale)}`}
                                >
                                    <Pencil aria-hidden />
                                </Button>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}

            {editing ? (
                <EntryDialog
                    kind={kind}
                    entry={editing === 'new' ? null : editing}
                    canDelete={can.delete}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function EntryDialog({
    kind,
    entry,
    canDelete,
    onClose,
}: {
    kind: Kind;
    entry: Entry | null;
    canDelete: boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation('organization');
    const form = useForm({
        code: entry?.code ?? '',
        name: { en: entry?.name.en ?? '', ar: entry?.name.ar ?? '' },
        rank: entry?.rank ?? 0,
        isActive: entry?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (entry) {
            form.put(catalogRoutes.update.url([kind, entry.id]), options);
        } else {
            form.post(catalogRoutes.store.url(kind), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={entry ? t(`catalogs.${kind}.edit`) : t(`catalogs.${kind}.new`)}
            footer={
                <>
                    {entry && canDelete && entry.isActive ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() =>
                                router.delete(catalogRoutes.destroy.url([kind, entry.id]), {
                                    preserveScroll: true,
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
                    <Button type="submit" form="catalog-form" loading={form.processing}>
                        {entry ? t('common:actions.saveChanges') : t('common:actions.create')}
                    </Button>
                </>
            }
        >
            <form id="catalog-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="catalog-code" label={t('fields.code')} error={errors.code} required>
                    <Input
                        id="catalog-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="catalog-name"
                    label={t('fields.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                {kind === 'grades' ? (
                    <Field
                        id="catalog-rank"
                        label={t('fields.rank')}
                        error={errors.rank}
                        hint={t('hints.rank')}
                    >
                        <Input
                            id="catalog-rank"
                            type="number"
                            min={0}
                            max={1000}
                            dir="ltr"
                            className="max-w-32"
                            value={form.data.rank}
                            onChange={(event) => form.setData('rank', Number(event.target.value))}
                        />
                    </Field>
                ) : null}
                <SwitchField
                    id="fields-active"
                    label={t('fields.active')}
                    checked={form.data.isActive}
                    onCheckedChange={(checked) => form.setData('isActive', checked)}
                />
            </form>
        </ResponsiveDialog>
    );
}
