import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
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
import { type Person, PeoplePicker } from '@/features/calendar/people-picker';
import { BilingualText, type Pair } from '@/features/hiring/bilingual-text';
import hiring from '@/routes/hiring';

type Stage = App.Domain.Hiring.Data.StageData;
type Automation = {
    message?: { enabled: boolean; subject: Partial<Pair>; body: Partial<Pair> };
    booking?: { enabled: boolean; title: string; duration: number; panel: string[] };
};

const pair = (value: Partial<Pair> | undefined): Pair => ({
    en: value?.en ?? '',
    ar: value?.ar ?? '',
});

/**
 * Stage automations (D93): what happens when a candidate enters a stage. A message to the
 * candidate in their language and, on interview stages, a self-booking invite with a set panel.
 */
export function AutomationEditor({
    stages,
    people,
    onSaved,
}: {
    stages: Stage[];
    people: Record<string, string>;
    onSaved?: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [current, setCurrent] = useState(stages[0]?.id ?? '');
    const stage = stages.find((s) => s.id === current);

    return (
        <div className="flex flex-col gap-4">
            <Field id="automation-stage" label={t('automations.stage')}>
                <Select value={current} onValueChange={setCurrent}>
                    <SelectTrigger id="automation-stage" className="min-h-11">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {stages.map((s) => (
                            <SelectItem key={s.id} value={s.id}>
                                {s.name}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            {stage ? (
                <StageAutomation key={stage.id} stage={stage} people={people} onSaved={onSaved} />
            ) : null}
        </div>
    );
}

function StageAutomation({
    stage,
    people,
    onSaved,
}: {
    stage: Stage;
    people: Record<string, string>;
    onSaved?: () => void;
}) {
    const { t } = useTranslation('hiring');
    const saved = (stage.automation ?? {}) as Automation;
    const [messageOn, setMessageOn] = useState(saved.message?.enabled ?? false);
    const [subject, setSubject] = useState(pair(saved.message?.subject));
    const [body, setBody] = useState(pair(saved.message?.body));
    const [bookingOn, setBookingOn] = useState(saved.booking?.enabled ?? false);
    const [title, setTitle] = useState(saved.booking?.title ?? '');
    const [duration, setDuration] = useState(saved.booking?.duration ?? 30);
    const [panel, setPanel] = useState<Person[]>(() =>
        (saved.booking?.panel ?? []).map((id) => ({ id, name: people[id] ?? id })),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});

    return (
        <form
            className="flex flex-col gap-4"
            onSubmit={(e) => {
                e.preventDefault();
                router.put(
                    hiring.stages.automation.url(stage.id),
                    {
                        message: { enabled: messageOn, subject, body },
                        booking: {
                            enabled: bookingOn,
                            title,
                            duration,
                            panel: panel.map((p) => p.id),
                        },
                    },
                    { preserveScroll: true, onError: setErrors, onSuccess: () => onSaved?.() },
                );
            }}
            data-automation={stage.kind}
        >
            {errors.error ? (
                <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}
            <SwitchField
                id={`automation-message-${stage.id}`}
                label={t('automations.message')}
                hint={t('automations.messageHint')}
                checked={messageOn}
                onCheckedChange={setMessageOn}
            />
            {messageOn ? (
                <>
                    <BilingualText
                        id={`automation-subject-${stage.id}`}
                        label={t('automations.subject')}
                        value={subject}
                        onChange={setSubject}
                        errors={errors}
                        errorKey="message.subject"
                        required
                        maxLength={200}
                    />
                    <BilingualText
                        id={`automation-body-${stage.id}`}
                        label={t('automations.body')}
                        value={body}
                        onChange={setBody}
                        errors={errors}
                        errorKey="message.body"
                        required
                        multiline
                        maxLength={4000}
                        hint={t('automations.variables')}
                    />
                </>
            ) : null}
            {stage.kind === 'interview' ? (
                <>
                    <SwitchField
                        id={`automation-booking-${stage.id}`}
                        label={t('automations.booking')}
                        hint={t('automations.bookingHint')}
                        checked={bookingOn}
                        onCheckedChange={setBookingOn}
                    />
                    {bookingOn ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    id={`automation-title-${stage.id}`}
                                    label={t('interviews.title')}
                                >
                                    <Input
                                        id={`automation-title-${stage.id}`}
                                        value={title}
                                        maxLength={160}
                                        onChange={(e) => setTitle(e.target.value)}
                                    />
                                </Field>
                                <Field
                                    id={`automation-duration-${stage.id}`}
                                    label={t('interviews.duration')}
                                >
                                    <Select
                                        value={String(duration)}
                                        onValueChange={(v) => setDuration(Number(v))}
                                    >
                                        <SelectTrigger
                                            id={`automation-duration-${stage.id}`}
                                            className="min-h-11"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {[15, 30, 45, 60, 90, 120].map((m) => (
                                                <SelectItem key={m} value={String(m)}>
                                                    {t('interviews.minutes', { count: m })}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                            </div>
                            <PeoplePicker
                                label={t('interviews.panel')}
                                value={panel}
                                onChange={setPanel}
                            />
                            {errors['booking.panel'] ? (
                                <p role="alert" className="text-caption text-danger">
                                    {errors['booking.panel']}
                                </p>
                            ) : null}
                        </>
                    ) : null}
                </>
            ) : null}
            <Button type="submit" className="min-h-11 self-end" data-save-automation>
                {t('automations.save')}
            </Button>
        </form>
    );
}
