import { router } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { EmployeePicker } from '@/features/organization/pickers';
import reports from '@/routes/reports';

type Saved = App.Domain.Reports.Data.SavedReportData;

/**
 * Share a saved report with people and roles (D119). Each of them sees it with their own data.
 */
export function ShareDialog({
    saved,
    roles,
    open,
    onOpenChange,
}: {
    saved: Saved;
    roles: Array<{ name: string; label: string }>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const { t } = useTranslation('reports');
    const [people, setPeople] = useState<PickerOption[]>(saved.sharedWith?.people ?? []);
    const [chosenRoles, setChosenRoles] = useState<string[]>(saved.sharedWith?.roles ?? []);
    const [saving, setSaving] = useState(false);

    const save = () => {
        setSaving(true);
        router.put(
            reports.saved.share.url(saved.id),
            { people: people.map((p) => p.id), roles: chosenRoles },
            {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('share.title')}
            description={t('share.help')}
            footer={
                <Button className="min-h-11" onClick={save} disabled={saving} data-save-sharing>
                    {t('share.save')}
                </Button>
            }
        >
            <div className="flex flex-col gap-5">
                <section aria-labelledby="share-people" className="flex flex-col gap-2">
                    <h3 id="share-people" className="text-small font-medium">
                        {t('share.people')}
                    </h3>
                    <EmployeePicker
                        label={t('share.addPerson')}
                        placeholder={t('share.addPerson')}
                        value={null}
                        onChange={(option) =>
                            option &&
                            !people.some((p) => p.id === option.id) &&
                            setPeople([...people, option])
                        }
                        className="min-h-11"
                    />
                    {people.length > 0 ? (
                        <ul className="flex flex-wrap gap-2" data-shared-people>
                            {people.map((person) => (
                                <li
                                    key={person.id}
                                    className="flex items-center gap-1 rounded-full bg-muted ps-3 text-small"
                                >
                                    <bdi>{person.label}</bdi>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-9 rounded-full"
                                        aria-label={t('share.remove', { name: person.label })}
                                        onClick={() =>
                                            setPeople(people.filter((p) => p.id !== person.id))
                                        }
                                    >
                                        <X aria-hidden className="size-4" />
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    ) : null}
                </section>
                <fieldset className="flex flex-col gap-1">
                    <legend className="mb-1 text-small font-medium">{t('share.roles')}</legend>
                    {roles.map((role) => (
                        <label
                            key={role.name}
                            className="flex min-h-11 items-center gap-3 text-small"
                        >
                            <Checkbox
                                checked={chosenRoles.includes(role.name)}
                                onCheckedChange={(on) =>
                                    setChosenRoles(
                                        on === true
                                            ? [...chosenRoles, role.name]
                                            : chosenRoles.filter((r) => r !== role.name),
                                    )
                                }
                                data-share-role={role.name}
                            />
                            {role.label}
                        </label>
                    ))}
                </fieldset>
            </div>
        </ResponsiveDialog>
    );
}
