import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, ChevronLeft, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { BilingualText, type Pair } from '@/components/mw/bilingual-text';
import { DirIcon } from '@/components/mw/dir-icon';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { CourseStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import training from '@/routes/training';

type Path = App.Domain.Training.Data.LearningPathEditorData;
type CourseOption = App.Domain.Training.Data.CourseRefData;
type Props = { paths: Path[]; courses: CourseOption[] };

/** Learning paths for trainers and HR: an ordered set of courses, published when every course is. */
export default function ManagePaths({ paths, courses }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [editing, setEditing] = useState<Path | 'new' | null>(null);
    const titleOf = (id: string) => courses.find((c) => c.id === id)?.title ?? '—';

    return (
        <>
            <Head title={t('paths.title')} />
            <Link
                href={training.manage.courses.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('manage.title')}
            </Link>
            <PageHeader
                title={t('paths.title')}
                description={t('paths.description')}
                actions={
                    <Button className="min-h-11" onClick={() => setEditing('new')} data-new-path>
                        <Plus aria-hidden />
                        {t('paths.new')}
                    </Button>
                }
            />
            {paths.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-trainings"
                    title={t('paths.empty')}
                    description={t('paths.emptyHelp')}
                />
            ) : (
                <ul className="grid gap-4 md:grid-cols-2">
                    {paths.map((path) => (
                        <li
                            key={path.id}
                            className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4"
                            data-path={path.id}
                        >
                            <div className="flex items-start justify-between gap-2">
                                <h3 className="text-h4">
                                    {locale === 'ar' && path.title.ar
                                        ? path.title.ar
                                        : path.title.en}
                                </h3>
                                <CourseStatusBadge status={path.status} />
                            </div>
                            <ol className="list-decimal ps-5 text-small text-text-secondary">
                                {path.courses.map((id) => (
                                    <li key={id}>{titleOf(id)}</li>
                                ))}
                            </ol>
                            <div className="mt-auto flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    className="min-h-11"
                                    onClick={() => setEditing(path)}
                                >
                                    {t('paths.edit')}
                                </Button>
                                {path.status !== 'published' ? (
                                    <Button
                                        className="min-h-11"
                                        data-publish-path
                                        onClick={() =>
                                            router.put(
                                                training.manage.paths.status.url(path.id),
                                                { status: 'published' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('editor.publish')}
                                    </Button>
                                ) : (
                                    <Button
                                        variant="ghost"
                                        className="min-h-11"
                                        onClick={() =>
                                            router.put(
                                                training.manage.paths.status.url(path.id),
                                                { status: 'archived' },
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        {t('editor.archive')}
                                    </Button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            {editing ? (
                <PathDialog
                    path={editing === 'new' ? null : editing}
                    courses={courses}
                    onClose={() => setEditing(null)}
                />
            ) : null}
        </>
    );
}

function PathDialog({
    path,
    courses,
    onClose,
}: {
    path: Path | null;
    courses: CourseOption[];
    onClose: () => void;
}) {
    const { t } = useTranslation('training');
    const [title, setTitle] = useState<Pair>(path?.title ?? { en: '', ar: '' });
    const [description, setDescription] = useState<Pair>(path?.description ?? { en: '', ar: '' });
    const [chosen, setChosen] = useState<string[]>(path?.courses ?? []);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const available = courses.filter((c) => !chosen.includes(c.id));
    const move = (index: number, by: number) =>
        setChosen((all) => {
            const copy = [...all];
            const [item] = copy.splice(index, 1);
            copy.splice(index + by, 0, item!);

            return copy;
        });

    const save = () => {
        const payload = {
            title,
            description: description.en ? description : null,
            courses: chosen,
        };
        const options = { preserveScroll: true, onError: setErrors, onSuccess: onClose };

        if (path) {
            router.put(training.manage.paths.update.url(path.id), payload, options);
        } else {
            router.post(training.manage.paths.store.url(), payload, options);
        }
    };

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={path ? t('paths.edit') : t('paths.new')}
            description={t('paths.dialogHelp')}
            className="sm:max-w-2xl"
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={onClose}>
                        {t('cancel')}
                    </Button>
                    <Button className="min-h-11" onClick={save} data-save-path>
                        {t('paths.save')}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                <BilingualText
                    id="path-title"
                    label={t('editor.title')}
                    value={title}
                    onChange={setTitle}
                    errors={errors}
                    errorKey="title"
                    required
                    maxLength={160}
                />
                <BilingualText
                    id="path-description"
                    label={t('editor.description')}
                    value={description}
                    onChange={setDescription}
                    errors={errors}
                    errorKey="description"
                    multiline
                    maxLength={4000}
                />
                <div className="flex flex-col gap-2">
                    <span className="text-small font-medium">{t('paths.courses')}</span>
                    <ol className="flex flex-col gap-1">
                        {chosen.map((id, index) => (
                            <li
                                key={id}
                                className="flex items-center gap-1 rounded-md bg-muted/60 ps-3 text-small"
                            >
                                <span className="min-w-0 flex-1 truncate">
                                    {index + 1}. {courses.find((c) => c.id === id)?.title ?? '—'}
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
                                    disabled={index === chosen.length - 1}
                                    aria-label={t('editor.moveDown')}
                                    onClick={() => move(index, 1)}
                                >
                                    <ArrowDown aria-hidden />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    className="size-11"
                                    aria-label={t('paths.removeCourse')}
                                    onClick={() => setChosen((all) => all.filter((c) => c !== id))}
                                >
                                    <Trash2 aria-hidden />
                                </Button>
                            </li>
                        ))}
                    </ol>
                    {available.length > 0 ? (
                        <Field id="path-add" label={t('paths.addCourse')} error={errors.courses}>
                            <Select
                                value=""
                                onValueChange={(id) => setChosen((all) => [...all, id])}
                            >
                                <SelectTrigger id="path-add" className="min-h-11">
                                    <SelectValue placeholder={t('paths.chooseCourse')} />
                                </SelectTrigger>
                                <SelectContent>
                                    {available.map((course) => (
                                        <SelectItem key={course.id} value={course.id}>
                                            {course.title} ({course.code})
                                            {course.status === 'draft'
                                                ? ` · ${t('courseStatus.draft')}`
                                                : ''}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    ) : null}
                </div>
            </div>
        </ResponsiveDialog>
    );
}
