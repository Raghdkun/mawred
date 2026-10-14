import { Head, Link, router } from '@inertiajs/react';
import {
    ChevronLeft,
    Copy,
    Eye,
    Inbox,
    Monitor,
    Pencil,
    Plus,
    Settings2,
    Smartphone,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { BuilderDnd, Canvas, Palette } from '@/features/forms/builder/canvas';
import { PropertiesPanel } from '@/features/forms/builder/properties';
import { useBuilder } from '@/features/forms/builder/use-builder';
import { FormLocaleProvider } from '@/features/forms/form-locale';
import { FormRenderer } from '@/features/forms/form-renderer';
import { FormSettingsDialog } from '@/features/forms/form-settings-dialog';
import { FormStatusBadge } from '@/features/forms/form-status-badge';
import {
    type FormRuleContext,
    type FormSchema,
    allFields,
    asPayload,
    textOf,
} from '@/features/forms/schema';
import { ConfirmDialog } from '@/features/policies/confirm-dialog';
import { useLocale } from '@/hooks/use-locale';
import { type Locale } from '@/i18n';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import forms from '@/routes/forms';

type FormData = App.Domain.Forms.Data.FormData;

type Props = {
    form: FormData;
    schema: FormSchema;
    context: FormRuleContext;
    can: { update: boolean; delete: boolean; create: boolean };
};

/** `schema.pages.0.sections.1.fields.2.label.en` → problems per field key, plus the rest. */
function mapProblems(
    errors: Record<string, string>,
    schema: FormSchema,
): { byField: Record<string, string[]>; general: string[] } {
    const byField: Record<string, string[]> = {};
    const general: string[] = [];

    for (const [path, message] of Object.entries(errors)) {
        const match = /^schema\.pages\.(\d+)\.sections\.(\d+)\.fields\.(\d+)/.exec(path);
        const field = match
            ? schema.pages[Number(match[1])]?.sections[Number(match[2])]?.fields[Number(match[3])]
            : undefined;

        if (field) {
            byField[field.key] = [...(byField[field.key] ?? []), message];
        } else if (path !== 'error') {
            general.push(message);
        }
    }

    return { byField, general };
}

/**
 * Form builder (handoff 14, desktop-only 1280+): palette at the reading start, canvas, properties
 * at the reading end; EN/AR and phone preview. Smaller screens get a read-only preview.
 */
export default function FormBuilder({ form, schema: initial, context, can }: Props) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const builder = useBuilder(initial);
    const [mode, setMode] = useState<'edit' | 'preview'>('edit');
    const [previewLocale, setPreviewLocale] = useState<Locale>(locale);
    const [device, setDevice] = useState<'desktop' | 'phone'>('desktop');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [dialog, setDialog] = useState<'settings' | 'duplicate' | 'archive' | 'discard' | null>(
        null,
    );
    const problems = useMemo(() => mapProblems(errors, builder.schema), [errors, builder.schema]);
    const editable = can.update;

    const save = (then?: () => void) => {
        setSaving(true);
        const sent = builder.schema;
        router.put(
            forms.draft.save.url(form.id),
            { schema: asPayload(sent) },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setErrors({});
                    builder.markSaved();
                    then?.();
                },
                onError: (bag) => setErrors(bag),
                onFinish: () => setSaving(false),
            },
        );
    };

    const publish = () => {
        const run = () =>
            router.post(
                forms.publish.url(form.id),
                {},
                { preserveScroll: true, preserveState: true, onError: setErrors },
            );

        if (builder.dirty) {
            save(run);
        } else {
            run();
        }
    };

    const preview = (
        <FormLocaleProvider value={previewLocale}>
            <div
                dir={previewLocale === 'ar' ? 'rtl' : 'ltr'}
                lang={previewLocale}
                className={cn(
                    'mx-auto w-full rounded-lg bg-card p-6 shadow-sm',
                    device === 'phone' ? 'max-w-[390px]' : 'max-w-[640px]',
                )}
            >
                <h2 className="mb-6 text-h3">{localized(form.name, previewLocale)}</h2>
                <FormRenderer
                    schema={builder.schema}
                    context={context}
                    uploadUrl={forms.uploads.url(form.id, { query: { version: 'draft' } })}
                    onSubmit={() => undefined}
                    submitLabel={t('builder.previewSubmit')}
                    disabled
                />
            </div>
        </FormLocaleProvider>
    );

    return (
        <>
            <Head title={t('builder.pageTitle', { name: localized(form.name, locale) })} />
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Link
                    href={forms.index.url()}
                    className="inline-flex items-center gap-1 text-small text-text-secondary hover:text-foreground"
                >
                    <DirIcon>
                        <ChevronLeft aria-hidden className="size-4" />
                    </DirIcon>
                    {t('builder.back')}
                </Link>
            </div>
            <header className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-1">
                    <h1 className="text-h2">{localized(form.name, locale)}</h1>
                    <div className="flex flex-wrap items-center gap-2">
                        <FormStatusBadge form={form} />
                        {builder.dirty ? (
                            <span className="text-caption text-warning">
                                {t('builder.unsaved')}
                            </span>
                        ) : null}
                    </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {form.publishedNumber !== null ? (
                        <Button variant="outline" asChild>
                            <Link href={forms.preview.url(form.id)}>
                                <Eye aria-hidden />
                                {t('builder.fillIn')}
                            </Link>
                        </Button>
                    ) : null}
                    <Button variant="outline" asChild>
                        <Link href={forms.submissions.index.url(form.id)}>
                            <Inbox aria-hidden />
                            {t('builder.submissions')}
                        </Link>
                    </Button>
                    {editable ? (
                        <Button variant="outline" onClick={() => setDialog('settings')}>
                            <Settings2 aria-hidden />
                            {t('builder.settings')}
                        </Button>
                    ) : null}
                    {can.create ? (
                        <Button
                            variant="ghost"
                            size="icon"
                            aria-label={t('builder.duplicateForm')}
                            onClick={() => setDialog('duplicate')}
                        >
                            <Copy aria-hidden />
                        </Button>
                    ) : null}
                </div>
            </header>

            {errors.error || problems.general.length > 0 ? (
                <div
                    role="alert"
                    className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger"
                >
                    <p>{errors.error}</p>
                    {problems.general.length > 0 ? (
                        <ul className="mt-1 list-disc ps-5">
                            {problems.general.map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    ) : null}
                </div>
            ) : null}

            {/* Below 1280 px the builder is a read-only preview (handoff 14). */}
            <div className="xl:hidden">
                <p className="mb-4 rounded-lg border border-info-border bg-info-bg p-4 text-small text-info">
                    {t('builder.desktopOnly')}
                </p>
                {preview}
            </div>

            <div className="hidden flex-col gap-4 xl:flex">
                <div className="flex flex-wrap items-center gap-3 border-b border-border pb-3">
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        value={mode}
                        onValueChange={(next) => next && setMode(next as 'edit')}
                        aria-label={t('builder.mode')}
                    >
                        <ToggleGroupItem value="edit" className="px-3">
                            <Pencil aria-hidden />
                            {t('builder.edit')}
                        </ToggleGroupItem>
                        <ToggleGroupItem value="preview" className="px-3">
                            <Eye aria-hidden />
                            {t('builder.preview')}
                        </ToggleGroupItem>
                    </ToggleGroup>
                    {mode === 'preview' ? (
                        <>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={previewLocale}
                                onValueChange={(next) => next && setPreviewLocale(next as Locale)}
                                aria-label={t('builder.previewLanguage')}
                            >
                                <ToggleGroupItem value="en" className="px-3" lang="en">
                                    {t('common:language.en')}
                                </ToggleGroupItem>
                                <ToggleGroupItem value="ar" className="px-3" lang="ar">
                                    {t('common:language.ar')}
                                </ToggleGroupItem>
                            </ToggleGroup>
                            <ToggleGroup
                                type="single"
                                variant="outline"
                                value={device}
                                onValueChange={(next) => next && setDevice(next as 'phone')}
                                aria-label={t('builder.previewDevice')}
                            >
                                <ToggleGroupItem value="desktop" aria-label={t('builder.desktop')}>
                                    <Monitor aria-hidden />
                                </ToggleGroupItem>
                                <ToggleGroupItem value="phone" aria-label={t('builder.phone')}>
                                    <Smartphone aria-hidden />
                                </ToggleGroupItem>
                            </ToggleGroup>
                        </>
                    ) : (
                        <PageTabs builder={builder} />
                    )}
                    {editable ? (
                        <div className="ms-auto flex flex-wrap items-center gap-2">
                            {form.status !== 'published' ? (
                                <Button
                                    variant="ghost"
                                    onClick={() => setDialog('discard')}
                                    disabled={
                                        form.status === 'draft' &&
                                        allFields(builder.schema).length === 0
                                    }
                                >
                                    {t('builder.discard')}
                                </Button>
                            ) : null}
                            <Button
                                variant="outline"
                                onClick={() => save()}
                                loading={saving}
                                disabled={!builder.dirty}
                            >
                                {t('builder.saveDraft')}
                            </Button>
                            <Button
                                onClick={publish}
                                disabled={saving || (form.status === 'published' && !builder.dirty)}
                            >
                                {t('builder.publish')}
                            </Button>
                        </div>
                    ) : null}
                </div>

                {mode === 'preview' ? (
                    preview
                ) : (
                    <BuilderDnd builder={builder}>
                        <div className="grid grid-cols-[14rem_minmax(0,1fr)_24rem] items-start gap-6">
                            <aside className="sticky top-6">
                                {editable ? (
                                    <Palette builder={builder} isPublic={form.isPublic} />
                                ) : null}
                            </aside>
                            <Canvas builder={builder} problems={problems.byField} />
                            <aside
                                aria-label={t('builder.properties')}
                                className="sticky top-6 max-h-[calc(100svh-6rem)] overflow-y-auto rounded-lg bg-card p-4 shadow-sm"
                            >
                                <PropertiesPanel
                                    builder={builder}
                                    problems={problems.byField}
                                    isPublic={form.isPublic}
                                />
                            </aside>
                        </div>
                    </BuilderDnd>
                )}
            </div>

            {dialog === 'settings' ? (
                <FormSettingsDialog form={form} onClose={() => setDialog(null)} />
            ) : null}
            {dialog === 'duplicate' ? (
                <DuplicateDialog form={form} onClose={() => setDialog(null)} />
            ) : null}
            <ConfirmDialog
                open={dialog === 'discard'}
                onOpenChange={(open) => !open && setDialog(null)}
                title={t('builder.discard')}
                description={t('builder.discardConfirm')}
                confirmLabel={t('builder.discard')}
                onConfirm={() =>
                    router.delete(forms.draft.discard.url(form.id), {
                        onSuccess: () => router.reload(),
                    })
                }
            />
        </>
    );
}

