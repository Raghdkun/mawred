import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import calendarRoutes from '@/routes/calendar';
import { type Person, PeoplePicker } from './people-picker';
import { type CalendarEvent, localParts, toInstant } from './time';

type Repeat = 'none' | 'daily' | 'weekly' | 'monthly';
export type Scope = 'all' | 'this' | 'following';

/**
 * Create or change a meeting (handoff 27 booking dialog): when, who, where, privacy (D76) and a
 * simple repeat (D78). Changing one occurrence of a series asks which ones it applies to.
 */
export function EventForm({
    event,
    initial,
    timezone,
    meId,
    onClose,
}: {
    event: CalendarEvent | null;
    initial?: { start: string; end: string; people?: Person[] };
    timezone: string;
    meId: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('calendar');
    const { locale } = useLocale();
    // Default to the next whole hour, computed once when the form opens.
    const [[startIso, endIso]] = useState(() => {
        const next = new Date();
        next.setMinutes(0, 0, 0);
        next.setHours(next.getHours() + 1);

        return [
            event?.startsAt ?? initial?.start ?? next.toISOString(),
            event?.endsAt ?? initial?.end ?? new Date(next.getTime() + 3_600_000).toISOString(),
        ];
    });
    const start = localParts(startIso, timezone);
    const end = localParts(endIso, timezone);
    const [title, setTitle] = useState(event?.title ?? '');
    const [date, setDate] = useState(start.date);
    const [from, setFrom] = useState(start.time);
    const [to, setTo] = useState(end.time);
    const [people, setPeople] = useState<Person[]>(
        event
            ? event.participants
                  .filter((p) => p.id !== meId)
                  .map((p) => ({ id: p.id, name: p.name }))
            : (initial?.people ?? []).filter((p) => p.id !== meId),
    );
    const [optional, setOptional] = useState<string[]>(
        event ? event.participants.filter((p) => p.role === 'optional').map((p) => p.id) : [],
    );
    const [location, setLocation] = useState(event?.location ?? '');
    const [description, setDescription] = useState(event?.description ?? '');
    const [isPrivate, setPrivate] = useState(event?.private ?? false);
    const rule = event?.recurrence ?? null;
    const [repeat, setRepeat] = useState<Repeat>((rule?.freq as Repeat | undefined) ?? 'none');
    const [weekdays, setWeekdays] = useState<number[]>(
        rule?.byweekday ?? [new Date(`${start.date}T12:00:00Z`).getUTCDay() || 7],
    );
    const [until, setUntil] = useState(rule?.until ?? '');
    const [scope, setScope] = useState<Scope>('all');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const repeatsAndOneOccurrence = event?.repeats === true;
    const weekdayName = (d: number) =>
        formatDate(new Date(Date.UTC(2024, 0, d)), locale, { weekday: 'short', timeZone: 'UTC' });

    const save = () => {
        const startsAt = toInstant(date, from, timezone);
        let endsAt = toInstant(date, to, timezone);

        if (endsAt <= startsAt) {
            const next = new Date(`${date}T12:00:00Z`);
            next.setUTCDate(next.getUTCDate() + 1);
            endsAt = toInstant(next.toISOString().slice(0, 10), to, timezone);
        }

        const payload = {
            title,
            startsAt,
            endsAt,
            timezone,
            required: people.filter((p) => !optional.includes(p.id)).map((p) => p.id),
            optional: people.filter((p) => optional.includes(p.id)).map((p) => p.id),
            location: location.trim() || null,
            description: description.trim() || null,
            private: isPrivate,
            recurrence:
                repeat === 'none'
                    ? null
                    : {
                          freq: repeat,
                          interval: 1,
                          byweekday: repeat === 'weekly' ? weekdays : [],
                          until: until || null,
                          count: null,
                      },
            scope,
            occurrence: event?.startsAt ?? null,
        };
        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => setBusy(false),
            onError: setErrors,
            onSuccess: onClose,
        };

        if (event) {
            router.put(calendarRoutes.update.url(event.id), payload, options);
        } else {
            router.post(calendarRoutes.store.url(), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={event ? t('form.edit') : t('form.add')}
            className="sm:max-w-xl"
            footer={
                <>
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        type="button"
                        loading={busy}
                        disabled={title.trim() === ''}
                        onClick={save}
                        data-save-meeting
                    >
                        {t('form.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <Field id="meeting-title" label={t('form.title')} error={errors.title} required>
                    <Input
                        id="meeting-title"
                        value={title}
                        maxLength={200}
                        onChange={(e) => setTitle(e.target.value)}
                    />
                </Field>
                <div className="grid grid-cols-3 gap-3">
                    <Field id="meeting-date" label={t('form.date')} error={errors.startsAt}>
                        <Input
                            id="meeting-date"
                            type="date"
                            value={date}
                            onChange={(e) => e.target.value && setDate(e.target.value)}
                        />
                    </Field>
                    <Field id="meeting-from" label={t('form.starts')}>
                        <Input
                            id="meeting-from"
                            type="time"
                            dir="ltr"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </Field>
                    <Field id="meeting-to" label={t('form.ends')} error={errors.endsAt}>
                        <Input
                            id="meeting-to"
                            type="time"
                            dir="ltr"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </Field>
                </div>
                <PeoplePicker
                    label={t('form.people')}
                    value={people}
                    onChange={setPeople}
                    exclude={[meId]}
                />
                {people.length > 0 ? (
                    <fieldset className="flex flex-col gap-1">
                        <legend className="mb-1 text-small font-medium">
                            {t('form.optional')}
                        </legend>
                        {people.map((p) => (
                            <div key={p.id} className="flex min-h-11 items-center gap-2">
                                <Checkbox
                                    id={`optional-${p.id}`}
                                    checked={optional.includes(p.id)}
                                    onCheckedChange={(v) =>
                                        setOptional(
                                            v === true
                                                ? [...optional, p.id]
                                                : optional.filter((id) => id !== p.id),
                                        )
                                    }
                                />
                                <Label htmlFor={`optional-${p.id}`}>{p.name}</Label>
                            </div>
                        ))}
                    </fieldset>
                ) : null}
                <Field id="meeting-location" label={t('form.location')} error={errors.location}>
                    <Input
                        id="meeting-location"
                        value={location}
                        maxLength={200}
                        onChange={(e) => setLocation(e.target.value)}
                    />
                </Field>
                <Field id="meeting-notes" label={t('form.description')} error={errors.description}>
                    <Textarea
                        id="meeting-notes"
                        value={description}
                        maxLength={5000}
                        onChange={(e) => setDescription(e.target.value)}
                    />
                </Field>
                {!event || event.recurrence !== null || !event.repeats ? (
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field id="meeting-repeat" label={t('form.repeat')}>
                            <Select value={repeat} onValueChange={(v) => setRepeat(v as Repeat)}>
                                <SelectTrigger id="meeting-repeat" className="min-h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">{t('form.repeatNone')}</SelectItem>
                                    <SelectItem value="daily">{t('form.repeatDaily')}</SelectItem>
                                    <SelectItem value="weekly">{t('form.repeatWeekly')}</SelectItem>
                                    <SelectItem value="monthly">
                                        {t('form.repeatMonthly')}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </Field>
                        {repeat !== 'none' ? (
                            <Field
                                id="meeting-until"
                                label={t('form.endsOn')}
                                error={errors['recurrence.until']}
                            >
                                <Input
                                    id="meeting-until"
                                    type="date"
                                    min={date}
                                    value={until}
                                    onChange={(e) => setUntil(e.target.value)}
                                />
                            </Field>
                        ) : null}
                        {repeat === 'weekly' ? (
                            <fieldset className="sm:col-span-2">
                                <legend className="mb-1 text-small font-medium">
                                    {t('form.on')}
                                </legend>
                                <div className="flex flex-wrap gap-2">
                                    {[7, 1, 2, 3, 4, 5, 6].map((d) => (
                                        <label
                                            key={d}
                                            className="flex min-h-11 items-center gap-1.5 rounded-md border border-border px-2.5 text-small"
                                        >
                                            <Checkbox
                                                checked={weekdays.includes(d)}
                                                onCheckedChange={(v) =>
                                                    setWeekdays(
                                                        v === true
                                                            ? [...weekdays, d]
                                                            : weekdays.filter((x) => x !== d),
                                                    )
                                                }
                                            />
                                            {weekdayName(d)}
                                        </label>
                                    ))}
                                </div>
                            </fieldset>
                        ) : null}
                    </div>
                ) : null}
                <div className="flex min-h-11 items-center gap-2">
                    <Checkbox
                        id="meeting-private"
                        checked={isPrivate}
                        onCheckedChange={(v) => setPrivate(v === true)}
                    />
                    <Label htmlFor="meeting-private">{t('form.private')}</Label>
                </div>
                {repeatsAndOneOccurrence ? <ScopeChoice value={scope} onChange={setScope} /> : null}
            </div>
        </ResponsiveDialog>
    );
}

export function ScopeChoice({
    value,
    onChange,
}: {
    value: Scope;
    onChange: (scope: Scope) => void;
}) {
    const { t } = useTranslation('calendar');

    return (
        <fieldset className="flex flex-col gap-2 border-t border-border pt-4">
            <legend className="mb-1 text-small font-medium">{t('event.scope.title')}</legend>
            <RadioGroup value={value} onValueChange={(v) => onChange(v as Scope)}>
                {(['this', 'following', 'all'] as const).map((s) => (
                    <div key={s} className="flex min-h-11 items-center gap-2">
                        <RadioGroupItem id={`scope-${s}`} value={s} />
                        <Label htmlFor={`scope-${s}`}>{t(`event.scope.${s}`)}</Label>
                    </div>
                ))}
            </RadioGroup>
        </fieldset>
    );
}
