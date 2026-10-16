import { Head, Link, router } from '@inertiajs/react';
import { Download, Monitor } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { StatusBadge } from '@/components/mw/status-badge';
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
import { DayStatusBadge } from '@/features/attendance/day-status';
import { dayLabel } from '@/features/leave/format';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import attendance from '@/routes/attendance';

type Day = App.Domain.Attendance.Data.AttendanceDayData;
type Punch = App.Domain.Attendance.Data.PunchData;
type Props = {
    date: string;
    today: string;
    branch: { id: string; name: Record<string, string>; timezone: string };
    branches: Array<{ id: string; name: Record<string, string> }>;
    days: Day[];
    canCorrect: string[];
    detail: { day: string; punches: Punch[] } | null;
    can: { export: boolean; configure: boolean };
};

const COLUMNS = ['present', 'late', 'absent', 'on_leave'] as const;
type Column = (typeof COLUMNS)[number];

/** Which board column a day sits in (absent also holds "not in yet" and missing check-outs). */
function columnOf(status: string): Column | null {
    if (status === 'present' || status === 'late' || status === 'on_leave') return status;
    if (status === 'absent' || status === 'incomplete' || status === 'scheduled') return 'absent';

    return null;
}

/**
 * Daily attendance board (handoff 26): columns for present / late / absent / on leave with
 * counts on desktop (tabs on phones), everyone in a table, and a correction drawer whose reason
 * goes to the audit log.
 */
