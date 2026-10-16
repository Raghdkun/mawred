import { router } from '@inertiajs/react';
import { CalendarClock, Users } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CopyField } from '@/components/mw/copy-field';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import hiring from '@/routes/hiring';

type Interview = App.Domain.Hiring.Data.InterviewData;
type Scorecard = App.Domain.Hiring.Data.ScorecardData;
type Criterion = { id: string; name: string };

export const RECOMMENDATIONS = ['strong_no', 'no', 'yes', 'strong_yes'] as const;

/**
 * An interview on the profile: when, who, and the scorecard (D90). Panelists rate each criterion
 * 1–4 and recommend; others' scorecards appear once theirs is in (D82).
 */
export function InterviewCard({
    interview,
    criteria,
}: {
    interview: Interview;
    criteria: Criterion[];
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const when = interview.startsAt
        ? formatDate(interview.startsAt, locale, {
              dateStyle: 'full',
              timeStyle: 'short',
              timeZone: interview.timezone,
          })
        : null;

    return (
        <article
            className="flex flex-col gap-3 rounded-md border border-border p-4"
            data-interview={interview.id}
        >
            <header className="flex flex-wrap items-start justify-between gap-2">
                <div className="flex flex-col gap-0.5">
                    <h4 className="text-small font-semibold">{interview.title}</h4>
                    <p className="flex items-center gap-1.5 text-caption text-text-secondary">
                        <CalendarClock aria-hidden className="size-3.5" />
                        {when ?? t(`interviewStatus.${interview.status}`)}
                        {' · '}
                        {t('interviews.minutes', { count: interview.durationMinutes })}
                        {' · '}
                        {t(`interviewMode.${interview.mode}`)}
                    </p>
                    <p className="flex items-center gap-1.5 text-caption text-text-secondary">
                        <Users aria-hidden className="size-3.5" />
                        {interview.panel.map((p) => p.name).join(', ')}
                    </p>
                </div>
                <span className="text-caption text-text-secondary">
                    {t('interviews.submitted', {
                        count: interview.submitted,
                        total: interview.panel.length,
                    })}
                </span>
            </header>

            {interview.bookingUrl ? (
                <CopyField label={t('interviews.bookingLink')} value={interview.bookingUrl} />
            ) : null}

            {interview.can.manage &&
            (interview.status === 'awaiting' || interview.status === 'scheduled') ? (
                <Button
                    variant="ghost"
                    className="min-h-11 self-start text-danger"
                    data-cancel-interview
                    onClick={() =>
                        router.delete(hiring.interviews.destroy.url(interview.id), {
                            preserveScroll: true,
                        })
                    }
                >
                    {t('interviews.cancel')}
                </Button>
            ) : null}

            {interview.can.score ? (
                <ScorecardForm interview={interview} criteria={criteria} />
            ) : null}

            {interview.scorecards.length > 0 ? (
                <section className="flex flex-col gap-2" aria-label={t('scorecards.others')}>
                    <h5 className="text-caption font-medium text-text-secondary">
                        {t('scorecards.others')}
                    </h5>
                    {interview.scorecards.map((card, i) => (
                        <ScorecardView key={`${card.by}-${i}`} card={card} criteria={criteria} />
                    ))}
                </section>
            ) : interview.submitted > 0 && !interview.can.readOthers ? (
                <p className="text-caption text-text-secondary">{t('scorecards.blind')}</p>
            ) : null}
        </article>
    );
}

function ScorecardView({ card, criteria }: { card: Scorecard; criteria: Criterion[] }) {
    const { t } = useTranslation('hiring');

    return (
        <div className="rounded-md bg-muted p-3 text-small">
            <p className="flex flex-wrap items-center justify-between gap-2 font-medium">
                <span>{card.by}</span>
                {card.recommendation ? (
                    <span>{t(`recommendation.${card.recommendation}`)}</span>
                ) : null}
            </p>
            <ul className="mt-1 flex flex-col gap-0.5 text-caption text-text-secondary">
                {criteria.map((c) =>
                    card.ratings[c.id] ? (
                        <li key={c.id}>
                            {c.name}: {card.ratings[c.id]!.rating}/4
                            {card.ratings[c.id]!.note ? ` — ${card.ratings[c.id]!.note}` : ''}
                        </li>
                    ) : null,
                )}
            </ul>
            {card.comment ? <p className="mt-1 whitespace-pre-line">{card.comment}</p> : null}
        </div>
    );
}

function ScorecardForm({ interview, criteria }: { interview: Interview; criteria: Criterion[] }) {
    const { t } = useTranslation('hiring');
    const mine = interview.mine;
    const submitted = Boolean(mine?.submittedAt);
    const [ratings, setRatings] = useState<Record<string, { rating: number; note: string }>>(() =>
        Object.fromEntries(
            Object.entries(mine?.ratings ?? {}).map(([id, r]) => [
                id,
                { rating: r.rating, note: r.note ?? '' },
            ]),
        ),
    );
    const [recommendation, setRecommendation] = useState<string | null>(
        mine?.recommendation ?? null,
    );
    const [comment, setComment] = useState(mine?.comment ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});

    if (submitted && mine) {
        return (
            <section className="flex flex-col gap-1" aria-label={t('scorecards.yours')}>
                <h5 className="text-caption font-medium text-text-secondary">
                    {t('scorecards.yours')}
                </h5>
                <ScorecardView card={mine} criteria={criteria} />
            </section>
        );
    }

    const save = (submit: boolean) =>
        router.put(
            hiring.interviews.scorecard.url(interview.id),
            { ratings, recommendation, comment: comment || null, submit },
            { preserveScroll: true, onError: setErrors, onSuccess: () => setErrors({}) },
        );

    return (
        <form
            className="flex flex-col gap-3 rounded-md bg-muted/60 p-3"
            aria-label={t('scorecards.yours')}
            onSubmit={(e) => {
                e.preventDefault();
                save(true);
            }}
            data-scorecard-form
        >
            <h5 className="text-small font-medium">{t('scorecards.yours')}</h5>
            {criteria.map((c) => (
                <fieldset key={c.id} className="flex flex-col gap-1.5">
                    <legend className="text-small">{c.name}</legend>
                    <div className="flex flex-wrap gap-1" role="radiogroup" aria-label={c.name}>
                        {[1, 2, 3, 4].map((n) => {
                            const on = ratings[c.id]?.rating === n;

                            return (
                                <button
                                    key={n}
                                    type="button"
                                    role="radio"
                                    aria-checked={on}
                                    aria-label={t('scorecards.rating', {
                                        n,
                                        label: t(`scorecards.scale.${n}`),
                                    })}
                                    onClick={() =>
                                        setRatings((all) => ({
                                            ...all,
                                            [c.id]: { rating: n, note: all[c.id]?.note ?? '' },
                                        }))
                                    }
                                    className={cn(
                                        'inline-flex min-h-11 min-w-11 items-center justify-center rounded-md border px-3 text-small tabular-nums',
                                        on
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border bg-card hover:bg-muted',
                                    )}
                                    data-rate={`${c.id}-${n}`}
                                >
                                    {n}
                                </button>
                            );
                        })}
                    </div>
                </fieldset>
            ))}
            <fieldset className="flex flex-col gap-1.5">
                <legend className="text-small">{t('scorecards.recommendation')}</legend>
                <div
                    className="flex flex-wrap gap-1"
                    role="radiogroup"
                    aria-label={t('scorecards.recommendation')}
                >
                    {RECOMMENDATIONS.map((r) => (
                        <button
                            key={r}
                            type="button"
                            role="radio"
                            aria-checked={recommendation === r}
                            onClick={() => setRecommendation(r)}
                            className={cn(
                                'inline-flex min-h-11 items-center rounded-md border px-3 text-small',
                                recommendation === r
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border bg-card hover:bg-muted',
                            )}
                            data-recommend={r}
                        >
                            {t(`recommendation.${r}`)}
                        </button>
                    ))}
                </div>
            </fieldset>
            <label className="flex flex-col gap-1 text-small">
                {t('scorecards.comment')}
                <Textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    rows={3}
                    maxLength={4000}
                />
            </label>
            {errors.recommendation || errors.error ? (
                <p role="alert" className="text-caption text-danger">
                    {errors.recommendation ?? errors.error}
                </p>
            ) : null}
            <div className="flex flex-wrap justify-end gap-2">
                <Button
                    type="button"
                    variant="ghost"
                    className="min-h-11"
                    onClick={() => save(false)}
                >
                    {t('scorecards.saveDraft')}
                </Button>
                <Button type="submit" className="min-h-11" data-submit-scorecard>
                    {t('scorecards.submit')}
                </Button>
            </div>
        </form>
    );
}
