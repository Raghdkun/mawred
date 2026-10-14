import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars -- must match React's declaration for merging
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

export type SharedProps = {
    appName: string;
    locale: 'en' | 'ar';
    dir: 'ltr' | 'rtl';
    locales: Array<'en' | 'ar'>;
    auth: Auth | null;
    impersonation: { impersonator: string; target: string } | null;
    company: { name: Partial<Record<'en' | 'ar', string>> | null; logoUrl: string | null };
    setup: { complete: boolean };
    notifications: { unread: number } | null;
    approvals: { open: number } | null;
    sidebarOpen: boolean;
};

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: SharedProps & { [key: string]: unknown };
    }
}
