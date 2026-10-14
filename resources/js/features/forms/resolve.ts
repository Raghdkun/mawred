import { evaluate, type RuleContext, RuleError, truthy } from '@/lib/rules';
import { acceptsInput, type FormField, type FormRuleContext } from './schema';

const PASSES = 5;

function safely(rule: unknown, context: RuleContext): unknown {
    try {
        return evaluate(rule, context);
    } catch (error) {
        if (error instanceof RuleError) {
            return null;
        }

        throw error;
    }
}

/**
 * Visible fields and calculated values for a set of answers: the twin of
 * `App\Domain\Forms\Support\FormResolution` (same passes, same order). Hidden fields are
 * blanked before calculations and visibility are re-evaluated.
 */
export function resolveForm(
    fields: FormField[],
    input: Record<string, unknown>,
    context: FormRuleContext,
): { values: Record<string, unknown>; hidden: string[] } {
    let hidden: string[] = [];
    let values: Record<string, unknown> = {};

    for (let pass = 0; pass < PASSES; pass++) {
        values = {};

        for (const field of fields) {
            if (acceptsInput(field.type)) {
                values[field.key] = hidden.includes(field.key) ? null : (input[field.key] ?? null);
            }
        }

        for (const field of fields) {
            if (field.type === 'calculated') {
                values[field.key] = hidden.includes(field.key)
                    ? null
                    : safely(field.calc ?? null, { ...context, field: values });
            }
        }

        const next = fields
            .filter(
                (field) =>
                    field.visible !== undefined &&
                    field.visible !== null &&
                    field.visible !== true &&
                    !truthy(safely(field.visible, { ...context, field: values })),
            )
            .map((field) => field.key);

        if (next.length === hidden.length && next.every((key, i) => key === hidden[i])) {
            break;
        }

        hidden = next;
    }

    return { values, hidden };
}

export function isRequired(field: FormField, context: RuleContext): boolean {
    const rule = field.required ?? false;

    return typeof rule === 'boolean' ? rule : truthy(safely(rule, context));
}
