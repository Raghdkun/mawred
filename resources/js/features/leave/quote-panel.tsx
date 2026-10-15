import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Paperclip } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Skeleton } from '@/components/ui/skeleton';
import { useLocale } from '@/hooks/use-locale';
import { postJson } from '@/lib/api';
import { cn } from '@/lib/utils';
import LeaveApi from '@/actions/App/Http/Controllers/Api/V1/Leave/LeaveController';
import { amountLabel, type LeaveQuote, rangeLabel } from './format';

/** Answers that change what a time-away request takes. */
const KEYS = ['dates', 'half_day', 'kind', 'date', 'from_time', 'to_time'];

function useDebounced<T>(value: T, delay: number): T {
    const [debounced, setDebounced] = useState(value);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(value), delay);

        return () => window.clearTimeout(timer);
    }, [value, delay]);

    return debounced;
}

/**
 * Balance preview on the request form (handoff 11): what the request takes in working days (or
 * minutes), what is available on its first day and what is left after, plus anything that would
 * stop it. The server checks again on submit (D65).
 */
export function QuotePanel({
    typeId,
    values,
    returnedRequestId,
}: {
    typeId: string;
    values: Record<string, unknown>;
    returnedRequestId?: string;
}) {
    const { t } = useTranslation('leave');
    const { locale } = useLocale();
    const relevant = JSON.stringify(
        Object.fromEntries(KEYS.map((key) => [key, values[key] ?? null])),
    );
    const answers = useDebounced(relevant, 350);
    const query = useQuery({
        queryKey: ['leave-quote', typeId, answers, returnedRequestId],
        queryFn: () =>
            postJson<{ data: LeaveQuote }>(LeaveApi.quote.url(typeId), {
                values: JSON.parse(answers) as Record<string, unknown>,
                request: returnedRequestId ?? null,
            }),
        staleTime: 10_000,
        placeholderData: (previous) => previous,
    });
    const quote = query.data?.data;

    return (
        <section
            aria-labelledby="leave-quote-title"
            aria-live="polite"
            data-leave-quote
            className="flex flex-col gap-3 rounded-md bg-muted p-4"
        >
            <h2 id="leave-quote-title" className="text-body font-semibold">
                {t('quote.title')}
            </h2>
            {query.isPending ? (
                <Skeleton className="h-16" />
            ) : query.isError ? (
                <p className="text-small text-text-secondary">{t('quote.error')}</p>
            ) : !quote || quote.from === null ? (
                <p className="text-small text-text-secondary">{t('quote.pick')}</p>
            ) : (
                <>
                    <p className="text-small text-text-secondary">
                        {rangeLabel(quote.from, quote.to ?? quote.from, locale)}
                    </p>
                    <dl className="grid grid-cols-[1fr_auto] gap-x-3 gap-y-1.5 text-small">
                        <dt className="text-text-secondary">{t('quote.takes')}</dt>
                        <dd className="text-end font-medium tabular-nums" data-quote-amount>
                            {amountLabel(t, quote.amount, quote.unit, locale)}
                        </dd>
                        {quote.available !== null ? (
                            <>
                                <dt className="text-text-secondary">
                                    {quote.unit === 'minutes'
                                        ? t('quote.quotaLeft')
                                        : t('quote.available')}
                                </dt>
                                <dd className="text-end tabular-nums">
                                    {amountLabel(t, quote.available, quote.unit, locale)}
                                </dd>
                            </>
                        ) : null}
                        {quote.after !== null ? (
                            <>
                                <dt className="text-text-secondary">{t('quote.after')}</dt>
                                <dd
                                    className={cn(
                                        'text-end font-medium tabular-nums',
                                        quote.after < 0 && 'text-danger',
                                    )}
                                >
                                    {quote.after < 0 ? '−' : ''}
                                    {amountLabel(t, Math.abs(quote.after), quote.unit, locale)}
                                </dd>
                            </>
                        ) : null}
                    </dl>
                    {quote.unit === 'days' ? (
                        <p className="text-caption text-muted-foreground">
                            {t('quote.workingDays')}
                        </p>
                    ) : null}
                    {quote.attachmentRequired ? (
                        <p className="flex items-start gap-2 text-small text-text-secondary">
                            <Paperclip aria-hidden className="mt-0.5 size-4 shrink-0" />
                            {t('quote.attachment')}
                        </p>
                    ) : null}
                </>
            )}
            {quote && quote.problems.length > 0 ? (
                <div
                    role="alert"
                    className="flex flex-col gap-1 rounded-md border border-danger-border bg-danger-bg p-3 text-small text-danger"
                >
                    <span className="flex items-center gap-2 font-medium">
                        <AlertTriangle aria-hidden className="size-4 shrink-0" />
                        {t('quote.blocked')}
                    </span>
                    <ul className="flex flex-col gap-1 ps-6">
                        {quote.problems.map((problem) => (
                            <li key={problem.code}>{problem.message}</li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
