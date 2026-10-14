import { ArrowDown, ArrowUp, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { checkRule, type RuleNode } from '@/lib/rules';
import { type FieldOption, type FormField, hasOptions, textOf } from '../schema';
import {
    CONDITION_OPS,
    type Condition,
    type ConditionOp,
    type Conditions,
    fromRule,
    toRule,
} from './conditions';

/** Requester attributes a condition can test (FormContext requester.*). */
export const REQUESTER_REFS = [
    'requester.gender',
    'requester.grade',
    'requester.employment_type',
    'requester.branch',
    'requester.department',
] as const;

type RefOption = { ref: string; label: string; options?: FieldOption[] };

function useRefOptions(fields: FormField[], includeRequester: boolean): RefOption[] {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();

    return [
        ...fields
            .filter((f) => f.type !== 'note')
            .map((f) => ({
                ref: `field.${f.key}`,
                label: textOf(f.label, locale) || f.key,
                options: hasOptions(f.type) ? f.options : undefined,
            })),
        ...(includeRequester
            ? REQUESTER_REFS.map((ref) => ({
                  ref,
                  label: t(`builder.requester.${ref.split('.')[1]}`),
              }))
            : []),
    ];
}

/**
 * "Show / require this field when…" (DS conditional logic): rows of field–operator–value, all or
 * any. Rules the rows can't express open in the JSON editor instead.
 */
export function ConditionEditor({
    id,
    value,
    onChange,
    fields,
    includeRequester,
}: {
    id: string;
    value: unknown;
    onChange: (rule: RuleNode | null) => void;
    fields: FormField[];
    includeRequester: boolean;
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const parsed = fromRule(value);
    const [advanced, setAdvanced] = useState(parsed === null);
    const refs = useRefOptions(fields, includeRequester);

    if (advanced || parsed === null) {
        return (
            <JsonRuleEditor
                id={id}
                value={value}
                onChange={onChange}
                onSimple={parsed === null ? undefined : () => setAdvanced(false)}
            />
        );
    }

    const conditions: Conditions = parsed;
    const update = (next: Conditions) => onChange(toRule(next));
    const setItem = (index: number, item: Condition) =>
        update({ ...conditions, items: conditions.items.map((c, i) => (i === index ? item : c)) });

    return (
        <div className="flex flex-col gap-2">
            {conditions.items.length > 1 ? (
                <Select
                    value={conditions.mode}
                    onValueChange={(mode) => update({ ...conditions, mode: mode as 'all' | 'any' })}
                >
                    <SelectTrigger
                        id={`${id}-mode`}
                        aria-label={t('builder.match')}
                        className="w-56"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">{t('builder.matchAll')}</SelectItem>
                        <SelectItem value="any">{t('builder.matchAny')}</SelectItem>
                    </SelectContent>
                </Select>
            ) : null}
            {conditions.items.map((item, index) => {
                const target = refs.find((ref) => ref.ref === item.ref);
                const needsValue = item.op !== 'empty' && item.op !== 'not_empty';

                return (
                    // Conditions have no identity of their own; their position is their key.
                    <div
                        key={index}
                        className="flex flex-col gap-1.5 rounded-md border border-border p-2"
                    >
                        <Select
                            value={item.ref}
                            onValueChange={(ref) => setItem(index, { ...item, ref, value: '' })}
                        >
                            <SelectTrigger aria-label={t('builder.conditionField')}>
                                <SelectValue placeholder={t('builder.chooseField')} />
                            </SelectTrigger>
                            <SelectContent>
                                {refs.map((ref) => (
                                    <SelectItem key={ref.ref} value={ref.ref}>
                                        {ref.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <div className="flex items-center gap-1.5">
                            <Select
                                value={item.op}
                                onValueChange={(op) =>
                                    setItem(index, { ...item, op: op as ConditionOp })
                                }
                            >
                                <SelectTrigger
                                    aria-label={t('builder.conditionOperator')}
                                    className="w-40"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {CONDITION_OPS.map((op) => (
                                        <SelectItem key={op} value={op}>
                                            {t(`builder.ops.${op}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {needsValue ? (
                                target?.options ? (
                                    <Select
                                        value={item.value}
                                        onValueChange={(next) =>
                                            setItem(index, { ...item, value: next })
                                        }
                                    >
                                        <SelectTrigger
                                            aria-label={t('builder.conditionValue')}
                                            className="min-w-0 flex-1"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {target.options.map((option) => (
                                                <SelectItem key={option.value} value={option.value}>
                                                    {textOf(option.label, locale) || option.value}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                ) : (
                                    <Input
                                        aria-label={t('builder.conditionValue')}
                                        className="min-w-0 flex-1"
                                        value={item.value}
                                        onChange={(event) =>
                                            setItem(index, { ...item, value: event.target.value })
                                        }
                                    />
                                )
                            ) : (
                                <span className="flex-1" />
                            )}
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label={t('builder.removeCondition')}
                                onClick={() =>
                                    update({
                                        ...conditions,
                                        items: conditions.items.filter((_, i) => i !== index),
                                    })
                                }
                            >
                                <X aria-hidden />
                            </Button>
                        </div>
                    </div>
                );
            })}
            <div className="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        update({
                            ...conditions,
                            items: [
                                ...conditions.items,
                                { ref: refs[0]?.ref ?? '', op: 'eq', value: '' },
                            ],
                        })
                    }
                    disabled={refs.length === 0}
                >
                    <Plus aria-hidden />
                    {t('builder.addCondition')}
                </Button>
                <Button type="button" variant="ghost" size="sm" onClick={() => setAdvanced(true)}>
                    {t('builder.advanced')}
                </Button>
            </div>
        </div>
    );
}

/** A rule as `mawred.rule/v1` JSON, checked as you type. */
export function JsonRuleEditor({
    id,
    value,
    onChange,
    onSimple,
}: {
    id: string;
    value: unknown;
    onChange: (rule: RuleNode | null) => void;
    onSimple?: () => void;
}) {
    const { t } = useTranslation('forms');
    const [text, setText] = useState(() =>
        value === undefined || value === null ? '' : JSON.stringify(value, null, 2),
    );
    const [problem, setProblem] = useState<string | null>(null);

    return (
        <div className="flex flex-col gap-1.5">
            <Textarea
                id={id}
                dir="ltr"
                rows={5}
                className="font-mono text-caption"
                value={text}
                aria-invalid={problem !== null || undefined}
                onChange={(event) => {
                    setText(event.target.value);

                    if (event.target.value.trim() === '') {
                        setProblem(null);
                        onChange(null);
                        return;
                    }

                    try {
                        const parsed = JSON.parse(event.target.value) as RuleNode;
                        const problems = checkRule(parsed);
                        setProblem(problems[0] ?? null);
                        if (problems.length === 0) onChange(parsed);
                    } catch {
                        setProblem(t('builder.invalidJson'));
                    }
                }}
            />
            {problem ? (
                <p role="alert" className="text-caption text-danger">
                    {problem}
                </p>
            ) : (
                <p className="text-caption text-muted-foreground">{t('builder.jsonHint')}</p>
            )}
            {onSimple ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="self-start"
                    onClick={onSimple}
                >
                    {t('builder.simple')}
                </Button>
            ) : null}
        </div>
    );
}

type CalcShape =
    | { kind: 'days' | 'working_days'; range: string }
    | { kind: 'arith'; op: 'add' | 'sub' | 'mul' | 'div'; a: string; b: string }
    | { kind: 'json' };

const operand = (value: string): RuleNode =>
    /^-?\d+(\.\d+)?$/.test(value) ? Number(value) : { ref: `field.${value}` };
const operandText = (node: unknown): string | null =>
    typeof node === 'number'
        ? String(node)
        : typeof node === 'object' &&
            node !== null &&
            'ref' in node &&
            typeof node.ref === 'string' &&
            node.ref.startsWith('field.')
          ? node.ref.slice(6)
          : null;

/** The date range field a `days_between` / `working_days_between` node spans, if it is that shape. */
function rangeOf(node: unknown, op: string): string | null {
    if (
        typeof node !== 'object' ||
        node === null ||
        !('op' in node) ||
        node.op !== op ||
        !('args' in node) ||
        !Array.isArray(node.args) ||
        node.args.length !== 2
    ) {
        return null;
    }

    const from = operandText(node.args[0]);
    const to = operandText(node.args[1]);

    return from?.endsWith('.from') && to === `${from.slice(0, -5)}.to` ? from.slice(0, -5) : null;
}

function calcShape(rule: unknown): CalcShape {
    if (
        typeof rule !== 'object' ||
        rule === null ||
        !('op' in rule) ||
        !('args' in rule) ||
        !Array.isArray(rule.args)
    ) {
        return { kind: 'json' };
    }

    const { op, args } = rule as { op: string; args: unknown[] };

    // Inclusive days are stored as days_between + 1.
    const days =
        op === 'add' && args.length === 2 && args[1] === 1
            ? rangeOf(args[0], 'days_between')
            : null;
    if (days) return { kind: 'days', range: days };

    const working = rangeOf(rule, 'working_days_between');
    if (working) return { kind: 'working_days', range: working };

    if (['add', 'sub', 'mul', 'div'].includes(op) && args.length === 2) {
        const a = operandText(args[0]);
        const b = operandText(args[1]);

        if (a !== null && b !== null) return { kind: 'arith', op: op as 'add', a, b };
    }

    return { kind: 'json' };
}

/** Calculated field: common calculations as forms, anything else as JSON. */
export function CalcEditor({
    id,
    value,
    onChange,
    fields,
}: {
    id: string;
    value: unknown;
    onChange: (rule: RuleNode | null) => void;
    fields: FormField[];
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const [shape, setShape] = useState<CalcShape>(() => calcShape(value));
    const ranges = fields.filter((f) => f.type === 'date_range');
    const numbers = fields.filter((f) => f.type === 'number' || f.type === 'calculated');

    const apply = (next: CalcShape) => {
        setShape(next);

        if (next.kind === 'days' || next.kind === 'working_days') {
            if (!next.range) return;
            const op = next.kind === 'days' ? 'days_between' : 'working_days_between';
            const days: RuleNode = {
                op,
                args: [{ ref: `field.${next.range}.from` }, { ref: `field.${next.range}.to` }],
            };
            // Days between counts nights; inclusive days add one.
            onChange(next.kind === 'days' ? { op: 'add', args: [days, 1] } : days);
        } else if (next.kind === 'arith') {
            if (next.a && next.b)
                onChange({ op: next.op, args: [operand(next.a), operand(next.b)] });
        }
    };

    return (
        <div className="flex flex-col gap-2">
            <Select
                value={shape.kind}
                onValueChange={(kind) =>
                    apply(
                        kind === 'arith'
                            ? { kind, op: 'add', a: '', b: '' }
                            : kind === 'json'
                              ? { kind }
                              : { kind: kind as 'days', range: ranges[0]?.key ?? '' },
                    )
                }
            >
                <SelectTrigger id={`${id}-kind`} aria-label={t('builder.calcKind')}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="days">{t('builder.calc.days')}</SelectItem>
                    <SelectItem value="working_days">{t('builder.calc.workingDays')}</SelectItem>
                    <SelectItem value="arith">{t('builder.calc.arith')}</SelectItem>
                    <SelectItem value="json">{t('builder.calc.json')}</SelectItem>
                </SelectContent>
            </Select>
            {shape.kind === 'days' || shape.kind === 'working_days' ? (
                <Select value={shape.range} onValueChange={(range) => apply({ ...shape, range })}>
                    <SelectTrigger aria-label={t('builder.calc.range')}>
                        <SelectValue placeholder={t('builder.calc.range')} />
                    </SelectTrigger>
                    <SelectContent>
                        {ranges.map((f) => (
                            <SelectItem key={f.key} value={f.key}>
                                {textOf(f.label, locale) || f.key}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : null}
            {shape.kind === 'arith' ? (
                <div className="flex flex-wrap items-center gap-1.5">
                    <OperandInput
                        label={t('builder.calc.first')}
                        value={shape.a}
                        numbers={numbers}
                        onChange={(a) => apply({ ...shape, a })}
                    />
                    <Select
                        value={shape.op}
                        onValueChange={(op) => apply({ ...shape, op: op as 'add' })}
                    >
                        <SelectTrigger aria-label={t('builder.calc.operator')} className="w-16">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="add">+</SelectItem>
                            <SelectItem value="sub">−</SelectItem>
                            <SelectItem value="mul">×</SelectItem>
                            <SelectItem value="div">÷</SelectItem>
                        </SelectContent>
                    </Select>
                    <OperandInput
                        label={t('builder.calc.second')}
                        value={shape.b}
                        numbers={numbers}
                        onChange={(b) => apply({ ...shape, b })}
                    />
                </div>
            ) : null}
            {shape.kind === 'json' ? (
                <JsonRuleEditor id={id} value={value} onChange={onChange} />
            ) : null}
        </div>
    );
}

function OperandInput({
    label,
    value,
    numbers,
    onChange,
}: {
    label: string;
    value: string;
    numbers: FormField[];
    onChange: (value: string) => void;
}) {
    const { locale } = useLocale();
    const listId = `operands-${label.replace(/\W/g, '')}`;

    return (
        <>
            <Input
                aria-label={label}
                list={listId}
                dir="ltr"
                className="w-32"
                value={value}
                onChange={(event) => onChange(event.target.value.trim())}
            />
            <datalist id={listId}>
                {numbers.map((f) => (
                    <option key={f.key} value={f.key}>
                        {textOf(f.label, locale)}
                    </option>
                ))}
            </datalist>
        </>
    );
}

/** Choices for select, radio and checkboxes: value + EN/AR labels, reorderable. */
export function OptionsEditor({
    id,
    options,
    onChange,
}: {
    id: string;
    options: FieldOption[];
    onChange: (options: FieldOption[]) => void;
}) {
    const { t } = useTranslation('forms');
    const move = (from: number, to: number) => {
        const next = [...options];
        const [item] = next.splice(from, 1);
        if (item) next.splice(to, 0, item);
        onChange(next);
    };

    return (
        <div className="flex flex-col gap-2">
            {options.map((option, index) => (
                // Options are edited in place, value included; position is the stable key.
                <div
                    key={index}
                    className="flex flex-col gap-1.5 rounded-md border border-border p-2"
                >
                    <div className="grid grid-cols-2 gap-1.5">
                        <Input
                            aria-label={`${t('builder.optionLabel')} (EN)`}
                            dir="ltr"
                            value={option.label.en ?? ''}
                            onChange={(event) =>
                                onChange(
                                    options.map((o, i) =>
                                        i === index
                                            ? {
                                                  ...o,
                                                  label: { ...o.label, en: event.target.value },
                                              }
                                            : o,
                                    ),
                                )
                            }
                        />
                        <Input
                            aria-label={`${t('builder.optionLabel')} (AR)`}
                            dir="rtl"
                            lang="ar"
                            value={option.label.ar ?? ''}
                            onChange={(event) =>
                                onChange(
                                    options.map((o, i) =>
                                        i === index
                                            ? {
                                                  ...o,
                                                  label: { ...o.label, ar: event.target.value },
                                              }
                                            : o,
                                    ),
                                )
                            }
                        />
                    </div>
                    <div className="flex items-center gap-1">
                        <Label
                            htmlFor={`${id}-${index}-value`}
                            className="text-caption text-muted-foreground"
                        >
                            {t('builder.optionValue')}
                        </Label>
                        <Input
                            id={`${id}-${index}-value`}
                            dir="ltr"
                            className="h-8 min-w-0 flex-1 font-mono text-caption"
                            value={option.value}
                            onChange={(event) =>
                                onChange(
                                    options.map((o, i) =>
                                        i === index
                                            ? {
                                                  ...o,
                                                  value: event.target.value.replace(/\s+/g, '_'),
                                              }
                                            : o,
                                    ),
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('builder.moveUp')}
                            disabled={index === 0}
                            onClick={() => move(index, index - 1)}
                        >
                            <ArrowUp aria-hidden />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('builder.moveDown')}
                            disabled={index === options.length - 1}
                            onClick={() => move(index, index + 1)}
                        >
                            <ArrowDown aria-hidden />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            aria-label={t('builder.removeOption')}
                            disabled={options.length < 2}
                            onClick={() => onChange(options.filter((_, i) => i !== index))}
                        >
                            <X aria-hidden />
                        </Button>
                    </div>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() =>
                    onChange([
                        ...options,
                        { value: `option_${options.length + 1}`, label: { en: '', ar: '' } },
                    ])
                }
                disabled={options.length >= 200}
            >
                <Plus aria-hidden />
                {t('builder.addOption')}
            </Button>
        </div>
    );
}
