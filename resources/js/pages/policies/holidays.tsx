import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    FileUp,
    List,
    Pencil,
    Plus,
    X,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SwitchField } from '@/components/mw/switch-field';
import { Badge } from '@/components/ui/badge';
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
import { BranchPicker } from '@/features/organization/pickers';
import { ConfirmDialog } from '@/features/policies/confirm-dialog';
import { HolidayCalendarGrid, HolidayList } from '@/features/policies/holiday-calendar-grid';
import { PoliciesTabs } from '@/features/policies/policies-tabs';
import { type HolidayCalendar, type HolidayDay, type HolidayRule } from '@/features/policies/types';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { weekdayName } from '@/lib/regions';
import holidayRoutes from '@/routes/policies/holidays';

type Props = {
    year: number;
    branch: (PickerOption & { weekend: number[] }) | null;
    days: HolidayDay[];
    calendars: HolidayCalendar[];
    presets: Array<{ key: string; name: Record<string, string>; installed: boolean }>;
    can: { configure: boolean };
};

/**
 * Holiday calendar manager (handoff 21): a branch's year (calendar + list on desktop, list on
 * phones), then the shared calendars and rules behind it (D47).
 */
export default function Holidays({ year, branch, days, calendars, presets, can }: Props) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [view, setView] = useState<'calendar' | 'list'>('calendar');
    const [editingCalendar, setEditingCalendar] = useState<HolidayCalendar | 'new' | null>(null);

    const reload = (params: { branch?: string; year?: number }) =>
        router.get(
            holidayRoutes.index.url(),
            { branch: branch?.id, year, ...params },
            { preserveState: true, preserveScroll: true, replace: true },
        );

    return (
        <>
            <Head title={t('holidays.pageTitle')} />
            <PageHeader title={t('title')} description={t('holidays.calendarsHelp')} />
            <PoliciesTabs />

            <section aria-label={t('holidays.pageTitle')} className="mb-10 flex flex-col gap-4">
                <div className="flex flex-wrap items-end gap-3">
                    <BranchPicker
                        value={branch}
                        onChange={(option) => option && reload({ branch: option.id })}
                        label={t('holidays.branch')}
                        className="w-full sm:w-72"
                    />
                    <div
                        className="flex items-center gap-1"
                        role="group"
                        aria-label={t('holidays.year')}
                    >
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t('holidays.previousYear')}
                            onClick={() => reload({ year: year - 1 })}
                        >
                            <DirIcon>
                                <ChevronLeft aria-hidden />
                            </DirIcon>
                        </Button>
                        <span
                            className="min-w-16 text-center text-body font-semibold tabular-nums"
                            aria-live="polite"
                        >
                            {formatNumber(year, locale, { useGrouping: false })}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            aria-label={t('holidays.nextYear')}
                            onClick={() => reload({ year: year + 1 })}
                        >
                            <DirIcon>
                                <ChevronRight aria-hidden />
                            </DirIcon>
                        </Button>
                    </div>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={view}
                        onValueChange={(next) => next && setView(next as 'calendar' | 'list')}
                        className="ms-auto hidden md:flex"
                    >
                        <ToggleGroupItem value="calendar" aria-label={t('holidays.calendarView')}>
                            <CalendarDays aria-hidden />
                        </ToggleGroupItem>
                        <ToggleGroupItem value="list" aria-label={t('holidays.listView')}>
                            <List aria-hidden />
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>

                {!branch ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('holidays.noBranch')}
                    />
                ) : days.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('holidays.none')}
                    />
                ) : (
                    <>
                        <div className={view === 'calendar' ? 'hidden md:block' : 'hidden'}>
                            <HolidayCalendarGrid year={year} days={days} weekend={branch.weekend} />
                        </div>
                        <div className={view === 'calendar' ? 'md:hidden' : ''}>
                            <HolidayList days={days} />
                        </div>
                    </>
                )}
            </section>

            <section aria-labelledby="calendars-title" className="flex flex-col gap-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h2 id="calendars-title" className="text-h4">
                            {t('holidays.calendars')}
                        </h2>
                        <p className="text-small text-text-secondary">
                            {t('holidays.calendarsHelp')}
                        </p>
                    </div>
                    {can.configure ? (
                        <Button onClick={() => setEditingCalendar('new')}>
                            <Plus aria-hidden />
                            {t('holidays.newCalendar')}
                        </Button>
                    ) : null}
                </div>

                {can.configure && presets.some((preset) => !preset.installed) ? (
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-dashed border-border p-4">
                        <span className="text-small font-medium">{t('holidays.presets')}</span>
                        {presets.map((preset) => (
                            <Button
                                key={preset.key}
                                variant="outline"
                                size="sm"
                                disabled={preset.installed}
                                onClick={() =>
                                    router.post(
                                        holidayRoutes.presets.url(),
                                        {
                                            preset: preset.key,
                                            branchIds: branch ? [branch.id] : [],
                                        },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {preset.installed ? null : <Plus aria-hidden />}
                                {localized(preset.name, locale)}
                                {preset.installed ? ` · ${t('holidays.installed')}` : ''}
                            </Button>
                        ))}
                    </div>
                ) : null}

                {calendars.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-schedule"
                        title={t('holidays.emptyCalendars')}
                        description={t('holidays.emptyCalendarsHelp')}
                    />
                ) : (
                    calendars.map((calendar) => (
                        <CalendarCard
                            key={calendar.id}
                            calendar={calendar}
                            canConfigure={can.configure}
                            onEdit={() => setEditingCalendar(calendar)}
                        />
                    ))
                )}
            </section>

            {editingCalendar ? (
                <CalendarDialog
                    calendar={editingCalendar === 'new' ? null : editingCalendar}
                    onClose={() => setEditingCalendar(null)}
                />
            ) : null}
        </>
    );
}

