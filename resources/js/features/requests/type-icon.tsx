import { ModuleIcon } from '@/components/mw/module-icon';
import { type ModuleIconName, moduleIcons } from '@/components/mw/module-icons.generated';

/** A request type's module icon; unknown or missing icons fall back to the requests glyph. */
export function RequestTypeIcon({ icon, className }: { icon: string | null; className?: string }) {
    const name: ModuleIconName =
        icon !== null && icon in moduleIcons ? (icon as ModuleIconName) : 'requests';

    return <ModuleIcon name={name} aria-hidden className={className} />;
}
