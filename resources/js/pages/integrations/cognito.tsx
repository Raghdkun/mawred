import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, Plus } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { JsonViewer } from '@/features/integrations/json-viewer';
import { EmployeePicker } from '@/features/organization/pickers';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import integrations from '@/routes/integrations';
import requests from '@/routes/requests';
import { type Paginated } from '@/types/pagination';

type Source = {
    id: string;
    name: string;
    requestType: { id: string; name: Record<string, string> } | null;
    isActive: boolean;
    tokenLast4: string;
    rotatedAt: string | null;
    receivedToday: number;
    waiting: number;
};

type Receipt = {
    id: string;
    source: string | null;
    event: string;
    entryId: string | null;
    status: string;
    error: string | null;
    canReplay: boolean;
    request: { id: string; number: string } | null;
    at: string;
};

type Props = {
    sources: Source[];
    receipts: Paginated<Receipt>;
    requestTypes: Array<{ id: string; name: Record<string, string> }>;
    filters: { status: string | null; source: string | null };
    statuses: string[];
};

const STATUS_TONE: Record<string, string> = {
    processed: 'bg-status-approved-bg text-status-approved-fg',
    needs_person: 'bg-status-pending-bg text-status-pending-fg',
    failed: 'bg-status-rejected-bg text-status-rejected-fg',
    late_update: 'bg-status-escalated-bg text-status-escalated-fg',
};

const selectClass =
    'h-10 rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/** Cognito Forms (handoff 41): sources and the submission log with replay (D61). */
