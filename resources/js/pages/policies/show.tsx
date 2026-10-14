import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronLeft, Download, Pencil, ShieldCheck } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualInput } from '@/components/mw/bilingual-input';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { ConfirmDialog } from '@/features/policies/confirm-dialog';
import { ImpactDialog } from '@/features/policies/impact-dialog';
import {
    type PolicyKey,
    type PolicySet,
    type PolicySetValue,
    type PolicyValue,
} from '@/features/policies/types';
import { useImpact } from '@/features/policies/use-impact';
import { ValueEditor } from '@/features/policies/value-editor';
import { SECTIONS, keyLabel, sameValue, summarize, withDefaults } from '@/features/policies/values';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import policies from '@/routes/policies';

type Props = {
    set: PolicySet;
    date: string;
    today: string;
    keys: PolicyKey[];
    values: PolicySetValue[];
    weekStartsOn: number;
    can: { create: boolean; update: boolean; delete: boolean; export: boolean };
};

/** A change in progress: key id → new value, or null to remove the key from this set. */
type Draft = Record<string, PolicyValue | null>;

/**
 * Policy set editor (handoff 17): section nav + typed editors + a sticky save bar. Changes start
 * on a date and keep history; saving goes through the impact preview (20). Phones get a read-only
 * summary.
 */
