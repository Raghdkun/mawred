import { describe, expect, it } from 'vitest';
import { ancestorIds, buildTree, subtreeIds } from './tree';

const items = [
    { id: 'ops', parentId: null },
    { id: 'field', parentId: 'ops' },
    { id: 'north', parentId: 'field' },
    { id: 'sales', parentId: null },
];

describe('department tree helpers', () => {
    it('builds a forest', () => {
        const tree = buildTree(items);
        expect(tree.map((node) => node.item.id)).toEqual(['ops', 'sales']);
        expect(tree[0]!.children[0]!.children[0]!.item.id).toBe('north');
    });

    it('finds the subtree and ancestors', () => {
        expect([...subtreeIds(items, 'ops')].sort()).toEqual(['field', 'north', 'ops']);
        expect(ancestorIds(items, 'north')).toEqual(['field', 'ops']);
    });
});
