import { Link, router } from '@inertiajs/react';
import {
    columnVisibilityFeature,
    createColumnHelper,
    rowSelectionFeature,
    tableFeatures,
    useTable,
    type ColumnVisibilityState,
    type RowSelectionState,
} from '@tanstack/react-table';
import { ArrowDown, ArrowUp, Columns3, Rows3 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized, personName } from '@/lib/localized';
import { cn } from '@/lib/utils';
import people from '@/routes/people';
import { EmployeeStatusBadge } from './employee-status-badge';
import { PersonAvatar } from './person-avatar';

type Employee = App.Domain.People.Data.EmployeeSummaryData;

const features = tableFeatures({ rowSelectionFeature, columnVisibilityFeature });
const helper = createColumnHelper<typeof features, Employee>();
const OPTIONAL = [
    'position',
    'department',
    'branch',
    'manager',
    'status',
    'hireDate',
    'contact',
] as const;
const PREFS = 'mawred.people.table';

type Prefs = { visibility: ColumnVisibilityState; dense: boolean };

function readPrefs(): Prefs {
    try {
        const parsed = JSON.parse(window.localStorage.getItem(PREFS) ?? 'null') as Prefs | null;
        return parsed ?? { visibility: { contact: false }, dense: false };
    } catch {
        return { visibility: { contact: false }, dense: false };
    }
}

/**
 * Directory table (screen 7, desktop/tablet): selection for bulk actions, a column picker and
 * density, with sorting done by the server. Column and density choices are kept per browser.
 */
