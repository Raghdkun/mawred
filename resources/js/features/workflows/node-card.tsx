import { Handle, type NodeProps, Position } from '@xyflow/react';
import {
    Bell,
    CircleAlert,
    Clock,
    Database,
    Flag,
    GitBranch,
    GitFork,
    GitMerge,
    ListChecks,
    Play,
    Stamp,
    Webhook,
    Workflow,
} from 'lucide-react';
import { type ComponentType, type SVGProps } from 'react';
import { useTranslation } from 'react-i18next';
import { useLocale } from '@/hooks/use-locale';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import { type CanvasNode, OUTPUTS, type WorkflowNodeType } from './graph';

export const NODE_ICONS: Record<WorkflowNodeType, ComponentType<SVGProps<SVGSVGElement>>> = {
    start: Play,
    approval: Stamp,
    task: ListChecks,
    condition: GitBranch,
    split: GitFork,
    join: GitMerge,
    notify: Bell,
    delay: Clock,
    update_record: Database,
    webhook: Webhook,
    subworkflow: Workflow,
    end: Flag,
};

/** A node on the canvas: icon, type and label; inputs on the reading-start side, outputs at the end. */
export function NodeCard({ data, selected }: NodeProps<CanvasNode>) {
    const { t } = useTranslation('workflows');
    const { locale, isRtl } = useLocale();
    const Icon = NODE_ICONS[data.type];
    const outputs = OUTPUTS[data.type];
    const label = localized(
        (data.config.label as Record<string, string> | undefined) ?? {},
        locale,
    );
    const inSide = isRtl ? Position.Right : Position.Left;
    const outSide = isRtl ? Position.Left : Position.Right;

    return (
        <div
            className={cn(
                'relative w-[220px] rounded-lg border bg-card text-start shadow-sm transition-shadow duration-150 ease-soft',
                selected ? 'border-primary ring-2 ring-primary/30' : 'border-border',
                data.problems.length > 0 && 'border-danger',
                data.sim === 'done' && 'border-success ring-2 ring-success/30',
                data.sim === 'active' && 'border-primary ring-2 ring-primary/40',
            )}
        >
            {data.sim === 'active' ? (
                <span
                    aria-hidden
                    className="absolute -inset-1 animate-ping rounded-lg border-2 border-primary/40 motion-reduce:hidden"
                />
            ) : null}
            {data.type !== 'start' ? (
                <Handle type="target" position={inSide} className="!size-3" />
            ) : null}
            <div className="flex items-center gap-2 border-b border-border px-3 py-2">
                <span
                    className={cn(
                        'flex size-6 items-center justify-center rounded-md',
                        data.type === 'end'
                            ? 'bg-muted text-text-secondary'
                            : 'bg-primary/10 text-primary',
                    )}
                >
                    <Icon aria-hidden className="size-3.5" />
                </span>
                <span className="text-caption font-medium text-text-secondary">
                    {t(`nodes.${data.type}.name`)}
                </span>
                {data.problems.length > 0 ? (
                    <CircleAlert
                        aria-label={t('builder.hasProblems')}
                        className="ms-auto size-4 text-danger"
                    />
                ) : null}
            </div>
            <div className="px-3 py-2 text-small text-foreground">
                {label ||
                    (data.type === 'end' ? (
                        t(`outcomes.${String(data.config.outcome ?? 'completed')}`)
                    ) : (
                        <span className="text-muted-foreground">
                            {t(`nodes.${data.type}.hint`)}
                        </span>
                    ))}
            </div>
            {outputs === null ? (
                <Handle type="source" position={outSide} className="!size-3" />
            ) : (
                <div className="pb-1">
                    {outputs.map((handle) => (
                        <div
                            key={handle}
                            className="relative flex h-6 items-center justify-end px-3 text-caption text-muted-foreground"
                        >
                            <span>{t(`handles.${handle}`)}</span>
                            <Handle
                                id={handle}
                                type="source"
                                position={outSide}
                                className="!size-3"
                            />
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
