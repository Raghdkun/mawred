import { Head, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

/** Shown to everyone except Super Admins until setup is finished (brief §4). */
export default function SetupPending() {
    const { t } = useTranslation('setup');

    return (
        <>
            <Head title={t('pending.pageTitle')} />
            <div
                role="status"
                className="mx-auto flex max-w-md flex-1 flex-col items-center justify-center gap-5 px-4 py-12 text-center"
            >
                <Illustration name="system-maintenance" priority className="h-48 w-auto" />
                <h1 className="text-h2">{t('errors:setupPending.title')}</h1>
                <p className="text-body text-muted-foreground">
                    {t('errors:setupPending.description')}
                </p>
                <Button variant="outline" onClick={() => router.post(logout.url())}>
                    {t('pending.signOut')}
                </Button>
            </div>
        </>
    );
}
