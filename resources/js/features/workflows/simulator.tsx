import { CirclePlay, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLocale } from '@/hooks/use-locale';
import { xsrfToken } from '@/lib/api';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import { type CanvasNode, type WorkflowGraph } from './graph';
import { type Catalog } from './node-settings';

export type SimulationResult = {
    problems: Record<string, string[]>;
    path: Array<{
        node: string;
        type: string;
        status: string;
        assignees: string[];
        decision: string | null;
    }>;
    outcome: string | null;
    status: string | null;
};

const selectClass =
    'h-10 w-full rounded-md border border-input bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/**
 * Test run (handoff 15): sample answers and a decision per approval, run by the real engine
 * on a throwaway copy. Nothing is saved, notified or posted.
 */
export function Simulator({
    url,
    graph,
    nodes,
    catalog,
    onResult,
    onClose,
}: {
    url: string;
    graph: () => WorkflowGraph;
    nodes: CanvasNode[];
    catalog: Catalog;
    onResult: (result: SimulationResult | null) => void;
    onClose: () => void;
}) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const [fields, setFields] = useState<Record<string, unknown>>({});
    const [decisions, setDecisions] = useState<Record<string, string>>({});
    const [result, setResult] = useState<SimulationResult | null>(null);
    const [running, setRunning] = useState(false);
    const [failed, setFailed] = useState(false);
    const humans = nodes.filter(
        (node) => node.data.type === 'approval' || node.data.type === 'task',
    );

    const run = async () => {
        setRunning(true);
        setFailed(false);

        try {
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken() ?? '',
                },
                body: JSON.stringify({ graph: graph(), fields, decisions }),
            });

            if (!response.ok) throw new Error(response.statusText);

            const body = (await response.json()) as SimulationResult;
            setResult(body);
            onResult(body);
        } catch {
            setFailed(true);
        } finally {
            setRunning(false);
        }
    };

    return (
        <div className="flex flex-col gap-5">
            <div className="flex items-start justify-between gap-2">
                <div className="flex flex-col gap-0.5">
                    <h2 className="text-h4">{t('simulator.title')}</h2>
                    <p className="text-caption text-muted-foreground">{t('simulator.help')}</p>
                </div>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('simulator.close')}
                    onClick={() => {
                        onResult(null);
                        onClose();
                    }}
                >
                    <X aria-hidden />
                </Button>
            </div>

            {catalog.fields.length > 0 ? (
                <fieldset className="flex flex-col gap-3">
                    <legend className="mb-1 text-sm font-medium">{t('simulator.answers')}</legend>
                    {catalog.fields
                        .filter((field) => !['note', 'calculated', 'file'].includes(field.type))
                        .map((field) => {
                            const options = Array.isArray(field.options)
                                ? (field.options as Array<{
                                      value: string;
                                      label: Record<string, string>;
                                  }>)
                                : [];
                            const id = `sim-${field.key}`;
                            const set = (value: unknown) =>
                                setFields((current) => ({ ...current, [field.key]: value }));

                            return (
                                <div key={field.key} className="flex flex-col gap-1">
                                    <Label htmlFor={id} className="text-small">
                                        {localized(field.label ?? {}, locale) || field.key}
                                    </Label>
                                    {options.length > 0 ? (
                                        <select
                                            id={id}
                                            className={selectClass}
                                            value={String(fields[field.key] ?? '')}
                                            onChange={(event) =>
                                                set(
                                                    field.type === 'checkboxes' ||
                                                        field.type === 'multiselect'
                                                        ? [event.target.value]
                                                        : event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">—</option>
                                            {options.map((option) => (
                                                <option key={option.value} value={option.value}>
                                                    {localized(option.label, locale) ||
                                                        option.value}
                                                </option>
                                            ))}
                                        </select>
                                    ) : (
                                        <Input
                                            id={id}
                                            type={
                                                field.type === 'number' || field.type === 'money'
                                                    ? 'number'
                                                    : field.type === 'date'
                                                      ? 'date'
                                                      : 'text'
                                            }
                                            value={String(
                                                field.type === 'money'
                                                    ? ((
                                                          fields[field.key] as
                                                              { amount_minor?: number } | undefined
                                                      )?.amount_minor ?? '')
                                                    : (fields[field.key] ?? ''),
                                            )}
                                            onChange={(event) =>
                                                set(
                                                    field.type === 'number'
                                                        ? Number(event.target.value)
                                                        : field.type === 'money'
                                                          ? {
                                                                amount_minor: Number(
                                                                    event.target.value,
                                                                ),
                                                                currency: '',
                                                            }
                                                          : event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </div>
                            );
                        })}
                </fieldset>
            ) : (
                <p className="text-small text-muted-foreground">{t('simulator.noForm')}</p>
            )}

            {humans.length > 0 ? (
                <fieldset className="flex flex-col gap-3">
                    <legend className="mb-1 text-sm font-medium">{t('simulator.decisions')}</legend>
                    {humans.map((node) => (
                        <div key={node.id} className="flex flex-col gap-1">
                            <Label htmlFor={`decision-${node.id}`} className="text-small">
                                {localized(
                                    (node.data.config.label as Record<string, string>) ?? {},
                                    locale,
                                ) || node.id}
                            </Label>
                            <select
                                id={`decision-${node.id}`}
                                className={selectClass}
                                value={decisions[node.id] ?? ''}
                                onChange={(event) =>
                                    setDecisions((current) => ({
                                        ...current,
                                        [node.id]: event.target.value,
                                    }))
                                }
                            >
                                <option value="">
                                    {node.data.type === 'task'
                                        ? t('handles.done')
                                        : t('handles.approved')}
                                </option>
                                {node.data.type === 'approval' ? (
                                    <option value="rejected">{t('handles.rejected')}</option>
                                ) : null}
                            </select>
                        </div>
                    ))}
                </fieldset>
            ) : null}

            <Button onClick={() => void run()} loading={running}>
                <CirclePlay aria-hidden className="rtl:-scale-x-100" />
                {t('simulator.run')}
            </Button>

            <div aria-live="polite">
                {failed ? <p className="text-small text-danger">{t('simulator.failed')}</p> : null}
                {result ? (
                    Object.keys(result.problems).length > 0 ? (
                        <ul className="flex flex-col gap-1 rounded-md border border-danger-border bg-danger-bg p-3 text-small text-danger">
                            {Object.entries(result.problems).flatMap(([node, messages]) =>
                                messages.map((message) => (
                                    <li key={`${node}-${message}`}>
                                        {node === '_graph' ? message : `${node}: ${message}`}
                                    </li>
                                )),
                            )}
                        </ul>
                    ) : (
                        <div className="flex flex-col gap-3">
                            <p
                                className={cn(
                                    'rounded-md p-3 text-small font-medium',
                                    result.status !== 'completed'
                                        ? 'bg-status-pending-bg text-status-pending-fg'
                                        : result.outcome === 'rejected'
                                          ? 'bg-status-rejected-bg text-status-rejected-fg'
                                          : 'bg-status-approved-bg text-status-approved-fg',
                                )}
                            >
                                {result.status === 'completed'
                                    ? t('simulator.outcome', {
                                          outcome: t(`outcomes.${result.outcome ?? 'completed'}`),
                                      })
                                    : t(
                                          `simulator.stopped.${result.status === 'attention' ? 'attention' : 'waiting'}`,
                                      )}
                            </p>
                            <ol className="flex flex-col gap-2">
                                {result.path.map((step, index) => (
                                    <li
                                        key={`${step.node}-${index}`}
                                        className="flex flex-col gap-0.5 border-s-2 border-primary/40 ps-3"
                                    >
                                        <span className="text-small font-medium">
                                            <bdi dir="ltr">{step.node}</bdi> ·{' '}
                                            {t(`nodes.${step.type}.name`)}
                                        </span>
                                        {step.assignees.length > 0 ? (
                                            <span className="text-caption text-muted-foreground">
                                                {t('simulator.assignees', {
                                                    names: step.assignees.join('، '),
                                                })}
                                            </span>
                                        ) : null}
                                        {step.decision ? (
                                            <span className="text-caption text-muted-foreground">
                                                {t(`handles.${step.decision}`)}
                                            </span>
                                        ) : null}
                                    </li>
                                ))}
                            </ol>
                        </div>
                    )
                ) : null}
            </div>
        </div>
    );
}
