import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, KeyRound, Radar, TriangleAlert } from 'lucide-react';
import { type FormEvent, useId, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { JsonViewer, payloadPaths } from '@/features/integrations/json-viewer';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import integrations from '@/routes/integrations';

type Urls = { submit: string; update: string; delete: string };

type Props = {
    source: {
        id: string;
        name: string;
        requestType: { id: string; name: Record<string, string> } | null;
        posting: string | null;
        isActive: boolean;
        tokenLast4: string;
        rotatedAt: string | null;
        formUrl: string | null;
        mapping: {
            fields: Array<{ from: string; to: string }>;
            requesterBy: 'email' | 'employee_number';
            requesterPath: string;
            entryPath: string;
            versionPath: string;
            prefill: Record<string, string>;
        };
        captureSample: boolean;
        sample: Record<string, unknown> | null;
        sampleCapturedAt: string | null;
    };
    formFields: Array<{
        key: string;
        label: Record<string, string> | null;
        type: string;
        required: boolean;
    }>;
    requestTypes: Array<{ id: string; name: Record<string, string> }>;
    urlTemplate: Urls;
};

const selectClass =
    'h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/** One Cognito source (handoff 41): URLs, settings, field mapping against the sample payload. */
export default function CognitoSourcePage({
    source,
    formFields,
    requestTypes,
    urlTemplate,
}: Props) {
    const { t } = useTranslation('integrations');
    const { locale } = useLocale();
    const { flash } = usePage();
    const fresh = (flash as { urls?: Urls } | undefined)?.urls ?? null;
    const [rotating, setRotating] = useState(false);
    const paths = useMemo(
        () => (source.sample ? payloadPaths(source.sample) : []),
        [source.sample],
    );
    const listId = useId();
    const form = useForm({
        name: source.name,
        requestTypeId: source.requestType?.id ?? '',
        formUrl: source.formUrl ?? '',
        fields: source.mapping.fields,
        requesterBy: source.mapping.requesterBy,
        requesterPath: source.mapping.requesterPath,
        entryPath: source.mapping.entryPath,
        versionPath: source.mapping.versionPath,
        prefill: {
            email: source.mapping.prefill.email ?? '',
            name: source.mapping.prefill.name ?? '',
            employee_number: source.mapping.prefill.employee_number ?? '',
        },
        isActive: source.isActive,
    });
    const fromFor = (key: string) => form.data.fields.find((row) => row.to === key)?.from ?? '';
    const setFrom = (key: string, from: string) =>
        form.setData(
            'fields',
            from === ''
                ? form.data.fields.filter((row) => row.to !== key)
                : [...form.data.fields.filter((row) => row.to !== key), { from, to: key }],
        );
    const masked = (url: string) => url.replace('{token}', `••••${source.tokenLast4}`);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            requestTypeId: data.requestTypeId || null,
            formUrl: data.formUrl || null,
        }));
        form.put(integrations.cognito.update.url(source.id), { preserveScroll: true });
    };

    const pathInput = (
        id: string,
        value: string,
        onChange: (value: string) => void,
        label: string,
    ) => (
        <Input
            id={id}
            dir="ltr"
            list={listId}
            aria-label={label}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            className="font-mono text-caption"
        />
    );

    return (
        <>
            <Head title={source.name} />
            <Link
                href={integrations.cognito.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('types.cognito.name')}
            </Link>
            <PageHeader
                title={source.name}
                description={
                    source.requestType
                        ? t('cognito.feeds', { type: localized(source.requestType.name, locale) })
                        : source.posting
                          ? t('cognito.feedsPosting', { job: source.posting })
                          : t('cognito.notLinked')
                }
            />
            <datalist id={listId}>
                {paths.map((path) => (
                    <option key={path} value={path} />
                ))}
            </datalist>

            <form onSubmit={submit} className="flex max-w-4xl flex-col gap-6">
                <Section
                    title={t('cognito.urls')}
                    description={fresh ? t('cognito.urlsOnce') : t('cognito.urlsHelp')}
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setRotating(true)}
                        >
                            <KeyRound aria-hidden />
                            {t('cognito.rotate')}
                        </Button>
                    }
                >
                    {fresh ? (
                        <p className="mb-4 flex items-start gap-2 rounded-md border border-warning-border bg-warning-bg p-3 text-small text-warning">
                            <TriangleAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
                            {t('cognito.copyNow')}
                        </p>
                    ) : null}
                    <div className="flex flex-col gap-3">
                        {(['submit', 'update', 'delete'] as const).map((event) => (
                            <CopyField
                                key={event}
                                label={t(`cognito.endpoints.${event}`)}
                                value={fresh ? fresh[event] : masked(urlTemplate[event])}
                            />
                        ))}
                    </div>
                    <p className="mt-3 text-caption text-muted-foreground">
                        {t('cognito.noHeaders')}
                    </p>
                </Section>

                <Section title={t('cognito.settings')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            id="source-name"
                            label={t('cognito.name')}
                            error={form.errors.name}
                            required
                        >
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
                            hint={source.posting ? t('cognito.postingHint') : undefined}
                        >
                            <select
                                id="source-type"
                                className={selectClass}
                                value={form.data.requestTypeId}
                                disabled={source.posting !== null}
                                onChange={(event) =>
                                    form.setData('requestTypeId', event.target.value)
                                }
                            >
                                <option value="">{t('cognito.chooseLater')}</option>
                                {requestTypes.map((type) => (
                                    <option key={type.id} value={type.id}>
                                        {localized(type.name, locale)}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            id="source-url"
                            label={t('cognito.formUrl')}
                            error={form.errors.formUrl}
                        >
                            <Input
                                id="source-url"
                                dir="ltr"
                                type="url"
                                value={form.data.formUrl}
                                onChange={(event) => form.setData('formUrl', event.target.value)}
                            />
                        </Field>
                        <div className="flex items-center justify-between gap-3 self-end">
                            <Label htmlFor="source-active">{t('cognito.active')}</Label>
                            <Switch
                                id="source-active"
                                checked={form.data.isActive}
                                onCheckedChange={(value) => form.setData('isActive', value)}
                            />
                        </div>
                    </div>
                </Section>

                <Section
                    title={t('cognito.sample')}
                    description={
                        source.sampleCapturedAt
                            ? t('cognito.sampleAt', {
                                  date: formatDate(source.sampleCapturedAt, locale, {
                                      dateStyle: 'medium',
                                      timeStyle: 'short',
                                  }),
                              })
                            : t('cognito.sampleHelp')
                    }
                    actions={
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            disabled={source.captureSample}
                            onClick={() =>
                                router.post(
                                    integrations.cognito.capture.url(source.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <Radar aria-hidden />
                            {source.captureSample ? t('cognito.capturing') : t('cognito.capture')}
                        </Button>
                    }
                >
                    {source.sample ? (
                        <JsonViewer value={source.sample} label={t('cognito.sample')} />
                    ) : (
                        <p className="text-small text-muted-foreground">{t('cognito.noSample')}</p>
                    )}
                </Section>

                {source.posting ? null : (
                    <Section title={t('cognito.person')} description={t('cognito.personHelp')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field id="requester-by" label={t('cognito.matchBy')}>
                                <select
                                    id="requester-by"
                                    className={selectClass}
                                    value={form.data.requesterBy}
                                    onChange={(event) =>
                                        form.setData(
                                            'requesterBy',
                                            event.target.value as 'email' | 'employee_number',
                                        )
                                    }
                                >
                                    <option value="email">{t('cognito.byEmail')}</option>
                                    <option value="employee_number">{t('cognito.byNumber')}</option>
                                </select>
                            </Field>
                            <Field
                                id="requester-path"
                                label={t('cognito.cognitoField')}
                                error={form.errors.requesterPath}
                            >
                                {pathInput(
                                    'requester-path',
                                    form.data.requesterPath,
                                    (value) => form.setData('requesterPath', value),
                                    t('cognito.cognitoField'),
                                )}
                            </Field>
                            <Field id="entry-path" label={t('cognito.entryPath')}>
                                {pathInput(
                                    'entry-path',
                                    form.data.entryPath,
                                    (value) => form.setData('entryPath', value),
                                    t('cognito.entryPath'),
                                )}
                            </Field>
                            <Field id="version-path" label={t('cognito.versionPath')}>
                                {pathInput(
                                    'version-path',
                                    form.data.versionPath,
                                    (value) => form.setData('versionPath', value),
                                    t('cognito.versionPath'),
                                )}
                            </Field>
                        </div>
                    </Section>
                )}

                <Section
                    title={t('cognito.mapping')}
                    description={
                        formFields.length === 0
                            ? t('cognito.mappingNoForm')
                            : t('cognito.mappingHelp')
                    }
                >
                    {formFields.length > 0 ? (
                        <div className="overflow-hidden rounded-md border border-border">
                            <table className="w-full text-sm">
                                <thead className="bg-muted/60 text-caption text-text-secondary">
                                    <tr>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-start font-medium"
                                        >
                                            {t('cognito.mawredField')}
                                        </th>
                                        <th
                                            scope="col"
                                            className="px-3 py-2 text-start font-medium"
                                        >
                                            {t('cognito.cognitoField')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {formFields.map((field) => (
                                        <tr key={field.key} className="border-t border-border">
                                            <th
                                                scope="row"
                                                className="px-3 py-2 text-start font-normal"
                                            >
                                                <span className="block">
                                                    {localized(field.label ?? {}, locale) ||
                                                        field.key}
                                                    {field.required ? (
                                                        <span className="text-danger"> *</span>
                                                    ) : null}
                                                </span>
                                                <bdi
                                                    dir="ltr"
                                                    className="text-caption text-muted-foreground"
                                                >
                                                    {field.key} · {field.type}
                                                </bdi>
                                            </th>
                                            <td className="px-3 py-2">
                                                {pathInput(
                                                    `map-${field.key}`,
                                                    fromFor(field.key),
                                                    (value) => setFrom(field.key, value),
                                                    `${t('cognito.cognitoField')}: ${field.key}`,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : null}
                </Section>

                {source.posting ? null : (
                    <Section title={t('cognito.prefill')} description={t('cognito.prefillHelp')}>
                        <div className="grid gap-4 sm:grid-cols-3">
                            {(['email', 'name', 'employee_number'] as const).map((key) => (
                                <Field
                                    key={key}
                                    id={`prefill-${key}`}
                                    label={t(`cognito.prefillKeys.${key}`)}
                                >
                                    {pathInput(
                                        `prefill-${key}`,
                                        form.data.prefill[key],
                                        (value) =>
                                            form.setData('prefill', {
                                                ...form.data.prefill,
                                                [key]: value,
                                            }),
                                        t(`cognito.prefillKeys.${key}`),
                                    )}
                                </Field>
                            ))}
                        </div>
                    </Section>
                )}

                <div>
                    <Button type="submit" loading={form.processing}>
                        {t('hub.save')}
                    </Button>
                </div>
            </form>

            <AlertDialog open={rotating} onOpenChange={setRotating}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('cognito.rotateTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('cognito.rotateHelp')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('common:actions.cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                router.post(
                                    integrations.cognito.rotate.url(source.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('cognito.rotate')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
