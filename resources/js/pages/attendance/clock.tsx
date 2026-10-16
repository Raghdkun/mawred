import { Head, Link, router } from '@inertiajs/react';
import { CloudOff, LayoutDashboard, MapPin, Monitor, QrCode, ScanLine } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { toast } from 'sonner';
import { Field } from '@/components/mw/field';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { StatusBadge } from '@/components/mw/status-badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { DayStatusBadge } from '@/features/attendance/day-status';
import {
    forgetPunch,
    isNetworkError,
    queuedPunches,
    queuePunch,
    type QueuedPunch,
} from '@/features/attendance/offline-queue';
import { PunchRipple } from '@/features/attendance/punch-ripple';
import { QrScan } from '@/features/attendance/qr-scan';
import { type Area, useLocation } from '@/features/attendance/use-location';
import { useLocale } from '@/hooks/use-locale';
import { ApiError, postJson } from '@/lib/api';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import attendanceRoutes from '@/routes/attendance';
import requests from '@/routes/requests';
import PunchesApi from '@/actions/App/Http/Controllers/Api/V1/Attendance/PunchesController';

type Punch = App.Domain.Attendance.Data.PunchData;
type Day = App.Domain.Attendance.Data.AttendanceDayData;
type Props = {
    employeeId: string | null;
    today: string;
    timezone: string;
    branch: { name: Record<string, string>; area: Area | null } | null;
    mode: 'block' | 'flag' | 'off';
    next: 'in' | 'out';
    punches: Punch[];
    day: Day | null;
    correctionTypeId: string | null;
    can: { board: boolean; configure: boolean };
};

/**
 * Check-in (handoff 25): one big button with the branch-area chip, kiosk QR scanning and an
 * offline queue that sends check-ins later with their tap time (D70). Mobile-first; the same
 * card is centred on desktop.
 */
