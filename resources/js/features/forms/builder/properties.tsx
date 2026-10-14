import { Copy, Trash2 } from 'lucide-react';
import { type ReactNode, useId } from 'react';
import { useTranslation } from 'react-i18next';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type RuleNode } from '@/lib/rules';
import {
    type Bilingual,
    type FieldValidation,
    type FormField,
    type FormPage,
    type FormSection,
    acceptsInput,
    allFields,
    hasOptions,
} from '../schema';
import { type Builder, makeKey } from './use-builder';
import { CalcEditor, ConditionEditor, OptionsEditor } from './rule-editors';

const TODAY: RuleNode = { ref: 'ctx.today' };
const isToday = (value: unknown) =>
    typeof value === 'object' && value !== null && 'ref' in value && value.ref === 'ctx.today';

/** EN + AR side by side (handoff 14). */
export function BilingualFields({
    id,
    label,
    value,
    onChange,
    error,
}: {
    id: string;
    label: string;
    value: Bilingual | null | undefined;
    onChange: (value: Bilingual) => void;
    error?: string;
}) {
    return (
        <fieldset className="flex flex-col gap-1.5">
            <legend className="mb-1.5 text-sm font-medium">{label}</legend>
            <div className="grid grid-cols-2 gap-1.5">
                <Input
                    id={`${id}-en`}
                    aria-label={`${label} (EN)`}
                    dir="ltr"
                    lang="en"
                    placeholder="EN"
                    value={value?.en ?? ''}
                    onChange={(event) => onChange({ ...value, en: event.target.value })}
                />
                <Input
                    id={`${id}-ar`}
                    aria-label={`${label} (AR)`}
                    dir="rtl"
                    lang="ar"
                    placeholder="AR"
                    value={value?.ar ?? ''}
                    onChange={(event) => onChange({ ...value, ar: event.target.value })}
                />
            </div>
            {error ? <p className="text-caption text-danger">{error}</p> : null}
        </fieldset>
    );
}

function Group({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="flex flex-col gap-3 border-t border-border pt-4 first:border-t-0 first:pt-0">
            <h3 className="text-caption font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </h3>
            {children}
        </section>
    );
}

function NumberProp({
    id,
    label,
    value,
    onChange,
    min,
    max,
    hint,
}: {
    id: string;
    label: string;
    value: number | undefined;
    onChange: (value: number | undefined) => void;
    min?: number;
    max?: number;
    hint?: string;
}) {
    return (
        <div className="flex flex-col gap-1">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                type="number"
                dir="ltr"
                className="w-32"
                min={min}
                max={max}
                value={value ?? ''}
                onChange={(event) =>
                    onChange(event.target.value === '' ? undefined : Number(event.target.value))
                }
            />
            {hint ? <p className="text-caption text-muted-foreground">{hint}</p> : null}
        </div>
    );
}

/** Properties of the selected field, section or page (handoff 14: the reading-end panel). */
export function PropertiesPanel({
    builder,
    problems,
    isPublic,
}: {
    builder: Builder;
    problems: Record<string, string[]>;
    isPublic: boolean;
}) {
    const { t } = useTranslation('forms');
    const { selection, schema } = builder;

    if (selection?.kind === 'section') {
        const section = schema.pages
            .flatMap((p) => p.sections)
            .find((s) => s.key === selection.key);
        return section ? <SectionProperties builder={builder} section={section} /> : null;
    }

    if (selection?.kind === 'page') {
        const page = schema.pages.find((p) => p.key === selection.key);
        return page ? <PageProperties builder={builder} page={page} /> : null;
    }

    const field = builder.selectedField;

    if (!field) {
        return <p className="text-small text-muted-foreground">{t('builder.selectHint')}</p>;
    }

    return (
        <FieldProperties
            builder={builder}
            field={field}
            problems={problems[field.key] ?? []}
            isPublic={isPublic}
        />
    );
}

