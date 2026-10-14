import { Link } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import policies from '@/routes/policies';
import { type ChainLink, type ResolvedValue } from './types';
import { summarize } from './values';

/** The level that supplied a value, e.g. "Branch: Riyadh". */
export function useLevelLabel() {
    const { t } = useTranslation('policies');

    return (level: string, target: string | null) =>
        target
            ? t('explainer.fromTarget', { level: t(`levels.${level}`), target })
            : t('explainer.from', { level: t(`levels.${level}`) });
}

/** A value overrides when a less specific level also defines the key. */
export function isOverride(resolved: ResolvedValue): boolean {
    return resolved.chain.filter((link) => link.level !== 'default').length > 1;
}

/**
 * Policy source explainer (DSData): a chip naming where the value comes from, opening the full
 * precedence chain. The winner is filled; levels it overrides are struck through.
 */
export function ExplainerChip({ resolved, editor }: { resolved: ResolvedValue; editor: string }) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();
    const label = useLevelLabel();
    const winner = resolved.chain.find((link) => link.wins);
    const overrides = isOverride(resolved);

    return (
        <Popover>
            <PopoverTrigger asChild>
                <button
                    type="button"
                    className="inline-flex h-7 max-w-full items-center gap-1.5 rounded-full border border-border bg-card ps-2.5 pe-3 text-caption text-text-secondary transition-colors duration-150 ease-soft hover:border-ring hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:border-ring data-[state=open]:ring-3 data-[state=open]:ring-ring/20"
                >
                    <MapPin aria-hidden className="size-3.5 shrink-0" />
                    <span className="truncate">
                        {winner ? label(winner.level, winner.target) : ''}
                    </span>
                    {overrides ? (
                        <>
                            <span
                                aria-hidden
                                className="size-2 shrink-0 rounded-full bg-status-escalated-fg"
                            />
                            <span className="sr-only">{t('effective.overridden')}</span>
                        </>
                    ) : null}
                </button>
            </PopoverTrigger>
            <PopoverContent align="start" className="w-80 max-w-[calc(100vw-2rem)]">
                <p className="mb-3 text-sm font-medium">{t('explainer.chainTitle')}</p>
                <ol className="grid grid-cols-[1.25rem_minmax(0,1fr)_auto] items-center gap-x-2.5 gap-y-2 text-small">
                    {[...resolved.chain].reverse().map((link, index) => (
                        <ChainRow
                            // The chain is fixed per value; its order is its identity.
                            key={index}
                            link={link}
                            editor={editor}
                            label={label(link.level, link.target)}
                            overridden={
                                !link.wins &&
                                resolved.chain.indexOf(link) >
                                    resolved.chain.findIndex((l) => l.wins)
                            }
                        />
                    ))}
                </ol>
                {winner?.setId && winner.setName ? (
                    <Link
                        href={policies.show.url(winner.setId)}
                        className="mt-3 inline-block text-small text-link hover:underline"
                    >
                        {t('explainer.openSet', { name: localized(winner.setName, locale) })}
                    </Link>
                ) : null}
                {resolved.effectiveFrom ? (
                    <p className="mt-1 text-caption text-muted-foreground">
                        {t('explainer.since', { date: formatDate(resolved.effectiveFrom, locale) })}
                    </p>
                ) : null}
            </PopoverContent>
        </Popover>
    );
}

function ChainRow({
    link,
    editor,
    label,
    overridden,
}: {
    link: ChainLink;
    editor: string;
    label: string;
    overridden: boolean;
}) {
    const { t } = useTranslation('policies');
    const { locale } = useLocale();

    return (
        <li className="contents">
            <span
                aria-hidden
                className={cn(
                    'justify-self-center rounded-full',
                    link.wins
                        ? 'size-3 bg-primary ring-3 ring-primary/20'
                        : 'size-2.5 border-[1.5px] border-input',
                )}
            />
            <span
                className={cn(
                    'truncate',
                    link.wins ? 'font-medium text-foreground' : 'text-muted-foreground',
                )}
            >
                {label}
            </span>
            <span
                className={cn(
                    'text-end',
                    link.wins && 'font-semibold text-foreground',
                    overridden && 'text-muted-foreground line-through',
                )}
            >
                {summarize(editor, link.value, t, locale)}
            </span>
        </li>
    );
}
