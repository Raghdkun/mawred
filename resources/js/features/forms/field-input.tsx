import { Paperclip, X } from 'lucide-react';
import { useId, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { BranchPicker, DepartmentPicker, EmployeePicker } from '@/features/organization/pickers';
import { useFormLocale } from './form-locale';
import { formatHijri, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import {
    type DateRangeValue,
    FILE_ACCEPT,
    type FormField,
    type MoneyValue,
    type StoredFile,
    textOf,
} from './schema';
import { minorDigits, uploadFile } from './upload';

type ApiLeaveType = { id: string; code: string; name: Record<string, string> };

type Props = {
    field: FormField;
    id: string;
    value: unknown;
    onChange: (value: unknown) => void;
    invalid: boolean;
    describedBy?: string;
    /** Currency for money fields without a fixed one (the requester's branch). */
    defaultCurrency?: string | null;
    uploadUrl: string;
    disabled?: boolean;
};

/** One field's input, by MFS type (D51). Labels and errors are drawn by the renderer. */
export function FieldInput({
    field,
    id,
    value,
    onChange,
    invalid,
    describedBy,
    defaultCurrency,
    uploadUrl,
    disabled,
}: Props) {
    const { t } = useTranslation('forms');
    const locale = useFormLocale();
    const [pickerLabel, setPickerLabel] = useState<string | null>(null);
    const placeholder = textOf(field.placeholder, locale) || undefined;
    const common = {
        id,
        disabled,
        'aria-invalid': invalid || undefined,
        'aria-describedby': describedBy,
    };
    const options = field.options ?? [];

    switch (field.type) {
        case 'text':
        case 'email':
            return (
                <Input
                    {...common}
                    type={field.type === 'email' ? 'email' : 'text'}
                    dir={field.type === 'email' ? 'ltr' : undefined}
                    value={String(value ?? '')}
                    placeholder={placeholder}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'textarea':
            return (
                <Textarea
                    {...common}
                    rows={4}
                    value={String(value ?? '')}
                    placeholder={placeholder}
                    onChange={(event) => onChange(event.target.value)}
                />
            );
        case 'number':
            return (
                <Input
                    {...common}
                    type="number"
                    inputMode="decimal"
                    className="w-48 tabular-nums"
                    value={value === null || value === undefined ? '' : String(value)}
                    placeholder={placeholder}
                    onChange={(event) =>
                        onChange(event.target.value === '' ? null : Number(event.target.value))
                    }
                />
            );
        case 'money': {
            const currency = field.currency ?? defaultCurrency ?? 'USD';
            const digits = minorDigits(currency);
            const money = value as MoneyValue | null;

            return (
                <div className="relative w-56">
                    <Input
                        {...common}
                        type="number"
                        inputMode="decimal"
                        step={1 / 10 ** digits}
                        className="pe-14 tabular-nums"
                        value={money ? money.amount_minor / 10 ** digits : ''}
                        onChange={(event) =>
                            onChange(
                                event.target.value === ''
                                    ? null
                                    : {
                                          amount_minor: Math.round(
                                              Number(event.target.value) * 10 ** digits,
                                          ),
                                          currency,
                                      },
                            )
                        }
                    />
                    <span
                        aria-hidden
                        className="pointer-events-none absolute inset-y-0 end-3 flex items-center text-small text-muted-foreground"
                    >
                        {currency}
                    </span>
                </div>
            );
        }
        case 'phone':
            return (
                <Input
                    {...common}
                    type="tel"
                    dir="ltr"
                    inputMode="tel"
                    autoComplete="tel"
                    className="w-56"
                    value={String(value ?? '')}
                    placeholder={placeholder ?? '+966…'}
                    onChange={(event) => onChange(event.target.value.replace(/[\s()-]/g, ''))}
                />
            );
        case 'date':
            return (
                <DateInput
                    {...common}
                    value={(value as string | null) ?? ''}
                    onChange={(next) => onChange(next || null)}
                />
            );
        case 'date_range': {
            const range = (value as DateRangeValue | null) ?? { from: null, to: null };

            return (
                <div className="flex flex-wrap items-start gap-3">
                    <div className="flex flex-col gap-1">
                        <Label
                            htmlFor={`${id}-from`}
                            className="text-caption text-muted-foreground"
                        >
                            {t('renderer.from')}
                        </Label>
                        <DateInput
                            {...common}
                            id={`${id}-from`}
                            value={range.from ?? ''}
                            onChange={(from) => onChange({ ...range, from: from || null })}
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor={`${id}-to`} className="text-caption text-muted-foreground">
                            {t('renderer.to')}
                        </Label>
                        <DateInput
                            {...common}
                            id={`${id}-to`}
                            value={range.to ?? ''}
                            onChange={(to) => onChange({ ...range, to: to || null })}
                        />
                    </div>
                </div>
            );
        }
        case 'time':
            return (
                <Input
                    {...common}
                    type="time"
                    dir="ltr"
                    className="w-36"
                    value={String(value ?? '')}
                    onChange={(event) => onChange(event.target.value || null)}
                />
            );
        case 'select':
            return (
                <Select
                    value={(value as string | null) ?? ''}
                    onValueChange={onChange}
                    disabled={disabled}
                >
                    <SelectTrigger
                        id={id}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        className="w-full sm:w-80"
                    >
                        <SelectValue placeholder={placeholder ?? t('renderer.choose')} />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {textOf(option.label, locale)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            );
        case 'radio':
            return (
                <RadioGroup
                    value={(value as string | null) ?? ''}
                    onValueChange={onChange}
                    disabled={disabled}
                    aria-invalid={invalid || undefined}
                    aria-describedby={describedBy}
                    aria-labelledby={`${id}-label`}
                    className="gap-2"
                >
                    {options.map((option) => (
                        <div key={option.value} className="flex min-h-11 items-center gap-2.5">
                            <RadioGroupItem id={`${id}-${option.value}`} value={option.value} />
                            <Label htmlFor={`${id}-${option.value}`} className="font-normal">
                                {textOf(option.label, locale)}
                            </Label>
                        </div>
                    ))}
                </RadioGroup>
            );
        case 'multiselect':
        case 'checkboxes': {
            const selected = Array.isArray(value) ? (value as string[]) : [];

            return (
                <div
                    role="group"
                    aria-labelledby={`${id}-label`}
                    aria-describedby={describedBy}
                    className="flex flex-col gap-2"
                >
                    {options.map((option) => (
                        <div key={option.value} className="flex min-h-11 items-center gap-2.5">
                            <Checkbox
                                id={`${id}-${option.value}`}
                                checked={selected.includes(option.value)}
                                disabled={disabled}
                                onCheckedChange={(checked) =>
                                    onChange(
                                        checked
                                            ? [...selected, option.value]
                                            : selected.filter((item) => item !== option.value),
                                    )
                                }
                            />
                            <Label htmlFor={`${id}-${option.value}`} className="font-normal">
                                {textOf(option.label, locale)}
                            </Label>
                        </div>
                    ))}
                </div>
            );
        }
        case 'boolean':
            return (
                <div className="flex min-h-11 items-center gap-3">
                    <Switch
                        id={id}
                        checked={value === true}
                        onCheckedChange={onChange}
                        disabled={disabled}
                        aria-invalid={invalid || undefined}
                        aria-describedby={describedBy}
                        aria-labelledby={`${id}-label`}
                    />
                </div>
            );
        case 'file':
            return (
                <FileInput
                    field={field}
                    id={id}
                    value={(value as StoredFile[] | null) ?? []}
                    onChange={onChange}
                    uploadUrl={uploadUrl}
                    describedBy={describedBy}
                    disabled={disabled}
                />
            );
        case 'employee':
        case 'department':
        case 'branch':
        case 'leave_type': {
            const pickerValue: PickerOption | null = value
                ? { id: String(value), label: pickerLabel ?? String(value) }
                : null;
            const pickerProps = {
                value: pickerValue,
                onChange: (option: PickerOption | null) => {
                    setPickerLabel(option?.label ?? null);
                    onChange(option?.id ?? null);
                },
                label: textOf(field.label, locale),
                className: 'w-full sm:w-80',
            };

            if (field.type === 'employee') return <EmployeePicker {...pickerProps} />;
            if (field.type === 'department') return <DepartmentPicker {...pickerProps} />;
            if (field.type === 'branch') return <BranchPicker {...pickerProps} />;

            return (
                <AsyncPicker<ApiLeaveType>
                    endpoint="leave-types"
                    queryKey="picker-leave-types"
                    toOption={(type) => ({
                        id: type.code,
                        label: localized(type.name, locale),
                        description: type.code,
                    })}
                    placeholder={t('renderer.choose')}
                    {...pickerProps}
                />
            );
        }
        default:
            return null;
    }
}

function DateInput(props: {
    id: string;
    value: string;
    onChange: (value: string) => void;
    disabled?: boolean;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
}) {
    const locale = useFormLocale();
    const { onChange, value, ...rest } = props;

    return (
        <div className="flex flex-col gap-1">
            <Input
                {...rest}
                type="date"
                dir="ltr"
                className="w-44"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
            {value ? (
                <span className="text-caption text-muted-foreground">
                    {formatHijri(`${value}T12:00:00Z`, locale)}
                </span>
            ) : null}
        </div>
    );
}

function FileInput({
    field,
    id,
    value,
    onChange,
    uploadUrl,
    describedBy,
    disabled,
}: {
    field: FormField;
    id: string;
    value: StoredFile[];
    onChange: (value: unknown) => void;
    uploadUrl: string;
    describedBy?: string;
    disabled?: boolean;
}) {
    const { t } = useTranslation('forms');
    const locale = useFormLocale();
    const hintId = useId();
    const [progress, setProgress] = useState<number | null>(null);
    const [error, setError] = useState<string | null>(null);
    const max = field.validation?.maxFiles ?? 1;
    const kinds = field.validation?.accept ?? (['pdf', 'image', 'word', 'excel'] as const);
    const accept = kinds.flatMap((kind) => FILE_ACCEPT[kind] ?? []).join(',');

    const pick = async (files: FileList | null) => {
        const file = files?.[0];

        if (!file) return;

        setError(null);
        setProgress(0);

        try {
            const stored = await uploadFile(uploadUrl, field.key, file, setProgress);
            onChange([...value, stored]);
        } catch (failure) {
            setError(failure instanceof Error ? failure.message : t('renderer.uploadFailed'));
        } finally {
            setProgress(null);
        }
    };

    return (
        <div className="flex flex-col gap-2">
            {value.length > 0 ? (
                <ul className="flex flex-col gap-1.5">
                    {value.map((file) => (
                        <li
                            key={file.id}
                            className="flex items-center gap-2 rounded-md border border-border px-3 py-2 text-small"
                        >
                            <Paperclip
                                aria-hidden
                                className="size-4 shrink-0 text-muted-foreground"
                            />
                            <bdi className="min-w-0 flex-1 truncate">{file.name}</bdi>
                            <span className="text-caption text-muted-foreground tabular-nums">
                                {formatNumber(Math.ceil(file.size / 1024), locale)} KB
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label={`${t('renderer.removeFile')}: ${file.name}`}
                                onClick={() =>
                                    onChange(value.filter((other) => other.id !== file.id))
                                }
                                disabled={disabled}
                            >
                                <X aria-hidden />
                            </Button>
                        </li>
                    ))}
                </ul>
            ) : null}
            {value.length < max ? (
                <Input
                    id={id}
                    type="file"
                    accept={accept}
                    aria-labelledby={`${id}-label`}
                    aria-describedby={[describedBy, hintId].filter(Boolean).join(' ')}
                    disabled={disabled || progress !== null}
                    onChange={(event) => {
                        void pick(event.target.files);
                        event.target.value = '';
                    }}
                />
            ) : null}
            {progress !== null ? (
                <Progress value={progress} aria-label={t('renderer.uploading')} />
            ) : null}
            <p id={hintId} className="text-caption text-muted-foreground">
                {t('renderer.fileHint', {
                    count: max,
                    size: formatNumber(
                        Math.round((field.validation?.maxSizeKb ?? 10240) / 1024),
                        locale,
                    ),
                })}
            </p>
            {error ? (
                <p role="alert" className="text-caption text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
