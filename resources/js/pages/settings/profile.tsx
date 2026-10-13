import { Form, Head, Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Section } from '@/components/mw/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { send } from '@/routes/verification';

export default function Profile({
    mustVerifyEmail,
    status,
}: {
    mustVerifyEmail: boolean;
    status?: string;
}) {
    const { t } = useTranslation('settings');
    const { auth } = usePage().props;

    if (!auth) {
        return null;
    }

    return (
        <>
            <Head title={t('profile.pageTitle')} />
            <Section title={t('profile.title')} description={t('profile.description')}>
                <Form
                    {...ProfileController.update.form()}
                    options={{ preserveScroll: true }}
                    className="flex flex-col gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="name">{t('profile.name')}</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    defaultValue={auth.user.name}
                                    required
                                    autoComplete="name"
                                    aria-invalid={errors.name ? true : undefined}
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label htmlFor="email">{t('profile.email')}</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    dir="ltr"
                                    defaultValue={auth.user.email}
                                    required
                                    autoComplete="username"
                                    aria-invalid={errors.email ? true : undefined}
                                />
                                <InputError message={errors.email} />
                            </div>

                            {mustVerifyEmail && !auth.user.emailVerified ? (
                                <div className="flex flex-col gap-2 rounded-md border border-warning-border bg-warning-bg p-3 text-small text-warning">
                                    <p>{t('profile.unverified')}</p>
                                    <Link
                                        href={send()}
                                        as="button"
                                        className="self-start font-medium underline underline-offset-4"
                                    >
                                        {t('profile.resend')}
                                    </Link>
                                    {status === 'verification-link-sent' ? (
                                        <p role="status">{t('profile.linkSent')}</p>
                                    ) : null}
                                </div>
                            ) : null}

                            <Button
                                className="self-start"
                                loading={processing}
                                data-test="update-profile-button"
                            >
                                {t('profile.save')}
                            </Button>
                        </>
                    )}
                </Form>
            </Section>
        </>
    );
}
