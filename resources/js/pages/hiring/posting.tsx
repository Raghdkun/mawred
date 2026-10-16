import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader, Section } from '@/components/mw/page-header';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    type FormField,
    type FormSchema,
    allFields,
    asPayload,
    textOf,
} from '@/features/forms/schema';
import { BilingualText, type Pair } from '@/features/hiring/bilingual-text';
import { PostingStatusBadge } from '@/features/hiring/status';
import { useLocale } from '@/hooks/use-locale';
import hiring from '@/routes/hiring';

type Posting = App.Domain.Hiring.Data.PostingData;
type FormOption = { id: string; name: string; schema: FormSchema };
type Knockout = { field: string; rule: unknown };
type Props = {
    requisition: App.Domain.Hiring.Data.RequisitionData;
    posting: Posting | null;
    forms: FormOption[];
    defaultFormId: string | null;
    suggestedTitle: Record<string, string>;
    requisitionOpen: boolean;
};

const empty: Pair = { en: '', ar: '' };
const SCREENING_TYPES = ['select', 'radio', 'boolean', 'checkboxes', 'multiselect', 'number'];

/**
 * Posting editor (D81, D87): the advert in both languages, the application form, screening
 * knock-outs built from the form's questions, and the search and share texts. Publishing puts it
 * on the careers site while the requisition is open.
 */
