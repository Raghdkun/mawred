/** Shared auth payload from HandleInertiaRequests (only public ids and display data). */
export type User = {
    id: string; // ULID (ADR-0003)
    name: string;
    /** The linked employee's name in the current language, else the account name. */
    displayName: string;
    email: string;
    emailVerified: boolean;
    twoFactorEnabled: boolean;
    preferences: {
        locale: 'en' | 'ar';
        digits: 'latn' | 'arab';
        showHijri: boolean;
        timezone: string | null;
    };
};

export type HomeLayout = 'employee' | 'manager' | 'hr_admin';

export type Access = {
    permissions: string[];
    roles: string[];
    isSuperAdmin: boolean;
    homeLayout: HomeLayout;
    requiresTwoFactor: boolean;
};

export type Auth = {
    user: User;
    access: Access;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
