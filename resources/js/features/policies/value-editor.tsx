import type { FormDataConvertible } from '@inertiajs/core';
import { Plus, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { SwitchField } from '@/components/mw/switch-field';
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
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLocale } from '@/hooks/use-locale';
import { weekdayName } from '@/lib/regions';
import { cn } from '@/lib/utils';
import { type PolicyValue } from './types';
import { EDITORS, type FieldSpec, type Unit, weekFrom } from './values';

type Props = {
    /** Prefix for element ids, unique per key on the page. */
    id: string;
    editor: string;
    value: PolicyValue;
    onChange: (value: PolicyValue) => void;
    /** Server errors for this key, by field (e.g. `lateMinutes`, `tenureSteps.0.afterYears`). */
    errors: Record<string, string>;
    weekStartsOn: number;
    disabled?: boolean;
};

/** The typed editor for one policy value (handoff 17). Units sit at the reading end of inputs. */
export function ValueEditor({
    id,
    editor,
    value,
    onChange,
    errors,
    weekStartsOn,
    disabled,
}: Props) {
    const fields = EDITORS[editor] ?? [];
    const set = (name: string, next: FormDataConvertible) => onChange({ ...value, [name]: next });

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {fields.map((field) => (
                <FieldInput
                    key={field.name}
                    id={`${id}-${field.name}`}
                    spec={field}
                    value={value}
                    set={set}
                    errors={errors}
                    weekStartsOn={weekStartsOn}
                    disabled={disabled}
                />
            ))}
        </div>
    );
}

