import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import setup from '@/routes/setup';

/** First Super Admin sets a password from the one-time link printed by `mawred:install` (D4). */
export default function SetupClaim({
    token,
    name,
    email,
    passwordRules,
}: {
    token: string;
    name: string;
    email: string;
    passwordRules: string;
}) {
    const { t } = useTranslation('auth');

    setLayoutProps({ title: t('claim.title'), description: t('claim.description') });

    return (
        <>
            <Head title={t('claim.pageTitle')} />
            <Form {...setup.claim.store.form(token)} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="name">{t('claim.name')}</Label>
                            <Input id="name" value={name} readOnly />
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="email">{t('login.email')}</Label>
                            <Input
                                id="email"
                                dir="ltr"
                                value={email}
                                readOnly
                                autoComplete="username"
                            />
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
                        </div>
                        <Button type="submit" size="lg" className="w-full" loading={processing}>
                            {t('claim.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
