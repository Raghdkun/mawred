import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { SuccessRipple } from '@/components/mw/success-ripple';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { email } from '@/routes/password';
import { ArrowLeft } from 'lucide-react';

/** Forgot password (handoff 1b): success shows the "check your email" state with a ripple. */
export default function ForgotPassword({ status }: { status?: string }) {
    const { t } = useTranslation('auth');

    setLayoutProps(
        status
            ? { title: t('forgot.sentTitle'), description: t('forgot.sentDescription') }
            : { title: t('forgot.title'), description: t('forgot.description') },
    );

    const back = (
        <TextLink
            href={login()}
            className="inline-flex items-center gap-1.5 self-center text-small"
        >
            <DirIcon>
                <ArrowLeft aria-hidden className="size-4" />
            </DirIcon>
            {t('forgot.backToLogin')}
        </TextLink>
    );

    if (status) {
        return (
            <>
                <Head title={t('forgot.pageTitle')} />
                <SuccessRipple label={t('forgot.sentTitle')} />
                {back}
            </>
        );
    }

    return (
        <>
            <Head title={t('forgot.pageTitle')} />
            <Form {...email.form()} className="flex flex-col gap-5">
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
                                autoFocus
                                required
                                aria-invalid={errors.email ? true : undefined}
                            />
                            <InputError message={errors.email} />
                        </div>
                        <Button
                            size="lg"
                            className="w-full"
                            loading={processing}
                            data-test="email-password-reset-link-button"
                        >
                            {t('forgot.submit')}
                        </Button>
                    </>
                )}
            </Form>
            {back}
        </>
    );
}
