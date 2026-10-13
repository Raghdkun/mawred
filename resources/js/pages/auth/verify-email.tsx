import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation('auth');

    setLayoutProps({ title: t('verify.title'), description: t('verify.description') });

    return (
        <>
            <Head title={t('verify.pageTitle')} />

            {status === 'verification-link-sent' ? (
                <p
                    role="status"
                    className="rounded-md border border-success-border bg-success-bg px-3 py-2 text-small text-success"
                >
                    {t('verify.sent')}
                </p>
            ) : null}

            <Form {...send.form()} className="flex flex-col items-center gap-4">
                {({ processing }) => (
                    <>
                        <Button
                            size="lg"
                            variant="secondary"
                            className="w-full"
                            loading={processing}
                        >
                            {t('verify.resend')}
                        </Button>
                        <TextLink href={logout()} className="text-small">
                            {t('verify.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </>
    );
}
