import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { useLocale } from '@/hooks/use-locale';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import { BalanceRing } from './balance-ring';
import { amountLabel, dayLabel, type LeaveBalance } from './format';

/** What a balance is "out of": everything granted this year (taken days included). */
export function grantedOf(balance: LeaveBalance): number {
    return Math.max(balance.balance + balance.taken, balance.entitlement, balance.available, 0);
}

/** Balance cards (handoff 4 + My leave): ring, available days, holds and expiring carry-over. */
export function BalanceCards({
    balances,
    onStatement,
    compact = false,
    className,
}: {
    balances: LeaveBalance[];
    onStatement?: (balance: LeaveBalance) => void;
    compact?: boolean;
    className?: string;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();

    return (
        <ul
            className={cn(
                'grid gap-3 sm:grid-cols-2 xl:grid-cols-3',
                compact && 'sm:grid-cols-3 lg:grid-cols-1 xl:grid-cols-1',
                className,
            )}
        >
            {balances.map((balance) => {
                const total = grantedOf(balance);
                const available = formatNumber(balance.available, locale, {
                    maximumFractionDigits: 2,
                });

                return (
                    <li
                        key={balance.typeId}
                        data-leave-type={balance.code}
                        className="flex flex-col gap-3 rounded-lg border border-border bg-card p-4"
                    >
                        <div className="flex items-center gap-3">
                            <div className="relative">
                                <BalanceRing value={Math.max(0, balance.available)} total={total} />
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col">
                                <span className="truncate text-small text-text-secondary">
                                    {balance.name}
                                </span>
                                <span className="text-h3 tabular-nums">
                                    {available}
                                    <span className="ms-1 text-small font-normal text-text-secondary">
                                        {t('balances.available')}
                                    </span>
                                </span>
                                <span className="sr-only">
                                    {t('balances.ringLabel', {
                                        available: amountLabel(
                                            t,
                                            balance.available,
                                            balance.unit,
                                            locale,
                                        ),
                                        total: amountLabel(t, total, balance.unit, locale),
                                    })}
                                </span>
                                <span
                                    aria-hidden
                                    className="text-caption text-muted-foreground tabular-nums"
                                >
                                    {t('balances.ofTotal', {
                                        total: amountLabel(t, total, balance.unit, locale),
                                    })}
                                </span>
                            </div>
                        </div>
                        {!compact && (balance.reserved > 0 || balance.expiring) ? (
                            <ul className="flex flex-col gap-1 text-caption text-text-secondary">
                                {balance.reserved > 0 ? (
                                    <li>
                                        {t('balances.reserved', {
                                            amount: amountLabel(
                                                t,
                                                balance.reserved,
                                                balance.unit,
                                                locale,
                                            ),
                                        })}
                                    </li>
                                ) : null}
                                {balance.expiring && balance.expiresOn ? (
                                    <li className="text-warning">
                                        {t('balances.expiring', {
                                            amount: amountLabel(
                                                t,
                                                balance.expiring,
                                                balance.unit,
                                                locale,
                                            ),
                                            date: dayLabel(balance.expiresOn, locale),
                                        })}
                                    </li>
                                ) : null}
                            </ul>
                        ) : null}
                        {onStatement ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="min-h-11 self-start"
                                onClick={() => onStatement(balance)}
                            >
                                {t('balances.statement')}
                                <span className="sr-only">: {balance.name}</span>
                            </Button>
                        ) : null}
                    </li>
                );
            })}
        </ul>
    );
}
