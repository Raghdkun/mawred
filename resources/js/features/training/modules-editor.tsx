import { router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Plus, Trash2, Upload } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualText, type Pair } from '@/components/mw/bilingual-text';
import { Field } from '@/components/mw/field';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import training from '@/routes/training';
import { FallbackIcon, MODULE_ICONS } from './icons';

type Editor = App.Domain.Training.Data.CourseEditorData;
type OptionDraft = { key: string; id: string | null; label: Pair; correct: boolean };
type QuestionDraft = {
    key: string;
    id: string | null;
    type: string;
    prompt: Pair;
    points: number;
    options: OptionDraft[];
};
type ModuleDraft = {
    key: string;
    id: string | null;
    kind: string;
    title: Pair;
    body: Pair;
    url: string;
    fileName: string | null;
    durationMinutes: string;
    required: boolean;
    passMark: string;
    maxAttempts: string;
    questions: QuestionDraft[];
};

const KINDS = ['text', 'video', 'file', 'quiz'] as const;
const QUESTION_TYPES = ['single', 'multiple', 'true_false'] as const;
const blank: Pair = { en: '', ar: '' };
const key = () => crypto.randomUUID();

const trueFalse = (): OptionDraft[] => [
    { key: key(), id: 'true', label: { en: 'True', ar: 'صحيح' }, correct: true },
    { key: key(), id: 'false', label: { en: 'False', ar: 'خطأ' }, correct: false },
];

function fromServer(modules: Editor['modules']): ModuleDraft[] {
    return modules.map((m) => ({
        key: m.id,
        id: m.id,
        kind: m.kind,
        title: m.title,
        body: m.body,
        url: m.url ?? '',
        fileName: m.fileName,
        durationMinutes: m.durationMinutes ? String(m.durationMinutes) : '',
        required: m.required,
        passMark: m.passMark ? String(m.passMark) : '70',
        maxAttempts: m.maxAttempts ? String(m.maxAttempts) : '3',
        questions: m.questions.map((q) => ({
            key: q.id,
            id: q.id,
            type: q.type,
            prompt: q.prompt,
            points: q.points,
            options: q.options.map((o) => ({
                key: o.id,
                id: o.id,
                label: o.label,
                correct: o.correct,
            })),
        })),
    }));
}

/**
 * A course's modules in order (D98) and quiz questions (D102): add, reorder, remove, and a file
 * for file modules once saved. Saving sends the whole list; anything missing is removed.
 */
