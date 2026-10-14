import { Head, Link, router, useForm } from '@inertiajs/react';
import { FileUp, Plus, ShieldCheck } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SearchBox } from '@/components/mw/search-box';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { PoliciesTabs } from '@/features/policies/policies-tabs';
import { type PolicyAssignment, type PolicySet } from '@/features/policies/types';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import policies from '@/routes/policies';
import { type Paginated } from '@/types/pagination';

type Props = {
    sets: Paginated<PolicySet>;
    filters: { search: string | null };
    can: { create: boolean; update: boolean; delete: boolean; export: boolean };
};

/** Policy sets (handoff 16): name, preset badge, where each set applies, dates; cards on phones. */
export default function PolicySetsIndex({ sets, filters, can }: Props) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const [dialog, setDialog] = useState<'new' | 'import' | null>(null);

    const reload = () =>
        router.get(policies.index.url(), search ? { search } : {}, {
            preserveState: true,
            replace: true,
        });

    return (
        <>
            <Head title={t('sets.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.create ? (
                        <>
                            <Button variant="outline" onClick={() => setDialog('import')}>
                                <FileUp aria-hidden />
                                {t('sets.import')}
                            </Button>
                            <Button onClick={() => setDialog('new')}>
                                <Plus aria-hidden />
                                {t('sets.new')}
                            </Button>
                        </>
                    ) : null
                }
            />
            <PoliciesTabs />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={reload}
                    label={t('sets.search')}
                />
            </div>

            {sets.data.length === 0 ? (
                filters.search ? (
                    <PageState kind="no-results" title={t('sets.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-policies"
                        title={t('sets.empty')}
                        description={t('sets.emptyHelp')}
                    />
                )
            ) : (
                <div className="md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    <div
                        aria-hidden
                        className="hidden grid-cols-[minmax(0,2fr)_minmax(0,2fr)_8rem_9rem] gap-4 border-b border-border px-4 py-2.5 text-caption font-medium text-muted-foreground md:grid"
                    >
                        <span>{t('sets.name')}</span>
                        <span>{t('sets.usedBy')}</span>
                        <span>{t('sets.rulesColumn')}</span>
                        <span>{t('sets.lastChange')}</span>
                    </div>
                    <ul className="flex flex-col gap-2 md:gap-0">
                        {sets.data.map((set) => (
                            <li
                                key={set.id}
                                className="relative grid gap-3 rounded-lg bg-card p-4 shadow-sm transition-colors duration-150 ease-soft focus-within:bg-muted/60 hover:bg-muted/60 md:grid-cols-[minmax(0,2fr)_minmax(0,2fr)_8rem_9rem] md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                            >
                                <span className="flex min-w-0 flex-col gap-1">
                                    <Link
                                        href={policies.show.url(set.id)}
                                        className="truncate text-sm font-medium text-foreground after:absolute after:inset-0 focus-visible:outline-none"
                                    >
                                        {localized(set.name, locale)}
                                    </Link>
                                    {set.presetKey ? (
                                        <Badge variant="secondary" className="self-start">
                                            <ShieldCheck aria-hidden />
                                            {set.presetKey === 'neutral-default'
                                                ? t('sets.neutral')
                                                : t('sets.preset')}
                                        </Badge>
                                    ) : null}
                                </span>
                                <UsedBy assignments={set.assignments} />
                                <span className="text-small text-text-secondary tabular-nums">
                                    {t('sets.keys', { count: set.keysCount })}
                                </span>
                                <span className="text-small text-text-secondary">
                                    {set.lastChangeOn ? formatDate(set.lastChangeOn, locale) : '—'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}

            <Paginator
                prevUrl={sets.prev_page_url}
                nextUrl={sets.next_page_url}
                summary={
                    sets.total > 0
                        ? `${formatNumber(sets.from ?? 0, locale)}–${formatNumber(sets.to ?? 0, locale)} / ${formatNumber(sets.total, locale)}`
                        : undefined
                }
            />

            {dialog === 'new' ? <NewSetDialog onClose={() => setDialog(null)} /> : null}
            {dialog === 'import' ? <ImportDialog onClose={() => setDialog(null)} /> : null}
        </>
    );
}

function UsedBy({ assignments }: { assignments: PolicyAssignment[] }) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();

    if (assignments.length === 0) {
        return <span className="text-small text-muted-foreground">{t('sets.notAssigned')}</span>;
    }

    const shown = assignments.slice(0, 2);

    return (
        <span className="flex min-w-0 flex-wrap items-center gap-1.5 text-small text-text-secondary">
            {shown.map((assignment) => (
                <span
                    key={assignment.id}
                    className="inline-flex max-w-full items-center gap-1 rounded-full bg-muted px-2 py-0.5"
                >
                    <span className="truncate">
                        {assignment.targetName
                            ? `${t(`levels.${assignment.level}`)}: ${assignment.targetName}`
                            : t(`levels.${assignment.level}`)}
                    </span>
                    {assignment.effectiveFrom > new Date().toISOString().slice(0, 10) ? (
                        <span className="text-caption text-muted-foreground">
                            ·{' '}
                            {t('sets.from', {
                                date: formatDate(assignment.effectiveFrom, locale, {
                                    dateStyle: 'short',
                                }),
                            })}
                        </span>
                    ) : null}
                </span>
            ))}
            {assignments.length > shown.length ? (
                <span className="rounded-full border border-border px-2 py-0.5 tabular-nums">
                    +{formatNumber(assignments.length - shown.length, locale)}
                </span>
            ) : null}
        </span>
    );
}

function NewSetDialog({ onClose }: { onClose: () => void }) {
    const { t } = useTranslation('policies');
    const form = useForm({ name: { en: '', ar: '' }, description: { en: '', ar: '' } });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(policies.store.url());
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('sets.new')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="new-set-form" loading={form.processing}>
                        {t('common:actions.create')}
                    </Button>
                </>
            }
        >
            <form id="new-set-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <BilingualInput
                    id="set-name"
                    label={t('sets.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <BilingualInput
                    id="set-description"
                    label={t('sets.descriptionLabel')}
                    value={form.data.description}
                    onChange={(description) => form.setData('description', description)}
                    errors={errors}
                    errorKey="description"
                />
            </form>
        </ResponsiveDialog>
    );
}

function ImportDialog({ onClose }: { onClose: () => void }) {
    const { t } = useTranslation('policies');
    const form = useForm<{ file: File | null; effectiveFrom: string }>({
        file: null,
        effectiveFrom: new Date().toISOString().slice(0, 10),
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(policies.import.url(), { forceFormData: true });
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('sets.importTitle')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        form="import-preset-form"
                        loading={form.processing}
                        disabled={!form.data.file}
                    >
                        {t('common:actions.upload')}
                    </Button>
                </>
            }
        >
            <form
                id="import-preset-form"
                onSubmit={submit}
                className="flex flex-col gap-4"
                noValidate
            >
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="preset-file" label={t('sets.importFile')} error={errors.file} required>
                    <Input
                        id="preset-file"
                        type="file"
                        accept="application/json,.json"
                        onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                    />
                </Field>
                <Field
                    id="preset-from"
                    label={t('sets.importFrom')}
                    error={errors.effectiveFrom}
                    required
                >
                    <Input
                        id="preset-from"
                        type="date"
                        dir="ltr"
                        className="w-44"
                        value={form.data.effectiveFrom}
                        onChange={(event) => form.setData('effectiveFrom', event.target.value)}
                    />
                </Field>
            </form>
        </ResponsiveDialog>
    );
}
