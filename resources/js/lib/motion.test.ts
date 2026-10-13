import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { durations, easeExit, easeSoft, easeSpring, springStops } from './motion';

const tokens = readFileSync(resolve(process.cwd(), 'design/tokens/mawred-tokens.css'), 'utf8');

function token(name: string): string {
    const match = tokens.match(new RegExp(`--${name}:\\s*([^;]+);`));

    if (!match?.[1]) {
        throw new Error(`Token --${name} not found`);
    }

    return match[1].trim();
}

describe('motion presets mirror tokens.css', () => {
    it('uses the token durations', () => {
        expect(`${durations.micro * 1000}ms`).toBe(token('duration-micro'));
        expect(`${durations.component * 1000}ms`).toBe(token('duration-component'));
        expect(`${durations.page * 1000}ms`).toBe(token('duration-page'));
    });

    it('uses the token easing curves', () => {
        expect(token('ease-soft')).toContain(`cubic-bezier(${easeSoft.join(', ')})`);
        expect(token('ease-exit')).toContain(`cubic-bezier(${easeExit.join(', ')})`);

        const css = springStops
            .map(([v, p], i) => (i === 0 || i === springStops.length - 1 ? `${v}` : `${v} ${p}%`))
            .join(', ');
        expect(token('ease-spring')).toBe(`linear(${css})`);
    });

    it('springs to 1 with a small overshoot', () => {
        expect(easeSpring(0)).toBe(0);
        expect(easeSpring(1)).toBe(1);
        expect(Math.max(...Array.from({ length: 101 }, (_, i) => easeSpring(i / 100)))).toBeCloseTo(
            1.025,
            3,
        );
    });
});