export function ModulesEditor({
    courseId,
    modules,
}: {
    courseId: string;
    modules: Editor['modules'];
}) {
    const { t } = useTranslation('training');
    const [drafts, setDrafts] = useState<ModuleDraft[]>(() => fromServer(modules));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const update = (index: number, next: Partial<ModuleDraft>) =>
        setDrafts((all) => all.map((d, i) => (i === index ? { ...d, ...next } : d)));
    const move = (index: number, by: number) =>
        setDrafts((all) => {
            const copy = [...all];
            const [item] = copy.splice(index, 1);
            copy.splice(index + by, 0, item!);

            return copy;
        });
    const add = (kind: string) =>
        setDrafts((all) => [
            ...all,
            {
                key: key(),
                id: null,
                kind,
                title: blank,
                body: blank,
                url: '',
                fileName: null,
                durationMinutes: '',
                required: true,
                passMark: '70',
                maxAttempts: '3',
                questions: [],
            },
        ]);

    const save = () => {
        setSaving(true);
        router.put(
            training.manage.courses.modules.url(courseId),
            {
                modules: drafts.map((d) => ({
                    id: d.id,
                    kind: d.kind,
                    title: d.title,
                    body: d.body.en ? d.body : null,
                    url: d.kind === 'video' ? d.url || null : null,
                    durationMinutes: d.durationMinutes ? Number(d.durationMinutes) : null,
                    required: d.required,
                    passMark: d.kind === 'quiz' ? Number(d.passMark) || 70 : null,
                    maxAttempts: d.kind === 'quiz' ? Number(d.maxAttempts) || 3 : null,
                    questions:
                        d.kind === 'quiz'
                            ? d.questions.map((q) => ({
                                  id: q.id,
                                  type: q.type,
                                  prompt: q.prompt,
                                  points: q.points,
                                  options: q.options.map((o) => ({
                                      id: o.id,
                                      label: o.label,
                                      correct: o.correct,
                                  })),
                              }))
                            : [],
                })),
            },
            {
                preserveScroll: true,
                onError: setErrors,
                onSuccess: () => setErrors({}),
                onFinish: () => setSaving(false),
            },
        );
    };

    return (
        <div className="flex flex-col gap-4" data-modules-editor>
            {errors.error ? (
                <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                    {errors.error}
                </p>
            ) : null}
            {drafts.length === 0 ? (
                <p className="text-small text-text-secondary">{t('editor.noModules')}</p>
            ) : null}
            <ol className="flex flex-col gap-3">
                {drafts.map((draft, index) => {
                    const Icon = MODULE_ICONS[draft.kind] ?? FallbackIcon;
                    const prefix = `modules.${index}`;

                    return (
                        <li
                            key={draft.key}
                            className="rounded-lg border border-border bg-card"
                            data-module-draft={draft.kind}
                        >
                            <div className="flex items-center gap-2 border-b border-border p-3">
                                <Icon aria-hidden className="size-4 text-text-secondary" />
                                <span className="min-w-0 flex-1 truncate text-small font-medium">
                                    {index + 1}. {draft.title.en || t(`moduleKind.${draft.kind}`)}
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    disabled={index === 0}
                                    aria-label={t('editor.moveUp')}
                                    onClick={() => move(index, -1)}
                                >
                                    <ArrowUp aria-hidden />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    disabled={index === drafts.length - 1}
                                    aria-label={t('editor.moveDown')}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowDown aria-hidden />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11 text-danger"
                                    aria-label={t('editor.removeModule')}
                                    onClick={() =>
                                        setDrafts((all) => all.filter((_, i) => i !== index))
                                    }
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </div>
                            <div className="flex flex-col gap-4 p-4">
                                <BilingualText
                                    id={`${draft.key}-title`}
                                    label={t('editor.moduleTitle')}
                                    value={draft.title}
                                    onChange={(title) => update(index, { title })}
                                    errors={errors}
                                    errorKey={`${prefix}.title`}
                                    required
                                    maxLength={160}
                                />
                                {draft.kind !== 'quiz' ? (
                                    <BilingualText
                                        id={`${draft.key}-body`}
                                        label={
                                            draft.kind === 'text'
                                                ? t('editor.lesson')
                                                : t('editor.notes')
                                        }
                                        value={draft.body}
                                        onChange={(body) => update(index, { body })}
                                        errors={errors}
                                        errorKey={`${prefix}.body`}
                                        multiline
                                        maxLength={20000}
                                        hint={t('editor.plainTextHint')}
                                    />
                                ) : null}
                                {draft.kind === 'video' ? (
                                    <Field
                                        id={`${draft.key}-url`}
                                        label={t('editor.videoUrl')}
                                        hint={t('editor.videoUrlHint')}
                                        error={errors[`${prefix}.url`]}
                                        required
                                    >
                                        <Input
                                            id={`${draft.key}-url`}
                                            type="url"
                                            dir="ltr"
                                            className="min-h-11"
                                            value={draft.url}
                                            onChange={(e) => update(index, { url: e.target.value })}
                                        />
                                    </Field>
                                ) : null}
                                {draft.kind === 'file' ? (
                                    <ModuleFile courseId={courseId} draft={draft} />
                                ) : null}
                                <div className="grid gap-4 sm:grid-cols-3">
                                    <Field id={`${draft.key}-minutes`} label={t('editor.minutes')}>
                                        <Input
                                            id={`${draft.key}-minutes`}
                                            type="number"
                                            inputMode="numeric"
                                            min={1}
                                            max={600}
                                            className="min-h-11"
                                            value={draft.durationMinutes}
                                            onChange={(e) =>
                                                update(index, { durationMinutes: e.target.value })
                                            }
                                        />
                                    </Field>
                                    {draft.kind === 'quiz' ? (
                                        <>
                                            <Field
                                                id={`${draft.key}-pass`}
                                                label={t('editor.passMark')}
                                                error={errors[`${prefix}.passMark`]}
                                            >
                                                <Input
                                                    id={`${draft.key}-pass`}
                                                    type="number"
                                                    inputMode="numeric"
                                                    min={1}
                                                    max={100}
                                                    className="min-h-11"
                                                    value={draft.passMark}
                                                    onChange={(e) =>
                                                        update(index, { passMark: e.target.value })
                                                    }
                                                />
                                            </Field>
                                            <Field
                                                id={`${draft.key}-attempts`}
                                                label={t('editor.attempts')}
                                                error={errors[`${prefix}.maxAttempts`]}
                                            >
                                                <Input
                                                    id={`${draft.key}-attempts`}
                                                    type="number"
                                                    inputMode="numeric"
                                                    min={1}
                                                    max={10}
                                                    className="min-h-11"
                                                    value={draft.maxAttempts}
                                                    onChange={(e) =>
                                                        update(index, {
                                                            maxAttempts: e.target.value,
                                                        })
                                                    }
                                                />
                                            </Field>
                                        </>
                                    ) : null}
                                </div>
                                <SwitchField
                                    id={`${draft.key}-required`}
                                    label={t('editor.required')}
                                    hint={t('editor.requiredHint')}
                                    checked={draft.required}
                                    onCheckedChange={(required) => update(index, { required })}
                                />
                                {draft.kind === 'quiz' ? (
                                    <QuestionsEditor
                                        questions={draft.questions}
                                        onChange={(questions) => update(index, { questions })}
                                        errors={errors}
                                        prefix={prefix}
                                    />
                                ) : null}
                            </div>
                        </li>
                    );
                })}
            </ol>
            <div className="flex flex-wrap gap-2" role="group" aria-label={t('editor.addModule')}>
                {KINDS.map((kind) => {
                    const Icon = MODULE_ICONS[kind] ?? FallbackIcon;

                    return (
                        <Button
                            key={kind}
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => add(kind)}
                            data-add-module={kind}
                        >
                            <Icon aria-hidden />
                            {t(`editor.add.${kind}`)}
                        </Button>
                    );
                })}
            </div>
            <Button
                className="min-h-11 self-end"
                onClick={save}
                disabled={saving}
                data-save-modules
            >
                {t('editor.saveModules')}
            </Button>
        </div>
    );
}

