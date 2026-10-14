import { router, useForm } from '@inertiajs/react';
import { Download, FileText, Pencil, Plus } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { Section } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { CatalogSelect } from '@/features/organization/catalog-select';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import people from '@/routes/people';
import { daysUntil, type EmployeeDocument, type ProfileProps } from './types';

/** Expiry badge (handoff 8): warning within 30 days, danger once expired. */
function ExpiryBadge({ expiresOn }: { expiresOn: string | null }) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();

    if (!expiresOn) {
        return null;
    }

    const days = daysUntil(expiresOn);
    const tone =
        days < 0
            ? 'bg-danger-bg text-danger border-error-border'
            : days <= 30
              ? 'bg-warning-bg text-warning border-warning-border'
              : 'bg-muted text-text-secondary border-border';

    return (
        <span
            className={cn(
                'inline-flex h-6 items-center rounded-full border px-2.5 text-caption font-medium whitespace-nowrap',
                tone,
            )}
        >
            {days < 0
                ? t('documents.expired', {
                      date: formatDate(expiresOn, locale, { dateStyle: 'medium' }),
                  })
                : days <= 30
                  ? t('documents.expiresIn', { count: days })
                  : t('documents.expiresOn', {
                        date: formatDate(expiresOn, locale, { dateStyle: 'medium' }),
                    })}
        </span>
    );
}

