import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';

type ApiBranch = { id: string; code: string; name: Record<string, string> };
type ApiDepartment = { id: string; code: string; name: Record<string, string>; depth: number };
type ApiPosition = { id: string; code: string; title: Record<string, string> };
type ApiEmployee = {
    id: string;
    employee_number: string;
    name: { en: string; ar: string | null };
    preferred_name: string | null;
    position: { name: Record<string, string> } | null;
};

type PickerProps = {
    value: PickerOption | null;
    onChange: (option: PickerOption | null) => void;
    label: string;
    placeholder?: string;
    className?: string;
};

/** Search-first pickers over /api/v1 (hundreds of branches, thousands of people). */
export function BranchPicker(props: PickerProps) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();

    return (
        <AsyncPicker<ApiBranch>
            endpoint="branches"
            queryKey="picker-branches"
            toOption={(branch) => ({
                id: branch.id,
                label: localized(branch.name, locale),
                description: branch.code,
            })}
            placeholder={props.placeholder ?? t('pickers.branch')}
            {...props}
        />
    );
}

export function DepartmentPicker(props: PickerProps) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();

    return (
        <AsyncPicker<ApiDepartment>
            endpoint="departments"
            queryKey="picker-departments"
            toOption={(department) => ({
                id: department.id,
                label: localized(department.name, locale),
                description: department.code,
            })}
            placeholder={props.placeholder ?? t('pickers.department')}
            {...props}
        />
    );
}

export function PositionPicker(props: PickerProps) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();

    return (
        <AsyncPicker<ApiPosition>
            endpoint="positions"
            queryKey="picker-positions"
            toOption={(position) => ({
                id: position.id,
                label: localized(position.title, locale),
                description: position.code,
            })}
            placeholder={props.placeholder ?? t('pickers.position')}
            {...props}
        />
    );
}

export function EmployeePicker(props: PickerProps) {
    const { t } = useTranslation('organization');
    const { locale } = useLocale();

    return (
        <AsyncPicker<ApiEmployee>
            endpoint="employees"
            queryKey="picker-employees"
            toOption={(employee) => ({
                id: employee.id,
                label:
                    employee.preferred_name ??
                    (locale === 'ar' && employee.name.ar ? employee.name.ar : employee.name.en),
                description: [
                    employee.employee_number,
                    employee.position ? localized(employee.position.name, locale) : null,
                ]
                    .filter(Boolean)
                    .join(' · '),
            })}
            placeholder={props.placeholder ?? t('pickers.employee')}
            {...props}
        />
    );
}
