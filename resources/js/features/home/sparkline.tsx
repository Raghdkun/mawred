import { motion, useReducedMotion } from 'motion/react';
import { useId } from 'react';
import { easeSoft } from '@/lib/motion';

/**
 * A KPI sparkline: the trend as one line, revealed from the start of the period to the end
 * (Motion spec: 600 ms, ease-soft); with reduced motion it's drawn at once. Plain SVG, left to
 * right in both languages (charts never mirror), decorative: the card states the trend in words.
 */
export function Sparkline({ values }: { values: number[] }) {
    const reduced = useReducedMotion();
    const clip = useId();
    const max = Math.max(...values);
    const min = Math.min(...values);
    const span = max - min || 1;
    const step = values.length > 1 ? 100 / (values.length - 1) : 100;
    const points = values
        .map((v, i) => `${(i * step).toFixed(2)},${(30 - ((v - min) / span) * 28).toFixed(2)}`)
        .join(' ');

    return (
        <svg
            viewBox="0 0 100 32"
            preserveAspectRatio="none"
            className="h-10 w-full overflow-visible"
            aria-hidden
            focusable="false"
        >
            <defs>
                <clipPath id={clip}>
                    <motion.rect
                        x={-2}
                        y={-2}
                        height={36}
                        initial={reduced ? false : { width: 0 }}
                        animate={{ width: 104 }}
                        transition={{ duration: 0.6, ease: easeSoft }}
                    />
                </clipPath>
            </defs>
            <polyline
                points={points}
                fill="none"
                stroke="var(--chart-1)"
                strokeWidth={2}
                strokeLinecap="round"
                strokeLinejoin="round"
                vectorEffect="non-scaling-stroke"
                clipPath={`url(#${clip})`}
            />
        </svg>
    );
}
