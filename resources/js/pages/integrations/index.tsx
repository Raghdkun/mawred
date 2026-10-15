import { Head, Link, router } from '@inertiajs/react';
import { FileText, MessageCircle, Mail, RefreshCw, Settings2, Workflow } from 'lucide-react';
import { type ComponentType, type ReactNode, type SVGProps, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { PageHeader } from '@/components/mw/page-header';
import { HealthBadge, type IntegrationHealth } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import integrations from '@/routes/integrations';

type Card = App.Domain.Integrations.Data.IntegrationCardData;

const ICONS: Record<string, ComponentType<SVGProps<SVGSVGElement>>> = {
    email: Mail,
    whatsapp_official: MessageCircle,
    whatsapp_waha: MessageCircle,
    cognito: FileText,
};

const SETTINGS: Record<string, string | undefined> = {
    email: integrations.email.url(),
    cognito: integrations.cognito.index.url(),
    whatsapp_official: integrations.whatsapp.url(),
    whatsapp_waha: integrations.whatsapp.url(),
};

export function healthOf(status: string): IntegrationHealth {
    return status === 'healthy' || status === 'degraded' || status === 'disconnected'
        ? status
        : 'not-configured';
}

/** Integrations hub (handoff 41): one card per connection, health first. */
export default function IntegrationsHub({
    cards,
    can,
}: {
    cards: Card[];
    can: { configure: boolean };
}) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const [checking, setChecking] = useState<string | null>(null);

    return (
        <>
            <Head title={t('hub.pageTitle')} />
            <PageHeader title={t('hub.title')} description={t('hub.description')} />

            <ul className="grid gap-4 md:grid-cols-2">
                {cards.map((card) => {
                    const Icon = ICONS[card.type] ?? Workflow;
                    const configured = card.status !== 'not_configured';
                    const settings = SETTINGS[card.type];

                    return (
                        <li
                            key={card.type}
                            className="flex flex-col gap-4 rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight"
                        >
                            <div className="flex items-start gap-3">
                                <span className="flex size-10 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                    <Icon aria-hidden className="size-5" />
                                </span>
                                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <h2 className="text-h4">{t(`types.${card.type}.name`)}</h2>
                                    <p className="text-small text-text-secondary">
                                        {t(`types.${card.type}.description`)}
                                    </p>
                                </div>
                                <HealthBadge
                                    health={healthOf(card.status)}
                                    label={
                                        card.status === 'unverified'
                                            ? t('hub.unverified')
                                            : undefined
                                    }
                                />
                            </div>

                            {configured ? (
                                <Facts
                                    items={[
                                        ...Object.entries(card.details).map(
                                            ([key, value]): [string, ReactNode] => [
                                                t(`details.${key}`),
                                                value === null ? null : (
                                                    <bdi dir="ltr" className="break-all">
                                                        {value}
                                                    </bdi>
                                                ),
                                            ],
                                        ),
                                        [
                                            t('hub.lastSuccess'),
                                            card.lastSuccessAt
                                                ? formatDate(card.lastSuccessAt, locale, {
                                                      dateStyle: 'medium',
                                                      timeStyle: 'short',
                                                  })
                                                : null,
                                        ],
                                        [
                                            t('hub.lastFailure'),
                                            card.lastFailureAt
                                                ? formatDate(card.lastFailureAt, locale, {
                                                      dateStyle: 'medium',
                                                      timeStyle: 'short',
                                                  })
                                                : null,
                                        ],
                                    ]}
                                />
                            ) : (
                                <p className="text-small text-muted-foreground">
                                    {t('hub.notConfigured')}
                                </p>
                            )}

                            {card.lastError && card.status !== 'healthy' ? (
                                <p
                                    dir="ltr"
                                    className="rounded-md bg-danger-bg p-3 text-caption break-all text-danger"
                                >
                                    {card.lastError}
                                </p>
                            ) : null}

                            {can.configure ? (
                                <div className="mt-auto flex flex-wrap gap-2">
                                    {settings ? (
                                        <Button
                                            variant={configured ? 'outline' : 'default'}
                                            size="sm"
                                            asChild
                                        >
                                            <Link href={settings}>
                                                <Settings2 aria-hidden />
                                                {configured ? t('hub.edit') : t('hub.setUp')}
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span className="text-caption text-muted-foreground">
                                            {t('hub.comingSoon')}
                                        </span>
                                    )}
                                    {configured ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            loading={checking === card.type}
                                            onClick={() => {
                                                setChecking(card.type);
                                                router.post(
                                                    integrations.check.url(card.type),
                                                    {},
                                                    {
                                                        preserveScroll: true,
                                                        onFinish: () => setChecking(null),
                                                    },
                                                );
                                            }}
                                        >
                                            <RefreshCw aria-hidden />
                                            {t('hub.test')}
                                        </Button>
                                    ) : null}
                                </div>
                            ) : null}
                        </li>
                    );
                })}
            </ul>
        </>
    );
}
