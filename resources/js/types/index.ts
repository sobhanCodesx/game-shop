export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    role: string;
    is_admin: boolean;
}

export interface SharedPageProps {
    auth: { user: AuthUser | null };
    flash: { success: string | null; error: string | null };
    [key: string]: unknown;
}
