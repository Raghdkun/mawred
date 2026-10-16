import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Copy, Repeat, Settings2, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { addDays, daysBetween } from '@/features/schedule/colors';
import { CoverageDialog } from '@/features/schedule/coverage-dialog';
import { RosterGrid, type Person, type TimeOff } from '@/features/schedule/roster-grid';
import { RotationDialog } from '@/features/schedule/rotation-dialog';
import {
    type Claim,
    type RosterShift,
    ShiftDialog,
    type ShiftTemplate,
} from '@/features/schedule/shift-dialog';
import { rangeLabel } from '@/features/leave/format';
import { useAccess } from '@/hooks/use-access';
import { useLocale } from '@/hooks/use-locale';
import { localized, personName } from '@/lib/localized';
import roster from '@/routes/roster';
import schedule from '@/routes/schedule';

type View = 'week' | '2weeks' | 'month';
type Props = {
    branch: { id: string; name: Record<string, string>; weekend: number[] };
    branches: Array<{ id: string; name: Record<string, string> }>;
    from: string;
    to: string;
    view: View;
    today: string;
    people: Person[];
    shifts: RosterShift[];
    timeOff: TimeOff[];
    holidays: Array<{ date: string; name: Record<string, string> }>;
    coverage: Record<string, number>;
    templates: ShiftTemplate[];
    rotations: Array<{ id: string; name: Record<string, string>; length: number }>;
    drafts: number;
    claims: Record<string, Claim[]>;
};

type Editing = {
    date: string;
    employeeId: string | null;
    shift: RosterShift | null;
    title: string;
};

/**
 * Roster planner (handoff 22): Week / 2 weeks / Month, people × days with shift blocks, the open
 * shifts row, the coverage row and the publish bar with the unpublished count. Desktop-first;
 * tablets scroll sideways with the name column fixed; phones get My schedule instead.
 */
