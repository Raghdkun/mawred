import {
    DndContext,
    type DragEndEvent,
    PointerSensor,
    KeyboardSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { useVirtualizer } from '@tanstack/react-virtual';
import { useReducedMotion } from 'motion/react';
import { AlertTriangle, Plus } from 'lucide-react';
import { memo, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { dayLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { dragLift } from '@/lib/motion';
import { cn } from '@/lib/utils';
import { parseDay, shiftColor, weekdayIso } from './colors';
import { type RosterShift } from './shift-dialog';
import { ShiftTime } from './shift-time';

export type Person = {
    id: string;
    number: string;
    nameEn: string;
    nameAr: string | null;
    preferredName: string | null;
    position: Record<string, string> | null;
    canEdit: boolean;
};

export type TimeOff = { employeeId: string | null; from: string; to: string; status: string };

const ROW_HEIGHT = 72;
const OPEN = 'open';

type CellTarget = { employeeId: string | null; date: string };

/**
 * The planner grid (handoff 22): people × days, name column sticky at the reading start, day
 * columns in reading order. Rows are virtualised (architecture: 200+ people); shifts drag
 * between cells with a mouse, touch or keyboard (dnd-kit). Drag lift tilts −1.5° in Arabic.
 */
export function RosterGrid({
    days,
    today,
    weekend,
    people,
    shifts,
    timeOff,
    holidays,
    coverage,
    canPlanBranch,
    onAdd,
    onEdit,
    onMove,
}: {
    days: string[];
    today: string;
    weekend: number[];
    people: Person[];
    shifts: RosterShift[];
    timeOff: TimeOff[];
    holidays: Array<{ date: string; name: Record<string, string> }>;
    coverage: Record<string, number>;
    canPlanBranch: boolean;
    onAdd: (target: CellTarget) => void;
    onEdit: (shift: RosterShift) => void;
    onMove: (shift: RosterShift, target: CellTarget) => void;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const scroller = useRef<HTMLDivElement>(null);
    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 6 } }),
        useSensor(KeyboardSensor),
    );
    const byCell = new Map<string, RosterShift[]>();

    for (const shift of shifts) {
        const key = `${shift.employeeId ?? OPEN}|${shift.date}`;
        byCell.set(key, [...(byCell.get(key) ?? []), shift]);
    }

    const off = new Map<string, string>();

    for (const leave of timeOff) {
        for (const day of days) {
            if (leave.employeeId && day >= leave.from && day <= leave.to) {
                off.set(`${leave.employeeId}|${day}`, leave.status);
            }
        }
    }

    const holidayOn = new Map(holidays.map((h) => [h.date, localized(h.name, locale)]));
    // TanStack Virtual returns fresh functions each render; the compiler skips memoising this component.
    // eslint-disable-next-line react-hooks/incompatible-library
    const virtualizer = useVirtualizer({
        count: people.length,
        getScrollElement: () => scroller.current,
        estimateSize: () => ROW_HEIGHT,
        overscan: 8,
    });
    const columns = {
        gridTemplateColumns: `13rem repeat(${days.length}, minmax(${days.length > 14 ? '6.5rem' : '8rem'}, 1fr))`,
    };
    const shiftsById = new Map(shifts.map((s) => [s.id, s]));

    const onDragEnd = (event: DragEndEvent) => {
        const shift = shiftsById.get(String(event.active.id));
        const over = event.over?.id ? String(event.over.id).split('|') : null;

        if (!shift || !over) return;

        const employeeId = over[0] === OPEN ? null : (over[0] ?? null);
        const date = over[1] ?? shift.date;

        if (employeeId !== shift.employeeId || date !== shift.date) {
            onMove(shift, { employeeId, date });
        }
    };

    const coverageCount = (date: string) =>
        shifts.filter((s) => s.date === date && s.employeeId !== null).length;

    return (
        <DndContext sensors={sensors} onDragEnd={onDragEnd}>
            <div
                ref={scroller}
                className="max-h-[70vh] overflow-auto rounded-lg border border-border bg-card"
                data-roster-grid
            >
                <div
                    className="min-w-max"
                    role="table"
                    aria-label={t('roster.title')}
                    aria-rowcount={people.length + 3}
                >
                    <div
                        role="row"
                        className="sticky top-0 z-20 grid border-b border-border bg-muted text-caption text-text-secondary"
                        style={columns}
                    >
                        <span
                            role="columnheader"
                            className="sticky start-0 z-10 bg-muted px-3 py-2 font-medium"
                        >
                            {t('roster.people')}
                        </span>
                        {days.map((d) => (
                            <span
                                role="columnheader"
                                key={d}
                                className={cn(
                                    'flex flex-col items-center px-1 py-2',
                                    d === today &&
                                        'border-b-2 border-primary font-semibold text-foreground',
                                    holidayOn.has(d) && 'bg-info-bg text-info',
                                )}
                                title={holidayOn.get(d)}
                            >
                                <span>
                                    {formatDate(parseDay(d), locale, {
                                        weekday: 'short',
                                        timeZone: 'UTC',
                                    })}
                                </span>
                                <span className="tabular-nums">
                                    {formatNumber(parseDay(d).getUTCDate(), locale)}
                                </span>
                                <span className="sr-only">
                                    {dayLabel(d, locale, { day: 'numeric', month: 'long' })}
                                </span>
                            </span>
                        ))}
                    </div>

                    <Row
                        label={t('roster.openRow')}
                        sub={null}
                        rowKey={OPEN}
                        days={days}
                        columns={columns}
                        byCell={byCell}
                        off={off}
                        weekend={weekend}
                        holidayOn={holidayOn}
                        canEdit={canPlanBranch}
                        onAdd={onAdd}
                        onEdit={onEdit}
                        className="border-b-2 border-dashed"
                    />

                    <div
                        role="rowgroup"
                        style={{ height: virtualizer.getTotalSize(), position: 'relative' }}
                    >
                        {virtualizer.getVirtualItems().map((item) => {
                            const person = people[item.index]!;

                            return (
                                <div
                                    key={person.id}
                                    style={{
                                        position: 'absolute',
                                        top: 0,
                                        insetInlineStart: 0,
                                        width: '100%',
                                        transform: `translateY(${item.start}px)`,
                                    }}
                                >
                                    <Row
                                        label={
                                            person.preferredName ??
                                            (locale === 'ar' && person.nameAr
                                                ? person.nameAr
                                                : person.nameEn)
                                        }
                                        sub={
                                            person.position
                                                ? localized(person.position, locale)
                                                : person.number
                                        }
                                        rowKey={person.id}
                                        days={days}
                                        columns={columns}
                                        byCell={byCell}
                                        off={off}
                                        weekend={weekend}
                                        holidayOn={holidayOn}
                                        canEdit={person.canEdit}
                                        onAdd={onAdd}
                                        onEdit={onEdit}
                                    />
                                </div>
                            );
                        })}
                    </div>

                    <div
                        role="row"
                        className="sticky bottom-0 z-20 grid border-t border-border bg-muted text-caption"
                        style={columns}
                        data-coverage-row
                    >
                        <span
                            role="rowheader"
                            className="sticky start-0 z-10 bg-muted px-3 py-2 font-medium"
                        >
                            {t('roster.coverageRow')}
                        </span>
                        {days.map((d) => {
                            const required = coverage[String(weekdayIso(d))] ?? 0;
                            const count = coverageCount(d);
                            const short = required > 0 && count < required;

                            return (
                                <span
                                    role="cell"
                                    key={d}
                                    className={cn(
                                        'flex items-center justify-center px-1 py-2 tabular-nums',
                                        required > 0 &&
                                            (short
                                                ? 'bg-danger-bg font-medium text-danger'
                                                : 'bg-success-bg text-success'),
                                    )}
                                >
                                    {required > 0
                                        ? t('roster.coverage', {
                                              count: formatNumber(count, locale),
                                              required: formatNumber(required, locale),
                                          })
                                        : t('roster.coverageNone', {
                                              count: formatNumber(count, locale),
                                          })}
                                </span>
                            );
                        })}
                    </div>
                </div>
            </div>
        </DndContext>
    );
}

