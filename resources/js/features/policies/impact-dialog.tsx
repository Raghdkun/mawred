import { useTranslation } from 'react-i18next';
import { PageState } from '@/components/mw/page-state';
import { ResponsiveDialog } from '@/components/mw/responsive-dialog';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import { type ImpactPreview, type PolicyKey } from './types';
import { keyLabel, summarize } from './values';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    preview: ImpactPreview | null;
    loading: boolean;
    failed: boolean;
    keys: PolicyKey[];
    onConfirm?: () => void;
    confirming?: boolean;
};

/**
 * Change-impact preview (handoff 20): a summary banner, then each affected employee with the
 * value before and after, and for leave rules the days earned in the year before, after and
 * the difference (negative deltas in danger text with a minus sign, never colour alone).
 */
export function ImpactDialog({
    open,
    onOpenChange,
    preview,
    loading,
    failed,
    keys,
    onConfirm,
    confirming,
}: Props) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const keyOf = (id: string) => keys.find((key) => key.id === id);

    return (
        <ResponsiveDialog
            open={open}
            onOpenChange={onOpenChange}
            title={t('impact.title')}
            className="sm:max-w-3xl"
            footer={
                <>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        {t('common:actions.cancel')}
                    </Button>
                    {onConfirm ? (
                        <Button
                            onClick={onConfirm}
                            loading={confirming}
                            disabled={loading || failed}
                        >
                            {t('impact.confirm')}
                        </Button>
                    ) : null}
                </>
            }
        >
            <div aria-live="polite" aria-busy={loading} className="flex flex-col gap-4">
                {loading ? (
                    <div className="flex flex-col gap-3">
                        <span className="sr-only">{t('impact.loading')}</span>
                        <Skeleton className="h-14 w-full" />
                        <Skeleton className="h-10 w-full" />
                        <Skeleton className="h-10 w-full" />
                    </div>
                ) : failed || !preview ? (
                    <PageState kind="error" title={t('impact.error')} className="py-6" />
                ) : (
                    <>
                        <div className="rounded-lg border border-info-border bg-info-bg p-4 text-info">
                            <p className="text-body font-semibold">
                                {t('impact.summary', {
                                    count: preview.affected,
                                    date: formatDate(preview.date, locale),
                                })}
                            </p>
                            <p className="text-small">
                                {t('impact.checked', {
                                    count: formatNumber(preview.checked, locale),
                                })}
                                {preview.affected > preview.rows.length
                                    ? ` ${t('impact.showing', { count: formatNumber(preview.rows.length, locale) })}`
                                    : ''}
                            </p>
                        </div>
                        {preview.affected === 0 ? (
                            <p className="text-small text-text-secondary">{t('impact.none')}</p>
                        ) : (
                            <ul className="flex flex-col divide-y divide-border rounded-lg border border-border">
                                {preview.rows.map((row) => (
                                    <li key={row.employeeId} className="flex flex-col gap-2 p-3">
                                        <span className="flex flex-wrap items-baseline gap-x-2">
                                            <span className="text-sm font-medium">
                                                {locale === 'ar' && row.nameAr
                                                    ? row.nameAr
                                                    : row.nameEn}
                                            </span>
                                            <bdi
                                                dir="ltr"
                                                className="text-caption text-muted-foreground"
                                            >
                                                {row.employeeNumber}
                                            </bdi>
                                        </span>
                                        <dl className="grid gap-x-4 gap-y-1 text-small md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
                                            {row.changes.map((change) => {
                                                const key = keyOf(change.key);
                                                const editor = key?.editor ?? '';

                                                return (
                                                    <div key={change.key} className="contents">
                                                        <dt className="text-text-secondary">
                                                            {key
                                                                ? keyLabel(key, t, locale)
                                                                : change.key}
                                                        </dt>
                                                        <dd className="text-muted-foreground tabular-nums line-through">
                                                            <span className="sr-only">
                                                                {t('impact.before')}:{' '}
                                                            </span>
                                                            {summarize(
                                                                editor,
                                                                change.before,
                                                                t,
                                                                locale,
                                                            )}
                                                        </dd>
                                                        <dd className="font-medium tabular-nums">
                                                            <span className="sr-only">
                                                                {t('impact.after')}:{' '}
                                                            </span>
                                                            {summarize(
                                                                editor,
                                                                change.after,
                                                                t,
                                                                locale,
                                                            )}
                                                        </dd>
                                                    </div>
                                                );
                                            })}
                                        </dl>
                                        {row.balances.length > 0 ? (
                                            <dl
                                                className="grid gap-x-4 gap-y-1 rounded-md bg-muted p-2 text-small md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]"
                                                data-impact-balance
                                            >
                                                {row.balances.map((balance) => (
                                                    <div key={balance.code} className="contents">
                                                        <dt className="text-text-secondary">
                                                            {t('impact.earned', {
                                                                type: localized(
                                                                    balance.name,
                                                                    locale,
                                                                ),
                                                                year: formatNumber(
                                                                    balance.year,
                                                                    locale,
                                                                    { useGrouping: false },
                                                                ),
                                                            })}
                                                        </dt>
                                                        <dd className="text-muted-foreground tabular-nums">
                                                            <span className="sr-only">
                                                                {t('impact.before')}:{' '}
                                                            </span>
                                                            {formatNumber(balance.before, locale, {
                                                                maximumFractionDigits: 2,
                                                            })}
                                                        </dd>
                                                        <dd className="font-medium tabular-nums">
                                                            <span className="sr-only">
                                                                {t('impact.after')}:{' '}
                                                            </span>
                                                            {formatNumber(balance.after, locale, {
                                                                maximumFractionDigits: 2,
                                                            })}
                                                        </dd>
                                                        <dd
                                                            className={cn(
                                                                'tabular-nums',
                                                                balance.delta < 0
                                                                    ? 'text-danger'
                                                                    : 'text-success',
                                                            )}
                                                        >
                                                            <span className="sr-only">
                                                                {t('impact.delta')}:{' '}
                                                            </span>
                                                            <bdi>
                                                                {balance.delta < 0
                                                                    ? '−'
                                                                    : balance.delta > 0
                                                                      ? '+'
                                                                      : ''}
                                                                {formatNumber(
                                                                    Math.abs(balance.delta),
                                                                    locale,
                                                                    { maximumFractionDigits: 2 },
                                                                )}
                                                            </bdi>
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        ) : null}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {preview.rows.some((row) => row.balances.length > 0) ? (
                            <p className="text-caption text-muted-foreground">
                                {t('impact.balances', {
                                    year: formatNumber(Number(preview.date.slice(0, 4)), locale, {
                                        useGrouping: false,
                                    }),
                                })}
                            </p>
                        ) : null}
                    </>
                )}
            </div>
        </ResponsiveDialog>
    );
}
