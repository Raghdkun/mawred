import { Head, Link, router } from '@inertiajs/react';
import { Plus, Route } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import { CourseStatusBadge } from '@/features/training/status';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import training from '@/routes/training';
import { type Paginated } from '@/types/pagination';

type Row = App.Domain.Training.Data.CourseRowData;
type Props = {
    courses: Paginated<Row>;
    filters: { status: string; search: string };
};

/** Courses for trainers and HR: status tabs, search, and how many people are on each. */
export default function ManageCourses({ courses, filters }: Props) {
    const { t } = useTranslation('training');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search);
    const n = (value: number) => formatNumber(value, locale);

    return (
        <>
            <Head title={t('manage.title')} />
            <PageHeader
                title={t('manage.title')}
                description={t('manage.description')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={training.manage.paths.index.url()}>
                                <Route aria-hidden />
                                {t('manage.paths')}
                            </Link>
                        </Button>
                        <Button asChild className="min-h-11">
                            <Link href={training.manage.courses.create.url()} data-new-course>
                                <Plus aria-hidden />
                                {t('manage.new')}
                            </Link>
                        </Button>
                    </div>
                }
            />
            <UnderlineTabs
                label={t('manage.statusTabs')}
                items={['', 'draft', 'published', 'archived'].map((status) => ({
                    key: status || 'current',
                    label: status ? t(`courseStatus.${status}`) : t('manage.current'),
                    href: training.manage.courses.index.url({ query: status ? { status } : {} }),
                    active: filters.status === status,
                }))}
            />
            <SearchBox
                value={search}
                onChange={setSearch}
                onSubmit={() =>
                    router.get(
                        training.manage.courses.index.url(),
                        Object.fromEntries(
                            Object.entries({ ...filters, search }).filter(([, v]) => v),
                        ),
                        {
                            preserveState: true,
                            replace: true,
                        },
                    )
                }
                label={t('manage.search')}
                className="mb-4 sm:max-w-sm"
            />

            {courses.data.length === 0 ? (
                <PageState
                    kind={filters.search ? 'no-results' : 'empty'}
                    illustration="empty-trainings"
                    title={filters.search ? t('catalog.noResults') : t('manage.empty')}
                    description={
                        filters.search ? t('catalog.noResultsHelp') : t('manage.emptyHelp')
                    }
                />
            ) : (
                <>
                    <ul className="flex flex-col gap-3 md:hidden">
                        {courses.data.map((course) => (
                            <li
                                key={course.id}
                                className="relative flex flex-col gap-2 rounded-lg border border-border bg-card p-4"
                            >
                                <Link
                                    href={training.manage.courses.edit.url(course.id)}
                                    className="text-body font-medium after:absolute after:inset-0 after:content-['']"
                                >
                                    {course.title}
                                </Link>
                                <div className="flex flex-wrap items-center gap-2 text-caption text-text-secondary">
                                    <CourseStatusBadge status={course.status} />
                                    <bdi dir="ltr">{course.code}</bdi>
                                    <span>{t(`category.${course.category}`)}</span>
                                    {course.mandatory ? (
                                        <span>{t('catalog.mandatory')}</span>
                                    ) : null}
                                </div>
                                <p className="text-caption text-text-secondary">
                                    {t('manage.counts', {
                                        active: n(course.active),
                                        requested: n(course.requested),
                                        completed: n(course.completed),
                                    })}
                                </p>
                            </li>
                        ))}
                    </ul>
                    <div className="hidden overflow-x-auto rounded-lg border border-border bg-card md:block">
                        <table className="w-full text-small">
                            <thead className="border-b border-border text-start text-caption text-text-secondary">
                                <tr>
                                    <th scope="col" className="p-3 text-start font-medium">
                                        {t('manage.columns.course')}
                                    </th>
                                    <th scope="col" className="p-3 text-start font-medium">
                                        {t('manage.columns.status')}
                                    </th>
                                    <th scope="col" className="p-3 text-end font-medium">
                                        {t('manage.columns.modules')}
                                    </th>
                                    <th scope="col" className="p-3 text-end font-medium">
                                        {t('manage.columns.active')}
                                    </th>
                                    <th scope="col" className="p-3 text-end font-medium">
                                        {t('manage.columns.requested')}
                                    </th>
                                    <th scope="col" className="p-3 text-end font-medium">
                                        {t('manage.columns.completed')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {courses.data.map((course) => (
                                    <tr key={course.id} data-course-row={course.id}>
                                        <td className="p-3">
                                            <Link
                                                href={training.manage.courses.edit.url(course.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {course.title}
                                            </Link>
                                            <div className="text-caption text-text-secondary">
                                                <bdi dir="ltr">{course.code}</bdi> ·{' '}
                                                {t(`category.${course.category}`)}
                                                {course.mandatory
                                                    ? ` · ${t('catalog.mandatory')}`
                                                    : ''}
                                                {course.requiresApproval
                                                    ? ` · ${t('catalog.approval')}`
                                                    : ''}
                                            </div>
                                        </td>
                                        <td className="p-3">
                                            <CourseStatusBadge status={course.status} />
                                        </td>
                                        <td className="p-3 text-end tabular-nums">
                                            {n(course.modules)}
                                        </td>
                                        <td className="p-3 text-end tabular-nums">
                                            <Link
                                                href={training.manage.courses.people.url(
                                                    course.id,
                                                    { query: { status: 'enrolled' } },
                                                )}
                                                className="hover:underline"
                                            >
                                                {n(course.active)}
                                            </Link>
                                        </td>
                                        <td className="p-3 text-end tabular-nums">
                                            {n(course.requested)}
                                        </td>
                                        <td className="p-3 text-end tabular-nums">
                                            {n(course.completed)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <Paginator
                        prevUrl={courses.prev_page_url}
                        nextUrl={courses.next_page_url}
                        summary={t('catalog.count', { count: courses.total })}
                    />
                </>
            )}
        </>
    );
}