export default function Board({
    date,
    today,
    branch,
    branches,
    days,
    canCorrect,
    detail,
    can,
}: Props) {
    const { t } = useTranslation('attendance');
    const { locale, dir } = useLocale();
    const [month, setMonth] = useState(date.slice(0, 7));
    const visit = (query: Record<string, string>) =>
        router.get(
            attendance.board.url({ query: { date, branch: branch.id, ...query } }),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const time = (iso: string | null) =>
        iso
            ? formatDate(iso, locale, {
                  hour: '2-digit',
                  minute: '2-digit',
                  timeZone: branch.timezone,
              })
            : '—';
    const name = (d: Day) => (locale === 'ar' && d.nameAr ? d.nameAr : d.nameEn);
    const minutes = (n: number) =>
        n > 0 ? t('board.minutes', { count: formatNumber(n, locale) }) : '—';
    const grouped = Object.fromEntries(
        COLUMNS.map((c) => [c, days.filter((d) => columnOf(d.status) === c)]),
    ) as Record<Column, Day[]>;
    const selected = detail ? days.find((d) => d.id === detail.day) : undefined;
    const open = (d: Day) =>
        router.get(
            attendance.board.url({ query: { date, branch: branch.id, day: d.id } }),
            {},
            { preserveScroll: true, preserveState: true, only: ['detail'] },
        );

    const card = (d: Day) => (
        <li key={d.id}>
            <button
                type="button"
                onClick={() => open(d)}
                data-board-day={d.employeeId}
                className="flex min-h-14 w-full flex-col items-start gap-0.5 rounded-md border border-border bg-card p-3 text-start hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <span className="font-medium">{name(d)}</span>
                <span className="text-caption text-text-secondary">
                    {d.firstIn ? time(d.firstIn) : t(`status.${d.status}`)}
                    {d.lateMinutes > 0 ? ` · ${minutes(d.lateMinutes)}` : ''}
                    {d.flaggedPunches ? ` · ${t('board.flags.flagged')}` : ''}
                </span>
            </button>
        </li>
    );

    return (
        <>
            <Head title={t('board.title')} />
            <PageHeader
                title={t('board.title')}
                description={t('board.description')}
                actions={
                    can.configure ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={attendance.kiosks.index.url()}>
                                <Monitor aria-hidden className="size-4" />
                                {t('clock.kiosks')}
                            </Link>
                        </Button>
                    ) : undefined
                }
            />

            <div className="mb-4 flex flex-wrap items-end gap-3">
                <Field id="board-date" label={t('board.date')}>
                    <Input
                        id="board-date"
                        type="date"
                        max={today}
                        value={date}
                        onChange={(e) => e.target.value && visit({ date: e.target.value })}
                        className="w-44"
                    />
                </Field>
                <Field id="board-branch" label={t('board.branch')}>
                    <Select value={branch.id} onValueChange={(id) => visit({ branch: id })}>
                        <SelectTrigger id="board-branch" className="min-h-11 w-56">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {branches.map((b) => (
                                <SelectItem key={b.id} value={b.id}>
                                    {localized(b.name, locale)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                {can.export ? (
                    <div className="ms-auto flex items-end gap-2">
                        <Field id="board-month" label={t('board.exportMonth')}>
                            <Input
                                id="board-month"
                                type="month"
                                value={month}
                                onChange={(e) => setMonth(e.target.value)}
                                className="w-40"
                            />
                        </Field>
                        <Button asChild variant="outline" className="min-h-11">
                            <a
                                href={attendance.export.url({
                                    query: { month, branch: branch.id },
                                })}
                                download
                            >
                                <Download aria-hidden className="size-4" />
                                {t('board.export')}
                            </a>
                        </Button>
                    </div>
                ) : null}
            </div>

            {days.length === 0 ? (
                <PageState
                    kind="empty"
                    illustration="empty-employees"
                    title={t('board.noPeople')}
                />
            ) : (
                <>
                    <div className="mb-6 hidden gap-3 md:grid md:grid-cols-4" data-board-columns>
                        {COLUMNS.map((c) => (
                            <section
                                key={c}
                                aria-labelledby={`col-${c}`}
                                className="flex flex-col gap-2 rounded-lg bg-muted p-3"
                            >
                                <h2
                                    id={`col-${c}`}
                                    className="flex items-center justify-between text-small font-semibold"
                                >
                                    {t(`board.columns.${c}`)}
                                    <span className="rounded-full bg-card px-2 text-caption tabular-nums">
                                        {formatNumber(grouped[c].length, locale)}
                                    </span>
                                </h2>
                                {grouped[c].length === 0 ? (
                                    <p className="text-caption text-muted-foreground">
                                        {t('board.empty')}
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-2">{grouped[c].map(card)}</ul>
                                )}
                            </section>
                        ))}
                    </div>

                    <Tabs defaultValue="present" className="mb-6 md:hidden" dir={dir}>
                        <TabsList className="w-full">
                            {COLUMNS.map((c) => (
                                <TabsTrigger key={c} value={c} className="min-h-11 flex-1">
                                    {t(`board.columns.${c}`)}{' '}
                                    {formatNumber(grouped[c].length, locale)}
                                </TabsTrigger>
                            ))}
                        </TabsList>
                        {COLUMNS.map((c) => (
                            <TabsContent key={c} value={c}>
                                {grouped[c].length === 0 ? (
                                    <p className="p-3 text-small text-muted-foreground">
                                        {t('board.empty')}
                                    </p>
                                ) : (
                                    <ul className="flex flex-col gap-2">{grouped[c].map(card)}</ul>
                                )}
                            </TabsContent>
                        ))}
                    </Tabs>

                    <section aria-labelledby="board-table" className="hidden md:block">
                        <h2 id="board-table" className="mb-2 text-h4">
                            {t('board.table')}
                        </h2>
                        <div className="overflow-x-auto rounded-lg border border-border bg-card">
                            <table className="w-full text-small">
                                <thead>
                                    <tr className="border-b border-border text-caption text-muted-foreground">
                                        {[
                                            'name',
                                            'status',
                                            'scheduled',
                                            'in',
                                            'out',
                                            'worked',
                                            'late',
                                            'overtime',
                                        ].map((h) => (
                                            <th
                                                key={h}
                                                scope="col"
                                                className="px-3 py-2 text-start font-medium"
                                            >
                                                {t(`board.${h}`)}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {days.map((d) => (
                                        <tr
                                            key={d.id}
                                            className="border-b border-border last:border-0"
                                        >
                                            <td className="px-3 py-2">
                                                <button
                                                    type="button"
                                                    className="text-start font-medium text-link hover:underline"
                                                    onClick={() => open(d)}
                                                >
                                                    {name(d)}
                                                </button>
                                            </td>
                                            <td className="px-3 py-2">
                                                <span className="flex flex-wrap items-center gap-1">
                                                    <DayStatusBadge status={d.status} />
                                                    {d.corrected ? (
                                                        <span className="text-caption text-muted-foreground">
                                                            {t('board.corrected')}
                                                        </span>
                                                    ) : null}
                                                    {d.flaggedPunches ? (
                                                        <StatusBadge
                                                            status="escalated"
                                                            label={t('board.flags.flagged')}
                                                        />
                                                    ) : null}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {d.scheduledStart
                                                    ? `${time(d.scheduledStart)}–${time(d.scheduledEnd)}`
                                                    : '—'}
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {time(d.firstIn)}
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {time(d.lastOut)}
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {minutes(d.workedMinutes)}
                                            </td>
                                            <td
                                                className={cn(
                                                    'px-3 py-2 tabular-nums',
                                                    d.lateMinutes > 0 && 'text-warning',
                                                )}
                                            >
                                                {minutes(d.lateMinutes)}
                                            </td>
                                            <td className="px-3 py-2 tabular-nums">
                                                {minutes(d.overtimeMinutes)}
                                                {d.overtimeMinutes > 0 &&
                                                d.overtimeStatus !== 'none' ? (
                                                    <span className="block text-caption text-muted-foreground">
                                                        {t(
                                                            `board.overtimeStatus.${d.overtimeStatus}`,
                                                        )}
                                                    </span>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </>
            )}

            {selected && detail ? (
                <CorrectionDrawer
                    key={selected.id}
                    day={selected}
                    punches={detail.punches}
                    timezone={branch.timezone}
                    canCorrect={canCorrect.includes(selected.employeeId)}
                    onClose={() =>
                        router.get(
                            attendance.board.url({ query: { date, branch: branch.id } }),
                            {},
                            { preserveScroll: true, preserveState: true, only: ['detail'] },
                        )
                    }
                />
            ) : null}
        </>
    );
}

function CorrectionDrawer({
    day,
    punches,
    timezone,
    canCorrect,
    onClose,
}: {
    day: Day;
    punches: Punch[];
    timezone: string;
    canCorrect: boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation('attendance');
    const { locale } = useLocale();
    const toLocal = (iso: string | null) =>
        iso
            ? formatDate(iso, 'en', {
                  hour: '2-digit',
                  minute: '2-digit',
                  hourCycle: 'h23',
                  timeZone: timezone,
              })
            : '';
    const [checkIn, setCheckIn] = useState(toLocal(day.firstIn));
    const [checkOut, setCheckOut] = useState(toLocal(day.lastOut));
    const [reason, setReason] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);
    const time = (iso: string) =>
        formatDate(iso, locale, { hour: '2-digit', minute: '2-digit', timeZone: timezone });

    return (
        <ResponsiveDialog
            open
            onOpenChange={(next) => !next && onClose()}
            title={t('correct.title', {
                name: locale === 'ar' && day.nameAr ? day.nameAr : day.nameEn,
                date: dayLabel(day.date, locale),
            })}
            footer={
                canCorrect ? (
                    <Button
                        type="button"
                        loading={busy}
                        disabled={reason.trim() === '' || (checkIn === '' && checkOut === '')}
                        data-save-correction
                        onClick={() =>
                            router.post(
                                attendance.correct.url(day.employeeId),
                                {
                                    date: day.date,
                                    checkIn: checkIn || null,
                                    checkOut: checkOut || null,
                                    reason,
                                },
                                {
                                    preserveScroll: true,
                                    onStart: () => setBusy(true),
                                    onFinish: () => setBusy(false),
                                    onError: setErrors,
                                    onSuccess: onClose,
                                },
                            )
                        }
                    >
                        {t('correct.save')}
                    </Button>
                ) : undefined
            }
        >
            <div className="flex flex-col gap-4">
                <section aria-labelledby="correct-punches" className="flex flex-col gap-2">
                    <h3 id="correct-punches" className="text-small font-semibold">
                        {t('correct.punches')}
                    </h3>
                    {punches.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('correct.noPunches')}</p>
                    ) : (
                        <ul className="flex flex-col gap-1 text-small">
                            {punches.map((p) => (
                                <li
                                    key={p.id}
                                    className={cn(
                                        'flex flex-wrap items-center gap-2',
                                        p.voided && 'text-muted-foreground line-through',
                                    )}
                                >
                                    <span className="w-24 font-medium">{t(`clock.${p.kind}`)}</span>
                                    <span className="tabular-nums">{time(p.occurredAt)}</span>
                                    <span className="text-caption text-text-secondary">
                                        {t(`clock.sources.${p.source}`)}
                                    </span>
                                    {p.flagged ? (
                                        <StatusBadge
                                            status="escalated"
                                            label={t('clock.flagged')}
                                        />
                                    ) : null}
                                    {p.reason ? (
                                        <span className="w-full text-caption text-text-secondary">
                                            {p.reason}
                                        </span>
                                    ) : null}
                                    {p.voided ? (
                                        <span className="sr-only">{t('correct.voided')}</span>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                {canCorrect ? (
                    <section
                        aria-labelledby="correct-form"
                        className="flex flex-col gap-3 border-t border-border pt-4"
                    >
                        <h3 id="correct-form" className="text-small font-semibold">
                            {t('correct.form')}
                        </h3>
                        {errors.error ? (
                            <p
                                role="alert"
                                className="rounded-md bg-danger-bg p-3 text-small text-danger"
                            >
                                {errors.error}
                            </p>
                        ) : null}
                        <div className="grid grid-cols-2 gap-3">
                            <Field
                                id="correct-in"
                                label={t('correct.checkIn')}
                                error={errors.checkIn}
                            >
                                <Input
                                    id="correct-in"
                                    type="time"
                                    dir="ltr"
                                    value={checkIn}
                                    onChange={(e) => setCheckIn(e.target.value)}
                                />
                            </Field>
                            <Field
                                id="correct-out"
                                label={t('correct.checkOut')}
                                error={errors.checkOut}
                            >
                                <Input
                                    id="correct-out"
                                    type="time"
                                    dir="ltr"
                                    value={checkOut}
                                    onChange={(e) => setCheckOut(e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field
                            id="correct-reason"
                            label={t('correct.reason')}
                            hint={t('correct.reasonHint')}
                            error={errors.reason}
                            required
                        >
                            <Textarea
                                id="correct-reason"
                                value={reason}
                                maxLength={300}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </Field>
                    </section>
                ) : (
                    <p className="text-small text-muted-foreground">{t('correct.readOnly')}</p>
                )}
            </div>
        </ResponsiveDialog>
    );
}
