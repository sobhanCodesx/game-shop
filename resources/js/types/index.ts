export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    avatar_url: string | null;
    role: string;
    is_admin: boolean;
}

export interface SharedPageProps {
    auth: { user: AuthUser | null };
    flash: { success: string | null; error: string | null };
    admin: { pending_orders_count: number; open_tickets_count: number } | null;
    notifications: {
        unread_count: number;
        latest: {
            id: string;
            title: string;
            message: string;
            url?: string;
            read_at: string | null;
        }[];
    } | null;
    cart: { item_count: number };
    storefront: {
        categories: import("../Components/Storefront/Navigation/types").NavigationCategory[];
        stories: import("../Components/Storefront/Navigation/types").StorefrontStory[];
    };
    [key: string]: unknown;
}

export interface StorefrontPricing {
    regular_price: number;
    sale_price: number;
    final_price: number;
    is_partner_price: boolean;
    discount_amount: number;
}

export interface StorefrontProduct {
    id: number;
    title: string;
    slug: string;
    url: string;
    category: string | null;
    badge: string | null;
    product_type: string | null;
    availability: string;
    stock: number | null;
    trade_enabled: boolean;
    cover_url: string | null;
    cover_alt: string;
    variants_count: number | null;
    pricing: StorefrontPricing;
}

export interface StorefrontContent {
    id: number;
    type: "post" | "video" | "short";
    title: string;
    slug: string;
    url: string;
    excerpt: string | null;
    thumbnail_url: string | null;
    video_url: string | null;
    duration: number | null;
    views: number;
    published_at: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}
export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
}
