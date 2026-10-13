export type ScopeValue = App.Domain.Access.Enums.Scope;
/** A cell value: a scope, `on` for unscoped (configuration) modules, or `none`. */
export type CellValue = ScopeValue | 'on' | 'none';

/** Click cycles through these (DSData permNote). "Team" is the whole tree; direct reports via the cell menu. */
export const SCOPED_CYCLE: CellValue[] = [
    'none',
    'own',
    'team_tree',
    'department',
    'branch',
    'all',
];
export const UNSCOPED_CYCLE: CellValue[] = ['none', 'on'];

export const SCOPED_OPTIONS: CellValue[] = [
    'none',
    'own',
    'direct_reports',
    'team_tree',
    'department',
    'branch',
    'all',
];

export function nextValue(current: CellValue, scoped: boolean): CellValue {
    const cycle = scoped ? SCOPED_CYCLE : UNSCOPED_CYCLE;
    const normalised = current === 'direct_reports' ? 'team_tree' : current;
    const index = cycle.indexOf(normalised);

    return cycle[(index + 1) % cycle.length] ?? 'none';
}

/** Server grants (permission → scope) to a cell value; unscoped grants are stored as `all`. */
export function cellFromGrant(grant: string | undefined, scoped: boolean): CellValue {
    if (!grant) {
        return 'none';
    }

    return scoped ? (grant as ScopeValue) : 'on';
}

export function grantFromCell(value: CellValue): ScopeValue | null {
    if (value === 'none') {
        return null;
    }

    return value === 'on' ? 'all' : value;
}
