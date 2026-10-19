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
namespace Attendance {
namespace Data {
export type AttendanceDayData = {
id: string,
employeeId: string,
nameEn: string,
nameAr: string | null,
number: string,
date: string,
status: string,
scheduleSource: string,
scheduledStart: string | null,
scheduledEnd: string | null,
firstIn: string | null,
lastOut: string | null,
workedMinutes: number,
lateMinutes: number,
earlyLeaveMinutes: number,
overtimeMinutes: number,
overtimeStatus: string,
overtimeApprovedMinutes: number,
flags: string[],
corrected: boolean,
flaggedPunches: boolean,
};
export type PunchData = {
id: string,
kind: string,
source: string,
occurredAt: string,
date: string,
geofence: string,
flagged: boolean,
reason: string | null,
voided: boolean,
};
}
namespace Enums {
export type DayStatus = 'scheduled' | 'present' | 'late' | 'absent' | 'incomplete' | 'on_leave' | 'off';
export type GeofenceResult = 'inside' | 'outside' | 'unknown' | 'off';
export type OvertimeStatus = 'none' | 'pending' | 'approved' | 'rejected' | 'auto';
export type PunchKind = 'in' | 'out';
export type PunchSource = 'pwa' | 'kiosk' | 'correction' | 'import';
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
namespace Calendar {
namespace Data {
export type BookingPageInputData = {
title: {
en: string,
ar?: string | null,
},
durationMinutes: number,
windows: {
weekday: number,
from: string,
to: string,
}[],
timezone: string,
startsOn: string,
endsOn: string | null,
bufferMinutes: number,
description: {
en?: string | null,
ar?: string | null,
} | null,
location: string | null,
audience: string,
branchId: string | null,
departmentId: string | null,
};
export type EventData = {
id: string,
key: string,
kind: string,
title: string,
description: string | null,
location: string | null,
startsAt: string,
endsAt: string,
timezone: string,
private: boolean,
recurrence: {
freq: string,
interval?: number,
byweekday?: number[],
until?: string | null,
count?: number | null,
} | null,
repeats: boolean,
organizer: string,
isOrganizer: boolean,
myResponse: string | null,
participants: {
id: string,
name: string,
role: string,
response: string,
}[],
};
export type EventInputData = {
title: string,
startsAt: string,
endsAt: string,
timezone: string,
required: string[],
optional: string[],
description: string | null,
location: string | null,
private: boolean,
recurrence: {
freq: string,
interval?: number,
byweekday?: number[],
until?: string | null,
count?: number | null,
} | null,
};
}
namespace Enums {
export type EventKind = 'meeting' | 'booking' | 'interview' | 'training';
export type ParticipantRole = 'organizer' | 'required' | 'optional';
export type Response = 'pending' | 'accepted' | 'tentative' | 'declined';
export type Visibility = 'normal' | 'private';
}
}
namespace Forms {
namespace Data {
export type FormData = {
id: string,
code: string,
name: Record<string, string>,
description: Record<string, string>,
category: string | null,
status: string,
publishedNumber: number | null,
publishedAt: string | null,
isPublic: boolean,
publicSlug: string | null,
publicUrl: string | null,
successMessage: Record<string, string>,
closesAt: string | null,
submissionsCount: number,
};
export type FormSettingsData = {
code: string,
name: {
en: string,
ar?: string | null,
},
description: {
en?: string | null,
ar?: string | null,
},
category: string | null,
isPublic: boolean,
publicSlug: string | null,
successMessage: {
en?: string | null,
ar?: string | null,
},
closesAt: string | null,
};
export type SubmissionData = {
id: string,
createdAt: string,
source: string,
version: number,
submittedBy: string | null,
answers: Record<string, any> | null,
};
}
namespace Enums {
export type FieldType = 'text' | 'textarea' | 'number' | 'money' | 'email' | 'phone' | 'date' | 'date_range' | 'time' | 'select' | 'multiselect' | 'radio' | 'checkboxes' | 'boolean' | 'file' | 'employee' | 'department' | 'branch' | 'leave_type' | 'calculated' | 'note';
export type SubmissionSource = 'internal' | 'public' | 'test' | 'cognito';
export type VersionStatus = 'draft' | 'published' | 'archived';
}
}
namespace Hiring {
namespace Data {
export type ApplicationDetailData = {
id: string,
job: {
id: string,
number: string,
title: string,
open: boolean,
},
stageId: string,
stages: App.Domain.Hiring.Data.StageData[],
status: string,
source: string,
appliedAt: string,
stageEnteredAt: string,
knockouts: string[],
rejectionReason: string | null,
schema: Record<string, any> | null,
answers: Record<string, any>,
display: Record<string, string>,
cv: {
url: string,
name: string,
mime: string,
} | null,
events: App.Domain.Hiring.Data.ApplicationEventData[],
can: {
update: boolean,
see: boolean,
},
interviews: App.Domain.Hiring.Data.InterviewData[],
criteria: {
id: string,
name: string,
}[],
offers: App.Domain.Hiring.Data.OfferData[],
hire: {
nameEn: string,
hireDate: string,
branchId: string,
branch: string | null,
departmentId: string | null,
department: string | null,
positionId: string | null,
position: string | null,
managerId: string | null,
manager: string | null,
employmentTypeId: string | null,
} | null,
employeeId: string | null,
score: number | null,
offerDefaults: {
jobTitle: string,
branchId: string,
branch: string | null,
departmentId: string | null,
department: string | null,
positionId: string | null,
position: string | null,
managerId: string | null,
manager: string | null,
currency: string,
salaryMinor: number | null,
} | null,
};
export type ApplicationEventData = {
kind: string,
at: string,
actor: string | null,
fromStage: string | null,
toStage: string | null,
body: string | null,
data: Record<string, any>,
};
export type BoardCardData = {
id: string,
candidateId: string,
name: string,
stageId: string,
status: string,
source: string,
appliedAt: string,
stageEnteredAt: string,
knockedOut: boolean,
knockouts: string[],
tags: string[],
possibleDuplicate: boolean,
rejectionReason: string | null,
score: number | null,
interviewers: string[],
nextInterviewAt: string | null,
};
export type CandidateMessageData = {
id: string,
direction: string,
channel: string,
purpose: string,
subject: string | null,
body: string,
status: string,
error: string | null,
by: string | null,
at: string,
};
export type CandidateProfileData = {
id: string,
name: string,
email: string | null,
phone: string | null,
locale: string,
location: string | null,
source: string,
talentPool: boolean,
talentPoolConsentedAt: string | null,
consentedAt: string | null,
tags: string[],
duplicateOf: {
id: string,
name: string,
} | null,
createdAt: string,
};
export type CandidateRowData = {
id: string,
name: string,
location: string | null,
tags: string[],
talentPool: boolean,
possibleDuplicate: boolean,
lastActivityAt: string,
applications: {
id: string,
job: string,
stage: string,
status: string,
}[],
};
export type CareersPostingData = {
slug: string,
title: string,
summary: string | null,
description: string,
location: string | null,
workplace: string,
employmentType: string | null,
department: string | null,
branch: string,
salary: {
min: number | null,
max: number | null,
currency: string,
} | null,
publishedAt: string | null,
closesOn: string | null,
};
export type HireCandidateData = {
nameEn: string,
hireDate: string,
branchId: string,
nameAr: string | null,
departmentId: string | null,
positionId: string | null,
managerId: string | null,
employmentTypeId: string | null,
workEmail: string | null,
};
export type HiringSettingsData = {
careersIntro: {
en: string,
ar?: string | null,
},
privacyNotice: {
en: string,
ar?: string | null,
},
retentionMonths: number | null,
poolRetentionMonths: number | null,
rejectionMessage: {
enabled: boolean,
subject: {
en: string,
ar?: string | null,
},
body: {
en: string,
ar?: string | null,
},
} | null,
offerLetter: {
en: string,
ar?: string | null,
} | null,
};
export type InterviewData = {
id: string,
title: string,
status: string,
mode: string,
location: string | null,
durationMinutes: number,
timezone: string,
startsAt: string | null,
endsAt: string | null,
bookingUrl: string | null,
panel: {
id: string,
name: string,
}[],
mine: App.Domain.Hiring.Data.ScorecardData | null,
scorecards: App.Domain.Hiring.Data.ScorecardData[],
submitted: number,
can: {
score: boolean,
manage: boolean,
readOthers: boolean,
},
};
export type InterviewInputData = {
title: string,
durationMinutes: number,
panel: string[],
timezone: string,
mode: string,
location: string | null,
startsAt: string | null,
};
export type JobPostingInputData = {
title: {
en: string,
ar?: string | null,
},
description: {
en: string,
ar?: string | null,
},
summary: {
en?: string | null,
ar?: string | null,
} | null,
location: {
en?: string | null,
ar?: string | null,
} | null,
workplace: string,
showSalary: boolean,
formId: string | null,
knockouts: {
field: string,
rule: any,
}[],
seo: {
title?: {
en?: string | null,
ar?: string | null,
},
description?: {
en?: string | null,
ar?: string | null,
},
} | null,
closesOn: string | null,
cognitoSourceId: string | null,
};
export type OfferData = {
id: string,
status: string,
jobTitle: string,
startOn: string,
salary: {
amount_minor: number,
currency: string,
} | null,
branchId: string | null,
branch: string | null,
departmentId: string | null,
department: string | null,
positionId: string | null,
position: string | null,
employmentTypeId: string | null,
managerId: string | null,
manager: string | null,
locale: string,
letter: string,
expiresOn: string | null,
sentAt: string | null,
respondedAt: string | null,
signedName: string | null,
declineReason: string | null,
pdfUrl: string,
};
export type OfferInputData = {
jobTitle: string,
startOn: string,
salary: {
amount_minor: number,
currency: string,
},
branchId: string,
departmentId: string | null,
positionId: string | null,
employmentTypeId: string | null,
managerId: string | null,
expiresOn: string | null,
letter: string | null,
};
export type PostingData = {
id: string,
slug: string,
url: string,
title: {
en: string,
ar: string,
},
description: {
en: string,
ar: string,
},
summary: {
en: string,
ar: string,
},
location: {
en: string,
ar: string,
},
workplace: string,
showSalary: boolean,
formId: string | null,
knockouts: {
field: string,
rule: any,
}[],
seo: {
title: {
en: string,
ar: string,
},
description: {
en: string,
ar: string,
},
},
status: string,
publishedAt: string | null,
closesOn: string | null,
applicants: number,
cognitoSourceId: string | null,
};
export type RequisitionData = {
id: string,
number: string,
title: string,
status: string,
reason: string,
branch: string,
department: string | null,
position: string | null,
headcount: number,
hiringManager: string | null,
requestId: string | null,
salaryMinMinor: number | null,
salaryMaxMinor: number | null,
currency: string | null,
targetStartOn: string | null,
justification: string | null,
openedAt: string | null,
createdAt: string,
counts: {
active: number,
total: number,
postings: number,
},
};
export type ScorecardData = {
by: string,
ratings: Record<string, {
rating: number,
note: string | null,
}>,
recommendation: string | null,
comment: string | null,
submittedAt: string | null,
};
export type StageData = {
id: string,
name: string,
names: {
en: string,
ar: string,
},
kind: string,
position: number,
automation: Record<string, any> | null,
};
}
namespace Enums {
export type ApplicationEventKind = 'applied' | 'moved' | 'rejected' | 'reopened' | 'note' | 'merged' | 'interview' | 'scorecard' | 'offer' | 'hired';
export type ApplicationSource = 'careers' | 'cognito' | 'manual' | 'referral' | 'talent_pool';
export type ApplicationStatus = 'active' | 'rejected' | 'withdrawn' | 'hired';
export type InterviewMode = 'onsite' | 'video' | 'phone';
export type InterviewStatus = 'awaiting' | 'scheduled' | 'completed' | 'cancelled';
export type MemberRole = 'recruiter' | 'hiring_manager' | 'coordinator';
export type MessageChannel = 'email' | 'whatsapp';
export type MessageDirection = 'out' | 'in';
export type MessagePurpose = 'manual' | 'stage' | 'rejection' | 'booking' | 'interview' | 'offer' | 'reply';
export type MessageStatus = 'queued' | 'sent' | 'failed' | 'received';
export type OfferStatus = 'draft' | 'pending' | 'approved' | 'rejected' | 'sent' | 'accepted' | 'declined' | 'withdrawn' | 'expired';
export type PostingStatus = 'draft' | 'published' | 'closed';
export type Recommendation = 'strong_no' | 'no' | 'yes' | 'strong_yes';
export type RejectionReason = 'not_qualified' | 'knockout' | 'better_candidate' | 'salary' | 'no_show' | 'withdrew' | 'position_closed' | 'other';
export type RequisitionReason = 'new' | 'replacement';
export type RequisitionStatus = 'pending' | 'open' | 'on_hold' | 'filled' | 'closed' | 'rejected' | 'cancelled';
export type StageKind = 'applied' | 'screening' | 'interview' | 'offer' | 'hired';
export type Workplace = 'onsite' | 'hybrid' | 'remote';
}
}
namespace Integrations {
namespace Data {
export type IntegrationCardData = {
type: string,
status: string,
checkedAt: string | null,
lastSuccessAt: string | null,
lastFailureAt: string | null,
lastError: string | null,
details: Record<string, string | null>,
};
}
namespace Enums {
export type IntegrationStatus = 'healthy' | 'degraded' | 'disconnected' | 'not_configured' | 'unverified';
export type IntegrationType = 'email' | 'whatsapp_official' | 'whatsapp_waha' | 'cognito';
export type ReceiptStatus = 'received' | 'processed' | 'needs_person' | 'failed' | 'duplicate' | 'late_update' | 'sample' | 'ignored' | 'discarded';
}
}
namespace Leave {
namespace Data {
export type HolidayMarkData = {
date: string,
name: Record<string, string>,
};
export type LeaveBalanceData = {
typeId: string,
code: string,
name: string,
unit: string,
year: number,
entitlement: number,
earned: number,
carriedOver: number,
adjusted: number,
taken: number,
reserved: number,
balance: number,
available: number,
expiring: number | null,
expiresOn: string | null,
};
export type LeaveBlackoutData = {
id: string,
name: Record<string, string>,
startsOn: string,
endsOn: string,
branchId: string | null,
branchName: Record<string, string> | null,
departmentId: string | null,
departmentName: Record<string, string> | null,
leaveTypes: {
id: string,
name: Record<string, string>,
}[],
};
export type LeaveBlackoutInputData = {
name: {
en: string,
ar?: string | null,
},
startsOn: string,
endsOn: string,
branchId: string | null,
departmentId: string | null,
leaveTypeIds: string[],
};
export type LeaveEventData = {
id: string,
employeeId: string,
employeeName: string,
employeeNameAr: string | null,
kind: string,
typeCode: string | null,
typeName: string | null,
from: string,
to: string,
halfDay: string | null,
fromTime: string | null,
toTime: string | null,
amount: number,
minutes: number | null,
status: string,
requestId: string | null,
};
export type LeaveQuoteData = {
kind: string,
typeName: string | null,
unit: string,
from: string | null,
to: string | null,
amount: number,
dates: string[],
available: number | null,
after: number | null,
attachmentRequired: boolean,
problems: {
code: string,
message: string,
}[],
};
export type LedgerEntryData = {
id: string,
kind: string,
label: string,
amount: number,
runningBalance: number,
effectiveOn: string,
expiresOn: string | null,
note: string | null,
requestId: string | null,
requestNumber: string | null,
actor: string | null,
};
}
namespace Enums {
export type LeaveRequestKind = 'leave' | 'day_off' | 'hourly';
export type LeaveRequestStatus = 'pending' | 'approved' | 'rejected' | 'cancelled';
export type LedgerKind = 'accrual' | 'cap' | 'carry_over' | 'carry_expiry' | 'adjustment' | 'debit' | 'credit';
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
namespace Enums {
export type NotificationCategory = 'approvals' | 'requests' | 'documents' | 'schedule' | 'hiring' | 'training' | 'reports' | 'security' | 'integrations';
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
export type AllowanceData = {
id: string,
type: string,
label: string | null,
amount: {
minor: number,
currency: string,
} | null,
effectiveFrom: string,
effectiveTo: string | null,
note: string | null,
};
export type AllowanceInputData = {
type: string,
amount: string,
currency: string,
effectiveFrom: string,
effectiveTo: string | null,
label: string | null,
note: string | null,
};
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
export type AllowanceType = 'housing' | 'transport' | 'food' | 'phone' | 'education' | 'other';
export type CustomFieldType = 'text' | 'textarea' | 'number' | 'date' | 'select' | 'multiselect' | 'boolean' | 'email' | 'phone' | 'url';
export type EmployeeStatus = 'pending' | 'active' | 'suspended' | 'terminated';
export type Gender = 'female' | 'male';
export type HistoryEvent = 'hired' | 'placement_changed' | 'salary_changed' | 'allowance_changed' | 'status_changed' | 'terminated';
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
export type HolidayCalendarData = {
id: string,
code: string,
name: Record<string, string>,
countryCode: string | null,
presetKey: string | null,
isActive: boolean,
branches: {
id: string,
name: Record<string, string>,
}[],
rules: App.Domain.Policies.Data.HolidayRuleData[],
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
export type HolidayDayData = {
ruleId: string,
name: Record<string, string>,
date: string,
observedOn: string,
hijriBased: boolean,
day: number,
days: number,
};
export type HolidayRuleData = {
id: string,
name: Record<string, string>,
type: string,
month: number | null,
day: number | null,
weekday: number | null,
nth: number | null,
date: string | null,
durationDays: number,
observed: string,
hijriBased: boolean,
fromYear: number | null,
toYear: number | null,
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
export type ImpactBalanceData = {
code: string,
name: Record<string, string>,
year: number,
before: number,
after: number,
delta: number,
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
balances: App.Domain.Policies.Data.ImpactBalanceData[],
};
export type LeaveTypeData = {
id: string,
code: string,
name: Record<string, string>,
unit: string,
isPaid: boolean,
allowsHalfDays: boolean,
eligibleGender: string | null,
requiresAttachment: boolean,
sortOrder: number,
isActive: boolean,
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
export type PolicyAssignmentData = {
id: string,
level: string,
targetId: string | null,
targetName: string | null,
setId: string,
setName: Record<string, string>,
effectiveFrom: string,
effectiveTo: string | null,
reason: string | null,
};
export type PolicyKeyData = {
id: string,
section: string,
editor: string,
default: Record<string, any>,
branchFallback: boolean,
affects: string[],
leaveType: App.Domain.Policies.Data.LeaveTypeData | null,
};
export type PolicySetData = {
id: string,
name: Record<string, string>,
description: Record<string, string>,
presetKey: string | null,
presetVersion: string | null,
keysCount: number,
lastChangeOn: string | null,
assignments: App.Domain.Policies.Data.PolicyAssignmentData[],
};
export type PolicySetValueData = {
key: string,
value: Record<string, any> | null,
effectiveFrom: string | null,
scheduled: {
value: Record<string, any> | null,
effectiveFrom: string,
}[],
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
attachmentAfterDays: number | null,
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
namespace Reports {
namespace Data {
export type ReportCardData = {
key: string,
category: App.Domain.Reports.Enums.ReportCategory,
title: string,
description: string,
canExport: boolean,
};
export type ReportFilterData = {
key: string,
type: App.Domain.Reports.Enums.FilterType,
required: boolean,
label: string | null,
options: {
value: string,
label: string,
}[],
};
export type ReportResultData = {
columns: {
key: string,
label: string,
type: App.Domain.Reports.Enums.ColumnType,
}[],
rows: Record<string, string | number | null>[],
total: number,
truncated: boolean,
kpis: {
label: string,
value: number,
format: string,
}[],
chart: {
type: string,
x: string,
series: {
key: string,
label: string,
}[],
data: Record<string, string | number | null>[],
} | null,
};
export type ReportRunData = {
id: string,
report: string,
title: string,
period: string,
format: App.Domain.Reports.Enums.ReportFormat,
status: App.Domain.Reports.Enums.RunStatus,
rows: number | null,
fileName: string | null,
error: string | null,
createdAt: string,
expiresAt: string | null,
};
}
namespace Enums {
export type ColumnType = 'text' | 'number' | 'decimal' | 'percent' | 'date' | 'minutes';
export type FilterType = 'period' | 'month' | 'year' | 'as_of' | 'days' | 'branch' | 'department' | 'choice';
export type ReportCategory = 'people' | 'leave' | 'time' | 'requests' | 'hiring' | 'training' | 'payroll' | 'admin';
export type ReportFormat = 'xlsx' | 'csv' | 'pdf';
export type RunStatus = 'queued' | 'running' | 'ready' | 'failed';
}
}
namespace Requests {
namespace Data {
export type RequestRowData = {
id: string,
number: string,
typeId: string,
typeCode: string,
typeName: Record<string, string>,
typeIcon: string | null,
status: string,
submittedAt: string,
decidedAt: string | null,
requester: string,
};
export type RequestTypeData = {
id: string,
code: string,
name: Record<string, string>,
description: Record<string, string>,
category: string,
icon: string | null,
isSensitive: boolean,
isActive: boolean,
available: boolean,
availableInPhase: number,
formId: string | null,
workflowId: string | null,
ready: boolean,
source: string,
externalUrl: string | null,
behaviour: string | null,
};
export type TaskData = {
id: string,
kind: string,
step: Record<string, string>,
assignedAt: string,
dueAt: string | null,
overdue: boolean,
delegatedFrom: string | null,
decisions: string[],
request: App.Domain.Requests.Data.RequestRowData | null,
about: string | null,
instructions: string | null,
facts: {
label: string,
value: string,
}[],
url: string | null,
};
export type TimelineEntryData = {
event: string,
at: string,
actor: string | null,
subject: string | null,
comment: string | null,
via: string | null,
data: Record<string, any>,
};
}
namespace Enums {
export type RequestCategory = 'leave' | 'time' | 'finance' | 'documents' | 'people' | 'assets' | 'training';
export type RequestStatus = 'in_review' | 'returned' | 'approved' | 'rejected' | 'completed' | 'cancelled';
}
}
namespace Scheduling {
namespace Data {
export type MyShiftData = {
id: string,
date: string,
startsAt: string,
endsAt: string,
breakMinutes: number,
paidMinutes: number,
name: Record<string, string> | null,
color: number,
branch: Record<string, string>,
claimed: boolean,
};
export type RosterShiftData = {
id: string,
employeeId: string | null,
date: string,
startsAt: string,
endsAt: string,
breakMinutes: number,
paidMinutes: number,
templateId: string | null,
color: number,
status: string,
positionId: string | null,
note: string | null,
autoAssign: boolean,
claims: number,
conflicts: string[],
};
export type RosterShiftInputData = {
date: string,
employeeId: string | null,
templateId: string | null,
startsAt: string | null,
endsAt: string | null,
breakMinutes: number | null,
positionId: string | null,
note: string | null,
autoAssign: boolean,
};
export type ShiftTemplateData = {
id: string,
code: string,
name: Record<string, string>,
startsAt: string,
endsAt: string,
breakMinutes: number,
color: number,
branchId: string | null,
isActive: boolean,
};
export type ShiftTemplateInputData = {
code: string,
name: {
en: string,
ar?: string | null,
},
startsAt: string,
endsAt: string,
breakMinutes: number,
color: number,
branchId: string | null,
isActive: boolean,
};
}
namespace Enums {
export type ClaimStatus = 'pending' | 'accepted' | 'declined';
export type ShiftStatus = 'draft' | 'published';
}
}
namespace Setup {
namespace Enums {
export type SetupStep = 'welcome' | 'company' | 'languages' | 'branch' | 'policies' | 'admin' | 'email' | 'whatsapp' | 'cognito' | 'review';
export type StepStatus = 'pending' | 'completed' | 'skipped';
}
}
namespace Training {
namespace Data {
export type CertificateData = {
id: string,
serial: string,
course: string,
holder: string,
issuedOn: string,
expiresOn: string | null,
state: string,
verifyUrl: string,
pdfUrl: string | null,
};
export type CourseCardData = {
id: string,
code: string,
title: string,
summary: string | null,
category: string,
durationMinutes: number | null,
modules: number,
requiresApproval: boolean,
mandatory: boolean,
issuesCertificate: boolean,
enrollment: App.Domain.Training.Data.EnrollmentData | null,
};
export type CourseEditorData = {
id: string,
code: string,
status: string,
title: {
en: string,
ar: string,
},
summary: {
en: string,
ar: string,
},
description: {
en: string,
ar: string,
},
category: string,
durationMinutes: number | null,
requiresApproval: boolean,
issuesCertificate: boolean,
validityMonths: number | null,
owner: {
id: string,
name: string,
} | null,
audience: {
branches: {
id: string,
name: string,
}[],
departments: {
id: string,
name: string,
}[],
positions: {
id: string,
name: string,
}[],
employmentTypes: {
id: string,
name: string,
}[],
hiredWithinDays: number | null,
} | null,
dueDays: number | null,
modules: {
id: string,
kind: string,
title: {
en: string,
ar: string,
},
body: {
en: string,
ar: string,
},
url: string | null,
fileName: string | null,
durationMinutes: number | null,
required: boolean,
passMark: number | null,
maxAttempts: number | null,
questions: {
id: string,
type: string,
prompt: {
en: string,
ar: string,
},
points: number,
options: {
id: string,
label: {
en: string,
ar: string,
},
correct: boolean,
}[],
}[],
}[],
};
export type CourseInputData = {
title: {
en: string,
ar?: string | null,
},
summary: {
en?: string | null,
ar?: string | null,
} | null,
description: {
en?: string | null,
ar?: string | null,
} | null,
category: string,
durationMinutes: number | null,
requiresApproval: boolean,
issuesCertificate: boolean,
validityMonths: number | null,
ownerId: string | null,
audience: {
branches?: string[],
departments?: string[],
positions?: string[],
employmentTypes?: string[],
hiredWithinDays?: number | null,
} | null,
dueDays: number | null,
};
export type CourseRefData = {
id: string,
code: string,
title: string,
status: string,
mandatory: boolean,
};
export type CourseRowData = {
id: string,
code: string,
title: string,
category: string,
status: string,
mandatory: boolean,
requiresApproval: boolean,
modules: number,
active: number,
requested: number,
completed: number,
};
export type EnrollmentData = {
id: string,
status: string,
source: string,
progress: number,
dueOn: string | null,
overdue: boolean,
requestedAt: string | null,
enrolledAt: string | null,
completedAt: string | null,
course: {
id: string,
code: string,
title: string,
},
employee: {
id: string,
name: string,
} | null,
};
export type ExternalTrainingData = {
id: string,
title: string,
provider: string | null,
startsOn: string | null,
endsOn: string | null,
cost: {
amountMinor: number,
currency: string,
} | null,
certificateName: string | null,
certificateUrl: string | null,
course: {
id: string,
title: string,
} | null,
};
export type LearningPathData = {
id: string,
title: string,
description: string | null,
status: string,
courses: {
id: string,
code: string,
title: string,
durationMinutes: number | null,
status: string | null,
progress: number,
}[],
joined: boolean,
};
export type LearningPathEditorData = {
id: string,
title: {
en: string,
ar: string,
},
description: {
en: string,
ar: string,
},
status: string,
courses: string[],
};
export type LearningPathInputData = {
title: {
en: string,
ar?: string | null,
},
description: {
en?: string | null,
ar?: string | null,
} | null,
courses: string[],
};
export type ModuleData = {
id: string,
kind: string,
title: string,
body: string | null,
url: string | null,
fileName: string | null,
durationMinutes: number | null,
required: boolean,
done: boolean,
quiz: {
passMark: number,
attemptsAllowed: number,
attemptsUsed: number,
passed: boolean,
bestScore: number | null,
questions: {
id: string,
type: string,
prompt: string,
points: number,
options: {
id: string,
label: string,
}[],
}[],
last: {
score: number,
passed: boolean,
correct: string[],
} | null,
} | null,
};
export type ModuleSummaryData = {
id: string,
kind: string,
title: string,
durationMinutes: number | null,
required: boolean,
done: boolean,
quiz: {
passMark: number,
attemptsAllowed: number,
attemptsUsed: number,
bestScore: number | null,
passed: boolean,
} | null,
};
export type ModulesInputData = {
modules: {
id?: string | null,
kind: string,
title: {
en: string,
ar?: string | null,
},
body?: {
en?: string | null,
ar?: string | null,
} | null,
url?: string | null,
durationMinutes?: number | null,
required?: boolean,
passMark?: number | null,
maxAttempts?: number | null,
questions?: {
id?: string | null,
type: string,
prompt: {
en: string,
ar?: string | null,
},
points?: number,
options: {
id?: string | null,
label: {
en: string,
ar?: string | null,
},
correct: boolean,
}[],
}[],
}[],
};
export type RosterRowData = {
id: string,
name: string,
status: string,
attendedAt: string | null,
checkIn: string | null,
};
export type SessionData = {
id: string,
startsAt: string,
endsAt: string,
timezone: string,
mode: string,
location: string | null,
meetingUrl: string | null,
capacity: number | null,
booked: number,
waitlisted: number,
attended: number,
trainer: string | null,
status: string,
mine: {
id: string,
status: string,
attended: boolean,
position: number | null,
} | null,
};
export type SessionInputData = {
startsAt: string,
endsAt: string,
timezone: string,
mode: string,
location: string | null,
meetingUrl: string | null,
capacity: number | null,
trainerId: string | null,
};
}
namespace Enums {
export type BookingStatus = 'booked' | 'waitlisted' | 'cancelled';
export type CourseCategory = 'compliance' | 'safety' | 'onboarding' | 'skills' | 'leadership' | 'technical';
export type CourseStatus = 'draft' | 'published' | 'archived';
export type EnrollmentSource = 'self' | 'assigned' | 'audience' | 'path' | 'request' | 'renewal';
export type EnrollmentStatus = 'requested' | 'enrolled' | 'completed' | 'rejected' | 'cancelled';
export type ModuleKind = 'text' | 'video' | 'file' | 'quiz';
export type QuestionType = 'single' | 'multiple' | 'true_false';
export type SessionMode = 'onsite' | 'online';
export type SessionStatus = 'scheduled' | 'cancelled';
}
}
namespace Workflows {
namespace Data {
export type WorkflowDefinitionData = {
id: string,
code: string,
name: Record<string, string>,
description: Record<string, string>,
category: string | null,
publishedVersion: number | null,
hasDraft: boolean,
openInstances: number,
requestTypes: Record<string, string>[],
};
}
namespace Enums {
export type Decision = 'approved' | 'rejected' | 'returned' | 'done';
export type DecisionChannel = 'web' | 'api' | 'email' | 'whatsapp' | 'system';
export type InstanceStatus = 'running' | 'returned' | 'attention' | 'completed' | 'cancelled';
export type Outcome = 'approved' | 'rejected' | 'completed' | 'cancelled';
export type StepStatus = 'pending' | 'running' | 'waiting' | 'completed' | 'failed' | 'skipped' | 'cancelled';
export type TaskKind = 'approval' | 'task';
export type TaskStatus = 'open' | 'done' | 'cancelled';
export type VersionStatus = 'draft' | 'published' | 'archived';
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
