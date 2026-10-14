import { router, useForm } from '@inertiajs/react';
import { Lock, Pencil } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { Field } from '@/components/mw/field';
import { Section } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import { countryName } from '@/lib/regions';
import people from '@/routes/people';
import { type ProfileProps } from './types';

type Sensitive = App.Domain.People.Data.SensitiveDetailsData;
type CustomField = App.Domain.People.Data.CustomFieldValueData;
type Group = 'nationalId' | 'passport' | 'residency' | 'bank' | 'personalContact' | 'dateOfBirth';

const GROUP_KEYS: Record<Group, string> = {
    nationalId: 'employee.national_id',
    passport: 'employee.passport',
    residency: 'employee.residency',
    bank: 'employee.bank',
    personalContact: 'employee.personal_contact',
    dateOfBirth: 'employee.date_of_birth',
};

const NONE = '__none';

export function PersonalTab({ profile, can }: Pick<ProfileProps, 'profile' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [editingProfile, setEditingProfile] = useState(false);
    const [editingGroup, setEditingGroup] = useState<Group | null>(null);
    const [editingCustom, setEditingCustom] = useState<'personal' | 'job' | null>(null);
    const summary = profile.summary;
    const sensitive = profile.sensitive;
    const date = (value: string | null | undefined) =>
        value ? formatDate(value, locale, { dateStyle: 'medium' }) : '—';
    const visibleGroups = (Object.keys(GROUP_KEYS) as Group[]).filter(
        (group) => GROUP_KEYS[group] in sensitive.access,
    );

    return (
        <div className="grid gap-6 lg:grid-cols-2">
            <Section
                title={t('sections.personal')}
                actions={
                    can.update ? (
                        <Button size="sm" variant="ghost" onClick={() => setEditingProfile(true)}>
                            <Pencil aria-hidden />
                            {t('common:actions.edit')}
                        </Button>
                    ) : null
                }
            >
                <Facts
                    items={[
                        [
                            t('fields.nameEn'),
                            <bdi key="name-en" lang="en">
                                {summary.nameEn}
                            </bdi>,
                        ],
                        [
                            t('fields.nameAr'),
                            summary.nameAr ? (
                                <bdi key="name-ar" lang="ar">
                                    {summary.nameAr}
                                </bdi>
                            ) : (
                                '—'
                            ),
                        ],
                        [t('fields.preferredName'), summary.preferredName ?? '—'],
                        [t('fields.gender'), profile.gender ? t(`genders.${profile.gender}`) : '—'],
                        [
                            t('fields.nationality'),
                            profile.nationality ? countryName(profile.nationality, locale) : '—',
                        ],
                        [
                            t('fields.maritalStatus'),
                            profile.maritalStatus ? t(`marital.${profile.maritalStatus}`) : '—',
                        ],
                    ]}
                />
            </Section>

            {visibleGroups.length > 0 ? (
                <Section title={t('sections.sensitive')} description={t('sensitive.help')}>
                    <ul className="flex flex-col divide-y divide-border">
                        {visibleGroups.map((group) => (
                            <li
                                key={group}
                                className="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
                            >
                                <Lock
                                    aria-hidden
                                    className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span className="text-caption text-muted-foreground">
                                        {t(`sensitive.groups.${group}`)}
                                    </span>
                                    <SensitiveValue
                                        group={group}
                                        sensitive={sensitive}
                                        date={date}
                                    />
                                </div>
                                {can.update && sensitive.access[GROUP_KEYS[group]] === 'write' ? (
                                    <Button
                                        size="icon-sm"
                                        variant="ghost"
                                        onClick={() => setEditingGroup(group)}
                                        aria-label={`${t('common:actions.edit')}: ${t(`sensitive.groups.${group}`)}`}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </Section>
            ) : null}

            {(['personal', 'job'] as const).map((section) => {
                const fields = profile.customFields.filter(
                    (field) => field.section === section && field.access !== 'hidden',
                );

                return fields.length ? (
                    <Section
                        key={section}
                        title={t(`sections.custom.${section}`)}
                        actions={
                            can.update && fields.some((field) => field.access === 'write') ? (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => setEditingCustom(section)}
                                >
                                    <Pencil aria-hidden />
                                    {t('common:actions.edit')}
                                </Button>
                            ) : null
                        }
                    >
                        <Facts
                            items={fields.map((field) => [
                                localized(field.label, locale),
                                <CustomValue key={field.key} field={field} />,
                            ])}
                        />
                    </Section>
                ) : null;
            })}

            {editingProfile ? (
                <ProfileDialog profile={profile} onClose={() => setEditingProfile(false)} />
            ) : null}
            {editingGroup ? (
                <SensitiveDialog
                    employeeId={summary.id}
                    group={editingGroup}
                    sensitive={sensitive}
                    onClose={() => setEditingGroup(null)}
                />
            ) : null}
            {editingCustom ? (
                <CustomFieldsDialog
                    employeeId={summary.id}
                    fields={profile.customFields.filter(
                        (field) => field.section === editingCustom && field.access === 'write',
                    )}
                    onClose={() => setEditingCustom(null)}
                />
            ) : null}
        </div>
    );
}

function SensitiveValue({
    group,
    sensitive,
    date,
}: {
    group: Group;
    sensitive: Sensitive;
    date: (value: string | null | undefined) => string;
}) {
    const { t } = useTranslation('people');
    const empty = <span className="text-muted-foreground">—</span>;

    switch (group) {
        case 'nationalId':
            return sensitive.nationalId ? (
                <bdi dir="ltr" className="text-sm">
                    {sensitive.nationalId}
                </bdi>
            ) : (
                empty
            );
        case 'passport':
            return sensitive.passport?.number ? (
                <span className="text-sm">
                    <bdi dir="ltr">{sensitive.passport.number}</bdi>
                    {sensitive.passport.expiresOn
                        ? ` · ${t('sensitive.expires', { date: date(sensitive.passport.expiresOn) })}`
                        : ''}
                </span>
            ) : (
                empty
            );
        case 'residency':
            return sensitive.residency?.number ? (
                <span className="text-sm">
                    <bdi dir="ltr">{sensitive.residency.number}</bdi>
                    {sensitive.residency.expiresOn
                        ? ` · ${t('sensitive.expires', { date: date(sensitive.residency.expiresOn) })}`
                        : ''}
                </span>
            ) : (
                empty
            );
        case 'bank':
            return sensitive.bank?.iban ? (
                <span className="flex flex-col text-sm">
                    <bdi dir="ltr">{sensitive.bank.iban}</bdi>
                    <span className="text-caption text-muted-foreground">
                        {[sensitive.bank.bank_name, sensitive.bank.account_name]
                            .filter(Boolean)
                            .join(' · ')}
                    </span>
                </span>
            ) : (
                empty
            );
        case 'personalContact':
            return sensitive.personalContact ? (
                <span className="flex flex-col text-sm">
                    {sensitive.personalContact.phone ? (
                        <bdi dir="ltr">{sensitive.personalContact.phone}</bdi>
                    ) : null}
                    {sensitive.personalContact.email ? (
                        <bdi dir="ltr">{sensitive.personalContact.email}</bdi>
                    ) : null}
                    {sensitive.personalContact.emergency_name ? (
                        <span className="text-caption text-muted-foreground">
                            {t('sensitive.emergency', {
                                name: sensitive.personalContact.emergency_name,
                            })}{' '}
                            {sensitive.personalContact.emergency_phone ? (
                                <bdi dir="ltr">{sensitive.personalContact.emergency_phone}</bdi>
                            ) : null}
                        </span>
                    ) : null}
                </span>
            ) : (
                empty
            );
        case 'dateOfBirth':
            return sensitive.dateOfBirth ? (
                <span className="text-sm">{date(sensitive.dateOfBirth)}</span>
            ) : (
                empty
            );
    }
}

function CustomValue({ field }: { field: CustomField }) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const value = field.value as unknown;

    if (value === null || value === undefined || value === '') {
        return <span className="text-muted-foreground">—</span>;
    }

    if (field.type === 'boolean') {
        return <>{value ? t('common:yes') : t('common:no')}</>;
    }

    if (field.type === 'date' && typeof value === 'string') {
        return <>{formatDate(value, locale, { dateStyle: 'medium' })}</>;
    }

    if (field.type === 'select' || field.type === 'multiselect') {
        const values = Array.isArray(value) ? (value as string[]) : [String(value)];
        return (
            <>
                {values
                    .map((v) =>
                        localized(
                            field.options.find((option) => option.value === v)?.label ?? { en: v },
                            locale,
                        ),
                    )
                    .join('، ')}
            </>
        );
    }

    return ['email', 'phone', 'url'].includes(field.type) ? (
        <bdi dir="ltr">{String(value)}</bdi>
    ) : (
        <>{String(value)}</>
    );
}

function ProfileDialog({
    profile,
    onClose,
}: {
    profile: ProfileProps['profile'];
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const summary = profile.summary;
    const form = useForm({
        nameEn: summary.nameEn,
        nameAr: summary.nameAr ?? '',
        preferredName: summary.preferredName ?? '',
        workEmail: summary.workEmail ?? '',
        workPhone: summary.workPhone ?? '',
        gender: profile.gender ?? '',
        nationality: profile.nationality ?? '',
        maritalStatus: profile.maritalStatus ?? '',
        hireDate: summary.hireDate,
        probationEndsOn: profile.probationEndsOn ?? '',
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) =>
            Object.fromEntries(
                Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]),
            ),
        );
        form.put(people.update.url(summary.id), { preserveScroll: true, onSuccess: onClose });
    };

    const select = (key: 'gender' | 'maritalStatus', values: string[], prefix: string) => (
        <Select
            value={form.data[key] || NONE}
            onValueChange={(value) => form.setData(key, value === NONE ? '' : value)}
        >
            <SelectTrigger id={`profile-${key}`}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>{t('common:none')}</SelectItem>
                {values.map((value) => (
                    <SelectItem key={value} value={value}>
                        {t(`${prefix}.${value}`)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('profile.editTitle')}
            className="sm:max-w-2xl"
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="profile-form" loading={form.processing}>
                        {t('common:actions.saveChanges')}
                    </Button>
                </>
            }
        >
            <form
                id="profile-form"
                onSubmit={submit}
                className="grid gap-4 sm:grid-cols-2"
                noValidate
            >
                {errors.error ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-bg p-3 text-small text-danger sm:col-span-2"
                    >
                        {errors.error}
                    </p>
                ) : null}
                <Field
                    id="profile-name-en"
                    label={t('fields.nameEn')}
                    error={errors.nameEn}
                    required
                >
                    <Input
                        id="profile-name-en"
                        dir="ltr"
                        lang="en"
                        value={form.data.nameEn}
                        onChange={(event) => form.setData('nameEn', event.target.value)}
                    />
                </Field>
                <Field id="profile-name-ar" label={t('fields.nameAr')} error={errors.nameAr}>
                    <Input
                        id="profile-name-ar"
                        dir="rtl"
                        lang="ar"
                        value={form.data.nameAr}
                        onChange={(event) => form.setData('nameAr', event.target.value)}
                    />
                </Field>
                <Field
                    id="profile-preferred"
                    label={t('fields.preferredName')}
                    error={errors.preferredName}
                >
                    <Input
                        id="profile-preferred"
                        value={form.data.preferredName}
                        onChange={(event) => form.setData('preferredName', event.target.value)}
                    />
                </Field>
                <Field id="profile-email" label={t('fields.workEmail')} error={errors.workEmail}>
                    <Input
                        id="profile-email"
                        type="email"
                        dir="ltr"
                        value={form.data.workEmail}
                        onChange={(event) => form.setData('workEmail', event.target.value)}
                    />
                </Field>
                <Field id="profile-phone" label={t('fields.workPhone')} error={errors.workPhone}>
                    <Input
                        id="profile-phone"
                        type="tel"
                        dir="ltr"
                        value={form.data.workPhone}
                        onChange={(event) => form.setData('workPhone', event.target.value)}
                    />
                </Field>
                <Field
                    id="profile-nationality"
                    label={t('fields.nationality')}
                    error={errors.nationality}
                    hint={t('hints.countryCode')}
                >
                    <Input
                        id="profile-nationality"
                        dir="ltr"
                        maxLength={2}
                        value={form.data.nationality}
                        onChange={(event) =>
                            form.setData('nationality', event.target.value.toUpperCase())
                        }
                    />
                </Field>
                <Field id="profile-gender" label={t('fields.gender')} error={errors.gender}>
                    {select('gender', ['female', 'male'], 'genders')}
                </Field>
                <Field
                    id="profile-maritalStatus"
                    label={t('fields.maritalStatus')}
                    error={errors.maritalStatus}
                >
                    {select(
                        'maritalStatus',
                        ['single', 'married', 'divorced', 'widowed'],
                        'marital',
                    )}
                </Field>
                <Field
                    id="profile-hire"
                    label={t('fields.hireDate')}
                    error={errors.hireDate}
                    required
                >
                    <Input
                        id="profile-hire"
                        type="date"
                        dir="ltr"
                        value={form.data.hireDate}
                        onChange={(event) => form.setData('hireDate', event.target.value)}
                    />
                </Field>
                <Field
                    id="profile-probation"
                    label={t('fields.probationEndsOn')}
                    error={errors.probationEndsOn}
                >
                    <Input
                        id="profile-probation"
                        type="date"
                        dir="ltr"
                        value={form.data.probationEndsOn}
                        onChange={(event) => form.setData('probationEndsOn', event.target.value)}
                    />
                </Field>
            </form>
        </ResponsiveDialog>
    );
}

function SensitiveDialog({
    employeeId,
    group,
    sensitive,
    onClose,
}: {
    employeeId: string;
    group: Group;
    sensitive: Sensitive;
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const initial: Record<Group, Record<string, string>> = {
        nationalId: { number: sensitive.nationalId ?? '' },
        passport: {
            number: sensitive.passport?.number ?? '',
            country: sensitive.passport?.country ?? '',
            expiresOn: sensitive.passport?.expiresOn ?? '',
        },
        residency: {
            number: sensitive.residency?.number ?? '',
            expiresOn: sensitive.residency?.expiresOn ?? '',
        },
        bank: {
            bankName: sensitive.bank?.bank_name ?? '',
            iban: sensitive.bank?.iban ?? '',
            accountName: sensitive.bank?.account_name ?? '',
        },
        personalContact: {
            phone: sensitive.personalContact?.phone ?? '',
            email: sensitive.personalContact?.email ?? '',
            address: sensitive.personalContact?.address ?? '',
            emergencyName: sensitive.personalContact?.emergency_name ?? '',
            emergencyPhone: sensitive.personalContact?.emergency_phone ?? '',
            emergencyRelation: sensitive.personalContact?.emergency_relation ?? '',
        },
        dateOfBirth: { value: sensitive.dateOfBirth ?? '' },
    };
    const [values, setValues] = useState(initial[group]);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const dateKeys = ['expiresOn', 'value'];
    const ltrKeys = ['number', 'iban', 'phone', 'email', 'emergencyPhone', 'country'];

    const submit = () => {
        const cleaned = Object.fromEntries(
            Object.entries(values).map(([key, value]) => [
                key,
                value.trim() === '' ? null : value.trim(),
            ]),
        );
        const payload =
            group === 'dateOfBirth' ? { dateOfBirth: cleaned.value } : { [group]: cleaned };

        router.put(people.sensitive.update.url(employeeId), payload, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onError: setErrors,
            onSuccess: onClose,
        });
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t(`sensitive.groups.${group}`)}
            description={t('sensitive.editHelp')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {errors[group] || errors.error ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-bg p-3 text-small text-danger sm:col-span-2"
                    >
                        {errors[group] ?? errors.error}
                    </p>
                ) : null}
                {Object.keys(values).map((key) => (
                    <Field
                        key={key}
                        id={`sensitive-${key}`}
                        label={t(`sensitive.fields.${key}`)}
                        error={
                            errors[`${group}.${key}`] ??
                            (group === 'dateOfBirth' ? errors.dateOfBirth : undefined)
                        }
                        className={key === 'address' ? 'sm:col-span-2' : undefined}
                    >
                        <Input
                            id={`sensitive-${key}`}
                            type={dateKeys.includes(key) ? 'date' : 'text'}
                            dir={
                                ltrKeys.includes(key) || dateKeys.includes(key) ? 'ltr' : undefined
                            }
                            autoComplete="off"
                            value={values[key]}
                            onChange={(event) =>
                                setValues({
                                    ...values,
                                    [key]:
                                        key === 'country'
                                            ? event.target.value.toUpperCase()
                                            : event.target.value,
                                })
                            }
                        />
                    </Field>
                ))}
            </div>
        </ResponsiveDialog>
    );
}

function CustomFieldsDialog({
    employeeId,
    fields,
    onClose,
}: {
    employeeId: string;
    fields: CustomField[];
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [values, setValues] = useState<Record<string, unknown>>(
        Object.fromEntries(
            fields.map((field) => [
                field.key,
                field.value ??
                    (field.type === 'multiselect' ? [] : field.type === 'boolean' ? false : ''),
            ]),
        ),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = () =>
        router.put(
            people.customFields.update.url(employeeId),
            {
                values: Object.fromEntries(
                    Object.entries(values).map(([key, value]) => [
                        key,
                        value === '' ? null : value,
                    ]),
                ) as Record<string, string | number | boolean | string[] | null>,
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: setErrors,
                onSuccess: onClose,
            },
        );

    const input = (field: CustomField) => {
        const id = `custom-${field.key}`;
        const value = values[field.key];

        switch (field.type) {
            case 'textarea':
                return (
                    <Textarea
                        id={id}
                        value={String(value ?? '')}
                        onChange={(event) =>
                            setValues({ ...values, [field.key]: event.target.value })
                        }
                    />
                );
            case 'boolean':
                return (
                    <Switch
                        id={id}
                        checked={Boolean(value)}
                        onCheckedChange={(checked) =>
                            setValues({ ...values, [field.key]: checked })
                        }
                    />
                );
            case 'select':
                return (
                    <Select
                        value={String(value || NONE)}
                        onValueChange={(next) =>
                            setValues({ ...values, [field.key]: next === NONE ? '' : next })
                        }
                    >
                        <SelectTrigger id={id}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={NONE}>{t('common:none')}</SelectItem>
                            {field.options.map((option) => (
                                <SelectItem key={option.value} value={option.value}>
                                    {localized(option.label, locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                );
            case 'multiselect': {
                const selected = Array.isArray(value) ? (value as string[]) : [];
                return (
                    <div
                        role="group"
                        aria-labelledby={`${id}-label`}
                        className="flex flex-wrap gap-3"
                    >
                        {field.options.map((option) => (
                            <label key={option.value} className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={selected.includes(option.value)}
                                    onCheckedChange={(checked) =>
                                        setValues({
                                            ...values,
                                            [field.key]: checked
                                                ? [...selected, option.value]
                                                : selected.filter((v) => v !== option.value),
                                        })
                                    }
                                />
                                {localized(option.label, locale)}
                            </label>
                        ))}
                    </div>
                );
            }
            default:
                return (
                    <Input
                        id={id}
                        type={
                            {
                                number: 'number',
                                date: 'date',
                                email: 'email',
                                phone: 'tel',
                                url: 'url',
                            }[field.type] ?? 'text'
                        }
                        dir={
                            ['number', 'date', 'email', 'phone', 'url'].includes(field.type)
                                ? 'ltr'
                                : undefined
                        }
                        value={String(value ?? '')}
                        onChange={(event) =>
                            setValues({ ...values, [field.key]: event.target.value })
                        }
                    />
                );
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('custom.editTitle')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button onClick={submit} loading={processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                {fields.map((field) => (
                    <Field
                        key={field.key}
                        id={`custom-${field.key}`}
                        label={localized(field.label, locale)}
                        hint={localized(field.help, locale) || undefined}
                        error={errors[`custom.${field.key}`]}
                        required={field.isRequired}
                    >
                        {input(field)}
                    </Field>
                ))}
            </div>
        </ResponsiveDialog>
    );
}
