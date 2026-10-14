import type { FormDataConvertible } from '@inertiajs/core';
import { Link, router, useForm } from '@inertiajs/react';
import {
    CircleCheck,
    CircleX,
    ImageUp,
    Send,
    ShieldCheck,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import { type DragEvent, type FormEvent, Fragment, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BranchForm } from '@/features/organization/branch-form';
import { Illustration } from '@/components/mw/illustration';
import { MawredLogo } from '@/components/mw/mawred-logo';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Switch } from '@/components/ui/switch';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { summarize } from '@/features/policies/values';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import security from '@/routes/security';
import setup from '@/routes/setup';
import { type Step, type StepKey } from './types';

function StepHeading({ title, description }: { title: string; description: string }) {
    return (
        <div className="mb-6 flex flex-col gap-2">
            <h1 className="text-h2 text-foreground">{title}</h1>
            <p className="text-body text-muted-foreground">{description}</p>
        </div>
    );
}

export function WelcomeStep() {
    const { t } = useTranslation('setup');

    return (
        <div className="flex flex-col items-start gap-6">
            <Illustration name="scene-setup-welcome" priority className="h-48 w-auto" />
            <StepHeading title={t('welcome.title')} description={t('welcome.description')} />
            <Button size="lg" onClick={() => router.post(setup.steps.complete.url('welcome'))}>
                {t('welcome.start')}
            </Button>
        </div>
    );
}

type Company = {
    name: { en: string; ar: string };
    legalName: string | null;
    accent: string | null;
};

