import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRightLeft,
    BadgeCheck,
    CalendarPlus,
    ChevronLeft,
    Copy,
    FilePlus,
    FileText,
    MessageSquare,
    Star,
    UserX,
    X,
} from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { DirIcon } from '@/components/mw/dir-icon';
import { Facts } from '@/components/mw/facts';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SwitchField } from '@/components/mw/switch-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Answers } from '@/features/forms/answers';
import { type FormSchema } from '@/features/forms/schema';
import { ApplicationStatusBadge, REJECTION_REASONS } from '@/features/hiring/status';
import { HireDialog } from '@/features/hiring/hire-dialog';
import { InterviewCard } from '@/features/hiring/interview-card';
import { InterviewDialog } from '@/features/hiring/interview-dialog';
import { MessagesPanel } from '@/features/hiring/messages-panel';
import { OfferCard } from '@/features/hiring/offer-card';
import { OfferDialog } from '@/features/hiring/offer-dialog';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import hiring from '@/routes/hiring';
import people from '@/routes/people';

type Profile = App.Domain.Hiring.Data.CandidateProfileData;
type Application = App.Domain.Hiring.Data.ApplicationDetailData;
type Props = {
    candidate: Profile;
    applications: Application[];
    selected: string | null;
    messages: App.Domain.Hiring.Data.CandidateMessageData[] | null;
    can: { update: boolean; see: boolean; anonymise: boolean };
    tags: string[];
    employmentTypes: Array<{ id: string; name: string }>;
    timezone: string;
};

/**
 * Candidate profile (handoff 30): the CV at the reading start with the details beside it on
 * desktop, tabs on phones. Each application shows its stage, answers and history; recruiters tag,
 * pool, move, reject, merge duplicates and add the candidate to another job.
 */
