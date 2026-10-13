import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Copy, Info, Pencil, Search, Trash2 } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader, Section } from '@/components/mw/page-header';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { AccessTabs } from '@/features/access/access-tabs';
import { PermissionMatrix, PermissionSummary } from '@/features/access/permission-matrix';
import { CloneRoleDialog, DeleteRoleDialog, EditRoleDialog } from '@/features/access/role-dialogs';
import { type CellValue, cellFromGrant, grantFromCell } from '@/features/access/scopes';
import { useLocale } from '@/hooks/use-locale';
import { componentTransition } from '@/lib/motion';
import { cn } from '@/lib/utils';
import accessRoles from '@/routes/access/roles';
import { fieldLabel } from '@/features/access/field-label';

type Props = {
    role: App.Domain.Access.Data.RoleSummaryData;
    modules: App.Domain.Access.Data.PermissionModuleData[];
    actions: string[];
    homeLayouts: string[];
    grants: Record<string, string>;
    defaults: Record<string, string> | null;
    sensitiveFields: string[];
    fieldLabels: Record<string, Record<string, string>>;
    fields: Record<string, 'read' | 'write'>;
    can: { update: boolean; create: boolean; delete: boolean };
};

function toCells(
    modules: Props['modules'],
    grants: Record<string, string>,
): Record<string, CellValue> {
    const cells: Record<string, CellValue> = {};

    for (const module of modules) {
        for (const action of module.actions) {
            const permission = `${module.module}.${action}`;
            cells[permission] = cellFromGrant(grants[permission], module.scoped);
        }
    }

    return cells;
}

