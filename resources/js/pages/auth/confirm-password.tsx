import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/input-error';
import PasskeyVerify from '@/components/passkey-verify';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/password/confirm';

export default function ConfirmPassword() {
    const { t } = useTranslation('auth');

    setLayoutProps({ title: t('confirm.title'), description: t('confirm.description') });

    return (
        <>
            <Head title={t('confirm.pageTitle')} />

            <PasskeyVerify
                routes={{ options: confirmOptions(), submit: confirmStore() }}
                label={t('confirm.passkey')}
                loadingLabel={t('confirm.passkeyLoading')}
                separator={t('confirm.or')}
            />

            <Form {...store.form()} resetOnSuccess={['password']} className="flex flex-col gap-5">
                {({ processing, errors }) => (
                    <>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="password">{t('login.password')}</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                autoComplete="current-password"
                                autoFocus
                                required
                            />
                            <InputError message={errors.password} />
                        </div>
                        <Button
                            type="submit"
                            size="lg"
                            className="w-full"
                            loading={processing}
                            data-test="confirm-password-button"
                        >
                            {t('confirm.submit')}
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}
