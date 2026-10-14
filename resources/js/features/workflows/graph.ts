import { type Edge, type Node } from '@xyflow/react';

/** mawred.workflow/v1 (ADR-0009): the document the engine runs and the builder edits. */
export type WorkflowNodeType =
    | 'start'
    | 'approval'
    | 'task'
    | 'condition'
    | 'split'
    | 'join'
    | 'notify'
    | 'delay'
    | 'update_record'
    | 'webhook'
    | 'subworkflow'
    | 'end';

export type NodeConfig = Record<string, unknown>;
export type GraphNode = {
    id: string;
    type: WorkflowNodeType;
    config: NodeConfig;
    position?: { x: number; y: number };
};
export type GraphEdge = {
    id: string;
    source: string;
    target: string;
    sourceHandle?: string | null;
};
export type WorkflowGraph = {
    format: 'mawred.workflow/v1';
    nodes: GraphNode[];
    edges: GraphEdge[];
};

export type CanvasNodeData = {
    type: WorkflowNodeType;
    config: NodeConfig;
    problems: string[];
    sim?: 'done' | 'active' | 'failed' | null;
};
export type CanvasNode = Node<CanvasNodeData, 'workflow'>;

/**
 * Output handles per node type, as the engine expects them (`handles()` on each handler);
 * `null` means one unnamed output that may fan out.
 */
export const OUTPUTS: Record<WorkflowNodeType, string[] | null> = {
    start: null,
    approval: ['approved', 'rejected'],
    task: ['done'],
    condition: ['true', 'false'],
    split: null,
    join: null,
    notify: null,
    delay: null,
    update_record: null,
    webhook: null,
    subworkflow: ['approved', 'rejected', 'completed'],
    end: [],
};

/** The palette, in the order the builder lists it (Start is placed once, automatically). */
export const PALETTE: WorkflowNodeType[] = [
    'approval',
    'task',
    'condition',
    'split',
    'join',
    'notify',
    'delay',
    'update_record',
    'webhook',
    'subworkflow',
    'end',
];

export const DEFAULT_CONFIG: Record<WorkflowNodeType, NodeConfig> = {
    start: {},
    approval: {
        assignee: { type: 'manager' },
        mode: 'any',
        sla: { hours: 48, reminderHours: 24, escalateTo: { type: 'assignee_manager' } },
        allowReturn: true,
    },
    task: { assignee: { type: 'requester' } },
    condition: {},
    split: {},
    join: {},
    notify: {
        channel: 'in_app',
        to: { type: 'requester' },
        subject: { en: '', ar: '' },
        body: { en: '', ar: '' },
    },
    delay: { hours: 24 },
    update_record: {},
    webhook: {},
    subworkflow: {},
    end: { outcome: 'approved' },
};

const NODE_WIDTH = 220;

/** The canvas flows in the reading direction: in Arabic, x is mirrored. */
function mirror(x: number, rtl: boolean): number {
    return rtl ? -x - NODE_WIDTH : x;
}

export function toCanvas(
    graph: WorkflowGraph,
    rtl: boolean,
    problems: Record<string, string[]>,
): { nodes: CanvasNode[]; edges: Edge[] } {
    return {
        nodes: graph.nodes.map((node, index) => ({
            id: node.id,
            type: 'workflow',
            position: {
                x: mirror(node.position?.x ?? index * 260, rtl),
                y: node.position?.y ?? 120,
            },
            data: { type: node.type, config: node.config ?? {}, problems: problems[node.id] ?? [] },
            deletable: node.type !== 'start',
        })),
        edges: graph.edges.map((edge) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            sourceHandle: edge.sourceHandle ?? null,
        })),
    };
}

export function fromCanvas(nodes: CanvasNode[], edges: Edge[], rtl: boolean): WorkflowGraph {
    return {
        format: 'mawred.workflow/v1',
        nodes: nodes.map((node) => ({
            id: node.id,
            type: node.data.type,
            config: node.data.config,
            position: {
                x: Math.round(mirror(node.position.x, rtl)),
                y: Math.round(node.position.y),
            },
        })),
        edges: edges.map((edge) => ({
            id: edge.id,
            source: edge.source,
            target: edge.target,
            ...(edge.sourceHandle ? { sourceHandle: edge.sourceHandle } : {}),
        })),
    };
}

export function newNodeId(type: WorkflowNodeType, existing: string[]): string {
    for (let i = 1; ; i++) {
        const id = `${type}_${i}`;

        if (!existing.includes(id)) return id;
    }
}

/** Whether another edge may leave this handle (named handles take one edge; unnamed may fan out). */
export function canConnect(
    type: WorkflowNodeType,
    handle: string | null,
    edges: Edge[],
    source: string,
): boolean {
    const outputs = OUTPUTS[type];

    if (outputs === null) {
        return type === 'split' || !edges.some((edge) => edge.source === source);
    }

    return (
        handle !== null &&
        outputs.includes(handle) &&
        !edges.some((edge) => edge.source === source && edge.sourceHandle === handle)
    );
}
