import { router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
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
import {
    BranchPicker,
    DepartmentPicker,
    EmployeePicker,
    PositionPicker,
} from '@/features/organization/pickers';
import hiring from '@/routes/hiring';

type Hire = NonNullable<App.Domain.Hiring.Data.ApplicationDetailData['hire']>;

const option = (id: string | null, label: string | null): PickerOption | null =>
    id ? { id, label: label ?? '' } : null;

/**
 * Confirms the hire (D85): details prefilled from the accepted offer. Confirming creates the
 * employee; onboarding starts and their invitation goes out on the start date.
 */
export function HireDialog({
    applicationId,
    hire,
    employmentTypes,
    onClose,
}: {
    applicationId: string;
    hire: Hire;
    employmentTypes: Array<{ id: string; name: string }>;
    onClose: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [nameEn, setNameEn] = useState(hire.nameEn);
    const [nameAr, setNameAr] = useState('');
    const [hireDate, setHireDate] = useState(hire.hireDate);
    const [workEmail, setWorkEmail] = useState('');
    const [branch, setBranch] = useState(option(hire.branchId, hire.branch));
    const [department, setDepartment] = useState(option(hire.departmentId, hire.department));
    const [position, setPosition] = useState(option(hire.positionId, hire.position));
    const [manager, setManager] = useState(option(hire.managerId, hire.manager));
    const [employmentType, setEmploymentType] = useState(hire.employmentTypeId ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('hire.title')}
            description={t('hire.description')}
            className="sm:max-w-xl"
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button
                        className="min-h-11"
                        disabled={busy || !branch}
                        data-confirm-hire
                        onClick={() => {
                            setBusy(true);
                            router.post(
                                hiring.applications.hire.url(applicationId),
                                {
                                    nameEn,
                                    nameAr: nameAr || null,
                                    hireDate,
                                    branchId: branch?.id ?? '',
                                    departmentId: department?.id ?? null,
                                    positionId: position?.id ?? null,
                                    managerId: manager?.id ?? null,
                                    employmentTypeId: employmentType || null,
                                    workEmail: workEmail || null,
                                },
                                {
                                    preserveScroll: true,
                                    onError: setErrors,
                                    onSuccess: onClose,
                                    onFinish: () => setBusy(false),
                                },
                            );
                        }}
                    >
                        {t('hire.confirm')}
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
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        id="hire-name-en"
                        label={t('hire.nameEn')}
                        error={errors.nameEn}
                        required
                    >
                        <Input
                            id="hire-name-en"
                            dir="ltr"
                            value={nameEn}
                            onChange={(e) => setNameEn(e.target.value)}
                        />
                    </Field>
                    <Field id="hire-name-ar" label={t('hire.nameAr')} error={errors.nameAr}>
                        <Input
                            id="hire-name-ar"
                            dir="rtl"
                            lang="ar"
                            value={nameAr}
                            onChange={(e) => setNameAr(e.target.value)}
                        />
                    </Field>
                    <Field id="hire-date" label={t('hire.date')} error={errors.hireDate} required>
                        <Input
                            id="hire-date"
                            type="date"
                            className="min-h-11"
                            value={hireDate}
                            onChange={(e) => setHireDate(e.target.value)}
                        />
                    </Field>
                    <Field id="hire-email" label={t('hire.workEmail')} error={errors.workEmail}>
                        <Input
                            id="hire-email"
                            type="email"
                            dir="ltr"
                            value={workEmail}
                            onChange={(e) => setWorkEmail(e.target.value)}
                        />
                    </Field>
                </div>
                <BranchPicker label={t('offers.branch')} value={branch} onChange={setBranch} />
                <DepartmentPicker
                    label={t('offers.department')}
                    value={department}
                    onChange={setDepartment}
                />
                <PositionPicker
                    label={t('offers.position')}
                    value={position}
                    onChange={setPosition}
                />
                <EmployeePicker label={t('offers.manager')} value={manager} onChange={setManager} />
                {employmentTypes.length > 0 ? (
                    <Field id="hire-type" label={t('offers.employmentType')}>
                        <Select
                            value={employmentType || '__none'}
                            onValueChange={(v) => setEmploymentType(v === '__none' ? '' : v)}
                        >
                            <SelectTrigger id="hire-type" className="min-h-11">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none">—</SelectItem>
                                {employmentTypes.map((type) => (
                                    <SelectItem key={type.id} value={type.id}>
                                        {type.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}
