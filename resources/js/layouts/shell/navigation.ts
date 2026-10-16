import { type RouteDefinition } from '@/wayfinder';
import { dashboard } from '@/routes';
import accessRoles from '@/routes/access/roles';
import audit from '@/routes/audit';
import organizationBranches from '@/routes/organization/branches';
import forms from '@/routes/forms';
import integrations from '@/routes/integrations';
import leave from '@/routes/leave';
import schedule from '@/routes/schedule';
import attendance from '@/routes/attendance';
import people from '@/routes/people';
import policies from '@/routes/policies';
import approvals from '@/routes/approvals';
import notifications from '@/routes/notifications';
import requestTypes from '@/routes/request-types';
import requests from '@/routes/requests';
import workflowRoutes from '@/routes/workflows';
import profile from '@/routes/profile';
import setup from '@/routes/setup';
import { Building2, House, type LucideIcon, Plus, UserRound } from 'lucide-react';
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
    /** Prefixes inside `match` that belong to another item. */
    exclude?: string[];
    /** A shared-prop counter shown beside the item. */
    badge?: 'approvals';
    /** The raised centre action of the bottom tab bar. */
    primary?: boolean;
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
                {
                    key: 'requests',
                    label: 'nav.requests',
                    href: requests.index(),
                    icon: 'requests',
                    match: ['/requests'],
                },
                {
                    key: 'approvals',
                    label: 'nav.approvals',
                    href: approvals.index(),
                    icon: 'approvals',
                    match: ['/approvals'],
                    badge: 'approvals',
                },
                {
                    key: 'schedule',
                    label: 'nav.schedule',
                    href: schedule.mine(),
                    icon: 'schedule',
                    match: ['/schedule', '/roster'],
                },
                {
                    key: 'attendance',
                    label: 'nav.attendance',
                    href: attendance.clock(),
                    icon: 'attendance',
                    match: ['/attendance'],
                },
                {
                    key: 'leave',
                    label: 'nav.leave',
                    href: leave.index(),
                    icon: 'leave',
                    match: ['/leave'],
                    exclude: ['/leave/blackouts'],
                },
                {
                    key: 'people',
                    label: 'nav.people',
                    href: people.index(),
                    icon: 'people',
                    permission: 'people.view',
                    match: ['/people'],
                    exclude: ['/people/settings'],
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
                    key: 'organization',
                    label: 'nav.organization',
                    href: organizationBranches.index(),
                    icon: Building2,
                    permission: 'organization.view',
                    match: ['/organization'],
                },
                {
                    key: 'forms',
                    label: 'nav.forms',
                    href: forms.index(),
                    icon: 'forms',
                    permission: 'forms.view',
                    match: ['/forms'],
                },
                {
                    key: 'request-types',
                    label: 'nav.requestTypes',
                    href: requestTypes.index(),
                    icon: 'requests',
                    permission: 'requests.configure',
                    match: ['/request-types'],
                },
                {
                    key: 'workflows',
                    label: 'nav.workflows',
                    href: workflowRoutes.index(),
                    icon: 'workflows',
                    permission: 'workflows.view',
                    match: ['/workflows'],
                },
                {
                    key: 'policies',
                    label: 'nav.policies',
                    href: policies.index(),
                    icon: 'policies',
                    permission: 'policies.view',
                    match: ['/policies'],
                },
                {
                    key: 'integrations',
                    label: 'nav.integrations',
                    href: integrations.index(),
                    icon: 'integrations',
                    permission: 'integrations.view',
                    match: ['/integrations'],
                },
                {
                    key: 'notification-templates',
                    label: 'nav.notificationTemplates',
                    href: notifications.templates.index(),
                    icon: 'channel',
                    permission: 'notifications.configure',
                    match: ['/notifications/templates'],
                },
                {
                    key: 'people-settings',
                    label: 'nav.peopleSettings',
                    href: people.settings.fields(),
                    icon: 'people',
                    permission: 'people.configure',
                    match: ['/people/settings'],
                },
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

/**
 * Bottom tab bar on phones (handoff: Home, Requests, New, Schedule, Me). Approvals hold the
 * Schedule slot until Phase 5; notifications stay on the top bar bell.
 */
export function mobileTabs(): NavItem[] {
    return [
        { key: 'home', label: 'nav.home', href: dashboard(), icon: House, match: ['/dashboard'] },
        {
            key: 'requests',
            label: 'nav.requests',
            href: requests.index(),
            icon: 'requests',
            match: ['/requests'],
            exclude: ['/requests/new'],
        },
        {
            key: 'new',
            label: 'nav.newRequest',
            href: requests.center(),
            icon: Plus,
            match: ['/requests/new'],
            primary: true,
        },
        {
            key: 'approvals',
            label: 'nav.approvals',
            href: approvals.index(),
            icon: 'approvals',
            match: ['/approvals'],
            badge: 'approvals',
        },
        { key: 'me', label: 'nav.me', href: profile.edit(), icon: UserRound, match: ['/settings'] },
    ];
}

export function hrefOf(href: NavItem['href']): string {
    return typeof href === 'string' ? href : href.url;
}

export function isActive(item: NavItem, currentPath: string): boolean {
    const under = (prefix: string) =>
        currentPath === prefix || currentPath.startsWith(`${prefix}/`);

    return item.match.some(under) && !(item.exclude ?? []).some(under);
}
