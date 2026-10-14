import { Head, router } from '@inertiajs/react';
import { ChevronDown, Filter } from 'lucide-react';
import { type FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AsyncPicker, type PickerOption } from '@/components/mw/async-picker';
import { PageHeader } from '@/components/mw/page-header';
import { PageState } from '@/components/mw/page-state';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import audit from '@/routes/audit';

type Entry = App.Domain.Audit.Data.AuditEntryData;
type Filters = {
    log?: string;
    event?: string;
    actor?: string;
    from?: string;
    to?: string;
    search?: string;
};
type Props = {
    entries: { data: Entry[]; nextCursor: string | null; prevCursor: string | null };
    filters: Filters;
    logs: string[];
};

function ValueCell({ value }: { value: unknown }) {
    const text =
        value === null || value === undefined
            ? '—'
            : typeof value === 'object'
              ? JSON.stringify(value)
              : String(value);

    return (
        <bdi dir="ltr" className="max-w-full self-start font-mono text-caption break-all">
            {text}
        </bdi>
    );
}

function Diff({ entry }: { entry: Entry }) {
    const { t } = useTranslation('audit');
    const after = (entry.changes.attributes ?? {}) as Record<string, unknown>;
    const before = (entry.changes.old ?? {}) as Record<string, unknown>;
    const keys = Array.from(new Set([...Object.keys(before), ...Object.keys(after)]));
    const properties = Object.entries(entry.properties ?? {});

    return (
        <div className="flex flex-col gap-3 border-t border-border bg-surface-sunken/60 p-4">
            {keys.length > 0 ? (
                <table className="w-full text-start text-small">
                    <thead>
                        <tr className="text-caption text-muted-foreground">
                            <th className="pb-1 text-start font-medium">{t('diff.field')}</th>
                            <th className="pb-1 text-start font-medium">{t('diff.before')}</th>
                            <th className="pb-1 text-start font-medium">{t('diff.after')}</th>
                        </tr>
                    </thead>
                    <tbody>
                        {keys.map((key) => (
                            <tr key={key} className="align-top">
                                <th scope="row" className="py-1 pe-3 text-start font-medium">
                                    <bdi
                                        dir="ltr"
                                        className="max-w-full self-start font-mono text-caption"
                                    >
                                        {key}
                                    </bdi>
                                </th>
                                <td className="py-1 pe-3 text-danger">
                                    <ValueCell value={before[key]} />
                                </td>
                                <td className="py-1 text-success">
                                    <ValueCell value={after[key]} />
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : null}
            {properties.length > 0 ? (
                <pre
                    dir="ltr"
                    className="overflow-x-auto rounded-md bg-card p-3 font-mono text-caption"
                >
                    {JSON.stringify(entry.properties, null, 2)}
                </pre>
            ) : null}
            <dl className="flex flex-wrap gap-x-6 gap-y-1 text-caption text-muted-foreground">
                {entry.channel ? (
                    <div className="flex gap-1">
                        <dt>{t('meta.channel')}:</dt>
                        <dd>{t(`channels.${entry.channel}`, { defaultValue: entry.channel })}</dd>
                    </div>
                ) : null}
                {entry.ip ? (
                    <div className="flex gap-1">
                        <dt>{t('meta.ip')}:</dt>
                        <dd>
                            <bdi dir="ltr">{entry.ip}</bdi>
                        </dd>
                    </div>
                ) : null}
                {entry.requestId ? (
                    <div className="flex gap-1">
                        <dt>{t('meta.request')}:</dt>
                        <dd>
                            <bdi dir="ltr" className="font-mono">
                                {entry.requestId}
                            </bdi>
                        </dd>
                    </div>
                ) : null}
            </dl>
        </div>
    );
}

/** Audit log (handoff 43): filters, diff expansion, immutable. */
export default function AuditIndex({ entries, filters, logs }: Props) {
    const { t } = useTranslation('audit');
    const { locale } = useLocale();
    const [form, setForm] = useState<Filters>(filters);
    const [actor, setActor] = useState<PickerOption | null>(
        filters.actor ? { id: filters.actor, label: filters.actor } : null,
    );
    const [open, setOpen] = useState<string | null>(null);

    const apply = (next: Filters) => {
        const query = Object.fromEntries(Object.entries(next).filter(([, value]) => value));
        router.get(audit.index.url(), query, { preserveState: true, replace: true });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        apply({ ...form, actor: actor?.id });
    };

    const page = (cursor: string | null) =>
        router.get(
            audit.index.url(),
            { ...filters, cursor },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title={t('pageTitle')} />
            <PageHeader title={t('title')} description={t('description')} />

            <form
                onSubmit={submit}
                className="mb-5 grid gap-3 rounded-lg bg-card p-4 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto_auto_auto]"
            >
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="audit-search">{t('filters.search')}</Label>
                    <Input
                        id="audit-search"
                        type="search"
                        value={form.search ?? ''}
                        onChange={(event) => setForm({ ...form, search: event.target.value })}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <span className="text-sm font-medium">{t('filters.actor')}</span>
                    <AsyncPicker<{ id: string; name: string; email: string }>
                        endpoint="users"
                        queryKey="audit-actors"
                        toOption={(user) => ({
                            id: user.id,
                            label: user.name,
                            description: user.email,
                        })}
                        value={actor}
                        onChange={setActor}
                        placeholder={t('filters.anyone')}
                        label={t('filters.actor')}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="audit-log">{t('filters.log')}</Label>
                    <select
                        id="audit-log"
                        value={form.log ?? ''}
                        onChange={(event) =>
                            setForm({ ...form, log: event.target.value || undefined })
                        }
                        className="h-10 rounded-md border border-input bg-card px-3 text-sm hover:border-input-hover focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none"
                    >
                        <option value="">{t('filters.allLogs')}</option>
                        {logs.map((log) => (
                            <option key={log} value={log}>
                                {t(`logs.${log}`)}
                            </option>
                        ))}
                    </select>
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="audit-from">{t('filters.from')}</Label>
                    <Input
                        id="audit-from"
                        type="date"
                        value={form.from ?? ''}
                        onChange={(event) => setForm({ ...form, from: event.target.value })}
                    />
                </div>
                <div className="flex flex-col gap-1.5">
                    <Label htmlFor="audit-to">{t('filters.to')}</Label>
                    <Input
                        id="audit-to"
                        type="date"
                        value={form.to ?? ''}
                        onChange={(event) => setForm({ ...form, to: event.target.value })}
                    />
                </div>
                <div className="flex items-end gap-2">
                    <Button type="submit">
                        <Filter aria-hidden /> {t('filters.apply')}
                    </Button>
                </div>
            </form>

            {entries.data.length === 0 ? (
                <PageState
                    kind={Object.keys(filters).length > 0 ? 'no-results' : 'empty'}
                    illustration="empty-reports"
                    title={t('empty')}
                />
            ) : (
                <ol className="overflow-hidden rounded-lg border border-border bg-card">
                    {entries.data.map((entry) => {
                        const expanded = open === entry.key;
                        const hasDetails =
                            Object.keys(entry.changes).length > 0 ||
                            Object.keys(entry.properties).length > 0 ||
                            entry.ip;

                        return (
                            <li key={entry.key} className="border-b border-border last:border-b-0">
                                <button
                                    type="button"
                                    onClick={() => setOpen(expanded ? null : entry.key)}
                                    aria-expanded={hasDetails ? expanded : undefined}
                                    disabled={!hasDetails}
                                    className="flex w-full flex-col gap-1 px-4 py-3 text-start hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none focus-visible:ring-inset disabled:cursor-default disabled:hover:bg-transparent md:flex-row md:items-center md:gap-4"
                                >
                                    <time
                                        dateTime={entry.occurredAt}
                                        className="shrink-0 text-caption text-muted-foreground tabular-nums md:w-44"
                                    >
                                        {formatDate(entry.occurredAt, locale, {
                                            dateStyle: 'medium',
                                            timeStyle: 'medium',
                                        })}
                                    </time>
                                    <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                                        <span className="text-sm">
                                            <span className="font-medium">
                                                {entry.actor?.name ?? t('system')}
                                            </span>{' '}
                                            <span className="text-text-secondary">
                                                {t(`events.${entry.event ?? ''}`, {
                                                    defaultValue: entry.description,
                                                })}
                                            </span>
                                            {entry.subject ? (
                                                <span className="text-text-secondary">
                                                    {' · '}
                                                    {entry.subject.name ?? entry.subject.type}
                                                </span>
                                            ) : null}
                                        </span>
                                        {entry.impersonator ? (
                                            <span className="text-caption text-warning">
                                                {t('impersonatedBy', { name: entry.impersonator })}
                                            </span>
                                        ) : null}
                                    </span>
                                    <span className="flex items-center gap-2">
                                        {entry.log ? (
                                            <Badge variant="outline">
                                                {t(`logs.${entry.log}`, {
                                                    defaultValue: entry.log,
                                                })}
                                            </Badge>
                                        ) : null}
                                        {hasDetails ? (
                                            <ChevronDown
                                                aria-hidden
                                                className={cn(
                                                    'size-4 text-muted-foreground transition-transform duration-150',
                                                    expanded && 'rotate-180',
                                                )}
                                            />
                                        ) : null}
                                    </span>
                                </button>
                                {expanded ? <Diff entry={entry} /> : null}
                            </li>
                        );
                    })}
                </ol>
            )}

            {entries.prevCursor || entries.nextCursor ? (
                <div className="mt-4 flex justify-end gap-2">
                    <Button
                        variant="outline"
                        disabled={!entries.prevCursor}
                        onClick={() => page(entries.prevCursor)}
                    >
                        {t('newer')}
                    </Button>
                    <Button
                        variant="outline"
                        disabled={!entries.nextCursor}
                        onClick={() => page(entries.nextCursor)}
                    >
                        {t('older')}
                    </Button>
                </div>
            ) : null}
        </>
    );
}