export function CompanyStep({ company, logoUrl }: { company: Company; logoUrl: string | null }) {
    const { t } = useTranslation('setup');
    const fileInput = useRef<HTMLInputElement>(null);
    const [dragging, setDragging] = useState(false);
    const [preview, setPreview] = useState<string | null>(logoUrl);
    const form = useForm<{
        name: { en: string; ar: string };
        legalName: string;
        accent: string;
        logo: File | null;
        removeLogo: boolean;
    }>({
        name: { en: company.name.en ?? '', ar: company.name.ar ?? '' },
        legalName: company.legalName ?? '',
        accent: company.accent ?? '#4570C0',
        logo: null,
        removeLogo: false,
    });

    useEffect(
        () => () => {
            if (preview?.startsWith('blob:')) URL.revokeObjectURL(preview);
        },
        [preview],
    );

    const pick = (file: File | undefined) => {
        if (!file) return;
        form.setData((data) => ({ ...data, logo: file, removeLogo: false }));
        setPreview(URL.createObjectURL(file));
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        setDragging(false);
        pick(event.dataTransfer.files[0]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(setup.steps.company.url(), { forceFormData: true });
    };

    return (
        <div className="grid gap-8 xl:grid-cols-[minmax(0,640px)_minmax(0,1fr)]">
            <form onSubmit={submit} className="flex flex-col gap-5">
                <StepHeading title={t('company.title')} description={t('company.description')} />
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="name-en">{t('company.nameEn')}</Label>
                        <Input
                            id="name-en"
                            dir="ltr"
                            lang="en"
                            value={form.data.name.en}
                            onChange={(e) =>
                                form.setData('name', { ...form.data.name, en: e.target.value })
                            }
                            required
                        />
                        <InputError message={form.errors['name.en' as keyof typeof form.errors]} />
                    </div>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="name-ar">{t('company.nameAr')}</Label>
                        <Input
                            id="name-ar"
                            dir="rtl"
                            lang="ar"
                            value={form.data.name.ar}
                            onChange={(e) =>
                                form.setData('name', { ...form.data.name, ar: e.target.value })
                            }
                            required
                        />
                        <InputError message={form.errors['name.ar' as keyof typeof form.errors]} />
                    </div>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="legal-name">{t('company.legalName')}</Label>
                    <Input
                        id="legal-name"
                        value={form.data.legalName}
                        onChange={(e) => form.setData('legalName', e.target.value)}
                    />
                    <p className="text-caption text-muted-foreground">
                        {t('company.legalNameHint')}
                    </p>
                </div>

                <div className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium">{t('company.logo')}</span>
                    <div
                        onDragOver={(event) => {
                            event.preventDefault();
                            setDragging(true);
                        }}
                        onDragLeave={() => setDragging(false)}
                        onDrop={onDrop}
                        className={cn(
                            'flex flex-col items-center gap-3 rounded-lg border-[1.5px] border-dashed border-input-hover bg-surface-sunken/50 p-6 text-center transition-colors duration-150',
                            dragging && 'border-water bg-secondary',
                        )}
                    >
                        {preview ? (
                            <img
                                src={preview}
                                alt=""
                                className="max-h-16 max-w-48 object-contain"
                            />
                        ) : (
                            <ImageUp aria-hidden className="size-8 text-muted-foreground" />
                        )}
                        <p className="text-small text-text-secondary">{t('company.logoDrop')}</p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => fileInput.current?.click()}
                            >
                                {t('company.logoChoose')}
                            </Button>
                            {preview ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        form.setData((data) => ({
                                            ...data,
                                            logo: null,
                                            removeLogo: true,
                                        }));
                                        setPreview(null);
                                    }}
                                >
                                    <Trash2 aria-hidden /> {t('company.logoRemove')}
                                </Button>
                            ) : null}
                        </div>
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="sr-only"
                            aria-label={t('company.logo')}
                            onChange={(e) => pick(e.target.files?.[0])}
                        />
                    </div>
                    <p className="text-caption text-muted-foreground">{t('company.logoHint')}</p>
                    <InputError message={form.errors.logo} />
                </div>

                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="accent">{t('company.accent')}</Label>
                    <div className="flex items-center gap-3">
                        <input
                            id="accent-picker"
                            type="color"
                            value={form.data.accent}
                            onChange={(e) => form.setData('accent', e.target.value.toUpperCase())}
                            aria-label={t('company.accent')}
                            className="size-10 cursor-pointer rounded-md border border-input bg-card p-1"
                        />
                        <Input
                            id="accent"
                            dir="ltr"
                            value={form.data.accent}
                            onChange={(e) => form.setData('accent', e.target.value)}
                            className="w-32 font-mono"
                        />
                    </div>
                    <p className="text-caption text-muted-foreground">{t('company.accentHint')}</p>
                    <InputError message={form.errors.accent} />
                </div>

                <Button type="submit" size="lg" className="self-start" loading={form.processing}>
                    {t('company.save')}
                </Button>
            </form>

            {/* Live co-brand preview (D22: company identity only in co-brand areas). */}
            <aside aria-label={t('company.preview')} className="hidden xl:block">
                <p className="mb-2 text-overline text-muted-foreground uppercase">
                    {t('company.previewSignIn')}
                </p>
                <div className="overflow-hidden rounded-xl border border-border bg-background shadow-md">
                    <div className="h-1.5" style={{ backgroundColor: form.data.accent }} />
                    <div className="flex flex-col gap-4 p-5">
                        <div className="flex items-center gap-3">
                            {preview ? (
                                <img src={preview} alt="" className="h-7 max-w-32 object-contain" />
                            ) : null}
                            <span className="font-medium">{form.data.name.en || '—'}</span>
                        </div>
                        <div className="h-9 rounded-md border border-input bg-card" />
                        <div className="h-9 rounded-md border border-input bg-card" />
                        <div className="h-10 rounded-md bg-primary" />
                        <div className="flex items-center justify-center gap-1.5 text-caption text-muted-foreground">
                            <MawredLogo variant="symbol" className="size-4" />
                            {t('shell:mawred')}
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    );
}

type Languages = { defaultLocale: string; defaultDigits: string; hijriByDefault: boolean };

