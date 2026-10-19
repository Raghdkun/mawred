import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, MoreHorizontal, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AssignDialog } from '@/features/training/assign-dialog';
import { ProgressRing } from '@/features/training/progress-ring';
import { DueChip, EnrollmentStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import training from '@/routes/training';
import { type Paginated } from '@/types/pagination';

type Enrollment = App.Domain.Training.Data.EnrollmentData;
type Props = {
    course: App.Domain.Training.Data.CourseRefData;
    quizzes: Array<{ id: string; title: string }>;
    enrollments: Paginated<Enrollment>;
    filters: { status: string; search: string };
    feedback: {
        count: number;
        average: number;
        comments: Array<{ rating: number; comment: string; at: string | null }>;
    };
};

const STATUSES = ['', 'enrolled', 'overdue', 'requested', 'completed', 'cancelled'] as const;

/** The people on a course (trainers and HR): progress and due dates, assigning, resetting a quiz, removing. */
export default function CoursePeople({ course, quizzes, enrollments, filters, feedback }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search);
    const [assigning, setAssigning] = useState(false);

    return (
        <>
            <Head title={`${t('course.people')} · ${course.title}`} />
            <Link
                href={training.manage.courses.edit.url(course.id)}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {course.title}
            </Link>
            <PageHeader
                title={t('people.title')}
                description={course.mandatory ? t('people.mandatoryHelp') : t('people.help')}
                actions={
                    course.status === 'published' ? (
                        <Button className="min-h-11" onClick={() => setAssigning(true)} data-assign>
                            <UserPlus aria-hidden />
                            {t('people.assign')}
                        </Button>
                    ) : null
                }
            />
            <UnderlineTabs
                label={t('people.statusTabs')}
                items={STATUSES.map((status) => ({
                    key: status || 'all',
                    label: status ? t(`people.status.${status}`) : t('people.status.all'),
                    href: training.manage.courses.people.url(course.id, {
                        query: status ? { status } : {},
                    }),
                    active: filters.status === status,
                }))}
            />
            <SearchBox
                value={search}
                onChange={setSearch}
                onSubmit={() =>
                    router.get(
                        training.manage.courses.people.url(course.id),
                        Object.fromEntries(
                            Object.entries({ ...filters, search }).filter(([, v]) => v),
                        ),
                        {
                            preserveState: true,
                            replace: true,
                        },
                    )
                }
                label={t('people.search')}
                className="mb-4 sm:max-w-sm"
            />
            {enrollments.data.length === 0 ? (
                <PageState
                    kind={filters.search || filters.status ? 'no-results' : 'empty'}
                    illustration="empty-trainings"
                    title={t('people.empty')}
                    description={t('people.emptyHelp')}
                />
            ) : (
                <>
                    <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                        {enrollments.data.map((row) => (
                            <li
                                key={row.id}
                                className="flex items-center gap-3 p-3"
                                data-person={row.id}
                            >
                                <ProgressRing
                                    value={row.progress}
                                    size={44}
                                    label={t('catalog.progress', { value: row.progress })}
                                />
                                <div className="flex min-w-0 flex-1 flex-col gap-1">
                                    <span className="truncate text-body font-medium">
                                        {row.employee?.name ?? '—'}
                                    </span>
                                    <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                        <EnrollmentStatusBadge status={row.status} />
                                        <span>{t(`source.${row.source}`)}</span>
                                        {row.dueOn && row.status === 'enrolled' ? (
                                            <DueChip
                                                dueOn={row.dueOn}
                                                overdue={row.overdue}
                                                label={
                                                    row.overdue
                                                        ? t('course.overdueOn', {
                                                              date: formatDate(row.dueOn, locale, {
                                                                  dateStyle: 'medium',
                                                              }),
                                                          })
                                                        : t('course.due', {
                                                              date: formatDate(row.dueOn, locale, {
                                                                  dateStyle: 'medium',
                                                              }),
                                                          })
                                                }
                                            />
                                        ) : null}
                                        {row.completedAt ? (
                                            <span>
                                                {t('course.completedOn', {
                                                    date: formatDate(row.completedAt, locale, {
                                                        dateStyle: 'medium',
                                                    }),
                                                })}
                                            </span>
                                        ) : null}
                                    </div>
                                </div>
                                {['requested', 'enrolled'].includes(row.status) ? (
                                    <DropdownMenu dir={locale === 'ar' ? 'rtl' : 'ltr'}>
                                        <DropdownMenuTrigger asChild>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="size-11"
                                                aria-label={t('people.actions', {
                                                    name: row.employee?.name ?? '',
                                                })}
                                                data-person-menu
                                            >
                                                <MoreHorizontal aria-hidden />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            {quizzes.map((quiz) => (
                                                <DropdownMenuItem
                                                    key={quiz.id}
                                                    onSelect={() =>
                                                        router.post(
                                                            training.manage.enrollments.reset.url([
                                                                row.id,
                                                                quiz.id,
                                                            ]),
                                                            {},
                                                            { preserveScroll: true },
                                                        )
                                                    }
                                                >
                                                    {t('people.resetQuiz', { quiz: quiz.title })}
                                                </DropdownMenuItem>
                                            ))}
                                            <DropdownMenuItem
                                                className="text-danger"
                                                data-remove-person
                                                onSelect={() =>
                                                    router.delete(
                                                        training.enrollments.cancel.url(row.id),
                                                        { preserveScroll: true },
                                                    )
                                                }
                                            >
                                                {t('people.remove')}
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                    <Paginator
                        prevUrl={enrollments.prev_page_url}
                        nextUrl={enrollments.next_page_url}
                        summary={t('people.count', { count: enrollments.total })}
                    />
                </>
            )}
            {feedback.count > 0 ? (
                <section
                    aria-labelledby="feedback-summary"
                    className="mt-8 flex flex-col gap-3"
                    data-feedback-summary
                >
                    <h2 id="feedback-summary" className="text-h3">
                        {t('feedback.summary', {
                            average: formatNumber(feedback.average, locale, {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            }),
                            count: feedback.count,
                        })}
                    </h2>
                    <ul className="flex flex-col gap-2">
                        {feedback.comments.map((c, i) => (
                            <li key={i} className="rounded-md bg-card p-3 text-small shadow-sm">
                                <span className="sr-only">
                                    {t('feedback.stars', { count: c.rating })}:{' '}
                                </span>
                                <span aria-hidden className="me-2 text-warning">
                                    {'★'.repeat(c.rating)}
                                </span>
                                {c.comment}
                            </li>
                        ))}
                    </ul>
                </section>
            ) : null}
            {assigning ? (
                <AssignDialog courseId={course.id} onClose={() => setAssigning(false)} />
            ) : null}
        </>
    );
}
