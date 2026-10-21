import { Head, router, useForm } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    GripVertical,
    MoreHorizontal,
    Network,
    Plus,
} from 'lucide-react';
import { type DragEvent, type FormEvent, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SearchBox } from '@/components/mw/search-box';
import { Badge } from '@/components/ui/badge';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CatalogSelect } from '@/features/organization/catalog-select';
import { OrganizationTabs } from '@/features/organization/organization-tabs';
import { ancestorIds, buildTree, subtreeIds, type TreeNode } from '@/features/organization/tree';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import departmentRoutes from '@/routes/organization/departments';

type Department = App.Domain.Organization.Data.DepartmentData;
type CostCenter = App.Domain.Organization.Data.CatalogEntryData;

type Props = {
    departments: Department[];
    options: { costCenters: CostCenter[] };
    can: { create: boolean; update: boolean; delete: boolean };
};

const ROOT = '__root';

/**
 * Organization → Departments (handoff 39): the unlimited-depth tree. Drag a department onto
 * another to move it (or onto the top zone to make it top-level); "Move to…" does the same from
 * the keyboard. Indentation grows toward the reading end.
 */
export default function DepartmentsIndex({ departments, options, can }: Props) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const focus =
        typeof window === 'undefined'
            ? null
            : new URLSearchParams(window.location.search).get('focus');
    const [expanded, setExpanded] = useState<Set<string>>(
        () =>
            new Set(
                focus
                    ? ancestorIds(departments, focus)
                    : departments.filter((d) => d.depth === 0).map((d) => d.id),
            ),
    );
    const [search, setSearch] = useState('');
    const [editing, setEditing] = useState<Department | 'new' | null>(null);
    const [moving, setMoving] = useState<Department | null>(null);
    const [dragged, setDragged] = useState<string | null>(null);
    const [dropTarget, setDropTarget] = useState<string | null>(null);

    useEffect(() => {
        if (focus) {
            document.getElementById(`department-${focus}`)?.scrollIntoView({ block: 'center' });
        }
    }, [focus]);

    const needle = search.trim().toLocaleLowerCase(locale);
    const visible = useMemo(() => {
        if (!needle) {
            return departments;
        }

        const matches = departments.filter(
            (d) =>
                d.code.toLocaleLowerCase(locale).includes(needle) ||
                Object.values(d.name).some((name) =>
                    name.toLocaleLowerCase(locale).includes(needle),
                ),
        );
        const keep = new Set(matches.flatMap((d) => [d.id, ...ancestorIds(departments, d.id)]));

        return departments.filter((d) => keep.has(d.id));
    }, [departments, needle, locale]);

    const tree = useMemo(() => buildTree(visible), [visible]);
    const blocked = useMemo(
        () => (dragged ? subtreeIds(departments, dragged) : new Set<string>()),
        [departments, dragged],
    );

    const move = (department: string, parentId: string | null) =>
        router.patch(departmentRoutes.move.url(department), { parentId }, { preserveScroll: true });

    const onDrop = (event: DragEvent, target: string) => {
        event.preventDefault();
        setDropTarget(null);

        if (!dragged || blocked.has(target)) {
            return;
        }

        const current = departments.find((d) => d.id === dragged);
        const parentId = target === ROOT ? null : target;

        if (current && current.parentId !== parentId) {
            move(dragged, parentId);
        }

        setDragged(null);
    };

    const toggle = (id: string) =>
        setExpanded((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });

    const renderNode = (node: TreeNode<Department>, level: number) => {
        const department = node.item;
        const open = needle !== '' || expanded.has(department.id);
        const isTarget = dropTarget === department.id && !blocked.has(department.id);

        return (
            <li
                key={department.id}
                role="treeitem"
                aria-selected={false}
                aria-expanded={node.children.length ? open : undefined}
                aria-level={level + 1}
            >
                <div
                    id={`department-${department.id}`}
                    draggable={can.update}
                    onDragStart={(event) => {
                        event.dataTransfer.effectAllowed = 'move';
                        event.dataTransfer.setData('text/plain', department.id);
                        setDragged(department.id);
                    }}
                    onDragEnd={() => {
                        setDragged(null);
                        setDropTarget(null);
                    }}
                    onDragOver={(event) => {
                        if (dragged && !blocked.has(department.id)) {
                            event.preventDefault();
                            setDropTarget(department.id);
                        }
                    }}
                    onDragLeave={() =>
                        setDropTarget((current) => (current === department.id ? null : current))
                    }
                    onDrop={(event) => onDrop(event, department.id)}
                    style={{ paddingInlineStart: `${level * 1.5 + 0.5}rem` }}
                    className={cn(
                        'group flex min-h-12 items-center gap-2 border-b border-border pe-2 transition-colors duration-150 ease-soft',
                        focus === department.id && 'bg-selected',
                        isTarget && 'bg-primary/10 ring-2 ring-primary ring-inset',
                        dragged === department.id && 'opacity-50',
                    )}
                >
                    {can.update ? (
                        <GripVertical
                            aria-hidden
                            className="size-4 shrink-0 cursor-grab text-muted-foreground opacity-0 group-hover:opacity-100"
                        />
                    ) : null}
                    {node.children.length ? (
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            onClick={() => toggle(department.id)}
                            aria-label={open ? t('departments.collapse') : t('departments.expand')}
                        >
                            {open ? (
                                <ChevronDown aria-hidden />
                            ) : (
                                <DirIcon>
                                    <ChevronRight aria-hidden />
                                </DirIcon>
                            )}
                        </Button>
                    ) : (
                        <span aria-hidden className="size-8 shrink-0" />
                    )}
                    <Network aria-hidden className="size-4 shrink-0 text-text-secondary" />
                    <span className="flex min-w-0 flex-1 flex-col py-2">
                        <span className="truncate text-sm font-medium">
                            {localized(department.name, locale)}
                        </span>
                        <bdi
                            dir="ltr"
                            className="max-w-full self-start text-caption text-text-secondary"
                        >
                            {department.code}
                        </bdi>
                    </span>
                    {!department.isActive ? (
                        <Badge variant="outline">{t('common:inactive')}</Badge>
                    ) : null}
                    {can.update || can.delete ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label={`${t('common:actions.more')}: ${localized(department.name, locale)}`}
                                >
                                    <MoreHorizontal aria-hidden />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                {can.update ? (
                                    <>
                                        <DropdownMenuItem onSelect={() => setEditing(department)}>
                                            {t('common:actions.edit')}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem onSelect={() => setMoving(department)}>
                                            {t('departments.moveTo')}
                                        </DropdownMenuItem>
                                    </>
                                ) : null}
                                {can.create ? (
                                    <DropdownMenuItem
                                        onSelect={() =>
                                            setEditing({
                                                ...department,
                                                id: '',
                                                code: '',
                                                name: { en: '', ar: '' },
                                                parentId: department.id,
                                                depth: department.depth + 1,
                                            })
                                        }
                                    >
                                        {t('departments.addChild')}
                                    </DropdownMenuItem>
                                ) : null}
                                {can.delete ? (
                                    <DropdownMenuItem
                                        variant="destructive"
                                        onSelect={() =>
                                            router.delete(
                                                departmentRoutes.destroy.url(department.id),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('common:actions.archive')}
                                    </DropdownMenuItem>
                                ) : null}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>
                {open && node.children.length ? (
                    <ul role="group">
                        {node.children.map((child) => renderNode(child, level + 1))}
                    </ul>
                ) : null}
            </li>
        );
    };

    return (
        <>
            <Head title={t('departments.pageTitle')} />
            <PageHeader
                title={t('title')}
                description={t('description')}
                actions={
                    can.create ? (
                        <Button onClick={() => setEditing('new')}>
                            <Plus aria-hidden />
                            {t('departments.new')}
                        </Button>
                    ) : null
                }
            />
            <OrganizationTabs />

            <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => undefined}
                    label={t('departments.search')}
                />
                {can.update ? (
                    <p className="text-caption text-muted-foreground">
                        {t('departments.dragHelp')}
                    </p>
                ) : null}
            </div>

            {departments.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-search"
                    title={t('departments.empty')}
                    description={t('departments.emptyHelp')}
                />
            ) : visible.length === 0 ? (
                <PageState kind="no-results" title={t('departments.noResults')} />
            ) : (
                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    {dragged ? (
                        <div
                            onDragOver={(event) => {
                                event.preventDefault();
                                setDropTarget(ROOT);
                            }}
                            onDrop={(event) => onDrop(event, ROOT)}
                            className={cn(
                                'border-b border-dashed border-border p-3 text-center text-caption text-muted-foreground',
                                dropTarget === ROOT && 'bg-primary/10 text-foreground',
                            )}
                        >
                            {t('departments.dropTop')}
                        </div>
                    ) : null}
                    <ul role="tree" aria-label={t('departments.treeLabel')}>
                        {tree.map((node) => renderNode(node, 0))}
                    </ul>
                </div>
            )}

            {editing ? (
                <DepartmentDialog
                    department={editing === 'new' ? null : editing}
                    departments={departments}
                    costCenters={options.costCenters}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {moving ? (
                <MoveDialog
                    department={moving}
                    departments={departments}
                    onClose={() => setMoving(null)}
                    onMove={move}
                />
            ) : null}
        </>
    );
}

function ParentSelect({
    id,
    value,
    onChange,
    departments,
    exclude,
}: {
    id: string;
    value: string | null;
    onChange: (value: string | null) => void;
    departments: Department[];
    exclude: Set<string>;
}) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();

    return (
        <Select
            value={value ?? ROOT}
            onValueChange={(next) => onChange(next === ROOT ? null : next)}
        >
            <SelectTrigger id={id}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent className="max-h-80">
                <SelectItem value={ROOT}>{t('departments.topLevel')}</SelectItem>
                {departments
                    .filter((d) => !exclude.has(d.id))
                    .map((d) => (
                        <SelectItem key={d.id} value={d.id}>
                            <span style={{ paddingInlineStart: `${d.depth}rem` }}>
                                {localized(d.name, locale)}
                            </span>
                        </SelectItem>
                    ))}
            </SelectContent>
        </Select>
    );
}

