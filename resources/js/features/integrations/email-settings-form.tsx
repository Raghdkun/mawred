import { router, useForm } from '@inertiajs/react';
import { CircleCheck, CircleX, Send } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { cn } from '@/lib/utils';

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
 * Outgoing email through any SMTP provider (D9), used by setup step 7 and the integrations hub.
 * The password is stored encrypted and never shown again; leaving it empty keeps the saved one.
 * A test send proves the connection.
 */
export function EmailSettingsForm({
    email,
    saveUrl,
    testUrl,
    saveLabel,
    heading,
    extraActions,
}: {
    email: EmailSettings | null;
    saveUrl: string;
    testUrl: string;
    saveLabel: string;
    heading?: ReactNode;
    extraActions?: ReactNode;
}) {
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
        form.post(saveUrl, { preserveScroll: true });
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
            {heading}
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
                    {saveLabel}
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
                                testUrl,
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
                {extraActions}
            </div>
        </form>
    );
}
