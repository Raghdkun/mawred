import { router } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

/** Rating a finished course, 1–5 stars and an optional comment (D109). */
export function FeedbackForm({
    enrollmentId,
    initial,
}: {
    enrollmentId: string;
    initial: { rating: number; comment: string | null } | null;
}) {
    const { t } = useTranslation('training');
    const [rating, setRating] = useState(initial?.rating ?? 0);
    const [comment, setComment] = useState(initial?.comment ?? '');
    const [sending, setSending] = useState(false);

    return (
        <form
            className="flex flex-col gap-3"
            onSubmit={(event) => {
                event.preventDefault();
                setSending(true);
                router.post(
                    training.feedback.url(enrollmentId),
                    { rating, comment: comment || null },
                    { preserveScroll: true, onFinish: () => setSending(false) },
                );
            }}
            data-feedback
        >
            <fieldset className="flex flex-col gap-1">
                <legend className="mb-1 text-small font-medium">{t('feedback.rate')}</legend>
                <div className="flex gap-1" role="radiogroup" aria-label={t('feedback.rate')}>
                    {[1, 2, 3, 4, 5].map((n) => (
                        <button
                            key={n}
                            type="button"
                            role="radio"
                            aria-checked={rating === n}
                            aria-label={t('feedback.stars', { count: n })}
                            onClick={() => setRating(n)}
                            className="inline-flex size-11 items-center justify-center rounded-md hover:bg-muted"
                            data-star={n}
                        >
                            <Star
                                aria-hidden
                                className={cn(
                                    'size-6',
                                    n <= rating
                                        ? 'fill-warning stroke-warning'
                                        : 'stroke-text-secondary',
                                )}
                            />
                        </button>
                    ))}
                </div>
            </fieldset>
            <label className="flex flex-col gap-1 text-small">
                {t('feedback.comment')}
                <Textarea
                    value={comment}
                    onChange={(e) => setComment(e.target.value)}
                    rows={3}
                    maxLength={2000}
                />
            </label>
            <Button
                type="submit"
                className="min-h-11 self-start"
                disabled={rating === 0 || sending}
                data-send-feedback
            >
                {initial ? t('feedback.update') : t('feedback.send')}
            </Button>
        </form>
    );
}