function FieldInput({
    id,
    spec,
    value,
    set,
    errors,
    weekStartsOn,
    disabled,
}: {
    id: string;
    spec: FieldSpec;
    value: PolicyValue;
    set: (name: string, next: FormDataConvertible) => void;
    errors: Record<string, string>;
    weekStartsOn: number;
    disabled?: boolean;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const error = errors[spec.name];
    const label = t(`fields.${spec.name}`);

    switch (spec.kind) {
        case 'number':
            return (
                <FieldShell id={id} label={label} error={error}>
                    <UnitInput
                        id={id}
                        unit={spec.unit}
                        step={spec.step}
                        min={spec.min}
                        max={spec.max}
                        nullable={spec.nullable}
                        value={value[spec.name] as number | null | undefined}
                        onChange={(next) => set(spec.name, next)}
                        disabled={disabled}
                        invalid={Boolean(error)}
                    />
                </FieldShell>
            );
        case 'time':
            return (
                <FieldShell id={id} label={label} error={error}>
                    <Input
                        id={id}
                        type="time"
                        dir="ltr"
                        value={String(value[spec.name] ?? '')}
                        onChange={(event) => set(spec.name, event.target.value)}
                        disabled={disabled}
                        aria-invalid={Boolean(error)}
                        className="w-36"
                    />
                </FieldShell>
            );
        case 'switch':
            return (
                <div className="sm:col-span-2">
                    <SwitchField
                        id={id}
                        label={label}
                        checked={Boolean(value[spec.name])}
                        onCheckedChange={(checked) => set(spec.name, checked)}
                        disabled={disabled}
                    />
                </div>
            );
        case 'select':
            if (spec.segmented) {
                return (
                    <div className="flex flex-col gap-1.5 sm:col-span-2">
                        <span id={`${id}-label`} className="text-sm font-medium">
                            {label}
                        </span>
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            aria-labelledby={`${id}-label`}
                            value={String(value[spec.name] ?? '')}
                            onValueChange={(next) => next && set(spec.name, next)}
                            className="flex-wrap justify-start"
                            disabled={disabled}
                        >
                            {spec.options.map((option) => (
                                <ToggleGroupItem key={option} value={option} className="px-3">
                                    {t(`options.${spec.name}.${option}`)}
                                </ToggleGroupItem>
                            ))}
                        </ToggleGroup>
                        <FieldError id={id} error={error} />
                    </div>
                );
            }

            return (
                <FieldShell id={id} label={label} error={error}>
                    <Select
                        value={String(value[spec.name] ?? '')}
                        onValueChange={(next) => set(spec.name, next)}
                        disabled={disabled}
                    >
                        <SelectTrigger id={id} aria-invalid={Boolean(error)}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {spec.options.map((option) => (
                                <SelectItem key={option} value={option}>
                                    {t(`options.${spec.name}.${option}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </FieldShell>
            );
        case 'weekdays': {
            const days = Array.isArray(value[spec.name]) ? (value[spec.name] as number[]) : [];

            return (
                <div className="flex flex-col gap-1.5 sm:col-span-2">
                    <span id={`${id}-label`} className="text-sm font-medium">
                        {label}
                    </span>
                    <ToggleGroup
                        type="multiple"
                        variant="outline"
                        aria-labelledby={`${id}-label`}
                        value={days.map(String)}
                        onValueChange={(next) =>
                            set(
                                spec.name,
                                next.map(Number).sort((a, b) => a - b),
                            )
                        }
                        className="flex-wrap justify-start"
                        disabled={disabled}
                    >
                        {weekFrom(weekStartsOn).map((day) => (
                            <ToggleGroupItem
                                key={day}
                                value={String(day)}
                                aria-label={weekdayName(day, locale, 'long')}
                                className="min-w-12"
                            >
                                {weekdayName(day, locale)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <FieldError id={id} error={error} />
                </div>
            );
        }
        case 'multipliers':
            return (
                <fieldset className="flex flex-col gap-2 sm:col-span-2">
                    <legend className="mb-1.5 text-sm font-medium">{label}</legend>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        {spec.fields.map((name) => (
                            <FieldShell
                                key={name}
                                id={`${id}-${name}`}
                                label={t(`fields.${name}`)}
                                error={errors[name]}
                            >
                                <UnitInput
                                    id={`${id}-${name}`}
                                    unit="multiplier"
                                    step={0.05}
                                    min={1}
                                    max={5}
                                    value={value[name] as number | undefined}
                                    onChange={(next) => set(name, next)}
                                    disabled={disabled}
                                    invalid={Boolean(errors[name])}
                                />
                            </FieldShell>
                        ))}
                    </div>
                </fieldset>
            );
        case 'tenure':
            return (
                <TenureSteps id={id} value={value} set={set} errors={errors} disabled={disabled} />
            );
    }
}

function TenureSteps({
    id,
    value,
    set,
    errors,
    disabled,
}: {
    id: string;
    value: PolicyValue;
    set: (name: string, next: FormDataConvertible) => void;
    errors: Record<string, string>;
    disabled?: boolean;
}) {
    const { t } = useTranslation('policies');
    const steps = Array.isArray(value.tenureSteps)
        ? (value.tenureSteps as Array<{ afterYears: number; entitlement: number }>)
        : [];
    const update = (next: typeof steps) => set('tenureSteps', next);

    return (
        <fieldset className="flex flex-col gap-2 sm:col-span-2">
            <legend className="mb-1.5 text-sm font-medium">{t('fields.tenureSteps')}</legend>
            {steps.map((step, index) => (
                // Steps have no identity of their own; their position is their key.
                <div key={index} className="flex flex-wrap items-end gap-3">
                    <FieldShell
                        id={`${id}-${index}-years`}
                        label={t('fields.afterYears')}
                        error={errors[`tenureSteps.${index}.afterYears`]}
                    >
                        <UnitInput
                            id={`${id}-${index}-years`}
                            unit="years"
                            min={1}
                            max={60}
                            value={step.afterYears}
                            onChange={(next) =>
                                update(
                                    steps.map((s, i) =>
                                        i === index ? { ...s, afterYears: next ?? 1 } : s,
                                    ),
                                )
                            }
                            disabled={disabled}
                        />
                    </FieldShell>
                    <FieldShell
                        id={`${id}-${index}-days`}
                        label={t('fields.entitlement')}
                        error={errors[`tenureSteps.${index}.entitlement`]}
                    >
                        <UnitInput
                            id={`${id}-${index}-days`}
                            unit="days"
                            step={0.5}
                            min={0}
                            max={3650}
                            value={step.entitlement}
                            onChange={(next) =>
                                update(
                                    steps.map((s, i) =>
                                        i === index ? { ...s, entitlement: next ?? 0 } : s,
                                    ),
                                )
                            }
                            disabled={disabled}
                        />
                    </FieldShell>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        aria-label={t('editor.removeStep')}
                        onClick={() => update(steps.filter((_, i) => i !== index))}
                        disabled={disabled}
                    >
                        <X aria-hidden />
                    </Button>
                </div>
            ))}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="self-start"
                onClick={() =>
                    update([
                        ...steps,
                        {
                            afterYears: (steps.at(-1)?.afterYears ?? 0) + 5,
                            entitlement: Number(value.entitlement ?? 0),
                        },
                    ])
                }
                disabled={disabled || steps.length >= 10}
            >
                <Plus aria-hidden />
                {t('editor.addStep')}
            </Button>
        </fieldset>
    );
}

/** A number input whose unit sits at the reading end (handoff 17). Empty means "no limit" when nullable. */
export function UnitInput({
    id,
    unit,
    value,
    onChange,
    step = 1,
    min,
    max,
    nullable,
    disabled,
    invalid,
}: {
    id: string;
    unit: Unit | 'years';
    value: number | null | undefined;
    onChange: (value: number | null) => void;
    step?: number;
    min?: number;
    max?: number;
    nullable?: boolean;
    disabled?: boolean;
    invalid?: boolean;
}) {
    const { t } = useTranslation('policies');

    return (
        <div className="relative w-full max-w-48">
            <Input
                id={id}
                type="number"
                inputMode="decimal"
                step={step}
                min={min}
                max={max}
                value={value ?? ''}
                placeholder={nullable ? t('fields.noLimit') : undefined}
                onChange={(event) => {
                    const raw = event.target.value;
                    onChange(raw === '' ? (nullable ? null : 0) : Number(raw));
                }}
                disabled={disabled}
                aria-invalid={invalid}
                className={cn('pe-14 text-start tabular-nums', unit === 'multiplier' && 'pe-8')}
            />
            <span
                aria-hidden
                className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-small text-muted-foreground"
            >
                {t(`units.${unit}`)}
            </span>
        </div>
    );
}

function FieldShell({
    id,
    label,
    error,
    children,
}: {
    id: string;
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={id}>{label}</Label>
            {children}
            <FieldError id={id} error={error} />
        </div>
    );
}

function FieldError({ id, error }: { id: string; error?: string }) {
    return error ? (
        <p id={`${id}-error`} className="text-caption text-danger">
            {error}
        </p>
    ) : null;
}
