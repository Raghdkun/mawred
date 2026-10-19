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
import { localParts, toInstant } from '@/features/calendar/time';
import training from '@/routes/training';

type Session = App.Domain.Training.Data.SessionData;

/** Plans or changes a session (D98): date and times in a timezone, on site or online, and seats. */
export function SessionDialog({
    courseId,
    session,
    timezone,
    onClose,
}: {
    courseId: string;
    session: Session | null;
    timezone: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('training');
    const zone = session?.timezone ?? timezone;
    const [date, setDate] = useState(session ? localParts(session.startsAt, zone).date : '');
    const [from, setFrom] = useState(session ? localParts(session.startsAt, zone).time : '09:00');
    const [to, setTo] = useState(session ? localParts(session.endsAt, zone).time : '11:00');
    const [mode, setMode] = useState(session?.mode ?? 'onsite');
    const [location, setLocation] = useState(session?.location ?? '');
    const [meetingUrl, setMeetingUrl] = useState(session?.meetingUrl ?? '');
    const [capacity, setCapacity] = useState(session?.capacity ? String(session.capacity) : '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const submit = () => {
        setSending(true);
        const payload = {
            startsAt: date ? toInstant(date, from, zone) : '',
            endsAt: date ? toInstant(date, to, zone) : '',
            timezone: zone,
            mode,
            location: mode === 'onsite' ? location || null : null,
            meetingUrl: mode === 'online' ? meetingUrl || null : null,
            capacity: capacity ? Number(capacity) : null,
        };
        const options = {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: onClose,
            onFinish: () => setSending(false),
        };

        if (session) {
            router.put(training.manage.sessions.update.url(session.id), payload, options);
        } else {
            router.post(training.manage.sessions.store.url(courseId), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={session ? t('sessions.edit') : t('sessions.new')}
            description={t('sessions.dialogHelp', { zone })}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={sending || !date}
                        onClick={submit}
                        data-save-session
                    >
                        {t('sessions.save')}
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
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field
                        id="session-date"
                        label={t('sessions.date')}
                        error={errors.startsAt}
                        required
                    >
                        <Input
                            id="session-date"
                            type="date"
                            className="min-h-11"
                            value={date}
                            onChange={(e) => setDate(e.target.value)}
                        />
                    </Field>
                    <Field id="session-from" label={t('sessions.from')}>
                        <Input
                            id="session-from"
                            type="time"
                            className="min-h-11"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </Field>
                    <Field id="session-to" label={t('sessions.to')} error={errors.endsAt}>
                        <Input
                            id="session-to"
                            type="time"
                            className="min-h-11"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </Field>
                </div>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field id="session-mode" label={t('sessions.mode')}>
                        <Select value={mode} onValueChange={setMode}>
                            <SelectTrigger id="session-mode" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="onsite">{t('sessions.onsite')}</SelectItem>
                                <SelectItem value="online">{t('sessions.online')}</SelectItem>
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        id="session-capacity"
                        label={t('sessions.capacity')}
                        hint={t('sessions.capacityHint')}
                        error={errors.capacity}
                    >
                        <Input
                            id="session-capacity"
                            type="number"
                            inputMode="numeric"
                            min={1}
                            className="min-h-11"
                            value={capacity}
                            onChange={(e) => setCapacity(e.target.value)}
                        />
                    </Field>
                </div>
                {mode === 'onsite' ? (
                    <Field
                        id="session-location"
                        label={t('sessions.location')}
                        error={errors.location}
                    >
                        <Input
                            id="session-location"
                            className="min-h-11"
                            maxLength={200}
                            value={location}
                            onChange={(e) => setLocation(e.target.value)}
                        />
                    </Field>
                ) : (
                    <Field
                        id="session-url"
                        label={t('sessions.meetingUrl')}
                        error={errors.meetingUrl}
                        required
                    >
                        <Input
                            id="session-url"
                            type="url"
                            dir="ltr"
                            className="min-h-11"
                            value={meetingUrl}
                            onChange={(e) => setMeetingUrl(e.target.value)}
                        />
                    </Field>
                )}
            </div>
        </ResponsiveDialog>
    );
}
