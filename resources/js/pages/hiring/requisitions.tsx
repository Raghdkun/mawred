import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Kanban, Plus, Trash2, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { Facts } from '@/components/mw/facts';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Paginator } from '@/components/mw/paginator';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { SearchBox } from '@/components/mw/search-box';
import { UnderlineTabs } from '@/components/mw/underline-tabs';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMinor, PostingStatusBadge, RequisitionStatusBadge } from '@/features/hiring/status';
import { Timeline } from '@/features/requests/timeline';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import hiring from '@/routes/hiring';
import { type Paginated } from '@/types/pagination';

type Row = App.Domain.Hiring.Data.RequisitionData;
type Member = { user: string; name: string; role: string };
type Detail = {
    requisition: Row;
    timeline: App.Domain.Requests.Data.TimelineEntryData[];
    members: Member[];
    postings: Array<{ id: string; title: string; status: string; url: string; applicants: number }>;
    next: string[];
    can: { update: boolean };
};
type Props = {
    requisitions: Paginated<Row>;
    filters: { status: string; search: string };
    detail: Detail | null;
    can: { create: boolean };
};

const GROUPS = ['open', 'pending', 'closed', 'all'] as const;

/**
 * Job requisitions (handoff 28, D84): approval status badges, a filter bar, and a drawer with
 * the approval timeline, the hiring team and the postings. Cards on phones.
 */
