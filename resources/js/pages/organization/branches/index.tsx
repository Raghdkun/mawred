import { Head, Link, router } from '@inertiajs/react';
import { Building2, ChevronLeft, MapPin, Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SearchBox } from '@/components/mw/search-box';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { BranchForm } from '@/features/organization/branch-form';
import { GeofenceMap } from '@/features/organization/geofence-map';
import { OrganizationTabs } from '@/features/organization/organization-tabs';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { countryName, weekdayName } from '@/lib/regions';
import { cn } from '@/lib/utils';
import branchRoutes from '@/routes/organization/branches';
import { type Paginated } from '@/types/pagination';

type Branch = App.Domain.Organization.Data.BranchData;
type CostCenter = App.Domain.Organization.Data.CatalogEntryData;

type Props = {
    branches: Paginated<Branch>;
    selected: Branch | null;
    filters: { search: string | null; status: 'active' | 'all' };
    options: { costCenters: CostCenter[] };
    can: { create: boolean; update: boolean; delete: boolean };
};

/** Organization → Branches (handoff 39): search-first paged list + detail with the geofence map. */
export default function BranchesIndex({ branches, selected, filters, options, can }: Props) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search ?? '');
    const [creating, setCreating] = useState(false);

    const reload = (params: Record<string, string>) =>
        router.get(
            branchRoutes.index.url(),
            { ...(search ? { search } : {}), status: filters.status, ...params },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={selected ? localized(selected.name, locale) : t('branches.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.create ? (
                        <Button onClick={() => setCreating(true)}>
                            <Plus aria-hidden />
                            {t('branches.new')}
                        </Button>
                    ) : null
                }
            />
            <OrganizationTabs />

            <div className="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)]">
                <section
                    aria-label={t('branches.listLabel')}
                    className={cn('flex flex-col gap-3', selected && 'hidden lg:flex')}
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <SearchBox
                            value={search}
                            onChange={setSearch}
                            onSubmit={() => reload({})}
                            label={t('branches.search')}
                            className="sm:max-w-none lg:max-w-none"
                        />
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            value={filters.status}
                            onValueChange={(status) => status && reload({ status })}
                            aria-label={t('filters.status')}
                        >
                            <ToggleGroupItem value="active">{t('common:active')}</ToggleGroupItem>
                            <ToggleGroupItem value="all">{t('common:all')}</ToggleGroupItem>
                        </ToggleGroup>
                    </div>

                    {branches.data.length === 0 ? (
                        filters.search ? (
                            <PageState kind="no-results" title={t('branches.noResults')} />
                        ) : (
                            <PageState
                                kind="empty"
                                illustration="empty-search"
                                title={t('branches.empty')}
                                description={t('branches.emptyHelp')}
                            />
                        )
                    ) : (
                        <ul className="flex flex-col overflow-hidden rounded-lg border border-border bg-card">
                            {branches.data.map((branch) => {
                                const active = selected?.id === branch.id;

                                return (
                                    <li
                                        key={branch.id}
                                        className="border-b border-border last:border-b-0"
                                    >
                                        <Link
                                            href={branchRoutes.show.url(branch.id, {
                                                query: {
                                                    ...(filters.search
                                                        ? { search: filters.search }
                                                        : {}),
                                                    status: filters.status,
                                                    page: String(branches.current_page),
                                                },
                                            })}
                                            preserveScroll
                                            aria-current={active ? 'true' : undefined}
                                            className={cn(
                                                'flex min-h-14 items-center gap-3 px-4 py-3 transition-colors duration-150 ease-soft hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                                active && 'bg-selected',
                                            )}
                                        >
                                            <Building2
                                                aria-hidden
                                                className="size-5 shrink-0 text-text-secondary"
                                            />
                                            <span className="flex min-w-0 flex-1 flex-col">
                                                <span className="truncate text-sm font-medium">
                                                    {localized(branch.name, locale)}
                                                </span>
                                                <span className="truncate text-caption text-text-secondary">
                                                    <bdi dir="ltr">{branch.code}</bdi> ·{' '}
                                                    {countryName(branch.countryCode, locale)}
                                                </span>
                                            </span>
                                            {branch.geofence ? (
                                                <MapPin
                                                    aria-label={t('branches.hasGeofence')}
                                                    className="size-4 text-text-secondary"
                                                />
                                            ) : null}
                                            {!branch.isActive ? (
                                                <Badge variant="outline">
                                                    {t('common:inactive')}
                                                </Badge>
                                            ) : null}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}

                    <Paginator
                        prevUrl={branches.prev_page_url}
                        nextUrl={branches.next_page_url}
                        summary={
                            branches.total > 0
                                ? t('pagination', {
                                      from: formatNumber(branches.from ?? 0, locale),
                                      to: formatNumber(branches.to ?? 0, locale),
                                      total: formatNumber(branches.total, locale),
                                  })
                                : undefined
                        }
                    />
                </section>

                <section
                    aria-label={t('branches.detailLabel')}
                    className={cn(!selected && 'hidden lg:block')}
                >
                    {selected ? (
                        <BranchDetail
                            branch={selected}
                            costCenters={options.costCenters}
                            can={can}
                        />
                    ) : (
                        <div className="hidden h-full min-h-64 items-center justify-center rounded-lg border border-dashed border-border p-8 text-center text-small text-muted-foreground lg:flex">
                            {t('branches.pick')}
                        </div>
                    )}
                </section>
            </div>

            <ResponsiveDialog
                open={creating}
                onOpenChange={setCreating}
                title={t('branches.new')}
                className="sm:max-w-3xl"
            >
                <BranchForm
                    branch={null}
                    action={branchRoutes.store.url()}
                    method="post"
                    costCenters={options.costCenters}
                    submitLabel={t('branches.create')}
                    onSuccess={() => setCreating(false)}
                />
            </ResponsiveDialog>
        </>
    );
}

function BranchDetail({
    branch,
    costCenters,
    can,
}: {
    branch: Branch;
    costCenters: CostCenter[];
    can: Props['can'];
}) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [editing, setEditing] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const costCenter = costCenters.find((center) => center.id === branch.costCenterId);
    const weekend = new Intl.ListFormat(locale, { type: 'conjunction' }).format(
        branch.weekendDays.map((day) => weekdayName(day, locale, 'long')),
    );

    const facts: Array<[string, React.ReactNode]> = [
        [
            t('fields.code'),
            <bdi key="code" dir="ltr">
                {branch.code}
            </bdi>,
        ],
        [t('fields.country'), countryName(branch.countryCode, locale)],
        [
            t('fields.timezone'),
            <bdi key="timezone" dir="ltr">
                {branch.timezone}
            </bdi>,
        ],
        [
            t('fields.currency'),
            <bdi key="defaultCurrency" dir="ltr">
                {branch.defaultCurrency}
            </bdi>,
        ],
        [t('fields.weekend'), weekend || t('branches.noWeekend')],
        [
            t('fields.hours'),
            <bdi key="hours" dir="ltr">{`${branch.workStartsAt}–${branch.workEndsAt}`}</bdi>,
        ],
        [t('fields.costCenter'), costCenter ? localized(costCenter.name, locale) : '—'],
        [t('fields.city'), branch.address?.city ?? '—'],
    ];

    return (
        <article className="flex flex-col gap-5 rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight sm:p-6">
            <Link
                href={branchRoutes.index.url()}
                className="inline-flex items-center gap-1.5 text-small text-link hover:underline lg:hidden"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('branches.back')}
            </Link>
            <header className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex flex-col gap-1">
                    <h2 className="text-h3 text-foreground">{localized(branch.name, locale)}</h2>
                    <p className="text-small text-muted-foreground">
                        {branch.isActive ? t('common:active') : t('common:inactive')}
                    </p>
                </div>
                <div className="flex gap-2">
                    {can.update ? (
                        <Button variant="outline" onClick={() => setEditing(true)}>
                            <Pencil aria-hidden />
                            {t('common:actions.edit')}
                        </Button>
                    ) : null}
                    {can.delete && branch.isActive ? (
                        <Button variant="ghost" onClick={() => setArchiving(true)}>
                            {t('common:actions.archive')}
                        </Button>
                    ) : null}
                </div>
            </header>

            <dl className="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                {facts.map(([label, value]) => (
                    <div key={label} className="flex flex-col gap-0.5">
                        <dt className="text-caption text-muted-foreground">{label}</dt>
                        <dd className="text-sm text-foreground">{value}</dd>
                    </div>
                ))}
            </dl>

            <div className="flex flex-col gap-2">
                <h3 className="text-body font-semibold text-foreground">
                    {t('sections.geofence')}
                </h3>
                {branch.geofence ? (
                    <>
                        <GeofenceMap key={branch.id} value={branch.geofence} readOnly />
                        <p className="text-caption text-muted-foreground">
                            {t('geofence.metres', { count: branch.geofence.radiusM })}
                        </p>
                    </>
                ) : (
                    <p className="text-small text-muted-foreground">{t('geofence.none')}</p>
                )}
            </div>

            <ResponsiveDialog
                open={editing}
                onOpenChange={setEditing}
                title={t('branches.edit')}
                className="sm:max-w-3xl"
            >
                <BranchForm
                    key={branch.id}
                    branch={branch}
                    action={branchRoutes.update.url(branch.id)}
                    method="put"
                    costCenters={costCenters}
                    submitLabel={t('common:actions.saveChanges')}
                    onSuccess={() => setEditing(false)}
                />
            </ResponsiveDialog>

            <AlertDialog open={archiving} onOpenChange={setArchiving}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {t('branches.archiveTitle', { name: localized(branch.name, locale) })}
                        </AlertDialogTitle>
                        <AlertDialogDescription>{t('branches.archiveHelp')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('common:actions.cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() => router.delete(branchRoutes.destroy.url(branch.id))}
                        >
                            {t('common:actions.archive')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </article>
    );
}
