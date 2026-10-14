import { Head, Link, router, usePoll } from '@inertiajs/react';
import { ChevronLeft, Forward, Plane } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { Facts } from '@/components/mw/facts';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Answers } from '@/features/forms/answers';
import { RequestStatusBadge } from '@/features/requests/request-status';
import { SlaBadge } from '@/features/requests/sla-badge';
import { Timeline } from '@/features/requests/timeline';
import { RequestTypeIcon } from '@/features/requests/type-icon';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import approvals from '@/routes/approvals';
import delegations from '@/routes/delegations';
import requests from '@/routes/requests';
import { type RequestDetail } from '../requests/show';

type Task = App.Domain.Requests.Data.TaskData;

type Props = { tasks: Task[]; selected: Task | null; detail: RequestDetail | null };

/**
 * Approvals inbox (handoff 13 ★): a 400 px list beside the selected approval on desktop; list
 * then detail on tablets and phones, where cards swipe toward the reading end to approve.
 */
export default function ApprovalsInbox({ tasks, selected, detail }: Props) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const { currentUrl } = useCurrentUrl();
    const explicit = new URL(currentUrl, 'http://x').searchParams.has('task');
    const [checked, setChecked] = useState<string[]>([]);
    const approvable = tasks.filter((task) => task.kind === 'approval');
    const allChecked = approvable.length > 0 && checked.length === approvable.length;

    // New tasks arrive without a reload; the selected detail is left alone.
    usePoll(30000, { only: ['tasks'] });

    const bulkApprove = () =>
        router.post(
            approvals.bulk.url(),
            { tasks: checked },
            { preserveScroll: true, onSuccess: () => setChecked([]) },
        );

    return (
        <>
            <Head title={t('inbox.pageTitle')} />
            <PageHeader
                title={t('inbox.title')}
                description={
                    tasks.length > 0
                        ? t('inbox.count', {
                              count: tasks.length,
                              formatted: formatNumber(tasks.length, locale),
                          })
                        : undefined
                }
                className={cn(explicit && 'hidden lg:flex')}
                actions={
                    <Button variant="ghost" asChild>
                        <Link href={delegations.index.url()}>
                            <Plane aria-hidden className="rtl:-scale-x-100" />
                            {t('away.link')}
                        </Link>
                    </Button>
                }
            />

            {tasks.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-inbox"
                    title={t('inbox.empty')}
                    description={t('inbox.emptyHelp')}
                />
            ) : (
                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    <section
                        aria-label={t('inbox.list')}
                        className={cn(
                            'flex w-full flex-col gap-3 lg:w-[400px] lg:shrink-0',
                            explicit && 'hidden lg:flex',
                        )}
                    >
                        {approvable.length > 1 ? (
                            <div className="flex min-h-11 items-center gap-3 rounded-lg bg-card px-4 py-2 shadow-sm">
                                <Checkbox
                                    id="select-all"
                                    checked={
                                        allChecked
                                            ? true
                                            : checked.length > 0
                                              ? 'indeterminate'
                                              : false
                                    }
                                    onCheckedChange={(value) =>
                                        setChecked(
                                            value === true ? approvable.map((task) => task.id) : [],
                                        )
                                    }
                                />
                                <Label htmlFor="select-all" className="flex-1 text-small">
                                    {checked.length > 0
                                        ? t('inbox.selected', {
                                              count: checked.length,
                                              formatted: formatNumber(checked.length, locale),
                                          })
                                        : t('inbox.selectAll')}
                                </Label>
                                {checked.length > 0 ? (
                                    <Button size="sm" onClick={bulkApprove}>
                                        {t('inbox.approveSelected', {
                                            count: checked.length,
                                            formatted: formatNumber(checked.length, locale),
                                        })}
                                    </Button>
                                ) : null}
                            </div>
                        ) : null}
                        <ul className="flex flex-col gap-2">
                            {tasks.map((task) => (
                                <TaskCard
                                    key={task.id}
                                    task={task}
                                    active={selected?.id === task.id}
                                    checked={checked.includes(task.id)}
                                    onCheck={(value) =>
                                        setChecked((current) =>
                                            value
                                                ? [...current, task.id]
                                                : current.filter((id) => id !== task.id),
                                        )
                                    }
                                />
                            ))}
                        </ul>
                    </section>

                    {selected ? (
                        <section
                            aria-label={t('inbox.detail')}
                            className={cn('min-w-0 flex-1', !explicit && 'hidden lg:block')}
                        >
                            <Link
                                href={approvals.index.url()}
                                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground lg:hidden"
                            >
                                <DirIcon>
                                    <ChevronLeft aria-hidden className="size-4" />
                                </DirIcon>
                                {t('inbox.back')}
                            </Link>
                            <ApprovalCard key={selected.id} task={selected} detail={detail} />
                        </section>
                    ) : null}
                </div>
            )}
        </>
    );
}