export default function Requisitions({ requisitions, filters, detail, can }: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [search, setSearch] = useState(filters.search);
    const visit = (query: Record<string, string | undefined>) =>
        router.get(
            hiring.requisitions.index.url(),
            Object.fromEntries(
                Object.entries({
                    status: filters.status,
                    search: search || undefined,
                    ...query,
                }).filter(([, v]) => v),
            ),
            { preserveState: true, preserveScroll: true, replace: true },
        );
    const openDetail = (id: string | undefined) => visit({ open: id });

    return (
        <>
            <Head title={t('requisitions.title')} />
            <PageHeader
                title={t('requisitions.title')}
                description={t('requisitions.description')}
                actions={
                    can.create ? (
                        <Button asChild>
                            <Link href={hiring.requisitions.create.url()} data-new-requisition>
                                <Plus aria-hidden />
                                {t('requisitions.new')}
                            </Link>
                        </Button>
                    ) : null
                }
            />

            <UnderlineTabs
                label={t('requisitions.tabs')}
                items={GROUPS.map((group) => ({
                    key: group,
                    label: t(`requisitions.group.${group}`),
                    href: hiring.requisitions.index.url({ query: { status: group } }),
                    active: filters.status === group,
                }))}
            />

            <div className="mb-4">
                <SearchBox
                    value={search}
                    onChange={setSearch}
                    onSubmit={() => visit({})}
                    label={t('requisitions.search')}
                    className="sm:max-w-xs"
                />
            </div>

            {requisitions.data.length === 0 ? (
                filters.search ? (
                    <PageState kind="no-results" title={t('requisitions.noResults')} />
                ) : (
                    <PageState
                        kind="empty"
                        illustration="empty-candidates"
                        title={t('requisitions.empty')}
                        description={t('requisitions.emptyHelp')}
                    />
                )
            ) : (
                <ul
                    className="flex flex-col gap-2 md:gap-0 md:overflow-hidden md:rounded-lg md:border md:border-border md:bg-card"
                    data-requisitions
                >
                    <li
                        aria-hidden
                        className="hidden border-b border-border bg-muted/60 px-4 py-2 text-caption font-medium text-muted-foreground md:flex md:gap-4"
                    >
                        <span className="flex-1">{t('requisitions.col.job')}</span>
                        <span className="w-40">{t('requisitions.col.where')}</span>
                        <span className="w-28 text-end">{t('requisitions.col.candidates')}</span>
                        <span className="w-40">{t('requisitions.col.status')}</span>
                    </li>
                    {requisitions.data.map((row) => (
                        <li
                            key={row.id}
                            className="relative flex flex-col gap-2 rounded-lg bg-card p-4 shadow-sm transition-colors duration-150 ease-soft focus-within:bg-muted/60 hover:bg-muted/60 md:flex-row md:items-center md:gap-4 md:rounded-none md:border-b md:border-border md:px-4 md:py-3 md:shadow-none md:last:border-b-0"
                            data-requisition={row.id}
                        >
                            <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                <button
                                    type="button"
                                    onClick={() => openDetail(row.id)}
                                    className="truncate text-start text-sm font-medium after:absolute after:inset-0 focus-visible:outline-none"
                                >
                                    {row.title}
                                </button>
                                <span className="text-caption text-muted-foreground">
                                    <bdi dir="ltr">{row.number}</bdi> ·{' '}
                                    {t('requisitions.headcount', {
                                        count: row.headcount,
                                        formatted: formatNumber(row.headcount, locale),
                                    })}
                                </span>
                            </span>
                            <span className="text-small text-text-secondary md:w-40 md:truncate">
                                {[row.branch, row.department].filter(Boolean).join(' · ')}
                            </span>
                            <span className="text-small text-text-secondary md:w-28 md:text-end">
                                {t('requisitions.activeCandidates', {
                                    count: row.counts.active,
                                    formatted: formatNumber(row.counts.active, locale),
                                })}
                            </span>
                            <span className="md:w-40">
                                <RequisitionStatusBadge status={row.status} />
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            <Paginator
                prevUrl={requisitions.prev_page_url}
                nextUrl={requisitions.next_page_url}
                summary={
                    requisitions.total > 0
                        ? `${formatNumber(requisitions.from ?? 0, locale)}–${formatNumber(requisitions.to ?? 0, locale)} / ${formatNumber(requisitions.total, locale)}`
                        : undefined
                }
            />

            {detail ? (
                <RequisitionDrawer
                    key={detail.requisition.id}
                    detail={detail}
                    onClose={() => openDetail(undefined)}
                />
            ) : null}
        </>
    );
}

function RequisitionDrawer({ detail, onClose }: { detail: Detail; onClose: () => void }) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const r = detail.requisition;
    const salary =
        r.currency && (r.salaryMinMinor !== null || r.salaryMaxMinor !== null)
            ? [r.salaryMinMinor, r.salaryMaxMinor]
                  .filter((v): v is number => v !== null)
                  .map((v) => formatMinor(v, r.currency!, locale))
                  .join(' – ')
            : null;
    const [editingTeam, setEditingTeam] = useState(false);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={r.title}
            description={`${r.number} · ${t(`requisitionStatus.${r.status}`)}`}
            className="sm:max-w-2xl"
            footer={
                <div className="flex flex-wrap justify-end gap-2">
                    {detail.can.update
                        ? detail.next.map((status) => (
                              <Button
                                  key={status}
                                  variant="outline"
                                  className="min-h-11"
                                  data-status-to={status}
                                  onClick={() =>
                                      router.put(
                                          hiring.requisitions.status.url(r.id),
                                          { status },
                                          { preserveScroll: true },
                                      )
                                  }
                              >
                                  {t(`requisitions.to.${status}`)}
                              </Button>
                          ))
                        : null}
                    {r.status !== 'pending' ? (
                        <Button asChild className="min-h-11">
                            <Link href={hiring.board.url(r.id)} data-open-board>
                                <Kanban aria-hidden />
                                {t('requisitions.openBoard')}
                            </Link>
                        </Button>
                    ) : null}
                </div>
            }
        >
            <div className="flex flex-col gap-6" data-requisition-detail>
                <div className="flex items-center gap-2">
                    <RequisitionStatusBadge status={r.status} />
                </div>
                <Facts
                    items={[
                        [t('requisitions.fact.branch'), r.branch],
                        [t('requisitions.fact.department'), r.department ?? '—'],
                        [t('requisitions.fact.position'), r.position ?? '—'],
                        [t('requisitions.fact.headcount'), formatNumber(r.headcount, locale)],
                        [t('requisitions.fact.reason'), t(`reason.${r.reason}`)],
                        [t('requisitions.fact.manager'), r.hiringManager ?? '—'],
                        [
                            t('requisitions.fact.start'),
                            r.targetStartOn
                                ? formatDate(r.targetStartOn, locale, { dateStyle: 'medium' })
                                : '—',
                        ],
                        [t('requisitions.fact.salary'), salary ?? '—'],
                    ]}
                />
                {r.justification ? (
                    <section className="flex flex-col gap-1">
                        <h3 className="text-small font-medium">{t('requisitions.fact.why')}</h3>
                        <p className="text-small whitespace-pre-line text-text-secondary">
                            {r.justification}
                        </p>
                    </section>
                ) : null}

                <section aria-labelledby="team-title" className="flex flex-col gap-2">
                    <div className="flex items-center justify-between gap-2">
                        <h3 id="team-title" className="flex items-center gap-2 text-h4">
                            <Users aria-hidden className="size-4" />
                            {t('team.title')}
                        </h3>
                        {detail.can.update && !editingTeam ? (
                            <Button
                                variant="ghost"
                                className="min-h-11"
                                onClick={() => setEditingTeam(true)}
                                data-edit-team
                            >
                                {t('team.edit')}
                            </Button>
                        ) : null}
                    </div>
                    {editingTeam ? (
                        <TeamEditor
                            requisitionId={r.id}
                            members={detail.members}
                            onDone={() => setEditingTeam(false)}
                        />
                    ) : detail.members.length === 0 ? (
                        <p className="text-small text-text-secondary">{t('team.empty')}</p>
                    ) : (
                        <ul className="flex flex-col gap-1">
                            {detail.members.map((m) => (
                                <li
                                    key={m.user}
                                    className="flex items-center justify-between gap-2 text-small"
                                >
                                    <span>{m.name}</span>
                                    <span className="text-text-secondary">
                                        {t(`memberRole.${m.role}`)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section aria-labelledby="postings-title" className="flex flex-col gap-2">
                    <div className="flex items-center justify-between gap-2">
                        <h3 id="postings-title" className="text-h4">
                            {t('postings.title')}
                        </h3>
                        {detail.can.update && r.status === 'open' ? (
                            <Button asChild variant="ghost" className="min-h-11">
                                <Link href={hiring.postings.create.url(r.id)} data-new-posting>
                                    <Plus aria-hidden />
                                    {t('postings.new')}
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                    {detail.postings.length === 0 ? (
                        <p className="text-small text-text-secondary">{t('postings.empty')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border rounded-md border border-border">
                            {detail.postings.map((p) => (
                                <li key={p.id} className="flex flex-wrap items-center gap-2 p-3">
                                    <Link
                                        href={hiring.postings.edit.url(p.id)}
                                        className="min-w-0 flex-1 truncate text-small font-medium hover:underline"
                                    >
                                        {p.title}
                                    </Link>
                                    <span className="text-caption text-text-secondary">
                                        {t('postings.applicants', {
                                            count: p.applicants,
                                            formatted: formatNumber(p.applicants, locale),
                                        })}
                                    </span>
                                    <PostingStatusBadge status={p.status} />
                                    {p.status === 'published' ? (
                                        <a
                                            href={p.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex size-11 items-center justify-center rounded-md text-text-secondary hover:text-foreground"
                                            aria-label={t('postings.view', { title: p.title })}
                                        >
                                            <ExternalLink aria-hidden className="size-4" />
                                        </a>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {detail.timeline.length > 0 ? (
                    <section aria-labelledby="approval-title" className="flex flex-col gap-2">
                        <h3 id="approval-title" className="text-h4">
                            {t('requisitions.approval')}
                        </h3>
                        <Timeline entries={detail.timeline} />
                    </section>
                ) : null}
            </div>
        </ResponsiveDialog>
    );
}

const ROLES = ['recruiter', 'hiring_manager', 'coordinator'] as const;

function TeamEditor({
    requisitionId,
    members,
    onDone,
}: {
    requisitionId: string;
    members: Member[];
    onDone: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [list, setList] = useState<Member[]>(members);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [picked, setPicked] = useState<PickerOption | null>(null);

    return (
        <div className="flex flex-col gap-3 rounded-md border border-border p-3">
            <ul className="flex flex-col gap-2">
                {list.map((m, i) => (
                    <li key={m.user} className="flex flex-col gap-1">
                        <div className="flex items-center gap-2">
                            <span className="min-w-0 flex-1 truncate text-small">{m.name}</span>
                            <Select
                                value={m.role}
                                onValueChange={(role) =>
                                    setList((all) =>
                                        all.map((x, j) => (j === i ? { ...x, role } : x)),
                                    )
                                }
                            >
                                <SelectTrigger
                                    className="min-h-11 w-40"
                                    aria-label={t('team.roleFor', { name: m.name })}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {ROLES.map((role) => (
                                        <SelectItem key={role} value={role}>
                                            {t(`memberRole.${role}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="size-11 text-danger"
                                aria-label={t('team.remove', { name: m.name })}
                                onClick={() => setList((all) => all.filter((_, j) => j !== i))}
                            >
                                <Trash2 aria-hidden className="size-4" />
                            </Button>
                        </div>
                        {errors[`members.${i}.user`] ? (
                            <p role="alert" className="text-caption text-danger">
                                {errors[`members.${i}.user`]}
                            </p>
                        ) : null}
                    </li>
                ))}
            </ul>
            <AsyncPicker<{ id: string; name: string; email: string }>
                endpoint="hiring/people"
                queryKey="hiring-people"
                label={t('team.add')}
                placeholder={t('team.addPlaceholder')}
                value={picked}
                toOption={(u) => ({ id: u.id, label: u.name, description: u.email })}
                onChange={(option) => {
                    setPicked(null);

                    if (option && !list.some((m) => m.user === option.id)) {
                        setList((all) => [
                            ...all,
                            { user: option.id, name: option.label, role: 'recruiter' },
                        ]);
                    }
                }}
            />
            <div className="flex justify-end gap-2">
                <Button type="button" variant="ghost" className="min-h-11" onClick={onDone}>
                    {t('cancel')}
                </Button>
                <Button
                    type="button"
                    className="min-h-11"
                    data-save-team
                    onClick={() =>
                        router.put(
                            hiring.requisitions.team.url(requisitionId),
                            { members: list.map(({ user, role }) => ({ user, role })) },
                            { preserveScroll: true, onError: setErrors, onSuccess: onDone },
                        )
                    }
                >
                    {t('team.save')}
                </Button>
            </div>
        </div>
    );
}