export function LanguagesStep({ languages }: { languages: Languages }) {
    const { t } = useTranslation('setup');
    const form = useForm(languages);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(setup.steps.languages.url());
    };

    return (
        <form onSubmit={submit} className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t('languages.title')} description={t('languages.description')} />
            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">{t('languages.defaultLocale')}</legend>
                <RadioGroup
                    value={form.data.defaultLocale}
                    onValueChange={(value) => form.setData('defaultLocale', value)}
                    className="flex gap-6"
                >
                    {(['en', 'ar'] as const).map((locale) => (
                        <Label key={locale} className="flex items-center gap-2 font-normal">
                            <RadioGroupItem value={locale} />
                            <span lang={locale}>{t(`common:language.${locale}`)}</span>
                        </Label>
                    ))}
                </RadioGroup>
            </fieldset>
            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">{t('languages.defaultDigits')}</legend>
                <RadioGroup
                    value={form.data.defaultDigits}
                    onValueChange={(value) => form.setData('defaultDigits', value)}
                    className="flex flex-wrap gap-6"
                >
                    {(['latn', 'arab'] as const).map((digits) => (
                        <Label key={digits} className="flex items-center gap-2 font-normal">
                            <RadioGroupItem value={digits} />
                            {t(`settings:preferences.digits.${digits}`)}
                        </Label>
                    ))}
                </RadioGroup>
            </fieldset>
            <div className="flex items-start justify-between gap-4">
                <div className="flex flex-col gap-1">
                    <Label htmlFor="hijri-default">{t('languages.hijri')}</Label>
                    <p className="text-small text-muted-foreground">{t('languages.hijriHint')}</p>
                </div>
                <Switch
                    id="hijri-default"
                    checked={form.data.hijriByDefault}
                    onCheckedChange={(checked) => form.setData('hijriByDefault', checked)}
                />
            </div>
            <Button type="submit" size="lg" className="self-start" loading={form.processing}>
                {t('languages.save')}
            </Button>
        </form>
    );
}

/** Step 4: the first branch, with its work week and check-in area (handoff 2: map + radius slider). */
export function BranchStep({ branch }: { branch: App.Domain.Organization.Data.BranchData | null }) {
    const { t } = useTranslation('setup');

    return (
        <div className="flex max-w-[720px] flex-col gap-6">
            <StepHeading title={t('branch.title')} description={t('branch.description')} />
            <BranchForm
                branch={branch}
                action={setup.steps.branch.url()}
                method="post"
                costCenters={[]}
                submitLabel={t('branch.save')}
                secondaryAction={
                    branch ? null : (
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => router.post(setup.steps.skip.url('branch'))}
                        >
                            {t('common:actions.skip')}
                        </Button>
                    )
                }
            />
        </div>
    );
}

export type PolicySummary = {
    values: Record<string, Record<string, FormDataConvertible>>;
    holidayPresets: Array<{ key: string; name: Record<string, string>; installed: boolean }>;
};

const SUMMARY_KEYS = [
    ['leave.type.ANNUAL', 'leave_rule', 'annual'],
    ['overtime.rules', 'overtime', 'overtime'],
    ['attendance.grace', 'grace', 'grace'],
    ['employment.probation', 'probation', 'probation'],
] as const;

