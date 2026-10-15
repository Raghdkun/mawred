import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronLeft,
    MessageCircle,
    RefreshCw,
    Send,
    ShieldAlert,
    Smartphone,
    TriangleAlert,
    Users,
} from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import { HealthBadge } from '@/components/mw/status-badge';
import { SuccessRipple } from '@/components/mw/success-ripple';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import integrations from '@/routes/integrations';
import { healthOf } from './index';

type Official = {
    phoneNumberId: string;
    wabaId: string;
    accessToken: string | null;
    appSecret: string | null;
    verifyToken: string | null;
    status: string;
    lastError: string | null;
};
type Waha = {
    baseUrl: string;
    session: string;
    apiKey: string | null;
    status: string;
    lastError: string | null;
};
type Template = {
    name: string;
    language: string;
    status: string;
    category: string | null;
    params: number;
    buttons: string[];
    syncedAt: string;
};
type Route = { category: string; critical: boolean; chain: string[]; risky: boolean };

type Props = {
    official: Official | null;
    waha: Waha | null;
    webhooks: { official: string; waha: string };
    templates: Template[];
    routing: Route[];
    verificationTemplate: string | null;
    myNumber: string | null;
    recent: Array<{
        id: string;
        driver: string;
        purpose: string;
        to: string | null;
        status: string;
        error: string | null;
        at: string;
    }>;
};

type Step = 'driver' | 'official' | 'waha' | 'routing' | 'test';
type Choice = 'official' | 'waha' | 'both';

