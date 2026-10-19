import { Head, Link, router, usePage } from '@inertiajs/react';
import { Award, Check, ChevronLeft, Clock, Pencil, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PlainText } from '@/components/mw/plain-text';
import { SuccessRipple } from '@/components/mw/success-ripple';
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
import { CATEGORY_ICONS, FallbackIcon, MODULE_ICONS } from '@/features/training/icons';
import { ProgressRing } from '@/features/training/progress-ring';
import { CourseStatusBadge, DueChip, EnrollmentStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Props = {
    course: App.Domain.Training.Data.CourseCardData;
    description: string | null;
    validityMonths: number | null;
    status: string;
    modules: App.Domain.Training.Data.ModuleSummaryData[];
    enrollment: App.Domain.Training.Data.EnrollmentData | null;
    paths: Array<{ id: string; title: string }>;
    can: { enrol: boolean; learn: boolean; leave: boolean; manage: boolean };
};

/**
 * A course (handoff 33): what it covers, its modules with the learner's progress, and the
 * enrolment card: enrol (or ask, D99), continue where they left off, or leave.
 */
export default function Course({
    course,
    description,
    validityMonths,
    status,
    modules,
    enrollment,
    paths,
    can,
}: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const page = usePage();
    const flash = page.flash as { completed?: boolean } | undefined;
    const justCompleted =
        Boolean(flash?.completed) ||
        (enrollment?.status === 'completed' &&
            new URLSearchParams(page.url.split('?')[1] ?? '').has('completed'));
    const [leaving, setLeaving] = useState(false);
    const [busy, setBusy] = useState(false);
    const Icon = CATEGORY_ICONS[course.category] ?? FallbackIcon;
    const nextModule = modules.find((m) => !m.done) ?? modules[0];
    const started = modules.some((m) => m.done);

    const enrol = () => {
        setBusy(true);
        router.post(
            training.courses.enrol.url(course.id),
            {},
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    return (
        <>
            <Head title={course.title} />
            <Link
                href={training.catalog.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('course.back')}
            </Link>

            {status !== 'published' ? (
                <p className="mb-4 rounded-md border border-status-pending-border bg-status-pending-bg p-3 text-small text-status-pending-fg">
                    {t('course.notPublished')}
                </p>
            ) : null}

            {justCompleted ? (
                <div
                    className="mb-6 flex flex-col items-center gap-2 rounded-lg bg-card p-6 text-center shadow-sm"
                    role="status"
                    data-course-completed
                >
                    <SuccessRipple label={t('course.completedTitle')} />
                    <h2 className="text-h3">{t('course.completedTitle')}</h2>
                    <p className="text-small text-text-secondary">{t('course.completedHelp')}</p>
                </div>
            ) : null}

            <div className="grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start">
                <div className="flex flex-col gap-6">
                    <header className="flex flex-col gap-2">
                        <p className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                            <span className="inline-flex items-center gap-1.5">
                                <Icon aria-hidden className="size-3.5" />
                                {t(`category.${course.category}`)}
                            </span>
                            <span aria-hidden>·</span>
                            <bdi dir="ltr">{course.code}</bdi>
                            {can.manage ? <CourseStatusBadge status={status} /> : null}
                        </p>
                        <h1 className="text-h2">{course.title}</h1>
                        {course.summary ? (
                            <p className="text-body text-text-secondary">{course.summary}</p>
                        ) : null}
                        <ul className="flex flex-wrap gap-x-4 gap-y-1 text-small text-text-secondary">
                            {course.durationMinutes ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <Clock aria-hidden className="size-4" />
                                    {t('minutes', { count: course.durationMinutes })}
                                </li>
                            ) : null}
                            {course.issuesCertificate ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <Award aria-hidden className="size-4" />
                                    {validityMonths
                                        ? t('course.certificateValid', { count: validityMonths })
                                        : t('course.certificate')}
                                </li>
                            ) : null}
                            {course.mandatory ? (
                                <li className="inline-flex items-center gap-1.5">
                                    <Users aria-hidden className="size-4" />
                                    {t('course.mandatoryHelp')}
                                </li>
                            ) : null}
                        </ul>
                    </header>

                    {description ? (
                        <PlainText text={description} className="text-body leading-relaxed" />
                    ) : null}

                    <section aria-labelledby="outline-title" className="flex flex-col gap-3">
                        <h2 id="outline-title" className="text-h3">
                            {t('course.outline')}
                        </h2>
                        <ol
                            className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card"
                            data-outline
                        >
                            {modules.map((module, index) => {
                                const ModuleIcon = MODULE_ICONS[module.kind] ?? FallbackIcon;
                                const body = (
                                    <>
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'inline-flex size-9 shrink-0 items-center justify-center rounded-full',
                                                module.done
                                                    ? 'bg-success-bg text-success'
                                                    : 'bg-muted text-text-secondary',
                                            )}
                                        >
                                            {module.done ? (
                                                <Check className="size-4" />
                                            ) : (
                                                <ModuleIcon className="size-4" />
                                            )}
                                        </span>
                                        <span className="flex min-w-0 flex-1 flex-col">
                                            <span className="text-body font-medium">
                                                <span className="sr-only">
                                                    {t('course.step', { n: index + 1 })}{' '}
                                                </span>
                                                {module.title}
                                            </span>
                                            <span className="text-caption text-text-secondary">
                                                {t(`moduleKind.${module.kind}`)}
                                                {module.durationMinutes
                                                    ? ` · ${t('minutes', { count: module.durationMinutes })}`
                                                    : ''}
                                                {module.required
                                                    ? ''
                                                    : ` · ${t('course.optional')}`}
                                                {module.quiz
                                                    ? ` · ${t('course.quizInfo', { mark: module.quiz.passMark, used: module.quiz.attemptsUsed, allowed: module.quiz.attemptsAllowed })}`
                                                    : ''}
                                            </span>
                                        </span>
                                        {module.done ? (
                                            <span className="sr-only">{t('course.done')}</span>
                                        ) : null}
                                    </>
                                );

                                return (
                                    <li key={module.id}>
                                        {can.learn || can.manage ? (
                                            <Link
                                                href={training.learn.url([course.id, module.id])}
                                                className="flex min-h-14 items-center gap-3 p-3 hover:bg-muted/60 focus-visible:bg-muted/60"
                                            >
                                                {body}
                                            </Link>
                                        ) : (
                                            <div className="flex min-h-14 items-center gap-3 p-3">
                                                {body}
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ol>
                    </section>

                    {paths.length > 0 ? (
                        <section aria-labelledby="paths-title" className="flex flex-col gap-2">
                            <h2 id="paths-title" className="text-h4">
                                {t('course.partOf')}
                            </h2>
                            <ul className="flex flex-wrap gap-2">
                                {paths.map((path) => (
                                    <li key={path.id}>
                                        <Link
                                            href={training.paths.show.url(path.id)}
                                            className="inline-flex min-h-11 items-center rounded-full border border-border px-4 text-small hover:bg-muted"
                                        >
                                            {path.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    ) : null}
                </div>

                <aside
                    className="order-first flex flex-col gap-4 rounded-lg bg-card p-5 shadow-sm lg:sticky lg:top-20 lg:order-none"
                    data-enrolment-card
                >
                    {enrollment && ['enrolled', 'completed'].includes(enrollment.status) ? (
                        <div className="flex items-center gap-4">
                            <ProgressRing
                                value={enrollment.progress}
                                size={72}
                                label={t('catalog.progress', { value: enrollment.progress })}
                            />
                            <div className="flex flex-col gap-1">
                                <EnrollmentStatusBadge
                                    status={enrollment.status}
                                    className="self-start"
                                />
                                {enrollment.completedAt ? (
                                    <p className="text-small text-text-secondary">
                                        {t('course.completedOn', {
                                            date: formatDate(enrollment.completedAt, locale, {
                                                dateStyle: 'medium',
                                            }),
                                        })}
                                    </p>
                                ) : null}
                                {enrollment.dueOn && enrollment.status === 'enrolled' ? (
                                    <DueChip
                                        dueOn={enrollment.dueOn}
                                        overdue={enrollment.overdue}
                                        className="self-start"
                                        label={
                                            enrollment.overdue
                                                ? t('course.overdueOn', {
                                                      date: formatDate(enrollment.dueOn, locale, {
                                                          dateStyle: 'medium',
                                                      }),
                                                  })
                                                : t('course.due', {
                                                      date: formatDate(enrollment.dueOn, locale, {
                                                          dateStyle: 'medium',
                                                      }),
                                                  })
                                        }
                                    />
                                ) : null}
                            </div>
                        </div>
                    ) : enrollment?.status === 'requested' ? (
                        <div className="flex flex-col gap-2">
                            <EnrollmentStatusBadge status="requested" className="self-start" />
                            <p className="text-small text-text-secondary">
                                {t('course.requested')}
                            </p>
                        </div>
                    ) : enrollment && ['rejected', 'cancelled'].includes(enrollment.status) ? (
                        <p className="text-small text-text-secondary">
                            {t(`course.${enrollment.status}`)}
                        </p>
                    ) : (
                        <p className="text-small text-text-secondary">
                            {course.requiresApproval
                                ? t('course.requestHelp')
                                : t('course.enrolHelp')}
                        </p>
                    )}

                    {can.learn && nextModule ? (
                        <Button asChild className="min-h-11" data-continue>
                            <Link href={training.learn.url([course.id, nextModule.id])}>
                                {enrollment?.status === 'completed'
                                    ? t('course.review')
                                    : started
                                      ? t('course.continue')
                                      : t('course.start')}
                            </Link>
                        </Button>
                    ) : null}
                    {can.enrol ? (
                        <Button className="min-h-11" onClick={enrol} disabled={busy} data-enrol>
                            {course.requiresApproval ? t('course.request') : t('course.enrol')}
                        </Button>
                    ) : null}
                    {can.leave && enrollment ? (
                        <Button
                            variant="ghost"
                            className="min-h-11"
                            onClick={() => setLeaving(true)}
                            data-leave
                        >
                            {enrollment.status === 'requested'
                                ? t('course.withdraw')
                                : t('course.leave')}
                        </Button>
                    ) : null}
                    {can.manage ? (
                        <div className="flex flex-wrap gap-2 border-t border-border pt-4">
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={training.manage.courses.edit.url(course.id)}>
                                    <Pencil aria-hidden />
                                    {t('course.edit')}
                                </Link>
                            </Button>
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={training.manage.courses.people.url(course.id)}>
                                    <Users aria-hidden />
                                    {t('course.people')}
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </aside>
            </div>

            <AlertDialog open={leaving} onOpenChange={setLeaving}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('course.leaveTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('course.leaveHelp')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel className="min-h-11">{t('cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            className="min-h-11"
                            data-confirm-leave
                            onClick={() =>
                                enrollment &&
                                router.delete(training.enrollments.cancel.url(enrollment.id), {
                                    preserveScroll: true,
                                })
                            }
                        >
                            {t('course.leaveConfirm')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
