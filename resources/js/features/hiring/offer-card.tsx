import { router } from '@inertiajs/react';
import { FileText, Mail, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { StatusBadge, type WorkflowStatus } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { formatMinor } from '@/features/hiring/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import hiring from '@/routes/hiring';

type Offer = App.Domain.Hiring.Data.OfferData;

const TONES: Record<string, WorkflowStatus> = {
    draft: 'draft',
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
    sent: 'in-review',
    accepted: 'approved',
    declined: 'rejected',
    withdrawn: 'cancelled',
    expired: 'cancelled',
};

/** An offer on the profile (handoff 32): terms, the letter, and what can be done next. */
export function OfferCard({
    offer,
    canUpdate,
    onEdit,
    hasPhone,
    hasEmail,
}: {
    offer: Offer;
    canUpdate: boolean;
    onEdit: () => void;
    hasPhone: boolean;
    hasEmail: boolean;
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [busy, setBusy] = useState(false);
    const post = (url: string, data: Record<string, string> = {}) => {
        setBusy(true);
        router.post(url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };
    const live = ['draft', 'pending', 'approved', 'sent'].includes(offer.status);

    return (
        <article
            className="flex flex-col gap-3 rounded-md border border-border p-4"
            data-offer={offer.id}
            data-offer-status={offer.status}
        >
            <header className="flex flex-wrap items-center justify-between gap-2">
                <h4 className="text-small font-semibold">{offer.jobTitle}</h4>
                <StatusBadge
                    status={TONES[offer.status] ?? 'draft'}
                    label={t(`offerStatus.${offer.status}`)}
                />
            </header>
            <Facts
                items={[
                    [
                        t('offers.startOn'),
                        formatDate(offer.startOn, locale, { dateStyle: 'medium' }),
                    ],
                    [
                        t('offers.salary'),
                        offer.salary
                            ? formatMinor(offer.salary.amount_minor, offer.salary.currency, locale)
                            : '—',
                    ],
                    [t('offers.branch'), offer.branch ?? '—'],
                    [
                        t('offers.expiresOn'),
                        offer.expiresOn
                            ? formatDate(offer.expiresOn, locale, { dateStyle: 'medium' })
                            : '—',
                    ],
                    ...(offer.signedName
                        ? ([[t('offers.signedBy'), offer.signedName]] as Array<[string, string]>)
                        : []),
                ]}
            />
            {offer.declineReason ? (
                <p className="rounded-md bg-muted p-3 text-small whitespace-pre-line">
                    {offer.declineReason}
                </p>
            ) : null}
            <div className="flex flex-wrap gap-2">
                <Button asChild variant="outline" className="min-h-11">
                    <a href={offer.pdfUrl} target="_blank" rel="noreferrer" data-offer-pdf>
                        <FileText aria-hidden />
                        {offer.status === 'accepted'
                            ? t('offers.signedLetter')
                            : t('offers.preview')}
                    </a>
                </Button>
                {canUpdate && offer.status === 'draft' ? (
                    <>
                        <Button
                            variant="outline"
                            className="min-h-11"
                            onClick={onEdit}
                            data-edit-offer
                        >
                            {t('offers.edit')}
                        </Button>
                        <Button
                            className="min-h-11"
                            disabled={busy}
                            onClick={() => post(hiring.offers.submit.url(offer.id))}
                            data-submit-offer
                        >
                            {t('offers.submit')}
                        </Button>
                    </>
                ) : null}
                {canUpdate && (offer.status === 'approved' || offer.status === 'sent') ? (
                    <>
                        {hasEmail ? (
                            <Button
                                className="min-h-11"
                                disabled={busy}
                                onClick={() =>
                                    post(hiring.offers.send.url(offer.id), { channel: 'email' })
                                }
                                data-send-offer="email"
                            >
                                <Mail aria-hidden />
                                {offer.status === 'sent'
                                    ? t('offers.resendEmail')
                                    : t('offers.sendEmail')}
                            </Button>
                        ) : null}
                        {hasPhone ? (
                            <Button
                                variant="outline"
                                className="min-h-11"
                                disabled={busy}
                                onClick={() =>
                                    post(hiring.offers.send.url(offer.id), { channel: 'whatsapp' })
                                }
                                data-send-offer="whatsapp"
                            >
                                <MessageCircle aria-hidden />
                                {t('offers.sendWhatsApp')}
                            </Button>
                        ) : null}
                    </>
                ) : null}
                {canUpdate && live ? (
                    <Button
                        variant="ghost"
                        className="min-h-11 text-danger"
                        disabled={busy}
                        onClick={() => post(hiring.offers.withdraw.url(offer.id))}
                        data-withdraw-offer
                    >
                        {t('offers.withdraw')}
                    </Button>
                ) : null}
            </div>
        </article>
    );
}
