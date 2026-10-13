declare namespace App {
namespace Domain {
namespace Access {
namespace Data {
export type PermissionModuleData = {
module: string,
actions: string[],
scoped: boolean,
};
export type RoleSummaryData = {
id: string,
name: string,
label: Record<string, string>,
description: Record<string, string>,
isSystem: boolean,
isSuperAdmin: boolean,
apiOnly: boolean,
requiresTwoFactor: boolean,
homeLayout: string,
usersCount: number,
permissionsCount: number,
};
export type UpdateRoleGrantsData = {
grants: Record<string, string>,
};
export type UserAccountData = {
id: string,
name: string,
email: string,
status: string,
twoFactorEnabled: boolean,
lastLoginAt: string | null,
roles: {
name: string,
label: Record<string, string>,
}[],
};
export type UserSessionData = {
id: string,
ip: string | null,
device: string,
lastSeenAt: string,
startedAt: string,
current: boolean,
};
}
namespace Enums {
export type AnchorType = 'branch' | 'department';
export type FieldAccess = 'read' | 'write';
export type HomeLayout = 'employee' | 'manager' | 'hr_admin';
export type Scope = 'own' | 'direct_reports' | 'team_tree' | 'department' | 'branch' | 'all';
}
}
namespace Audit {
namespace Data {
export type AuditEntryData = {
key: string,
occurredAt: string,
log: string | null,
event: string | null,
description: string,
actor: {
id: string,
name: string,
} | null,
impersonator: string | null,
subject: {
type: string,
id: string | null,
name: string | null,
} | null,
changes: Record<string, any>,
properties: Record<string, any>,
channel: string | null,
ip: string | null,
requestId: string | null,
};
}
}
namespace Notifications {
namespace Data {
export type NotificationItemData = {
id: string,
category: string,
title: string,
body: string | null,
url: string | null,
read: boolean,
createdAt: string,
};
}
}
namespace Organization {
namespace Data {
export type BranchData = {
id: string,
code: string,
name: Record<string, string>,
address: Record<string, string> | null,
countryCode: string,
timezone: string,
defaultCurrency: string,
phoneRegion: string,
weekendDays: number[],
workStartsAt: string,
workEndsAt: string,
geofence: {
lat: number,
lng: number,
radiusM: number,
} | null,
costCenterId: string | null,
isActive: boolean,
};
export type BranchInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
countryCode: string,
timezone: string,
defaultCurrency: string,
phoneRegion: string,
weekendDays: number[],
workStartsAt: string,
workEndsAt: string,
address: {
line1?: string | null,
line2?: string | null,
city?: string | null,
postal_code?: string | null,
} | null,
geofence: {
lat: number,
lng: number,
radiusM: number,
} | null,
costCenterId: string | null,
isActive: boolean,
};
export type CatalogEntryData = {
id: string,
code: string,
name: Record<string, string>,
rank: number | null,
isActive: boolean,
};
export type CatalogEntryInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
rank: number | null,
isActive: boolean,
};
export type DepartmentData = {
id: string,
code: string,
name: Record<string, string>,
parentId: string | null,
depth: number,
costCenterId: string | null,
isActive: boolean,
};
export type DepartmentInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
parentId: string | null,
costCenterId: string | null,
isActive: boolean,
};
export type PositionData = {
id: string,
code: string,
title: Record<string, string>,
departmentId: string | null,
gradeId: string | null,
isActive: boolean,
};
export type PositionInputData = {
code: string,
title: {
en: string,
ar?: string | null,
},
departmentId: string | null,
gradeId: string | null,
isActive: boolean,
};
}
namespace Enums {
export type CatalogKind = 'employment-types' | 'grades' | 'cost-centers';
}
}
namespace Setup {
namespace Enums {
export type SetupStep = 'welcome' | 'company' | 'languages' | 'branch' | 'policies' | 'admin' | 'email' | 'whatsapp' | 'cognito' | 'review';
export type StepStatus = 'pending' | 'completed' | 'skipped';
}
}
}
}
