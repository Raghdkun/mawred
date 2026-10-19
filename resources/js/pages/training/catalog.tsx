import { Head, Link, router } from '@inertiajs/react';
import { GraduationCap, Settings2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import { CourseCard } from '@/features/training/course-card';
import { CATEGORY_ICONS } from '@/features/training/icons';
import { cn } from '@/lib/utils';
import training from '@/routes/training';
import { type Paginated } from '@/types/pagination';

type Props = {
    courses: Paginated<App.Domain.Training.Data.CourseCardData>;
    paths: App.Domain.Training.Data.LearningPathData[];
    filters: { search: string; category: string; view: 'courses' | 'paths' };
    categories: string[];
    can: { manage: boolean };
};

/**
 * The training catalog (handoff 33): search and category pills over course cards, 3-up on
 * desktop and 1-up on phones; learning paths on their own tab.
 */
export default function Catalog({ courses, paths, filters, categories, can }: Props) {
    const { t } = useTranslation('training');
    const [search, setSearch] = useState(filters.search);
    const visit = (next: Partial<Props['filters']>) =>
        router.get(
            training.catalog.url(),
            Object.fromEntries(
                Object.entries({ ...filters, search, ...next }).filter(
                    ([key, v]) => v && !(key === 'view' && v === 'courses'),
                ),
            ),
            { preserveState: true, replace: true },
        );
    const filtered = filters.search !== '' || filters.category !== '';

    return (
        <>
            <Head title={t('catalog.title')} />
            <PageHeader
                title={t('catalog.title')}
                description={t('catalog.description')}
                actions={
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={training.my.url()}>
                                <GraduationCap aria-hidden />
                                {t('catalog.myLearning')}
                            </Link>
                        </Button>
                        {can.manage ? (
                            <Button asChild variant="outline" className="min-h-11">
                                <Link href={training.manage.courses.index.url()}>
                                    <Settings2 aria-hidden />
                                    {t('catalog.manage')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                }
            />

            <UnderlineTabs
                label={t('catalog.tabs')}
                items={(['courses', 'paths'] as const).map((view) => ({
                    key: view,
                    label: t(`catalog.view.${view}`),
                    href: training.catalog.url({ query: view === 'courses' ? {} : { view } }),
                    active: filters.view === view,
                }))}
            />

            {filters.view === 'paths' ? (
                paths.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-trainings"
                        title={t('catalog.noPaths')}
                        description={t('catalog.noPathsHelp')}
                    />
                ) : (
                    <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        {paths.map((path) => (
                            <li key={path.id}>
                                <article className="relative flex h-full flex-col gap-2 rounded-lg border border-border bg-card p-4 shadow-sm focus-within:ring-2 focus-within:ring-ring">
                                    <h3 className="text-h4">
                                        <Link
                                            href={training.paths.show.url(path.id)}
                                            className="outline-none after:absolute after:inset-0 after:content-['']"
                                        >
                                            {path.title}
                                        </Link>
                                    </h3>
                                    {path.description ? (
                                        <p className="line-clamp-3 text-small text-text-secondary">
                                            {path.description}
                                        </p>
                                    ) : null}
                                    <p className="mt-auto text-caption text-text-secondary">
                                        {t('catalog.pathCourses', { count: path.courses.length })}
                                        {path.joined ? ` · ${t('path.joined')}` : ''}
                                    </p>
                                </article>
                            </li>
                        ))}
                    </ul>
                )
            ) : (
                <>
                    <div className="mb-4 flex flex-col gap-3">
                        <SearchBox
                            value={search}
                            onChange={setSearch}
                            onSubmit={() => visit({})}
                            label={t('catalog.search')}
                            className="sm:max-w-sm"
                        />
                        <div
                            role="group"
                            aria-label={t('catalog.categories')}
                            className="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1"
                        >
                            {['', ...categories].map((category) => {
                                const Icon = category ? CATEGORY_ICONS[category] : null;
                                const on = filters.category === category;

                                return (
                                    <button
                                        key={category || 'all'}
                                        type="button"
                                        aria-pressed={on}
                                        onClick={() => visit({ category })}
                                        className={cn(
                                            'inline-flex min-h-11 shrink-0 items-center gap-1.5 rounded-full border px-4 text-small transition-colors motion-reduce:transition-none',
                                            on
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border bg-card hover:bg-muted',
                                        )}
                                        data-category={category || 'all'}
                                    >
                                        {Icon ? <Icon aria-hidden className="size-4" /> : null}
                                        {category ? t(`category.${category}`) : t('category.all')}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {courses.data.length === 0 ? (
                        filtered ? (
                            <PageState
                                kind="no-results"
                                title={t('catalog.noResults')}
                                description={t('catalog.noResultsHelp')}
                            />
                        ) : (
                            <PageState
                                kind="empty"
                                illustration="empty-trainings"
                                title={t('catalog.empty')}
                                description={t('catalog.emptyHelp')}
                            />
                        )
                    ) : (
                        <>
                            <ul className="grid gap-4 md:grid-cols-2 xl:grid-cols-3" data-courses>
                                {courses.data.map((course) => (
                                    <li key={course.id} className="flex">
                                        <CourseCard course={course} />
                                    </li>
                                ))}
                            </ul>
                            <Paginator
                                prevUrl={courses.prev_page_url}
                                nextUrl={courses.next_page_url}
                                summary={t('catalog.count', { count: courses.total })}
                            />
                        </>
                    )}
                </>
            )}
        </>
    );
}