/** Step 5: the neutral default rules are already in force; review them and optionally add holidays. */
export function PoliciesStep({ policies }: { policies: PolicySummary }) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const available = policies.holidayPresets.filter((preset) => !preset.installed);
    const form = useForm<{ holidayPreset: string | null }>({ holidayPreset: null });

    return (
        <div className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t('setup.title')} description={t('setup.description')} />
            <section aria-labelledby="policy-review" className="rounded-lg bg-card p-5 shadow-sm">
                <h3 id="policy-review" className="mb-3 text-body font-semibold">
                    {t('setup.review')}
                </h3>
                <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 text-sm">
                    {SUMMARY_KEYS.map(([key, editor, label]) => (
                        <Fragment key={key}>
                            <dt className="text-muted-foreground">{t(`setup.${label}`)}</dt>
                            <dd className="font-medium">
                                {summarize(editor, policies.values[key] ?? null, t, locale)}
                            </dd>
                        </Fragment>
                    ))}
                </dl>
            </section>
            {available.length > 0 ? (
                <div className="flex flex-col gap-1.5">
                    <span id="holiday-preset-label" className="text-sm font-medium">
                        {t('setup.holidays')}
                    </span>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        aria-labelledby="holiday-preset-label"
                        value={form.data.holidayPreset ?? 'none'}
                        onValueChange={(value) =>
                            value && form.setData('holidayPreset', value === 'none' ? null : value)
                        }
                        className="flex-wrap justify-start"
                    >
                        <ToggleGroupItem value="none" className="px-3">
                            {t('setup.noHolidays')}
                        </ToggleGroupItem>
                        {available.map((preset) => (
                            <ToggleGroupItem key={preset.key} value={preset.key} className="px-3">
                                {localized(preset.name, locale)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                </div>
            ) : null}
            <p className="text-small text-text-secondary">{t('setup.later')}</p>
            <Button
                size="lg"
                className="self-start"
                loading={form.processing}
                onClick={() => form.post(setup.steps.policies.url())}
            >
                {t('setup.continue')}
            </Button>
        </div>
    );
}

export type EmailSettings = {
    host: string;
    port: number;
    security: 'starttls' | 'ssl';
    username: string | null;
    fromAddress: string;
    fromName: string;
    hasPassword: boolean;
    status: string;
    lastError: string | null;
};

/**
 * Step 7: outgoing email through any SMTP provider (D9). The password is stored encrypted and
 * never shown again; leaving it empty keeps the saved one. A test send proves the connection.
 */
export function EmailStep({ email }: { email: EmailSettings | null }) {
    const { t } = useTranslation('setup');
    const form = useForm({
        host: email?.host ?? '',
        port: email?.port ?? 587,
        security: email?.security ?? ('starttls' as 'starttls' | 'ssl'),
        username: email?.username ?? '',
        password: '',
        fromAddress: email?.fromAddress ?? '',
        fromName: email?.fromName ?? '',
    });
    const [testing, setTesting] = useState(false);
    const [testError, setTestError] = useState<string | null>(null);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            username: data.username || null,
            password: data.password || null,
        }));
        form.post(setup.steps.email.url(), { preserveScroll: true });
    };

    const text = (
        key: 'host' | 'username' | 'fromAddress' | 'fromName',
        type = 'text',
        autoComplete = 'off',
    ) => (
        <div className="flex flex-col gap-1.5">
            <Label htmlFor={`email-${key}`}>{t(`email.${key}`)}</Label>
            <Input
                id={`email-${key}`}
                type={type}
                dir="ltr"
                autoComplete={autoComplete}
                value={form.data[key]}
                aria-invalid={form.errors[key] !== undefined}
                onChange={(event) => form.setData(key, event.target.value)}
            />
            <InputError message={form.errors[key]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t('email.title')} description={t('email.description')} />
            <div className="grid gap-4 sm:grid-cols-[1fr_120px]">
                {text('host')}
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="email-port">{t('email.port')}</Label>
                    <Input
                        id="email-port"
                        type="number"
                        dir="ltr"
                        min={1}
                        max={65535}
                        value={form.data.port}
                        aria-invalid={form.errors.port !== undefined}
                        onChange={(event) => form.setData('port', Number(event.target.value))}
                    />
                    <InputError message={form.errors.port} />
                </div>
            </div>
            <fieldset className="flex flex-col gap-2">
                <legend className="mb-1 text-sm font-medium">{t('email.security')}</legend>
                <RadioGroup
                    value={form.data.security}
                    onValueChange={(value) => {
                        form.setData((data) => ({
                            ...data,
                            security: value as 'starttls' | 'ssl',
                            port: value === 'ssl' ? 465 : data.port === 465 ? 587 : data.port,
                        }));
                    }}
                    className="flex flex-wrap gap-6"
                >
                    {(['starttls', 'ssl'] as const).map((security) => (
                        <Label key={security} className="flex items-center gap-2 font-normal">
                            <RadioGroupItem value={security} />
                            {t(`email.securities.${security}`)}
                        </Label>
                    ))}
                </RadioGroup>
            </fieldset>
            <div className="grid gap-4 sm:grid-cols-2">
                {text('username', 'text', 'username')}
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="email-password">{t('email.password')}</Label>
                    <Input
                        id="email-password"
                        type="password"
                        dir="ltr"
                        autoComplete="new-password"
                        placeholder={email?.hasPassword ? t('email.passwordSaved') : undefined}
                        value={form.data.password}
                        onChange={(event) => form.setData('password', event.target.value)}
                    />
                    <InputError message={form.errors.password} />
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                {text('fromAddress', 'email', 'email')}
                {text('fromName')}
            </div>

            {email ? (
                <div
                    role="status"
                    className={cn(
                        'flex items-start gap-2 rounded-lg border p-3 text-small',
                        email.status === 'healthy'
                            ? 'border-success-border bg-success-bg text-success'
                            : email.status === 'disconnected'
                              ? 'border-danger-border bg-danger-bg text-danger'
                              : 'border-border bg-muted text-text-secondary',
                    )}
                >
                    {email.status === 'healthy' ? (
                        <CircleCheck aria-hidden className="mt-0.5 size-4" />
                    ) : email.status === 'disconnected' ? (
                        <CircleX aria-hidden className="mt-0.5 size-4" />
                    ) : null}
                    <span>
                        {t(
                            `email.status.${email.status === 'healthy' || email.status === 'disconnected' ? email.status : 'unverified'}`,
                        )}
                        {email.status === 'disconnected' && email.lastError ? (
                            <bdi className="mt-1 block break-all opacity-80">{email.lastError}</bdi>
                        ) : null}
                    </span>
                </div>
            ) : null}
            {testError ? (
                <p role="alert" className="text-small text-danger">
                    {testError}
                </p>
            ) : null}

            <div className="flex flex-wrap items-center gap-3">
                <Button type="submit" size="lg" loading={form.processing}>
                    {t('email.save')}
                </Button>
                {email ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        loading={testing}
                        onClick={() => {
                            setTesting(true);
                            setTestError(null);
                            router.post(
                                setup.steps.email.test.url(),
                                {},
                                {
                                    preserveScroll: true,
                                    onError: (errors) => setTestError(errors.error ?? null),
                                    onFinish: () => setTesting(false),
                                },
                            );
                        }}
                    >
                        <Send aria-hidden className="rtl:-scale-x-100" />
                        {t('email.test')}
                    </Button>
                ) : null}
                <Button
                    type="button"
                    variant="ghost"
                    size="lg"
                    onClick={() => router.post(setup.steps.skip.url('email'))}
                >
                    {t('common:actions.skip')}
                </Button>
            </div>
        </form>
    );
}