function FieldProperties({
    builder,
    field,
    problems,
    isPublic,
}: {
    builder: Builder;
    field: FormField;
    problems: string[];
    isPublic: boolean;
}) {
    const { t } = useTranslation('forms');
    const id = useId();
    const update = (patch: Partial<FormField>) =>
        builder.updateField(field.key, { ...field, ...patch });
    const validate = (patch: Partial<FieldValidation>) =>
        update({ validation: { ...field.validation, ...patch } });
    const others = allFields(builder.schema).filter((f) => f.key !== field.key);
    const conditional = typeof field.required === 'object' && field.required !== null;

    return (
        <div className="flex flex-col gap-4">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-col">
                    <span className="text-caption text-muted-foreground">
                        {t(`types.${field.type}`)}
                    </span>
                    <bdi
                        dir="ltr"
                        className="self-start font-mono text-caption text-text-secondary"
                    >
                        {field.key}
                    </bdi>
                </div>
                <div className="flex gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('builder.duplicate')}
                        onClick={() => builder.duplicateField(field.key)}
                    >
                        <Copy aria-hidden />
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        aria-label={t('builder.removeField')}
                        onClick={() => builder.removeField(field.key)}
                    >
                        <Trash2 aria-hidden />
                    </Button>
                </div>
            </div>

            {problems.length > 0 ? (
                <ul
                    role="alert"
                    className="flex list-disc flex-col gap-0.5 rounded-md bg-danger-bg p-3 ps-7 text-caption text-danger"
                >
                    {problems.map((problem) => (
                        <li key={problem}>{problem}</li>
                    ))}
                </ul>
            ) : null}

            <Group title={t('builder.groups.content')}>
                <BilingualFields
                    id={`${id}-label`}
                    label={field.type === 'note' ? t('builder.heading') : t('builder.label')}
                    value={field.label}
                    onChange={(label) => {
                        const fresh = !field.label.en && /^[a-z_]+(_\d+)?$/.test(field.key);
                        update({
                            label,
                            ...(fresh && label.en
                                ? {
                                      key: makeKey(
                                          label.en,
                                          builder.keys.filter((k) => k !== field.key),
                                      ),
                                  }
                                : {}),
                        });
                    }}
                />
                <BilingualFields
                    id={`${id}-help`}
                    label={field.type === 'note' ? t('builder.body') : t('builder.help')}
                    value={field.help ?? null}
                    onChange={(help) => update({ help })}
                />
                {acceptsInput(field.type) &&
                !['radio', 'checkboxes', 'boolean', 'file', 'date', 'date_range', 'time'].includes(
                    field.type,
                ) ? (
                    <BilingualFields
                        id={`${id}-placeholder`}
                        label={t('builder.placeholder')}
                        value={field.placeholder ?? null}
                        onChange={(placeholder) => update({ placeholder })}
                    />
                ) : null}
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`${id}-key`}>{t('builder.key')}</Label>
                    <Input
                        id={`${id}-key`}
                        dir="ltr"
                        className="font-mono"
                        value={field.key}
                        onChange={(event) =>
                            update({
                                key: event.target.value
                                    .toLowerCase()
                                    .replace(/[^a-z0-9_]/g, '_')
                                    .slice(0, 40),
                            })
                        }
                    />
                    <p className="text-caption text-muted-foreground">{t('builder.keyHint')}</p>
                </div>
            </Group>

            {hasOptions(field.type) ? (
                <Group title={t('builder.groups.options')}>
                    <OptionsEditor
                        id={`${id}-options`}
                        options={field.options ?? []}
                        onChange={(options) => update({ options })}
                    />
                </Group>
            ) : null}

            {field.type === 'calculated' ? (
                <Group title={t('builder.groups.calculation')}>
                    <CalcEditor
                        id={`${id}-calc`}
                        value={field.calc}
                        fields={others}
                        onChange={(calc) => update({ calc: calc ?? 0 })}
                    />
                </Group>
            ) : null}

            {acceptsInput(field.type) ? (
                <Group title={t('builder.groups.rules')}>
                    <SwitchField
                        id={`${id}-required`}
                        label={t('builder.required')}
                        checked={field.required === true || conditional}
                        onCheckedChange={(on) => update({ required: on })}
                    />
                    {field.required === true || conditional ? (
                        <>
                            <SwitchField
                                id={`${id}-required-when`}
                                label={t('builder.requiredWhen')}
                                checked={conditional}
                                onCheckedChange={(on) =>
                                    update({ required: on ? { op: 'all', args: [true] } : true })
                                }
                            />
                            {conditional ? (
                                <ConditionEditor
                                    id={`${id}-required-rule`}
                                    value={field.required}
                                    fields={others}
                                    includeRequester={!isPublic}
                                    onChange={(rule) => update({ required: rule ?? true })}
                                />
                            ) : null}
                        </>
                    ) : null}
                    <TypeValidation field={field} id={id} validate={validate} update={update} />
                </Group>
            ) : null}

            <Group title={t('builder.groups.visibility')}>
                <SwitchField
                    id={`${id}-visible`}
                    label={t('builder.showWhen')}
                    checked={field.visible !== undefined && field.visible !== null}
                    onCheckedChange={(on) =>
                        update({ visible: on ? { op: 'all', args: [true] } : null })
                    }
                />
                {field.visible !== undefined && field.visible !== null ? (
                    <ConditionEditor
                        id={`${id}-visible-rule`}
                        value={field.visible}
                        fields={others}
                        includeRequester={!isPublic}
                        onChange={(rule) => update({ visible: rule })}
                    />
                ) : null}
            </Group>
        </div>
    );
}

