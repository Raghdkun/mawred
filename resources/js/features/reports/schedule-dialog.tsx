import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import reports from '@/routes/reports';
import { type FilterValues } from './filter-bar';

const FREQUENCIES = ['daily', 'weekly', 'monthly'] as const;
const RANGES: App.Domain.Reports.Enums.PeriodRange[] = [
    'last_month',
    'this_month',
    'last_7_days',
    'last_30_days',
    'this_year',
];
const KEPT = ['branch', 'department', 'days', 'year', 'leave_type', 'log'];

/** Weekday names in the viewer's language, ISO order (Monday = 1). */
export function weekdayName(day: number, locale: 'en' | 'ar'): string {
    // 2024-01-01 was a Monday.
    return formatDate(new Date(Date.UTC(2024, 0, day)), locale, {
        weekday: 'long',
        timeZone: 'UTC',
    });
}

/** An hour of the day, e.g. 7:00 AM, in the viewer's language. */
export function hourName(hour: number, locale: 'en' | 'ar'): string {
    return formatDate(new Date(Date.UTC(2024, 0, 1, hour)), locale, {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'UTC',
    });
}

/**
 * Schedule a report (D120): how often, when, which file, and for reports with a period, which
 * period each run covers. The other filters on screen go with it.
 */
export function ScheduleDialog({
    reportKey,
    values,
    hasPeriod,
    formats,
    open,
    onOpenChange,
}: {
    reportKey: string;
    values: FilterValues;
    hasPeriod: boolean;
    formats: App.Domain.Reports.Enums.ReportFormat[];
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('reports');
    const { locale, dir } = useLocale();
    const [frequency, setFrequency] = useState<(typeof FREQUENCIES)[number]>('weekly');
    const [weekday, setWeekday] = useState('7');
    const [monthDay, setMonthDay] = useState('1');
    const [hour, setHour] = useState('7');
    const [format, setFormat] = useState<string>('xlsx');
    const [range, setRange] = useState<string>('last_month');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);

    const save = () => {
        const params: Record<string, string | number> = {};

        for (const key of KEPT) {
            const value = values[key];

            if (value !== null && value !== undefined && value !== '') {
                params[key] = value;
            }
        }

        if (hasPeriod) {
            params.range = range;
        }

        setSaving(true);
        router.post(
            reports.schedules.store.url(),
            {
                report: reportKey,
                format,
                frequency,
                weekday: frequency === 'weekly' ? Number(weekday) : null,
                monthDay: frequency === 'monthly' ? Number(monthDay) : null,
                hour: Number(hour),
                params,
            },
            { onError: setErrors, onFinish: () => setSaving(false) },
        );
    };

    const select = (
        id: string,
        label: string,
        value: string,
        onChange: (v: string) => void,
        options: Array<{ value: string; label: string }>,
        error?: string,
    ) => (
        <Field id={id} label={label} error={error}>
            <Select dir={dir} value={value} onValueChange={onChange}>
                <SelectTrigger id={id} className="min-h-11 w-full" data-schedule-field={id}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((o) => (
                        <SelectItem key={o.value} value={o.value}>
                            {o.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </Field>
    );

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('schedule.title')}
            description={t('schedule.help')}
            footer={
                <Button className="min-h-11" onClick={save} disabled={saving} data-save-schedule>
                    {t('schedule.save')}
                </Button>
            }
        >
            <div className="flex flex-col gap-4">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <div className="grid gap-4 sm:grid-cols-2">
                    {select(
                        'schedule-frequency',
                        t('schedule.frequency'),
                        frequency,
                        (v) => setFrequency(v as typeof frequency),
                        FREQUENCIES.map((f) => ({
                            value: f,
                            label: t(`schedule.frequencies.${f}`),
                        })),
                        errors.frequency,
                    )}
                    {frequency === 'weekly'
                        ? select(
                              'schedule-weekday',
                              t('schedule.weekday'),
                              weekday,
                              setWeekday,
                              [1, 2, 3, 4, 5, 6, 7].map((d) => ({
                                  value: String(d),
                                  label: weekdayName(d, locale),
                              })),
                              errors.weekday,
                          )
                        : null}
                    {frequency === 'monthly'
                        ? select(
                              'schedule-month-day',
                              t('schedule.monthDay'),
                              monthDay,
                              setMonthDay,
                              Array.from({ length: 28 }, (_, i) => ({
                                  value: String(i + 1),
                                  label: String(i + 1),
                              })),
                              errors.monthDay,
                          )
                        : null}
                    {select(
                        'schedule-hour',
                        t('schedule.hour'),
                        hour,
                        setHour,
                        Array.from({ length: 24 }, (_, h) => ({
                            value: String(h),
                            label: hourName(h, locale),
                        })),
                        errors.hour,
                    )}
                    {select(
                        'schedule-format',
                        t('schedule.format'),
                        format,
                        setFormat,
                        formats.map((f) => ({ value: f, label: t(`export.${f}`) })),
                        errors.format,
                    )}
                    {hasPeriod
                        ? select(
                              'schedule-range',
                              t('schedule.range'),
                              range,
                              setRange,
                              RANGES.map((r) => ({ value: r, label: t(`schedule.ranges.${r}`) })),
                              errors.params,
                          )
                        : null}
                </div>
                {hasPeriod ? (
                    <p className="text-caption text-text-secondary">{t('schedule.rangeHelp')}</p>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