export function LaterStep({ step }: { step: Step }) {
    const { t } = useTranslation('setup');

    return (
        <div className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t(`steps.${step.key}`)} description={t('later.title')} />
            <p className="rounded-lg border border-info-border bg-info-bg p-4 text-small text-info">
                {t('later.description', {
                    step: t(`steps.${step.key}`),
                    module: t(`later.modules.${step.key}`),
                })}
            </p>
            <Button
                variant="secondary"
                size="lg"
                className="self-start"
                onClick={() => router.post(setup.steps.skip.url(step.key))}
            >
                {t('common:actions.skip')}
            </Button>
        </div>
    );
}

export function AdminStep({
    admin,
}: {
    admin: { name: string; email: string; twoFactorEnabled: boolean };
}) {
    const { t } = useTranslation('setup');

    return (
        <div className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t('admin.title')} description={t('admin.description')} />
            <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-6 gap-y-2 rounded-lg bg-card p-5 text-sm shadow-sm">
                <dt className="text-muted-foreground">{t('admin.name')}</dt>
                <dd className="font-medium">{admin.name}</dd>
                <dt className="text-muted-foreground">{t('admin.email')}</dt>
                <dd>
                    <bdi dir="ltr">{admin.email}</bdi>
                </dd>
            </dl>
            {admin.twoFactorEnabled ? (
                <p className="flex items-center gap-2 text-small text-success">
                    <ShieldCheck aria-hidden className="size-5" /> {t('admin.twoFactorOn')}
                </p>
            ) : (
                <div className="flex flex-col items-start gap-3">
                    <p className="flex items-center gap-2 text-small text-warning">
                        <ShieldAlert aria-hidden className="size-5" /> {t('admin.twoFactorOff')}
                    </p>
                    <Button asChild>
                        <Link href={security.edit()}>{t('admin.goToSecurity')}</Link>
                    </Button>
                </div>
            )}
            <Button
                variant="secondary"
                className="self-start"
                onClick={() => router.get(setup.show.url())}
            >
                {t('common:actions.continue')}
            </Button>
        </div>
    );
}

