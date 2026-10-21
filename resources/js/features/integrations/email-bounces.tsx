import { router, usePage } from '@inertiajs/react';
import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import integrations from '@/routes/integrations';

export type Bounces = {
    tokenLast4: string | null;
    suppressed: Array<{
        id: string;
        email: string;
        reason: 'hard_bounce' | 'spam_complaint';
        detail: string | null;
        at: string;
    }>;
};

/**
 * Postmark bounce and complaint handling (D127): the webhook URL (shown once when issued) and
 * the addresses Mawred has stopped emailing, each of which an admin can allow again.
 */
export function EmailBounces({ bounces }: { bounces: Bounces }) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const { flash } = usePage();
    const fresh = (flash as { bounceUrl?: string } | undefined)?.bounceUrl ?? null;

    return (
        <section
            aria-labelledby="email-bounces"
            className="mt-6 flex flex-col gap-4 rounded-lg bg-card p-5 shadow-sm sm:p-6"
            data-email-bounces
        >
            <div>
                <h2 id="email-bounces" className="text-h4">
                    {t('bounces.title')}
                </h2>
                <p className="mt-1 text-small text-text-secondary">{t('bounces.help')}</p>
            </div>
            {fresh ? (
                <div className="flex flex-col gap-2">
                    <CopyField label={t('bounces.url')} value={fresh} />
                    <p className="text-caption text-warning">{t('bounces.copyNow')}</p>
                </div>
            ) : bounces.tokenLast4 ? (
                <p className="text-small text-text-secondary">
                    {t('bounces.current', { last4: bounces.tokenLast4 })}
                </p>
            ) : null}
            <Button
                variant="outline"
                className="min-h-11 self-start"
                onClick={() =>
                    router.post(
                        integrations.email.bounces.rotate.url(),
                        {},
                        { preserveScroll: true },
                    )
                }
                data-bounce-token
            >
                <RefreshCw aria-hidden />
                {bounces.tokenLast4 ? t('bounces.rotate') : t('bounces.create')}
            </Button>
            <div>
                <h3 className="mb-2 text-small font-medium">{t('bounces.suppressedTitle')}</h3>
                {bounces.suppressed.length === 0 ? (
                    <p className="text-small text-text-secondary">{t('bounces.suppressedEmpty')}</p>
                ) : (
                    <ul
                        className="flex flex-col divide-y divide-border rounded-md border border-border"
                        data-suppressed
                    >
                        {bounces.suppressed.map((s) => (
                            <li key={s.id} className="flex flex-wrap items-center gap-3 p-3">
                                <div className="flex min-w-0 flex-1 flex-col">
                                    <bdi className="truncate text-small font-medium">{s.email}</bdi>
                                    <span className="text-caption text-text-secondary">
                                        {t(`bounces.reasons.${s.reason}`)} ·{' '}
                                        {formatDate(s.at, locale, { dateStyle: 'medium' })}
                                        {s.detail ? ` · ${s.detail}` : ''}
                                    </span>
                                </div>
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    aria-label={t('bounces.allowLabel', { email: s.email })}
                                    onClick={() =>
                                        router.delete(
                                            integrations.email.suppressions.destroy.url(s.id),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('bounces.allow')}
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </section>
    );
}