const Row = memo(function Row({
    label,
    sub,
    rowKey,
    days,
    columns,
    byCell,
    off,
    weekend,
    holidayOn,
    canEdit,
    onAdd,
    onEdit,
    className,
}: {
    label: string;
    sub: string | null;
    rowKey: string;
    days: string[];
    columns: React.CSSProperties;
    byCell: Map<string, RosterShift[]>;
    off: Map<string, string>;
    weekend: number[];
    holidayOn: Map<string, string>;
    canEdit: boolean;
    onAdd: (target: CellTarget) => void;
    onEdit: (shift: RosterShift) => void;
    className?: string;
}) {
    return (
        <div
            role="row"
            className={cn('grid border-b border-border', className)}
            style={{ ...columns, minHeight: ROW_HEIGHT }}
            data-roster-row={rowKey}
        >
            <span
                role="rowheader"
                className="sticky start-0 z-10 flex min-w-0 flex-col justify-center border-e border-border bg-card px-3 py-2"
            >
                <span className="truncate text-small font-medium">{label}</span>
                {sub ? (
                    <span className="truncate text-caption text-muted-foreground">{sub}</span>
                ) : null}
            </span>
            {days.map((date) => (
                <Cell
                    key={date}
                    rowKey={rowKey}
                    date={date}
                    label={label}
                    shifts={byCell.get(`${rowKey}|${date}`) ?? []}
                    offStatus={off.get(`${rowKey}|${date}`) ?? null}
                    weekend={weekend.includes(weekdayIso(date))}
                    holiday={holidayOn.has(date)}
                    canEdit={canEdit}
                    onAdd={onAdd}
                    onEdit={onEdit}
                />
            ))}
        </div>
    );
});