export default function Roster(props: Props) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const { can } = useAccess();
    const { branch, from, to, view, today } = props;
    const [editing, setEditing] = useState<Editing | null>(null);
    const [dialog, setDialog] = useState<'rotation' | 'coverage' | null>(null);
    const days = daysBetween(from, to);
    const visit = (query: Record<string, string>) =>
        router.get(
            roster.show.url({ query: { branch: branch.id, view, date: from, ...query } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const step = view === 'month' ? 31 : view === '2weeks' ? 14 : 7;
    const personFor = (id: string | null) => props.people.find((p) => p.id === id);
    const nameOf = (id: string | null) => {
        const person = personFor(id);

        return person ? personName(person, locale) : t('roster.openRow');
    };

    const move = (shift: RosterShift, target: { employeeId: string | null; date: string }) =>
        router.put(
            roster.shifts.update.url(shift.id),
            {
                date: target.date,
                employeeId: target.employeeId,
                templateId: shift.templateId,
                startsAt: shift.templateId ? null : shift.startsAt,
                endsAt: shift.templateId ? null : shift.endsAt,
                breakMinutes: shift.templateId ? null : shift.breakMinutes,
                note: shift.note,
                autoAssign: target.employeeId === null && shift.autoAssign,
            },
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title={t('roster.title')} />
            <PageHeader
                title={t('roster.title')}
                description={t('roster.description')}
                actions={
                    can('scheduling.configure') ? (
                        <Button
                            asChild
                            variant="outline"
                            className="hidden min-h-11 md:inline-flex"
                        >
                            <Link href={schedule.settings.url()}>
                                <Settings2 aria-hidden className="size-4" />
                                {t('roster.settings')}
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="md:hidden">
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('roster.title')}
                    description={t('roster.mobile')}
                    action={
                        <Button asChild className="min-h-11">
                            <Link href={schedule.mine.url()}>{t('roster.mobileLink')}</Link>
                        </Button>
                    }
                />
            </div>

            <div className="hidden flex-col gap-4 md:flex">
                <div className="flex flex-wrap items-center gap-3">
                    <Select value={branch.id} onValueChange={(id) => visit({ branch: id })}>
                        <SelectTrigger aria-label={t('roster.branch')} className="min-h-11 w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {props.branches.map((b) => (
                                <SelectItem key={b.id} value={b.id}>
                                    {localized(b.name, locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <ToggleGroup
                        type="single"
                        value={view}
                        onValueChange={(v) => v && visit({ view: v })}
                        aria-label={t('roster.title')}
                    >
                        {(['week', '2weeks', 'month'] as const).map((v) => (
                            <ToggleGroupItem key={v} value={v} className="min-h-11 px-4">
                                {t(`roster.views.${v}`)}
                            </ToggleGroupItem>
                        ))}
                    </ToggleGroup>
                    <div className="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11"
                            aria-label={t('roster.previous')}
                            onClick={() => visit({ date: addDays(from, -step) })}
                        >
                            <DirIcon>
                                <ChevronLeft aria-hidden className="size-4" />
                            </DirIcon>
                        </Button>
                        <span
                            aria-live="polite"
                            className="min-w-48 text-center text-small font-medium"
                        >
                            {rangeLabel(from, to, locale)}
                        </span>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="size-11"
                            aria-label={t('roster.next')}
                            onClick={() => visit({ date: addDays(from, step) })}
                        >
                            <DirIcon>
                                <ChevronRight aria-hidden className="size-4" />
                            </DirIcon>
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="min-h-11"
                            onClick={() => visit({ date: today })}
                        >
                            {t('roster.today')}
                        </Button>
                    </div>
                    <div className="ms-auto flex flex-wrap gap-2">
                        {view === 'week' ? (
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11"
                                onClick={() =>
                                    router.post(
                                        roster.copy.url(branch.id),
                                        { from: addDays(from, -7), to: from },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Copy aria-hidden className="size-4" />
                                {t('roster.copyWeek')}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setDialog('rotation')}
                        >
                            <Repeat aria-hidden className="size-4" />
                            {t('roster.rotation')}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            className="min-h-11"
                            onClick={() => setDialog('coverage')}
                        >
                            <Users aria-hidden className="size-4" />
                            {t('roster.coverageEdit')}
                        </Button>
                    </div>
                </div>

                {props.people.length === 0 ? (
                    <PageState
                        kind="empty"
                        illustration="empty-employees"
                        title={t('roster.nobody')}
                    />
                ) : (
                    <RosterGrid
                        days={days}
                        today={today}
                        weekend={branch.weekend}
                        people={props.people}
                        shifts={props.shifts}
                        timeOff={props.timeOff}
                        holidays={props.holidays}
                        coverage={props.coverage}
                        canPlanBranch
                        onAdd={({ employeeId, date }) =>
                            setEditing({
                                employeeId,
                                date,
                                shift: null,
                                title: `${t('dialog.add')} · ${nameOf(employeeId)}`,
                            })
                        }
                        onEdit={(shift) =>
                            setEditing({
                                employeeId: shift.employeeId,
                                date: shift.date,
                                shift,
                                title: `${t('dialog.edit')} · ${nameOf(shift.employeeId)}`,
                            })
                        }
                        onMove={move}
                    />
                )}

                <div
                    role="region"
                    aria-label={t('roster.publish')}
                    className="sticky bottom-4 z-30 flex items-center justify-between gap-3 rounded-lg border border-border bg-card p-3 shadow-md"
                    data-publish-bar
                >
                    <span aria-live="polite" className="text-small">
                        {t('roster.publishBar', { count: props.drafts })}
                    </span>
                    <Button
                        type="button"
                        className="min-h-11"
                        disabled={props.drafts === 0}
                        onClick={() =>
                            router.post(
                                roster.publish.url(branch.id),
                                { from, to },
                                { preserveScroll: true },
                            )
                        }
                        data-publish
                    >
                        {t('roster.publish')}
                    </Button>
                </div>
            </div>

            {editing ? (
                <ShiftDialog
                    key={`${editing.shift?.id ?? 'new'}-${editing.employeeId}-${editing.date}`}
                    open
                    onClose={() => setEditing(null)}
                    branchId={branch.id}
                    date={editing.date}
                    employeeId={editing.employeeId}
                    title={editing.title}
                    shift={editing.shift}
                    templates={props.templates}
                    claims={editing.shift ? (props.claims[editing.shift.id] ?? []) : []}
                />
            ) : null}
            {dialog === 'rotation' ? (
                <RotationDialog
                    open
                    onClose={() => setDialog(null)}
                    branchId={branch.id}
                    rotations={props.rotations}
                    people={props.people}
                    from={from}
                    to={to}
                />
            ) : null}
            {dialog === 'coverage' ? (
                <CoverageDialog
                    open
                    onClose={() => setDialog(null)}
                    branchId={branch.id}
                    coverage={props.coverage}
                />
            ) : null}
        </>
    );
}
