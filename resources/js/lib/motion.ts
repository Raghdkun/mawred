import { type Transition, type Variants } from 'motion/react';

/**
 * Motion language (design/canvas-source/Motion.dc.html, tokens.css): "everything moves like
 * water settling". Soft spring for surfaces, never overshoot on data. Values mirror the CSS
 * tokens; `motion.test.ts` fails if they drift.
 */
export const durations = {
    micro: 0.15,
    component: 0.25,
    page: 0.4,
} as const;

/** --ease-soft: default, data, numbers, charts. */
export const easeSoft = [0.22, 1, 0.36, 1] as const;

/** --ease-exit. */
export const easeExit = [0.4, 0, 1, 1] as const;

/** --ease-spring: linear() stops (progress, at%), 2.5 % overshoot — UI surfaces only. */
export const springStops: ReadonlyArray<readonly [number, number]> = [
    [0, 0],
    [0.06, 2.5],
    [0.22, 6],
    [0.5, 12],
    [0.76, 19],
    [0.92, 27],
    [1.01, 37],
    [1.025, 45],
    [1.012, 58],
    [1.002, 72],
    [1, 100],
];

/** Piecewise-linear easing equivalent to the CSS `linear()` spring. */
export function easeSpring(t: number): number {
    const at = Math.min(Math.max(t, 0), 1) * 100;

    for (let i = 1; i < springStops.length; i++) {
        const [v1, p1] = springStops[i] as readonly [number, number];
        const [v0, p0] = springStops[i - 1] as readonly [number, number];

        if (at <= p1) {
            return v0 + ((v1 - v0) * (at - p0)) / (p1 - p0);
        }
    }

    return 1;
}

export type Direction = 'ltr' | 'rtl';

/** Page enter: 400 ms fade + 8 px slide in reading direction; reduced → 150 ms crossfade. */
export function pageTransition(
    dir: Direction,
    reduced: boolean,
): { variants: Variants; transition: Transition } {
    if (reduced) {
        return {
            variants: { initial: { opacity: 0 }, animate: { opacity: 1 } },
            transition: { duration: durations.micro },
        };
    }

    return {
        variants: {
            initial: { opacity: 0, x: dir === 'rtl' ? 8 : -8 },
            animate: { opacity: 1, x: 0 },
        },
        transition: { duration: durations.page, ease: easeSoft },
    };
}

/** Popover, menu, toast, sheet: 250 ms spring in (scale .97 → 1), 150 ms ease-exit out. */
export function componentTransition(reduced: boolean): { variants: Variants } {
    if (reduced) {
        return {
            variants: {
                initial: { opacity: 0 },
                animate: { opacity: 1, transition: { duration: durations.micro } },
                exit: { opacity: 0, transition: { duration: durations.micro } },
            },
        };
    }

    return {
        variants: {
            initial: { opacity: 0, scale: 0.97 },
            animate: {
                opacity: 1,
                scale: 1,
                transition: { duration: durations.component, ease: easeSpring },
            },
            exit: {
                opacity: 0,
                scale: 0.97,
                transition: { duration: durations.micro, ease: easeExit },
            },
        },
    };
}

/** Drag lift: shadow-lg, scale 1.02, tilt 1.5° (−1.5° in RTL); reduced → shadow only. */
export function dragLift(dir: Direction, reduced: boolean): { scale: number; rotate: number } {
    return reduced ? { scale: 1, rotate: 0 } : { scale: 1.02, rotate: dir === 'rtl' ? -1.5 : 1.5 };
}