function DepartmentDialog({
    department,
    departments,
    costCenters,
    onClose,
}: {
    department: Department | null;
    departments: Department[];
    costCenters: CostCenter[];
    onClose: () => void;
}) {
    const { t } = useTranslation('organization');
    const isNew = !department?.id;
    const form = useForm({
        code: department?.code ?? '',
        name: { en: department?.name.en ?? '', ar: department?.name.ar ?? '' },
        parentId: department?.parentId ?? null,
        costCenterId: department?.costCenterId ?? null,
        isActive: department?.isActive ?? true,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const exclude = useMemo(
        () => (department?.id ? subtreeIds(departments, department.id) : new Set<string>()),
        [department, departments],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onClose };
        if (isNew) {
            form.post(departmentRoutes.store.url(), options);
        } else {
            form.put(departmentRoutes.update.url(department.id), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={isNew ? t('departments.new') : t('departments.edit')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="department-form" loading={form.processing}>
                        {isNew ? t('common:actions.create') : t('common:actions.saveChanges')}
                    </Button>
                </>
            }
        >
            <form id="department-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="department-code" label={t('fields.code')} error={errors.code} required>
                    <Input
                        id="department-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="department-name"
                    label={t('fields.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <Field id="department-parent" label={t('fields.parent')} error={errors.parentId}>
                    <ParentSelect
                        id="department-parent"
                        value={form.data.parentId}
                        onChange={(parentId) => form.setData('parentId', parentId)}
                        departments={departments}
                        exclude={exclude}
                    />
                </Field>
                <Field
                    id="department-cost-center"
                    label={t('fields.costCenter')}
                    error={errors.costCenterId}
                >
                    <CatalogSelect
                        id="department-cost-center"
                        value={form.data.costCenterId}
                        onChange={(value) => form.setData('costCenterId', value)}
                        options={costCenters}
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

function MoveDialog({
    department,
    departments,
    onClose,
    onMove,
}: {
    department: Department;
    departments: Department[];
    onClose: () => void;
    onMove: (department: string, parentId: string | null) => void;
}) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const [parentId, setParentId] = useState<string | null>(department.parentId);
    const exclude = useMemo(
        () => subtreeIds(departments, department.id),
        [department, departments],
    );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('departments.moveTitle', { name: localized(department.name, locale) })}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        onClick={() => {
                            onMove(department.id, parentId);
                            onClose();
                        }}
                        disabled={parentId === department.parentId}
                    >
                        {t('departments.move')}
                    </Button>
                </>
            }
        >
            <Field id="move-parent" label={t('fields.parent')}>
                <ParentSelect
                    id="move-parent"
                    value={parentId}
                    onChange={setParentId}
                    departments={departments}
                    exclude={exclude}
                />
            </Field>
        </ResponsiveDialog>
    );
}