export default function CandidateProfile({
    candidate,
    applications,
    selected,
    messages,
    can,
    tags,
    employmentTypes,
    timezone,
}: Props) {
    const { t } = useTranslation('hiring');
    const { locale, dir } = useLocale();
    const [current, setCurrent] = useState(
        selected && applications.some((a) => a.id === selected)
            ? selected
            : (applications[0]?.id ?? null),
    );
    const application = applications.find((a) => a.id === current) ?? null;
    const [anonymising, setAnonymising] = useState(false);

    return (
        <>
            <Head title={candidate.name} />
            <Link
                href={
                    application
                        ? hiring.board.url(application.job.id)
                        : hiring.candidates.index.url()
                }
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {application ? application.job.title : t('candidates.title')}
            </Link>
            <PageHeader
                title={candidate.name}
                description={[candidate.location, t(`source.${candidate.source}`)]
                    .filter(Boolean)
                    .join(' · ')}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        {candidate.talentPool ? (
                            <span className="inline-flex items-center gap-1 rounded-full bg-warning-bg px-3 py-1 text-caption">
                                <Star aria-hidden className="size-3.5 fill-current text-warning" />
                                {t('candidates.inPool')}
                            </span>
                        ) : null}
                        {can.anonymise ? (
                            <Button
                                variant="ghost"
                                className="min-h-11 text-danger"
                                onClick={() => setAnonymising(true)}
                                data-anonymise
                            >
                                {t('privacy.anonymise')}
                            </Button>
                        ) : null}
                    </div>
                }
            />
            {anonymising ? (
                <ResponsiveDialog
                    open
                    onOpenChange={(open) => !open && setAnonymising(false)}
                    title={t('privacy.confirmTitle', { name: candidate.name })}
                    description={t('privacy.confirmBody')}
                    footer={
                        <div className="flex justify-end gap-2">
                            <Button
                                variant="ghost"
                                className="min-h-11"
                                onClick={() => setAnonymising(false)}
                            >
                                {t('cancel')}
                            </Button>
                            <Button
                                variant="destructive"
                                className="min-h-11"
                                data-confirm-anonymise
                                onClick={() =>
                                    router.post(hiring.candidates.anonymise.url(candidate.id))
                                }
                            >
                                {t('privacy.anonymise')}
                            </Button>
                        </div>
                    }
                >
                    <span />
                </ResponsiveDialog>
            ) : null}

            {candidate.duplicateOf ? (
                <DuplicateBanner candidate={candidate} canUpdate={can.update} />
            ) : null}

            {applications.length > 1 ? (
                <div
                    role="tablist"
                    aria-label={t('profile.applications')}
                    className="mb-4 flex flex-wrap gap-2"
                >
                    {applications.map((a) => (
                        <button
                            key={a.id}
                            type="button"
                            role="tab"
                            aria-selected={a.id === current}
                            onClick={() => setCurrent(a.id)}
                            className={cn(
                                'inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-small',
                                a.id === current
                                    ? 'border-primary bg-primary/10 text-foreground'
                                    : 'border-border text-text-secondary hover:text-foreground',
                            )}
                        >
                            {a.job.title}
                            <ApplicationStatusBadge status={a.status} />
                        </button>
                    ))}
                </div>
            ) : null}

            <Tabs defaultValue="cv" className="gap-4" dir={dir}>
                <TabsList className="md:hidden">
                    <TabsTrigger value="cv" className="text-foreground">
                        {t('profile.cv')}
                    </TabsTrigger>
                    <TabsTrigger value="details" className="text-foreground">
                        {t('profile.details')}
                    </TabsTrigger>
                    <TabsTrigger value="history" className="text-foreground">
                        {t('profile.history')}
                    </TabsTrigger>
                </TabsList>
                <div className="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)] xl:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                    <TabsContent
                        value="cv"
                        forceMount
                        className="data-[state=inactive]:hidden md:row-span-2 md:data-[state=inactive]:block"
                    >
                        {/* The CV stays beside the details while they scroll (handoff 30). */}
                        <div className="md:sticky md:top-20">
                            <CvPane application={application} name={candidate.name} />
                        </div>
                    </TabsContent>
                    <TabsContent
                        value="details"
                        forceMount
                        className="flex flex-col gap-6 data-[state=inactive]:hidden md:data-[state=inactive]:flex"
                    >
                        {can.see ? (
                            <ContactCard
                                candidate={candidate}
                                canUpdate={can.update}
                                knownTags={tags}
                            />
                        ) : null}
                        {application ? (
                            <>
                                <ApplicationCard application={application} />
                                <InterviewsSection application={application} timezone={timezone} />
                                {application.can.see ? (
                                    <OffersSection
                                        application={application}
                                        candidate={candidate}
                                        employmentTypes={employmentTypes}
                                    />
                                ) : null}
                            </>
                        ) : (
                            <PageState
                                kind="empty"
                                illustration="empty-candidates"
                                title={t('profile.noApplications')}
                            />
                        )}
                        {can.update ? <AddToJob candidateId={candidate.id} /> : null}
                    </TabsContent>
                    <TabsContent
                        value="history"
                        forceMount
                        className="data-[state=inactive]:hidden md:col-start-2 md:data-[state=inactive]:block"
                    >
                        <div className="flex flex-col gap-6">
                            {application?.can.see ? <History application={application} /> : null}
                            {messages !== null ? (
                                <MessagesPanel
                                    candidateId={candidate.id}
                                    applicationId={application?.id ?? null}
                                    messages={messages}
                                    canSend={can.update}
                                    hasEmail={candidate.email !== null}
                                    hasPhone={candidate.phone !== null}
                                />
                            ) : null}
                        </div>
                    </TabsContent>
                </div>
            </Tabs>
            <span className="sr-only">
                {formatDate(candidate.createdAt, locale, { dateStyle: 'medium' })}
            </span>
        </>
    );
}

function CvPane({ application, name }: { application: Application | null; name: string }) {
    const { t } = useTranslation('hiring');
    const cv = application?.cv ?? null;

    if (!cv) {
        return (
            <div className="flex min-h-64 flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-border p-6 text-center text-small text-text-secondary">
                <FileText aria-hidden className="size-8" />
                {t('profile.noCv')}
            </div>
        );
    }

    return (
        <section aria-label={t('profile.cv')} className="flex flex-col gap-2" data-cv>
            <div className="flex items-center justify-between gap-2">
                <span className="truncate text-small font-medium">{cv.name}</span>
                <a
                    href={cv.url}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex min-h-11 items-center text-small text-primary hover:underline"
                >
                    {t('profile.openCv')}
                </a>
            </div>
            {cv.mime === 'application/pdf' ? (
                <iframe
                    src={cv.url}
                    title={t('profile.cvOf', { name })}
                    className="h-[70vh] min-h-96 w-full rounded-lg border border-border bg-card"
                />
            ) : (
                <p className="rounded-lg border border-border bg-card p-4 text-small text-text-secondary">
                    {t('profile.cvDownload')}
                </p>
            )}
        </section>
    );
}

