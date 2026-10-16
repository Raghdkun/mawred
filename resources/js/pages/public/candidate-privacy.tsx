import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { Button } from '@/components/ui/button';
import careers from '@/routes/careers';

/**
 * A candidate asks for their details to be deleted (D96). Their name, contact details, CV,
 * answers and messages go at once; open applications are withdrawn.
 */
export default function CandidatePrivacy({ deleted, token }: { deleted: boolean; token: string }) {
    const { t } = useTranslation('hiring');
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);

    return (
        <div className="mx-auto flex w-full max-w-[640px] flex-col items-center gap-4 px-4 py-12 text-center">
            <Head title={t('privacyPage.title')} />
            {deleted ? (
                <>
                    <Illustration name="success-approved" className="w-56" />
                    <h1 className="text-h2" data-deleted>
                        {t('privacyPage.done')}
                    </h1>
                    <p className="text-body text-text-secondary">{t('privacyPage.doneHelp')}</p>
                </>
            ) : (
                <>
                    <h1 className="text-h2">{t('privacyPage.title')}</h1>
                    <p className="text-body text-text-secondary">{t('privacyPage.body')}</p>
                    {error ? (
                        <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                            {error}
                        </p>
                    ) : null}
                    <Button
                        variant="destructive"
                        className="min-h-11"
                        disabled={sending}
                        data-forget
                        onClick={() => {
                            setSending(true);
                            router.post(careers.privacy.forget.url(token), {}, { onError: (e) => setError(e.error ?? null), onFinish: () => setSending(false) });
                        }}
                    >
                        {t('privacyPage.confirm')}
                    </Button>
                </>
            )}
        </div>
    );
}