function Cell({
    rowKey,
    date,
    label,
    shifts,
    offStatus,
    weekend,
    holiday,
    canEdit,
    onAdd,
    onEdit,
}: {
    rowKey: string;
    date: string;
    label: string;
    shifts: RosterShift[];
    offStatus: string | null;
    weekend: boolean;
    holiday: boolean;
    canEdit: boolean;
    onAdd: (target: CellTarget) => void;
    onEdit: (shift: RosterShift) => void;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const { setNodeRef, isOver } = useDroppable({ id: `${rowKey}|${date}`, disabled: !canEdit });
    const target = { employeeId: rowKey === OPEN ? null : rowKey, date };

    return (
        <div
            role="cell"
            ref={setNodeRef}
            data-cell={`${rowKey}|${date}`}
            className={cn(
                'group relative flex flex-col gap-1 border-e border-border p-1 last:border-e-0',
                weekend && 'bg-muted/60',
                holiday && 'bg-info-bg/60',
                offStatus === 'approved' && 'bg-surface-sunken',
                offStatus === 'pending' && 'bg-status-pending-bg/60',
                isOver && 'ring-2 ring-primary ring-inset',
            )}
        >
            {offStatus ? (
                <span className="truncate text-caption text-text-secondary">
                    {offStatus === 'pending' ? t('roster.timeOffPending') : t('roster.timeOff')}
                </span>
            ) : null}
            {shifts.map((shift) => (
                <ShiftBlock key={shift.id} shift={shift} canEdit={canEdit} onEdit={onEdit} />
            ))}
            {canEdit ? (
                <button
                    type="button"
                    onClick={() => onAdd(target)}
                    aria-label={
                        rowKey === OPEN
                            ? t('roster.addOpen', { date: dayLabel(date, locale) })
                            : t('roster.addShift', { name: label, date: dayLabel(date, locale) })
                    }
                    className="flex min-h-6 flex-1 items-center justify-center rounded-sm text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <Plus aria-hidden className="size-4" />
                </button>
            ) : null}
        </div>
    );
}

function ShiftBlock({
    shift,
    canEdit,
    onEdit,
}: {
    shift: RosterShift;
    canEdit: boolean;
    onEdit: (shift: RosterShift) => void;
}) {
    const { t } = useTranslation('schedule');
    const { locale } = useLocale();
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: shift.id,
        disabled: !canEdit,
    });
    const lift = dragLift(locale === 'ar' ? 'rtl' : 'ltr', useReducedMotion() ?? false);
    const conflicts = shift.conflicts.map((c) => t(`roster.conflicts.${c}`));
    const draft = shift.status === 'draft';

    return (
        <button
            type="button"
            ref={setNodeRef}
            {...attributes}
            {...listeners}
            onClick={() => canEdit && onEdit(shift)}
            data-shift={shift.id}
            data-status={shift.status}
            aria-label={[
                `${shift.startsAt}–${shift.endsAt}`,
                draft ? t('roster.draft') : null,
                shift.employeeId === null && shift.claims > 0
                    ? t('roster.claims', { count: shift.claims })
                    : null,
                ...conflicts,
            ]
                .filter(Boolean)
                .join(', ')}
            style={
                transform
                    ? {
                          transform: `translate3d(${transform.x}px, ${transform.y}px, 0) rotate(${lift.rotate}deg) scale(${lift.scale})`,
                          zIndex: 30,
                      }
                    : undefined
            }
            className={cn(
                'flex w-full min-w-0 flex-col items-start rounded-sm border-s-4 px-1.5 py-1 text-start text-caption transition-shadow duration-150 ease-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none motion-reduce:transition-none',
                shiftColor(shift.color).block,
                draft && 'border-dashed outline-1 outline-border outline-dashed',
                conflicts.length > 0 && 'ring-1 ring-danger',
                isDragging && 'shadow-lg',
                !canEdit && 'cursor-default',
            )}
        >
            <span className="flex w-full items-center gap-1">
                <ShiftTime
                    startsAt={shift.startsAt}
                    endsAt={shift.endsAt}
                    className="font-medium"
                />
                {conflicts.length > 0 ? (
                    <AlertTriangle aria-hidden className="ms-auto size-3.5 shrink-0 text-danger" />
                ) : null}
            </span>
            {shift.employeeId === null && shift.claims > 0 ? (
                <span className="text-text-secondary">
                    {t('roster.claims', { count: shift.claims })}
                </span>
            ) : null}
            {conflicts.length > 0 ? (
                <span aria-hidden className="line-clamp-2 text-danger">
                    {conflicts.join(' · ')}
                </span>
            ) : null}
        </button>
    );
}
