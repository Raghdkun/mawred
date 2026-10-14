import { Head, Link, router } from '@inertiajs/react';
import {
    addEdge,
    Background,
    type Connection,
    Controls,
    type Edge,
    type EdgeChange,
    MiniMap,
    type NodeChange,
    ReactFlow,
    ReactFlowProvider,
    useEdgesState,
    useNodesState,
    useReactFlow,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';
import { ChevronLeft, CircleAlert, CirclePlay, Monitor, Undo2 } from 'lucide-react';
import { type DragEvent, useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DirIcon } from '@/components/mw/dir-icon';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { asPayload } from '@/features/forms/schema';
import {
    canConnect,
    type CanvasNode,
    DEFAULT_CONFIG,
    fromCanvas,
    newNodeId,
    PALETTE,
    toCanvas,
    type WorkflowGraph,
    type WorkflowNodeType,
} from '@/features/workflows/graph';
import { NODE_ICONS, NodeCard } from '@/features/workflows/node-card';
import { type Catalog, NodeSettings } from '@/features/workflows/node-settings';
import { type SimulationResult, Simulator } from '@/features/workflows/simulator';
import { useAppearance } from '@/hooks/use-appearance';
import { useLocale } from '@/hooks/use-locale';
import { formatDate, formatNumber } from '@/lib/format';
import { localized } from '@/lib/localized';
import { cn } from '@/lib/utils';
import workflows from '@/routes/workflows';

type Version = {
    number: number;
    status: string;
    publishedAt: string | null;
    publishedBy: string | null;
};

type Props = {
    workflow: App.Domain.Workflows.Data.WorkflowDefinitionData;
    graph: WorkflowGraph;
    isDraft: boolean;
    problems: Record<string, string[]>;
    versions: Version[];
    catalog: Catalog;
    can: { update: boolean };
};

const nodeTypes = { workflow: NodeCard };

/** Workflow builder (handoff 15 ★): desktop canvas; smaller screens get a read-only view. */
export default function WorkflowBuilder(props: Props) {
    return (
        <ReactFlowProvider>
            <Builder {...props} />
        </ReactFlowProvider>
    );
}

function Builder({ workflow, graph, isDraft, problems, versions, catalog, can }: Props) {
    const { t } = useTranslation('workflows');
    const { locale, isRtl } = useLocale();
    const { resolvedAppearance } = useAppearance();
    const flow = useReactFlow<CanvasNode, Edge>();
    const [initial] = useState(() => toCanvas(graph, isRtl, problems));
    const [nodes, setNodes, onNodesChange] = useNodesState<CanvasNode>(initial.nodes);
    const [edges, setEdges, onEdgesChange] = useEdgesState<Edge>(initial.edges);
    const [selected, setSelected] = useState<string | null>(null);
    const [simulating, setSimulating] = useState(false);
    const [saving, setSaving] = useState<'idle' | 'pending' | 'saving' | 'saved'>('idle');
    const timer = useRef<number | null>(null);
    const readOnly = !can.update;
    const problemCount = Object.values(problems).reduce((total, list) => total + list.length, 0);
    const selectedNode = nodes.find((node) => node.id === selected) ?? null;
    const publishedVersion = versions.find((version) => version.status === 'published');

    // Server-side validation comes back with each save; show it on the nodes.
    useEffect(() => {
        setNodes((current) =>
            current.map((node) => ({
                ...node,
                data: { ...node.data, problems: problems[node.id] ?? [] },
            })),
        );
    }, [problems, setNodes]);

    const save = useCallback(() => {
        setSaving('saving');
        router.put(
            workflows.draft.url(workflow.id),
            asPayload({ graph: fromCanvas(flow.getNodes(), flow.getEdges(), isRtl) }),
            {
                preserveState: true,
                preserveScroll: true,
                only: ['problems', 'isDraft', 'workflow', 'versions'],
                onFinish: () => setSaving('saved'),
            },
        );
    }, [flow, isRtl, workflow.id]);

    const changed = useCallback(() => {
        if (readOnly) return;
        setSaving('pending');
        if (timer.current) window.clearTimeout(timer.current);
        timer.current = window.setTimeout(save, 1200);
    }, [readOnly, save]);

    useEffect(
        () => () => {
            if (timer.current) window.clearTimeout(timer.current);
        },
        [],
    );

    const meaningful = (changes: Array<NodeChange<CanvasNode> | EdgeChange>) =>
        changes.some(
            (change) =>
                change.type !== 'select' &&
                change.type !== 'dimensions' &&
                change.type !== 'position',
        );

    const addNode = (type: WorkflowNodeType, position?: { x: number; y: number }) => {
        const id = newNodeId(
            type,
            nodes.map((node) => node.id),
        );
        const bounds = document.getElementById('workflow-canvas')?.getBoundingClientRect();
        const at =
            position ??
            flow.screenToFlowPosition({
                x: (bounds?.left ?? 0) + (bounds?.width ?? 800) / 2,
                y: (bounds?.top ?? 0) + (bounds?.height ?? 600) / 2,
            });

        setNodes((current) => [
            ...current.map((node) => ({ ...node, selected: false })),
            {
                id,
                type: 'workflow',
                position: at,
                selected: true,
                data: { type, config: structuredClone(DEFAULT_CONFIG[type]), problems: [] },
            },
        ]);
        setSelected(id);
        setSimulating(false);
        changed();
    };

    const onDrop = (event: DragEvent) => {
        event.preventDefault();
        const type = event.dataTransfer.getData('application/x-mawred-node') as WorkflowNodeType;

        if (type && PALETTE.includes(type)) {
            addNode(type, flow.screenToFlowPosition({ x: event.clientX, y: event.clientY }));
        }
    };

    const isValidConnection = (connection: Connection | Edge) => {
        const source = nodes.find((node) => node.id === connection.source);
        const target = nodes.find((node) => node.id === connection.target);

        return Boolean(
            source &&
            target &&
            source.id !== target.id &&
            target.data.type !== 'start' &&
            canConnect(source.data.type, connection.sourceHandle ?? null, edges, source.id),
        );
    };

    const showSimulation = (result: SimulationResult | null) => {
        const path = result?.path.map((step) => step.node) ?? [];
        const last = path[path.length - 1];

        setNodes((current) =>
            current.map((node) => ({
                ...node,
                data: {
                    ...node.data,
                    sim: path.includes(node.id) ? (node.id === last ? 'active' : 'done') : null,
                },
            })),
        );
        setEdges((current) =>
            current.map((edge) => ({
                ...edge,
                animated: path.includes(edge.source) && path.includes(edge.target),
            })),
        );
    };

    const status =
        saving === 'saving'
            ? t('builder.saving')
            : saving === 'saved'
              ? t('builder.saved')
              : saving === 'pending'
                ? t('builder.unsaved')
                : null;

    return (
        <>
            <Head title={t('builder.pageTitle', { name: localized(workflow.name, locale) })} />
            <div className="-mx-4 -my-6 flex h-[calc(100svh-3.5rem)] flex-col md:-mx-6 md:h-[calc(100svh-4rem)]">
                <header className="flex flex-wrap items-center gap-3 border-b border-border bg-card px-4 py-3">
                    <Link
                        href={workflows.index.url()}
                        className="inline-flex min-h-11 min-w-11 items-center justify-center text-text-secondary hover:text-foreground"
                    >
                        <DirIcon>
                            <ChevronLeft aria-hidden className="size-4" />
                        </DirIcon>
                        <span className="sr-only">{t('index.title')}</span>
                    </Link>
                    <div className="flex min-w-0 flex-col">
                        <h1 className="truncate text-h4">{localized(workflow.name, locale)}</h1>
                        <p className="text-caption text-muted-foreground" aria-live="polite">
                            {isDraft
                                ? t('builder.draft')
                                : publishedVersion
                                  ? t('builder.published', {
                                        number: formatNumber(publishedVersion.number, locale),
                                    })
                                  : t('builder.new')}
                            {status ? ` · ${status}` : ''}
                        </p>
                    </div>
                    {problemCount > 0 ? (
                        <Badge variant="outline" className="border-danger-border text-danger">
                            <CircleAlert aria-hidden />
                            {t('builder.problems', {
                                count: problemCount,
                                formatted: formatNumber(problemCount, locale),
                            })}
                        </Badge>
                    ) : null}
                    <div className="ms-auto flex flex-wrap items-center gap-2">
                        <Button
                            variant={simulating ? 'secondary' : 'outline'}
                            onClick={() => {
                                setSimulating((value) => !value);
                                setSelected(null);
                            }}
                            className="hidden xl:inline-flex"
                        >
                            <CirclePlay aria-hidden className="rtl:-scale-x-100" />
                            {t('builder.simulate')}
                        </Button>
                        {can.update && isDraft && publishedVersion ? (
                            <Button
                                variant="ghost"
                                onClick={() =>
                                    router.delete(workflows.discard.url(workflow.id), {
                                        onSuccess: () => router.reload(),
                                    })
                                }
                            >
                                <Undo2 aria-hidden />
                                {t('builder.discard')}
                            </Button>
                        ) : null}
                        {can.update ? (
                            <Button
                                disabled={
                                    !isDraft ||
                                    problemCount > 0 ||
                                    saving === 'pending' ||
                                    saving === 'saving'
                                }
                                onClick={() =>
                                    router.post(
                                        workflows.publish.url(workflow.id),
                                        {},
                                        { preserveScroll: true, preserveState: true },
                                    )
                                }
                            >
                                {t('builder.publish')}
                            </Button>
                        ) : null}
                    </div>
                </header>

                <p className="flex items-center gap-2 border-b border-border bg-info-bg px-4 py-2 text-small text-info xl:hidden">
                    <Monitor aria-hidden className="size-4 shrink-0" />
                    {t('builder.desktopOnly')}
                </p>

                <div className="flex min-h-0 flex-1">
                    {!readOnly ? (
                        <aside
                            aria-label={t('builder.palette')}
                            className="hidden w-56 shrink-0 flex-col gap-1 overflow-y-auto border-e border-border bg-card p-3 xl:flex"
                        >
                            <p className="px-2 pb-1 text-overline text-muted-foreground uppercase">
                                {t('builder.addStep')}
                            </p>
                            {PALETTE.map((type) => {
                                const Icon = NODE_ICONS[type];

                                return (
                                    <button
                                        key={type}
                                        type="button"
                                        draggable
                                        onDragStart={(event) => {
                                            event.dataTransfer.setData(
                                                'application/x-mawred-node',
                                                type,
                                            );
                                            event.dataTransfer.effectAllowed = 'move';
                                        }}
                                        onClick={() => addNode(type)}
                                        className="flex min-h-11 items-center gap-2 rounded-md px-2 text-start text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <span className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
                                            <Icon aria-hidden className="size-4" />
                                        </span>
                                        <span className="flex flex-col">
                                            <span>{t(`nodes.${type}.name`)}</span>
                                            <span className="text-caption text-muted-foreground">
                                                {t(`nodes.${type}.hint`)}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </aside>
                    ) : null}

                    <div
                        id="workflow-canvas"
                        role="region"
                        aria-label={t('builder.canvas')}
                        className="min-w-0 flex-1"
                        onDragOver={(event) => event.preventDefault()}
                        onDrop={onDrop}
                    >
                        <ReactFlow<CanvasNode, Edge>
                            nodes={nodes}
                            edges={edges}
                            nodeTypes={nodeTypes}
                            onNodesChange={(changes) => {
                                onNodesChange(
                                    changes.filter(
                                        (change) =>
                                            !(
                                                change.type === 'remove' &&
                                                nodes.find((node) => node.id === change.id)?.data
                                                    .type === 'start'
                                            ),
                                    ),
                                );
                                if (meaningful(changes)) changed();
                            }}
                            onNodeDragStop={changed}
                            onEdgesChange={(changes) => {
                                onEdgesChange(changes);
                                if (meaningful(changes)) changed();
                            }}
                            onConnect={(connection) => {
                                const handle = connection.sourceHandle ?? null;
                                setEdges((current) =>
                                    addEdge(
                                        {
                                            ...connection,
                                            id: `e-${connection.source}-${handle ?? 'out'}-${connection.target}`,
                                        },
                                        current,
                                    ),
                                );
                                changed();
                            }}
                            isValidConnection={isValidConnection}
                            onSelectionChange={({ nodes: chosen }) => {
                                setSelected(chosen[0]?.id ?? null);
                                if (chosen.length > 0) setSimulating(false);
                            }}
                            nodesDraggable={!readOnly}
                            nodesConnectable={!readOnly}
                            deleteKeyCode={readOnly ? null : ['Backspace', 'Delete']}
                            colorMode={resolvedAppearance === 'dark' ? 'dark' : 'light'}
                            fitView
                            proOptions={{ hideAttribution: true }}
                        >
                            <Background gap={20} />
                            <Controls
                                position={isRtl ? 'bottom-right' : 'bottom-left'}
                                showInteractive={false}
                            />
                            <MiniMap
                                position={isRtl ? 'bottom-left' : 'bottom-right'}
                                pannable
                                zoomable
                                className="hidden xl:block"
                            />
                        </ReactFlow>
                    </div>

                    <aside
                        aria-label={t('builder.panel')}
                        className="hidden w-[360px] shrink-0 overflow-y-auto border-s border-border bg-card p-4 xl:block"
                    >
                        {simulating ? (
                            <Simulator
                                url={workflows.simulate.url(workflow.id)}
                                graph={() => fromCanvas(flow.getNodes(), flow.getEdges(), isRtl)}
                                nodes={nodes}
                                catalog={catalog}
                                onResult={showSimulation}
                                onClose={() => setSimulating(false)}
                            />
                        ) : selectedNode ? (
                            <NodeSettings
                                key={selectedNode.id}
                                nodeId={selectedNode.id}
                                type={selectedNode.data.type}
                                config={selectedNode.data.config}
                                problems={selectedNode.data.problems}
                                catalog={catalog}
                                readOnly={readOnly}
                                onChange={(config) => {
                                    setNodes((current) =>
                                        current.map((node) =>
                                            node.id === selectedNode.id
                                                ? { ...node, data: { ...node.data, config } }
                                                : node,
                                        ),
                                    );
                                    changed();
                                }}
                                onDelete={() => {
                                    void flow.deleteElements({ nodes: [{ id: selectedNode.id }] });
                                    setSelected(null);
                                    changed();
                                }}
                            />
                        ) : (
                            <Overview
                                problems={problems}
                                nodes={nodes}
                                versions={versions}
                                onSelect={(id) => {
                                    setNodes((current) =>
                                        current.map((node) => ({
                                            ...node,
                                            selected: node.id === id,
                                        })),
                                    );
                                    setSelected(id);
                                }}
                            />
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}

function Overview({
    problems,
    nodes,
    versions,
    onSelect,
}: {
    problems: Record<string, string[]>;
    nodes: CanvasNode[];
    versions: Version[];
    onSelect: (id: string) => void;
}) {
    const { t } = useTranslation('workflows');
    const { locale } = useLocale();
    const entries = Object.entries(problems);

    return (
        <div className="flex flex-col gap-6">
            <section className="flex flex-col gap-2">
                <h2 className="text-h4">{t('builder.checks')}</h2>
                {entries.length === 0 ? (
                    <p className="text-small text-success">{t('builder.noProblems')}</p>
                ) : (
                    <ul className="flex flex-col gap-2">
                        {entries.map(([node, messages]) => (
                            <li key={node}>
                                {nodes.some((candidate) => candidate.id === node) ? (
                                    <button
                                        type="button"
                                        onClick={() => onSelect(node)}
                                        className="flex w-full flex-col items-start gap-0.5 rounded-md border border-danger-border bg-danger-bg p-2 text-start text-small text-danger hover:underline"
                                    >
                                        <bdi dir="ltr" className="font-medium">
                                            {node}
                                        </bdi>
                                        {messages.map((message) => (
                                            <span key={message}>{message}</span>
                                        ))}
                                    </button>
                                ) : (
                                    <p className="rounded-md border border-danger-border bg-danger-bg p-2 text-small text-danger">
                                        {messages.join(' ')}
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
                <p className="text-caption text-muted-foreground">{t('builder.howTo')}</p>
            </section>
            <section className="flex flex-col gap-2">
                <h2 className="text-h4">{t('builder.versions')}</h2>
                <ol className="flex flex-col gap-2">
                    {versions.map((version) => (
                        <li
                            key={version.number}
                            className={cn(
                                'flex flex-col rounded-md border p-2 text-small',
                                version.status === 'published'
                                    ? 'border-primary/50'
                                    : 'border-border',
                            )}
                        >
                            <span className="font-medium">
                                {t('builder.version', {
                                    number: formatNumber(version.number, locale),
                                })}{' '}
                                · {t(`builder.versionStatus.${version.status}`)}
                            </span>
                            {version.publishedAt ? (
                                <span className="text-caption text-muted-foreground">
                                    {formatDate(version.publishedAt, locale, {
                                        dateStyle: 'medium',
                                        timeStyle: 'short',
                                    })}
                                    {version.publishedBy ? ` · ${version.publishedBy}` : ''}
                                </span>
                            ) : null}
                        </li>
                    ))}
                </ol>
            </section>
        </div>
    );
}