export function DocumentsTab({
    profile,
    documents,
    options,
    can,
}: Pick<ProfileProps, 'profile' | 'documents' | 'options' | 'can'>) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<EmployeeDocument | 'new' | null>(null);
    const employeeId = profile.summary.id;

    return (
        <Section
            title={t('documents.title')}
            actions={
                can.update ? (
                    <Button size="sm" onClick={() => setEditing('new')}>
                        <Plus aria-hidden />
                        {t('documents.add')}
                    </Button>
                ) : null
            }
        >
            {documents.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-inbox"
                    title={t('documents.empty')}
                    className="py-6"
                />
            ) : (
                <ul className="flex flex-col divide-y divide-border">
                    {documents.map((document) => (
                        <li key={document.id} className="flex flex-wrap items-center gap-3 py-3">
                            <FileText aria-hidden className="size-5 shrink-0 text-text-secondary" />
                            <span className="flex min-w-0 flex-1 flex-col">
                                <span className="text-sm font-medium">
                                    {document.type ? localized(document.type.name, locale) : '—'}
                                </span>
                                <span className="text-caption text-muted-foreground">
                                    {document.number ? (
                                        <bdi dir="ltr">{document.number}</bdi>
                                    ) : null}
                                    {document.number && document.fileName ? ' · ' : null}
                                    {document.fileName ? (
                                        <bdi dir="ltr">{document.fileName}</bdi>
                                    ) : null}
                                </span>
                            </span>
                            <ExpiryBadge expiresOn={document.expiresOn} />
                            <div className="flex gap-1">
                                {document.fileName ? (
                                    <Button variant="ghost" size="icon-sm" asChild>
                                        <a
                                            href={people.documents.download.url([
                                                employeeId,
                                                document.id,
                                            ])}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label={`${t('common:actions.download')}: ${document.type ? localized(document.type.name, locale) : ''}`}
                                        >
                                            <Download aria-hidden />
                                        </a>
                                    </Button>
                                ) : null}
                                {can.update ? (
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        onClick={() => setEditing(document)}
                                        aria-label={`${t('common:actions.edit')}: ${document.type ? localized(document.type.name, locale) : ''}`}
                                    >
                                        <Pencil aria-hidden />
                                    </Button>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {editing ? (
                <DocumentDialog
                    employeeId={employeeId}
                    document={editing === 'new' ? null : editing}
                    documentTypes={options.documentTypes}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </Section>
    );
}

function DocumentDialog({
    employeeId,
    document,
    documentTypes,
    onClose,
}: {
    employeeId: string;
    document: EmployeeDocument | null;
    documentTypes: ProfileProps['options']['documentTypes'];
    onClose: () => void;
}) {
    const { t } = useTranslation('people');
    const form = useForm<{
        documentTypeId: string | null;
        number: string;
        issuedOn: string;
        expiresOn: string;
        notes: string;
        file: File | null;
    }>({
        documentTypeId: document?.type?.id ?? documentTypes[0]?.id ?? null,
        number: document?.number ?? '',
        issuedOn: document?.issuedOn ?? '',
        expiresOn: document?.expiresOn ?? '',
        notes: document?.notes ?? '',
        file: null,
    });
    const errors = form.errors as Partial<Record<string, string>>;
    const type = documentTypes.find((candidate) => candidate.id === form.data.documentTypeId);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({
            ...Object.fromEntries(
                Object.entries(data).map(([key, value]) => [key, value === '' ? null : value]),
            ),
            file: data.file ?? undefined,
        }));
        form.post(
            document
                ? people.documents.update.url([employeeId, document.id])
                : people.documents.store.url(employeeId),
            {
                preserveScroll: true,
                forceFormData: true,
                onSuccess: onClose,
            },
        );
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={document ? t('documents.edit') : t('documents.add')}
            footer={
                <>
                    {document ? (
                        <Button
                            variant="ghost"
                            className="me-auto"
                            onClick={() =>
                                router.delete(
                                    people.documents.destroy.url([employeeId, document.id]),
                                    { preserveScroll: true, onSuccess: onClose },
                                )
                            }
                        >
                            {t('common:actions.archive')}
                        </Button>
                    ) : null}
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="document-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form
                id="document-form"
                onSubmit={submit}
                className="grid gap-4 sm:grid-cols-2"
                noValidate
            >
                {errors.error ? (
                    <p
                        role="alert"
                        className="rounded-md bg-danger-bg p-3 text-small text-danger sm:col-span-2"
                    >
                        {errors.error}
                    </p>
                ) : null}
                <Field
                    id="document-type"
                    label={t('documents.type')}
                    error={errors.documentTypeId}
                    required
                    className="sm:col-span-2"
                >
                    <CatalogSelect
                        id="document-type"
                        value={form.data.documentTypeId}
                        onChange={(value) => form.setData('documentTypeId', value)}
                        options={documentTypes}
                        allowNone={false}
                    />
                </Field>
                <Field id="document-number" label={t('documents.number')} error={errors.number}>
                    <Input
                        id="document-number"
                        dir="ltr"
                        autoComplete="off"
                        value={form.data.number}
                        onChange={(event) => form.setData('number', event.target.value)}
                    />
                </Field>
                <Field id="document-issued" label={t('documents.issuedOn')} error={errors.issuedOn}>
                    <Input
                        id="document-issued"
                        type="date"
                        dir="ltr"
                        value={form.data.issuedOn}
                        onChange={(event) => form.setData('issuedOn', event.target.value)}
                    />
                </Field>
                <Field
                    id="document-expires"
                    label={t('documents.expiresOnLabel')}
                    error={errors.expiresOn}
                    required={type?.requiresExpiry}
                >
                    <Input
                        id="document-expires"
                        type="date"
                        dir="ltr"
                        value={form.data.expiresOn}
                        onChange={(event) => form.setData('expiresOn', event.target.value)}
                    />
                </Field>
                <Field
                    id="document-file"
                    label={document ? t('documents.replaceFile') : t('documents.file')}
                    error={errors.file}
                    required={!document}
                    hint={t('documents.fileHint')}
                >
                    <Input
                        id="document-file"
                        type="file"
                        accept="application/pdf,image/jpeg,image/png,image/webp"
                        onChange={(event) => form.setData('file', event.target.files?.[0] ?? null)}
                    />
                </Field>
                <Field
                    id="document-notes"
                    label={t('documents.notes')}
                    error={errors.notes}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="document-notes"
                        value={form.data.notes}
                        onChange={(event) => form.setData('notes', event.target.value)}
                        maxLength={500}
                    />
                </Field>
                {form.progress ? (
                    <progress
                        value={form.progress.percentage}
                        max={100}
                        className="w-full sm:col-span-2"
                    />
                ) : null}
            </form>
        </ResponsiveDialog>
    );
}
