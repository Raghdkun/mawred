import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import { ScopeCell } from './scope-cell';
import { type CellValue } from './scopes';

type Module = App.Domain.Access.Data.PermissionModuleData;

/**
 * Permission matrix (handoff 38): modules × actions, sticky header row and a module column
 * that sticks to the reading start. Columns run in the reading direction.
 */
export function PermissionMatrix({
    modules,
    actions,
    values,
    defaults,
    readOnly,
    onChange,
}: {
    modules: Module[];
    actions: string[];
    values: Record<string, CellValue>;
    defaults: Record<string, CellValue> | null;
    readOnly: boolean;
    onChange: (permission: string, value: CellValue) => void;
}) {
    const { t } = useTranslation('access');

    return (
        <div className="max-h-[70vh] overflow-auto rounded-lg border border-border bg-card">
            <table className="w-full border-separate border-spacing-0 text-sm">
                <thead>
                    <tr>
                        <th
                            scope="col"
                            className="sticky start-0 top-0 z-20 h-10 border-b border-border bg-surface-sunken px-3 text-start text-caption font-medium text-text-secondary"
                        >
                            <span className="sr-only">{t('matrix.title')}</span>
                        </th>
                        {actions.map((action) => (
                            <th
                                key={action}
                                scope="col"
                                className="sticky top-0 z-10 h-10 border-b border-border bg-surface-sunken px-3 text-start text-caption font-medium whitespace-nowrap text-text-secondary"
                            >
                                {t(`actions.${action}`)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {modules.map((module) => {
                        const moduleLabel = t(`modules.${module.module}`);

                        return (
                            <tr key={module.module} className="group">
                                <th
                                    scope="row"
                                    className="sticky start-0 z-10 h-[52px] border-b border-border bg-card px-3 text-start font-medium whitespace-nowrap group-hover:bg-muted"
                                >
                                    {moduleLabel}
                                </th>
                                {actions.map((action) => {
                                    const permission = `${module.module}.${action}`;
                                    const applicable = module.actions.includes(action);

                                    return (
                                        <td
                                            key={action}
                                            className={cn(
                                                'h-[52px] border-b border-border px-3 whitespace-nowrap group-hover:bg-muted/60',
                                            )}
                                        >
                                            {applicable ? (
                                                <ScopeCell
                                                    value={values[permission] ?? 'none'}
                                                    scoped={module.scoped}
                                                    overridden={
                                                        defaults !== null &&
                                                        (defaults[permission] ?? 'none') !==
                                                            (values[permission] ?? 'none')
                                                    }
                                                    readOnly={readOnly}
                                                    moduleLabel={moduleLabel}
                                                    actionLabel={t(`actions.${action}`)}
                                                    onChange={(value) =>
                                                        onChange(permission, value)
                                                    }
                                                />
                                            ) : (
                                                <span
                                                    className="text-muted-foreground"
                                                    title={t('matrix.notApplicable')}
                                                >
                                                    <span aria-hidden>—</span>
                                                    <span className="sr-only">
                                                        {t('matrix.notApplicable')}
                                                    </span>
                                                </span>
                                            )}
                                        </td>
                                    );
                                })}
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

/** Phones and narrow tablets: a read-only list per module (handoff: mobile read-only per role). */
export function PermissionSummary({
    modules,
    values,
}: {
    modules: Module[];
    values: Record<string, CellValue>;
}) {
    const { t } = useTranslation('access');

    return (
        <ul className="flex flex-col gap-3">
            {modules.map((module) => {
                const granted = module.actions.filter(
                    (action) => (values[`${module.module}.${action}`] ?? 'none') !== 'none',
                );

                return (
                    <li key={module.module} className="rounded-lg bg-card p-4 shadow-sm">
                        <p className="mb-2 font-medium">{t(`modules.${module.module}`)}</p>
                        {granted.length === 0 ? (
                            <p className="text-small text-muted-foreground">
                                {t('scopeNames.none')}
                            </p>
                        ) : (
                            <dl className="grid grid-cols-[minmax(0,1fr)_auto] gap-x-3 gap-y-1.5 text-small">
                                {granted.map((action) => {
                                    const value = values[`${module.module}.${action}`] ?? 'none';

                                    return (
                                        <div key={action} className="contents">
                                            <dt className="text-text-secondary">
                                                {t(`actions.${action}`)}
                                            </dt>
                                            <dd className="font-medium">
                                                {t(`scopeNames.${value}`)}
                                            </dd>
                                        </div>
                                    );
                                })}
                            </dl>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
