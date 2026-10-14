import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { Field } from '@/components/mw/field';
import { CatalogSelect } from '@/features/organization/catalog-select';
import {
    BranchPicker,
    DepartmentPicker,
    EmployeePicker,
    PositionPicker,
} from '@/features/organization/pickers';

type Catalog = App.Domain.Organization.Data.CatalogEntryData;

export type PlacementValue = {
    branch: PickerOption | null;
    department: PickerOption | null;
    position: PickerOption | null;
    manager: PickerOption | null;
    employmentTypeId: string | null;
    gradeId: string | null;
};

export const emptyPlacement: PlacementValue = {
    branch: null,
    department: null,
    position: null,
    manager: null,
    employmentTypeId: null,
    gradeId: null,
};

/** The payload PlacementInputData expects. */
export function placementPayload(value: PlacementValue) {
    return {
        branchId: value.branch?.id ?? '',
        departmentId: value.department?.id ?? null,
        positionId: value.position?.id ?? null,
        managerId: value.manager?.id ?? null,
        employmentTypeId: value.employmentTypeId,
        gradeId: value.gradeId,
    };
}

/** Where someone works: search-first pickers for the big lists, selects for the short ones. */
export function PlacementFields({
    value,
    onChange,
    errors,
    errorPrefix = '',
    employmentTypes,
    grades,
    showManager = true,
}: {
    value: PlacementValue;
    onChange: (value: PlacementValue) => void;
    errors: Partial<Record<string, string>>;
    errorPrefix?: string;
    employmentTypes: Catalog[];
    grades: Catalog[];
    showManager?: boolean;
}) {
    const { t } = useTranslation('people');
    const error = (key: string) => errors[`${errorPrefix}${key}`] ?? errors[key];

    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <Field
                id="placement-branch"
                label={t('fields.branch')}
                error={error('branchId')}
                required
            >
                <BranchPicker
                    value={value.branch}
                    onChange={(branch) => onChange({ ...value, branch })}
                    label={t('fields.branch')}
                />
            </Field>
            <Field
                id="placement-department"
                label={t('fields.department')}
                error={error('departmentId')}
            >
                <DepartmentPicker
                    value={value.department}
                    onChange={(department) => onChange({ ...value, department })}
                    label={t('fields.department')}
                />
            </Field>
            <Field id="placement-position" label={t('fields.position')} error={error('positionId')}>
                <PositionPicker
                    value={value.position}
                    onChange={(position) => onChange({ ...value, position })}
                    label={t('fields.position')}
                />
            </Field>
            {showManager ? (
                <Field
                    id="placement-manager"
                    label={t('fields.manager')}
                    error={error('managerId')}
                >
                    <EmployeePicker
                        value={value.manager}
                        onChange={(manager) => onChange({ ...value, manager })}
                        label={t('fields.manager')}
                    />
                </Field>
            ) : null}
            <Field
                id="placement-type"
                label={t('fields.employmentType')}
                error={error('employmentTypeId')}
            >
                <CatalogSelect
                    id="placement-type"
                    value={value.employmentTypeId}
                    onChange={(employmentTypeId) => onChange({ ...value, employmentTypeId })}
                    options={employmentTypes}
                />
            </Field>
            <Field id="placement-grade" label={t('fields.grade')} error={error('gradeId')}>
                <CatalogSelect
                    id="placement-grade"
                    value={value.gradeId}
                    onChange={(gradeId) => onChange({ ...value, gradeId })}
                    options={grades}
                />
            </Field>
        </div>
    );
}
