import {
    Check,
    CircleAlert,
    Clock,
    CornerUpLeft,
    Forward,
    Play,
    RotateCcw,
    Send,
    X,
} from 'lucide-react';
import { type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

type Entry = App.Domain.Requests.Data.TimelineEntryData;

const ICONS: Record<string, { icon: ReactNode; tone: string }> = {
    started: {
        icon: <Send aria-hidden className="size-3.5" />,
        tone: 'bg-primary text-primary-foreground',
    },
    resubmitted: {
        icon: <Send aria-hidden className="size-3.5" />,
        tone: 'bg-primary text-primary-foreground',
    },
    'task.assigned': {
        icon: <Play aria-hidden className="size-3.5 rtl:-scale-x-100" />,
        tone: 'bg-status-in-review-bg text-status-in-review-fg',
    },
    'task.approved': {
        icon: <Check aria-hidden className="size-3.5" />,
        tone: 'bg-status-approved-bg text-status-approved-fg',
    },
    'task.done': {
        icon: <Check aria-hidden className="size-3.5" />,
        tone: 'bg-status-approved-bg text-status-approved-fg',
    },
    'task.rejected': {
        icon: <X aria-hidden className="size-3.5" />,
        tone: 'bg-status-rejected-bg text-status-rejected-fg',
    },
    'task.returned': {
        icon: <CornerUpLeft aria-hidden className="size-3.5 rtl:-scale-x-100" />,
        tone: 'bg-status-returned-bg text-status-returned-fg',
    },
    'task.delegated': {
        icon: <Forward aria-hidden className="size-3.5 rtl:-scale-x-100" />,
        tone: 'bg-muted text-text-secondary',
    },
    'task.escalated': {
        icon: <CircleAlert aria-hidden className="size-3.5" />,
        tone: 'bg-status-escalated-bg text-status-escalated-fg',
    },
    'task.overdue': {
        icon: <Clock aria-hidden className="size-3.5" />,
        tone: 'bg-status-escalated-bg text-status-escalated-fg',
    },
    'task.reminded': {
        icon: <Clock aria-hidden className="size-3.5" />,
        tone: 'bg-muted text-text-secondary',
    },
    'step.retried': {
        icon: <RotateCcw aria-hidden className="size-3.5" />,
        tone: 'bg-muted text-text-secondary',
    },
    cancelled: {
        icon: <X aria-hidden className="size-3.5" />,
        tone: 'bg-status-cancelled-bg text-status-cancelled-fg',
    },
};

/**
 * The workflow timeline (handoff 12): newest last, the rail on the reading-start side. Each
 * entry names who acted, on which step, and any comment.
 */
export function Timeline({ entries }: { entries: Entry[] }) {
    const { t } = useTranslation('requests');
    const { locale } = useLocale();
    const known = entries.filter(
        (entry) => t(`timeline.events.${entry.event}`, { defaultValue: '' }) !== '',
    );

    if (known.length === 0) {
        return <p className="text-small text-muted-foreground">{t('timeline.empty')}</p>;
    }

    return (
        <ol className="relative flex flex-col gap-5 ps-9 before:absolute before:inset-y-2 before:start-3 before:w-px before:bg-border">
            {known.map((entry, index) => {
                const look =
                    ICONS[entry.event] ??
                    (entry.event === 'completed'
                        ? outcomeLook(entry.data.outcome)
                        : ICONS['task.reminded']);
                const step =
                    typeof entry.data.step === 'string' && entry.data.step !== ''
                        ? entry.data.step
                        : null;

                return (
                    <li
                        key={`${entry.event}-${entry.at}-${index}`}
                        className="relative flex flex-col gap-0.5"
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'absolute -start-9 top-0 flex size-6 items-center justify-center rounded-full ring-4 ring-card',
                                look?.tone,
                            )}
                        >
                            {look?.icon}
                        </span>
                        <p className="text-sm text-foreground">
                            {t(`timeline.events.${entry.event}`, {
                                actor: entry.actor ?? t('timeline.system'),
                                subject: entry.subject ?? '',
                                outcome: t(
                                    `timeline.outcomes.${String(entry.data.outcome ?? 'completed')}`,
                                ),
                            })}
                        </p>
                        <p className="text-caption text-muted-foreground">
                            {step ? <span>{step} · </span> : null}
                            <time dateTime={entry.at}>
                                {formatDate(entry.at, locale, {
                                    dateStyle: 'medium',
                                    timeStyle: 'short',
                                })}
                            </time>
                            {entry.via && entry.via !== 'web' ? (
                                <span> · {t(`timeline.via.${entry.via}`)}</span>
                            ) : null}
                        </p>
                        {entry.comment ? (
                            <blockquote className="mt-1 rounded-md border-s-2 border-border bg-muted/60 px-3 py-2 text-small whitespace-pre-line text-foreground">
                                <bdi>{entry.comment}</bdi>
                            </blockquote>
                        ) : null}
                    </li>
                );
            })}
        </ol>
    );
}

function outcomeLook(outcome: unknown) {
    return outcome === 'rejected'
        ? ICONS['task.rejected']
        : outcome === 'cancelled'
          ? ICONS.cancelled
          : ICONS['task.approved'];
}
