import { useForm } from '@inertiajs/react';
import { type FormEvent, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { Field } from '@/components/mw/field';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import {
    COUNTRY_CENTERS,
    COUNTRY_CODES,
    COUNTRY_CURRENCY,
    PRIORITY_COUNTRIES,
    countryName,
    currencies,
    timeZones,
    weekdayName,
} from '@/lib/regions';
import { type Geofence, GeofenceMap } from './geofence-map';

type Branch = App.Domain.Organization.Data.BranchData;
type CostCenter = App.Domain.Organization.Data.CatalogEntryData;

const NONE = '__none';

export type BranchFormData = {
    code: string;
    name: { en: string; ar: string };
    countryCode: string;
    timezone: string;
    defaultCurrency: string;
    phoneRegion: string;
    weekendDays: number[];
    workStartsAt: string;
    workEndsAt: string;
    address: { line1: string; line2: string; city: string; postal_code: string };
    geofence: Geofence | null;
    costCenterId: string | null;
    isActive: boolean;
};

export function branchFormData(branch: Branch | null): BranchFormData {
    return {
        code: branch?.code ?? '',
        name: { en: branch?.name.en ?? '', ar: branch?.name.ar ?? '' },
        countryCode: branch?.countryCode ?? 'SA',
        timezone: branch?.timezone ?? 'Asia/Riyadh',
        defaultCurrency: branch?.defaultCurrency ?? 'SAR',
        phoneRegion: branch?.phoneRegion ?? 'SA',
        weekendDays: branch?.weekendDays ?? [5, 6],
        workStartsAt: branch?.workStartsAt ?? '09:00',
        workEndsAt: branch?.workEndsAt ?? '17:00',
        address: {
            line1: branch?.address?.line1 ?? '',
            line2: branch?.address?.line2 ?? '',
            city: branch?.address?.city ?? '',
            postal_code: branch?.address?.postal_code ?? '',
        },
        geofence: branch?.geofence ?? null,
        costCenterId: branch?.costCenterId ?? null,
        isActive: branch?.isActive ?? true,
    };
}

/**
 * Branch create / edit (screen 39) and setup step 4. Picking a country fills sensible defaults
 * for its time zone, currency and phone region; everything stays editable.
 */
export function BranchForm({
    branch,
    action,
    method,
    costCenters,
    submitLabel,
    onSuccess,
    secondaryAction,
}: {
    branch: Branch | null;
    action: string;
    method: 'post' | 'put';
    costCenters: CostCenter[];
    submitLabel: string;
    onSuccess?: () => void;
    secondaryAction?: React.ReactNode;
}) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();
    const form = useForm<BranchFormData>(branchFormData(branch));
    const errors = form.errors as Partial<Record<string, string>>;

    const countries = useMemo(() => {
        const rest = COUNTRY_CODES.filter((code) => !PRIORITY_COUNTRIES.includes(code)).sort(
            (a, b) => countryName(a, locale).localeCompare(countryName(b, locale), locale),
        );

        return [...PRIORITY_COUNTRIES, ...rest];
    }, [locale]);
    const zones = useMemo(() => timeZones(), []);
    const money = useMemo(() => currencies(), []);

    const pickCountry = (code: string) =>
        form.setData((data) => ({
            ...data,
            countryCode: code,
            phoneRegion: code,
            defaultCurrency: COUNTRY_CURRENCY[code] ?? data.defaultCurrency,
            timezone: defaultZone(code) ?? data.timezone,
        }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            address: Object.values(data.address).some(Boolean) ? data.address : null,
        }));
        form.submit(method, action, { preserveScroll: true, onSuccess: () => onSuccess?.() });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6" noValidate>
            {'error' in errors && errors.error ? (
                <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}

            <div className="grid gap-4 sm:grid-cols-[10rem_1fr]">
                <Field
                    id="branch-code"
                    label={t('fields.code')}
                    error={errors.code}
                    required
                    hint={t('hints.code')}
                >
                    <Input
                        id="branch-code"
                        dir="ltr"
                        value={form.data.code}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                        maxLength={32}
                        required
                    />
                </Field>
                <Field
                    id="branch-cost-center"
                    label={t('fields.costCenter')}
                    error={errors.costCenterId}
                >
                    <Select
                        value={form.data.costCenterId ?? NONE}
                        onValueChange={(value) =>
                            form.setData('costCenterId', value === NONE ? null : value)
                        }
                    >
                        <SelectTrigger id="branch-cost-center">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>{t('common:none')}</SelectItem>
                            {costCenters.map((center) => (
                                <SelectItem key={center.id} value={center.id}>
                                    {localized(center.name, locale)} · {center.code}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            </div>

            <BilingualInput
                id="branch-name"
                label={t('fields.name')}
                value={form.data.name}
                onChange={(name) => form.setData('name', name)}
                errors={errors}
                errorKey="name"
            />

            <fieldset className="grid gap-4 sm:grid-cols-2">
                <legend className="mb-3 text-body font-semibold text-foreground">
                    {t('sections.region')}
                </legend>
                <Field
                    id="branch-country"
                    label={t('fields.country')}
                    error={errors.countryCode}
                    required
                >
                    <Select value={form.data.countryCode} onValueChange={pickCountry}>
                        <SelectTrigger id="branch-country">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="max-h-80">
                            {countries.map((code) => (
                                <SelectItem key={code} value={code}>
                                    {countryName(code, locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    id="branch-timezone"
                    label={t('fields.timezone')}
                    error={errors.timezone}
                    required
                >
                    <Select
                        value={form.data.timezone}
                        onValueChange={(value) => form.setData('timezone', value)}
                    >
                        <SelectTrigger id="branch-timezone" dir="ltr">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="max-h-80" dir="ltr">
                            {zones.map((zone) => (
                                <SelectItem key={zone} value={zone}>
                                    {zone}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    id="branch-currency"
                    label={t('fields.currency')}
                    error={errors.defaultCurrency}
                    required
                >
                    <Select
                        value={form.data.defaultCurrency}
                        onValueChange={(value) => form.setData('defaultCurrency', value)}
                    >
                        <SelectTrigger id="branch-currency" dir="ltr">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent className="max-h-80" dir="ltr">
                            {money.map((code) => (
                                <SelectItem key={code} value={code}>
                                    {code}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field id="branch-city" label={t('fields.city')} error={errors['address.city']}>
                    <Input
                        id="branch-city"
                        value={form.data.address.city}
                        onChange={(event) =>
                            form.setData('address', {
                                ...form.data.address,
                                city: event.target.value,
                            })
                        }
                    />
                </Field>
            </fieldset>

            <fieldset className="flex flex-col gap-4">
                <legend className="mb-3 text-body font-semibold text-foreground">
                    {t('sections.workWeek')}
                </legend>
                <div className="flex flex-col gap-1.5">
                    <span id="branch-weekend-label" className="text-sm font-medium">
                        {t('fields.weekend')}
                    </span>
                    <ToggleGroup
                        type="multiple"
                        variant="outline"
                        aria-labelledby="branch-weekend-label"
                        value={form.data.weekendDays.map(String)}
                        onValueChange={(days) =>
                            form.setData('weekendDays', days.map(Number).sort())
                        }
                        className="flex-wrap justify-start"
                    >
                        {[1, 2, 3, 4, 5, 6, 7].map((day) => (
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
                    {errors.weekendDays ? (
                        <p className="text-caption text-danger">{errors.weekendDays}</p>
                    ) : null}
                </div>
                <div className="grid grid-cols-2 gap-4 sm:max-w-sm">
                    <Field
                        id="branch-starts"
                        label={t('fields.startsAt')}
                        error={errors.workStartsAt}
                        required
                    >
                        <Input
                            id="branch-starts"
                            type="time"
                            dir="ltr"
                            value={form.data.workStartsAt}
                            onChange={(event) => form.setData('workStartsAt', event.target.value)}
                        />
                    </Field>
                    <Field
                        id="branch-ends"
                        label={t('fields.endsAt')}
                        error={errors.workEndsAt}
                        required
                    >
                        <Input
                            id="branch-ends"
                            type="time"
                            dir="ltr"
                            value={form.data.workEndsAt}
                            onChange={(event) => form.setData('workEndsAt', event.target.value)}
                        />
                    </Field>
                </div>
            </fieldset>

            <fieldset className="flex flex-col gap-3">
                <legend className="mb-1 text-body font-semibold text-foreground">
                    {t('sections.geofence')}
                </legend>
                <p className="text-small text-muted-foreground">{t('geofence.help')}</p>
                <GeofenceMap
                    value={form.data.geofence}
                    onChange={(geofence) => form.setData('geofence', geofence)}
                    fallbackCenter={COUNTRY_CENTERS[form.data.countryCode]}
                />
                {form.data.geofence ? (
                    <div className="flex flex-wrap items-center gap-4">
                        <label htmlFor="branch-radius" className="text-sm font-medium">
                            {t('fields.radius')}
                        </label>
                        <input
                            id="branch-radius"
                            type="range"
                            min={25}
                            max={2000}
                            step={25}
                            value={form.data.geofence.radiusM}
                            onChange={(event) =>
                                form.setData('geofence', {
                                    ...(form.data.geofence as Geofence),
                                    radiusM: Number(event.target.value),
                                })
                            }
                            className="w-full max-w-xs accent-primary"
                            aria-valuetext={t('geofence.metres', {
                                count: form.data.geofence.radiusM,
                            })}
                        />
                        <span className="text-small text-muted-foreground tabular-nums">
                            {t('geofence.metres', { count: form.data.geofence.radiusM })}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => form.setData('geofence', null)}
                        >
                            {t('geofence.clear')}
                        </Button>
                    </div>
                ) : (
                    <p className="text-caption text-muted-foreground">{t('geofence.none')}</p>
                )}
                {errors['geofence.lat'] || errors['geofence.radiusM'] ? (
                    <p className="text-caption text-danger">
                        {errors['geofence.lat'] ?? errors['geofence.radiusM']}
                    </p>
                ) : null}
            </fieldset>

            <SwitchField
                id="fields-active"
                label={t('fields.active')}
                hint={t('hints.active')}
                checked={form.data.isActive}
                onCheckedChange={(checked) => form.setData('isActive', checked)}
            />

            <div className="flex flex-wrap justify-end gap-2">
                {secondaryAction}
                <Button type="submit" loading={form.processing}>
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}

/** The main time zone for a country, when there is an obvious one. */
function defaultZone(country: string): string | null {
    const zones: Record<string, string> = {
        SA: 'Asia/Riyadh',
        AE: 'Asia/Dubai',
        KW: 'Asia/Kuwait',
        QA: 'Asia/Qatar',
        BH: 'Asia/Bahrain',
        OM: 'Asia/Muscat',
        EG: 'Africa/Cairo',
        JO: 'Asia/Amman',
        GB: 'Europe/London',
    };

    return zones[country] ?? null;
}
