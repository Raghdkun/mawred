import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SearchBox } from '@/components/mw/search-box';
import { Badge } from '@/components/ui/badge';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { CatalogSelect } from '@/features/organization/catalog-select';
import { OrganizationTabs } from '@/features/organization/organization-tabs';
import { DepartmentPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import positionRoutes from '@/routes/organization/positions';
import { type Paginated } from '@/types/pagination';

type Position = App.Domain.Organization.Data.PositionData;
type Grade = App.Domain.Organization.Data.CatalogEntryData;

type Props = {
    positions: Paginated<Position>;
    filters: { search: string | null; department: string | null; status: 'active' | 'all' };
    options: { grades: Grade[] };
    can: { create: boolean; update: boolean; delete: boolean };
};

/** Organization → Positions (handoff 39): search-first, paged; cards on phones. */
export default function PositionsIndex({ positions, filters, options, can }: Props) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const [department, setDepartment] = useState<PickerOption | null>(null);
    const [editing, setEditing] = useState<Position | 'new' | null>(null);

    const reload = (params: Record<string, string | null>) =>
        router.get(
            positionRoutes.index.url(),
            Object.fromEntries(
                Object.entries({
                    search: search || null,
                    department: department?.id ?? filters.department,
                    status: filters.status,
                    ...params,
                }).filter(([, value]) => value !== null && value !== ''),
            ),
            { preserveState: true, replace: true },
        );

    const gradeName = (id: string | null) => {
        const grade = options.grades.find((candidate) => candidate.id === id);
        return grade ? localized(grade.name, locale) : '—';
    };

    return (
        <>
            <Head title={t('positions.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            {t('positions.new')}
                        </Button>
                    ) : null
                }
            />
            <OrganizationTabs />

            <div className="mb-4 flex flex-wrap items-center gap-2">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => reload({})}
                    label={t('positions.search')}
                />
                <DepartmentPicker
                    value={department}
                    onChange={(option) => {
                        setDepartment(option);
                        reload({ department: option?.id ?? null });
                    }}
                    label={t('filters.department')}
                    className="w-full sm:w-64"
                />
            </div>

            {positions.data.length === 0 ? (
                filters.search || filters.department ? (
                    <PageState kind="no-results" title={t('positions.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-search"
                        title={t('positions.empty')}
                        description={t('positions.emptyHelp')}
                    />
                )
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {positions.data.map((position) => (
                        <li
                            key={position.id}
                            className="flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                        >
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-sm font-medium">
                                    {localized(position.title, locale)}
                                </span>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {position.code}
                                </bdi>
                            </span>
                            <span className="text-small text-text-secondary md:w-56">
                                {position.departmentName
                                    ? localized(position.departmentName, locale)
                                    : t('positions.anyDepartment')}
                            </span>
                            <span className="text-small text-text-secondary md:w-32">
                                {gradeName(position.gradeId)}
                            </span>
                            <span className="flex items-center gap-2 md:w-28 md:justify-end">
                                {!position.isActive ? (
                                    <Badge variant="outline">{t('common:inactive')}</Badge>
                                ) : null}
                                {can.update ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() => setEditing(position)}
                                        aria-label={`${t('common:actions.edit')}: ${localized(position.title, locale)}`}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                ) : null}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            <Paginator
                prevUrl={positions.prev_page_url}
                nextUrl={positions.next_page_url}
                summary={
                    positions.total > 0
                        ? t('pagination', {
                              from: formatNumber(positions.from ?? 0, locale),
                              to: formatNumber(positions.to ?? 0, locale),
                              total: formatNumber(positions.total, locale),
                          })
                        : undefined
                }
            />

            {editing ? (
                <PositionDialog
                    position={editing === 'new' ? null : editing}
                    grades={options.grades}
                    canDelete={can.delete}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function PositionDialog({
    position,
    grades,
    canDelete,
    onClose,
}: {
    position: Position | null;
    grades: Grade[];
    canDelete: boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [department, setDepartment] = useState<PickerOption | null>(
        position?.departmentId && position.departmentName
            ? { id: position.departmentId, label: localized(position.departmentName, locale) }
            : null,
    );
    const form = useForm({
        code: position?.code ?? '',
        title: { en: position?.title.en ?? '', ar: position?.title.ar ?? '' },
        departmentId: position?.departmentId ?? null,
        gradeId: position?.gradeId ?? null,
        isActive: position?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (position) {
            form.put(positionRoutes.update.url(position.id), options);
        } else {
            form.post(positionRoutes.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={position ? t('positions.edit') : t('positions.new')}
            footer={
                <>
                    {position && canDelete && position.isActive ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() =>
                                router.delete(positionRoutes.destroy.url(position.id), {
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
                    <Button type="submit" form="position-form" loading={form.processing}>
                        {position ? t('common:actions.saveChanges') : t('common:actions.create')}
                    </Button>
                </>
            }
        >
            <form id="position-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="position-code" label={t('fields.code')} error={errors.code} required>
                    <Input
                        id="position-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="position-title"
                    label={t('fields.title')}
                    value={form.data.title}
                    onChange={(title) => form.setData('title', title)}
                    errors={errors}
                    errorKey="title"
                />
                <Field
                    id="position-department"
                    label={t('fields.department')}
                    error={errors.departmentId}
                    hint={t('hints.positionDepartment')}
                >
                    <DepartmentPicker
                        value={department}
                        onChange={(option) => {
                            setDepartment(option);
                            form.setData('departmentId', option?.id ?? null);
                        }}
                        label={t('fields.department')}
                    />
                </Field>
                <Field id="position-grade" label={t('fields.grade')} error={errors.gradeId}>
                    <CatalogSelect
                        id="position-grade"
                        value={form.data.gradeId}
                        onChange={(value) => form.setData('gradeId', value)}
                        options={grades}
                    />
                </Field>
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
