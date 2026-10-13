import { type SVGProps } from 'react';
import { cn } from '@/lib/utils';
import { type ModuleIconName, moduleIcons } from './module-icons.generated';

/** Module icons that imply a direction and mirror in Arabic (handoff global RTL rules). */
const MIRRORED: ReadonlySet<ModuleIconName> = new Set(['requests', 'workflows', 'interviews']);

export function ModuleIcon({
    name,
    className,
    ...props
}: { name: ModuleIconName } & SVGProps<SVGSVGElement>) {
    const Icon = moduleIcons[name];

    return (
        <Icon
            className={cn('size-5 shrink-0', MIRRORED.has(name) && 'rtl:-scale-x-100', className)}
            {...props}
        />
    );
}
