import { motion, useReducedMotion } from 'motion/react';
import { easeSoft } from '@/lib/motion';

/** The ring that spreads from the check-in button when a punch is saved (handoff 25). */
export function PunchRipple() {
    const reduced = useReducedMotion();

    return (
        <motion.span
            aria-hidden
            className="pointer-events-none absolute inset-0 rounded-full border-4 border-primary"
            initial={{ opacity: 0.6, scale: 1 }}
            animate={{ opacity: 0, scale: reduced ? 1 : 1.4 }}
            transition={{ duration: reduced ? 0.2 : 0.7, ease: easeSoft }}
        />
    );
}
