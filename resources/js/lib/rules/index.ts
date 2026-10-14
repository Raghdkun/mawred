/**
 * The shared rules language `mawred.rule/v1` (ADR-0008): the TypeScript twin of
 * `app/Support/Rules/Rules.php`. Both must give the same result for every fixture in
 * `tests/fixtures/rules`; change them together.
 *
 * Nodes: a JSON scalar is a literal, `{ value }` is a literal of any shape, `{ ref }` reads the
 * context by dotted path, `{ op, args }` applies an operator. Null propagates.
 */
export const RULE_FORMAT = 'mawred.rule/v1';
export const MAX_DEPTH = 32;

export type RuleNode =
    | string
    | number
    | boolean
    | null
    | { value: unknown }
    | { ref: string }
    | { op: string; args: RuleNode[] };

export type RuleContext = {
    field?: Record<string, unknown>;
    requester?: Record<string, unknown> | null;
    ctx?: {
        today?: string;
        calendar?: { weekend?: number[]; holidays?: string[] };
        balances?: Record<string, number>;
        [key: string]: unknown;
    };
    [root: string]: unknown;
};

/** operator → [min args, max args (null = any)] */
export const OPERATORS: Record<string, [number, number | null]> = {
    all: [1, null],
    any: [1, null],
    not: [1, 1],
    eq: [2, 2],
    neq: [2, 2],
    gt: [2, 2],
    gte: [2, 2],
    lt: [2, 2],
    lte: [2, 2],
    in: [2, 2],
    contains: [2, 2],
    empty: [1, 1],
    between: [3, 3],
    add: [2, null],
    sub: [2, 2],
    mul: [2, null],
    div: [2, 2],
    round: [1, 2],
    sum: [1, 1],
    count: [1, 1],
    if: [3, 3],
    days_between: [2, 2],
    working_days_between: [2, 2],
    balance: [1, 1],
};

export class RuleError extends Error {}

const isObject = (value: unknown): value is Record<string, unknown> =>
    typeof value === 'object' && value !== null && !Array.isArray(value);

export function evaluate(node: unknown, context: RuleContext, depth = 0): unknown {
    if (depth > MAX_DEPTH) {
        throw new RuleError('Rule is nested too deeply.');
    }

    if (
        node === null ||
        typeof node === 'string' ||
        typeof node === 'number' ||
        typeof node === 'boolean'
    ) {
        return node;
    }

    if (!isObject(node)) {
        throw new RuleError('Unsupported rule node.');
    }

    const keys = Object.keys(node);

    if (keys.length === 1 && keys[0] === 'value') {
        return node.value;
    }

    if (keys.length === 1 && keys[0] === 'ref') {
        if (typeof node.ref !== 'string') {
            throw new RuleError('A ref must be a string.');
        }

        return resolve(node.ref, context);
    }

    const op = node.op;
    const args = node.args;

    if (typeof op !== 'string' || !(op in OPERATORS) || !Array.isArray(args)) {
        throw new RuleError('Unknown rule node.');
    }

    const [min, max] = OPERATORS[op];

    if (args.length < min || (max !== null && args.length > max)) {
        throw new RuleError(`Wrong number of arguments for ${op}.`);
    }

    const run = (arg: unknown) => evaluate(arg, context, depth + 1);

    switch (op) {
        case 'all':
            return args.every((arg) => truthy(run(arg)));
        case 'any':
            return args.some((arg) => truthy(run(arg)));
        case 'not':
            return !truthy(run(args[0]));
        case 'if':
            return truthy(run(args[0])) ? run(args[1]) : run(args[2]);
    }

    const values = args.map(run);
    const [a, b, c] = values;

    switch (op) {
        case 'eq':
            return equals(a, b);
        case 'neq':
            return !equals(a, b);
        case 'gt':
            return compare(a, b) === 1;
        case 'gte':
            return compare(a, b) === 1 || compare(a, b) === 0;
        case 'lt':
            return compare(a, b) === -1;
        case 'lte':
            return compare(a, b) === -1 || compare(a, b) === 0;
        case 'in':
            return Array.isArray(b) && b.some((item) => equals(a, item));
        case 'contains':
            return contains(a, b);
        case 'empty':
            return isEmpty(a);
        case 'between': {
            const low = compare(a, b);
            const high = compare(a, c);

            return (low === 0 || low === 1) && (high === -1 || high === 0);
        }
        case 'add':
            return arithmetic(values, (x, y) => x + y);
        case 'sub':
            return arithmetic(values, (x, y) => x - y);
        case 'mul':
            return arithmetic(values, (x, y) => x * y);
        case 'div':
            return toNumber(b) === 0 ? null : arithmetic(values, (x, y) => x / y);
        case 'round':
            return round(a, values.length > 1 ? b : 0);
        case 'sum':
            return Array.isArray(a)
                ? a.reduce<number>((total, item) => total + (toNumber(item) ?? 0), 0)
                : null;
        case 'count':
            return Array.isArray(a) ? a.length : a === null || a === undefined ? 0 : 1;
        case 'days_between':
            return daysBetween(a, b);
        case 'working_days_between':
            return workingDaysBetween(a, b, context);
        case 'balance':
            return typeof a === 'string'
                ? (toNumber(resolve(`ctx.balances.${a}`, context)) ?? null)
                : null;
        default:
            throw new RuleError('Unknown rule node.');
    }
}

