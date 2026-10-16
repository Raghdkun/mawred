import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { type Person, PeoplePicker } from '@/features/calendar/people-picker';
import { toInstant } from '@/features/calendar/time';
import hiring from '@/routes/hiring';

const DURATIONS = [15, 30, 45, 60, 90, 120];

/**
 * Plans an interview (D89, D92): the panel (any colleague), length and place, then either a
 * booking link for the candidate to pick a time the whole panel is free, or a set time.
 */
export function InterviewDialog({
    applicationId,
    timezone,
    onClose,
}: {
    applicationId: string;
    timezone: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [title, setTitle] = useState(t('interviews.defaultTitle'));
    const [duration, setDuration] = useState(30);
    const [mode, setMode] = useState('onsite');
    const [location, setLocation] = useState('');
    const [panel, setPanel] = useState<Person[]>([]);
    const [how, setHow] = useState<'link' | 'time'>('link');
    const [date, setDate] = useState('');
    const [time, setTime] = useState('10:00');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const submit = () => {
        setSending(true);
        router.post(
            hiring.interviews.store.url(applicationId),
            {
                title,
                durationMinutes: duration,
                mode,
                location: location || null,
                panel: panel.map((p) => p.id),
                timezone,
                startsAt: how === 'time' && date ? toInstant(date, time, timezone) : null,
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: onClose,
                onFinish: () => setSending(false),
            },
        );
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('interviews.planTitle')}
            description={t('interviews.planDescription')}
            className="sm:max-w-xl"
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={sending || panel.length === 0}
                        onClick={submit}
                        data-save-interview
                    >
                        {how === 'link' ? t('interviews.sendLink') : t('interviews.book')}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="interview-title" label={t('interviews.title')} error={errors.title}>
                    <Input
                        id="interview-title"
                        value={title}
                        maxLength={160}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </Field>
                <PeoplePicker label={t('interviews.panel')} value={panel} onChange={setPanel} />
                {errors.panel ? (
                    <p role="alert" className="text-caption text-danger">
                        {errors.panel}
                    </p>
                ) : null}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="interview-duration" label={t('interviews.duration')}>
                        <Select
                            value={String(duration)}
                            onValueChange={(v) => setDuration(Number(v))}
                        >
                            <SelectTrigger id="interview-duration" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {DURATIONS.map((m) => (
                                    <SelectItem key={m} value={String(m)}>
                                        {t('interviews.minutes', { count: m })}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field id="interview-mode" label={t('interviews.mode')}>
                        <Select value={mode} onValueChange={setMode}>
                            <SelectTrigger id="interview-mode" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {(['onsite', 'video', 'phone'] as const).map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {t(`interviewMode.${m}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                </div>
                <Field
                    id="interview-location"
                    label={t('interviews.location')}
                    hint={t('interviews.locationHint')}
                >
                    <Input
                        id="interview-location"
                        value={location}
                        maxLength={200}
                        onChange={(e) => setLocation(e.target.value)}
                    />
                </Field>
                <fieldset className="flex flex-col gap-2">
                    <legend className="mb-1 text-small font-medium">{t('interviews.when')}</legend>
                    {(['link', 'time'] as const).map((option) => (
                        <div
                            key={option}
                            className="flex min-h-11 items-start gap-2 rounded-md border border-border p-3 text-small"
                        >
                            <input
                                id={`interview-how-${option}`}
                                type="radio"
                                name="interview-how"
                                value={option}
                                checked={how === option}
                                onChange={() => setHow(option)}
                                aria-describedby={`interview-how-${option}-help`}
                                className="mt-1 size-4 accent-primary"
                            />
                            <span className="flex flex-col">
                                <label htmlFor={`interview-how-${option}`} className="font-medium">
                                    {t(`interviews.how.${option}`)}
                                </label>
                                <span
                                    id={`interview-how-${option}-help`}
                                    className="text-caption text-text-secondary"
                                >
                                    {t(`interviews.how.${option}Help`)}
                                </span>
                            </span>
                        </div>
                    ))}
                </fieldset>
                {how === 'time' ? (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="interview-date"
                            label={t('interviews.date')}
                            error={errors.startsAt}
                        >
                            <Input
                                id="interview-date"
                                type="date"
                                className="min-h-11"
                                value={date}
                                onChange={(e) => setDate(e.target.value)}
                            />
                        </Field>
                        <Field id="interview-time" label={t('interviews.time')} hint={timezone}>
                            <Input
                                id="interview-time"
                                type="time"
                                className="min-h-11"
                                value={time}
                                onChange={(e) => setTime(e.target.value)}
                            />
                        </Field>
                    </div>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
