import { type ReactElement, cloneElement } from 'react';
import { cn } from '@/lib/utils';

/**
 * Wraps an icon that implies direction (arrows, chevrons, back, progress) so it mirrors in RTL
 * (handoff global RTL rules). Never use for checkmarks, clocks, media, search or logos.
 */
export function DirIcon({ children }: { children: ReactElement<{ className?: string }> }) {
    return cloneElement(children, { className: cn(children.props.className, 'rtl:-scale-x-100') });
}
