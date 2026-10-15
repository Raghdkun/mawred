import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { PageState } from '@/components/mw/page-state';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import { AdjustDialog } from './adjust-dialog';
import { BalanceCards } from './balance-cards';
import { type LeaveBalance, type LeaveEvent } from './format';
import { StatementDialog } from './statement-dialog';
import { TimeAwayList } from './time-away-list';

type BalancesResponse = { data: LeaveBalance[]; meta: { year: number; canAdjust: boolean } };

/**
 * Balances, statements and time away for one person and leave year (My leave and the profile
 * Leave tab). Reads /api/v1, so the mobile app gets the same numbers.
 */
export function LeavePanel({
    employeeId,
    initialYear,
}: {
    employeeId: string;
    initialYear: number;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const client = useQueryClient();
    const [year, setYear] = useState(initialYear);
    const [statement, setStatement] = useState<LeaveBalance | null>(null);
    const [adjusting, setAdjusting] = useState(false);

    const balances = useQuery({
        queryKey: ['leave-balances', employeeId, year],
        queryFn: ({ signal }) =>
            api<BalancesResponse>(`employees/${employeeId}/leave`, { params: { year }, signal }),
    });
    const events = useQuery({
        queryKey: ['leave-events', employeeId, year],
        queryFn: ({ signal }) =>
            api<{ data: LeaveEvent[] }>(`employees/${employeeId}/leave/requests`, {
                params: { year },
                signal,
            }),
    });
    const refresh = () => {
        void client.invalidateQueries({ queryKey: ['leave-balances', employeeId] });
        void client.invalidateQueries({ queryKey: ['leave-events', employeeId] });
        void client.invalidateQueries({ queryKey: ['leave-statement', employeeId] });
    };
    const canAdjust = balances.data?.meta.canAdjust ?? false;

    return (
        <div className="flex flex-col gap-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div role="group" aria-label={t('year.label')} className="flex items-center gap-1">
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11"
                        aria-label={t('year.previous')}
                        onClick={() => setYear(year - 1)}
                    >
                        <DirIcon>
                            <ChevronLeft aria-hidden className="size-4" />
                        </DirIcon>
                    </Button>
                    <span aria-live="polite" className="min-w-16 text-center text-h4 tabular-nums">
                        {formatNumber(year, locale, { useGrouping: false })}
                    </span>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-11"
                        aria-label={t('year.next')}
                        onClick={() => setYear(year + 1)}
                    >
                        <DirIcon>
                            <ChevronRight aria-hidden className="size-4" />
                        </DirIcon>
                    </Button>
                </div>
                {canAdjust ? (
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-11"
                        onClick={() => setAdjusting(true)}
                    >
                        {t('adjust.button')}
                    </Button>
                ) : null}
            </div>

            <section aria-labelledby="leave-balances" className="flex flex-col gap-3">
                <h2 id="leave-balances" className="text-h4">
                    {t('balances.title')}
                </h2>
                {balances.isPending ? (
                    <div aria-busy className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <Skeleton className="h-36" />
                        <Skeleton className="h-36" />
                        <Skeleton className="h-36" />
                    </div>
                ) : balances.isError ? (
                    <PageState kind="error" />
                ) : balances.data.data.length === 0 ? (
                    <p className="text-small text-muted-foreground">{t('balances.empty')}</p>
                ) : (
                    <BalanceCards balances={balances.data.data} onStatement={setStatement} />
                )}
            </section>

            <section aria-labelledby="leave-time-away" className="flex flex-col gap-3">
                <h2 id="leave-time-away" className="text-h4">
                    {t('timeAway.title')}
                </h2>
                {events.isPending ? (
                    <Skeleton className="h-24" />
                ) : events.isError ? (
                    <PageState kind="error" />
                ) : (
                    <TimeAwayList
                        events={events.data.data}
                        canCancel={canAdjust}
                        onChanged={refresh}
                    />
                )}
            </section>

            <StatementDialog
                employeeId={employeeId}
                balance={statement}
                onClose={() => setStatement(null)}
            />
            {canAdjust && balances.data ? (
                <AdjustDialog
                    open={adjusting}
                    onOpenChange={setAdjusting}
                    employeeId={employeeId}
                    year={year}
                    balances={balances.data.data}
                    onSaved={refresh}
                />
            ) : null}
        </div>
    );
}
