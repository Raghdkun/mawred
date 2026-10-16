import {
    DndContext,
    type DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { Head, Link, router } from '@inertiajs/react';
import { useReducedMotion } from 'motion/react';
import {
    AlertTriangle,
    ChevronLeft,
    Copy,
    ListChecks,
    MoreHorizontal,
    Plus,
    Settings2,
    UserX,
    Users,
    Zap,
} from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { FormRenderer } from '@/features/forms/form-renderer';
import { type FormRuleContext, type FormSchema, asPayload } from '@/features/forms/schema';
import { AutomationEditor } from '@/features/hiring/automation-editor';
import { CriteriaEditor } from '@/features/hiring/criteria-editor';
import { StageEditor } from '@/features/hiring/stage-editor';
import { daysSince, REJECTION_REASONS, RequisitionStatusBadge } from '@/features/hiring/status';
import { useLocale } from '@/hooks/use-locale';
import { useIsMobile } from '@/hooks/use-mobile';
import { formatNumber } from '@/lib/format';
import { dragLift } from '@/lib/motion';
import { cn } from '@/lib/utils';
import hiring from '@/routes/hiring';

type Card = App.Domain.Hiring.Data.BoardCardData;

/** Refusals (e.g. the requisition was closed meanwhile) come back as flashed errors. */
const showError = (errors: Record<string, string>) => {
    const message = errors.error ?? Object.values(errors)[0];

    if (message) toast.error(message);
};
type Stage = App.Domain.Hiring.Data.StageData;
type Props = {
    requisition: App.Domain.Hiring.Data.RequisitionData;
    stages: Stage[];
    cards: Card[];
    showRejected: boolean;
    postings: Array<{ id: string; title: string; status: string }>;
    addForm: { schema: FormSchema; context: FormRuleContext } | null;
    criteria: Array<{ id: string; name: { en: string; ar: string } }>;
    automationPeople: Record<string, string>;
    can: { update: boolean; open: boolean };
};

/**
 * The pipeline kanban (handoff 29 ★): stage columns in the reading direction with counts, cards
 * with days in stage, tags and flags, drag between stages (keyboard too, and a "Move to" menu),
 * bulk reject, stage settings. Tablets scroll sideways; phones show one stage at a time.
 */
export default function Board({
    requisition,
    stages,
    cards,
    showRejected,
    postings,
    addForm,
    criteria,
    automationPeople,
    can,
}: Props) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [now] = useState(() => Date.now());
    const [pending, setPending] = useState<Record<string, string>>({});
    const [selected, setSelected] = useState<string[]>([]);
    const [rejecting, setRejecting] = useState<string[] | null>(null);
    const [adding, setAdding] = useState(false);
    const [editingStages, setEditingStages] = useState(false);
    const [editing, setEditing] = useState<'criteria' | 'automations' | null>(null);
    const [mobileStage, setMobileStage] = useState(stages[0]?.id ?? '');
    const [announcement, setAnnouncement] = useState('');
    const isMobile = useIsMobile();
    const editable = can.update && can.open && !showRejected;
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor),
    );

    const stageOf = (card: Card) => pending[card.id] ?? card.stageId;
    const inStage = (stage: Stage) => cards.filter((c) => stageOf(c) === stage.id);
    const stageName = (id: string) => stages.find((s) => s.id === id)?.name ?? '';

    const move = (card: Card, stageId: string) => {
        if (stageOf(card) === stageId) return;

        setPending((p) => ({ ...p, [card.id]: stageId }));
        setAnnouncement(t('board.moved', { name: card.name, stage: stageName(stageId) }));
        router.post(
            hiring.applications.move.url(card.id),
            { stage: stageId },
            {
                preserveScroll: true,
                preserveState: true,
                onError: showError,
                onFinish: () =>
                    setPending((p) => {
                        const next = { ...p };
                        delete next[card.id];

                        return next;
                    }),
            },
        );
    };

    const onDragEnd = (event: DragEndEvent) => {
        const card = cards.find((c) => c.id === String(event.active.id));
        const target = event.over?.id ? String(event.over.id) : null;

        if (card && target) move(card, target);
    };

    const toggle = (id: string, on: boolean) =>
        setSelected((list) => (on ? [...list, id] : list.filter((x) => x !== id)));

    const column = (stage: Stage, className?: string) => (
        <StageColumn
            key={stage.id}
            stage={stage}
            cards={inStage(stage)}
            stages={stages}
            editable={editable}
            showRejected={showRejected}
            canUpdate={can.update}
            now={now}
            selected={selected}
            onToggle={toggle}
            onMove={move}
            onReject={(id) => setRejecting([id])}
            className={className}
        />
    );

    return (
        <>
            <Head title={`${requisition.title} · ${t('board.title')}`} />
            <Link
                href={hiring.requisitions.index.url({ query: { open: requisition.id } })}
                className="mb-4 inline-flex min-h-11 items-center gap-1 text-small text-text-secondary hover:text-foreground"
            >
                <DirIcon>
                    <ChevronLeft aria-hidden className="size-4" />
                </DirIcon>
                {t('board.back')}
            </Link>
            <PageHeader
                title={requisition.title}
                description={[requisition.number, requisition.branch, requisition.department]
                    .filter(Boolean)
                    .join(' · ')}
                actions={
                    <div className="flex flex-wrap items-center gap-2">
                        <RequisitionStatusBadge status={requisition.status} />
                        {editable && addForm ? (
                            <Button
                                className="min-h-11"
                                onClick={() => setAdding(true)}
                                data-add-candidate
                            >
                                <Plus aria-hidden />
                                {t('board.add')}
                            </Button>
                        ) : null}
                        {can.update ? (
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        className="size-11"
                                        aria-label={t('board.more')}
                                    >
                                        <MoreHorizontal aria-hidden className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onSelect={() => setEditingStages(true)}>
                                        <Settings2 aria-hidden />
                                        {t('board.stageSettings')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => setEditing('automations')}
                                        data-automations
                                    >
                                        <Zap aria-hidden />
                                        {t('automations.title')}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        onSelect={() => setEditing('criteria')}
                                        data-criteria
                                    >
                                        <ListChecks aria-hidden />
                                        {t('criteria.title')}
                                    </DropdownMenuItem>
                                    {can.open ? (
                                        <DropdownMenuItem asChild>
                                            <Link href={hiring.postings.create.url(requisition.id)}>
                                                <Plus aria-hidden />
                                                {t('postings.new')}
                                            </Link>
                                        </DropdownMenuItem>
                                    ) : null}
                                    {postings.length > 0 ? (
                                        <>
                                            <DropdownMenuSeparator />
                                            <DropdownMenuLabel>
                                                {t('postings.title')}
                                            </DropdownMenuLabel>
                                            {postings.map((p) => (
                                                <DropdownMenuItem key={p.id} asChild>
                                                    <Link href={hiring.postings.edit.url(p.id)}>
                                                        {p.title} · {t(`postingStatus.${p.status}`)}
                                                    </Link>
                                                </DropdownMenuItem>
                                            ))}
                                        </>
                                    ) : null}
                                </DropdownMenuContent>
                            </DropdownMenu>
                        ) : null}
                    </div>
                }
            />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div
                    role="group"
                    aria-label={t('board.view')}
                    className="inline-flex rounded-md border border-border bg-card p-0.5"
                >
                    {[false, true].map((rejected) => (
                        <Link
                            key={String(rejected)}
                            href={hiring.board.url(requisition.id, {
                                query: rejected ? { rejected: 1 } : {},
                            })}
                            preserveScroll
                            aria-current={showRejected === rejected ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-10 items-center rounded-sm px-3 text-small',
                                showRejected === rejected
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-text-secondary hover:text-foreground',
                            )}
                        >
                            {rejected ? t('board.rejectedView') : t('board.activeView')}
                        </Link>
                    ))}
                </div>
                {selected.length > 0 && editable ? (
                    <div className="flex items-center gap-2" data-bulk>
                        <span className="text-small text-text-secondary">
                            {t('board.selected', {
                                count: selected.length,
                                formatted: formatNumber(selected.length, locale),
                            })}
                        </span>
                        <Button
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setRejecting(selected)}
                            data-bulk-reject
                        >
                            <UserX aria-hidden />
                            {t('board.reject')}
                        </Button>
                    </div>
                ) : null}
            </div>

            <p aria-live="polite" className="sr-only">
                {announcement}
            </p>

            {cards.length === 0 && showRejected ? (
                <PageState
                    kind="empty"
                    illustration="empty-candidates"
                    title={t('board.noRejected')}
                />
            ) : (
                <DndContext sensors={sensors} onDragEnd={onDragEnd}>
                    {isMobile ? (
                        // Phones: one stage at a time with a switcher.
                        <div className="flex flex-col gap-3">
                            <Select value={mobileStage} onValueChange={setMobileStage}>
                                <SelectTrigger
                                    className="min-h-11"
                                    aria-label={t('board.stage')}
                                    data-stage-switcher
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {stages.map((s) => (
                                        <SelectItem key={s.id} value={s.id}>
                                            {s.name} ({formatNumber(inStage(s).length, locale)})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {stages
                                .filter((s) => s.id === mobileStage)
                                .map((s) => column(s, 'w-full'))}
                        </div>
                    ) : (
                        // Tablet and desktop: columns in the reading direction, scrolling sideways when needed.
                        <div className="overflow-x-auto pb-4" data-board>
                            <ol className="flex min-w-max gap-3" aria-label={t('board.title')}>
                                {stages.map((s) => (
                                    <li key={s.id} className="w-72 shrink-0">
                                        {column(s)}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )}
                </DndContext>
            )}

            {rejecting ? (
                <RejectDialog
                    ids={rejecting}
                    onClose={(done) => {
                        setRejecting(null);

                        if (done) setSelected([]);
                    }}
                />
            ) : null}

            {adding && addForm ? (
                <AddCandidateDialog
                    requisitionId={requisition.id}
                    form={addForm}
                    onClose={() => setAdding(false)}
                />
            ) : null}

            <ResponsiveDialog
                open={editingStages}
                onOpenChange={setEditingStages}
                title={t('board.stageSettings')}
                description={t('stages.description')}
                className="sm:max-w-3xl"
            >
                <StageEditor
                    stages={stages}
                    saveUrl={hiring.board.stages.url(requisition.id)}
                    onSaved={() => setEditingStages(false)}
                />
            </ResponsiveDialog>

            <ResponsiveDialog
                open={editing === 'criteria'}
                onOpenChange={(open) => !open && setEditing(null)}
                title={t('criteria.title')}
                description={t('criteria.description')}
                className="sm:max-w-2xl"
            >
                <CriteriaEditor
                    criteria={criteria}
                    saveUrl={hiring.board.criteria.url(requisition.id)}
                    onSaved={() => setEditing(null)}
                />
            </ResponsiveDialog>

            <ResponsiveDialog
                open={editing === 'automations'}
                onOpenChange={(open) => !open && setEditing(null)}
                title={t('automations.title')}
                description={t('automations.description')}
                className="sm:max-w-3xl"
            >
                <AutomationEditor
                    stages={stages}
                    people={automationPeople}
                    onSaved={() => setEditing(null)}
                />
            </ResponsiveDialog>
        </>
    );
}

function StageColumn({
    stage,
    cards,
    stages,
    editable,
    showRejected,
    canUpdate,
    now,
    selected,
    onToggle,
    onMove,
    onReject,
    className,
}: {
    stage: Stage;
    cards: Card[];
    stages: Stage[];
    editable: boolean;
    showRejected: boolean;
    canUpdate: boolean;
    now: number;
    selected: string[];
    onToggle: (id: string, on: boolean) => void;
    onMove: (card: Card, stageId: string) => void;
    onReject: (id: string) => void;
    className?: string;
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const { setNodeRef, isOver } = useDroppable({ id: stage.id, disabled: !editable });
    const headingId = `stage-${stage.id}`;

    return (
        <section
            ref={setNodeRef}
            aria-labelledby={headingId}
            data-stage={stage.id}
            data-stage-kind={stage.kind}
            className={cn(
                'flex min-h-48 flex-col gap-2 rounded-lg bg-surface-sunken p-2 transition-colors duration-150 ease-soft motion-reduce:transition-none',
                isOver && 'bg-primary/10 ring-2 ring-primary',
                className,
            )}
        >
            <h2
                id={headingId}
                className="flex items-center justify-between gap-2 px-1 py-1 text-small font-semibold"
            >
                <span className="truncate">{stage.name}</span>
                <span className="rounded-full bg-card px-2 text-caption font-medium text-text-secondary tabular-nums">
                    {formatNumber(cards.length, locale)}
                </span>
            </h2>
            {cards.length === 0 ? (
                <p className="px-1 py-6 text-center text-caption text-text-secondary">
                    {t('board.emptyStage')}
                </p>
            ) : (
                <ul className="flex flex-col gap-2">
                    {cards.map((card) => (
                        <li key={card.id}>
                            <BoardCard
                                card={card}
                                stages={stages}
                                editable={editable}
                                showRejected={showRejected}
                                canUpdate={canUpdate}
                                now={now}
                                checked={selected.includes(card.id)}
                                onToggle={onToggle}
                                onMove={onMove}
                                onReject={onReject}
                            />
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function BoardCard({
    card,
    stages,
    editable,
    showRejected,
    canUpdate,
    now,
    checked,
    onToggle,
    onMove,
    onReject,
}: {
    card: Card;
    stages: Stage[];
    editable: boolean;
    showRejected: boolean;
    canUpdate: boolean;
    now: number;
    checked: boolean;
    onToggle: (id: string, on: boolean) => void;
    onMove: (card: Card, stageId: string) => void;
    onReject: (id: string) => void;
}) {
    const { t } = useTranslation('hiring');
    const { locale, dir } = useLocale();
    const reduced = useReducedMotion() ?? false;
    const lift = dragLift(dir, reduced);
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: card.id,
        disabled: !editable,
    });
    const days = daysSince(card.stageEnteredAt, now);

    return (
        <article
            ref={setNodeRef}
            data-card={card.id}
            style={
                transform
                    ? {
                          transform: `translate3d(${transform.x}px, ${transform.y}px, 0) rotate(${lift.rotate}deg) scale(${lift.scale})`,
                          zIndex: 30,
                      }
                    : undefined
            }
            className={cn(
                'relative flex flex-col gap-2 rounded-md border border-border bg-card p-3 shadow-sm transition-shadow duration-150 ease-soft motion-reduce:transition-none',
                isDragging && 'shadow-lg',
                card.knockedOut && 'border-s-4 border-s-warning',
            )}
        >
            <div className="flex items-start gap-2">
                {editable ? (
                    <Checkbox
                        checked={checked}
                        onCheckedChange={(on) => onToggle(card.id, on === true)}
                        aria-label={t('board.select', { name: card.name })}
                        className="mt-0.5"
                    />
                ) : null}
                <div className="flex min-w-0 flex-1 flex-col gap-0.5">
                    <Link
                        href={hiring.candidates.show.url(card.candidateId, {
                            query: { application: card.id },
                        })}
                        className="truncate text-sm font-medium hover:underline"
                        data-candidate-link
                    >
                        {card.name}
                    </Link>
                    <span className="text-caption text-text-secondary">
                        {showRejected && card.rejectionReason
                            ? t(`rejection.${card.rejectionReason}`)
                            : t('board.daysInStage', {
                                  count: days,
                                  formatted: formatNumber(days, locale),
                              })}
                        {' · '}
                        {t(`source.${card.source}`)}
                    </span>
                    {card.interviewers.length > 0 ? (
                        <span className="flex items-center gap-1 text-caption text-text-secondary">
                            <Users aria-hidden className="size-3" />
                            <span className="truncate">{card.interviewers.join(', ')}</span>
                        </span>
                    ) : null}
                </div>
                {card.score !== null ? (
                    <span
                        className="inline-flex h-6 shrink-0 items-center rounded-full bg-primary/10 px-2 text-caption font-medium text-foreground tabular-nums"
                        aria-label={t('board.score', {
                            score: formatNumber(card.score, locale, { maximumFractionDigits: 1 }),
                        })}
                    >
                        {formatNumber(card.score, locale, { maximumFractionDigits: 1 })}/4
                    </span>
                ) : null}
                {editable ? (
                    <button
                        type="button"
                        {...attributes}
                        {...listeners}
                        aria-label={t('board.drag', { name: card.name })}
                        className="inline-flex size-8 shrink-0 cursor-grab items-center justify-center rounded-sm text-text-secondary hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none active:cursor-grabbing"
                        data-drag-handle
                    >
                        <span aria-hidden className="grid grid-cols-2 gap-0.5">
                            {Array.from({ length: 6 }, (_, i) => (
                                <span key={i} className="size-1 rounded-full bg-current" />
                            ))}
                        </span>
                    </button>
                ) : null}
            </div>

            {card.tags.length > 0 || card.knockedOut || card.possibleDuplicate ? (
                <ul className="flex flex-wrap gap-1" aria-label={t('board.flags')}>
                    {card.knockedOut ? (
                        <li
                            className="inline-flex items-center gap-1 rounded-full bg-warning-bg px-2 py-0.5 text-caption text-warning"
                            title={card.knockouts.join(', ')}
                        >
                            <AlertTriangle aria-hidden className="size-3" />
                            {t('board.knockedOut')}
                            <span className="sr-only">: {card.knockouts.join(', ')}</span>
                        </li>
                    ) : null}
                    {card.possibleDuplicate ? (
                        <li className="inline-flex items-center gap-1 rounded-full bg-info-bg px-2 py-0.5 text-caption text-info">
                            <Copy aria-hidden className="size-3" />
                            {t('board.duplicate')}
                        </li>
                    ) : null}
                    {card.tags.slice(0, 3).map((tag) => (
                        <li
                            key={tag}
                            className="rounded-full bg-muted px-2 py-0.5 text-caption text-text-secondary"
                        >
                            {tag}
                        </li>
                    ))}
                </ul>
            ) : null}

            {editable ? (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="ghost"
                            className="min-h-9 justify-start px-2 text-caption"
                            data-card-menu
                        >
                            {t('board.moveTo')}
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="start">
                        {stages
                            .filter((s) => s.id !== card.stageId)
                            .map((s) => (
                                <DropdownMenuItem
                                    key={s.id}
                                    onSelect={() => onMove(card, s.id)}
                                    data-move-to={s.kind}
                                >
                                    {s.name}
                                </DropdownMenuItem>
                            ))}
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            className="text-danger"
                            onSelect={() => onReject(card.id)}
                            data-reject
                        >
                            <UserX aria-hidden />
                            {t('board.reject')}
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ) : showRejected && canUpdate ? (
                <Button
                    variant="ghost"
                    className="min-h-9 justify-start px-2 text-caption"
                    onClick={() =>
                        router.post(
                            hiring.applications.reopen.url(card.id),
                            {},
                            { preserveScroll: true, onError: showError },
                        )
                    }
                    data-reopen
                >
                    {t('board.reopen')}
                </Button>
            ) : null}
        </article>
    );
}

function RejectDialog({ ids, onClose }: { ids: string[]; onClose: (done: boolean) => void }) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [reason, setReason] = useState<string>('not_qualified');
    const [note, setNote] = useState('');
    const [notify, setNotify] = useState(true);
    const [sending, setSending] = useState(false);

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose(false)}
            title={t('reject.title', {
                count: ids.length,
                formatted: formatNumber(ids.length, locale),
            })}
            description={t('reject.description')}
            footer={
                <div className="flex justify-end gap-2">
                    <Button variant="ghost" className="min-h-11" onClick={() => onClose(false)}>
                        {t('cancel')}
                    </Button>
                    <Button
                        variant="destructive"
                        className="min-h-11"
                        disabled={sending}
                        data-confirm-reject
                        onClick={() => {
                            setSending(true);
                            router.post(
                                hiring.applications.reject.url(),
                                { applications: ids, reason, note: note || null, notify },
                                {
                                    preserveScroll: true,
                                    onError: showError,
                                    onSuccess: () => onClose(true),
                                    onFinish: () => setSending(false),
                                },
                            );
                        }}
                    >
                        {t('board.reject')}
                    </Button>
                </div>
            }
        >
            <div className="flex flex-col gap-4">
                <label className="flex flex-col gap-1 text-small">
                    <span id="reject-reason">{t('reject.reason')}</span>
                    <Select value={reason} onValueChange={setReason}>
                        <SelectTrigger className="min-h-11" aria-labelledby="reject-reason">
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
                </label>
                <label className="flex flex-col gap-1 text-small">
                    {t('reject.note')}
                    <Textarea
                        value={note}
                        onChange={(e) => setNote(e.target.value)}
                        maxLength={2000}
                        rows={3}
                    />
                </label>
                <label
                    htmlFor="reject-notify"
                    className="flex min-h-11 items-center gap-2 text-small"
                >
                    <Checkbox
                        id="reject-notify"
                        checked={notify}
                        onCheckedChange={(on) => setNotify(on === true)}
                    />
                    {t('reject.notify')}
                </label>
            </div>
        </ResponsiveDialog>
    );
}

function AddCandidateDialog({
    requisitionId,
    form,
    onClose,
}: {
    requisitionId: string;
    form: { schema: FormSchema; context: FormRuleContext };
    onClose: () => void;
}) {
    const { t } = useTranslation('hiring');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);
    const [source, setSource] = useState('manual');

    return (
        <ResponsiveDialog
            open
            onOpenChange={(open) => !open && onClose()}
            title={t('add.title')}
            description={t('add.description')}
            className="sm:max-w-2xl"
        >
            <div className="flex flex-col gap-4">
                <label className="flex flex-col gap-1 text-small">
                    <span id="add-source">{t('add.source')}</span>
                    <Select value={source} onValueChange={setSource}>
                        <SelectTrigger className="min-h-11" aria-labelledby="add-source">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="manual">{t('source.manual')}</SelectItem>
                            <SelectItem value="referral">{t('source.referral')}</SelectItem>
                        </SelectContent>
                    </Select>
                </label>
                {errors.error ? (
                    <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                        {errors.error}
                    </p>
                ) : null}
                <FormRenderer
                    schema={form.schema}
                    context={form.context}
                    uploadUrl={hiring.board.uploads.url(requisitionId)}
                    serverErrors={errors}
                    submitting={sending}
                    submitLabel={t('add.submit')}
                    onSubmit={(values) => {
                        setSending(true);
                        router.post(
                            hiring.board.add.url(requisitionId),
                            asPayload({ values, source }),
                            {
                                preserveScroll: true,
                                onError: setErrors,
                                onSuccess: onClose,
                                onFinish: () => setSending(false),
                            },
                        );
                    }}
                />
            </div>
        </ResponsiveDialog>
    );
}
