import { type RuleNode } from '@/lib/rules';

export const CONDITION_OPS = [
    'eq',
    'neq',
    'gt',
    'gte',
    'lt',
    'lte',
    'contains',
    'empty',
    'not_empty',
] as const;
export type ConditionOp = (typeof CONDITION_OPS)[number];

export type Condition = { ref: string; op: ConditionOp; value: string };
export type Conditions = { mode: 'all' | 'any'; items: Condition[] };

const toLiteral = (value: string): string | number =>
    /^-?\d+(\.\d+)?$/.test(value) ? Number(value) : value;

/** Simple conditions → `mawred.rule/v1`. */
export function toRule(conditions: Conditions): RuleNode | null {
    const items = conditions.items.filter((item) => item.ref);

    if (items.length === 0) {
        return null;
    }

    const nodes: RuleNode[] = items.map((item) => {
        const ref = { ref: item.ref };

        if (item.op === 'empty') return { op: 'empty', args: [ref] };
        if (item.op === 'not_empty') return { op: 'not', args: [{ op: 'empty', args: [ref] }] };

        return { op: item.op, args: [ref, toLiteral(item.value)] };
    });

    return nodes.length === 1 ? nodes[0] : { op: conditions.mode, args: nodes };
}

function fromNode(node: unknown): Condition | null {
    if (
        typeof node !== 'object' ||
        node === null ||
        !('op' in node) ||
        !('args' in node) ||
        !Array.isArray(node.args)
    ) {
        return null;
    }

    const { op, args } = node as { op: string; args: unknown[] };
    const refOf = (arg: unknown) =>
        typeof arg === 'object' && arg !== null && 'ref' in arg && typeof arg.ref === 'string'
            ? arg.ref
            : null;

    if (op === 'not' && args.length === 1) {
        const inner = args[0] as { op?: string; args?: unknown[] };
        const ref = inner.op === 'empty' && Array.isArray(inner.args) ? refOf(inner.args[0]) : null;
        return ref ? { ref, op: 'not_empty', value: '' } : null;
    }

    if (op === 'empty' && args.length === 1) {
        const ref = refOf(args[0]);
        return ref ? { ref, op: 'empty', value: '' } : null;
    }

    if ((CONDITION_OPS as readonly string[]).includes(op) && args.length === 2) {
        const ref = refOf(args[0]);
        const literal = args[1];

        return ref && (typeof literal === 'string' || typeof literal === 'number')
            ? { ref, op: op as ConditionOp, value: String(literal) }
            : null;
    }

    return null;
}

/** `mawred.rule/v1` → simple conditions, or null when the rule is too complex for the editor. */
export function fromRule(rule: unknown): Conditions | null {
    if (rule === null || rule === undefined || rule === true) {
        return { mode: 'all', items: [] };
    }

    const single = fromNode(rule);

    if (single) {
        return { mode: 'all', items: [single] };
    }

    if (
        typeof rule === 'object' &&
        rule !== null &&
        'op' in rule &&
        (rule.op === 'all' || rule.op === 'any') &&
        'args' in rule &&
        Array.isArray(rule.args)
    ) {
        // A freshly switched-on condition starts as all(true): no conditions yet.
        if (rule.args.every((arg) => arg === true)) {
            return { mode: rule.op, items: [] };
        }

        const items = rule.args.map(fromNode);

        return items.every((item): item is Condition => item !== null)
            ? { mode: rule.op, items }
            : null;
    }

    return null;
}