export default function Clock({
    employeeId,
    timezone,
    branch,
    mode,
    next,
    punches,
    day,
    correctionTypeId,
    can,
}: Props) {
    const { t } = useTranslation('attendance');
    const { locale } = useLocale();
    const area = mode === 'off' ? null : (branch?.area ?? null);
    const { status, position } = useLocation(area, employeeId !== null);
    // After an offline punch the button flips locally until the server's answer catches up.
    const [local, setLocal] = useState<{ next: 'in' | 'out'; kind: 'in' | 'out' } | null>(null);
    const kind = local && local.next === next ? local.kind : next;
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [scanning, setScanning] = useState(false);
    const [queued, setQueued] = useState<QueuedPunch[]>([]);
    const [error, setError] = useState<string | null>(null);
    const [ripple, setRipple] = useState(0);
    const time = (iso: string) =>
        formatDate(iso, locale, { hour: '2-digit', minute: '2-digit', timeZone: timezone });
    const blocked = mode === 'block' && area !== null && status !== 'inside';
    const needsReason = mode === 'flag' && area !== null && status === 'outside';

    const send = useCallback(async (punch: QueuedPunch, offline: boolean) => {
        const { id: _id, tapped_at: tappedAt, ...rest } = punch;
        await postJson(PunchesApi.store.url(), {
            ...rest,
            ...(offline ? { tapped_at: tappedAt, sent_at: new Date().toISOString() } : {}),
        });
    }, []);

    const flush = useCallback(async () => {
        const waiting = await queuedPunches();

        for (const punch of waiting) {
            try {
                await send(punch, true);
                await forgetPunch(punch.id);
            } catch (e) {
                if (isNetworkError(e)) break;
                await forgetPunch(punch.id);
                toast.error(e instanceof ApiError ? e.message : t('common:states.error.title'));
            }
        }

        setQueued(await queuedPunches());
        router.reload({ only: ['punches', 'day', 'next'] });
    }, [send, t]);

    useEffect(() => {
        void queuedPunches().then((waiting) => {
            setQueued(waiting);
            if (waiting.length > 0 && navigator.onLine) void flush();
        });
        const online = () => void flush();
        window.addEventListener('online', online);

        return () => window.removeEventListener('online', online);
    }, [flush]);

    const punch = async (code?: string) => {
        setError(null);
        setBusy(true);
        const entry: QueuedPunch = {
            id: crypto.randomUUID(),
            kind,
            tapped_at: new Date().toISOString(),
            ...(position && !code
                ? {
                      latitude: position.latitude,
                      longitude: position.longitude,
                      accuracy: position.accuracy,
                  }
                : {}),
            ...(code ? { code } : {}),
            ...(reason.trim() ? { reason: reason.trim() } : {}),
        };

        try {
            await send(entry, false);
            toast.success(t(`clock.done.${kind}`, { time: time(new Date().toISOString()) }));
            setRipple((n) => n + 1);
            setReason('');
            router.reload({ only: ['punches', 'day', 'next'] });
        } catch (e) {
            if (isNetworkError(e)) {
                await queuePunch(entry);
                setQueued(await queuedPunches());
                setLocal({ next, kind: kind === 'in' ? 'out' : 'in' });
                toast.info(t('clock.queued'));
            } else {
                setError(e instanceof ApiError ? e.message : t('common:states.error.title'));
            }
        } finally {
            setBusy(false);
            setScanning(false);
        }
    };

    if (!employeeId) {
        return (
            <>
                <Head title={t('clock.title')} />
                <PageHeader title={t('clock.title')} />
                <PageState
                    kind="empty"
                    illustration="empty-schedule"
                    title={t('clock.title')}
                    description={t('clock.noEmployee')}
                />
            </>
        );
    }

    return (
        <>
            <Head title={t('clock.title')} />
            <div className="mx-auto flex w-full max-w-md flex-col gap-4">
                <PageHeader
                    title={t('clock.title')}
                    description={t('clock.description')}
                    className="mb-2"
                />

                {queued.length > 0 ? (
                    <p
                        role="status"
                        className="flex items-start gap-2 rounded-md border border-warning-border bg-warning-bg p-3 text-small text-warning"
                        data-offline-banner
                    >
                        <CloudOff aria-hidden className="mt-0.5 size-4 shrink-0" />
                        {t('clock.offline', { count: queued.length })}
                    </p>
                ) : null}

                <section
                    aria-labelledby="clock-today"
                    className="flex flex-col items-center gap-4 rounded-lg bg-card p-6 shadow-sm inset-shadow-highlight"
                >
                    <h2 id="clock-today" className="sr-only">
                        {t('clock.today')}
                    </h2>
                    <p className="text-small text-text-secondary">
                        {formatDate(new Date(), locale, {
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            timeZone: timezone,
                        })}
                    </p>
                    {day ? (
                        <div className="flex flex-wrap items-center justify-center gap-2 text-small">
                            <DayStatusBadge status={day.status} />
                            <span className="text-text-secondary">
                                {day.scheduledStart && day.scheduledEnd
                                    ? t('clock.scheduled', {
                                          from: time(day.scheduledStart),
                                          to: time(day.scheduledEnd),
                                      })
                                    : t('clock.unscheduled')}
                            </span>
                        </div>
                    ) : null}

                    {area ? (
                        <span
                            role="status"
                            data-area={status}
                            className={cn(
                                'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-caption font-medium',
                                status === 'inside' &&
                                    'border-success-border bg-success-bg text-success',
                                status === 'outside' &&
                                    'border-warning-border bg-warning-bg text-warning',
                                (status === 'checking' || status === 'unknown') &&
                                    'border-border bg-muted text-text-secondary',
                            )}
                        >
                            <MapPin aria-hidden className="size-3.5" />
                            {t(`clock.area.${status}`)}
                        </span>
                    ) : null}

                    <div className="relative">
                        <button
                            type="button"
                            onClick={() => void punch()}
                            disabled={busy || blocked || (needsReason && reason.trim() === '')}
                            data-punch={kind}
                            className={cn(
                                'relative flex size-44 flex-col items-center justify-center gap-1 overflow-hidden rounded-full text-h3 font-semibold shadow-lg transition-transform duration-150 ease-spring focus-visible:ring-4 focus-visible:ring-ring focus-visible:outline-none active:scale-95 disabled:cursor-not-allowed disabled:opacity-50 motion-reduce:transition-none',
                                kind === 'in'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-secondary text-secondary-foreground',
                            )}
                        >
                            {busy ? t('clock.sending') : t(`clock.${kind}`)}
                        </button>
                        {ripple > 0 ? <PunchRipple key={ripple} /> : null}
                    </div>

                    {blocked ? (
                        <p role="alert" className="text-center text-small text-warning">
                            {t('clock.area.blocked')}
                        </p>
                    ) : null}
                    {needsReason ? (
                        <Field
                            id="clock-reason"
                            label={t('clock.reason')}
                            hint={t('clock.reasonHint')}
                            className="w-full"
                            required
                        >
                            <Textarea
                                id="clock-reason"
                                value={reason}
                                maxLength={300}
                                onChange={(e) => setReason(e.target.value)}
                            />
                        </Field>
                    ) : null}
                    {error ? (
                        <p role="alert" className="text-center text-small text-danger">
                            {error}
                        </p>
                    ) : null}

                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setScanning(!scanning)}
                        aria-expanded={scanning}
                    >
                        {scanning ? (
                            <ScanLine aria-hidden className="size-4" />
                        ) : (
                            <QrCode aria-hidden className="size-4" />
                        )}
                        {scanning ? t('clock.stopScan') : t('clock.scan')}
                    </Button>
                    {scanning ? <QrScan onCode={(code) => void punch(code)} /> : null}
                </section>

                <section aria-labelledby="clock-recent" className="flex flex-col gap-2">
                    <h2 id="clock-recent" className="text-h4">
                        {t('clock.recent')}
                    </h2>
                    {punches.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('clock.noRecent')}</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border rounded-lg border border-border bg-card">
                            {punches.map((p) => (
                                <li
                                    key={p.id}
                                    className="flex items-center gap-3 p-3 text-small"
                                    data-recent-punch={p.kind}
                                >
                                    <span className="w-20 font-medium">{t(`clock.${p.kind}`)}</span>
                                    <span className="tabular-nums">{time(p.occurredAt)}</span>
                                    <span className="text-text-secondary">
                                        {formatDate(p.occurredAt, locale, {
                                            weekday: 'short',
                                            timeZone: timezone,
                                        })}
                                    </span>
                                    <span className="ms-auto text-caption text-muted-foreground">
                                        {t(`clock.sources.${p.source}`)}
                                    </span>
                                    {p.flagged ? (
                                        <StatusBadge
                                            status="escalated"
                                            label={t('clock.flagged')}
                                        />
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <nav className="flex flex-wrap gap-2">
                    {correctionTypeId ? (
                        <Button asChild variant="link" className="min-h-11">
                            <Link href={requests.create.url(correctionTypeId)}>
                                {t('clock.correction')}
                            </Link>
                        </Button>
                    ) : null}
                    {can.board ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={attendanceRoutes.board.url()}>
                                <LayoutDashboard aria-hidden className="size-4" />
                                {t('clock.board')}
                            </Link>
                        </Button>
                    ) : null}
                    {can.configure ? (
                        <Button asChild variant="outline" className="min-h-11">
                            <Link href={attendanceRoutes.kiosks.index.url()}>
                                <Monitor aria-hidden className="size-4" />
                                {t('clock.kiosks')}
                            </Link>
                        </Button>
                    ) : null}
                </nav>
            </div>
        </>
    );
}
