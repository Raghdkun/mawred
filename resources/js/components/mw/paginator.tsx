import { router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { DirIcon } from '@/components/mw/dir-icon';
import { Button } from '@/components/ui/button';

/** Previous / next for server-paged lists (works for page links and cursor links). */
export function Paginator({
    prevUrl,
    nextUrl,
    summary,
}: {
    prevUrl: string | null;
    nextUrl: string | null;
    summary?: string;
}) {
    const { t } = useTranslation('common');

    if (!prevUrl && !nextUrl) {
        return null;
    }

    const go = (url: string | null) =>
        url && router.visit(url, { preserveState: true, preserveScroll: true });

    return (
        <nav
            aria-label={t('pagination.label')}
            className="mt-4 flex flex-wrap items-center justify-between gap-3"
        >
            <span className="text-caption text-muted-foreground">{summary}</span>
            <div className="flex gap-2">
                <Button variant="outline" size="sm" disabled={!prevUrl} onClick={() => go(prevUrl)}>
                    <DirIcon>
                        <ChevronLeft aria-hidden />
                    </DirIcon>
                    {t('pagination.previous')}
                </Button>
                <Button variant="outline" size="sm" disabled={!nextUrl} onClick={() => go(nextUrl)}>
                    {t('pagination.next')}
                    <DirIcon>
                        <ChevronRight aria-hidden />
                    </DirIcon>
                </Button>
            </div>
        </nav>
    );
}
