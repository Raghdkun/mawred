import { Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';
import training from '@/routes/training';
import { EnrollmentStatusBadge } from './status';

type Step = App.Domain.Training.Data.LearningPathData['courses'][number];

/** A learning path as a vertical stepper (handoff 33): done steps ticked, the current one ringed. */
export function PathStepper({ courses }: { courses: Step[] }) {
    const { t } = useTranslation('training');
    const current = courses.findIndex((c) => c.status !== 'completed');

    return (
        <ol className="flex flex-col" aria-label={t('path.courses')}>
            {courses.map((course, index) => {
                const done = course.status === 'completed';
                const last = index === courses.length - 1;

                return (
                    <li
                        key={course.id}
                        className="relative flex gap-4 pb-6 last:pb-0"
                        data-step={course.id}
                    >
                        {!last ? (
                            <span
                                aria-hidden
                                className={cn(
                                    'absolute start-[1.1875rem] top-10 bottom-0 w-0.5',
                                    done ? 'bg-primary' : 'bg-border',
                                )}
                            />
                        ) : null}
                        <span
                            aria-hidden
                            className={cn(
                                'z-10 inline-flex size-10 shrink-0 items-center justify-center rounded-full border-2 text-small font-semibold tabular-nums',
                                done
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : index === current
                                      ? 'border-primary bg-card text-primary'
                                      : 'border-border bg-card text-text-secondary',
                            )}
                        >
                            {done ? <Check className="size-5" /> : index + 1}
                        </span>
                        <div className="flex min-w-0 flex-1 flex-col gap-1 pt-1.5">
                            <span className="sr-only">{t('path.step', { n: index + 1 })}</span>
                            <Link
                                href={training.courses.show.url(course.id)}
                                className="text-body font-medium hover:underline"
                            >
                                {course.title}
                            </Link>
                            <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                {course.durationMinutes ? (
                                    <span>{t('minutes', { count: course.durationMinutes })}</span>
                                ) : null}
                                {course.status ? (
                                    <EnrollmentStatusBadge status={course.status} />
                                ) : (
                                    <span>{t('path.notStarted')}</span>
                                )}
                                {course.status === 'enrolled' ? (
                                    <span className="tabular-nums">
                                        {t('catalog.progress', { value: course.progress })}
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
