import { Head, Link, router } from '@inertiajs/react';
import { Check, ChevronLeft, ChevronRight, Download, ExternalLink } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PlainText } from '@/components/mw/plain-text';
import { Button } from '@/components/ui/button';
import { FallbackIcon, MODULE_ICONS } from '@/features/training/icons';
import { ProgressRing } from '@/features/training/progress-ring';
import { QuizPlayer } from '@/features/training/quiz-player';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Props = {
    course: App.Domain.Training.Data.CourseRefData;
    outline: App.Domain.Training.Data.ModuleSummaryData[];
    module: App.Domain.Training.Data.ModuleData;
    previous: string | null;
    next: string | null;
    enrollment: App.Domain.Training.Data.EnrollmentData | null;
    preview: boolean;
};

/**
 * Working through a course (handoff 34): the module beside the outline (above it, folded, on
 * phones). Lessons, videos and files are marked done; quizzes complete by passing (D102).
 */
export default function Learn({
    course,
    outline,
    module,
    previous,
    next,
    enrollment,
    preview,
}: Props) {
    const { t } = useTranslation('training');
    const [busy, setBusy] = useState(false);
    const Icon = MODULE_ICONS[module.kind] ?? FallbackIcon;
    const goNext = () =>
        router.visit(
            next
                ? training.learn.url([course.id, next])
                : training.courses.show.url(course.id, {
                      query: enrollment?.status === 'completed' ? { completed: 1 } : {},
                  }),
        );

    const markDone = () => {
        setBusy(true);
        router.post(
            training.learn.complete.url([course.id, module.id]),
            {},
            { onFinish: () => setBusy(false) },
        );
    };

    const outlineList = (
        <ol className="flex flex-col gap-1" data-learn-outline>
            {outline.map((item, index) => {
                const ItemIcon = MODULE_ICONS[item.kind] ?? FallbackIcon;
                const current = item.id === module.id;

                return (
                    <li key={item.id}>
                        <Link
                            href={training.learn.url([course.id, item.id])}
                            aria-current={current ? 'step' : undefined}
                            className={cn(
                                'flex min-h-11 items-center gap-2 rounded-md px-2 py-1.5 text-small',
                                current
                                    ? 'bg-primary/10 font-medium text-foreground'
                                    : 'text-text-secondary hover:bg-muted',
                            )}
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'inline-flex size-6 shrink-0 items-center justify-center rounded-full',
                                    item.done ? 'bg-success-bg text-success' : 'bg-muted',
                                )}
                            >
                                {item.done ? (
                                    <Check className="size-3.5" />
                                ) : (
                                    <ItemIcon className="size-3.5" />
                                )}
                            </span>
                            <span className="min-w-0 flex-1 truncate">
                                <span className="sr-only">
                                    {t('course.step', { n: index + 1 })}{' '}
                                </span>
                                {item.title}
                            </span>
                            {item.done ? <span className="sr-only">{t('course.done')}</span> : null}
                        </Link>
                    </li>
                );
            })}
        </ol>
    );

    return (
        <>
            <Head title={`${module.title} · ${course.title}`} />
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <Link
                    href={training.courses.show.url(course.id)}
                    className="inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
                >
                    <DirIcon>
                        <ChevronLeft aria-hidden className="size-4" />
                    </DirIcon>
                    {course.title}
                </Link>
                {enrollment ? (
                    <ProgressRing
                        value={enrollment.progress}
                        size={44}
                        label={t('catalog.progress', { value: enrollment.progress })}
                    />
                ) : null}
            </div>

            {preview ? (
                <p
                    className="mb-4 rounded-md border border-border bg-muted p-3 text-small text-text-secondary"
                    data-preview
                >
                    {t('learn.preview')}
                </p>
            ) : null}

            <div className="grid gap-6 md:grid-cols-[16rem_minmax(0,1fr)] md:items-start">
                <nav
                    aria-label={t('learn.outline')}
                    className="hidden md:sticky md:top-20 md:block"
                >
                    {outlineList}
                </nav>
                <details className="rounded-lg border border-border bg-card md:hidden">
                    <summary className="flex min-h-11 cursor-pointer items-center px-3 text-small font-medium">
                        {t('learn.outline')}
                    </summary>
                    <nav aria-label={t('learn.outline')} className="p-2">
                        {outlineList}
                    </nav>
                </details>

                <article className="flex min-w-0 flex-col gap-5" data-module={module.id}>
                    <header className="flex flex-col gap-1">
                        <p className="inline-flex items-center gap-1.5 text-caption text-text-secondary">
                            <Icon aria-hidden className="size-3.5" />
                            {t(`moduleKind.${module.kind}`)}
                            {module.durationMinutes
                                ? ` · ${t('minutes', { count: module.durationMinutes })}`
                                : ''}
                            {module.required ? '' : ` · ${t('course.optional')}`}
                        </p>
                        <h1 className="text-h2">{module.title}</h1>
                    </header>

                    {module.body ? (
                        <PlainText text={module.body} className="text-body leading-relaxed" />
                    ) : null}

                    {module.kind === 'video' && module.url ? (
                        <Button asChild variant="outline" className="min-h-11 self-start">
                            <a
                                href={module.url}
                                target="_blank"
                                rel="noreferrer noopener"
                                data-open-video
                            >
                                {t('learn.openVideo')}
                                <ExternalLink aria-hidden />
                                <span className="sr-only">{t('learn.newTab')}</span>
                            </a>
                        </Button>
                    ) : null}

                    {module.kind === 'file' && module.fileName ? (
                        <Button asChild variant="outline" className="min-h-11 self-start">
                            <a href={training.learn.file.url([course.id, module.id])} data-download>
                                <Download aria-hidden />
                                {t('learn.download', { name: module.fileName })}
                            </a>
                        </Button>
                    ) : null}

                    {module.kind === 'quiz' && module.quiz ? (
                        <QuizPlayer
                            key={module.id}
                            courseId={course.id}
                            moduleId={module.id}
                            quiz={module.quiz}
                            preview={preview}
                            onContinue={goNext}
                        />
                    ) : null}

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t border-border pt-4">
                        {previous ? (
                            <Button asChild variant="ghost" className="min-h-11">
                                <Link href={training.learn.url([course.id, previous])}>
                                    <DirIcon>
                                        <ChevronLeft aria-hidden />
                                    </DirIcon>
                                    {t('learn.previous')}
                                </Link>
                            </Button>
                        ) : (
                            <span />
                        )}
                        {module.kind !== 'quiz' && !preview && !module.done ? (
                            <Button
                                className="min-h-11"
                                onClick={markDone}
                                disabled={busy}
                                data-mark-done
                            >
                                {next ? t('learn.doneNext') : t('learn.doneFinish')}
                            </Button>
                        ) : next ? (
                            <Button
                                asChild
                                variant={module.done || preview ? 'default' : 'ghost'}
                                className="min-h-11"
                            >
                                <Link href={training.learn.url([course.id, next])}>
                                    {t('learn.next')}
                                    <DirIcon>
                                        <ChevronRight aria-hidden />
                                    </DirIcon>
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </article>
            </div>
        </>
    );
}
