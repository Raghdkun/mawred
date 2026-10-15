import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Facts } from '@/components/mw/facts';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { api } from '@/lib/api';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { amountLabel, dayLabel, type LedgerEntry, type LeaveBalance, signed } from './format';

/** A balance's statement: the summary, then every ledger line with a running balance. */
export function StatementDialog({
    employeeId,
    balance,
    onClose,
}: {
    employeeId: string;
    balance: LeaveBalance | null;
    onClose: () => void;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const query = useQuery({
        queryKey: ['leave-statement', employeeId, balance?.typeId, balance?.year],
        queryFn: ({ signal }) =>
            api<{ data: LedgerEntry[] }>(
                `employees/${employeeId}/leave/${balance?.typeId}/statement`,
                {
                    params: { year: balance?.year },
                    signal,
                },
            ),
        enabled: balance !== null,
    });
    const amount = (value: number) => (balance ? amountLabel(t, value, balance.unit, locale) : '');

    return (
        <ResponsiveDialog
            open={balance !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                balance
                    ? t('statement.title', {
                          type: balance.name,
                          year: formatNumber(balance.year, locale, { useGrouping: false }),
                      })
                    : ''
            }
            description={t('statement.description')}
            className="sm:max-w-2xl"
        >
            {balance ? (
                <div className="flex flex-col gap-5">
                    <Facts
                        className="sm:grid-cols-3"
                        items={[
                            [t('statement.summary.earned'), amount(balance.earned)],
                            [t('statement.summary.carriedOver'), amount(balance.carriedOver)],
                            [t('statement.summary.adjusted'), amount(balance.adjusted)],
                            [t('statement.summary.taken'), amount(balance.taken)],
                            [t('statement.summary.reserved'), amount(balance.reserved)],
                            [
                                t('statement.summary.available'),
                                <strong key="a">{amount(balance.available)}</strong>,
                            ],
                        ]}
                    />
                    {query.isPending ? (
                        <div aria-busy className="flex flex-col gap-2">
                            <Skeleton className="h-10 w-full" />
                            <Skeleton className="h-10 w-full" />
                        </div>
                    ) : query.isError ? (
                        <PageState kind="error" className="py-4" />
                    ) : query.data.data.length === 0 ? (
                        <p className="text-small text-muted-foreground">{t('statement.empty')}</p>
                    ) : (
                        <table className="w-full text-small">
                            <thead>
                                <tr className="border-b border-border text-start text-caption text-muted-foreground">
                                    <th scope="col" className="py-2 pe-3 text-start font-medium">
                                        {t('statement.date')}
                                    </th>
                                    <th scope="col" className="py-2 pe-3 text-start font-medium">
                                        {t('statement.what')}
                                    </th>
                                    <th scope="col" className="py-2 pe-3 text-end font-medium">
                                        {t('statement.change')}
                                    </th>
                                    <th scope="col" className="py-2 text-end font-medium">
                                        {t('statement.balance')}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {query.data.data.map((entry) => (
                                    <tr
                                        key={entry.id}
                                        className="border-b border-border align-top last:border-0"
                                    >
                                        <td className="py-2 pe-3 whitespace-nowrap">
                                            {dayLabel(entry.effectiveOn, locale, {
                                                day: 'numeric',
                                                month: 'short',
                                            })}
                                        </td>
                                        <td className="py-2 pe-3">
                                            <span className="block">{entry.label}</span>
                                            {entry.note || entry.requestNumber || entry.actor ? (
                                                <span className="block text-caption text-muted-foreground">
                                                    {[
                                                        entry.requestNumber ? (
                                                            <bdi key="n">{entry.requestNumber}</bdi>
                                                        ) : null,
                                                        entry.note,
                                                        entry.actor
                                                            ? t('statement.by', {
                                                                  name: entry.actor,
                                                              })
                                                            : null,
                                                    ]
                                                        .filter(Boolean)
                                                        .map((part, index) => (
                                                            <span key={index}>
                                                                {index > 0 ? ' · ' : ''}
                                                                {part}
                                                            </span>
                                                        ))}
                                                </span>
                                            ) : null}
                                        </td>
                                        <td
                                            className={cn(
                                                'py-2 pe-3 text-end tabular-nums',
                                                entry.amount < 0 && 'text-danger',
                                            )}
                                        >
                                            <bdi>{signed(entry.amount, locale)}</bdi>
                                        </td>
                                        <td className="py-2 text-end tabular-nums">
                                            {formatNumber(entry.runningBalance, locale, {
                                                maximumFractionDigits: 2,
                                            })}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            ) : null}
        </ResponsiveDialog>
    );
}
