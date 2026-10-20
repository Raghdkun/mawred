import { animate, useReducedMotion } from 'motion/react';
import { useEffect, useState } from 'react';
import { easeSoft } from '@/lib/motion';

/**
 * Counts a KPI up from zero when it appears (Motion spec: 800 ms, ease-soft, never overshoots).
 * With reduced motion the final value shows at once.
 */
export function useCountUp(value: number, decimals = 0): number {
    const reduced = useReducedMotion();
    const [shown, setShown] = useState(0);

    useEffect(() => {
        if (reduced) {
            return;
        }

        const controls = animate(0, value, {
            duration: 0.8,
            ease: easeSoft,
            onUpdate: (latest) => setShown(Number(latest.toFixed(decimals))),
        });

        return () => controls.stop();
    }, [value, decimals, reduced]);

    return reduced ? value : shown;
}
