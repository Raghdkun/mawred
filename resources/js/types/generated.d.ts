declare namespace App {
    namespace Domain {
        namespace Access {
            namespace Data {
                export type PermissionModuleData = {
                    module: string;
                    actions: string[];
                    scoped: boolean;
                };
                export type RoleSummaryData = {
                    id: string;
                    name: string;
                    label: Record<string, string>;
                    description: Record<string, string>;
                    isSystem: boolean;
                    isSuperAdmin: boolean;
                    apiOnly: boolean;
                    requiresTwoFactor: boolean;
                    homeLayout: string;
                    usersCount: number;
                    permissionsCount: number;
                };
                export type UpdateRoleGrantsData = {
                    grants: Record<string, string>;
                };
                export type UserAccountData = {
                    id: string;
                    name: string;
                    email: string;
                    status: string;
                    twoFactorEnabled: boolean;
                    lastLoginAt: string | null;
                    roles: {
                        name: string;
                        label: Record<string, string>;
                    }[];
                };
                export type UserSessionData = {
                    id: string;
                    ip: string | null;
                    device: string;
                    lastSeenAt: string;
                    startedAt: string;
                    current: boolean;
                };
            }
            namespace Enums {
                export type AnchorType = 'branch' | 'department';
                export type FieldAccess = 'read' | 'write';
                export type HomeLayout = 'employee' | 'manager' | 'hr_admin';
                export type Scope =
                    'own' | 'direct_reports' | 'team_tree' | 'department' | 'branch' | 'all';
            }
        }
        namespace Audit {
            namespace Data {
                export type AuditEntryData = {
                    key: string;
                    occurredAt: string;
                    log: string | null;
                    event: string | null;
                    description: string;
                    actor: {
                        id: string;
                        name: string;
                    } | null;
                    impersonator: string | null;
                    subject: {
                        type: string;
                        id: string | null;
                        name: string | null;
                    } | null;
                    changes: Record<string, any>;
                    properties: Record<string, any>;
                    channel: string | null;
                    ip: string | null;
                    requestId: string | null;
                };
            }
        }
        namespace Notifications {
            namespace Data {
                export type NotificationItemData = {
                    id: string;
                    category: string;
                    title: string;
                    body: string | null;
                    url: string | null;
                    read: boolean;
                    createdAt: string;
                };
            }
        }
        namespace Setup {
            namespace Enums {
                export type SetupStep =
                    | 'welcome'
                    | 'company'
                    | 'languages'
                    | 'branch'
                    | 'policies'
                    | 'admin'
                    | 'email'
                    | 'whatsapp'
                    | 'cognito'
                    | 'review';
                export type StepStatus = 'pending' | 'completed' | 'skipped';
            }
        }
    }
}
