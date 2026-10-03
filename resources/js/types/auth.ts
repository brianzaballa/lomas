export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    two_factor_enabled?: boolean;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    role_names?: string[];
    permission_names?: string[];
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorConfigContent = {
    title: string;
    description: string;
    buttonText: string;
};

export type Paginated<T> = {
    data: T[];
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    per_page: number;
};