export default function PostingEditor({
    requisition,
    posting,
    forms,
    defaultFormId,
    suggestedTitle,
    requisitionOpen,
}: Props) {
    const { t } = useTranslation('hiring');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [title, setTitle] = useState<Pair>(
        posting?.title ?? { en: suggestedTitle.en ?? '', ar: suggestedTitle.ar ?? '' },
    );
    const [summary, setSummary] = useState<Pair>(posting?.summary ?? empty);
    const [description, setDescription] = useState<Pair>(posting?.description ?? empty);
    const [location, setLocation] = useState<Pair>(posting?.location ?? empty);
    const [workplace, setWorkplace] = useState(posting?.workplace ?? 'onsite');
    const [showSalary, setShowSalary] = useState(posting?.showSalary ?? false);
    const [formId, setFormId] = useState(posting?.formId ?? defaultFormId ?? forms[0]?.id ?? '');
    const [closesOn, setClosesOn] = useState(posting?.closesOn ?? '');
    const [knockouts, setKnockouts] = useState<Knockout[]>(
        (posting?.knockouts as Knockout[] | undefined) ?? [],
    );
    const [seoTitle, setSeoTitle] = useState<Pair>(posting?.seo.title ?? empty);
    const [seoDescription, setSeoDescription] = useState<Pair>(posting?.seo.description ?? empty);
    const schema = forms.find((f) => f.id === formId)?.schema ?? null;
    const questions = schema
        ? allFields(schema).filter((f) => SCREENING_TYPES.includes(f.type))
        : [];

    const save = () => {
        // Knock-out rules are arbitrary JSON (Phase 3a gotcha): send through asPayload.
        const payload = asPayload({
            title,
            summary,
            description,
            location,
            workplace,
            showSalary,
            formId: formId || null,
            closesOn: closesOn || null,
            knockouts,
            seo: { title: seoTitle, description: seoDescription },
        });
        setSaving(true);
        const options = {
            preserveScroll: true,
            onError: setErrors,
            onSuccess: () => setErrors({}),
            onFinish: () => setSaving(false),
        };

        if (posting) {
            router.put(hiring.postings.update.url(posting.id), payload, options);
        } else {
            router.post(hiring.postings.store.url(requisition.id), payload, options);
        }
    };

    return (
        <>
            <Head title={posting ? t('posting.editTitle') : t('posting.newTitle')} />
            <Link
                href={hiring.board.url(requisition.id)}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {requisition.title}
            </Link>
            <PageHeader
                title={posting ? t('posting.editTitle') : t('posting.newTitle')}
                description={`${requisition.number} · ${requisition.title}`}
                actions={
                    posting ? (
                        <div className="flex flex-wrap items-center gap-2">
                            <PostingStatusBadge status={posting.status} />
                            {posting.status !== 'published' ? (
                                <Button
                                    className="min-h-11"
                                    disabled={!requisitionOpen}
                                    data-publish
                                    onClick={() =>
                                        router.put(
                                            hiring.postings.status.url(posting.id),
                                            { status: 'published' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('posting.publish')}
                                </Button>
                            ) : (
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    data-close-posting
                                    onClick={() =>
                                        router.put(
                                            hiring.postings.status.url(posting.id),
                                            { status: 'closed' },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {t('posting.close')}
                                </Button>
                            )}
                        </div>
                    ) : null
                }
            />

            {errors.error ? (
                <p role="alert" className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}
            {posting && !requisitionOpen ? (
                <p className="mb-4 rounded-md bg-warning-bg p-3 text-small">
                    {t('posting.requisitionClosed')}
                </p>
            ) : null}

            <form
                className="flex max-w-4xl flex-col gap-8"
                onSubmit={(event) => {
                    event.preventDefault();
                    save();
                }}
            >
                {posting?.status === 'published' ? (
                    <CopyField label={t('posting.link')} value={posting.url} />
                ) : null}

                <Section title={t('posting.advert')}>
                    <div className="flex flex-col gap-4">
                        <BilingualText
                            id="title"
                            label={t('posting.jobTitle')}
                            value={title}
                            onChange={setTitle}
                            errors={errors}
                            errorKey="title"
                            required
                            maxLength={120}
                        />
                        <BilingualText
                            id="summary"
                            label={t('posting.summary')}
                            value={summary}
                            onChange={setSummary}
                            errors={errors}
                            errorKey="summary"
                            maxLength={300}
                            hint={t('posting.summaryHint')}
                        />
                        <BilingualText
                            id="description"
                            label={t('posting.descriptionField')}
                            value={description}
                            onChange={setDescription}
                            errors={errors}
                            errorKey="description"
                            required
                            multiline
                            maxLength={12000}
                            hint={t('posting.descriptionHint')}
                        />
                        <BilingualText
                            id="location"
                            label={t('posting.location')}
                            value={location}
                            onChange={setLocation}
                            errors={errors}
                            errorKey="location"
                            maxLength={120}
                            hint={t('posting.locationHint')}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                id="workplace"
                                label={t('posting.workplace')}
                                error={errors.workplace}
                            >
                                <Select value={workplace} onValueChange={setWorkplace}>
                                    <SelectTrigger id="workplace" className="min-h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {(['onsite', 'hybrid', 'remote'] as const).map((w) => (
                                            <SelectItem key={w} value={w}>
                                                {t(`workplace.${w}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Field
                                id="closes-on"
                                label={t('posting.closesOn')}
                                error={errors.closesOn}
                                hint={t('posting.closesOnHint')}
                            >
                                <Input
                                    id="closes-on"
                                    type="date"
                                    value={closesOn}
                                    onChange={(e) => setClosesOn(e.target.value)}
                                    className="min-h-11"
                                />
                            </Field>
                        </div>
                        <SwitchField
                            id="show-salary"
                            label={t('posting.showSalary')}
                            hint={t('posting.showSalaryHint')}
                            checked={showSalary}
                            onCheckedChange={setShowSalary}
                        />
                    </div>
                </Section>

                <Section title={t('posting.applying')} description={t('posting.applyingHelp')}>
                    <div className="flex flex-col gap-4">
                        <Field id="form" label={t('posting.form')} error={errors.formId}>
                            <Select
                                value={formId}
                                onValueChange={(id) => {
                                    setFormId(id);
                                    setKnockouts([]);
                                }}
                            >
                                <SelectTrigger id="form" className="min-h-11">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {forms.map((f) => (
                                        <SelectItem key={f.id} value={f.id}>
                                            {f.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                        <KnockoutEditor
                            questions={questions}
                            value={knockouts}
                            onChange={setKnockouts}
                            errors={errors}
                        />
                    </div>
                </Section>

                <Section title={t('posting.seo')} description={t('posting.seoHelp')}>
                    <div className="flex flex-col gap-4">
                        <BilingualText
                            id="seo-title"
                            label={t('posting.seoTitle')}
                            value={seoTitle}
                            onChange={setSeoTitle}
                            errors={errors}
                            errorKey="seo.title"
                            maxLength={70}
                        />
                        <BilingualText
                            id="seo-description"
                            label={t('posting.seoDescription')}
                            value={seoDescription}
                            onChange={setSeoDescription}
                            errors={errors}
                            errorKey="seo.description"
                            maxLength={160}
                        />
                    </div>
                </Section>

                <div className="flex justify-end">
                    <Button type="submit" className="min-h-11" disabled={saving} data-save-posting>
                        {t('posting.save')}
                    </Button>
                </div>
            </form>
        </>
    );
}

/** A knock-out is stored as a `mawred.rule/v1` condition; this editor builds the common shapes. */
function describe(
    rule: unknown,
): { kind: 'in'; values: unknown[] } | { kind: 'lt'; min: number } | null {
    const node = rule as { op?: string; args?: unknown[] } | null;

    if (
        node?.op === 'in' &&
        Array.isArray(node.args) &&
        (node.args[1] as { value?: unknown })?.value
    ) {
        return {
            kind: 'in',
            values: ((node.args[1] as { value: unknown[] }).value ?? []) as unknown[],
        };
    }

    if (node?.op === 'lt' && Array.isArray(node.args) && typeof node.args[1] === 'number') {
        return { kind: 'lt', min: node.args[1] };
    }

    return null;
}

function KnockoutEditor({
    questions,
    value,
    onChange,
    errors,
}: {
    questions: FormField[];
    value: Knockout[];
    onChange: (value: Knockout[]) => void;
    errors: Record<string, string>;
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const unused = questions.filter((q) => !value.some((k) => k.field === q.key));
    const set = (i: number, next: Knockout) => onChange(value.map((k, j) => (j === i ? next : k)));
    const ruleFor = (q: FormField, picked: unknown[] | number): unknown =>
        q.type === 'number'
            ? { op: 'lt', args: [{ ref: `field.${q.key}` }, picked] }
            : { op: 'in', args: [{ ref: `field.${q.key}` }, { value: picked }] };
    const choices = (q: FormField): Array<{ value: unknown; label: string }> =>
        q.type === 'boolean'
            ? [
                  { value: true, label: t('knockouts.yes') },
                  { value: false, label: t('knockouts.no') },
              ]
            : (q.options ?? []).map((o) => ({ value: o.value, label: textOf(o.label, locale) }));

    return (
        <fieldset className="flex flex-col gap-3" data-knockouts>
            <legend className="mb-1 text-small font-medium">{t('knockouts.title')}</legend>
            <p className="text-caption text-text-secondary">{t('knockouts.help')}</p>
            {value.map((k, i) => {
                const q = questions.find((x) => x.key === k.field);
                const shape = describe(k.rule);

                return (
                    <div
                        key={k.field}
                        className="flex flex-col gap-2 rounded-md border border-border p-3"
                        data-knockout={k.field}
                    >
                        <div className="flex items-center justify-between gap-2">
                            <span className="text-small font-medium">
                                {q ? textOf(q.label, locale) : k.field}
                            </span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11 text-danger"
                                aria-label={t('knockouts.remove')}
                                onClick={() => onChange(value.filter((_, j) => j !== i))}
                            >
                                <Trash2 aria-hidden className="size-4" />
                            </Button>
                        </div>
                        {!q || !shape ? (
                            <p className="text-caption text-text-secondary">
                                {t('knockouts.custom')}
                            </p>
                        ) : q.type === 'number' && shape.kind === 'lt' ? (
                            <label className="flex flex-wrap items-center gap-2 text-small">
                                {t('knockouts.below')}
                                <Input
                                    type="number"
                                    className="min-h-11 w-32"
                                    value={shape.min}
                                    onChange={(e) =>
                                        set(i, {
                                            field: q.key,
                                            rule: ruleFor(q, Number(e.target.value)),
                                        })
                                    }
                                />
                            </label>
                        ) : shape.kind === 'in' ? (
                            <div className="flex flex-col gap-1">
                                <span className="text-caption text-text-secondary">
                                    {t('knockouts.when')}
                                </span>
                                {choices(q).map((c) => {
                                    const on = shape.values.some((v) => v === c.value);
                                    const id = `ko-${q.key}-${String(c.value)}`;

                                    return (
                                        <label
                                            key={id}
                                            htmlFor={id}
                                            className="flex min-h-11 items-center gap-2 text-small"
                                        >
                                            <Checkbox
                                                id={id}
                                                checked={on}
                                                onCheckedChange={(next) =>
                                                    set(i, {
                                                        field: q.key,
                                                        rule: ruleFor(
                                                            q,
                                                            next
                                                                ? [...shape.values, c.value]
                                                                : shape.values.filter(
                                                                      (v) => v !== c.value,
                                                                  ),
                                                        ),
                                                    })
                                                }
                                            />
                                            {c.label}
                                        </label>
                                    );
                                })}
                            </div>
                        ) : null}
                        {errors[`knockouts.${i}.rule`] || errors[`knockouts.${i}.field`] ? (
                            <p role="alert" className="text-caption text-danger">
                                {errors[`knockouts.${i}.rule`] ?? errors[`knockouts.${i}.field`]}
                            </p>
                        ) : null}
                    </div>
                );
            })}
            {unused.length > 0 ? (
                <Select
                    value=""
                    onValueChange={(key) => {
                        const q = questions.find((x) => x.key === key);

                        if (q)
                            onChange([
                                ...value,
                                { field: q.key, rule: ruleFor(q, q.type === 'number' ? 0 : []) },
                            ]);
                    }}
                >
                    <SelectTrigger
                        className="min-h-11 sm:w-72"
                        aria-label={t('knockouts.add')}
                        data-add-knockout
                    >
                        <span className="flex items-center gap-2 text-text-secondary">
                            <Plus aria-hidden className="size-4" />
                            {t('knockouts.add')}
                        </span>
                    </SelectTrigger>
                    <SelectContent>
                        {unused.map((q) => (
                            <SelectItem key={q.key} value={q.key}>
                                {textOf(q.label, locale)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            ) : questions.length === 0 ? (
                <p className="text-caption text-text-secondary">{t('knockouts.noQuestions')}</p>
            ) : null}
        </fieldset>
    );
}
