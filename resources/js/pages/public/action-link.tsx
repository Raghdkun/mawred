import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Illustration } from '@/components/mw/illustration';
import { PageState } from '@/components/mw/page-state';
import { SuccessRipple } from '@/components/mw/success-ripple';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Answers } from '@/features/forms/answers';
import { type FormSchema } from '@/features/forms/schema';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import actionLinks from '@/routes/action-links';

type Props =
    | { state: 'expired' }
    | { state: 'done'; decision: string }
    | {
          state: 'ready';
          token: string;
          decision: string;
          request: { number: string; type: Record<string, string>; requester: string | null; submittedAt: string } | null;
          schema: FormSchema | null;
          answers: Record<string, unknown> | null;
          display: Record<string, string>;
      };

/**
 * A link approval from an email (ADR-0009, D24). Opening the link never decides anything: the
 * assignee reviews and confirms here. Links are single-use and expire.
 */
export default function ActionLink(props: Props) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const [comment, setComment] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [sending, setSending] = useState(false);

    if (props.state === 'expired') {
        return (
            <div className="mx-auto w-full max-w-[560px] px-4 py-12">
                <Head title={t('link.expiredTitle')} />
                <PageState kind="empty" illustration="empty-inbox" title={t('link.expiredTitle')} description={t('link.expiredHelp')} />
            </div>
        );
    }

    if (props.state === 'done') {
        return (
            <div className="mx-auto flex w-full max-w-[560px] flex-col items-center gap-4 px-4 py-12 text-center">
                <Head title={t(`link.done.${props.decision}`)} />
                {props.decision === 'approved' ? <SuccessRipple label={t('link.done.approved')} /> : <Illustration name="success-submitted" className="w-48" />}
                <h1 className="text-h2">{t(`link.done.${props.decision}`)}</h1>
                <p className="text-body text-text-secondary">{t('link.doneHelp')}</p>
            </div>
        );
    }

    const approving = props.decision === 'approved';

    return (
        <div className="mx-auto flex w-full max-w-[640px] flex-col gap-6 px-4 py-8">
            <Head title={t(`link.title.${props.decision}`)} />
            <header className="flex flex-col gap-1">
                <h1 className="text-h2">{t(`link.title.${props.decision}`)}</h1>
                {props.request ? (
                    <p className="text-body text-text-secondary">
                        {localized(props.request.type, locale)} · <bdi dir="ltr">{props.request.number}</bdi>
                        {props.request.requester ? <> · {props.request.requester}</> : null}
                        {' · '}
                        {formatDate(props.request.submittedAt, locale, { dateStyle: 'medium' })}
                    </p>
                ) : null}
            </header>

            {props.schema && props.answers ? (
                <section className="rounded-lg bg-card p-5 shadow-sm sm:p-6">
                    <Answers schema={props.schema} answers={props.answers} display={props.display} fileUrl={() => '#'} />
                </section>
            ) : null}

            <form
                className="flex flex-col gap-3 rounded-lg bg-card p-5 shadow-sm sm:p-6"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (!approving && comment.trim() === '') {
                        setError(t('inbox.commentNeeded'));

                        return;
                    }

                    setSending(true);
                    router.post(actionLinks.confirm.url(props.token), { comment: comment || null }, {
                        onError: (errors) => setError(errors.comment ?? errors.error ?? null),
                        onFinish: () => setSending(false),
                    });
                }}
            >
                <Label htmlFor="link-comment">{approving ? t('link.commentOptional') : t('link.commentRequired')}</Label>
                <Textarea
                    id="link-comment"
                    value={comment}
                    maxLength={2000}
                    aria-invalid={error !== null}
                    aria-describedby={error ? 'link-error' : undefined}
                    onChange={(event) => {
                        setComment(event.target.value);
                        setError(null);
                    }}
                />
                {error ? (
                    <p id="link-error" role="alert" className="text-caption text-danger">
                        {error}
                    </p>
                ) : null}
                <Button type="submit" variant={approving ? 'default' : 'destructive'} disabled={sending} className="self-start">
                    {t(`link.confirm.${props.decision}`)}
                </Button>
            </form>
        </div>
    );
}
