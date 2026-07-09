export type UserRole = 'profiler' | 'admin' | 'user';

export type User = {
    id: number;
    name: string;
    email: string;
    role: UserRole | null;
    role_label: string | null;
    is_profiler: boolean;
    is_admin: boolean;
    can_manage_users: boolean;
    has_office_access: boolean;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};