function TypeValidation({
    field,
    id,
    validate,
    update,
}: {
    field: FormField;
    id: string;
    validate: (patch: Partial<FieldValidation>) => void;
    update: (patch: Partial<FormField>) => void;
}) {
    const { t } = useTranslation('forms');
    const v = field.validation ?? {};

    switch (field.type) {
        case 'text':
        case 'textarea':
            return (
                <div className="flex flex-wrap gap-3">
                    <NumberProp
                        id={`${id}-minLength`}
                        label={t('builder.minLength')}
                        value={v.minLength}
                        min={0}
                        onChange={(minLength) => validate({ minLength })}
                    />
                    <NumberProp
                        id={`${id}-maxLength`}
                        label={t('builder.maxLength')}
                        value={v.maxLength}
                        min={1}
                        max={field.type === 'text' ? 255 : 5000}
                        onChange={(maxLength) => validate({ maxLength })}
                    />
                </div>
            );
        case 'number':
            return (
                <>
                    <div className="flex flex-wrap gap-3">
                        <NumberProp
                            id={`${id}-min`}
                            label={t('builder.min')}
                            value={typeof v.min === 'number' ? v.min : undefined}
                            onChange={(min) => validate({ min })}
                        />
                        <NumberProp
                            id={`${id}-max`}
                            label={t('builder.max')}
                            value={typeof v.max === 'number' ? v.max : undefined}
                            onChange={(max) => validate({ max })}
                        />
                    </div>
                    <SwitchField
                        id={`${id}-integer`}
                        label={t('builder.wholeNumbers')}
                        checked={v.integer === true}
                        onCheckedChange={(integer) => validate({ integer })}
                    />
                </>
            );
        case 'money':
            return (
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`${id}-currency`}>{t('builder.currency')}</Label>
                    <Input
                        id={`${id}-currency`}
                        dir="ltr"
                        maxLength={3}
                        className="w-24 uppercase"
                        value={field.currency ?? ''}
                        onChange={(event) =>
                            update({ currency: event.target.value.toUpperCase() || null })
                        }
                    />
                    <p className="text-caption text-muted-foreground">
                        {t('builder.currencyHint')}
                    </p>
                </div>
            );
        case 'date':
        case 'date_range':
            return (
                <>
                    <SwitchField
                        id={`${id}-notPast`}
                        label={t('builder.notBeforeToday')}
                        checked={isToday(v.min)}
                        onCheckedChange={(on) => validate({ min: on ? TODAY : undefined })}
                    />
                    {field.type === 'date' ? (
                        <SwitchField
                            id={`${id}-defaultToday`}
                            label={t('builder.defaultToday')}
                            checked={isToday(field.default)}
                            onCheckedChange={(on) => update({ default: on ? TODAY : null })}
                        />
                    ) : (
                        <NumberProp
                            id={`${id}-maxDays`}
                            label={t('builder.maxDays')}
                            value={v.maxDays}
                            min={1}
                            max={3660}
                            onChange={(maxDays) => validate({ maxDays })}
                        />
                    )}
                </>
            );
        case 'multiselect':
        case 'checkboxes':
            return (
                <div className="flex flex-wrap gap-3">
                    <NumberProp
                        id={`${id}-minItems`}
                        label={t('builder.minItems')}
                        value={v.minItems}
                        min={0}
                        onChange={(minItems) => validate({ minItems })}
                    />
                    <NumberProp
                        id={`${id}-maxItems`}
                        label={t('builder.maxItems')}
                        value={v.maxItems}
                        min={1}
                        onChange={(maxItems) => validate({ maxItems })}
                    />
                </div>
            );
        case 'file':
            return (
                <>
                    <div className="flex flex-wrap gap-3">
                        <NumberProp
                            id={`${id}-maxFiles`}
                            label={t('builder.maxFiles')}
                            value={v.maxFiles ?? 1}
                            min={1}
                            max={10}
                            onChange={(maxFiles) => validate({ maxFiles })}
                        />
                        <NumberProp
                            id={`${id}-maxSize`}
                            label={t('builder.maxSizeMb')}
                            value={Math.round((v.maxSizeKb ?? 10240) / 1024)}
                            min={1}
                            max={20}
                            onChange={(mb) =>
                                validate({
                                    maxSizeKb:
                                        mb === undefined
                                            ? undefined
                                            : Math.min(20, Math.max(1, mb)) * 1024,
                                })
                            }
                        />
                    </div>
                    <fieldset className="flex flex-col gap-1.5">
                        <legend className="mb-1 text-sm font-medium">{t('builder.accept')}</legend>
                        {(['pdf', 'image', 'word', 'excel'] as const).map((kind) => {
                            const accept = v.accept ?? ['pdf', 'image', 'word', 'excel'];

                            return (
                                <div key={kind} className="flex items-center gap-2">
                                    <Checkbox
                                        id={`${id}-accept-${kind}`}
                                        checked={accept.includes(kind)}
                                        onCheckedChange={(on) => {
                                            const next = on
                                                ? [...accept, kind]
                                                : accept.filter((k) => k !== kind);
                                            if (next.length > 0) validate({ accept: next });
                                        }}
                                    />
                                    <Label htmlFor={`${id}-accept-${kind}`} className="font-normal">
                                        {t(`builder.kinds.${kind}`)}
                                    </Label>
                                </div>
                            );
                        })}
                    </fieldset>
                </>
            );
        case 'branch':
        case 'department':
            return (
                <SwitchField
                    id={`${id}-defaultMine`}
                    label={t('builder.defaultMine')}
                    checked={typeof field.default === 'object' && field.default !== null}
                    onCheckedChange={(on) =>
                        update({ default: on ? { ref: `requester.${field.type}` } : null })
                    }
                />
            );
        case 'select':
        case 'radio':
            return (
                <div className="flex flex-col gap-1">
                    <Label htmlFor={`${id}-default`}>{t('builder.defaultOption')}</Label>
                    <Select
                        value={typeof field.default === 'string' ? field.default : '__none'}
                        onValueChange={(value) =>
                            update({ default: value === '__none' ? null : value })
                        }
                    >
                        <SelectTrigger id={`${id}-default`}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none">{t('builder.noDefault')}</SelectItem>
                            {(field.options ?? []).map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {option.label.en || option.value}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            );
        default:
            return null;
    }
}

