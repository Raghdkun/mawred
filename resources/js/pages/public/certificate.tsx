import { Head } from '@inertiajs/react';
import { BadgeCheck, CircleSlash, Clock3 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Props = {
    serial: string;
    certificate: { holder: string; course: string; issuedOn: string; expiresOn: string | null; state: 'valid' | 'expired' | 'revoked' } | null;
};

const STATES = {
    valid: { icon: BadgeCheck, tone: 'border-status-approved-border bg-status-approved-bg text-status-approved-fg' },
    expired: { icon: Clock3, tone: 'border-status-cancelled-border bg-status-cancelled-bg text-status-cancelled-fg' },
    revoked: { icon: CircleSlash, tone: 'border-status-rejected-border bg-status-rejected-bg text-status-rejected-fg' },
} as const;

/**
 * The public certificate check (D104, handoff 35, mobile-first): the holder, course, dates and
 * whether it's valid, expired or revoked. The serial stays left-to-right in Arabic.
 */
export default function CertificateCheck({ serial, certificate }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const date = (value: string) => formatDate(value, locale, { dateStyle: 'long' });

    return (
        <div className="mx-auto flex w-full max-w-md flex-col items-center gap-6 px-4 py-10 text-center">
            <Head title={t('verify.title')} />
            <h1 className="text-h2">{t('verify.title')}</h1>
            <p className="text-small text-text-secondary">
                {t('verify.serial')} <bdi dir="ltr" className="font-mono">{serial}</bdi>
            </p>
            {certificate ? (
                (() => {
                    const state = STATES[certificate.state];
                    const Icon = state.icon;

                    return (
                        <section className="flex w-full flex-col gap-4 rounded-lg bg-card p-6 shadow-sm" data-certificate-state={certificate.state}>
                            <span className={cn('inline-flex items-center justify-center gap-2 self-center rounded-full border px-4 py-1.5 text-small font-medium', state.tone)}>
                                <Icon aria-hidden className="size-4" />
                                {t(`certificate.state.${certificate.state}`)}
                            </span>
                            <dl className="grid grid-cols-2 gap-3 text-start">
                                <div className="col-span-2">
                                    <dt className="text-caption text-text-secondary">{t('verify.holder')}</dt>
                                    <dd className="text-h4">{certificate.holder}</dd>
                                </div>
                                <div className="col-span-2">
                                    <dt className="text-caption text-text-secondary">{t('verify.course')}</dt>
                                    <dd className="text-body font-medium">{certificate.course}</dd>
                                </div>
                                <div>
                                    <dt className="text-caption text-text-secondary">{t('verify.issued')}</dt>
                                    <dd className="text-small">{date(certificate.issuedOn)}</dd>
                                </div>
                                <div>
                                    <dt className="text-caption text-text-secondary">{t('verify.expires')}</dt>
                                    <dd className="text-small">{certificate.expiresOn ? date(certificate.expiresOn) : t('certificate.noExpiry')}</dd>
                                </div>
                            </dl>
                        </section>
                    );
                })()
            ) : (
                <section className="flex flex-col items-center gap-3" data-certificate-state="unknown">
                    <Illustration name="system-404" className="w-48" />
                    <p className="text-body font-medium">{t('verify.unknown')}</p>
                    <p className="text-small text-text-secondary">{t('verify.unknownHelp')}</p>
                </section>
            )}
        </div>
    );
}