function CalendarCard({
    calendar,
    canConfigure,
    onEdit,
}: {
    calendar: HolidayCalendar;
    canConfigure: boolean;
    onEdit: () => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [editingRule, setEditingRule] = useState<HolidayRule | 'new' | null>(null);
    const [importing, setImporting] = useState(false);
    const [removing, setRemoving] = useState<HolidayRule | null>(null);

    return (
        <article
            aria-labelledby={`calendar-${calendar.id}`}
            className="rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight"
        >
            <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <h3
                        id={`calendar-${calendar.id}`}
                        className="flex flex-wrap items-center gap-2 text-body font-semibold"
                    >
                        {localized(calendar.name, locale)}
                        <bdi dir="ltr" className="text-caption font-normal text-muted-foreground">
                            {calendar.code}
                        </bdi>
                        {!calendar.isActive ? (
                            <Badge variant="outline">{t('common:inactive')}</Badge>
                        ) : null}
                    </h3>
                    <p className="text-caption text-text-secondary">
                        {calendar.branches.length === 0
                            ? t('holidays.noBranches')
                            : `${t('holidays.branches')}: ${calendar.branches.map((b) => localized(b.name, locale)).join(locale === 'ar' ? '، ' : ', ')}`}
                    </p>
                </div>
                {canConfigure ? (
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" onClick={() => setImporting(true)}>
                            <FileUp aria-hidden />
                            {t('holidays.import')}
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setEditingRule('new')}>
                            <Plus aria-hidden />
                            {t('holidays.newRule')}
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label={`${t('holidays.editCalendar')}: ${localized(calendar.name, locale)}`}
                            onClick={onEdit}
                        >
                            <Pencil aria-hidden />
                        </Button>
                    </div>
                ) : null}
            </header>
            {calendar.rules.length === 0 ? (
                <p className="text-small text-muted-foreground">{t('holidays.noRules')}</p>
            ) : (
                <ul className="flex flex-col divide-y divide-border">
                    {calendar.rules.map((rule) => (
                        <li key={rule.id} className="flex flex-wrap items-center gap-3 py-2.5">
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="text-sm">{localized(rule.name, locale)}</span>
                                <span className="text-caption text-muted-foreground">
                                    {ruleSummary(rule, t, locale)}
                                </span>
                            </span>
                            {rule.hijriBased ? (
                                <Badge variant="secondary">{t('holidays.hijriBadge')}</Badge>
                            ) : null}
                            {canConfigure ? (
                                <span className="flex gap-1">
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label={`${t('holidays.editRule')}: ${localized(rule.name, locale)}`}
                                        onClick={() => setEditingRule(rule)}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label={`${t('holidays.removeRule')}: ${localized(rule.name, locale)}`}
                                        onClick={() => setRemoving(rule)}
                                    >
                                        <X aria-hidden />
                                    </Button>
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ul>
            )}
            {editingRule ? (
                <RuleDialog
                    calendar={calendar}
                    rule={editingRule === 'new' ? null : editingRule}
                    onClose={() => setEditingRule(null)}
                />
            ) : null}
            {importing ? (
                <ImportDialog calendar={calendar} onClose={() => setImporting(false)} />
            ) : null}
            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={t('holidays.removeRule')}
                description={removing ? localized(removing.name, locale) : ''}
                confirmLabel={t('common:actions.remove')}
                onConfirm={() =>
                    removing &&
                    router.delete(holidayRoutes.rules.destroy.url([calendar.id, removing.id]), {
                        preserveScroll: true,
                        onFinish: () => setRemoving(null),
                    })
                }
            />
        </article>
    );
}

function monthName(month: number, locale: string): string {
    return formatDate(new Date(Date.UTC(2024, month - 1, 1)), locale as 'en' | 'ar', {
        month: 'long',
        timeZone: 'UTC',
    });
}

function ruleSummary(
    rule: HolidayRule,
    t: (key: string, values?: Record<string, string>) => string,
    locale: 'en' | 'ar',
): string {
    switch (rule.type) {
        case 'fixed':
            return t('holidays.ruleSummary.fixed', {
                date: formatDate(
                    new Date(Date.UTC(2024, (rule.month ?? 1) - 1, rule.day ?? 1)),
                    locale,
                    { day: 'numeric', month: 'long', timeZone: 'UTC' },
                ),
            });
        case 'nth_weekday':
            return t('holidays.ruleSummary.nth_weekday', {
                nth: t(`holidays.nths.${rule.nth}`),
                weekday: weekdayName(rule.weekday ?? 1, locale, 'long'),
                month: monthName(rule.month ?? 1, locale),
            });
        default:
            return t('holidays.ruleSummary.one_off', {
                date: rule.date
                    ? formatDate(rule.date, locale, { dateStyle: 'long', timeZone: 'UTC' })
                    : '',
            });
    }
}

function CalendarDialog({
    calendar,
    onClose,
}: {
    calendar: HolidayCalendar | null;
    onClose: () => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [archiving, setArchiving] = useState(false);
    const [branches, setBranches] = useState<PickerOption[]>(
        (calendar?.branches ?? []).map((b) => ({ id: b.id, label: localized(b.name, locale) })),
    );
    const form = useForm({
        code: calendar?.code ?? '',
        name: { en: calendar?.name.en ?? '', ar: calendar?.name.ar ?? '' },
        countryCode: calendar?.countryCode ?? '',
        isActive: calendar?.isActive ?? true,
        branchIds: [] as string[],
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            countryCode: data.countryCode || null,
            branchIds: branches.map((b) => b.id),
        }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (calendar) {
            form.put(holidayRoutes.update.url(calendar.id), options);
        } else {
            form.post(holidayRoutes.store.url(), options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={calendar ? t('holidays.editCalendar') : t('holidays.newCalendar')}
            footer={
                <>
                    {calendar ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() => setArchiving(true)}
                        >
                            {t('holidays.archiveCalendar')}
                        </Button>
                    ) : null}
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="calendar-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form id="calendar-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="calendar-code" label={t('holidays.code')} error={errors.code} required>
                    <Input
                        id="calendar-code"
                        dir="ltr"
                        value={form.data.code}
                        disabled={Boolean(calendar)}
                        onChange={(event) => form.setData('code', event.target.value.toUpperCase())}
                    />
                </Field>
                <BilingualInput
                    id="calendar-name"
                    label={t('holidays.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <Field
                    id="calendar-country"
                    label={t('holidays.country')}
                    error={errors.countryCode}
                >
                    <Input
                        id="calendar-country"
                        dir="ltr"
                        maxLength={2}
                        className="w-20 uppercase"
                        value={form.data.countryCode}
                        onChange={(event) =>
                            form.setData('countryCode', event.target.value.toUpperCase())
                        }
                    />
                </Field>
                <div className="flex flex-col gap-2">
                    <BranchPicker
                        value={null}
                        onChange={(option) =>
                            option &&
                            !branches.some((b) => b.id === option.id) &&
                            setBranches([...branches, option])
                        }
                        label={t('holidays.branches')}
                    />
                    {branches.length > 0 ? (
                        <ul className="flex flex-wrap gap-1.5">
                            {branches.map((b) => (
                                <li key={b.id}>
                                    <Badge variant="secondary" className="gap-1 pe-1">
                                        {b.label}
                                        <button
                                            type="button"
                                            aria-label={`${t('common:actions.remove')}: ${b.label}`}
                                            className="inline-flex size-5 items-center justify-center rounded-full hover:bg-muted"
                                            onClick={() =>
                                                setBranches(
                                                    branches.filter((other) => other.id !== b.id),
                                                )
                                            }
                                        >
                                            <X aria-hidden className="size-3" />
                                        </button>
                                    </Badge>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                    {errors.branchIds ? (
                        <p className="text-caption text-danger">{errors.branchIds}</p>
                    ) : null}
                </div>
                <SwitchField
                    id="calendar-active"
                    label={t('holidays.active')}
                    checked={form.data.isActive}
                    onCheckedChange={(checked) => form.setData('isActive', checked)}
                />
            </form>
            {calendar ? (
                <ConfirmDialog
                    open={archiving}
                    onOpenChange={setArchiving}
                    title={t('holidays.archiveCalendar')}
                    description={t('holidays.archiveConfirm')}
                    confirmLabel={t('common:actions.archive')}
                    onConfirm={() =>
                        router.delete(holidayRoutes.destroy.url(calendar.id), {
                            preserveScroll: true,
                            onSuccess: onClose,
                        })
                    }
                />
            ) : null}
        </ResponsiveDialog>
    );
}

function RuleDialog({
    calendar,
    rule,
    onClose,
}: {
    calendar: HolidayCalendar;
    rule: HolidayRule | null;
    onClose: () => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const form = useForm({
        name: { en: rule?.name.en ?? '', ar: rule?.name.ar ?? '' },
        type: rule?.type ?? 'fixed',
        month: rule?.month ?? 1,
        day: rule?.day ?? 1,
        weekday: rule?.weekday ?? 1,
        nth: rule?.nth ?? 1,
        date: rule?.date ?? '',
        durationDays: rule?.durationDays ?? 1,
        observed: rule?.observed ?? 'none',
        hijriBased: rule?.hijriBased ?? false,
        fromYear: rule?.fromYear ?? null,
        toYear: rule?.toYear ?? null,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const type = form.data.type;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            month: data.type === 'one_off' ? null : data.month,
            day: data.type === 'fixed' ? data.day : null,
            weekday: data.type === 'nth_weekday' ? data.weekday : null,
            nth: data.type === 'nth_weekday' ? data.nth : null,
            date: data.type === 'one_off' ? data.date : null,
        }));
        const options = { preserveScroll: true, onSuccess: onClose };
        if (rule) {
            form.put(holidayRoutes.rules.update.url([calendar.id, rule.id]), options);
        } else {
            form.post(holidayRoutes.rules.store.url(calendar.id), options);
        }
    };

    const numberInput = (
        id: string,
        field: 'day' | 'durationDays' | 'fromYear' | 'toYear',
        label: string,
        nullable = false,
    ) => (
        <Field id={id} label={label} error={errors[field]}>
            <Input
                id={id}
                type="number"
                dir="ltr"
                className="w-28"
                value={form.data[field] ?? ''}
                onChange={(event) =>
                    form.setData(
                        field,
                        event.target.value === '' && nullable ? null : Number(event.target.value),
                    )
                }
            />
        </Field>
    );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={rule ? t('holidays.editRule') : t('holidays.newRule')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="rule-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form id="rule-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <BilingualInput
                    id="rule-name"
                    label={t('holidays.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <Field id="rule-type" label={t('holidays.type')} error={errors.type}>
                    <Select value={type} onValueChange={(value) => form.setData('type', value)}>
                        <SelectTrigger id="rule-type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(['fixed', 'nth_weekday', 'one_off'] as const).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {t(`holidays.types.${value}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                {type === 'one_off' ? (
                    <Field id="rule-date" label={t('holidays.date')} error={errors.date} required>
                        <Input
                            id="rule-date"
                            type="date"
                            dir="ltr"
                            className="w-44"
                            value={form.data.date}
                            onChange={(event) => form.setData('date', event.target.value)}
                        />
                    </Field>
                ) : (
                    <div className="flex flex-wrap gap-4">
                        {type === 'nth_weekday' ? (
                            <>
                                <Field id="rule-nth" label={t('holidays.nth')} error={errors.nth}>
                                    <Select
                                        value={String(form.data.nth)}
                                        onValueChange={(value) =>
                                            form.setData('nth', Number(value))
                                        }
                                    >
                                        <SelectTrigger id="rule-nth" className="w-32">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {['1', '2', '3', '4', '5', '-1'].map((value) => (
                                                <SelectItem key={value} value={value}>
                                                    {t(`holidays.nths.${value}`)}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    id="rule-weekday"
                                    label={t('holidays.weekday')}
                                    error={errors.weekday}
                                >
                                    <Select
                                        value={String(form.data.weekday)}
                                        onValueChange={(value) =>
                                            form.setData('weekday', Number(value))
                                        }
                                    >
                                        <SelectTrigger id="rule-weekday" className="w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[1, 2, 3, 4, 5, 6, 7].map((day) => (
                                                <SelectItem key={day} value={String(day)}>
                                                    {weekdayName(day, locale, 'long')}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </>
                        ) : null}
                        <Field id="rule-month" label={t('holidays.month')} error={errors.month}>
                            <Select
                                value={String(form.data.month)}
                                onValueChange={(value) => form.setData('month', Number(value))}
                            >
                                <SelectTrigger id="rule-month" className="w-40">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {Array.from({ length: 12 }, (_, i) => i + 1).map((month) => (
                                        <SelectItem key={month} value={String(month)}>
                                            {monthName(month, locale)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        {type === 'fixed'
                            ? numberInput('rule-day', 'day', t('holidays.day'))
                            : null}
                    </div>
                )}
                {numberInput('rule-duration', 'durationDays', t('holidays.duration'))}
                <Field
                    id="rule-observed"
                    label={t('holidays.observedRule')}
                    error={errors.observed}
                >
                    <Select
                        value={form.data.observed}
                        onValueChange={(value) => form.setData('observed', value)}
                    >
                        <SelectTrigger id="rule-observed">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {(
                                [
                                    'none',
                                    'nearest_weekday',
                                    'next_working_day',
                                    'previous_working_day',
                                ] as const
                            ).map((value) => (
                                <SelectItem key={value} value={value}>
                                    {t(`holidays.observedRules.${value}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                {type === 'one_off' ? (
                    <SwitchField
                        id="rule-hijri"
                        label={t('holidays.hijri')}
                        checked={form.data.hijriBased}
                        onCheckedChange={(checked) => form.setData('hijriBased', checked)}
                    />
                ) : (
                    <div className="flex flex-wrap gap-4">
                        {numberInput('rule-from', 'fromYear', t('holidays.fromYear'), true)}
                        {numberInput('rule-to', 'toYear', t('holidays.toYear'), true)}
                    </div>
                )}
            </form>
        </ResponsiveDialog>
    );
}

function ImportDialog({ calendar, onClose }: { calendar: HolidayCalendar; onClose: () => void }) {
    const { t } = useTranslation('policies');
    const form = useForm<{ file: File | null }>({ file: null });
    const errors = form.errors as Partial<Record<string, string>>;
    const rowErrors = Object.entries(errors).filter(([key]) => key.startsWith('rows.'));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(holidayRoutes.import.url(calendar.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('holidays.importTitle')}
            description={t('holidays.importHelp')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="submit"
                        form="holiday-import-form"
                        loading={form.processing}
                        disabled={!form.data.file}
                    >
                        {t('common:actions.upload')}
                    </Button>
                </>
            }
        >
            <form
                id="holiday-import-form"
                onSubmit={submit}
                className="flex flex-col gap-4"
                noValidate
            >
                {errors.error ? (
                    <div
                        role="alert"
                        className="flex flex-col gap-1 rounded-md bg-danger-bg p-3 text-small text-danger"
                    >
                        <p>{errors.error}</p>
                        {rowErrors.length > 0 ? (
                            <ul className="list-disc ps-5">
                                {rowErrors.map(([key, message]) => (
                                    <li key={key}>
                                        <bdi dir="ltr">{key.replace('rows.', '#')}</bdi>: {message}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                ) : null}
                <Field
                    id="holiday-file"
                    label={t('holidays.importFile')}
                    error={errors.file}
                    required
                >
                    <Input
                        id="holiday-file"
                        type="file"
                        accept=".csv,.xlsx,text/csv"
                        onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                    />
                </Field>
            </form>
        </ResponsiveDialog>
    );
}
