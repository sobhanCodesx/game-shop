export interface AuthUser {
    id: number;
    name: string;
    email: string;
    avatar: string | null;
    avatar_url: string | null;
    role: string;
    is_admin: boolean;
    has_password: boolean;
}

export interface SharedPageProps {
    auth: { user: AuthUser | null };
    flash: { success: string | null; error: string | null };
    admin: { pending_orders_count: number; open_tickets_count: number } | null;
    impersonation: { active: boolean; admin_name: string | null } | null;
    notifications: {
        unread_count: number;
        latest: {
            id: string;
            title: string;
            message: string;
            url?: string;
            read_at: string | null;
            created_at: string;
        }[];
    } | null;
    cart: { item_count: number };
    storefront: {
        categories: import("../Components/Storefront/Navigation/types").NavigationCategory[];
        stories: import("../Components/Storefront/Navigation/types").StorefrontStory[];
        fresh_content_at: string | null;
        android_app: {
            version: string;
            version_code: number;
            file_size: number;
            released_at: string | null;
            download_url: string;
        } | null;
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
    meta_badges: Array<{
        key: string;
        label: string;
        value: string;
        tone: "success" | "danger" | "warning" | "accent" | "info" | "neutral";
    }>;
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
    media_type?: "image" | "video" | null;
    views: number;
    likes_count: number;
    comments_count: number;
    is_liked: boolean;
    allow_comments: boolean;
    published_at: string | null;
    channel: {
        id: number;
        name: string;
        url: string;
        avatar_url: string | null;
    } | null;
}

export type FeedItemType =
    | "post"
    | "news"
    | "article"
    | "video"
    | "clip"
    | "trailer"
    | "game_update"
    | "review"
    | "image";

export type FeedMedia =
    | {
          id: number;
          type: "image";
          url: string;
          thumbnail: null;
          width: number | null;
          height: number | null;
          duration: null;
          alt: string;
      }
    | {
          id: number;
          type: "video";
          url: string;
          thumbnail: string | null;
          width: number | null;
          height: number | null;
          duration: number | null;
          alt: string;
      };

export interface FeedItemData {
    id: number;
    type: FeedItemType;
    title: string;
    body: string | null;
    body_html: string | null;
    badge: string | null;
    url: string;
    feed_slug: string;
    created_at: string;
    media: FeedMedia[];
    author: { name: string; avatar_url: string | null; url: string | null };
    likes_count: number;
    comments_count: number;
    is_liked: boolean;
    is_saved: boolean;
    allow_comments: boolean;
    related_product: {
        id: number;
        title: string;
        url: string;
        image_url: string | null;
        price: number;
    } | null;
    related_video: { id: number; title: string; url: string } | null;
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
