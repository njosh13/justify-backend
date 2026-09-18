export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type CurrentFirm = {
    id: string;
    name: string;
    vat_registered: boolean;
    default_cost_basis: 'party_party' | 'advocate_client';
    rounding_policy: string;
    role: 'owner' | 'admin' | 'advocate' | 'accounts' | 'readonly' | null;
};

export type Auth = {
    user: User;
    firm: CurrentFirm | null;
    firms: { id: string; name: string }[];
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
