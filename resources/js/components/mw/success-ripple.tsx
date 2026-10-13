import { Check } from 'lucide-react';
import { motion, useReducedMotion } from 'motion/react';
import { easeSoft } from '@/lib/motion';

/**
 * Success moment (Motion spec): the check draws in 200 ms, then three rings ripple out
 * (600 ms, 90 ms stagger). Reduced motion: a static check with one ring.
 */
export function SuccessRipple({ label }: { label: string }) {
    const reduced = useReducedMotion() ?? false;
    const rings = reduced ? [0] : [0, 1, 2];

    return (
        <div
            role="img"
            aria-label={label}
            className="relative mx-auto flex size-28 items-center justify-center text-success"
        >
            {rings.map((ring) => (
                <motion.span
                    key={ring}
                    aria-hidden
                    className="absolute inset-0 rounded-full border-2 border-current"
                    initial={reduced ? { opacity: 0.25, scale: 0.8 } : { opacity: 0.5, scale: 0.4 }}
                    animate={reduced ? { opacity: 0.25, scale: 0.8 } : { opacity: 0, scale: 1 }}
                    transition={{ duration: 0.6, delay: 0.2 + ring * 0.09, ease: easeSoft }}
                />
            ))}
            <motion.span
                className="relative flex size-14 items-center justify-center rounded-full bg-success-bg"
                initial={reduced ? false : { scale: 0.6, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ duration: 0.2, ease: easeSoft }}
            >
                <Check aria-hidden className="size-7" strokeWidth={2.25} />
            </motion.span>
        </div>
    );
}