function ContactCard({
    candidate,
    canUpdate,
    knownTags,
}: {
    candidate: Profile;
    canUpdate: boolean;
    knownTags: string[];
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [tagInput, setTagInput] = useState('');
    const saveTags = (next: string[]) =>
        router.put(
            hiring.candidates.tags.url(candidate.id),
            { tags: next },
            { preserveScroll: true },
        );
    const addTag = (event: FormEvent) => {
        event.preventDefault();
        const name = tagInput.trim();

        if (name && !candidate.tags.some((tag) => tag.toLowerCase() === name.toLowerCase())) {
            saveTags([...candidate.tags, name]);
        }

        setTagInput('');
    };

    return (
        <section
            aria-labelledby="contact-title"
            className="flex flex-col gap-4 rounded-lg bg-card p-4 shadow-sm"
        >
            <h2 id="contact-title" className="text-h4">
                {t('profile.contact')}
            </h2>
            <Facts
                items={[
                    [
                        t('profile.email'),
                        candidate.email ? (
                            <bdi dir="ltr" className="break-all">
                                {candidate.email}
                            </bdi>
                        ) : (
                            '—'
                        ),
                    ],
                    [
                        t('profile.phone'),
                        candidate.phone ? <bdi dir="ltr">{candidate.phone}</bdi> : '—',
                    ],
                    [t('profile.language'), t(`profile.lang.${candidate.locale}`)],
                    [
                        t('profile.consent'),
                        candidate.consentedAt
                            ? formatDate(candidate.consentedAt, locale, { dateStyle: 'medium' })
                            : t('profile.noConsent'),
                    ],
                ]}
            />
            <div className="flex flex-col gap-2">
                <span id="tags-label" className="text-small font-medium">
                    {t('profile.tags')}
                </span>
                <ul aria-labelledby="tags-label" className="flex flex-wrap gap-1" data-tags>
                    {candidate.tags.length === 0 ? (
                        <li className="text-caption text-text-secondary">{t('profile.noTags')}</li>
                    ) : null}
                    {candidate.tags.map((tag) => (
                        <li
                            key={tag}
                            className="inline-flex items-center gap-1 rounded-full bg-muted py-0.5 ps-2 pe-1 text-caption"
                        >
                            {tag}
                            {canUpdate ? (
                                <button
                                    type="button"
                                    aria-label={t('profile.removeTag', { tag })}
                                    onClick={() =>
                                        saveTags(candidate.tags.filter((x) => x !== tag))
                                    }
                                    className="inline-flex size-6 items-center justify-center rounded-full hover:bg-card"
                                >
                                    <X aria-hidden className="size-3" />
                                </button>
                            ) : null}
                        </li>
                    ))}
                </ul>
                {canUpdate ? (
                    <form onSubmit={addTag} className="flex gap-2">
                        <Input
                            value={tagInput}
                            onChange={(e) => setTagInput(e.target.value)}
                            maxLength={40}
                            list="known-tags"
                            aria-label={t('profile.addTag')}
                            placeholder={t('profile.addTag')}
                            className="min-h-11"
                            data-tag-input
                        />
                        <datalist id="known-tags">
                            {knownTags.map((tag) => (
                                <option key={tag} value={tag} />
                            ))}
                        </datalist>
                        <Button type="submit" variant="outline" className="min-h-11">
                            {t('profile.add')}
                        </Button>
                    </form>
                ) : null}
            </div>
            {canUpdate ? (
                <SwitchField
                    id="talent-pool"
                    label={t('profile.pool')}
                    hint={
                        candidate.talentPoolConsentedAt
                            ? t('profile.poolConsented')
                            : t('profile.poolNoConsent')
                    }
                    checked={candidate.talentPool}
                    onCheckedChange={(inPool) =>
                        router.put(
                            hiring.candidates.pool.url(candidate.id),
                            { inPool },
                            { preserveScroll: true },
                        )
                    }
                />
            ) : null}
        </section>
    );
}

function ApplicationCard({ application }: { application: Application }) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [reason, setReason] = useState<string>('not_qualified');
    const editable =
        application.can.update && application.job.open && application.status === 'active';

    return (
        <section
            aria-labelledby="application-title"
            className="flex flex-col gap-4 rounded-lg bg-card p-4 shadow-sm"
            data-application={application.id}
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="application-title" className="text-h4">
                    <Link href={hiring.board.url(application.job.id)} className="hover:underline">
                        {application.job.title}
                    </Link>
                </h2>
                <ApplicationStatusBadge status={application.status} />
            </div>
            <Facts
                items={[
                    [
                        t('profile.stage'),
                        application.stages.find((s) => s.id === application.stageId)?.name ?? '—',
                    ],
                    [
                        t('profile.applied'),
                        formatDate(application.appliedAt, locale, { dateStyle: 'medium' }),
                    ],
                    [t('profile.source'), t(`source.${application.source}`)],
                    ...(application.rejectionReason
                        ? ([
                              [
                                  t('profile.rejectedFor'),
                                  t(`rejection.${application.rejectionReason}`),
                              ],
                          ] as Array<[string, string]>)
                        : []),
                ]}
            />
            {application.knockouts.length > 0 ? (
                <p
                    role="note"
                    className="flex items-start gap-2 rounded-md bg-warning-bg p-3 text-small"
                >
                    <AlertTriangle aria-hidden className="mt-0.5 size-4 shrink-0 text-warning" />
                    {t('profile.knockedOut', { questions: application.knockouts.join(', ') })}
                </p>
            ) : null}

            {editable ? (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label className="flex flex-1 flex-col gap-1 text-small">
                        <span id="move-label">{t('board.moveTo')}</span>
                        <Select
                            value={application.stageId}
                            onValueChange={(stage) =>
                                router.post(
                                    hiring.applications.move.url(application.id),
                                    { stage },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <SelectTrigger
                                className="min-h-11"
                                aria-labelledby="move-label"
                                data-move-stage
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {application.stages.map((s) => (
                                    <SelectItem key={s.id} value={s.id}>
                                        {s.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </label>
                    <div className="flex gap-2">
                        <Select value={reason} onValueChange={setReason}>
                            <SelectTrigger
                                className="min-h-11 w-44"
                                aria-label={t('reject.reason')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {REJECTION_REASONS.map((r) => (
                                    <SelectItem key={r} value={r}>
                                        {t(`rejection.${r}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            className="min-h-11 text-danger"
                            data-reject
                            onClick={() =>
                                router.post(
                                    hiring.applications.reject.url(),
                                    { applications: [application.id], reason },
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <UserX aria-hidden />
                            {t('board.reject')}
                        </Button>
                    </div>
                </div>
            ) : application.can.update &&
              application.status === 'rejected' &&
              application.job.open ? (
                <Button
                    variant="outline"
                    className="min-h-11 self-start"
                    onClick={() =>
                        router.post(
                            hiring.applications.reopen.url(application.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    {t('board.reopen')}
                </Button>
            ) : null}

            {application.schema ? (
                <section aria-labelledby="answers-title" className="flex flex-col gap-1">
                    <h3 id="answers-title" className="text-small font-medium">
                        {t('profile.answers')}
                    </h3>
                    <Answers
                        schema={application.schema as unknown as FormSchema}
                        answers={application.answers}
                        display={application.display}
                        fileUrl={(fileId) =>
                            hiring.applications.files.url([application.id, fileId])
                        }
                    />
                </section>
            ) : null}
        </section>
    );
}

function History({ application }: { application: Application }) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [note, setNote] = useState('');

    return (
        <section
            aria-labelledby="history-title"
            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm"
        >
            <h2 id="history-title" className="text-h4">
                {t('profile.history')}
            </h2>
            <form
                className="flex flex-col gap-2"
                onSubmit={(e) => {
                    e.preventDefault();

                    if (!note.trim()) return;

                    router.post(
                        hiring.applications.notes.url(application.id),
                        { body: note },
                        { preserveScroll: true, onSuccess: () => setNote('') },
                    );
                }}
            >
                <Textarea
                    value={note}
                    onChange={(e) => setNote(e.target.value)}
                    rows={2}
                    maxLength={4000}
                    aria-label={t('profile.note')}
                    placeholder={t('profile.notePlaceholder')}
                    data-note-input
                />
                <Button
                    type="submit"
                    variant="outline"
                    className="min-h-11 self-end"
                    disabled={!note.trim()}
                >
                    <MessageSquare aria-hidden />
                    {t('profile.addNote')}
                </Button>
            </form>
            <ol className="flex flex-col gap-3" data-history>
                {application.events.map((event, i) => (
                    <li
                        key={`${event.at}-${i}`}
                        className="flex flex-col gap-0.5 border-s-2 border-border ps-3"
                    >
                        <span className="text-small">
                            {event.kind === 'moved'
                                ? t('event.moved', {
                                      from: event.fromStage ?? '—',
                                      to: event.toStage ?? '—',
                                  })
                                : event.kind === 'rejected'
                                  ? t('event.rejected', {
                                        reason: t(
                                            `rejection.${String(event.data.reason ?? 'other')}`,
                                        ),
                                    })
                                  : event.kind === 'applied' && event.data.again
                                    ? t('event.appliedAgain')
                                    : (event.kind === 'interview' || event.kind === 'offer') &&
                                        typeof event.data.event === 'string'
                                      ? t(`event.${event.kind}_${event.data.event}`, {
                                            title: String(event.data.title ?? ''),
                                        })
                                      : event.kind === 'note' && event.data.anonymised
                                        ? t('event.anonymised')
                                        : t(`event.${event.kind}`)}
                        </span>
                        {event.body ? (
                            <p className="text-small whitespace-pre-line text-text-secondary">
                                {event.body}
                            </p>
                        ) : null}
                        <span className="text-caption text-muted-foreground">
                            {[
                                event.actor,
                                formatDate(event.at, locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                }),
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </span>
                    </li>
                ))}
            </ol>
        </section>
    );
}

function DuplicateBanner({ candidate, canUpdate }: { candidate: Profile; canUpdate: boolean }) {
    const { t } = useTranslation('hiring');
    const other = candidate.duplicateOf!;
    const [error, setError] = useState<string | null>(null);
    const onError = (errors: Record<string, string>) =>
        setError(errors.error ?? Object.values(errors)[0] ?? null);

    return (
        <>
            <div
                role="note"
                className="mb-4 flex flex-col gap-3 rounded-lg border border-info-border bg-info-bg p-4 sm:flex-row sm:items-center"
                data-duplicate
            >
                <Copy aria-hidden className="size-5 shrink-0 text-info" />
                <p className="flex-1 text-small">
                    {t('duplicate.text')}{' '}
                    <Link
                        href={hiring.candidates.show.url(other.id)}
                        className="font-medium underline"
                    >
                        {other.name}
                    </Link>
                </p>
                {canUpdate ? (
                    <div className="flex gap-2">
                        <Button
                            variant="ghost"
                            className="min-h-11"
                            onClick={() =>
                                router.post(
                                    hiring.candidates.notDuplicate.url(candidate.id),
                                    {},
                                    { preserveScroll: true, onError },
                                )
                            }
                        >
                            {t('duplicate.dismiss')}
                        </Button>
                        <Button
                            className="min-h-11"
                            data-merge
                            onClick={() =>
                                router.post(
                                    hiring.candidates.merge.url(candidate.id),
                                    {},
                                    { onError },
                                )
                            }
                        >
                            {t('duplicate.merge', { name: other.name })}
                        </Button>
                    </div>
                ) : null}
            </div>
            {error ? (
                <p
                    role="alert"
                    className="-mt-2 mb-4 rounded-md bg-danger-bg p-3 text-small text-danger"
                >
                    {error}
                </p>
            ) : null}
        </>
    );
}

function AddToJob({ candidateId }: { candidateId: string }) {
    const { t } = useTranslation('hiring');
    const [job, setJob] = useState<PickerOption | null>(null);
    const [error, setError] = useState<string | null>(null);

    return (
        <section
            aria-labelledby="add-job-title"
            className="flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm"
        >
            <h2 id="add-job-title" className="flex items-center gap-2 text-h4">
                <ArrowRightLeft aria-hidden className="size-4" />
                {t('profile.addToJob')}
            </h2>
            <AsyncPicker<{ id: string; number: string; title: string; branch: string | null }>
                endpoint="hiring/requisitions?status=open"
                queryKey="hiring-open-requisitions"
                label={t('profile.job')}
                placeholder={t('profile.pickJob')}
                value={job}
                toOption={(r) => ({
                    id: r.id,
                    label: r.title,
                    description: [r.number, r.branch].filter(Boolean).join(' · '),
                })}
                onChange={setJob}
            />
            {error ? (
                <p role="alert" className="text-caption text-danger">
                    {error}
                </p>
            ) : null}
            <Button
                className="min-h-11 self-end"
                disabled={!job}
                onClick={() =>
                    job &&
                    router.post(
                        hiring.candidates.add.url(candidateId),
                        { requisition: job.id },
                        {
                            preserveScroll: true,
                            onError: (e) => setError(e.requisition ?? e.error ?? null),
                            onSuccess: () => setJob(null),
                        },
                    )
                }
            >
                {t('profile.add')}
            </Button>
        </section>
    );
}

function InterviewsSection({
    application,
    timezone,
}: {
    application: Application;
    timezone: string;
}) {
    const { t } = useTranslation('hiring');
    const [planning, setPlanning] = useState(false);
    const canPlan =
        application.can.update && application.status === 'active' && application.job.open;

    if (application.interviews.length === 0 && !canPlan) {
        return null;
    }

    return (
        <section
            aria-labelledby="interviews-title"
            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm"
            data-interviews
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="interviews-title" className="text-h4">
                    {t('interviews.sectionTitle')}
                </h2>
                {canPlan ? (
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setPlanning(true)}
                        data-plan-interview
                    >
                        <CalendarPlus aria-hidden />
                        {t('interviews.plan')}
                    </Button>
                ) : null}
            </div>
            {application.interviews.length === 0 ? (
                <p className="text-small text-text-secondary">{t('interviews.none')}</p>
            ) : (
                application.interviews.map((interview) => (
                    <InterviewCard
                        key={interview.id}
                        interview={interview}
                        criteria={application.criteria}
                    />
                ))
            )}
            {planning ? (
                <InterviewDialog
                    applicationId={application.id}
                    timezone={timezone}
                    onClose={() => setPlanning(false)}
                />
            ) : null}
        </section>
    );
}

function OffersSection({
    application,
    candidate,
    employmentTypes,
}: {
    application: Application;
    candidate: Profile;
    employmentTypes: Array<{ id: string; name: string }>;
}) {
    const { t } = useTranslation('hiring');
    const [editing, setEditing] = useState<App.Domain.Hiring.Data.OfferData | 'new' | null>(null);
    const [hiringOpen, setHiringOpen] = useState(false);
    const live = application.offers.some((o) =>
        ['draft', 'pending', 'approved', 'sent', 'accepted'].includes(o.status),
    );
    const canDraft =
        application.can.update &&
        application.status === 'active' &&
        !live &&
        application.offerDefaults !== null;

    if (application.offers.length === 0 && !canDraft && !application.employeeId) {
        return null;
    }

    return (
        <section
            aria-labelledby="offers-title"
            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm"
            data-offers
        >
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 id="offers-title" className="text-h4">
                    {t('offers.sectionTitle')}
                </h2>
                {canDraft ? (
                    <Button
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setEditing('new')}
                        data-new-offer
                    >
                        <FilePlus aria-hidden />
                        {t('offers.new')}
                    </Button>
                ) : null}
            </div>
            {application.employeeId ? (
                <p className="flex items-center gap-2 rounded-md bg-success-bg p-3 text-small">
                    <BadgeCheck aria-hidden className="size-4 text-success" />
                    {t('hire.done')}
                    <Link
                        href={people.show.url(application.employeeId)}
                        className="font-medium underline"
                    >
                        {t('hire.openEmployee')}
                    </Link>
                </p>
            ) : null}
            {application.hire ? (
                <div className="flex flex-wrap items-center justify-between gap-2 rounded-md border border-success-border bg-success-bg p-3">
                    <span className="text-small">{t('hire.ready')}</span>
                    <Button className="min-h-11" onClick={() => setHiringOpen(true)} data-hire>
                        {t('hire.button')}
                    </Button>
                </div>
            ) : null}
            {application.offers.map((offer) => (
                <OfferCard
                    key={offer.id}
                    offer={offer}
                    canUpdate={application.can.update}
                    onEdit={() => setEditing(offer)}
                    hasEmail={candidate.email !== null}
                    hasPhone={candidate.phone !== null}
                />
            ))}
            {editing && application.offerDefaults ? (
                <OfferDialog
                    applicationId={application.id}
                    offer={editing === 'new' ? null : editing}
                    defaults={application.offerDefaults}
                    employmentTypes={employmentTypes}
                    candidateLocale={candidate.locale}
                    onClose={() => setEditing(null)}
                />
            ) : null}
            {hiringOpen && application.hire ? (
                <HireDialog
                    applicationId={application.id}
                    hire={application.hire}
                    employmentTypes={employmentTypes}
                    onClose={() => setHiringOpen(false)}
                />
            ) : null}
        </section>
    );
}
