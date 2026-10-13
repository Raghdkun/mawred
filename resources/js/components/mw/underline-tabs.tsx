import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

/**
 * Route tabs (DSNavigation `.tab`): 44 px, underline in primary for the active tab, scrolls
 * horizontally on phones and flows in the reading direction.
 */
export function UnderlineTabs({
    label,
    items,
}: {
    label: string;
    items: Array<{ key: string; label: string; href: string; active: boolean }>;
}) {
    return (
        <nav aria-label={label} className="-mx-4 mb-6 overflow-x-auto px-4 md:mx-0 md:px-0">
            <div className="flex min-w-max gap-1 border-b border-border">
                {items.map((item) => (
                    <Link
                        key={item.key}
                        href={item.href}
                        aria-current={item.active ? 'page' : undefined}
                        className={cn(
                            'relative flex h-11 items-center gap-2 rounded-t-sm px-3.5 text-sm text-text-secondary transition-colors duration-150 ease-soft hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                            item.active &&
                                'font-medium text-foreground after:absolute after:inset-x-2.5 after:-bottom-px after:h-0.5 after:rounded-full after:bg-primary hover:bg-transparent',
                        )}
                    >
                        {item.label}
                    </Link>
                ))}
            </div>
        </nav>
    );
}