export function ReviewStep({ steps, complete }: { steps: Step[]; complete: boolean }) {
    const { t } = useTranslation('setup');
    const [finishing, setFinishing] = useState(false);
    const missing = steps.filter((step) => step.required && step.status !== 'completed');

    if (complete) {
        return (
            <div className="flex flex-col items-center gap-6 py-6 text-center">
                <Illustration name="scene-setup-all-set" priority className="h-56 w-auto" />
                <div className="flex max-w-md flex-col gap-2">
                    <h1 className="text-h2">{t('done.title')}</h1>
                    <p className="text-body text-muted-foreground">{t('done.description')}</p>
                </div>
                <Button size="lg" asChild>
                    <Link href="/">{t('done.go')}</Link>
                </Button>
            </div>
        );
    }

    return (
        <div className="flex max-w-[640px] flex-col gap-6">
            <StepHeading title={t('review.title')} description={t('review.description')} />
            <ul className="flex flex-col divide-y divide-border rounded-lg bg-card shadow-sm">
                {steps
                    .filter((step) => step.key !== 'review')
                    .map((step) => (
                        <li
                            key={step.key}
                            className="flex items-center justify-between gap-3 px-4 py-3"
                        >
                            <span className="text-sm">{t(`steps.${step.key}`)}</span>
                            <span className="flex items-center gap-3">
                                <span
                                    className={cn(
                                        'text-caption',
                                        step.status === 'completed' && 'text-success',
                                        step.status === 'skipped' && 'text-muted-foreground',
                                        step.status === 'pending' &&
                                            (step.required
                                                ? 'text-warning'
                                                : 'text-muted-foreground'),
                                    )}
                                >
                                    {t(`status.${step.status}`)}
                                </span>
                                <Link
                                    href={setup.show(step.key)}
                                    className="text-small text-link hover:underline"
                                >
                                    {t('review.edit')}
                                </Link>
                            </span>
                        </li>
                    ))}
            </ul>
            {missing.length > 0 ? (
                <p className="text-small text-warning">
                    {t('review.missing', {
                        steps: missing.map((step) => t(`steps.${step.key}`)).join('، '),
                    })}
                </p>
            ) : null}
            <Button
                size="lg"
                className="self-start"
                disabled={missing.length > 0}
                loading={finishing}
                onClick={() => {
                    setFinishing(true);
                    router.post(setup.finish.url(), {}, { onFinish: () => setFinishing(false) });
                }}
            >
                {t('review.finish')}
            </Button>
        </div>
    );
}

export function stepIsLater(step: Step | undefined): boolean {
    return (step?.availableInPhase ?? 0) > 0;
}

export type { StepKey };
