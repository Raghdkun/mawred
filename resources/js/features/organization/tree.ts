export type TreeNode<T> = { item: T; children: TreeNode<T>[] };

/** Builds a forest from a flat list in tree order (parents before children). */
export function buildTree<T extends { id: string; parentId: string | null }>(
    items: T[],
): TreeNode<T>[] {
    const nodes = new Map<string, TreeNode<T>>();
    const roots: TreeNode<T>[] = [];

    for (const item of items) {
        nodes.set(item.id, { item, children: [] });
    }

    for (const item of items) {
        const node = nodes.get(item.id)!;
        const parent = item.parentId ? nodes.get(item.parentId) : undefined;
        (parent ? parent.children : roots).push(node);
    }

    return roots;
}

/** Ids of an item and everything below it (a department cannot move inside its own subtree). */
export function subtreeIds<T extends { id: string; parentId: string | null }>(
    items: T[],
    rootId: string,
): Set<string> {
    const ids = new Set([rootId]);
    let grew = true;

    while (grew) {
        grew = false;
        for (const item of items) {
            if (item.parentId && ids.has(item.parentId) && !ids.has(item.id)) {
                ids.add(item.id);
                grew = true;
            }
        }
    }

    return ids;
}

/** Ancestor ids, nearest first. */
export function ancestorIds<T extends { id: string; parentId: string | null }>(
    items: T[],
    id: string,
): string[] {
    const byId = new Map(items.map((item) => [item.id, item]));
    const result: string[] = [];
    let current = byId.get(id)?.parentId ?? null;

    while (current && !result.includes(current)) {
        result.push(current);
        current = byId.get(current)?.parentId ?? null;
    }

    return result;
}
