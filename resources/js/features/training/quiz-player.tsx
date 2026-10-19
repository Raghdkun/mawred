import { router } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { SuccessRipple } from '@/components/mw/success-ripple';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import training from '@/routes/training';

type Quiz = NonNullable<App.Domain.Training.Data.ModuleData['quiz']>;

/**
 * A quiz (handoff 34, D102): one radio group or checkbox group per question, options in their
 * authored order. After submitting, a result screen: the success ripple when passed, otherwise
 * the score, which questions were right, and another go while attempts remain.
 */
export function QuizPlayer({
    courseId,
    moduleId,
    quiz,
    preview,
    onContinue,
}: {
    courseId: string;
    moduleId: string;
    quiz: Quiz;
    preview: boolean;
    onContinue: () => void;
}) {
    const { t } = useTranslation('training');
    const [answers, setAnswers] = useState<Record<string, string[]>>({});
    const [reviewing, setReviewing] = useState(quiz.last !== null || quiz.passed);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const left = quiz.attemptsAllowed - quiz.attemptsUsed;
    const answered = quiz.questions.every((q) => (answers[q.id] ?? []).length > 0);

    const choose = (questionId: string, optionId: string, many: boolean) =>
        setAnswers((all) => {
            const current = all[questionId] ?? [];

            return {
                ...all,
                [questionId]: many
                    ? current.includes(optionId)
                        ? current.filter((id) => id !== optionId)
                        : [...current, optionId]
                    : [optionId],
            };
        });

    const submit = () => {
        setSending(true);
        setError(null);
        router.post(
            training.learn.attempt.url([courseId, moduleId]),
            { answers },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setReviewing(true);
                    setAnswers({});
                },
                onError: (errors) => setError(errors.error ?? Object.values(errors)[0] ?? null),
                onFinish: () => setSending(false),
            },
        );
    };

    if (reviewing && (quiz.last || quiz.passed)) {
        const passed = quiz.passed;
        const score = quiz.last?.score ?? quiz.bestScore ?? 0;

        return (
            <section
                className="flex flex-col items-center gap-4 rounded-lg bg-card p-6 text-center shadow-sm"
                aria-live="polite"
                data-quiz-result={passed ? 'passed' : 'failed'}
            >
                {passed ? <SuccessRipple label={t('learn.quiz.passedTitle')} /> : null}
                <h2 className="text-h3">
                    {passed ? t('learn.quiz.passedTitle') : t('learn.quiz.failedTitle')}
                </h2>
                <p className="text-body text-text-secondary">
                    {passed
                        ? t('learn.quiz.passedHelp', { score })
                        : t('learn.quiz.failedHelp', { score, mark: quiz.passMark })}
                </p>
                {quiz.last && !passed ? (
                    <ol className="flex w-full max-w-md flex-col gap-1 text-start text-small">
                        {quiz.questions.map((question, index) => {
                            const right = quiz.last?.correct.includes(question.id) ?? false;

                            return (
                                <li key={question.id} className="flex items-start gap-2">
                                    {right ? (
                                        <Check
                                            aria-hidden
                                            className="mt-0.5 size-4 shrink-0 text-success"
                                        />
                                    ) : (
                                        <X
                                            aria-hidden
                                            className="mt-0.5 size-4 shrink-0 text-danger"
                                        />
                                    )}
                                    <span>
                                        <span className="sr-only">
                                            {right
                                                ? t('learn.quiz.correct')
                                                : t('learn.quiz.incorrect')}
                                            :{' '}
                                        </span>
                                        {t('learn.quiz.question', { n: index + 1 })} —{' '}
                                        {question.prompt}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                ) : null}
                <div className="flex flex-wrap justify-center gap-2">
                    {!passed && left > 0 ? (
                        <Button className="min-h-11" onClick={() => setReviewing(false)} data-retry>
                            {t('learn.quiz.retry', { count: left })}
                        </Button>
                    ) : null}
                    {passed ? (
                        <Button className="min-h-11" onClick={onContinue} data-quiz-continue>
                            {t('learn.continue')}
                        </Button>
                    ) : null}
                </div>
                {!passed && left <= 0 ? (
                    <p className="text-small text-text-secondary">{t('learn.quiz.noAttempts')}</p>
                ) : null}
            </section>
        );
    }

    return (
        <form
            className="flex flex-col gap-6"
            onSubmit={(event) => {
                event.preventDefault();
                submit();
            }}
            data-quiz
        >
            <p className="text-small text-text-secondary">
                {t('learn.quiz.intro', { mark: quiz.passMark })} ·{' '}
                {t('learn.quiz.attempts', {
                    used: quiz.attemptsUsed,
                    allowed: quiz.attemptsAllowed,
                })}
            </p>
            {quiz.questions.map((question, index) => {
                const many = question.type === 'multiple';

                return (
                    <div
                        key={question.id}
                        className="rounded-lg border border-border bg-card p-4"
                        data-question={question.id}
                    >
                        <fieldset className="flex flex-col gap-2">
                            <legend className="mb-1 text-body font-medium">
                                <span className="text-text-secondary tabular-nums">
                                    {index + 1}.{' '}
                                </span>
                                {question.prompt}
                            </legend>
                            <p className="text-caption text-text-secondary">
                                {many ? t('learn.quiz.chooseMany') : t('learn.quiz.chooseOne')}
                            </p>
                            {question.options.map((option) => {
                                const id = `${question.id}-${option.id}`;
                                const checked = (answers[question.id] ?? []).includes(option.id);

                                return (
                                    <label
                                        key={option.id}
                                        htmlFor={id}
                                        className={cn(
                                            'flex min-h-11 cursor-pointer items-center gap-3 rounded-md border px-3 py-2 text-small transition-colors motion-reduce:transition-none',
                                            checked
                                                ? 'border-primary bg-primary/5'
                                                : 'border-border hover:bg-muted',
                                        )}
                                    >
                                        <input
                                            id={id}
                                            type={many ? 'checkbox' : 'radio'}
                                            name={question.id}
                                            value={option.id}
                                            checked={checked}
                                            disabled={preview}
                                            onChange={() => choose(question.id, option.id, many)}
                                            className="size-4 shrink-0 accent-primary"
                                            data-option={option.id}
                                        />
                                        {option.label}
                                    </label>
                                );
                            })}
                        </fieldset>
                    </div>
                );
            })}
            {error ? (
                <p role="alert" className="rounded-md bg-danger-bg p-3 text-small text-danger">
                    {error}
                </p>
            ) : null}
            {preview ? (
                <p className="text-small text-text-secondary">{t('learn.previewQuiz')}</p>
            ) : (
                <Button
                    type="submit"
                    className="min-h-11 self-end"
                    disabled={!answered || sending || left <= 0}
                    data-submit-quiz
                >
                    {t('learn.quiz.submit')}
                </Button>
            )}
        </form>
    );
}