function SectionProperties({ builder, section }: { builder: Builder; section: FormSection }) {
    const { t } = useTranslation('forms');
    const id = useId();

    return (
        <div className="flex flex-col gap-4">
            <BilingualFields
                id={`${id}-title`}
                label={t('builder.sectionTitle')}
                value={section.title ?? null}
                onChange={(title) => builder.updateSection(section.key, { ...section, title })}
            />
            <BilingualFields
                id={`${id}-description`}
                label={t('builder.sectionDescription')}
                value={section.description ?? null}
                onChange={(description) =>
                    builder.updateSection(section.key, { ...section, description })
                }
            />
            <Button
                type="button"
                variant="ghost"
                className="self-start"
                onClick={() => builder.removeSection(section.key)}
            >
                <Trash2 aria-hidden />
                {t('builder.removeSection')}
            </Button>
        </div>
    );
}

function PageProperties({ builder, page }: { builder: Builder; page: FormPage }) {
    const { t } = useTranslation('forms');
    const id = useId();

    return (
        <div className="flex flex-col gap-4">
            <BilingualFields
                id={`${id}-title`}
                label={t('builder.pageTitle')}
                value={page.title ?? null}
                onChange={(title) => builder.updatePage(page.key, { ...page, title })}
            />
            <Button
                type="button"
                variant="ghost"
                className="self-start"
                disabled={builder.schema.pages.length < 2}
                onClick={() => builder.removePage(page.key)}
            >
                <Trash2 aria-hidden />
                {t('builder.removePage')}
            </Button>
        </div>
    );
}