export default function CognitoIndex({
    sources,
    receipts,
    requestTypes,
    filters,
    statuses,
}: Props) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const [creating, setCreating] = useState(false);
    const [viewing, setViewing] = useState<Receipt | null>(null);
    const [assigning, setAssigning] = useState<Receipt | null>(null);
    const filter = (next: Partial<Props['filters']>) =>
        router.get(
            integrations.cognito.index.url(),
            Object.fromEntries(
                Object.entries({ ...filters, ...next }).filter(([, value]) => value),
            ) as Record<string, string>,
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title={t('cognito.pageTitle')} />
            <Link
                href={integrations.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('hub.title')}
            </Link>
            <PageHeader
                title={t('types.cognito.name')}
                description={t('cognito.description')}
                actions={
                    <Button onClick={() => setCreating(true)}>
                        <Plus aria-hidden />
                        {t('cognito.newSource')}
                    </Button>
                }
            />

            <div className="flex flex-col gap-6">
                <Section title={t('cognito.sources')}>
                    {sources.length === 0 ? (
                        <PageState
                            kind="empty"
                            illustration="empty-integrations"
                            title={t('cognito.noSources')}
                            description={t('cognito.noSourcesHelp')}
                            className="py-6"
                        />
                    ) : (
                        <ul className="grid gap-3 md:grid-cols-2">
                            {sources.map((source) => (
                                <li
                                    key={source.id}
                                    className="relative flex flex-col gap-2 rounded-md border border-border p-4 hover:bg-muted/60"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <Link
                                            href={integrations.cognito.show.url(source.id)}
                                            className="font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                        >
                                            {source.name}
                                        </Link>
                                        {!source.isActive ? (
                                            <Badge variant="outline">{t('cognito.off')}</Badge>
                                        ) : null}
                                    </div>
                                    <p className="text-small text-text-secondary">
                                        {source.requestType
                                            ? t('cognito.feeds', {
                                                  type: localized(source.requestType.name, locale),
                                              })
                                            : t('cognito.notLinked')}
                                    </p>
                                    <p className="text-caption text-muted-foreground">
                                        {t('cognito.today', {
                                            count: source.receivedToday,
                                            formatted: formatNumber(source.receivedToday, locale),
                                        })}
                                        {source.waiting > 0
                                            ? ` · ${t('cognito.waiting', { count: source.waiting, formatted: formatNumber(source.waiting, locale) })}`
                                            : ''}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </Section>

                <Section title={t('cognito.log')} description={t('cognito.logHelp')}>
                    <div className="mb-4 flex flex-wrap gap-3">
                        <label className="flex items-center gap-2 text-small text-text-secondary">
                            {t('cognito.status')}
                            <select
                                className={selectClass}
                                value={filters.status ?? ''}
                                onChange={(event) => filter({ status: event.target.value || null })}
                            >
                                <option value="">{t('cognito.anyStatus')}</option>
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {t(`cognito.statuses.${status}`)}
                                    </option>
                                ))}
                            </select>
                        </label>
                        {sources.length > 1 ? (
                            <label className="flex items-center gap-2 text-small text-text-secondary">
                                {t('cognito.source')}
                                <select
                                    className={selectClass}
                                    value={filters.source ?? ''}
                                    onChange={(event) =>
                                        filter({ source: event.target.value || null })
                                    }
                                >
                                    <option value="">{t('cognito.anySource')}</option>
                                    {sources.map((source) => (
                                        <option key={source.id} value={source.id}>
                                            {source.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        ) : null}
                    </div>

                    {receipts.data.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('cognito.noEntries')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border">
                            {receipts.data.map((receipt) => (
                                <li
                                    key={receipt.id}
                                    className="flex flex-col gap-2 py-3 md:flex-row md:items-center md:gap-4"
                                >
                                    <span className="flex min-w-0 flex-1 flex-col">
                                        <span className="text-sm font-medium">
                                            {t(`cognito.events.${receipt.event}`)}
                                            {receipt.entryId ? (
                                                <>
                                                    {' · '}
                                                    <bdi dir="ltr">{receipt.entryId}</bdi>
                                                </>
                                            ) : null}
                                        </span>
                                        <span className="text-caption text-muted-foreground">
                                            {receipt.source} ·{' '}
                                            {formatDate(receipt.at, locale, {
                                                dateStyle: 'medium',
                                                timeStyle: 'short',
                                            })}
                                        </span>
                                        {receipt.error ? (
                                            <span className="text-caption whitespace-pre-line text-danger">
                                                {receipt.error}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span
                                        className={cn(
                                            'inline-flex h-6 w-fit items-center rounded-full px-2.5 text-caption font-medium',
                                            STATUS_TONE[receipt.status] ??
                                                'bg-muted text-text-secondary',
                                        )}
                                    >
                                        {t(`cognito.statuses.${receipt.status}`)}
                                    </span>
                                    <span className="flex flex-wrap items-center gap-1">
                                        {receipt.request ? (
                                            <Button variant="ghost" size="sm" asChild>
                                                <Link href={requests.show.url(receipt.request.id)}>
                                                    <bdi dir="ltr">{receipt.request.number}</bdi>
                                                </Link>
                                            </Button>
                                        ) : null}
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setViewing(receipt)}
                                        >
                                            {t('cognito.payload')}
                                        </Button>
                                        {receipt.status === 'needs_person' ? (
                                            <Button size="sm" onClick={() => setAssigning(receipt)}>
                                                {t('cognito.pickPerson')}
                                            </Button>
                                        ) : null}
                                        {receipt.canReplay ? (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() =>
                                                    router.post(
                                                        integrations.cognito.receipts.replay.url(
                                                            receipt.id,
                                                        ),
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('cognito.replay')}
                                            </Button>
                                        ) : null}
                                        {receipt.status !== 'processed' &&
                                        receipt.status !== 'discarded' ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-danger hover:text-danger"
                                                onClick={() =>
                                                    router.post(
                                                        integrations.cognito.receipts.discard.url(
                                                            receipt.id,
                                                        ),
                                                        {},
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('cognito.discard')}
                                            </Button>
                                        ) : null}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <Paginator prevUrl={receipts.prev_page_url} nextUrl={receipts.next_page_url} />
                </Section>
            </div>

            {creating ? (
                <CreateSource requestTypes={requestTypes} onClose={() => setCreating(false)} />
            ) : null}
            {viewing ? <PayloadDialog receipt={viewing} onClose={() => setViewing(null)} /> : null}
            {assigning ? (
                <AssignDialog receipt={assigning} onClose={() => setAssigning(null)} />
            ) : null}
        </>
    );
}

function CreateSource({
    requestTypes,
    onClose,
}: {
    requestTypes: Props['requestTypes'];
    onClose: () => void;
}) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const form = useForm({
        name: '',
        requestTypeId: '',
        formUrl: '',
        fields: [] as Array<{ from: string; to: string }>,
        requesterBy: 'email',
        requesterPath: 'Email',
        entryPath: null,
        versionPath: null,
        prefill: {},
        isActive: true,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            requestTypeId: data.requestTypeId || null,
            formUrl: data.formUrl || null,
        }));
        form.post(integrations.cognito.store.url());
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('cognito.newSource')}
            description={t('cognito.newSourceHelp')}
        >
            <form onSubmit={submit} className="flex flex-col gap-4">
                <Field id="source-name" label={t('cognito.name')} error={form.errors.name} required>
                    <Input
                        id="source-name"
                        value={form.data.name}
                        onChange={(event) => form.setData('name', event.target.value)}
                        required
                    />
                </Field>
                <Field
                    id="source-type"
                    label={t('cognito.requestType')}
                    error={form.errors.requestTypeId}
                >
                    <select
                        id="source-type"
                        className={cn(selectClass, 'w-full')}
                        value={form.data.requestTypeId}
                        onChange={(event) => form.setData('requestTypeId', event.target.value)}
                    >
                        <option value="">{t('cognito.chooseLater')}</option>
                        {requestTypes.map((type) => (
                            <option key={type.id} value={type.id}>
                                {localized(type.name, locale)}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field id="source-url" label={t('cognito.formUrl')} error={form.errors.formUrl}>
                    <Input
                        id="source-url"
                        dir="ltr"
                        type="url"
                        placeholder="https://www.cognitoforms.com/…"
                        value={form.data.formUrl}
                        onChange={(event) => form.setData('formUrl', event.target.value)}
                    />
                </Field>
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" loading={form.processing}>
                        {t('cognito.create')}
                    </Button>
                </div>
            </form>
        </ResponsiveDialog>
    );
}

function PayloadDialog({ receipt, onClose }: { receipt: Receipt; onClose: () => void }) {
    const { t } = useTranslation('integrations');
    const [payload, setPayload] = useState<unknown>(undefined);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        fetch(integrations.cognito.receipts.show.url(receipt.id), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<{ payload: unknown }>)
                    : Promise.reject(new Error(response.statusText)),
            )
            .then((body) => setPayload(body.payload))
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError'))
                    setFailed(true);
            });

        return () => controller.abort();
    }, [receipt.id]);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('cognito.payload')}
            description={receipt.entryId ?? undefined}
            className="sm:max-w-2xl"
        >
            <div aria-live="polite" aria-busy={payload === undefined && !failed}>
                {failed ? (
                    <PageState kind="error" className="py-6" />
                ) : payload === undefined ? (
                    <Skeleton className="h-48 w-full" />
                ) : (
                    <JsonViewer value={payload} label={t('cognito.payload')} />
                )}
            </div>
        </ResponsiveDialog>
    );
}

function AssignDialog({ receipt, onClose }: { receipt: Receipt; onClose: () => void }) {
    const { t } = useTranslation('integrations');
    const [person, setPerson] = useState<PickerOption | null>(null);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('cognito.pickPerson')}
            description={t('cognito.pickPersonHelp')}
        >
            <div className="flex flex-col gap-4">
                <EmployeePicker value={person} onChange={setPerson} label={t('cognito.person')} />
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        disabled={!person}
                        onClick={() =>
                            person &&
                            router.post(
                                integrations.cognito.receipts.assign.url(receipt.id),
                                { employee: person.id },
                                { preserveScroll: true, onSuccess: onClose },
                            )
                        }
                    >
                        {t('cognito.startRequest')}
                    </Button>
                </div>
            </div>
        </ResponsiveDialog>
    );
}
