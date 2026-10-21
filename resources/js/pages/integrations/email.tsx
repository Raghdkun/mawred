import { Head, Link } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { type Bounces, EmailBounces } from '@/features/integrations/email-bounces';
import { type EmailSettings, EmailSettingsForm } from '@/features/integrations/email-settings-form';
import integrations from '@/routes/integrations';

/** Email connection settings (ADR-0015); the same form as setup step 7. */
export default function EmailIntegration({
    email,
    bounces,
}: {
    email: EmailSettings | null;
    bounces: Bounces;
}) {
    const { t } = useTranslation('integrations');

    return (
        <>
            <Head title={t('types.email.name')} />
            <Link
                href={integrations.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('hub.title')}
            </Link>
            <PageHeader title={t('types.email.name')} description={t('types.email.description')} />
            <div className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
                <EmailSettingsForm
                    email={email}
                    saveUrl={integrations.email.update.url()}
                    testUrl={integrations.email.test.url()}
                    saveLabel={t('hub.save')}
                />
            </div>
            {email ? <EmailBounces bounces={bounces} /> : null}
        </>
    );
}