function ModuleFile({ courseId, draft }: { courseId: string; draft: ModuleDraft }) {
    const { t } = useTranslation('training');
    const [uploading, setUploading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (!draft.id) {
        return <p className="text-small text-text-secondary">{t('editor.fileAfterSave')}</p>;
    }

    const id = `${draft.key}-file`;

    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={id} className="text-small font-medium">
                {t('editor.file')}
            </label>
            {draft.fileName ? (
                <p className="text-small text-text-secondary">
                    {t('editor.currentFile', { name: draft.fileName })}
                </p>
            ) : null}
            <div className="flex items-center gap-2">
                <Upload aria-hidden className="size-4 text-text-secondary" />
                <input
                    id={id}
                    type="file"
                    className="min-h-11 text-small file:me-3 file:min-h-11 file:rounded-md file:border file:border-border file:bg-card file:px-3"
                    disabled={uploading}
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (!file || !draft.id) return;

                        setUploading(true);
                        router.post(
                            training.manage.courses.file.url([courseId, draft.id]),
                            { file },
                            {
                                forceFormData: true,
                                preserveScroll: true,
                                onError: (e) => setError(e.file ?? e.error ?? null),
                                onFinish: () => setUploading(false),
                            },
                        );
                    }}
                />
            </div>
            {error ? (
                <p role="alert" className="text-caption text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
}

function QuestionsEditor({
    questions,
    onChange,
    errors,
    prefix,
}: {
    questions: QuestionDraft[];
    onChange: (questions: QuestionDraft[]) => void;
    errors: Record<string, string>;
    prefix: string;
}) {
    const { t } = useTranslation('training');
    const set = (index: number, next: Partial<QuestionDraft>) =>
        onChange(questions.map((q, i) => (i === index ? { ...q, ...next } : q)));

    return (
        <section className="flex flex-col gap-3" aria-label={t('editor.questions')}>
            <h4 className="text-small font-medium">{t('editor.questions')}</h4>
            {errors[`${prefix}.questions`] ? (
                <p role="alert" className="text-caption text-danger">
                    {errors[`${prefix}.questions`]}
                </p>
            ) : null}
            {questions.map((question, index) => {
                const many = question.type === 'multiple';
                const fixed = question.type === 'true_false';
                const qPrefix = `${prefix}.questions.${index}`;

                return (
                    <fieldset
                        key={question.key}
                        className="flex flex-col gap-3 rounded-md border border-border p-3"
                        data-question-draft
                    >
                        <legend className="px-1 text-caption text-text-secondary">
                            {t('learn.quiz.question', { n: index + 1 })}
                        </legend>
                        <div className="flex flex-wrap items-end gap-2">
                            <Field
                                id={`${question.key}-type`}
                                label={t('editor.questionType')}
                                className="min-w-48 flex-1"
                            >
                                <Select
                                    value={question.type}
                                    onValueChange={(type) =>
                                        set(index, {
                                            type,
                                            options:
                                                type === 'true_false'
                                                    ? trueFalse()
                                                    : question.type === 'true_false'
                                                      ? [
                                                            {
                                                                key: key(),
                                                                id: null,
                                                                label: blank,
                                                                correct: true,
                                                            },
                                                            {
                                                                key: key(),
                                                                id: null,
                                                                label: blank,
                                                                correct: false,
                                                            },
                                                        ]
                                                      : question.options,
                                        })
                                    }
                                >
                                    <SelectTrigger id={`${question.key}-type`} className="min-h-11">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {QUESTION_TYPES.map((type) => (
                                            <SelectItem key={type} value={type}>
                                                {t(`questionType.${type}`)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                            <Button
                                type="button"
                                variant="ghost"
                                className="min-h-11 text-danger"
                                onClick={() => onChange(questions.filter((_, i) => i !== index))}
                            >
                                <Trash2 aria-hidden />
                                {t('editor.removeQuestion')}
                            </Button>
                        </div>
                        <BilingualText
                            id={`${question.key}-prompt`}
                            label={t('editor.prompt')}
                            value={question.prompt}
                            onChange={(prompt) => set(index, { prompt })}
                            errors={errors}
                            errorKey={`${qPrefix}.prompt`}
                            required
                            maxLength={1000}
                        />
                        <div
                            className="flex flex-col gap-2"
                            role="group"
                            aria-label={t('editor.answers')}
                        >
                            <p className="text-caption text-text-secondary">
                                {many ? t('editor.markMany') : t('editor.markOne')}
                            </p>
                            {question.options.map((option, o) => (
                                <div
                                    key={option.key}
                                    className="flex flex-col gap-2 rounded-md bg-muted/50 p-2 sm:flex-row sm:items-start"
                                >
                                    <label className="flex min-h-11 shrink-0 items-center gap-2 text-small">
                                        <input
                                            type={many ? 'checkbox' : 'radio'}
                                            name={`${question.key}-correct`}
                                            checked={option.correct}
                                            onChange={() =>
                                                set(index, {
                                                    options: question.options.map((other, j) =>
                                                        many
                                                            ? j === o
                                                                ? {
                                                                      ...other,
                                                                      correct: !other.correct,
                                                                  }
                                                                : other
                                                            : { ...other, correct: j === o },
                                                    ),
                                                })
                                            }
                                            className="size-4 accent-primary"
                                        />
                                        {t('editor.correct')}
                                    </label>
                                    <div className="min-w-0 flex-1">
                                        <BilingualText
                                            id={`${option.key}-label`}
                                            label={t('editor.answer', { n: o + 1 })}
                                            value={option.label}
                                            onChange={(label) =>
                                                set(index, {
                                                    options: question.options.map((other, j) =>
                                                        j === o ? { ...other, label } : other,
                                                    ),
                                                })
                                            }
                                            errors={errors}
                                            errorKey={`${qPrefix}.options.${o}.label`}
                                            required
                                            maxLength={300}
                                        />
                                    </div>
                                    {!fixed && question.options.length > 2 ? (
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="size-11 shrink-0"
                                            aria-label={t('editor.removeAnswer', { n: o + 1 })}
                                            onClick={() =>
                                                set(index, {
                                                    options: question.options.filter(
                                                        (_, j) => j !== o,
                                                    ),
                                                })
                                            }
                                        >
                                            <Trash2 aria-hidden />
                                        </Button>
                                    ) : null}
                                </div>
                            ))}
                            {errors[`${qPrefix}.options`] ? (
                                <p role="alert" className="text-caption text-danger">
                                    {errors[`${qPrefix}.options`]}
                                </p>
                            ) : null}
                            {!fixed && question.options.length < 8 ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="min-h-11 self-start"
                                    onClick={() =>
                                        set(index, {
                                            options: [
                                                ...question.options,
                                                {
                                                    key: key(),
                                                    id: null,
                                                    label: blank,
                                                    correct: false,
                                                },
                                            ],
                                        })
                                    }
                                >
                                    <Plus aria-hidden />
                                    {t('editor.addAnswer')}
                                </Button>
                            ) : null}
                        </div>
                    </fieldset>
                );
            })}
            <Button
                type="button"
                variant="outline"
                className="min-h-11 self-start"
                data-add-question
                onClick={() =>
                    onChange([
                        ...questions,
                        {
                            key: key(),
                            id: null,
                            type: 'single',
                            prompt: blank,
                            points: 1,
                            options: [
                                { key: key(), id: null, label: blank, correct: true },
                                { key: key(), id: null, label: blank, correct: false },
                            ],
                        },
                    ])
                }
            >
                <Plus aria-hidden />
                {t('editor.addQuestion')}
            </Button>
        </section>
    );
}
