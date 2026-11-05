import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';

type InvitationLink = { url: string; email: string; expiresAt: string };

/**
 * The invitation link right after HR invites someone or sends a new invitation (D136). Shown once
 * (Mawred keeps only its hash), so HR can send it themselves when email is slow or not set up.
 */
export function InvitationLinkDialog() {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const { flash } = usePage();
    const link = (flash as { invitationLink?: InvitationLink } | undefined)?.invitationLink ?? null;
    const [dismissed, setDismissed] = useState<string | null>(null);

    if (!link) {
        return null;
    }

    return (
        <ResponsiveDialog
            open={dismissed !== link.url}
            onOpenChange={(open) => {
                if (!open) {
                    setDismissed(link.url);
                }
            }}
            title={t('invitationLink.title')}
            description={t('invitationLink.description', {
                date: formatDate(link.expiresAt, locale, { dateStyle: 'medium' }),
            })}
            footer={
                <Button className="min-h-11" onClick={() => setDismissed(link.url)}>
                    {t('invitationLink.done')}
                </Button>
            }
        >
            <div className="flex flex-col gap-3" data-invitation-link>
                <p className="text-small">
                    {t('invitationLink.for')}{' '}
                    <bdi dir="ltr" className="font-medium">
                        {link.email}
                    </bdi>
                </p>
                <CopyField label={t('invitationLink.label')} value={link.url} />
                <p className="text-caption text-warning">{t('invitationLink.warning')}</p>
            </div>
        </ResponsiveDialog>
    );
}
