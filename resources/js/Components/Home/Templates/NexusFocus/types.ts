import type { FeedItemData, StorefrontProduct } from "../../../../types";
import type { NavigationCategory } from "../../../Storefront/Navigation/types";

export interface NexusFocusSlide {
    id: number;
    title: string;
    alt: string | null;
    eyebrow: string | null;
    description: string | null;
    desktop_image_url: string;
    mobile_image_url: string | null;
    button_label: string | null;
    button_url: string | null;
}

export interface NexusFocusFeedItem {
    id: number;
    type: string;
    title: string;
    badge: string | null;
    url: string;
    created_at: string | null;
    media: FeedItemData["media"];
    author: FeedItemData["author"];
}

export interface NexusFocusFreshItem {
    key: string;
    type: "product" | "video";
    title: string;
    url: string;
    image_url: string | null;
    eyebrow: string;
    published_at: string;
    duration?: number | null;
}

export interface NexusFocusChannel {
    id: number;
    name: string;
    url: string;
    image_url: string | null;
    videos_count: number;
}

export interface NexusFocusStudio {
    id: number;
    name: string;
    url: string;
    logo_url: string | null;
    background_url: string | null;
    channels_count: number;
}

export interface NexusFocusRadarItem {
    id: string;
    title: string;
    description: string | null;
    cover_url: string | null;
    banner_url: string | null;
    release_date: string | null;
    status: "new" | "coming";
    playnexus_url?: string | null;
}

export type NexusFocusPersonalizedFeedItem = NexusFocusFeedItem & {
    relevance: {
        priority: "critical" | "high" | "medium" | "normal";
        reason: string;
        signal_label: string;
    };
};

export interface NexusFocusGameEvent {
    id: number;
    type_label: string;
    title: string;
    summary: string | null;
    priority: "critical" | "high" | "medium" | "normal";
    reason: string;
    url: string;
    detected_at: string | null;
    change: {
        label: string;
        kind: "date" | "money";
        from: string | number | null;
        to: string | number | null;
        currency?: string | null;
    } | null;
    game: {
        id: number;
        name: string;
        url: string;
        image_url: string | null;
        cover_url: string | null;
    } | null;
}

export interface NexusFocusPersonalizedHome {
    followed_games: Array<{
        id: number;
        name: string;
        url: string;
        image_url: string | null;
    }>;
    events: NexusFocusGameEvent[];
    videos: NexusFocusPersonalizedFeedItem[];
    feed: NexusFocusPersonalizedFeedItem[];
    intelligence: {
        focus_reason: string | null;
        focus_game: {
            id: number;
            name: string;
            url: string;
            image_url: string | null;
        } | null;
    };
}

export interface NexusFocusInput {
    heading: string;
    slides: NexusFocusSlide[];
    feed: NexusFocusFeedItem[];
    freshContent: NexusFocusFreshItem[];
    channels: NexusFocusChannel[];
    studios: NexusFocusStudio[];
    radar: NexusFocusRadarItem[];
    products: StorefrontProduct[];
    categories: NavigationCategory[];
    personalizedHome: NexusFocusPersonalizedHome | null;
    newsletter: {
        enabled: boolean;
        title: string;
        description: string;
    };
}

export interface NexusSpotlightItem {
    key: string;
    title: string;
    eyebrow: string;
    description: string | null;
    href: string;
    image: string | null;
    mobileImage?: string | null;
    kind: "event" | "campaign" | "feed" | "video" | "product";
    score: number;
}

export interface NexusPulseItem {
    key: string;
    title: string;
    reason: string;
    href: string;
    image: string | null;
    type: "event" | "feed" | "video" | "radar";
    priority: "critical" | "high" | "normal";
}

export interface NexusGameItem {
    key: string;
    name: string;
    href: string;
    image: string | null;
    meta: string;
    signal: string | null;
    personalized: boolean;
}

export interface NexusRadarSignal {
    key: string;
    game: string;
    title: string;
    description: string | null;
    href: string;
    image: string | null;
    time: string | null;
    tone: "important" | "normal";
}

export interface NexusExploreItem {
    key: string;
    title: string;
    href: string;
    image: string | null;
    kind: "category" | "studio";
    meta: string;
}

export interface NexusFocusModel {
    spotlight: NexusSpotlightItem[];
    pulse: NexusPulseItem[];
    games: NexusGameItem[];
    products: StorefrontProduct[];
    featuredVideo: NexusFocusFreshItem | NexusFocusPersonalizedFeedItem | null;
    stories: NexusFocusFeedItem[];
    radar: NexusRadarSignal[];
    explore: NexusExploreItem[];
}