function PageTabs({ builder }: { builder: ReturnType<typeof useBuilder> }) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();

    return (
        <div className="flex flex-wrap items-center gap-1">
            <div
                role="tablist"
                aria-label={t('builder.pages')}
                className="flex flex-wrap items-center gap-1"
            >
                {builder.schema.pages.map((page, index) => (
                    <button
                        key={page.key}
                        type="button"
                        role="tab"
                        aria-selected={builder.activePage === page.key}
                        onClick={() => {
                            builder.setActivePage(page.key);
                            builder.setSelection({ kind: 'page', key: page.key });
                        }}
                        className={cn(
                            'h-9 rounded-md px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            builder.activePage === page.key
                                ? 'bg-accent font-medium text-accent-foreground'
                                : 'text-text-secondary hover:bg-muted',
                        )}
                    >
                        {textOf(page.title, locale) || t('builder.pageN', { n: index + 1 })}
                    </button>
                ))}
            </div>
            <Button type="button" variant="ghost" size="sm" onClick={builder.addPage}>
                <Plus aria-hidden />
                {t('builder.addPage')}
            </Button>
        </div>
    );
}

function DuplicateDialog({ form, onClose }: { form: FormData; onClose: () => void }) {
    const { t } = useTranslation('forms');
    const [code, setCode] = useState(`${form.code}_COPY`.slice(0, 40));
    const [name, setName] = useState(`${form.name.en ?? form.code} (copy)`);
    const [error, setError] = useState<string | null>(null);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('builder.duplicateForm')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button
                        onClick={() =>
                            router.post(
                                forms.duplicate.url(form.id),
                                { code, name: { en: name } },
                                { onError: (bag) => setError(bag.code ?? bag.error ?? null) },
                            )
                        }
                    >
                        {t('builder.duplicateForm')}
                    </Button>
                </>
            }
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="copy-code">{t('settings.code')}</Label>
                    <Input
                        id="copy-code"
                        dir="ltr"
                        value={code}
                        onChange={(event) => setCode(event.target.value.toUpperCase())}
                        aria-invalid={error !== null || undefined}
                    />
                    {error ? <p className="text-caption text-danger">{error}</p> : null}
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="copy-name">{`${t('settings.name')} (EN)`}</Label>
                    <Input
                        id="copy-name"
                        value={name}
                        onChange={(event) => setName(event.target.value)}
                    />
                </div>
            </div>
        </ResponsiveDialog>
    );
}
