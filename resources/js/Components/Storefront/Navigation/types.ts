import type { AuthUser } from "../../../types";

export interface NavigationCategory {
    id: number;
    name: string;
    slug: string;
    image_url: string | null;
    products_count: number;
    children: NavigationCategory[];
}
export interface StorefrontStory {
    id: number;
    title: string;
    excerpt: string | null;
    media_type: "image" | "video";
    media_url: string;
    thumbnail_url: string | null;
    duration: number | null;
    link_url: string | null;
}

export interface StorefrontNavigationProps {
    announcement?: {
        enabled: boolean;
        text: string;
        url: string;
    };
    categories: NavigationCategory[];
    stories?: StorefrontStory[];
    user: AuthUser | null;
    freshContentAt?: string | null;
}

export type StorefrontTheme = "light" | "dark";
