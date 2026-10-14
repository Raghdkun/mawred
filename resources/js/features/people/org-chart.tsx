import { Link } from '@inertiajs/react';
import { useInfiniteQuery } from '@tanstack/react-query';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { api, type CursorPage } from '@/lib/api';
import { localized } from '@/lib/localized';
import people from '@/routes/people';
import { PersonAvatar } from './person-avatar';

type Node = {
    id: string;
    employee_number: string;
    name: { en: string; ar: string | null };
    preferred_name: string | null;
    position: { name: Record<string, string> } | null;
    branch: { name: Record<string, string> } | null;
    direct_reports_count?: number;
};

function nodeName(node: Node, locale: string): string {
    return node.preferred_name ?? (locale === 'ar' && node.name.ar ? node.name.ar : node.name.en);
}

/** One level of the chart: the visible roots, or one manager's direct reports, paged. */
function useLevel(manager: string | null, enabled: boolean) {
    return useInfiniteQuery({
        queryKey: ['org-chart', manager],
        queryFn: ({ pageParam }) =>
            api<CursorPage<Node>>('employees', {
                params: {
                    ...(manager ? { manager } : { roots: 1 }),
                    with_reports_count: 1,
                    per_page: 50,
                    cursor: pageParam,
                },
            }),
        initialPageParam: null as string | null,
        getNextPageParam: (last) => last.meta.next_cursor,
        enabled,
    });
}

function Level({ manager, depth }: { manager: string | null; depth: number }) {
    const { t } = useTranslation('people');
    const level = useLevel(manager, true);
    const nodes = level.data?.pages.flatMap((page) => page.data) ?? [];

    if (level.isPending) {
        return (
            <div
                className="flex flex-col gap-2 py-2"
                style={{ paddingInlineStart: `${depth * 1.75}rem` }}
            >
                <Skeleton className="h-16 w-full max-w-sm" />
                <Skeleton className="h-16 w-full max-w-sm" />
            </div>
        );
    }

    if (level.isError) {
        return <PageState kind="error" title={t('org.failed')} />;
    }

    if (depth === 0 && nodes.length === 0) {
        return (
            <PageState kind="empty" illustration="empty-employees" title={t('directory.empty')} />
        );
    }

    return (
        <ul
            role={depth === 0 ? 'tree' : 'group'}
            aria-label={depth === 0 ? t('org.label') : undefined}
            className="flex flex-col gap-2"
        >
            {nodes.map((node) => (
                <OrgNode key={node.id} node={node} depth={depth} />
            ))}
            {level.hasNextPage ? (
                <li style={{ paddingInlineStart: `${depth * 1.75}rem` }}>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => void level.fetchNextPage()}
                        loading={level.isFetchingNextPage}
                    >
                        {t('common:actions.loadMore')}
                    </Button>
                </li>
            ) : null}
        </ul>
    );
}

function OrgNode({ node, depth }: { node: Node; depth: number }) {
    const { t } = useTranslation('people');
    const { locale } = useLocale();
    const [open, setOpen] = useState(depth === 0);
    const reports = node.direct_reports_count ?? 0;
    const name = nodeName(node, locale);

    return (
        <li
            role="treeitem"
            aria-selected={false}
            aria-expanded={reports > 0 ? open : undefined}
            aria-level={depth + 1}
        >
            <div
                className="flex items-center gap-2"
                style={{ paddingInlineStart: `${depth * 1.75}rem` }}
            >
                {reports > 0 ? (
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        onClick={() => setOpen(!open)}
                        aria-label={open ? t('org.collapse', { name }) : t('org.expand', { name })}
                    >
                        {open ? (
                            <ChevronDown aria-hidden />
                        ) : (
                            <DirIcon>
                                <ChevronRight aria-hidden />
                            </DirIcon>
                        )}
                    </Button>
                ) : (
                    <span aria-hidden className="size-8 shrink-0" />
                )}
                <Link
                    href={people.show.url(node.id)}
                    className="flex min-w-0 flex-1 items-center gap-3 rounded-lg border border-border bg-card p-3 shadow-sm transition-colors duration-150 ease-soft hover:border-input-hover focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:max-w-sm"
                >
                    <PersonAvatar name={name} />
                    <span className="flex min-w-0 flex-1 flex-col">
                        <span className="truncate text-sm font-medium">{name}</span>
                        <span className="truncate text-caption text-muted-foreground">
                            {node.position
                                ? localized(node.position.name, locale)
                                : node.employee_number}
                        </span>
                    </span>
                    {reports > 0 ? (
                        <span className="rounded-full bg-muted px-2 text-caption text-text-secondary">
                            {t('org.reports', { count: reports })}
                        </span>
                    ) : null}
                </Link>
            </div>
            {open && reports > 0 ? (
                <div className="mt-2">
                    <Level manager={node.id} depth={depth + 1} />
                </div>
            ) : null}
        </li>
    );
}

/**
 * Org chart (screen 7): starts at the top of what the viewer can see and grows toward the
 * reading end, one manager at a time. On phones it is the same indented tree.
 */
export function OrgChart() {
    return <Level manager={null} depth={0} />;
}