export default function RoleShow({
    role,
    modules,
    actions,
    homeLayouts,
    grants,
    defaults,
    sensitiveFields,
    fieldLabels,
    fields,
    can,
}: Props) {
    const { t, i18n } = useTranslation('access');
    const { locale } = useLocale();
    const reduced = useReducedMotion() ?? false;
    const roleLabel = role.label[locale] ?? role.label.en ?? role.name;

    const saved = useMemo(
        () => toCells(modules, role.isSuperAdmin ? {} : grants),
        [modules, grants, role.isSuperAdmin],
    );
    const defaultCells = useMemo(
        () => (defaults ? toCells(modules, defaults) : null),
        [modules, defaults],
    );
    const [cells, setCells] = useState<Record<string, CellValue>>(saved);
    const [filter, setFilter] = useState('');
    const [reviewOpen, setReviewOpen] = useState(false);
    const [saving, setSaving] = useState(false);
    const [dialog, setDialog] = useState<'edit' | 'clone' | 'delete' | null>(null);
    const [fieldAccess, setFieldAccess] = useState<Record<string, 'none' | 'read' | 'write'>>(() =>
        Object.fromEntries(sensitiveFields.map((field) => [field, fields[field] ?? 'none'])),
    );

    const readOnly = !can.update || role.isSuperAdmin;
    const superAdminCells = useMemo(() => {
        const all: Record<string, CellValue> = {};
        modules.forEach((module) =>
            module.actions.forEach(
                (action) => (all[`${module.module}.${action}`] = module.scoped ? 'all' : 'on'),
            ),
        );

        return all;
    }, [modules]);
    const displayed = role.isSuperAdmin ? superAdminCells : cells;

    const changes = useMemo(() => {
        const added: string[] = [];
        const removed: string[] = [];
        const rescoped: string[] = [];

        for (const permission of Object.keys(cells)) {
            const before = saved[permission] ?? 'none';
            const after = cells[permission] ?? 'none';

            if (before === after) continue;
            if (before === 'none') added.push(permission);
            else if (after === 'none') removed.push(permission);
            else rescoped.push(permission);
        }

        return { added, removed, rescoped, count: added.length + removed.length + rescoped.length };
    }, [cells, saved]);

    const visibleModules = modules.filter((module) =>
        t(`modules.${module.module}`).toLowerCase().includes(filter.trim().toLowerCase()),
    );

    const save = () => {
        const payload: Record<string, string> = {};

        for (const [permission, value] of Object.entries(cells)) {
            const grant = grantFromCell(value);

            if (grant) payload[permission] = grant;
        }

        setSaving(true);
        router.put(
            accessRoles.grants.update.url(role.id),
            { grants: payload },
            {
                preserveScroll: true,
                onFinish: () => setSaving(false),
                onSuccess: () => setReviewOpen(false),
            },
        );
    };

    const permissionName = (permission: string) => {
        const [module, action] = permission.split('.');

        return `${t(`modules.${module}`)} · ${t(`actions.${action}`)}`;
    };

    const saveBar = componentTransition(reduced);

    return (
        <>
            <Head title={roleLabel} />

            <Link
                href={accessRoles.index()}
                className="mb-4 inline-flex items-center gap-1.5 text-small text-link hover:underline"
            >
                <DirIcon>
                    <ArrowLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('tabs.roles')}
            </Link>

            <PageHeader
                title={roleLabel}
                description={role.description[locale] ?? role.description.en}
                actions={
                    <>
                        {can.update && !role.isSuperAdmin ? (
                            <Button variant="outline" onClick={() => setDialog('edit')}>
                                <Pencil aria-hidden /> {t('details.edit')}
                            </Button>
                        ) : null}
                        {can.create ? (
                            <Button variant="outline" onClick={() => setDialog('clone')}>
                                <Copy aria-hidden /> {t('details.clone')}
                            </Button>
                        ) : null}
                        {can.delete ? (
                            <Button
                                variant="ghost"
                                className="text-danger"
                                onClick={() => setDialog('delete')}
                            >
                                <Trash2 aria-hidden /> {t('details.delete')}
                            </Button>
                        ) : null}
                    </>
                }
            />
            <AccessTabs />

            {role.isSuperAdmin ? (
                <p className="mb-4 flex items-start gap-2 rounded-md border border-info-border bg-info-bg p-3 text-small text-info">
                    <Info aria-hidden className="mt-0.5 size-4 shrink-0" />
                    {t('matrix.superAdmin')}
                </p>
            ) : null}

            <section aria-labelledby="matrix-title" className="mb-8">
                <div className="mb-3 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h2 id="matrix-title" className="text-h4">
                            {t('matrix.title')}
                        </h2>
                        {!readOnly ? (
                            <p className="hidden text-small text-muted-foreground xl:block">
                                {t('matrix.hint')}
                            </p>
                        ) : null}
                    </div>
                    <div className="relative hidden w-64 xl:block">
                        <Search
                            aria-hidden
                            className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            value={filter}
                            onChange={(event) => setFilter(event.target.value)}
                            placeholder={t('matrix.searchModules')}
                            aria-label={t('matrix.searchModules')}
                            className="ps-9"
                        />
                    </div>
                </div>

                <div className="hidden xl:block">
                    <PermissionMatrix
                        modules={visibleModules}
                        actions={actions}
                        values={displayed}
                        defaults={role.isSuperAdmin ? null : defaultCells}
                        readOnly={readOnly}
                        onChange={(permission, value) =>
                            setCells((current) => ({ ...current, [permission]: value }))
                        }
                    />
                </div>
                <div className="xl:hidden">
                    <p className="mb-3 text-small text-muted-foreground">
                        {t('matrix.desktopOnly')}
                    </p>
                    <PermissionSummary modules={modules} values={displayed} />
                </div>
            </section>

            {!role.isSuperAdmin ? (
                <Section
                    title={t('fields.title')}
                    description={t('fields.description')}
                    actions={
                        can.update ? (
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    router.put(
                                        accessRoles.fields.update.url(role.id),
                                        {
                                            fields: Object.fromEntries(
                                                Object.entries(fieldAccess).filter(
                                                    ([, access]) => access !== 'none',
                                                ),
                                            ),
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {t('fields.save')}
                            </Button>
                        ) : null
                    }
                >
                    <ul className="flex flex-col divide-y divide-border">
                        {sensitiveFields.map((field) => (
                            <li
                                key={field}
                                className="flex flex-wrap items-center justify-between gap-3 py-3"
                            >
                                <span className="text-sm font-medium">
                                    {fieldLabel(field, fieldLabels, i18n.language, t)}
                                </span>
                                <div
                                    role="radiogroup"
                                    aria-label={fieldLabel(field, fieldLabels, i18n.language, t)}
                                    className="inline-flex gap-1 rounded-lg bg-surface-sunken p-1"
                                >
                                    {(['none', 'read', 'write'] as const).map((access) => (
                                        <button
                                            key={access}
                                            type="button"
                                            role="radio"
                                            aria-checked={fieldAccess[field] === access}
                                            disabled={!can.update}
                                            onClick={() =>
                                                setFieldAccess((current) => ({
                                                    ...current,
                                                    [field]: access,
                                                }))
                                            }
                                            className={cn(
                                                'h-8 rounded-sm px-4 text-small font-medium text-text-secondary transition-colors duration-150 ease-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed',
                                                fieldAccess[field] === access &&
                                                    'bg-card text-foreground shadow-sm',
                                            )}
                                        >
                                            {t(`fields.${access}`)}
                                        </button>
                                    ))}
                                </div>
                            </li>
                        ))}
                    </ul>
                </Section>
            ) : null}

            {changes.count > 0 ? (
                <motion.div
                    role="region"
                    aria-label={t('matrix.unsaved', { count: changes.count })}
                    initial="initial"
                    animate="animate"
                    variants={saveBar.variants}
                    className="fixed inset-x-4 bottom-24 z-30 mx-auto flex max-w-xl items-center justify-between gap-3 rounded-xl border border-border bg-popover p-3 shadow-overlay md:bottom-6"
                >
                    <span className="ps-2 text-sm font-medium" aria-live="polite">
                        {t('matrix.unsaved', { count: changes.count })}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="ghost" onClick={() => setCells(saved)}>
                            {t('matrix.discard')}
                        </Button>
                        <Button onClick={() => setReviewOpen(true)}>{t('matrix.review')}</Button>
                    </div>
                </motion.div>
            ) : null}

            <Dialog open={reviewOpen} onOpenChange={setReviewOpen}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>{t('matrix.diffTitle')}</DialogTitle>
                        <DialogDescription>{t('matrix.diffDescription')}</DialogDescription>
                    </DialogHeader>
                    <div className="flex max-h-80 flex-col gap-4 overflow-y-auto text-sm">
                        {(['added', 'rescoped', 'removed'] as const).map((kind) =>
                            changes[kind].length > 0 ? (
                                <div key={kind} className="flex flex-col gap-1.5">
                                    <p className="text-caption font-medium text-muted-foreground uppercase">
                                        {t(`matrix.${kind}`)}
                                    </p>
                                    <ul className="flex flex-col gap-1">
                                        {changes[kind].map((permission) => (
                                            <li
                                                key={permission}
                                                className="flex items-center justify-between gap-3"
                                            >
                                                <span>{permissionName(permission)}</span>
                                                <span
                                                    className={cn(
                                                        'text-caption',
                                                        kind === 'removed'
                                                            ? 'text-danger'
                                                            : 'text-text-secondary',
                                                    )}
                                                >
                                                    {kind === 'removed'
                                                        ? t(
                                                              `scopeNames.${saved[permission] ?? 'none'}`,
                                                          )
                                                        : t(
                                                              `scopeNames.${cells[permission] ?? 'none'}`,
                                                          )}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null,
                        )}
                    </div>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setReviewOpen(false)}>
                            {t('common:actions.cancel')}
                        </Button>
                        <Button onClick={save} loading={saving}>
                            {t('matrix.confirmSave')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {dialog === 'edit' ? (
                <EditRoleDialog
                    role={role}
                    homeLayouts={homeLayouts}
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                />
            ) : null}
            {dialog === 'clone' ? (
                <CloneRoleDialog
                    role={role}
                    roleLabel={roleLabel}
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                />
            ) : null}
            {dialog === 'delete' ? (
                <DeleteRoleDialog
                    role={role}
                    roleLabel={roleLabel}
                    open
                    onOpenChange={(open) => !open && setDialog(null)}
                />
            ) : null}
        </>
    );
}
