import { describe, expect, it } from 'vitest';
import { fromRule, toRule } from './conditions';

describe('builder conditions', () => {
    it('round-trips simple conditions through the rules language', () => {
        const conditions = {
            mode: 'any' as const,
            items: [
                { ref: 'field.kind', op: 'eq' as const, value: 'trip' },
                { ref: 'field.nights', op: 'gt' as const, value: '3' },
                { ref: 'field.note', op: 'not_empty' as const, value: '' },
            ],
        };
        const rule = toRule(conditions);

        expect(rule).toEqual({
            op: 'any',
            args: [
                { op: 'eq', args: [{ ref: 'field.kind' }, 'trip'] },
                { op: 'gt', args: [{ ref: 'field.nights' }, 3] },
                { op: 'not', args: [{ op: 'empty', args: [{ ref: 'field.note' }] }] },
            ],
        });
        expect(fromRule(rule)).toEqual(conditions);
    });

    it('treats a single condition as the rule itself and gives up on complex rules', () => {
        expect(
            toRule({ mode: 'all', items: [{ ref: 'field.a', op: 'empty', value: '' }] }),
        ).toEqual({ op: 'empty', args: [{ ref: 'field.a' }] });
        expect(toRule({ mode: 'all', items: [] })).toBeNull();
        expect(fromRule({ op: 'gt', args: [{ op: 'add', args: [1, 2] }, 1] })).toBeNull();
    });
});

describe('a new condition', () => {
    it('starts empty', () => {
        expect(fromRule({ op: 'all', args: [true] })).toEqual({ mode: 'all', items: [] });
    });
});
