import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { EmployeePicker } from '@/features/organization/pickers';
import training from '@/routes/training';
import { MultiPicker, type Named } from './multi-picker';

/** Enrol people on a course with an optional due date; no approval needed (the assigner decides). */
export function AssignDialog({ courseId, onClose }: { courseId: string; onClose: () => void }) {
    const { t } = useTranslation('training');
    const [people, setPeople] = useState<Named[]>([]);
    const [dueOn, setDueOn] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    const submit = () => {
        setSending(true);
        router.post(
            training.manage.courses.assign.url(courseId),
            { employees: people.map((p) => p.id), dueOn: dueOn || null },
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
            title={t('people.assignTitle')}
            description={t('people.assignHelp')}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={sending || people.length === 0}
                        onClick={submit}
                        data-confirm-assign
                    >
                        {t('people.assignConfirm', { count: people.length })}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                <MultiPicker
                    picker={EmployeePicker}
                    label={t('people.who')}
                    value={people}
                    onChange={setPeople}
                    testId="assignees"
                />
                {errors.employees ? (
                    <p role="alert" className="text-caption text-danger">
                        {errors.employees}
                    </p>
                ) : null}
                <Field
                    id="assign-due"
                    label={t('people.dueOn')}
                    hint={t('people.dueOnHint')}
                    error={errors.dueOn}
                >
                    <Input
                        id="assign-due"
                        type="date"
                        className="min-h-11"
                        value={dueOn}
                        onChange={(e) => setDueOn(e.target.value)}
                    />
                </Field>
            </div>
        </ResponsiveDialog>
    );
}
