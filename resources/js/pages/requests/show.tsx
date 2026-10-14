import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, CornerUpLeft, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { Facts } from '@/components/mw/facts';
import { PageHeader } from '@/components/mw/page-header';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Answers } from '@/features/forms/answers';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { RequestStatusBadge } from '@/features/requests/request-status';
import { Timeline } from '@/features/requests/timeline';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import requests from '@/routes/requests';

type Task = App.Domain.Requests.Data.TaskData;

export type RequestDetail = {
    request: App.Domain.Requests.Data.RequestRowData;
    sensitive: boolean;
    schema: FormSchema;
    answers: Record<string, unknown>;
    display: Record<string, string>;
    timeline: App.Domain.Requests.Data.TimelineEntryData[];
    comments: Array<{ id: string; author: string; body: string; at: string }>;
    tasks: Task[];
    can: { withdraw: boolean; resubmit: boolean; comment: boolean };
};

type Props = RequestDetail & { context: FormRuleContext | null };

/**
 * Request detail (handoff 12): answers, the live workflow timeline, comments; the requester can
 * withdraw an open request or correct and resend a returned one.
 */
export default function ShowRequest(props: Props) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const { flash } = usePage();
    const { request, can, context } = props;
    const [withdrawing, setWithdrawing] = useState(false);
    const [reason, setReason] = useState('');
    const [correcting, setCorrecting] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const submitted = (flash as { submitted?: boolean } | undefined)?.submitted === true;
    const returned = [...props.timeline].reverse().find((entry) => entry.event === 'task.returned');
    const typeName = localized(request.typeName, locale);

    return (
        <>
            <Head title={`${typeName} · ${request.number}`} />
            <Link
                href={requests.index.url()}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('show.back')}
            </Link>

            {submitted ? (
                <section
                    aria-live="polite"
                    className="mb-6 flex flex-col items-center gap-3 rounded-lg bg-card p-6 text-center shadow-sm"
                >
                    <SuccessRipple label={t('show.submittedTitle')} />
                    <h2 className="text-h3">{t('show.submittedTitle')}</h2>
                    <p className="max-w-md text-small text-text-secondary">
                        {t('show.submittedBody', { number: request.number })}
                    </p>
                </section>
            ) : null}

            <PageHeader
                title={typeName}
                description={t('show.subtitle', {
                    number: request.number,
                    date: formatDate(request.submittedAt, locale, { dateStyle: 'medium' }),
                })}
                actions={
                    <>
                        <RequestStatusBadge status={request.status} />
                        {can.withdraw ? (
                            <Button variant="outline" onClick={() => setWithdrawing(true)}>
                                {t('show.withdraw')}
                            </Button>
                        ) : null}
                    </>
                }
            />

            {request.status === 'returned' ? (
                <section className="mb-6 flex flex-col gap-3 rounded-lg border border-status-returned-border bg-status-returned-bg p-4 text-status-returned-fg">
                    <p className="flex items-center gap-2 text-sm font-medium">
                        <CornerUpLeft aria-hidden className="size-4 rtl:-scale-x-100" />
                        {t('show.returnedTitle', { name: returned?.actor ?? t('timeline.system') })}
                    </p>
                    {returned?.comment ? (
                        <p className="text-small whitespace-pre-line text-foreground">
                            <bdi>{returned.comment}</bdi>
                        </p>
                    ) : null}
                    {can.resubmit && !correcting ? (
                        <Button className="self-start" onClick={() => setCorrecting(true)}>
                            {t('show.correct')}
                        </Button>
                    ) : null}
                </section>
            ) : null}

            <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                <div className="flex min-w-0 flex-1 flex-col gap-6">
                    {correcting && context ? (
                        <section
                            aria-labelledby="correct-title"
                            className="rounded-lg bg-card p-5 shadow-sm sm:p-6"
                        >
                            <h2 id="correct-title" className="mb-4 text-h4">
                                {t('show.correctTitle')}
                            </h2>
                            {errors.error ? (
                                <p
                                    role="alert"
                                    className="mb-4 rounded-md bg-danger-bg p-3 text-small text-danger"
                                >
                                    {errors.error}
                                </p>
                            ) : null}
                            <FormRenderer
                                schema={props.schema}
                                context={context}
                                initialValues={props.answers}
                                uploadUrl={requests.uploads.url(request.typeId)}
                                serverErrors={errors}
                                submitting={sending}
                                submitLabel={t('show.resend')}
                                onSubmit={(values) => {
                                    setSending(true);
                                    router.post(
                                        requests.resubmit.url(request.id),
                                        asPayload({ values }),
                                        {
                                            preserveScroll: true,
                                            onError: setErrors,
                                            onSuccess: () => setCorrecting(false),
                                            onFinish: () => setSending(false),
                                        },
                                    );
                                }}
                            />
                        </section>
                    ) : (
                        <section
                            aria-labelledby="answers-title"
                            className="rounded-lg bg-card p-5 shadow-sm sm:p-6"
                        >
                            <div className="mb-3 flex items-center justify-between gap-2">
                                <h2 id="answers-title" className="text-h4">
                                    {t('show.answers')}
                                </h2>
                                {props.sensitive ? (
                                    <span className="inline-flex items-center gap-1.5 text-caption text-muted-foreground">
                                        <ShieldCheck aria-hidden className="size-3.5" />
                                        {t('show.sensitive')}
                                    </span>
                                ) : null}
                            </div>
                            <Facts
                                className="mb-4"
                                items={[
                                    [t('show.requester'), request.requester],
                                    [
                                        t('show.number'),
                                        <bdi key="n" dir="ltr">
                                            {request.number}
                                        </bdi>,
                                    ],
                                ]}
                            />
                            <Answers
                                schema={props.schema}
                                answers={props.answers}
                                display={props.display}
                                fileUrl={(fileId) => requests.files.url([request.id, fileId])}
                            />
                        </section>
                    )}

                    <Comments
                        comments={props.comments}
                        canComment={can.comment}
                        requestId={request.id}
                    />
                </div>

                <aside
                    aria-labelledby="timeline-title"
                    className="w-full rounded-lg bg-card p-5 shadow-sm sm:p-6 lg:sticky lg:top-24 lg:w-96"
                >
                    <h2 id="timeline-title" className="mb-4 text-h4">
                        {t('show.timeline')}
                    </h2>
                    {props.tasks.length > 0 ? (
                        <p className="mb-4 rounded-md bg-status-in-review-bg px-3 py-2 text-small text-status-in-review-fg">
                            {t('show.waitingOn', {
                                step: props.tasks
                                    .map((task) => localized(task.step, locale) || t('show.aStep'))
                                    .join('، '),
                            })}
                        </p>
                    ) : null}
                    <Timeline entries={props.timeline} />
                </aside>
            </div>

            <AlertDialog open={withdrawing} onOpenChange={setWithdrawing}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('show.withdrawTitle')}</AlertDialogTitle>
                        <AlertDialogDescription>{t('show.withdrawHelp')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="withdraw-reason">{t('show.withdrawReason')}</Label>
                        <Textarea
                            id="withdraw-reason"
                            value={reason}
                            maxLength={500}
                            onChange={(event) => setReason(event.target.value)}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel>{t('common:actions.cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive-hover"
                            onClick={() =>
                                router.post(
                                    requests.withdraw.url(request.id),
                                    { reason: reason || null },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            {t('show.withdraw')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

function Comments({
    comments,
    canComment,
    requestId,
}: {
    comments: RequestDetail['comments'];
    canComment: boolean;
    requestId: string;
}) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [body, setBody] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);

    return (
        <section
            aria-labelledby="comments-title"
            className="rounded-lg bg-card p-5 shadow-sm sm:p-6"
        >
            <h2 id="comments-title" className="mb-4 text-h4">
                {t('show.comments')}
            </h2>
            {comments.length === 0 ? (
                <p className="mb-4 text-small text-muted-foreground">{t('show.noComments')}</p>
            ) : (
                <ul className="mb-4 flex flex-col gap-4">
                    {comments.map((comment) => (
                        <li key={comment.id} className="flex flex-col gap-1">
                            <p className="text-small">
                                <span className="font-medium text-foreground">
                                    {comment.author}
                                </span>
                                <span className="text-muted-foreground"> · </span>
                                <time dateTime={comment.at} className="text-muted-foreground">
                                    {formatDate(comment.at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </time>
                            </p>
                            <p className="text-sm whitespace-pre-line text-foreground">
                                <bdi>{comment.body}</bdi>
                            </p>
                        </li>
                    ))}
                </ul>
            )}
            {canComment ? (
                <form
                    className="flex flex-col gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        setSending(true);
                        router.post(
                            requests.comments.url(requestId),
                            { body },
                            {
                                preserveScroll: true,
                                onSuccess: () => {
                                    setBody('');
                                    setError(null);
                                },
                                onError: (errors) => setError(errors.body ?? errors.error ?? null),
                                onFinish: () => setSending(false),
                            },
                        );
                    }}
                >
                    <Label htmlFor="comment-body" className="sr-only">
                        {t('show.addComment')}
                    </Label>
                    <Textarea
                        id="comment-body"
                        value={body}
                        maxLength={2000}
                        placeholder={t('show.commentPlaceholder')}
                        aria-invalid={error !== null}
                        aria-describedby={error ? 'comment-error' : undefined}
                        onChange={(event) => setBody(event.target.value)}
                    />
                    {error ? (
                        <p id="comment-error" role="alert" className="text-caption text-danger">
                            {error}
                        </p>
                    ) : null}
                    <Button
                        type="submit"
                        variant="outline"
                        className="self-end"
                        disabled={sending || body.trim() === ''}
                    >
                        {t('show.addComment')}
                    </Button>
                </form>
            ) : null}
        </section>
    );
}
