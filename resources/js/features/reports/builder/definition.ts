type FilterOp = App.Domain.Reports.Enums.FilterOp;
type Measure = App.Domain.Reports.Enums.Measure;

export type FilterValue = string | number | string[] | { from: string; to: string };
export type DefinitionFilter = { field: string; op: FilterOp; value: FilterValue };

/** What the builder sends to the preview and saves; the server checks every part of it. */
export type Definition = {
    dataset: string;
    columns: string[];
    filters: DefinitionFilter[];
    groupBy: string | null;
    measure: Measure;
    measureField: string | null;
    chart: 'none' | 'bar' | 'line';
    sort: string | null;
    direction: 'asc' | 'desc';
};

export const STEPS = ['dataset', 'fields', 'filters', 'group', 'visualize'] as const;
export type Step = (typeof STEPS)[number];

export const EMPTY: Definition = {
    dataset: '',
    columns: [],
    filters: [],
    groupBy: null,
    measure: 'count',
    measureField: null,
    chart: 'none',
    sort: null,
    direction: 'asc',
};

/** A saved definition (stored JSON) back into the builder's shape. */
export function fromSaved(raw: Record<string, unknown> | null | undefined): Definition {
    if (!raw) {
        return EMPTY;
    }

    return {
        dataset: typeof raw.dataset === 'string' ? raw.dataset : '',
        columns: Array.isArray(raw.columns)
            ? raw.columns.filter((c): c is string => typeof c === 'string')
            : [],
        filters: Array.isArray(raw.filters) ? (raw.filters as DefinitionFilter[]) : [],
        groupBy: typeof raw.groupBy === 'string' ? raw.groupBy : null,
        measure: raw.measure === 'sum' || raw.measure === 'avg' ? raw.measure : 'count',
        measureField: typeof raw.measureField === 'string' ? raw.measureField : null,
        chart: raw.chart === 'bar' || raw.chart === 'line' ? raw.chart : 'none',
        sort: typeof raw.sort === 'string' ? raw.sort : null,
        direction: raw.direction === 'desc' ? 'desc' : 'asc',
    };
}

/** A filter's starting value for its condition. */
export function emptyValue(op: FilterOp): FilterValue {
    switch (op) {
        case 'in':
            return [];
        case 'between':
            return { from: '', to: '' };
        case 'last_days':
            return 30;
        default:
            return '';
    }
}

/** True once there's enough to preview: data chosen and either columns or a grouping. */
export function previewable(definition: Definition): boolean {
    return (
        definition.dataset !== '' && (definition.columns.length > 0 || definition.groupBy !== null)
    );
}

/** Which step a server error key belongs to (`filters.2.value` → filters). */
export function stepOf(errorKey: string): Step {
    if (errorKey.startsWith('filters')) {
        return 'filters';
    }

    if (errorKey === 'columns') {
        return 'fields';
    }

    if (errorKey === 'groupBy' || errorKey === 'measureField') {
        return 'group';
    }

    if (errorKey === 'dataset') {
        return 'dataset';
    }

    return 'visualize';
}
