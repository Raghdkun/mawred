import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

/** Login (handoff screen 1): split layout, hero on the reading-end half. */
export default function Login({ status, canResetPassword }: Props) {
    const { t } = useTranslation('auth');

    setLayoutProps({
        title: t('login.title'),
        description: t('login.description'),
        variant: 'split',
    });

    return (
        <>
            <Head title={t('login.pageTitle')} />

            {status ? (
                <p
                    role="status"
                    className="rounded-md border border-success-border bg-success-bg px-3 py-2 text-small text-success"
                >
                    {status}
                </p>
            ) : null}

            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email">{t('login.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                dir="ltr"
                                required
                                autoFocus
                                autoComplete="username"
                                aria-invalid={errors.email ? true : undefined}
                                aria-describedby={errors.email ? 'email-error' : undefined}
                            />
                            <InputError id="email-error" message={errors.email} />
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="password">{t('login.password')}</Label>
                                {canResetPassword ? (
                                    <TextLink href={request()} className="text-small">
                                        {t('login.forgot')}
                                    </TextLink>
                                ) : null}
                            </div>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="current-password"
                                aria-invalid={errors.password ? true : undefined}
                            />
                            <InputError message={errors.password} />
                        </div>

                        <div className="flex items-center gap-3">
                            <Checkbox id="remember" name="remember" />
                            <Label htmlFor="remember" className="font-normal">
                                {t('login.remember')}
                            </Label>
                        </div>

                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            loading={processing}
                            data-test="login-button"
                        >
                            {t('login.submit')}
                        </Button>
                    </>
                )}
            </Form>

            <PasskeyVerify placement="after" />
        </>
    );
}