/** Structural check, the twin of `Rules::check`: returns problems, empty when valid. */
export function checkRule(
    node: unknown,
    roots = ['field', 'requester', 'ctx'],
    depth = 0,
): string[] {
    if (depth > MAX_DEPTH) {
        return ['Rule is nested too deeply.'];
    }

    if (node === null || ['string', 'number', 'boolean'].includes(typeof node)) {
        return [];
    }

    if (!isObject(node)) {
        return ['Unsupported rule node.'];
    }

    const keys = Object.keys(node);

    if (keys.length === 1 && keys[0] === 'value') {
        return [];
    }

    if (keys.length === 1 && keys[0] === 'ref') {
        const root = typeof node.ref === 'string' ? node.ref.split('.')[0] : null;

        return root !== null && roots.includes(root)
            ? []
            : [`Unknown reference ${JSON.stringify(node.ref)}.`];
    }

    if (typeof node.op !== 'string' || !(node.op in OPERATORS)) {
        return [`Unknown operator ${JSON.stringify(node.op ?? null)}.`];
    }

    if (!Array.isArray(node.args)) {
        return [`${node.op} needs a list of arguments.`];
    }

    const [min, max] = OPERATORS[node.op];

    if (node.args.length < min || (max !== null && node.args.length > max)) {
        return [`Wrong number of arguments for ${node.op}.`];
    }

    return node.args.flatMap((arg) => checkRule(arg, roots, depth + 1));
}

/** Refs a rule reads (e.g. `field.amount`), to warn about deleted fields. */
export function ruleRefs(node: unknown): string[] {
    if (!isObject(node)) {
        return [];
    }

    if (typeof node.ref === 'string') {
        return [node.ref];
    }

    return [...new Set((Array.isArray(node.args) ? node.args : []).flatMap(ruleRefs))];
}

export function truthy(value: unknown): boolean {
    return !(
        value === null ||
        value === undefined ||
        value === false ||
        value === 0 ||
        value === '' ||
        (Array.isArray(value) && value.length === 0) ||
        (isObject(value) && Object.keys(value).length === 0)
    );
}

function resolve(path: string, context: RuleContext): unknown {
    let value: unknown = context;

    for (const segment of path.split('.')) {
        if (Array.isArray(value) && /^\d+$/.test(segment) && Number(segment) < value.length) {
            value = value[Number(segment)];
        } else if (isObject(value) && Object.prototype.hasOwnProperty.call(value, segment)) {
            value = value[segment];
        } else {
            return null;
        }
    }

    return value === undefined ? null : value;
}

