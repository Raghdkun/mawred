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
departmentName: Record<string, string> | null,
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
namespace People {
namespace Data {
export type AssetData = {
id: string,
name: string,
category: string | null,
assetTag: string,
serialNumber: string | null,
assignedOn: string,
dueBackOn: string | null,
returnedOn: string | null,
conditionNotes: string | null,
};
export type AssignmentData = {
id: string,
isPrimary: boolean,
isCurrent: boolean,
effectiveFrom: string,
effectiveTo: string | null,
branch: App.Domain.People.Data.RefData | null,
department: App.Domain.People.Data.RefData | null,
position: App.Domain.People.Data.RefData | null,
manager: App.Domain.People.Data.PersonRefData | null,
employmentType: App.Domain.People.Data.RefData | null,
grade: App.Domain.People.Data.RefData | null,
reason: string | null,
};
export type CustomFieldDefinitionInputData = {
key: string,
label: {
en: string,
ar?: string | null,
},
type: string,
help: {
en?: string | null,
ar?: string | null,
} | null,
options: {
value: string,
label: {
en: string,
ar?: string | null,
},
}[] | null,
section: string,
isRequired: boolean,
isSensitive: boolean,
isActive: boolean,
sortOrder: number,
};
export type CustomFieldValueData = {
key: string,
label: Record<string, string>,
help: Record<string, string>,
type: string,
section: string,
options: {
value: string,
label: Record<string, string>,
}[],
isRequired: boolean,
isSensitive: boolean,
access: string,
value: any,
};
export type DocumentTypeInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
requiresExpiry: boolean,
reminderDays: number[],
isActive: boolean,
};
export type EmployeeDocumentData = {
id: string,
type: App.Domain.People.Data.RefData | null,
number: string | null,
issuedOn: string | null,
expiresOn: string | null,
notes: string | null,
fileName: string | null,
mimeType: string | null,
size: number | null,
uploadedAt: string,
};
export type EmployeeDocumentInputData = {
documentTypeId: string,
number: string | null,
issuedOn: string | null,
expiresOn: string | null,
notes: string | null,
};
export type EmployeeProfileData = {
summary: App.Domain.People.Data.EmployeeSummaryData,
gender: string | null,
nationality: string | null,
maritalStatus: string | null,
probationEndsOn: string | null,
terminationDate: string | null,
terminationReason: string | null,
employmentType: App.Domain.People.Data.RefData | null,
grade: App.Domain.People.Data.RefData | null,
directReportsCount: number,
account: {
linked: boolean,
email: string | null,
invitation: {
email: string,
expiresAt: string,
} | null,
},
sensitive: App.Domain.People.Data.SensitiveDetailsData,
customFields: App.Domain.People.Data.CustomFieldValueData[],
};
export type EmployeeProfileInputData = {
nameEn: string,
nameAr: string | null,
preferredName: string | null,
workEmail: string | null,
workPhone: string | null,
gender: string | null,
nationality: string | null,
maritalStatus: string | null,
hireDate: string | null,
probationEndsOn: string | null,
};
export type EmployeeSummaryData = {
id: string,
number: string,
nameEn: string,
nameAr: string | null,
preferredName: string | null,
workEmail: string | null,
workPhone: string | null,
status: string,
hireDate: string,
branch: App.Domain.People.Data.RefData | null,
department: App.Domain.People.Data.RefData | null,
position: App.Domain.People.Data.RefData | null,
manager: App.Domain.People.Data.PersonRefData | null,
};
export type HireEmployeeData = {
nameEn: string,
hireDate: string,
placement: App.Domain.People.Data.PlacementInputData,
nameAr: string | null,
preferredName: string | null,
employeeNumber: string | null,
workEmail: string | null,
workPhone: string | null,
gender: string | null,
nationality: string | null,
probationEndsOn: string | null,
};
export type HistoryEntryData = {
id: string,
event: string,
effectiveOn: string,
changes: Record<string, any>,
salary: {
minor: number,
currency: string,
} | null,
note: string | null,
recordedAt: string,
};
export type ImportData = {
id: string,
status: string,
fileName: string,
headers: (string| null)[],
mapping: Record<string | number, string>,
totalRows: number,
validRows: number,
invalidRows: number,
toCreate: number,
toUpdate: number,
processedRows: number,
createdRows: number,
updatedRows: number,
failedRows: number,
failure: string | null,
hasErrorReport: boolean,
createdAt: string,
};
export type PersonRefData = {
id: string,
nameEn: string,
nameAr: string | null,
preferredName: string | null,
};
export type PlacementInputData = {
branchId: string,
departmentId: string | null,
positionId: string | null,
managerId: string | null,
employmentTypeId: string | null,
gradeId: string | null,
};
export type RefData = {
id: string,
code: string,
name: Record<string, string>,
};
export type SensitiveDetailsData = {
access: Record<string, string>,
nationalId: string | null,
passport: {
number: string | null,
country: string | null,
expiresOn: string | null,
} | null,
residency: {
number: string | null,
expiresOn: string | null,
} | null,
bank: Record<string, string | null> | null,
personalContact: Record<string, string | null> | null,
dateOfBirth: string | null,
};
export type SensitiveDetailsInputData = {
nationalId: {
number: string | null,
} | undefined,
passport: {
number: string | null,
country?: string | null,
expiresOn?: string | null,
} | undefined,
residency: {
number: string | null,
expiresOn?: string | null,
} | undefined,
bank: {
bankName?: string | null,
iban?: string | null,
accountName?: string | null,
} | undefined,
personalContact: {
email?: string | null,
phone?: string | null,
address?: string | null,
emergencyName?: string | null,
emergencyPhone?: string | null,
emergencyRelation?: string | null,
} | undefined,
dateOfBirth: undefined | string | null,
};
}
namespace Enums {
export type CustomFieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'multiselect' | 'boolean' | 'email' | 'phone' | 'url';
export type EmployeeStatus = 'pending' | 'active' | 'suspended' | 'terminated';
export type Gender = 'female' | 'male';
export type HistoryEvent = 'hired' | 'placement_changed' | 'salary_changed' | 'status_changed' | 'terminated';
export type ImportRowStatus = 'valid' | 'invalid' | 'done' | 'failed';
export type ImportStatus = 'uploaded' | 'validating' | 'validated' | 'committing' | 'completed' | 'failed' | 'cancelled';
export type MaritalStatus = 'single' | 'married' | 'divorced' | 'widowed';
}
}
namespace Policies {
namespace Data {
export type ChainLinkData = {
level: string,
setId: string | null,
setName: Record<string, string> | null,
target: string | null,
value: Record<string, any>,
effectiveFrom: string | null,
wins: boolean,
};
export type HolidayCalendarInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
countryCode: string | null,
isActive: boolean,
branchIds: string[],
};
export type HolidayRuleInputData = {
name: {
en: string,
ar?: string | null,
},
type: App.Domain.Policies.Enums.HolidayType,
month: number | null,
day: number | null,
weekday: number | null,
nth: number | null,
date: string | null,
durationDays: number,
observed: App.Domain.Policies.Enums.ObservedRule,
hijriBased: boolean,
fromYear: number | null,
toYear: number | null,
};
export type ImpactChangeData = {
key: string,
before: Record<string, any>,
after: Record<string, any>,
beforeLevel: string,
afterLevel: string,
};
export type ImpactPreviewData = {
date: string,
keys: string[],
checked: number,
affected: number,
rows: App.Domain.Policies.Data.ImpactRowData[],
};
export type ImpactRowData = {
employeeId: string,
employeeNumber: string,
nameEn: string,
nameAr: string | null,
changes: App.Domain.Policies.Data.ImpactChangeData[],
};
export type LeaveTypeInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
unit: string,
isPaid: boolean,
allowsHalfDays: boolean,
eligibleGender: string | null,
requiresAttachment: boolean,
sortOrder: number,
isActive: boolean,
};
export type ResolvedValueData = {
key: string,
section: string,
value: Record<string, any>,
level: string,
setId: string | null,
setName: Record<string, string> | null,
effectiveFrom: string | null,
chain: App.Domain.Policies.Data.ChainLinkData[],
};
namespace Values {
export type GeofenceModeValue = {
mode: string,
};
export type GraceValue = {
lateMinutes: number,
earlyLeaveMinutes: number,
countFrom: string,
};
export type HourlyPermissionValue = {
minutesPerMonth: number,
maxMinutesPerRequest: number,
};
export type LeaveRuleValue = {
entitlement: number,
accrual: string,
proRata: string,
rounding: string,
maxBalance: number | null,
carryOverMax: number | null,
carryOverExpiresMonths: number | null,
eligibleAfterDays: number,
tenureSteps: {
afterYears: number,
entitlement: number,
}[],
};
export type OvertimeValue = {
enabled: boolean,
dailyThresholdMinutes: number,
weekday: number,
weekend: number,
holiday: number,
night: number,
nightStartsAt: string,
nightEndsAt: string,
roundingMinutes: number,
monthlyCapMinutes: number | null,
requiresApproval: boolean,
};
export type ProbationValue = {
probationDays: number,
extensionDays: number,
employeeNoticeDays: number,
employerNoticeDays: number,
};
export type RestValue = {
minRestHours: number,
maxConsecutiveDays: number,
maxWeeklyHours: number,
};
export type WeekendValue = {
days: number[],
};
export type WorkHoursValue = {
startsAt: string,
endsAt: string,
breakMinutes: number,
};
}
}
namespace Enums {
export type DayKind = 'workday' | 'weekend' | 'holiday';
export type HolidayType = 'fixed' | 'nth_weekday' | 'one_off';
export type ObservedRule = 'none' | 'nearest_weekday' | 'next_working_day' | 'previous_working_day';
export type PolicyLevel = 'global' | 'branch' | 'department' | 'position' | 'grade' | 'employment_type' | 'employee';
export type PolicySection = 'leave' | 'work_week' | 'overtime' | 'attendance' | 'rest' | 'employment';
}
}
namespace Setup {
namespace Enums {
export type SetupStep = 'welcome' | 'company' | 'languages' | 'branch' | 'policies' | 'admin' | 'email' | 'whatsapp' | 'cognito' | 'review';
export type StepStatus = 'pending' | 'completed' | 'skipped';
}
}
}
namespace Support {
namespace Actors {
export type ActorType = 'user' | 'system' | 'integration' | 'token';
export type Channel = 'web' | 'api' | 'email_link' | 'whatsapp' | 'cognito' | 'system' | 'import';
}
namespace Search {
export type SearchHitData = {
type: string,
id: string,
title: string,
subtitle: string | null,
url: string,
status: string | null,
};
}
}
}
