export type Profile = App.Domain.People.Data.EmployeeProfileData;
export type Assignment = App.Domain.People.Data.AssignmentData;
export type EmployeeDocument = App.Domain.People.Data.EmployeeDocumentData;
export type HistoryEntry = App.Domain.People.Data.HistoryEntryData;
export type Asset = App.Domain.People.Data.AssetData;
export type Summary = App.Domain.People.Data.EmployeeSummaryData;
export type Catalog = App.Domain.Organization.Data.CatalogEntryData;
export type DocumentType = {
    id: string;
    code: string;
    name: Record<string, string>;
    requiresExpiry: boolean;
};

export type ProfileProps = {
    profile: Profile;
    tab: string;
    assignments: Assignment[];
    documents: EmployeeDocument[];
    history: HistoryEntry[];
    historyNames: Record<string, Record<string, string>>;
    defaultCurrency: string;
    assets: Asset[];
    directReports: Summary[];
    options: { grades: Catalog[]; employmentTypes: Catalog[]; documentTypes: DocumentType[] };
    can: {
        update: boolean;
        terminate: boolean;
        invite: boolean;
        policies: boolean;
        salary: { read: boolean; write: boolean };
    };
};

/** Days from today to a calendar date (negative when it has passed). */
export function daysUntil(date: string): number {
    const today = new Date();
    const start = Date.UTC(today.getFullYear(), today.getMonth(), today.getDate());
    const [year, month, day] = date.split('-').map(Number);

    return Math.round((Date.UTC(year!, month! - 1, day!) - start) / 86_400_000);
}

export function today(): string {
    const now = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}
