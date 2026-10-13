import { type LucideIcon } from 'lucide-react';
import { ModuleIcon } from '@/components/mw/module-icon';
import { type ModuleIconName } from '@/components/mw/module-icons.generated';
import { cn } from '@/lib/utils';

export function NavIcon({
    icon,
    className,
}: {
    icon: ModuleIconName | LucideIcon;
    className?: string;
}) {
    if (typeof icon === 'string') {
        return <ModuleIcon name={icon} className={className} />;
    }

    const Icon = icon;

    return <Icon aria-hidden strokeWidth={1.75} className={cn('size-5 shrink-0', className)} />;
}
