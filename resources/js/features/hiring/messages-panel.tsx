import { router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Mail, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import hiring from '@/routes/hiring';

type Message = App.Domain.Hiring.Data.CandidateMessageData;

/**
 * The communications log (D94): every email and WhatsApp message to the candidate and their
 * WhatsApp replies, newest first, with a composer for the hiring team.
 */
export function MessagesPanel({
    candidateId,
    applicationId,
    messages,
    canSend,
    hasEmail,
    hasPhone,
}: {
    candidateId: string;
    applicationId: string | null;
    messages: Message[];
    canSend: boolean;
    hasEmail: boolean;
    hasPhone: boolean;
}) {
    const { t } = useTranslation('hiring');
    const { locale } = useLocale();
    const [channel, setChannel] = useState(hasEmail ? 'email' : 'whatsapp');
    const [subject, setSubject] = useState('');
    const [body, setBody] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [sending, setSending] = useState(false);

    return (
        <section
            aria-labelledby="messages-title"
            className="flex flex-col gap-3 rounded-lg bg-card p-4 shadow-sm"
            data-messages
        >
            <h2 id="messages-title" className="text-h4">
                {t('messages.title')}
            </h2>
            {canSend && (hasEmail || hasPhone) ? (
                <form
                    className="flex flex-col gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        setSending(true);
                        router.post(
                            hiring.candidates.messages.url(candidateId),
                            {
                                channel,
                                subject: channel === 'email' ? subject : null,
                                body,
                                application: applicationId,
                            },
                            {
                                preserveScroll: true,
                                onError: setErrors,
                                onSuccess: () => {
                                    setBody('');
                                    setSubject('');
                                    setErrors({});
                                },
                                onFinish: () => setSending(false),
                            },
                        );
                    }}
                >
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Select value={channel} onValueChange={setChannel}>
                            <SelectTrigger
                                className="min-h-11 sm:w-40"
                                aria-label={t('messages.channel')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {hasEmail ? (
                                    <SelectItem value="email">{t('channel.email')}</SelectItem>
                                ) : null}
                                {hasPhone ? (
                                    <SelectItem value="whatsapp">
                                        {t('channel.whatsapp')}
                                    </SelectItem>
                                ) : null}
                            </SelectContent>
                        </Select>
                        {channel === 'email' ? (
                            <Input
                                value={subject}
                                onChange={(e) => setSubject(e.target.value)}
                                maxLength={200}
                                placeholder={t('messages.subject')}
                                aria-label={t('messages.subject')}
                                aria-invalid={Boolean(errors.subject)}
                                className="min-h-11"
                            />
                        ) : null}
                    </div>
                    <Textarea
                        value={body}
                        onChange={(e) => setBody(e.target.value)}
                        rows={3}
                        maxLength={4000}
                        aria-label={t('messages.body')}
                        placeholder={t('messages.body')}
                        data-message-body
                    />
                    {errors.subject || errors.body || errors.channel || errors.error ? (
                        <p role="alert" className="text-caption text-danger">
                            {errors.subject ?? errors.body ?? errors.channel ?? errors.error}
                        </p>
                    ) : null}
                    <Button
                        type="submit"
                        className="min-h-11 self-end"
                        disabled={sending || body.trim() === ''}
                        data-send-message
                    >
                        {t('messages.send')}
                    </Button>
                </form>
            ) : null}
            {messages.length === 0 ? (
                <p className="text-small text-text-secondary">{t('messages.empty')}</p>
            ) : (
                <ol className="flex flex-col gap-3">
                    {messages.map((m) => (
                        <li
                            key={m.id}
                            className={cn(
                                'flex flex-col gap-1 rounded-md p-3',
                                m.direction === 'in' ? 'bg-info-bg' : 'bg-muted',
                            )}
                        >
                            <span className="flex flex-wrap items-center gap-1.5 text-caption text-text-secondary">
                                {m.direction === 'in' ? (
                                    <ArrowDownLeft aria-hidden className="size-3.5" />
                                ) : (
                                    <ArrowUpRight
                                        aria-hidden
                                        className="size-3.5 rtl:-scale-x-100"
                                    />
                                )}
                                {m.channel === 'email' ? (
                                    <Mail aria-hidden className="size-3.5" />
                                ) : (
                                    <MessageCircle aria-hidden className="size-3.5" />
                                )}
                                <span>
                                    {t(`messages.direction.${m.direction}`)} ·{' '}
                                    {t(`channel.${m.channel}`)} ·{' '}
                                    {t(`messages.purpose.${m.purpose}`)}
                                </span>
                                <span>
                                    ·{' '}
                                    {formatDate(m.at, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                </span>
                                {m.by ? <span>· {m.by}</span> : null}
                                {m.status === 'failed' ? (
                                    <span className="font-medium text-danger">
                                        · {t('messages.failed')}
                                    </span>
                                ) : null}
                            </span>
                            {m.subject ? (
                                <span className="text-small font-medium">{m.subject}</span>
                            ) : null}
                            <p className="text-small whitespace-pre-line">{m.body}</p>
                            {m.error ? <p className="text-caption text-danger">{m.error}</p> : null}
                        </li>
                    ))}
                </ol>
            )}
        </section>
    );
}
