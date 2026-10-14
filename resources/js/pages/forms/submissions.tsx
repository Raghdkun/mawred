import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Answers } from '@/features/forms/answers';
import { type FormSchema } from '@/features/forms/schema';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import forms from '@/routes/forms';
import { type Paginated } from '@/types/pagination';

type Submission = App.Domain.Forms.Data.SubmissionData;

type Props = {
    form: App.Domain.Forms.Data.FormData;
    submissions: Paginated<Submission>;
    filters: { source: string | null };
};

type Detail = { submission: Submission; schema: FormSchema; display: Record<string, string> };

/** Submissions of a form, newest first. Opening one shows its answers (an audited read). */
export default function FormSubmissions({ form, submissions, filters }: Props) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const [open, setOpen] = useState<Submission | null>(null);

    return (
        <>
            <Head title={t('submissions.pageTitle', { name: localized(form.name, locale) })} />
            <Link
                href={forms.builder.url(form.id)}
                className="mb-4 inline-flex items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('preview.back')}
            </Link>
            <PageHeader title={t('submissions.title')} description={localized(form.name, locale)} />

            <ToggleGroup
                type="single"
                variant="outline"
                value={filters.source ?? 'all'}
                onValueChange={(source) =>
                    source &&
                    router.get(
                        forms.submissions.index.url(form.id),
                        source === 'all' ? {} : { source },
                        { preserveState: true, replace: true },
                    )
                }
                aria-label={t('submissions.source')}
                className="mb-4 flex-wrap justify-start"
            >
                {['all', 'internal', 'public', 'test'].map((source) => (
                    <ToggleGroupItem key={source} value={source} className="px-3">
                        {t(`submissions.sources.${source}`)}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>

            {submissions.data.length === 0 ? (
                <PageState kind="empty" illustration="empty-inbox" title={t('submissions.empty')} />
            ) : (
                <ul className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card">
                    {submissions.data.map((submission) => (
                        <li
                            key={submission.id}
                            className="md:border-b md:border-border md:last:border-b-0"
                        >
                            <button
                                type="button"
                                onClick={() => setOpen(submission)}
                                className="flex w-full flex-col gap-1 rounded-lg bg-card p-4 text-start shadow-sm hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none md:flex-row md:items-center md:gap-4 md:rounded-none md:px-4 md:py-3 md:shadow-none"
                            >
                                <span className="flex-1 text-sm font-medium">
                                    {submission.submittedBy ?? t('submissions.anonymous')}
                                </span>
                                <span className="text-small text-text-secondary md:w-48">
                                    {formatDate(submission.createdAt, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </span>
                                <span className="text-small text-text-secondary tabular-nums md:w-20">
                                    {t('submissions.version', {
                                        version: formatNumber(submission.version, locale),
                                    })}
                                </span>
                                <span className="md:w-24">
                                    <Badge variant="outline">
                                        {t(`submissions.sources.${submission.source}`)}
                                    </Badge>
                                </span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <Paginator prevUrl={submissions.prev_page_url} nextUrl={submissions.next_page_url} />

            {open ? (
                <SubmissionDialog
                    formId={form.id}
                    submission={open}
                    onClose={() => setOpen(null)}
                />
            ) : null}
        </>
    );
}

function SubmissionDialog({
    formId,
    submission,
    onClose,
}: {
    formId: string;
    submission: Submission;
    onClose: () => void;
}) {
    const { t } = useTranslation('forms');
    const { locale } = useLocale();
    const [detail, setDetail] = useState<Detail | null>(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();

        fetch(forms.submissions.show.url([formId, submission.id]), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) =>
                response.ok
                    ? (response.json() as Promise<Detail>)
                    : Promise.reject(new Error(response.statusText)),
            )
            .then(setDetail)
            .catch((error: unknown) => {
                if (!(error instanceof DOMException && error.name === 'AbortError'))
                    setFailed(true);
            });

        return () => controller.abort();
    }, [formId, submission.id]);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(next) => !next && onClose()}
            title={submission.submittedBy ?? t('submissions.anonymous')}
            description={formatDate(submission.createdAt, locale, {
                dateStyle: 'long',
                timeStyle: 'short',
            })}
            className="sm:max-w-2xl"
        >
            <div aria-live="polite" aria-busy={!detail && !failed}>
                {failed ? (
                    <PageState kind="error" className="py-6" />
                ) : !detail ? (
                    <div className="flex flex-col gap-3">
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                ) : (
                    <Answers
                        schema={detail.schema}
                        answers={detail.submission.answers ?? {}}
                        display={detail.display}
                        fileUrl={(fileId) =>
                            forms.submissions.files.url([formId, submission.id, fileId])
                        }
                    />
                )}
            </div>
        </ResponsiveDialog>
    );
}
