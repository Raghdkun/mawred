import { Head, router } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { Field } from '@/components/mw/field';
import { Illustration } from '@/components/mw/illustration';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { PlainText } from '@/features/hiring/plain-text';
import { SignaturePad } from '@/features/hiring/signature-pad';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import careers from '@/routes/careers';

type Props = {
    offer: {
        status: string;
        candidate: string;
        jobTitle: string;
        startOn: string;
        salary: string | null;
        branch: string | null;
        department: string | null;
        manager: string | null;
        expiresOn: string | null;
        letter: string;
        locale: string;
        signedName: string | null;
        respondedAt: string | null;
    };
    pdfUrl: string;
    token: string;
};

/**
 * The candidate's offer (handoff 32, D83): the letter in its own language and direction, the
 * terms, and accepting with a typed full name and a drawn signature, or declining.
 */
export default function OfferPage({ offer, pdfUrl, token }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [name, setName] = useState('');
    const [signature, setSignature] = useState<string | null>(null);
    const [agree, setAgree] = useState(false);
    const [declining, setDeclining] = useState(false);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const rtl = offer.locale === 'ar';

    if (offer.status === 'accepted' || offer.status === 'declined' || offer.status === 'expired') {
        return (
            <div className="mx-auto flex w-full max-w-[640px] flex-col items-center gap-4 px-4 py-12 text-center" data-offer-result={offer.status}>
                <Head title={offer.jobTitle} />
                {offer.status === 'accepted' ? <Illustration name="success-approved" className="w-56" /> : null}
                <h1 className="text-h2">{t(`offerPage.${offer.status}`)}</h1>
                <p className="text-body text-text-secondary">{t(`offerPage.${offer.status}Help`, { job: offer.jobTitle })}</p>
                {offer.status === 'accepted' ? (
                    <Button asChild variant="outline" className="min-h-11">
                        <a href={pdfUrl} target="_blank" rel="noreferrer">
                            <FileText aria-hidden />
                            {t('offerPage.download')}
                        </a>
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 px-4 py-8">
            <Head title={offer.jobTitle} />
            <header className="flex flex-col gap-1">
                <h1 className="text-h2">{t('offerPage.title', { job: offer.jobTitle })}</h1>
                <p className="text-body text-text-secondary">{t('offerPage.intro', { name: offer.candidate })}</p>
            </header>
            <div className="grid gap-8 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)] lg:items-start">
                <article dir={rtl ? 'rtl' : 'ltr'} lang={offer.locale} className="rounded-lg bg-card p-6 shadow-sm" aria-label={t('offers.letter')}>
                    <PlainText text={offer.letter} className="text-body leading-relaxed" />
                    <a href={pdfUrl} target="_blank" rel="noreferrer" className="mt-4 inline-flex min-h-11 items-center gap-1 text-small text-primary hover:underline">
                        <FileText aria-hidden className="size-4" />
                        {t('offerPage.pdf')}
                    </a>
                </article>
                <aside className="flex flex-col gap-4 rounded-lg bg-card p-5 shadow-sm lg:sticky lg:top-6">
                    <Facts
                        items={[
                            [t('offers.jobTitle'), offer.jobTitle],
                            [t('offers.startOn'), formatDate(offer.startOn, locale, { dateStyle: 'long' })],
                            [t('offers.salary'), offer.salary ?? '—'],
                            [t('offers.branch'), offer.branch ?? '—'],
                            ...(offer.manager ? ([[t('offers.manager'), offer.manager]] as Array<[string, string]>) : []),
                            ...(offer.expiresOn ? ([[t('offers.expiresOn'), formatDate(offer.expiresOn, locale, { dateStyle: 'long' })]] as Array<[string, string]>) : []),
                        ]}
                    />
                    {errors.error ? (
                        <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                            {errors.error}
                        </p>
                    ) : null}
                    {declining ? (
                        <div className="flex flex-col gap-3">
                            <label className="flex flex-col gap-1 text-small">
                                {t('offerPage.reason')}
                                <Textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={2000} />
                            </label>
                            <div className="flex justify-end gap-2">
                                <Button variant="ghost" className="min-h-11" onClick={() => setDeclining(false)}>
                                    {t('cancel')}
                                </Button>
                                <Button
                                    variant="destructive"
                                    className="min-h-11"
                                    disabled={sending}
                                    data-confirm-decline
                                    onClick={() => {
                                        setSending(true);
                                        router.post(careers.offers.decline.url(token), { reason: reason || null }, { onError: setErrors, onFinish: () => setSending(false) });
                                    }}
                                >
                                    {t('offerPage.decline')}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <form
                            className="flex flex-col gap-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                setSending(true);
                                router.post(careers.offers.accept.url(token), { name, signature, agree }, { onError: setErrors, onFinish: () => setSending(false) });
                            }}
                        >
                            <h2 className="text-h4">{t('offerPage.sign')}</h2>
                            <Field id="signed-name" label={t('offerPage.name')} error={errors.name} required>
                                <Input id="signed-name" autoComplete="name" value={name} onChange={(e) => setName(e.target.value)} maxLength={160} />
                            </Field>
                            <SignaturePad label={t('signature.label')} onChange={setSignature} describedBy={errors.signature ? 'signature-error' : undefined} />
                            {errors.signature ? (
                                <p id="signature-error" role="alert" className="text-caption text-danger">
                                    {errors.signature}
                                </p>
                            ) : null}
                            <label htmlFor="agree" className="flex min-h-11 items-start gap-2 text-small">
                                <Checkbox id="agree" checked={agree} onCheckedChange={(on) => setAgree(on === true)} className="mt-0.5" data-agree />
                                {t('offerPage.agree')}
                            </label>
                            {errors.agree ? (
                                <p role="alert" className="text-caption text-danger">
                                    {errors.agree}
                                </p>
                            ) : null}
                            <p className="text-caption text-text-secondary">{t('offerPage.legal')}</p>
                            <Button type="submit" className="min-h-11" disabled={sending || !signature || name.trim() === ''} data-accept-offer>
                                {t('offerPage.accept')}
                            </Button>
                            <Button type="button" variant="ghost" className="min-h-11" onClick={() => setDeclining(true)} data-decline-offer>
                                {t('offerPage.declineInstead')}
                            </Button>
                        </form>
                    )}
                </aside>
            </div>
        </div>
    );
}
