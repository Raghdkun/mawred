import { usePage } from '@inertiajs/react';

/**
 * Client-side permission checks for showing/hiding UI. The server always re-checks
 * (policies + ScopeResolver); this only avoids offering actions the user can't take.
 */
export function useAccess() {
    const { auth } = usePage().props;
    const permissions = new Set(auth?.access.permissions ?? []);
    const isSuperAdmin = auth?.access.isSuperAdmin ?? false;

    return {
        can: (permission: string): boolean => isSuperAdmin || permissions.has(permission),
        isSuperAdmin,
        homeLayout: auth?.access.homeLayout ?? 'employee',
    };
}
