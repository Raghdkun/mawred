import { type RouteDefinition } from '@/wayfinder';
import { dashboard } from '@/routes';
import accessRoles from '@/routes/access/roles';
import audit from '@/routes/audit';
import notifications from '@/routes/notifications';
import profile from '@/routes/profile';
import setup from '@/routes/setup';
import { Bell, House, type LucideIcon, UserRound } from 'lucide-react';
import { type ModuleIconName } from '@/components/mw/module-icons.generated';

export type NavItem = {
    key: string;
    /** i18n key in the `shell` namespace. */
    label: string;
    href: RouteDefinition<'get'> | string;
    /** A design module icon name, or a lucide glyph for generic destinations. */
    icon: ModuleIconName | LucideIcon;
    /** Visible when the user holds this permission (UI hint only; the server enforces). */
    permission?: string;
    /** URL prefixes that mark the item active. */
    match: string[];
};

export type NavGroup = { key: 'workspace' | 'admin'; label: string; items: NavItem[] };

/**
 * Sidebar structure from the design system (DSNavigation: Workspace + Admin groups). Modules
 * appear here as their phase ships; nothing links to a page that doesn't exist yet.
 */
export function navigation(setupComplete: boolean): NavGroup[] {
    return [
        {
            key: 'workspace',
            label: 'nav.workspace',
            items: [
                {
                    key: 'home',
                    label: 'nav.home',
                    href: dashboard(),
                    icon: House,
                    match: ['/dashboard'],
                },
            ],
        },
        {
            key: 'admin',
            label: 'nav.admin',
            items: [
                ...(!setupComplete
                    ? [
                          {
                              key: 'setup',
                              label: 'nav.setup',
                              href: setup.show(),
                              icon: 'setup' as const,
                              permission: 'setup.configure',
                              match: ['/setup'],
                          },
                      ]
                    : []),
                {
                    key: 'access',
                    label: 'nav.access',
                    href: accessRoles.index(),
                    icon: 'settings',
                    permission: 'access.view',
                    match: ['/access'],
                },
                {
                    key: 'audit',
                    label: 'nav.audit',
                    href: audit.index(),
                    icon: 'reports',
                    permission: 'audit.view',
                    match: ['/audit'],
                },
            ],
        },
    ];
}

/** Bottom tab bar on phones (handoff: Home, Requests, New, Schedule, Me). Modules join as they ship. */
export function mobileTabs(): NavItem[] {
    return [
        { key: 'home', label: 'nav.home', href: dashboard(), icon: House, match: ['/dashboard'] },
        {
            key: 'notifications',
            label: 'nav.notifications',
            href: notifications.index(),
            icon: Bell,
            match: ['/notifications'],
        },
        { key: 'me', label: 'nav.me', href: profile.edit(), icon: UserRound, match: ['/settings'] },
    ];
}

export function hrefOf(href: NavItem['href']): string {
    return typeof href === 'string' ? href : href.url;
}

export function isActive(item: NavItem, currentPath: string): boolean {
    return item.match.some(
        (prefix) => currentPath === prefix || currentPath.startsWith(`${prefix}/`),
    );
}