const SWIPE_THRESHOLD = 120;

function TaskCard({
    task,
    active,
    checked,
    onCheck,
}: {
    task: Task;
    active: boolean;
    checked: boolean;
    onCheck: (value: boolean) => void;
}) {
    const { t } = useTranslation('requests');
    const { locale, dir } = useLocale();
    const reduced = useReducedMotion() ?? false;
    const [leaving, setLeaving] = useState(false);
    const href = approvals.index.url({ query: { task: task.id } });
    const approveDirection = dir === 'rtl' ? -1 : 1;

    return (
        <li className="relative overflow-hidden rounded-lg">
            {task.kind === 'approval' ? (
                <span
                    aria-hidden
                    className="absolute inset-0 flex items-center justify-start rounded-lg bg-status-approved-bg ps-5 text-small font-medium text-status-approved-fg md:hidden"
                >
                    {t('inbox.swipeApprove')}
                </span>
            ) : null}
            <motion.div
                drag={task.kind === 'approval' && !reduced ? 'x' : false}
                dragDirectionLock
                dragSnapToOrigin
                dragConstraints={
                    approveDirection === 1 ? { left: 0, right: 200 } : { left: -200, right: 0 }
                }
                dragElastic={0.1}
                animate={leaving ? { x: approveDirection * 400, opacity: 0 } : undefined}
                onDragEnd={(_, info) => {
                    if (info.offset.x * approveDirection > SWIPE_THRESHOLD) {
                        setLeaving(true);
                        router.post(
                            approvals.decide.url(task.id),
                            { decision: 'approved' },
                            { preserveScroll: true, onError: () => setLeaving(false) },
                        );
                    }
                }}
                className={cn(
                    'relative flex items-start gap-3 rounded-lg border bg-card p-4 shadow-sm transition-colors duration-150 ease-soft',
                    active
                        ? 'border-primary ring-1 ring-primary'
                        : 'border-transparent hover:bg-muted/60',
                )}
            >
                {task.kind === 'approval' ? (
                    <Checkbox
                        checked={checked}
                        onCheckedChange={(value) => onCheck(value === true)}
                        aria-label={t('inbox.select', { number: task.request?.number ?? '' })}
                        className="relative z-10 mt-1"
                    />
                ) : null}
                <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                    <RequestTypeIcon icon={task.request?.typeIcon ?? null} className="size-4" />
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-1">
                    <Link
                        href={href}
                        preserveScroll
                        aria-current={active ? 'true' : undefined}
                        className="truncate text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                    >
                        {task.request
                            ? localized(task.request.typeName, locale)
                            : localized(task.step, locale)}
                    </Link>
                    <span className="truncate text-caption text-muted-foreground">
                        {task.request?.requester}
                        {task.request ? (
                            <>
                                {' · '}
                                <bdi dir="ltr">{task.request.number}</bdi>
                            </>
                        ) : null}
                    </span>
                    <span className="flex flex-wrap items-center gap-2">
                        <SlaBadge dueAt={task.dueAt} overdue={task.overdue} />
                        {task.delegatedFrom ? (
                            <span className="inline-flex items-center gap-1 text-caption text-muted-foreground">
                                <Forward aria-hidden className="size-3 rtl:-scale-x-100" />
                                {t('inbox.delegatedFrom', { name: task.delegatedFrom })}
                            </span>
                        ) : null}
                    </span>
                </span>
            </motion.div>
        </li>
    );
}