export default function PolicySetEditor({
    set,
    date,
    today,
    keys,
    values,
    weekStartsOn,
    can,
}: Props) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const [draft, setDraft] = useState<Draft>({});
    const [effectiveFrom, setEffectiveFrom] = useState(date > today ? date : today);
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [renaming, setRenaming] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const impact = useImpact();

    const stored = useMemo(
        () => Object.fromEntries(values.map((value) => [value.key, value])),
        [values],
    );
    const current = (id: string): PolicyValue | null =>
        id in draft ? draft[id] : (stored[id]?.value ?? null);
    const changes = Object.keys(draft).length;
    const editable = can.update;

    const change = (key: PolicyKey, next: PolicyValue | null) =>
        setDraft((previous) => {
            const copy = { ...previous };
            if (sameValue(next, stored[key.id]?.value ?? null)) {
                delete copy[key.id];
            } else {
                copy[key.id] = next;
            }
            return copy;
        });

    const payload = { values: draft, effectiveFrom, reason: reason || null };

    const save = () => {
        setSaving(true);
        router.put(policies.values.update.url(set.id), payload, {
            preserveScroll: true,
            onSuccess: () => {
                setDraft({});
                setErrors({});
                setReason('');
                impact.setOpen(false);
            },
            onError: (bag) => {
                setErrors(bag);
                impact.setOpen(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    const keyErrors = (id: string) =>
        Object.fromEntries(
            Object.entries(errors)
                .filter(([field]) => field.startsWith(`values.${id}.`))
                .map(([field, message]) => [field.slice(`values.${id}.`.length), message]),
        );

    const bySection = SECTIONS.map((section) => ({
        section,
        keys: keys.filter((key) => key.section === section),
    })).filter((group) => group.keys.length > 0);

    return (
        <>
            <Head title={t('editor.pageTitle', { name: localized(set.name, locale) })} />
            <Link
                href={policies.index.url()}
                className="mb-3 inline-flex items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('editor.back')}
            </Link>
            <PageHeader
                title={localized(set.name, locale)}
                description={
                    localized(set.description, locale) ||
                    (set.presetKey === 'neutral-default' ? t('sets.neutralHint') : undefined)
                }
                actions={
                    <>
                        {set.presetKey ? (
                            <Badge variant="secondary">
                                <ShieldCheck aria-hidden />
                                {set.presetKey === 'neutral-default'
                                    ? t('sets.neutral')
                                    : t('sets.preset')}
                            </Badge>
                        ) : null}
                        {can.update ? (
                            <Button variant="outline" onClick={() => setRenaming(true)}>
                                <Pencil aria-hidden />
                                {t('editor.rename')}
                            </Button>
                        ) : null}
                        {can.export ? (
                            <Button variant="outline" asChild>
                                <a href={policies.export.url(set.id, { query: { date } })} download>
                                    <Download aria-hidden />
                                    {t('editor.export')}
                                </a>
                            </Button>
                        ) : null}
                        {can.delete ? (
                            <Button variant="ghost" onClick={() => setArchiving(true)}>
                                {t('editor.archive')}
                            </Button>
                        ) : null}
                    </>
                }
            />

            {errors.error ? (
                <p role="alert" className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}

            {/* Phones: read-only summary (handoff 17). */}
            <div className="flex flex-col gap-4 md:hidden">
                <p className="rounded-lg border border-info-border bg-info-bg p-4 text-small text-info">
                    <span className="block font-semibold">{t('editor.desktopOnly')}</span>
                    {t('editor.desktopOnlyHint')}
                </p>
                {bySection.map(({ section, keys: sectionKeys }) => {
                    const defined = sectionKeys.filter((key) => stored[key.id]?.value);
                    return defined.length === 0 ? null : (
                        <section key={section} className="rounded-lg bg-card p-4 shadow-sm">
                            <h2 className="mb-2 text-body font-semibold">
                                {t(`sections.${section}`)}
                            </h2>
                            <dl className="flex flex-col gap-2">
                                {defined.map((key) => (
                                    <div key={key.id} className="flex flex-col">
                                        <dt className="text-caption text-muted-foreground">
                                            {keyLabel(key, t, locale)}
                                        </dt>
                                        <dd className="text-small">
                                            {summarize(
                                                key.editor,
                                                stored[key.id]?.value ?? null,
                                                t,
                                                locale,
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    );
                })}
            </div>

            <div className="hidden gap-8 md:flex">
                <nav aria-label={t('tabs.label')} className="w-48 shrink-0">
                    <ul className="sticky top-6 flex flex-col gap-1">
                        {bySection.map(({ section }) => (
                            <li key={section}>
                                <a
                                    href={`#section-${section}`}
                                    className="flex h-9 items-center rounded-md px-3 text-sm text-text-secondary hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {t(`sections.${section}`)}
                                </a>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div className="flex min-w-0 flex-1 flex-col gap-6 pb-28">
                    <div className="flex flex-wrap items-end gap-3">
                        <Field id="viewing-on" label={t('editor.viewingOn')}>
                            <Input
                                id="viewing-on"
                                type="date"
                                dir="ltr"
                                className="w-44"
                                value={date}
                                onChange={(event) =>
                                    event.target.value &&
                                    router.get(
                                        policies.show.url(set.id),
                                        { date: event.target.value },
                                        { preserveScroll: true },
                                    )
                                }
                            />
                        </Field>
                    </div>

                    {bySection.map(({ section, keys: sectionKeys }) => (
                        <section
                            key={section}
                            id={`section-${section}`}
                            aria-labelledby={`section-${section}-title`}
                            className="flex scroll-mt-6 flex-col gap-3"
                        >
                            <h2 id={`section-${section}-title`} className="text-h4">
                                {t(`sections.${section}`)}
                            </h2>
                            {sectionKeys.map((key) => (
                                <KeyCard
                                    key={key.id}
                                    policyKey={key}
                                    value={current(key.id)}
                                    stored={stored[key.id]}
                                    dirty={key.id in draft}
                                    errors={keyErrors(key.id)}
                                    editable={editable}
                                    weekStartsOn={weekStartsOn}
                                    onChange={(next) => change(key, next)}
                                />
                            ))}
                        </section>
                    ))}
                </div>
            </div>

            {changes > 0 ? (
                <div className="fixed start-0 end-0 bottom-0 z-30 hidden border-t border-border bg-card/95 shadow-lg backdrop-blur-sm md:start-[72px] md:block xl:start-[260px]">
                    <div className="mx-auto flex max-w-[1280px] flex-wrap items-end gap-4 px-8 py-3">
                        <p aria-live="polite" className="me-auto self-center text-sm font-medium">
                            {t('editor.changed', { count: changes })}
                        </p>
                        <Field
                            id="effective-from"
                            label={t('editor.effectiveFrom')}
                            error={errors.effectiveFrom}
                        >
                            <Input
                                id="effective-from"
                                type="date"
                                dir="ltr"
                                className="w-44"
                                value={effectiveFrom}
                                onChange={(event) => setEffectiveFrom(event.target.value)}
                            />
                        </Field>
                        {effectiveFrom < today ? (
                            <Field
                                id="change-reason"
                                label={t('editor.reason')}
                                hint={t('editor.reasonHint')}
                                error={errors.reason}
                                required
                            >
                                <Input
                                    id="change-reason"
                                    className="w-64"
                                    value={reason}
                                    onChange={(event) => setReason(event.target.value)}
                                />
                            </Field>
                        ) : null}
                        <Button
                            variant="ghost"
                            onClick={() => {
                                setDraft({});
                                setErrors({});
                            }}
                        >
                            {t('editor.discard')}
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => impact.run(policies.preview.url(set.id), payload)}
                        >
                            {t('editor.preview')}
                        </Button>
                        <Button
                            onClick={() => impact.run(policies.preview.url(set.id), payload)}
                            loading={saving}
                        >
                            {t('editor.save')}
                        </Button>
                    </div>
                </div>
            ) : null}

            <ImpactDialog
                open={impact.open}
                onOpenChange={impact.setOpen}
                preview={impact.preview}
                loading={impact.loading}
                failed={impact.failed}
                keys={keys}
                onConfirm={save}
                confirming={saving}
            />
            {renaming ? <RenameDialog set={set} onClose={() => setRenaming(false)} /> : null}
            <ConfirmDialog
                open={archiving}
                onOpenChange={setArchiving}
                title={t('editor.archive')}
                description={t('editor.archiveConfirm')}
                confirmLabel={t('common:actions.archive')}
                onConfirm={() =>
                    router.delete(policies.destroy.url(set.id), { onError: setErrors })
                }
            />
        </>
    );
}

function KeyCard({
    policyKey,
    value,
    stored,
    dirty,
    errors,
    editable,
    weekStartsOn,
    onChange,
}: {
    policyKey: PolicyKey;
    value: PolicyValue | null;
    stored: PolicySetValue | undefined;
    dirty: boolean;
    errors: Record<string, string>;
    editable: boolean;
    weekStartsOn: number;
    onChange: (value: PolicyValue | null) => void;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const titleId = `key-${policyKey.id.replaceAll('.', '-')}`;

    return (
        <article
            aria-labelledby={titleId}
            className={cn(
                'rounded-lg bg-card p-5 shadow-sm inset-shadow-highlight',
                dirty && 'ring-2 ring-ring/40',
            )}
        >
            <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 flex-col gap-0.5">
                    <h3 id={titleId} className="text-body font-semibold">
                        {keyLabel(policyKey, t, locale)}
                    </h3>
                    {value ? (
                        <span className="text-caption text-text-secondary">
                            {t('editor.setHere')}
                        </span>
                    ) : (
                        <span className="text-caption text-muted-foreground">
                            {policyKey.branchFallback
                                ? t('editor.branchFallback')
                                : t('editor.inherited')}
                        </span>
                    )}
                </div>
                {editable ? (
                    value ? (
                        <Button variant="ghost" size="sm" onClick={() => onChange(null)}>
                            {t('editor.remove')}
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => onChange(withDefaults(policyKey, null))}
                        >
                            {t('editor.define')}
                        </Button>
                    )
                ) : null}
            </header>
            {value ? (
                <ValueEditor
                    id={titleId}
                    editor={policyKey.editor}
                    value={withDefaults(policyKey, value)}
                    onChange={onChange}
                    errors={errors}
                    weekStartsOn={weekStartsOn}
                    disabled={!editable}
                />
            ) : (
                <p className="text-small text-muted-foreground">
                    {t('editor.default', {
                        value: summarize(policyKey.editor, policyKey.default, t, locale),
                    })}
                </p>
            )}
            {stored && stored.scheduled.length > 0 ? (
                <ul className="mt-4 flex flex-col gap-1 border-t border-border pt-3 text-caption text-text-secondary">
                    {stored.scheduled.map((change) => (
                        <li key={change.effectiveFrom}>
                            {change.value
                                ? `${t('editor.scheduled', { date: formatDate(change.effectiveFrom, locale) })}: ${summarize(policyKey.editor, change.value, t, locale)}`
                                : t('editor.scheduledRemoval', {
                                      date: formatDate(change.effectiveFrom, locale),
                                  })}
                        </li>
                    ))}
                </ul>
            ) : null}
        </article>
    );
}

function RenameDialog({ set, onClose }: { set: PolicySet; onClose: () => void }) {
    const { t } = useTranslation('policies');
    const form = useForm({
        name: { en: set.name.en ?? '', ar: set.name.ar ?? '' },
        description: { en: set.description.en ?? '', ar: set.description.ar ?? '' },
    });
    const errors = form.errors as Partial<Record<string, string>>;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(policies.update.url(set.id), { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('editor.rename')}
            footer={
                <>
                    <Button variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit" form="rename-set-form" loading={form.processing}>
                        {t('common:actions.save')}
                    </Button>
                </>
            }
        >
            <form id="rename-set-form" onSubmit={submit} className="flex flex-col gap-4" noValidate>
                <BilingualInput
                    id="rename-name"
                    label={t('sets.name')}
                    value={form.data.name}
                    onChange={(name) => form.setData('name', name)}
                    errors={errors}
                    errorKey="name"
                />
                <Field
                    id="rename-description-en"
                    label={`${t('sets.descriptionLabel')} (EN)`}
                    error={errors['description.en']}
                >
                    <Textarea
                        id="rename-description-en"
                        dir="ltr"
                        value={form.data.description.en}
                        onChange={(event) =>
                            form.setData('description', {
                                ...form.data.description,
                                en: event.target.value,
                            })
                        }
                    />
                </Field>
                <Field
                    id="rename-description-ar"
                    label={`${t('sets.descriptionLabel')} (AR)`}
                    error={errors['description.ar']}
                >
                    <Textarea
                        id="rename-description-ar"
                        dir="rtl"
                        lang="ar"
                        value={form.data.description.ar}
                        onChange={(event) =>
                            form.setData('description', {
                                ...form.data.description,
                                ar: event.target.value,
                            })
                        }
                    />
                </Field>
            </form>
        </ResponsiveDialog>
    );
}