function isNumeric(value: unknown): boolean {
    return (
        (typeof value === 'number' && Number.isFinite(value)) ||
        (typeof value === 'string' && /^-?\d+(\.\d+)?$/.test(value))
    );
}

function toNumber(value: unknown): number | null {
    return isNumeric(value) ? Number(value) : null;
}

function equals(a: unknown, b: unknown): boolean {
    if (isNumeric(a) && isNumeric(b)) {
        return Number(a) === Number(b);
    }

    if (typeof a === 'object' && a !== null && typeof b === 'object' && b !== null) {
        return JSON.stringify(a) === JSON.stringify(b);
    }

    return a === b;
}

function compare(a: unknown, b: unknown): number | null {
    if (isNumeric(a) && isNumeric(b)) {
        return Math.sign(Number(a) - Number(b));
    }

    if (typeof a === 'string' && typeof b === 'string') {
        return a < b ? -1 : a > b ? 1 : 0;
    }

    return null;
}

function contains(haystack: unknown, needle: unknown): boolean {
    if (Array.isArray(haystack)) {
        return haystack.some((item) => equals(item, needle));
    }

    return (
        typeof haystack === 'string' &&
        typeof needle === 'string' &&
        haystack.toLowerCase().includes(needle.toLowerCase())
    );
}

function isEmpty(value: unknown): boolean {
    return (
        value === null ||
        value === undefined ||
        (typeof value === 'string' &&
            value.replace(/^[ \t\n\r\0\x0B]+|[ \t\n\r\0\x0B]+$/g, '') === '') ||
        (Array.isArray(value) && value.length === 0) ||
        (isObject(value) && Object.keys(value).length === 0)
    );
}

function arithmetic(values: unknown[], apply: (a: number, b: number) => number): number | null {
    const numbers = values.map(toNumber);

    if (numbers.some((n) => n === null)) {
        return null;
    }

    const result = (numbers as number[]).reduce((total, n) => apply(total, n));

    return Number.isFinite(result) ? result : null;
}

/** Half away from zero, like PHP's round(). */
function round(value: unknown, digits: unknown): number | null {
    const number = toNumber(value);
    const places = toNumber(digits);

    if (number === null || places === null) {
        return null;
    }

    const p = Math.max(0, Math.min(6, Math.trunc(places)));
    const rounded = Number(`${Math.round(Number(`${Math.abs(number)}e${p}`))}e-${p}`);

    return number < 0 && rounded !== 0 ? -rounded : rounded;
}

function toDate(value: unknown): number | null {
    if (typeof value !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return null;
    }

    const time = Date.parse(`${value}T00:00:00Z`);

    return Number.isNaN(time) || new Date(time).toISOString().slice(0, 10) !== value ? null : time;
}

function daysBetween(a: unknown, b: unknown): number | null {
    const from = toDate(a);
    const to = toDate(b);

    return from === null || to === null ? null : Math.trunc((to - from) / 86_400_000);
}

function workingDaysBetween(a: unknown, b: unknown, context: RuleContext): number | null {
    const from = toDate(a);
    const to = toDate(b);

    if (from === null || to === null) {
        return null;
    }

    if (to < from) {
        return 0;
    }

    const weekendRaw = resolve('ctx.calendar.weekend', context);
    const holidaysRaw = resolve('ctx.calendar.holidays', context);
    const weekend = Array.isArray(weekendRaw) ? weekendRaw.map(Number) : [];
    const off = new Set(
        Array.isArray(holidaysRaw)
            ? holidaysRaw.filter((d): d is string => typeof d === 'string')
            : [],
    );
    let days = 0;

    for (let time = from, guard = 0; time <= to && guard < 3660; time += 86_400_000, guard++) {
        const date = new Date(time);
        const iso = date.getUTCDay() === 0 ? 7 : date.getUTCDay();

        if (!weekend.includes(iso) && !off.has(date.toISOString().slice(0, 10))) {
            days++;
        }
    }

    return days;
}