export function DirectoryTable({
    employees,
    sort,
    direction,
    onSort,
    selection,
    onSelectionChange,
    selectable,
}: {
    employees: Employee[];
    sort: string;
    direction: 'asc' | 'desc';
    onSort: (sort: string) => void;
    selection: RowSelectionState;
    onSelectionChange: (selection: RowSelectionState) => void;
    selectable: boolean;
}) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [prefs, setPrefs] = useState<Prefs>(() =>
        typeof window === 'undefined' ? { visibility: {}, dense: false } : readPrefs(),
    );

    useEffect(() => {
        try {
            window.localStorage.setItem(PREFS, JSON.stringify(prefs));
        } catch {
            // Preferences are a convenience only.
        }
    }, [prefs]);

    const sortHeader = (key: string, label: string) => (
        <button
            type="button"
            onClick={() => onSort(key)}
            className="inline-flex items-center gap-1 font-medium hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            aria-sort={
                sort === key ? (direction === 'asc' ? 'ascending' : 'descending') : undefined
            }
        >
            {label}
            {sort === key ? (
                direction === 'asc' ? (
                    <ArrowUp aria-hidden className="size-3.5" />
                ) : (
                    <ArrowDown aria-hidden className="size-3.5" />
                )
            ) : null}
        </button>
    );

    const columns = useMemo(
        () =>
            helper.columns([
                helper.display({
                    id: 'select',
                    header: ({ table }) =>
                        selectable ? (
                            <Checkbox
                                checked={
                                    table.getIsAllRowsSelected()
                                        ? true
                                        : table.getIsSomeRowsSelected()
                                          ? 'indeterminate'
                                          : false
                                }
                                onCheckedChange={(checked) =>
                                    table.toggleAllRowsSelected(checked === true)
                                }
                                aria-label={t('directory.selectAll')}
                            />
                        ) : null,
                    cell: ({ row }) =>
                        selectable ? (
                            <Checkbox
                                checked={row.getIsSelected()}
                                onCheckedChange={(checked) => row.toggleSelected(checked === true)}
                                aria-label={t('directory.select', {
                                    name: personName(row.original, locale),
                                })}
                            />
                        ) : null,
                }),
                helper.display({
                    id: 'person',
                    header: () => sortHeader('name', t('columns.person')),
                    cell: ({ row }) => (
                        <Link
                            href={people.show.url(row.original.id)}
                            className="flex min-w-0 items-center gap-3 rounded-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <PersonAvatar
                                name={personName(row.original, locale)}
                                className={prefs.dense ? 'size-7' : undefined}
                            />
                            <span className="flex min-w-0 flex-col">
                                <span className="truncate font-medium text-foreground hover:underline">
                                    {personName(row.original, locale)}
                                </span>
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start truncate text-caption text-muted-foreground"
                                >
                                    {row.original.number}
                                </bdi>
                            </span>
                        </Link>
                    ),
                }),
                helper.display({
                    id: 'position',
                    header: t('columns.position'),
                    cell: ({ row }) =>
                        row.original.position ? localized(row.original.position.name, locale) : '—',
                }),
                helper.display({
                    id: 'department',
                    header: t('columns.department'),
                    cell: ({ row }) =>
                        row.original.department
                            ? localized(row.original.department.name, locale)
                            : '—',
                }),
                helper.display({
                    id: 'branch',
                    header: t('columns.branch'),
                    cell: ({ row }) =>
                        row.original.branch ? localized(row.original.branch.name, locale) : '—',
                }),
                helper.display({
                    id: 'manager',
                    header: t('columns.manager'),
                    cell: ({ row }) =>
                        row.original.manager ? (
                            <Link
                                href={people.show.url(row.original.manager.id)}
                                className="text-link hover:underline"
                            >
                                {personName(row.original.manager, locale)}
                            </Link>
                        ) : (
                            '—'
                        ),
                }),
                helper.display({
                    id: 'status',
                    header: t('columns.status'),
                    cell: ({ row }) => <EmployeeStatusBadge status={row.original.status} />,
                }),
                helper.display({
                    id: 'hireDate',
                    header: () => sortHeader('hire_date', t('columns.hireDate')),
                    cell: ({ row }) =>
                        formatDate(row.original.hireDate, locale, { dateStyle: 'medium' }),
                }),
                helper.display({
                    id: 'contact',
                    header: t('columns.contact'),
                    cell: ({ row }) => (
                        <span className="flex flex-col">
                            {row.original.workEmail ? (
                                <bdi dir="ltr">{row.original.workEmail}</bdi>
                            ) : null}
                            {row.original.workPhone ? (
                                <bdi
                                    dir="ltr"
                                    className="max-w-full self-start text-caption text-muted-foreground"
                                >
                                    {row.original.workPhone}
                                </bdi>
                            ) : null}
                        </span>
                    ),
                }),
            ]),
        // sortHeader/t/locale/prefs.dense change the rendered headers and cells.
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [t, locale, sort, direction, selectable, prefs.dense],
    );

    const table = useTable({
        features,
        columns,
        data: employees,
        getRowId: (row) => row.id,
        state: { rowSelection: selection, columnVisibility: prefs.visibility },
        onRowSelectionChange: (updater) =>
            onSelectionChange(typeof updater === 'function' ? updater(selection) : updater),
        onColumnVisibilityChange: (updater) =>
            setPrefs((current) => ({
                ...current,
                visibility: typeof updater === 'function' ? updater(current.visibility) : updater,
            })),
    });

    return (
        <div className="flex flex-col gap-2">
            <div className="flex justify-end gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <Columns3 aria-hidden />
                            {t('directory.columns')}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>{t('directory.columns')}</DropdownMenuLabel>
                        {OPTIONAL.map((id) => (
                            <DropdownMenuCheckboxItem
                                key={id}
                                checked={table.getColumn(id)?.getIsVisible() ?? true}
                                onCheckedChange={(checked) =>
                                    table.getColumn(id)?.toggleVisibility(checked === true)
                                }
                                onSelect={(event) => event.preventDefault()}
                            >
                                {t(`columns.${id}`)}
                            </DropdownMenuCheckboxItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-pressed={prefs.dense}
                    onClick={() => setPrefs((current) => ({ ...current, dense: !current.dense }))}
                >
                    <Rows3 aria-hidden />
                    {prefs.dense ? t('directory.comfortable') : t('directory.compact')}
                </Button>
            </div>
            <div className="overflow-x-auto rounded-lg border border-border bg-card">
                <table className="w-full text-sm">
                    <thead className="bg-surface-sunken text-start text-caption text-text-secondary">
                        {table.getHeaderGroups().map((group) => (
                            <tr key={group.id}>
                                {group.headers.map((header) => (
                                    <th
                                        key={header.id}
                                        scope="col"
                                        className={cn(
                                            'px-3 py-2.5 text-start font-medium',
                                            header.column.id === 'select' && 'w-10',
                                        )}
                                    >
                                        {header.isPlaceholder ? null : (
                                            <table.FlexRender header={header} />
                                        )}
                                    </th>
                                ))}
                            </tr>
                        ))}
                    </thead>
                    <tbody>
                        {table.getRowModel().rows.map((row) => (
                            <tr
                                key={row.id}
                                aria-selected={row.getIsSelected() || undefined}
                                className={cn(
                                    'border-t border-border transition-colors duration-150 hover:bg-muted/50',
                                    row.getIsSelected() && 'bg-selected',
                                )}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <td
                                        key={cell.id}
                                        className={cn(
                                            'px-3 align-middle text-text-secondary',
                                            prefs.dense ? 'py-1.5' : 'py-3',
                                        )}
                                    >
                                        <table.FlexRender cell={cell} />
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/** Mobile and grid view: one card per person (screen 7: tables become cards). */
export function EmployeeCard({ employee }: { employee: Employee }) {
    const { locale } = useLocale();
    const name = personName(employee, locale);

    return (
        <Link
            href={people.show.url(employee.id)}
            className="flex items-center gap-3 rounded-lg bg-card p-4 shadow-sm inset-shadow-highlight transition-shadow duration-150 ease-soft hover:shadow-md focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <PersonAvatar name={name} className="size-11" />
            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                <span className="truncate text-sm font-medium text-foreground">{name}</span>
                <span className="truncate text-caption text-text-secondary">
                    {[
                        employee.position ? localized(employee.position.name, locale) : null,
                        employee.branch ? localized(employee.branch.name, locale) : null,
                    ]
                        .filter(Boolean)
                        .join(' · ')}
                </span>
                <bdi dir="ltr" className="max-w-full self-start text-caption text-muted-foreground">
                    {employee.number}
                </bdi>
            </span>
            {employee.status !== 'active' ? <EmployeeStatusBadge status={employee.status} /> : null}
        </Link>
    );
}

export function useClearSelectionOnVisit(clear: () => void) {
    useEffect(() => router.on('navigate', clear), [clear]);
}
