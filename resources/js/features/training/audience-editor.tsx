import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { SwitchField } from '@/components/mw/switch-field';
import { Input } from '@/components/ui/input';
import { BranchPicker, DepartmentPicker, PositionPicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import { MultiPicker, type Named } from './multi-picker';

export type Audience = {
    branches: Named[];
    departments: Named[];
    positions: Named[];
    employmentTypes: Named[];
    hiredWithinDays: number | null;
};

export const EMPTY_AUDIENCE: Audience = {
    branches: [],
    departments: [],
    positions: [],
    employmentTypes: [],
    hiredWithinDays: null,
};

/**
 * Who a mandatory course is for (D100): any mix of branches, departments (with everything under
 * them), positions and employment types, optionally only recent joiners, and how long they have.
 */
export function AudienceEditor({
    mandatory,
    onMandatoryChange,
    audience,
    onAudienceChange,
    dueDays,
    onDueDaysChange,
    employmentTypes,
    errors,
}: {
    mandatory: boolean;
    onMandatoryChange: (on: boolean) => void;
    audience: Audience;
    onAudienceChange: (audience: Audience) => void;
    dueDays: string;
    onDueDaysChange: (days: string) => void;
    employmentTypes: App.Domain.Organization.Data.CatalogEntryData[];
    errors: Record<string, string>;
}) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const set = (next: Partial<Audience>) => onAudienceChange({ ...audience, ...next });
    const audienceError = Object.entries(errors).find(([key]) => key.startsWith('audience'))?.[1];

    return (
        <div className="flex flex-col gap-5" data-audience-editor>
            <SwitchField
                id="mandatory"
                label={t('audience.mandatory')}
                hint={t('audience.mandatoryHint')}
                checked={mandatory}
                onCheckedChange={onMandatoryChange}
            />
            {mandatory ? (
                <>
                    <p className="text-small text-text-secondary">{t('audience.rule')}</p>
                    <div className="grid gap-5 md:grid-cols-2">
                        <MultiPicker
                            picker={BranchPicker}
                            label={t('audience.branches')}
                            value={audience.branches}
                            onChange={(branches) => set({ branches })}
                            testId="branches"
                        />
                        <MultiPicker
                            picker={DepartmentPicker}
                            label={t('audience.departments')}
                            value={audience.departments}
                            onChange={(departments) => set({ departments })}
                            testId="departments"
                        />
                        <MultiPicker
                            picker={PositionPicker}
                            label={t('audience.positions')}
                            value={audience.positions}
                            onChange={(positions) => set({ positions })}
                            testId="positions"
                        />
                        <fieldset className="flex flex-col gap-2">
                            <legend className="mb-1 text-small font-medium">
                                {t('audience.employmentTypes')}
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {employmentTypes.map((type) => {
                                    const on = audience.employmentTypes.some(
                                        (v) => v.id === type.id,
                                    );
                                    const name = localized(type.name, locale);

                                    return (
                                        <button
                                            key={type.id}
                                            type="button"
                                            aria-pressed={on}
                                            onClick={() =>
                                                set({
                                                    employmentTypes: on
                                                        ? audience.employmentTypes.filter(
                                                              (v) => v.id !== type.id,
                                                          )
                                                        : [
                                                              ...audience.employmentTypes,
                                                              { id: type.id, name },
                                                          ],
                                                })
                                            }
                                            className={cn(
                                                'inline-flex min-h-11 items-center rounded-full border px-4 text-small',
                                                on
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border bg-card hover:bg-muted',
                                            )}
                                        >
                                            {name}
                                        </button>
                                    );
                                })}
                            </div>
                        </fieldset>
                    </div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            id="hired-within"
                            label={t('audience.hiredWithin')}
                            hint={t('audience.hiredWithinHint')}
                            error={errors['audience.hiredWithinDays']}
                        >
                            <Input
                                id="hired-within"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={3650}
                                className="min-h-11"
                                value={audience.hiredWithinDays ?? ''}
                                onChange={(event) =>
                                    set({
                                        hiredWithinDays:
                                            event.target.value === ''
                                                ? null
                                                : Number(event.target.value),
                                    })
                                }
                            />
                        </Field>
                        <Field
                            id="due-days"
                            label={t('audience.dueDays')}
                            hint={t('audience.dueDaysHint')}
                            error={errors.dueDays}
                        >
                            <Input
                                id="due-days"
                                type="number"
                                inputMode="numeric"
                                min={1}
                                max={730}
                                className="min-h-11"
                                value={dueDays}
                                onChange={(event) => onDueDaysChange(event.target.value)}
                            />
                        </Field>
                    </div>
                    {audienceError ? (
                        <p role="alert" className="text-caption text-danger">
                            {audienceError}
                        </p>
                    ) : null}
                </>
            ) : null}
        </div>
    );
}
