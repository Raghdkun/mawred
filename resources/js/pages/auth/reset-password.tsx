import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    const { t } = useTranslation('auth');

    setLayoutProps({ title: t('reset.title'), description: t('reset.description') });

    return (
        <>
            <Head title={t('reset.pageTitle')} />
            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-5"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email">{t('login.email')}</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                dir="ltr"
                                autoComplete="username"
                                value={email}
                                readOnly
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="password">{t('reset.newPassword')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                autoFocus
                                required
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="password_confirmation">
                                {t('reset.confirmPassword')}
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                required
                            />
                            <InputError message={errors.password_confirmation} />
                        </div>
                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            loading={processing}
                            data-test="reset-password-button"
                        >
                            {t('reset.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