function ApprovalCard({ task, detail }: { task: Task; detail: RequestDetail | null }) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [comment, setComment] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState<string | null>(null);
    const [delegating, setDelegating] = useState(false);

    const decide = (decision: string) => {
        if ((decision === 'rejected' || decision === 'returned') && comment.trim() === '') {
            setError(t('inbox.commentNeeded'));
            document.getElementById('decision-comment')?.focus();

            return;
        }

        setSending(decision);
        router.post(
            approvals.decide.url(task.id),
            { decision, comment: comment || null },
            {
                preserveScroll: true,
                onError: (errors) =>
                    setError(errors.comment ?? errors.error ?? errors.decision ?? null),
                onFinish: () => setSending(null),
            },
        );
    };

    return (
        <div className="flex flex-col gap-6">
            <article className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
                <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-1">
                        <h2 className="text-h3">
                            {task.request
                                ? localized(task.request.typeName, locale)
                                : localized(task.step, locale)}
                        </h2>
                        {task.request ? (
                            <p className="text-small text-text-secondary">
                                {t('inbox.fromLabel')} <bdi>{task.request.requester}</bdi> ·{' '}
                                <bdi>
                                    {formatDate(task.request.submittedAt, locale, {
                                        dateStyle: 'medium',
                                    })}
                                </bdi>
                            </p>
                        ) : null}
                    </div>
                    <div className="flex items-center gap-2">
                        <SlaBadge dueAt={task.dueAt} overdue={task.overdue} />
                        {task.request ? <RequestStatusBadge status={task.request.status} /> : null}
                    </div>
                </header>

                {task.request ? (
                    <Facts
                        className="mb-4"
                        items={[
                            [
                                t('show.number'),
                                <bdi key="n" dir="ltr">
                                    {task.request.number}
                                </bdi>,
                            ],
                            [t('inbox.step'), localized(task.step, locale) || '—'],
                        ]}
                    />
                ) : null}

                {detail ? (
                    <Answers
                        schema={detail.schema}
                        answers={detail.answers}
                        display={detail.display}
                        fileUrl={(fileId) => requests.files.url([detail.request.id, fileId])}
                    />
                ) : null}

                <div className="mt-6 flex flex-col gap-2 border-t border-border pt-5">
                    <Label htmlFor="decision-comment">{t('inbox.comment')}</Label>
                    <Textarea
                        id="decision-comment"
                        value={comment}
                        maxLength={2000}
                        placeholder={t('inbox.commentPlaceholder')}
                        aria-invalid={error !== null}
                        aria-describedby={error ? 'decision-error' : undefined}
                        onChange={(event) => {
                            setComment(event.target.value);
                            setError(null);
                        }}
                    />
                    {error ? (
                        <p id="decision-error" role="alert" className="text-caption text-danger">
                            {error}
                        </p>
                    ) : null}
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        {task.decisions.map((decision) => (
                            <Button
                                key={decision}
                                data-decision={decision}
                                variant={
                                    decision === 'approved' || decision === 'done'
                                        ? 'default'
                                        : 'outline'
                                }
                                className={cn(
                                    decision === 'rejected' && 'text-danger hover:text-danger',
                                )}
                                disabled={sending !== null}
                                onClick={() => decide(decision)}
                            >
                                {t(`inbox.decisions.${decision}`)}
                            </Button>
                        ))}
                        <Button
                            variant="ghost"
                            className="ms-auto"
                            onClick={() => setDelegating(true)}
                        >
                            <Forward aria-hidden className="rtl:-scale-x-100" />
                            {t('inbox.delegate')}
                        </Button>
                    </div>
                </div>
            </article>

            {detail ? (
                <section
                    aria-labelledby="inbox-timeline"
                    className="rounded-lg bg-card p-5 shadow-sm sm:p-6"
                >
                    <h2 id="inbox-timeline" className="mb-4 text-h4">
                        {t('show.timeline')}
                    </h2>
                    <Timeline entries={detail.timeline} />
                </section>
            ) : null}

            {delegating ? (
                <DelegateDialog task={task} onClose={() => setDelegating(false)} />
            ) : null}
        </div>
    );
}

function DelegateDialog({ task, onClose }: { task: Task; onClose: () => void }) {
    const { t } = useTranslation('requests');
    const [to, setTo] = useState<PickerOption | null>(null);
    const [comment, setComment] = useState('');
    const [error, setError] = useState<string | null>(null);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('delegate.title')}
            description={t('delegate.help')}
        >
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (!to) {
                        setError(t('delegate.pick'));

                        return;
                    }

                    router.post(
                        approvals.delegate.url(task.id),
                        { to: to.id, comment: comment || null },
                        {
                            onSuccess: onClose,
                            onError: (errors) => setError(errors.to ?? errors.error ?? null),
                        },
                    );
                }}
            >
                <div className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium">{t('delegate.to')}</span>
                    <AsyncPicker<{ id: string; name: string }>
                        endpoint="delegates"
                        queryKey="delegates"
                        toOption={(user) => ({ id: user.id, label: user.name })}
                        value={to}
                        onChange={(option) => {
                            setTo(option);
                            setError(null);
                        }}
                        placeholder={t('delegate.search')}
                        label={t('delegate.to')}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="delegate-comment">{t('delegate.note')}</Label>
                    <Textarea
                        id="delegate-comment"
                        value={comment}
                        maxLength={500}
                        onChange={(event) => setComment(event.target.value)}
                    />
                </div>
                {error ? (
                    <p role="alert" className="text-caption text-danger">
                        {error}
                    </p>
                ) : null}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        {t('common:actions.cancel')}
                    </Button>
                    <Button type="submit">{t('delegate.submit')}</Button>
                </div>
            </form>
        </ResponsiveDialog>
    );
}
