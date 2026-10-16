import { router } from '@inertiajs/react';
import { WandSparkles } from 'lucide-react';
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
import { Textarea } from '@/components/ui/textarea';
import { minorDigits } from '@/features/forms/upload';
import {
    BranchPicker,
    DepartmentPicker,
    EmployeePicker,
    PositionPicker,
} from '@/features/organization/pickers';
import { postJson } from '@/lib/api';
import hiring from '@/routes/hiring';

type Offer = App.Domain.Hiring.Data.OfferData;
type Defaults = NonNullable<App.Domain.Hiring.Data.ApplicationDetailData['offerDefaults']>;
type Option = { id: string; name: string };

const option = (id: string | null, label: string | null): PickerOption | null =>
    id ? { id, label: label ?? '' } : null;

/**
 * Offer letter (handoff 32, D95): the terms in a side panel and the letter beside them in the
 * candidate's language and direction. "Fill from template" writes the letter from Hiring
 * settings with the terms filled in; the recruiter can adjust it before sending for approval.
 */
export function OfferDialog({
    applicationId,
    offer,
    defaults,
    employmentTypes,
    candidateLocale,
    onClose,
}: {
    applicationId: string;
    offer: Offer | null;
    defaults: Defaults;
    employmentTypes: Option[];
    candidateLocale: string;
    onClose: () => void;
}) {
    const { t } = useTranslation('hiring');
    const currency0 = offer?.salary?.currency ?? defaults.currency;
    const digits = (c: string) => minorDigits(c);
    const [jobTitle, setJobTitle] = useState(offer?.jobTitle ?? defaults.jobTitle);
    const [startOn, setStartOn] = useState(offer?.startOn ?? '');
    const [expiresOn, setExpiresOn] = useState(offer?.expiresOn ?? '');
    const [amount, setAmount] = useState(() => {
        const minor = offer?.salary?.amount_minor ?? defaults.salaryMinor;

        return minor ? String(minor / 10 ** digits(currency0)) : '';
    });
    const [currency, setCurrency] = useState(currency0);
    const [branch, setBranch] = useState(
        option(offer?.branchId ?? defaults.branchId, offer?.branch ?? defaults.branch),
    );
    const [department, setDepartment] = useState(
        option(
            offer?.departmentId ?? defaults.departmentId,
            offer?.department ?? defaults.department,
        ),
    );
    const [position, setPosition] = useState(
        option(offer?.positionId ?? defaults.positionId, offer?.position ?? defaults.position),
    );
    const [manager, setManager] = useState(
        option(offer?.managerId ?? defaults.managerId, offer?.manager ?? defaults.manager),
    );
    const [employmentType, setEmploymentType] = useState(offer?.employmentTypeId ?? '');
    const [letter, setLetter] = useState(offer?.letter ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const rtl = (offer?.locale ?? candidateLocale) === 'ar';

    const terms = () => ({
        jobTitle,
        startOn,
        salary: {
            amount_minor: Math.round(Number(amount || 0) * 10 ** digits(currency)),
            currency: currency.toUpperCase(),
        },
        branchId: branch?.id ?? '',
        departmentId: department?.id ?? null,
        positionId: position?.id ?? null,
        employmentTypeId: employmentType || null,
        managerId: manager?.id ?? null,
        expiresOn: expiresOn || null,
    });

    const fill = async () => {
        setBusy(true);

        try {
            const result = await postJson<{ letter: string }>(
                hiring.offers.letter.url(applicationId),
                terms(),
            );
            setLetter(result.letter);
            setErrors({});
        } catch {
            setErrors({ error: t('offers.fillFailed') });
        } finally {
            setBusy(false);
        }
    };

    const save = () => {
        setBusy(true);
        const payload = { ...terms(), letter: letter || null };
        const options = {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: onClose,
            onFinish: () => setBusy(false),
        };

        if (offer) {
            router.put(hiring.offers.update.url(offer.id), payload, options);
        } else {
            router.post(hiring.offers.store.url(applicationId), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={offer ? t('offers.editTitle') : t('offers.newTitle')}
            description={t('offers.description')}
            className="sm:max-w-5xl"
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button className="min-h-11" disabled={busy} onClick={save} data-save-offer>
                        {t('offers.saveDraft')}
                    </Button>
                </div>
            }
        >
            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <div className="flex flex-col gap-4">
                    {errors.error ? (
                        <p
                            role="alert"
                            className="rounded-md bg-danger-bg p-3 text-small text-danger"
                        >
                            {errors.error}
                        </p>
                    ) : null}
                    <Field
                        id="offer-title"
                        label={t('offers.jobTitle')}
                        error={errors.jobTitle}
                        required
                    >
                        <Input
                            id="offer-title"
                            value={jobTitle}
                            maxLength={160}
                            onChange={(e) => setJobTitle(e.target.value)}
                        />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="offer-start"
                            label={t('offers.startOn')}
                            error={errors.startOn}
                            required
                        >
                            <Input
                                id="offer-start"
                                type="date"
                                className="min-h-11"
                                value={startOn}
                                onChange={(e) => setStartOn(e.target.value)}
                            />
                        </Field>
                        <Field
                            id="offer-expires"
                            label={t('offers.expiresOn')}
                            error={errors.expiresOn}
                        >
                            <Input
                                id="offer-expires"
                                type="date"
                                className="min-h-11"
                                value={expiresOn}
                                onChange={(e) => setExpiresOn(e.target.value)}
                            />
                        </Field>
                    </div>
                    <div className="grid grid-cols-[minmax(0,1fr)_6rem] gap-2">
                        <Field
                            id="offer-salary"
                            label={t('offers.salary')}
                            error={errors['salary.amount_minor'] ?? errors.salary}
                            required
                        >
                            <Input
                                id="offer-salary"
                                inputMode="decimal"
                                value={amount}
                                onChange={(e) => setAmount(e.target.value.replace(/[^\d.]/g, ''))}
                            />
                        </Field>
                        <Field
                            id="offer-currency"
                            label={t('offers.currency')}
                            error={errors['salary.currency']}
                        >
                            <Input
                                id="offer-currency"
                                dir="ltr"
                                value={currency}
                                maxLength={3}
                                onChange={(e) => setCurrency(e.target.value.toUpperCase())}
                            />
                        </Field>
                    </div>
                    <BranchPicker label={t('offers.branch')} value={branch} onChange={setBranch} />
                    {errors.branchId ? (
                        <p role="alert" className="text-caption text-danger">
                            {errors.branchId}
                        </p>
                    ) : null}
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
                    <EmployeePicker
                        label={t('offers.manager')}
                        value={manager}
                        onChange={setManager}
                    />
                    {employmentTypes.length > 0 ? (
                        <Field id="offer-type" label={t('offers.employmentType')}>
                            <Select
                                value={employmentType || '__none'}
                                onValueChange={(v) => setEmploymentType(v === '__none' ? '' : v)}
                            >
                                <SelectTrigger id="offer-type" className="min-h-11">
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

                <section aria-labelledby="letter-title" className="flex flex-col gap-2">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h3 id="letter-title" className="text-small font-medium">
                            {t('offers.letter')}{' '}
                            <span className="text-caption text-text-secondary">
                                ({t(`profile.lang.${rtl ? 'ar' : 'en'}`)})
                            </span>
                        </h3>
                        <Button
                            variant="outline"
                            className="min-h-11"
                            disabled={busy || !branch}
                            onClick={() => void fill()}
                            data-fill-letter
                        >
                            <WandSparkles aria-hidden />
                            {t('offers.fill')}
                        </Button>
                    </div>
                    <Textarea
                        aria-labelledby="letter-title"
                        dir={rtl ? 'rtl' : 'ltr'}
                        lang={rtl ? 'ar' : 'en'}
                        value={letter}
                        onChange={(e) => setLetter(e.target.value)}
                        rows={18}
                        maxLength={20000}
                        placeholder={t('offers.letterPlaceholder')}
                        className="min-h-80 bg-card leading-relaxed"
                        data-letter
                    />
                    <p className="text-caption text-text-secondary">{t('offers.letterHint')}</p>
                </section>
            </div>
        </ResponsiveDialog>
    );
}
