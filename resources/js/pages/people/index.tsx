import { Head, Link, router } from '@inertiajs/react';
import { type RowSelectionState } from '@tanstack/react-table';
import { Mail, Plus, Upload } from 'lucide-react';
import { useCallback, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { BranchPicker, DepartmentPicker } from '@/features/organization/pickers';
import { AddEmployeeDialog } from '@/features/people/add-employee-dialog';
import {
    DirectoryTable,
    EmployeeCard,
    useClearSelectionOnVisit,
} from '@/features/people/directory-table';
import { OrgChart } from '@/features/people/org-chart';
import { SavedViews } from '@/features/people/saved-views';
import { useIsMobile } from '@/hooks/use-mobile';
import { cn } from '@/lib/utils';
import people from '@/routes/people';

type Employee = App.Domain.People.Data.EmployeeSummaryData;
type Catalog = App.Domain.Organization.Data.CatalogEntryData;
type View = 'table' | 'grid' | 'org';

type Props = {
    employees: { data: Employee[]; nextCursor: string | null; prevCursor: string | null };
    filters: {
        search: string | null;
        branch: PickerOption | null;
        department: PickerOption | null;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
        view: View;
    };
    options: { grades: Catalog[]; employmentTypes: Catalog[] };
    can: { create: boolean; update: boolean; import: boolean };
};

const STATUSES = ['current', 'active', 'pending', 'suspended', 'terminated', 'all'];

/** People directory (handoff 7): Table / Grid / Org chart, server-side search, filters and paging. */
export default function PeopleIndex({ employees, filters, options, can }: Props) {
    const { t } = useTranslation('people');
    const isMobile = useIsMobile();
    const [search, setSearch] = useState(filters.search ?? '');
    const [adding, setAdding] = useState(false);
    const [selection, setSelection] = useState<RowSelectionState>({});
    const selected = Object.keys(selection).filter((id) => selection[id]);
    useClearSelectionOnVisit(useCallback(() => setSelection({}), []));

    const query = (overrides: Record<string, string | null | undefined>) =>
        Object.fromEntries(
            Object.entries({
                search: search || null,
                branch: filters.branch?.id ?? null,
                department: filters.department?.id ?? null,
                status: filters.status === 'current' ? null : filters.status,
                sort: filters.sort === 'name' ? null : filters.sort,
                direction: filters.direction === 'asc' ? null : filters.direction,
                view: filters.view === 'table' ? null : filters.view,
                ...overrides,
            }).filter(([, value]) => value !== null && value !== undefined && value !== ''),
        );

    const reload = (overrides: Record<string, string | null | undefined>) =>
        router.get(people.index.url(), query(overrides), { preserveState: true, replace: true });

    const cursorUrl = (cursor: string | null) =>
        cursor ? people.index.url({ query: { ...query({}), cursor } }) : null;
    const view: View = filters.view;
    const tabs: View[] = ['table', 'grid', 'org'];

    return (
        <>
            <Head title={t('directory.pageTitle')} />
            <PageHeader
                title={t('directory.title')}
                description={t('directory.description')}
                actions={
                    <>
                        {can.import ? (
                            <Button variant="outline" asChild className="hidden md:inline-flex">
                                <Link href={people.imports.create.url()}>
                                    <Upload aria-hidden />
                                    {t('directory.import')}
                                </Link>
                            </Button>
                        ) : null}
                        {can.create ? (
                            <Button onClick={() => setAdding(true)}>
                                <Plus aria-hidden />
                                {t('directory.add')}
                            </Button>
                        ) : null}
                    </>
                }
            />

            <div
                role="tablist"
                aria-label={t('directory.views')}
                className="mb-4 inline-flex gap-1 rounded-full bg-surface-sunken p-1"
            >
                {tabs.map((tab) => (
                    <button
                        key={tab}
                        type="button"
                        role="tab"
                        aria-selected={view === tab}
                        onClick={() => reload({ view: tab === 'table' ? null : tab })}
                        className={cn(
                            'h-9 rounded-full px-4 text-sm transition-colors duration-150 ease-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            view === tab
                                ? 'bg-card font-medium text-foreground shadow-sm'
                                : 'text-text-secondary hover:text-foreground',
                        )}
                    >
                        {t(`directory.tabs.${tab}`)}
                    </button>
                ))}
            </div>

            {view === 'org' ? (
                <OrgChart />
            ) : (
                <>
                    <div className="mb-4 flex flex-wrap items-center gap-2">
                        <SearchBox
                            value={search}
                            onChange={setSearch}
                            onSubmit={() => reload({})}
                            label={t('directory.search')}
                        />
                        <BranchPicker
                            value={filters.branch}
                            onChange={(branch) => reload({ branch: branch?.id ?? null })}
                            label={t('fields.branch')}
                            className="w-full sm:w-52"
                        />
                        <DepartmentPicker
                            value={filters.department}
                            onChange={(department) =>
                                reload({ department: department?.id ?? null })
                            }
                            label={t('fields.department')}
                            className="w-full sm:w-52"
                        />
                        <Select
                            value={filters.status}
                            onValueChange={(status) =>
                                reload({ status: status === 'current' ? null : status })
                            }
                        >
                            <SelectTrigger
                                aria-label={t('fields.status')}
                                className="w-full sm:w-44"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {STATUSES.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {t(`filters.status.${status}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="ms-auto">
                            <SavedViews path={people.index.url()} />
                        </div>
                    </div>

                    {selected.length > 0 ? (
                        <div
                            role="region"
                            aria-label={t('directory.bulk')}
                            className="sticky top-16 z-10 mb-3 flex flex-wrap items-center gap-3 rounded-lg bg-primary px-4 py-2 text-primary-foreground shadow-md"
                        >
                            <span className="text-sm font-medium">
                                {t('directory.selected', { count: selected.length })}
                            </span>
                            {can.update ? (
                                <Button
                                    size="sm"
                                    variant="secondary"
                                    onClick={() =>
                                        router.post(
                                            people.invitations.store.url(),
                                            { employees: selected },
                                            {
                                                preserveScroll: true,
                                                onSuccess: () => setSelection({}),
                                            },
                                        )
                                    }
                                >
                                    <Mail aria-hidden />
                                    {t('directory.inviteSelected')}
                                </Button>
                            ) : null}
                            <Button
                                size="sm"
                                variant="ghost"
                                className="ms-auto text-primary-foreground hover:bg-primary-foreground/10"
                                onClick={() => setSelection({})}
                            >
                                {t('common:actions.clear')}
                            </Button>
                        </div>
                    ) : null}

                    {employees.data.length === 0 ? (
                        filters.search ||
                        filters.branch ||
                        filters.department ||
                        filters.status !== 'current' ? (
                            <PageState
                                kind="no-results"
                                title={t('directory.noResults')}
                                description={t('directory.noResultsHelp')}
                            />
                        ) : (
                            <PageState
                                kind="empty"
                                illustration="empty-employees"
                                title={t('directory.empty')}
                                description={t('directory.emptyHelp')}
                                action={
                                    can.create ? (
                                        <Button onClick={() => setAdding(true)}>
                                            {t('directory.add')}
                                        </Button>
                                    ) : undefined
                                }
                            />
                        )
                    ) : view === 'grid' || isMobile ? (
                        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                            {employees.data.map((employee) => (
                                <li key={employee.id}>
                                    <EmployeeCard employee={employee} />
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <DirectoryTable
                            employees={employees.data}
                            sort={filters.sort}
                            direction={filters.direction}
                            onSort={(sort) =>
                                reload({
                                    sort: sort === 'name' ? null : sort,
                                    direction:
                                        filters.sort === sort && filters.direction === 'asc'
                                            ? 'desc'
                                            : null,
                                })
                            }
                            selection={selection}
                            onSelectionChange={setSelection}
                            selectable={can.update}
                        />
                    )}

                    <Paginator
                        prevUrl={cursorUrl(employees.prevCursor)}
                        nextUrl={cursorUrl(employees.nextCursor)}
                    />
                </>
            )}

            {can.create ? (
                <AddEmployeeDialog
                    open={adding}
                    onOpenChange={setAdding}
                    employmentTypes={options.employmentTypes}
                    grades={options.grades}
                />
            ) : null}
        </>
    );
}
