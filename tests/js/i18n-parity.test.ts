import { readdirSync, readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

/** ADR-0011: EN and AR UI translations must stay key-complete. */
const root = resolve(process.cwd(), 'lang');

function keys(value: unknown, prefix = ''): string[] {
    if (value === null || typeof value !== 'object') {
        return [prefix];
    }

    return Object.entries(value as Record<string, unknown>).flatMap(([key, child]) =>
        keys(child, prefix ? `${prefix}.${key}` : key),
    );
}

function load(locale: string, file: string): unknown {
    return JSON.parse(readFileSync(resolve(root, locale, 'ui', file), 'utf8'));
}

const namespaces = readdirSync(resolve(root, 'en', 'ui')).filter((file) => file.endsWith('.json'));

describe('translation parity', () => {
    it('has the same namespaces in every locale', () => {
        expect(
            readdirSync(resolve(root, 'ar', 'ui'))
                .filter((file) => file.endsWith('.json'))
                .sort(),
        ).toEqual([...namespaces].sort());
    });

    it.each(namespaces)('%s has the same keys in English and Arabic', (file) => {
        expect(keys(load('ar', file)).sort()).toEqual(keys(load('en', file)).sort());
    });

    it.each(namespaces)('%s has no empty Arabic strings', (file) => {
        const empty: string[] = [];
        const walk = (value: unknown, path: string) => {
            if (typeof value === 'string') {
                if (value.trim() === '') empty.push(path);
            } else if (value && typeof value === 'object') {
                Object.entries(value as Record<string, unknown>).forEach(([key, child]) =>
                    walk(child, `${path}/${key}`),
                );
            }
        };

        walk(load('ar', file), file);
        expect(empty).toEqual([]);
    });
});
