import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import workflows from '@/routes/workflows';
import { type Paginated } from '@/types/pagination';

type Stuck = {
    id: string;
    workflow: Record<string, string>;
    request: string | null;
    reason: string | null;
    since: string | null;
    step: { id: string; node: string; type: string; attempts: number; error: string | null } | null;
};

/** Dead-letter view (ADR-0009): workflows stopped on a step that failed every retry. */
export default function StuckWorkflows({ instances }: { instances: Paginated<Stuck> }) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const [cancelling, setCancelling] = useState<Stuck | null>(null);
    const [reason, setReason] = useState('');

    return (
        <>
            <Head title={t('stuck.pageTitle')} />
            <Link
                href={workflows.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('index.title')}
            </Link>
            <PageHeader title={t('stuck.title')} description={t('stuck.description')} />

            {instances.data.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-inbox"
                    title={t('stuck.empty')}
                    description={t('stuck.emptyHelp')}
                />
            ) : (
                <ul className="flex flex-col gap-3">
                    {instances.data.map((instance) => (
                        <li
                            key={instance.id}
                            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="flex flex-col gap-0.5">
                                    <span className="text-sm font-medium">
                                        {localized(instance.workflow, locale)}
                                    </span>
                                    <span className="text-caption text-muted-foreground">
                                        {instance.request ? (
                                            <bdi dir="ltr">{instance.request}</bdi>
                                        ) : null}
                                        {instance.since
                                            ? ` · ${formatDate(instance.since, locale, { dateStyle: 'medium', timeStyle: 'short' })}`
                                            : ''}
                                    </span>
                                </div>
                                {instance.step ? (
                                    <span className="text-caption text-muted-foreground">
                                        <bdi dir="ltr">{instance.step.node}</bdi> ·{' '}
                                        {t(`nodes.${instance.step.type}.name`)} ·{' '}
                                        {t('stuck.attempts', {
                                            count: instance.step.attempts,
                                            formatted: formatNumber(instance.step.attempts, locale),
                                        })}
                                    </span>
                                ) : null}
                            </div>
                            {instance.step?.error || instance.reason ? (
                                <pre
                                    dir="ltr"
                                    className="overflow-x-auto rounded-md bg-muted p-3 text-caption whitespace-pre-wrap text-foreground"
                                >
                                    {instance.step?.error ?? instance.reason}
                                </pre>
                            ) : null}
                            <div className="flex flex-wrap gap-2">
                                {instance.step ? (
                                    <>
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                router.post(
                                                    workflows.stuck.retry.url(instance.step!.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('stuck.retry')}
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.post(
                                                    workflows.stuck.skip.url(instance.step!.id),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            {t('stuck.skip')}
                                        </Button>
                                    </>
                                ) : null}
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="text-danger hover:text-danger"
                                    onClick={() => setCancelling(instance)}
                                >
                                    {t('stuck.cancel')}
                                </Button>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <Paginator prevUrl={instances.prev_page_url} nextUrl={instances.next_page_url} />

            {cancelling ? (
                <ResponsiveDialog
                    open
                    onOpenChange={(open) => !open && setCancelling(null)}
                    title={t('stuck.cancelTitle')}
                    description={t('stuck.cancelHelp')}
                >
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.post(
                                workflows.stuck.cancel.url(cancelling.id),
                                { reason },
                                { preserveScroll: true, onSuccess: () => setCancelling(null) },
                            );
                        }}
                    >
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="cancel-reason">{t('stuck.reason')}</Label>
                            <Textarea
                                id="cancel-reason"
                                value={reason}
                                required
                                maxLength={500}
                                onChange={(event) => setReason(event.target.value)}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCancelling(null)}
                            >
                                {t('common:actions.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={reason.trim() === ''}
                            >
                                {t('stuck.cancel')}
                            </Button>
                        </div>
                    </form>
                </ResponsiveDialog>
            ) : null}
        </>
    );
}