/** WhatsApp setup (handoff 40): a wizard with a side rail; a compact stepper on phones. */
export default function WhatsAppSetup(props: Props) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const [choice, setChoice] = useState<Choice>(
        props.official && props.waha ? 'both' : props.waha ? 'waha' : 'official',
    );
    const steps: Step[] = [
        'driver',
        ...(choice !== 'waha' ? (['official'] as const) : []),
        ...(choice !== 'official' ? (['waha'] as const) : []),
        'routing',
        'test',
    ];
    const [step, setStep] = useState<Step>('driver');
    const current = steps.includes(step) ? step : 'driver';
    const index = steps.indexOf(current);
    const done = (s: Step) =>
        s === 'official'
            ? props.official?.status === 'healthy'
            : s === 'waha'
              ? props.waha?.status === 'healthy'
              : false;

    return (
        <>
            <Head title={t('whatsapp.pageTitle')} />
            <Link
                href={integrations.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('hub.title')}
            </Link>
            <PageHeader title={t('whatsapp.title')} description={t('whatsapp.description')} />

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <nav
                    aria-label={t('whatsapp.steps')}
                    className="lg:sticky lg:top-24 lg:w-64 lg:shrink-0"
                >
                    <ol className="flex gap-2 overflow-x-auto pb-1 lg:flex-col lg:gap-1">
                        {steps.map((s, i) => (
                            <li key={s} className="shrink-0">
                                <button
                                    type="button"
                                    aria-current={s === current ? 'step' : undefined}
                                    onClick={() => setStep(s)}
                                    className={cn(
                                        'flex min-h-11 items-center gap-3 rounded-md px-3 text-start text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                        s === current &&
                                            'bg-sidebar-accent font-medium text-sidebar-accent-foreground',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-6 items-center justify-center rounded-full border text-caption',
                                            done(s)
                                                ? 'border-success bg-success text-primary-foreground'
                                                : 'border-border',
                                        )}
                                    >
                                        {done(s) ? (
                                            <Check aria-hidden className="size-3.5" />
                                        ) : (
                                            formatNumber(i + 1, locale)
                                        )}
                                    </span>
                                    {t(`whatsapp.stepNames.${s}`)}
                                </button>
                            </li>
                        ))}
                    </ol>
                </nav>

                <div className="min-w-0 flex-1">
                    {current === 'driver' ? (
                        <DriverStep choice={choice} onChoose={setChoice} />
                    ) : null}
                    {current === 'official' ? <OfficialStep {...props} /> : null}
                    {current === 'waha' ? <WahaStep {...props} /> : null}
                    {current === 'routing' ? <RoutingStep {...props} /> : null}
                    {current === 'test' ? <TestStep {...props} /> : null}

                    <div className="mt-6 flex justify-between gap-2">
                        <Button
                            variant="ghost"
                            disabled={index === 0}
                            onClick={() => setStep(steps[index - 1] ?? 'driver')}
                        >
                            {t('whatsapp.back')}
                        </Button>
                        <Button
                            variant="outline"
                            disabled={index === steps.length - 1}
                            onClick={() => setStep(steps[index + 1] ?? 'test')}
                        >
                            {t('whatsapp.next')}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

function DriverStep({ choice, onChoose }: { choice: Choice; onChoose: (choice: Choice) => void }) {
    const { t } = useTranslation('integrations');

    return (
        <Section title={t('whatsapp.stepNames.driver')} description={t('whatsapp.driverHelp')}>
            <div
                role="radiogroup"
                aria-label={t('whatsapp.stepNames.driver')}
                className="grid gap-3 md:grid-cols-3"
            >
                {(['official', 'waha', 'both'] as const).map((option) => (
                    <button
                        key={option}
                        type="button"
                        role="radio"
                        aria-checked={choice === option}
                        onClick={() => onChoose(option)}
                        className={cn(
                            'flex flex-col gap-2 rounded-lg border p-4 text-start hover:border-primary/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            choice === option
                                ? 'border-primary ring-1 ring-primary'
                                : 'border-border',
                        )}
                    >
                        <span className="flex items-center gap-2 font-medium">
                            {option === 'waha' ? (
                                <Smartphone aria-hidden className="size-4" />
                            ) : (
                                <MessageCircle aria-hidden className="size-4" />
                            )}
                            {t(`whatsapp.drivers.${option}.name`)}
                        </span>
                        <span className="text-small text-text-secondary">
                            {t(`whatsapp.drivers.${option}.help`)}
                        </span>
                    </button>
                ))}
            </div>
        </Section>
    );
}

function Status({ status, error }: { status: string; error: string | null }) {
    const { t } = useTranslation('integrations');

    return (
        <div className="flex flex-col gap-2">
            <HealthBadge
                health={healthOf(status)}
                label={status === 'unverified' ? t('hub.unverified') : undefined}
                className="self-start"
            />
            {error && status !== 'healthy' ? (
                <p
                    dir="ltr"
                    className="rounded-md bg-danger-bg p-2 text-caption break-all text-danger"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function OfficialStep({ official, webhooks, templates, myNumber }: Props) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const form = useForm({
        phoneNumberId: official?.phoneNumberId ?? '',
        wabaId: official?.wabaId ?? '',
        accessToken: '',
        appSecret: '',
    });
    const [testTemplate, setTestTemplate] = useState('');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(integrations.whatsapp.official.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('accessToken', 'appSecret'),
        });
    };

    return (
        <div className="flex flex-col gap-6">
            <Section
                title={t('whatsapp.official.title')}
                description={t('whatsapp.official.help')}
                actions={
                    official ? (
                        <Status status={official.status} error={official.lastError} />
                    ) : undefined
                }
            >
                <form onSubmit={submit} className="flex flex-col gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="wa-phone-id"
                            label={t('whatsapp.official.phoneNumberId')}
                            error={form.errors.phoneNumberId}
                            required
                        >
                            <Input
                                id="wa-phone-id"
                                dir="ltr"
                                inputMode="numeric"
                                value={form.data.phoneNumberId}
                                onChange={(event) =>
                                    form.setData('phoneNumberId', event.target.value)
                                }
                                required
                            />
                        </Field>
                        <Field
                            id="wa-waba"
                            label={t('whatsapp.official.wabaId')}
                            error={form.errors.wabaId}
                            required
                        >
                            <Input
                                id="wa-waba"
                                dir="ltr"
                                inputMode="numeric"
                                value={form.data.wabaId}
                                onChange={(event) => form.setData('wabaId', event.target.value)}
                                required
                            />
                        </Field>
                        <Field
                            id="wa-token"
                            label={t('whatsapp.official.accessToken')}
                            error={form.errors.accessToken}
                        >
                            <Input
                                id="wa-token"
                                type="password"
                                dir="ltr"
                                autoComplete="off"
                                placeholder={official?.accessToken ?? undefined}
                                value={form.data.accessToken}
                                onChange={(event) =>
                                    form.setData('accessToken', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            id="wa-secret"
                            label={t('whatsapp.official.appSecret')}
                            error={form.errors.appSecret}
                        >
                            <Input
                                id="wa-secret"
                                type="password"
                                dir="ltr"
                                autoComplete="off"
                                placeholder={official?.appSecret ?? undefined}
                                value={form.data.appSecret}
                                onChange={(event) => form.setData('appSecret', event.target.value)}
                            />
                        </Field>
                    </div>
                    <p className="text-caption text-muted-foreground">
                        {t('whatsapp.secretsKept')}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button type="submit" loading={form.processing}>
                            {t('hub.save')}
                        </Button>
                        {official ? (
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        integrations.check.url('whatsapp_official'),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <RefreshCw aria-hidden />
                                {t('whatsapp.testConnection')}
                            </Button>
                        ) : null}
                    </div>
                </form>
            </Section>

            {official ? (
                <Section
                    title={t('whatsapp.official.webhook')}
                    description={t('whatsapp.official.webhookHelp')}
                >
                    <div className="flex flex-col gap-3">
                        <CopyField
                            label={t('whatsapp.official.callbackUrl')}
                            value={webhooks.official}
                        />
                        {official.verifyToken ? (
                            <CopyField
                                label={t('whatsapp.official.verifyToken')}
                                value={official.verifyToken}
                            />
                        ) : null}
                    </div>
                </Section>
            ) : null}

            {official ? (
                <Section
                    title={t('whatsapp.templates.title')}
                    description={t('whatsapp.templates.help')}
                    actions={
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                router.post(
                                    integrations.whatsapp.sync.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw aria-hidden />
                            {t('whatsapp.templates.sync')}
                        </Button>
                    }
                >
                    {templates.length === 0 ? (
                        <p className="text-small text-muted-foreground">
                            {t('whatsapp.templates.none')}
                        </p>
                    ) : (
                        <div className="overflow-x-auto rounded-md border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-caption text-text-secondary">
                                    <tr>
                                        {[
                                            'name',
                                            'language',
                                            'category',
                                            'status',
                                            'params',
                                            'buttons',
                                        ].map((column) => (
                                            <th
                                                key={column}
                                                scope="col"
                                                className="px-3 py-2 text-start font-medium"
                                            >
                                                {t(`whatsapp.templates.columns.${column}`)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {templates.map((template) => (
                                        <tr
                                            key={`${template.name}-${template.language}`}
                                            className="border-t border-border"
                                        >
                                            <td className="px-3 py-2">
                                                <bdi dir="ltr" className="font-mono text-caption">
                                                    {template.name}
                                                </bdi>
                                            </td>
                                            <td className="px-3 py-2">
                                                <bdi dir="ltr">{template.language}</bdi>
                                            </td>
                                            <td className="px-3 py-2 text-caption">
                                                {template.category}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Badge
                                                    variant={
                                                        template.status === 'APPROVED'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    className={
                                                        template.status === 'REJECTED'
                                                            ? 'border-danger-border text-danger'
                                                            : undefined
                                                    }
                                                >
                                                    {t(
                                                        `whatsapp.templates.statuses.${template.status}`,
                                                        { defaultValue: template.status },
                                                    )}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {template.params}
                                            </td>
                                            <td className="px-3 py-2 text-caption">
                                                {template.buttons.join(' · ')}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {templates[0] ? (
                        <p className="mt-2 text-caption text-muted-foreground">
                            {t('whatsapp.templates.synced', {
                                date: formatDate(templates[0].syncedAt, locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                }),
                            })}
                        </p>
                    ) : null}
                </Section>
            ) : null}

            {official ? (
                <Section
                    title={t('whatsapp.testMessage')}
                    description={
                        myNumber
                            ? t('whatsapp.testTo', { number: myNumber })
                            : t('whatsapp.needNumber')
                    }
                >
                    <div className="flex flex-wrap items-end gap-3">
                        <Field id="wa-test-template" label={t('whatsapp.testTemplate')}>
                            <select
                                id="wa-test-template"
                                className="h-10 rounded-md border border-input bg-card px-3 text-sm"
                                value={testTemplate}
                                onChange={(event) => setTestTemplate(event.target.value)}
                            >
                                <option value="">{t('whatsapp.defaultTemplate')}</option>
                                {templates
                                    .filter(
                                        (template) =>
                                            template.status === 'APPROVED' && template.params === 0,
                                    )
                                    .map((template) => (
                                        <option
                                            key={`${template.name}-${template.language}`}
                                            value={template.name}
                                        >
                                            {template.name}
                                        </option>
                                    ))}
                            </select>
                        </Field>
                        <Button
                            disabled={!myNumber}
                            onClick={() =>
                                router.post(
                                    integrations.whatsapp.test.url(),
                                    { driver: 'whatsapp_official', template: testTemplate || null },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Send aria-hidden className="rtl:-scale-x-100" />
                            {t('whatsapp.sendTest')}
                        </Button>
                    </div>
                </Section>
            ) : null}
        </div>
    );
}

function WahaStep({ waha, webhooks }: Props) {
    const { t } = useTranslation('integrations');
    const form = useForm({
        baseUrl: waha?.baseUrl ?? 'http://waha:3000',
        session: waha?.session ?? 'default',
        apiKey: '',
    });

    return (
        <div className="flex flex-col gap-6">
            <p
                role="note"
                className="flex items-start gap-2 rounded-lg border border-warning-border bg-warning-bg p-4 text-small text-warning"
            >
                <TriangleAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
                {t('whatsapp.waha.risk')}
            </p>
            <Section
                title={t('types.whatsapp_waha.name')}
                description={t('whatsapp.waha.help')}
                actions={waha ? <Status status={waha.status} error={waha.lastError} /> : undefined}
            >
                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(integrations.whatsapp.waha.url(), {
                            preserveScroll: true,
                            onSuccess: () => form.reset('apiKey'),
                        });
                    }}
                    className="flex flex-col gap-4"
                >
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            id="waha-url"
                            label={t('whatsapp.waha.baseUrl')}
                            error={form.errors.baseUrl}
                            required
                        >
                            <Input
                                id="waha-url"
                                dir="ltr"
                                value={form.data.baseUrl}
                                onChange={(event) => form.setData('baseUrl', event.target.value)}
                                required
                            />
                        </Field>
                        <Field
                            id="waha-session"
                            label={t('whatsapp.waha.session')}
                            error={form.errors.session}
                            required
                        >
                            <Input
                                id="waha-session"
                                dir="ltr"
                                value={form.data.session}
                                onChange={(event) => form.setData('session', event.target.value)}
                                required
                            />
                        </Field>
                        <Field
                            id="waha-key"
                            label={t('whatsapp.waha.apiKey')}
                            error={form.errors.apiKey}
                        >
                            <Input
                                id="waha-key"
                                type="password"
                                dir="ltr"
                                autoComplete="off"
                                placeholder={waha?.apiKey ?? undefined}
                                value={form.data.apiKey}
                                onChange={(event) => form.setData('apiKey', event.target.value)}
                            />
                        </Field>
                    </div>
                    <Button type="submit" className="self-start" loading={form.processing}>
                        {t('hub.save')}
                    </Button>
                </form>
            </Section>
            {waha ? (
                <Section
                    title={t('whatsapp.waha.pairTitle')}
                    description={t('whatsapp.waha.pairHelp')}
                >
                    <Pairing />
                    <p className="mt-4 text-caption text-muted-foreground">
                        {t('whatsapp.waha.webhook')} <bdi dir="ltr">{webhooks.waha}</bdi>
                    </p>
                </Section>
            ) : null}
        </div>
    );
}

/** Live QR pairing (handoff 40): waiting dot, the QR, then "Connected" with the ripple. */
function Pairing() {
    const { t } = useTranslation('integrations');
    const [state, setState] = useState<{ status: string; qr: string | null } | null>(null);
    const [error, setError] = useState<string | null>(null);
    const active = state !== null && state.status !== 'WORKING' && state.status !== 'FAILED';

    useEffect(() => {
        let stopped = false;
        const poll = () =>
            fetch(integrations.whatsapp.pairing.url(), {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((response) =>
                    response.ok
                        ? (response.json() as Promise<{ status: string; qr: string | null }>)
                        : null,
                )
                .then((next) => {
                    if (!stopped && next) setState(next);
                })
                .catch(() => undefined);

        void poll();
        const timer = window.setInterval(() => void poll(), 3000);

        return () => {
            stopped = true;
            window.clearInterval(timer);
        };
    }, []);

    return (
        <div
            aria-live="polite"
            className="flex flex-col items-center gap-4 rounded-lg border border-border p-6 text-center"
        >
            {state?.status === 'WORKING' ? (
                <>
                    <SuccessRipple label={t('whatsapp.waha.connected')} />
                    <Badge>{t('whatsapp.waha.connected')}</Badge>
                </>
            ) : state?.qr ? (
                <>
                    <img
                        src={state.qr}
                        alt={t('whatsapp.waha.qrAlt')}
                        className="size-56 rounded-md bg-white p-2"
                    />
                    <p className="text-small text-text-secondary">{t('whatsapp.waha.scan')}</p>
                </>
            ) : (
                <p className="flex items-center gap-2 text-small text-text-secondary">
                    <span
                        aria-hidden
                        className={cn('size-2 rounded-full bg-primary', active && 'animate-pulse')}
                    />
                    {state === null
                        ? t('whatsapp.waha.checking')
                        : t(`whatsapp.waha.statuses.${state.status}`, {
                              defaultValue: state.status,
                          })}
                </p>
            )}
            {state?.status !== 'WORKING' ? (
                <Button
                    variant={state?.status === 'FAILED' ? 'default' : 'outline'}
                    onClick={() => {
                        setError(null);
                        router.post(
                            integrations.whatsapp.pair.url(),
                            {},
                            {
                                preserveScroll: true,
                                onError: (errors) => setError(errors.error ?? null),
                            },
                        );
                    }}
                >
                    <Smartphone aria-hidden />
                    {t('whatsapp.waha.pair')}
                </Button>
            ) : null}
            {error ? (
                <p role="alert" className="text-small text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function RoutingStep({ routing, templates, verificationTemplate }: Props) {
    const { t } = useTranslation('integrations');
    const form = useForm({
        chains: Object.fromEntries(routing.map((row) => [row.category, row.chain])) as Record<
            string,
            string[]
        >,
        verificationTemplate: verificationTemplate ?? '',
    });
    const risky = (category: string) => {
        const chain = form.data.chains[category] ?? [];

        return (
            routing.find((row) => row.category === category)?.critical === true &&
            chain.includes('whatsapp_waha') &&
            !chain.includes('whatsapp_official')
        );
    };
    const toggle = (category: string, step: string, on: boolean) => {
        const chain = (form.data.chains[category] ?? []).filter((s) => s !== 'email' && s !== step);
        const next = ['whatsapp_official', 'whatsapp_waha'].filter((s) =>
            s === step ? on : chain.includes(s),
        );
        form.setData('chains', { ...form.data.chains, [category]: [...next, 'email'] });
    };

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.transform((data) => ({
                    ...data,
                    verificationTemplate: data.verificationTemplate || null,
                }));
                form.put(integrations.whatsapp.routing.url(), { preserveScroll: true });
            }}
            className="flex flex-col gap-6"
        >
            <Section title={t('whatsapp.routing.title')} description={t('whatsapp.routing.help')}>
                {routing.some((row) => risky(row.category)) ? (
                    <p
                        role="alert"
                        className="mb-4 flex items-start gap-2 rounded-md border border-danger-border bg-danger-bg p-3 text-small text-danger"
                    >
                        <ShieldAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
                        {t('whatsapp.routing.warning')}
                    </p>
                ) : null}
                <div className="overflow-x-auto rounded-md border border-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/60 text-caption text-text-secondary">
                            <tr>
                                <th scope="col" className="px-3 py-2 text-start font-medium">
                                    {t('whatsapp.routing.category')}
                                </th>
                                {['whatsapp_official', 'whatsapp_waha', 'email'].map((step) => (
                                    <th
                                        key={step}
                                        scope="col"
                                        className="px-3 py-2 text-center font-medium"
                                    >
                                        {t(`whatsapp.routing.steps.${step}`)}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {routing.map((row) => (
                                <tr
                                    key={row.category}
                                    className={cn(
                                        'border-t border-border',
                                        risky(row.category) && 'bg-danger-bg/40',
                                    )}
                                >
                                    <th scope="row" className="px-3 py-2 text-start font-normal">
                                        {t(
                                            `notifications:preferences.categories.${row.category}.name`,
                                        )}
                                        {row.critical ? (
                                            <span className="ms-2 text-caption text-muted-foreground">
                                                {t('whatsapp.routing.critical')}
                                            </span>
                                        ) : null}
                                    </th>
                                    {['whatsapp_official', 'whatsapp_waha'].map((step) => (
                                        <td key={step} className="px-3 py-2 text-center">
                                            <Checkbox
                                                checked={(
                                                    form.data.chains[row.category] ?? []
                                                ).includes(step)}
                                                onCheckedChange={(value) =>
                                                    toggle(row.category, step, value === true)
                                                }
                                                aria-label={`${t(`notifications:preferences.categories.${row.category}.name`)} · ${t(`whatsapp.routing.steps.${step}`)}`}
                                            />
                                        </td>
                                    ))}
                                    <td className="px-3 py-2 text-center">
                                        <Checkbox
                                            checked
                                            disabled
                                            aria-label={`${t(`notifications:preferences.categories.${row.category}.name`)} · ${t('whatsapp.routing.steps.email')}`}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <p className="mt-2 text-caption text-muted-foreground">
                    {t('whatsapp.routing.order')}
                </p>
            </Section>
            <Section
                title={t('whatsapp.routing.codeTitle')}
                description={t('whatsapp.routing.codeHelp')}
            >
                <select
                    aria-label={t('whatsapp.routing.codeTitle')}
                    className="h-10 w-full max-w-sm rounded-md border border-input bg-card px-3 text-sm"
                    value={form.data.verificationTemplate}
                    onChange={(event) => form.setData('verificationTemplate', event.target.value)}
                >
                    <option value="">{t('whatsapp.routing.noCodeTemplate')}</option>
                    {templates
                        .filter(
                            (template) => template.status === 'APPROVED' && template.params === 1,
                        )
                        .map((template) => (
                            <option
                                key={`${template.name}-${template.language}`}
                                value={template.name}
                            >
                                {template.name}
                            </option>
                        ))}
                </select>
                <InputError message={form.errors.verificationTemplate} />
            </Section>
            <Button type="submit" className="self-start" loading={form.processing}>
                {t('hub.save')}
            </Button>
        </form>
    );
}

function TestStep({ official, waha, myNumber, recent }: Props) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();

    return (
        <div className="flex flex-col gap-6">
            <Section
                title={t('whatsapp.testMessage')}
                description={
                    myNumber ? t('whatsapp.testTo', { number: myNumber }) : t('whatsapp.needNumber')
                }
            >
                <div className="flex flex-wrap gap-2">
                    {official ? (
                        <Button
                            variant="outline"
                            disabled={!myNumber}
                            onClick={() =>
                                router.post(
                                    integrations.whatsapp.test.url(),
                                    { driver: 'whatsapp_official' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('whatsapp.testOfficial')}
                        </Button>
                    ) : null}
                    {waha ? (
                        <Button
                            variant="outline"
                            disabled={!myNumber}
                            onClick={() =>
                                router.post(
                                    integrations.whatsapp.test.url(),
                                    { driver: 'whatsapp_waha' },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('whatsapp.testWaha')}
                        </Button>
                    ) : null}
                </div>
            </Section>
            <Section
                title={t('whatsapp.invite.title')}
                description={t('whatsapp.invite.help')}
                actions={
                    <Button
                        onClick={() =>
                            router.post(
                                integrations.whatsapp.invite.url(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        <Users aria-hidden />
                        {t('whatsapp.invite.send')}
                    </Button>
                }
            >
                <span className="sr-only">{t('whatsapp.invite.help')}</span>
            </Section>
            <Section title={t('whatsapp.log')}>
                {recent.length === 0 ? (
                    <p className="text-small text-muted-foreground">{t('whatsapp.noMessages')}</p>
                ) : (
                    <ul className="flex flex-col divide-y divide-border">
                        {recent.map((message) => (
                            <li
                                key={message.id}
                                className="flex flex-wrap items-center gap-3 py-2 text-small"
                            >
                                <span className="flex-1">
                                    <bdi dir="ltr" className="font-mono text-caption">
                                        {message.purpose}
                                    </bdi>{' '}
                                    · {t(`types.${message.driver}.name`)}{' '}
                                    {message.to ? <bdi dir="ltr">{message.to}</bdi> : null}
                                    {message.error ? (
                                        <span className="block text-caption text-danger">
                                            {message.error}
                                        </span>
                                    ) : null}
                                </span>
                                <Badge
                                    variant={message.status === 'failed' ? 'outline' : 'secondary'}
                                    className={
                                        message.status === 'failed'
                                            ? 'border-danger-border text-danger'
                                            : undefined
                                    }
                                >
                                    {t(`whatsapp.messageStatus.${message.status}`)}
                                </Badge>
                                <time
                                    dateTime={message.at}
                                    className="text-caption text-muted-foreground"
                                >
                                    {formatDate(message.at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </time>
                            </li>
                        ))}
                    </ul>
                )}
            </Section>
        </div>
    );
}
