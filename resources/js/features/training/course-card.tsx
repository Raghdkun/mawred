import { Link } from '@inertiajs/react';
import { Award, Clock, Layers } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import training from '@/routes/training';
import { CATEGORY_ICONS, FallbackIcon } from './icons';
import { ProgressRing } from './progress-ring';
import { DueChip, EnrollmentStatusBadge } from './status';

type Card = App.Domain.Training.Data.CourseCardData;

/** A course card in the catalog (handoff 33): category, length, modules and the viewer's progress. */
export function CourseCard({ course }: { course: Card }) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const Icon = CATEGORY_ICONS[course.category] ?? FallbackIcon;
    const enrollment = course.enrollment;
    const live = enrollment && ['enrolled', 'completed'].includes(enrollment.status);

    return (
        <article
            className="group relative flex w-full flex-col gap-3 rounded-lg border border-border bg-card p-4 shadow-sm transition-shadow focus-within:ring-2 focus-within:ring-ring hover:shadow-md motion-reduce:transition-none"
            data-course={course.id}
        >
            <div className="flex items-start justify-between gap-3">
                <span className="inline-flex items-center gap-1.5 rounded-full bg-muted px-2 py-0.5 text-caption text-text-secondary">
                    <Icon aria-hidden className="size-3.5" />
                    {t(`category.${course.category}`)}
                </span>
                {live ? (
                    <ProgressRing
                        value={enrollment.progress}
                        size={44}
                        label={t('catalog.progress', { value: enrollment.progress })}
                    />
                ) : null}
            </div>
            <div className="flex flex-col gap-1">
                <h3 className="text-h4">
                    <Link
                        href={training.courses.show.url(course.id)}
                        className="outline-none after:absolute after:inset-0 after:content-['']"
                    >
                        {course.title}
                    </Link>
                </h3>
                {course.summary ? (
                    <p className="line-clamp-2 text-small text-text-secondary">{course.summary}</p>
                ) : null}
            </div>
            <ul className="mt-auto flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-text-secondary">
                {course.durationMinutes ? (
                    <li className="inline-flex items-center gap-1">
                        <Clock aria-hidden className="size-3.5" />
                        {t('minutes', { count: course.durationMinutes })}
                    </li>
                ) : null}
                <li className="inline-flex items-center gap-1">
                    <Layers aria-hidden className="size-3.5" />
                    {t('catalog.modules', { count: course.modules })}
                </li>
                {course.issuesCertificate ? (
                    <li className="inline-flex items-center gap-1">
                        <Award aria-hidden className="size-3.5" />
                        {t('catalog.certificate')}
                    </li>
                ) : null}
            </ul>
            <div className="flex flex-wrap items-center gap-2">
                {course.mandatory ? (
                    <span className="rounded-full border border-status-pending-border bg-status-pending-bg px-2 py-0.5 text-caption text-status-pending-fg">
                        {t('catalog.mandatory')}
                    </span>
                ) : null}
                {course.requiresApproval && !enrollment ? (
                    <span className="rounded-full border border-border px-2 py-0.5 text-caption text-text-secondary">
                        {t('catalog.approval')}
                    </span>
                ) : null}
                {enrollment ? <EnrollmentStatusBadge status={enrollment.status} /> : null}
                {enrollment?.dueOn && enrollment.status === 'enrolled' ? (
                    <DueChip
                        dueOn={enrollment.dueOn}
                        overdue={enrollment.overdue}
                        label={
                            enrollment.overdue
                                ? t('course.overdue')
                                : t('course.due', {
                                      date: formatDate(enrollment.dueOn, locale, {
                                          dateStyle: 'medium',
                                      }),
                                  })
                        }
                    />
                ) : null}
            </div>
        </article>
    );
}
