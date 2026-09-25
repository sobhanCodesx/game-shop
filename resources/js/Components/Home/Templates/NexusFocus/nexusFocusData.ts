import type {
    NexusExploreItem,
    NexusFocusFeedItem,
    NexusFocusInput,
    NexusFocusModel,
    NexusGameItem,
    NexusPulseItem,
    NexusRadarSignal,
    NexusLatestSlideItem,
} from "./types";

const feedBadgeLabel: Record<string, string> = {
    breaking: "فوری",
    news: "خبر",
    trailer: "تریلر",
    update: "آپدیت",
    review: "نقد و بررسی",
};

const feedImage = (item: NexusFocusFeedItem) => {
    const media = item.media.find(
        (entry) => entry.type === "image" || Boolean(entry.thumbnail),
    );

    return media?.type === "image" ? media.url : (media?.thumbnail ?? null);
};

const uniqueBy = <T>(items: T[], keyFor: (item: T) => string) => {
    const seen = new Set<string>();

    return items.filter((item) => {
        const key = keyFor(item);
        if (!key || seen.has(key)) return false;
        seen.add(key);
        return true;
    });
};

const publicPulse = (input: NexusFocusInput): NexusPulseItem[] => [
    ...input.radar.map((item) => ({
        key: `radar-${item.id}`,
        title: item.title,
        reason: item.description || "تغییر تازه در دنیای بازی",
        href: item.playnexus_url ?? "/game-radar",
        image: item.banner_url ?? item.cover_url,
        type: "radar" as const,
        priority: "normal" as const,
    })),
    ...input.feed.map((item) => ({
        key: `feed-${item.id}`,
        title: item.title,
        reason: item.author.name || "تازه در PlayNexus",
        href: item.url,
        image: feedImage(item),
        type: "feed" as const,
        priority: "normal" as const,
    })),
    ...input.freshContent
        .filter((item) => item.type === "video")
        .map((item) => ({
            key: item.key,
            title: item.title,
            reason: item.eyebrow || "ویدیوی تازه",
            href: item.url,
            image: item.image_url,
            type: "video" as const,
            priority: "normal" as const,
        })),
];

const buildLatestSlides = (input: NexusFocusInput): NexusLatestSlideItem[] => {
    const items: NexusLatestSlideItem[] = [];

    input.latestArrivalsFeed.forEach((item) => {
        items.push({
            key: `feed-${item.id}`,
            title: item.title,
            eyebrow:
                feedBadgeLabel[item.badge ?? ""] ??
                (item.type === "video" ? "ویدیوی تازه" : "تازه در فید"),
            description:
                item.type === "video"
                    ? "ویدیوی تازه منتشرشده در PlayNexus"
                    : item.author.name || "محتوای تازه در PlayNexus",
            href: item.url,
            image:
                feedImage(item) ??
                item.game?.image_url ??
                (item.author.url ? item.author.avatar_url : null),
            kind: item.type === "video" ? "video" : "feed",
            publishedAt: item.created_at,
        });
    });

    input.freshContent.forEach((item) => {
        items.push({
            key: item.key,
            title: item.title,
            eyebrow:
                item.type === "video"
                    ? "ویدیوی تازه"
                    : item.eyebrow || "محصول تازه",
            description:
                item.type === "video"
                    ? "تازه منتشرشده در PlayNexus"
                    : item.eyebrow || "تازه وارد فروشگاه",
            href: item.url,
            image: item.image_url,
            kind: item.type,
            publishedAt: item.published_at,
        });
    });

    input.latestProducts.forEach((product) => {
        items.push({
            key: `latest-product-${product.id}`,
            title: product.title,
            eyebrow: "محصول تازه",
            description: product.category || "تازه وارد فروشگاه PlayNexus",
            href: product.url,
            image: product.cover_url,
            kind: "product",
            publishedAt: product.published_at ?? null,
        });
    });

    input.channels.forEach((game) => {
        items.push({
            key: `latest-game-${game.id}`,
            title: game.name,
            eyebrow: "بازی تازه",
            description:
                game.videos_count > 0
                    ? `${game.videos_count.toLocaleString("fa-IR")} ویدیو در صفحه بازی`
                    : "تازه وارد دنیای بازی‌های PlayNexus",
            href: game.url,
            image: game.image_url,
            kind: "game",
            publishedAt: game.created_at ?? null,
        });
    });

    input.studios.forEach((studio) => {
        items.push({
            key: `latest-studio-${studio.id}`,
            title: studio.name,
            eyebrow: "استودیوی تازه",
            description:
                studio.channels_count > 0
                    ? `${studio.channels_count.toLocaleString("fa-IR")} بازی و کانال`
                    : "تازه به PlayNexus اضافه شده",
            href: studio.url,
            image: studio.background_url ?? studio.logo_url,
            kind: "studio",
            publishedAt: studio.created_at ?? null,
        });
    });

    const latest = uniqueBy(
        items.filter((item) => Boolean(item.publishedAt)),
        (item) => item.href,
    )
        .sort(
            (a, b) =>
                Date.parse(b.publishedAt ?? "") -
                Date.parse(a.publishedAt ?? ""),
        )
        .slice(0, 6);

    if (latest.length > 0) return latest;

    return input.slides.slice(0, 4).map((slide) => ({
        key: `campaign-${slide.id}`,
        title: (slide.title || slide.alt || "PlayNexus").trim(),
        eyebrow: slide.eyebrow || "PLAYNEXUS",
        description: (slide.description || slide.alt || "").trim() || null,
        href: slide.button_url || "/",
        image: slide.desktop_image_url,
        mobileImage: slide.mobile_image_url,
        kind: "campaign",
        publishedAt: null,
    }));
};

const buildPulse = (
    input: NexusFocusInput,
    excludedHrefs: Set<string>,
): NexusPulseItem[] => {
    const personalized: NexusPulseItem[] = [];

    input.personalizedHome?.events.forEach((event) => {
        personalized.push({
            key: `pulse-event-${event.id}`,
            title: event.title,
            reason:
                event.reason ||
                event.summary ||
                "تغییر مهم برای یکی از بازی‌هایت",
            href: event.url,
            image: event.game?.image_url ?? event.game?.cover_url ?? null,
            type: "event",
            priority:
                event.priority === "critical"
                    ? "critical"
                    : event.priority === "high"
                      ? "high"
                      : "normal",
        });
    });

    input.personalizedHome?.videos.forEach((item) => {
        personalized.push({
            key: `pulse-video-${item.id}`,
            title: item.title,
            reason: item.relevance.reason,
            href: item.url,
            image: feedImage(item),
            type: "video",
            priority:
                item.relevance.priority === "critical"
                    ? "critical"
                    : item.relevance.priority === "high"
                      ? "high"
                      : "normal",
        });
    });

    input.personalizedHome?.feed.forEach((item) => {
        personalized.push({
            key: `pulse-feed-${item.id}`,
            title: item.title,
            reason: item.relevance.reason,
            href: item.url,
            image: feedImage(item),
            type: "feed",
            priority:
                item.relevance.priority === "critical"
                    ? "critical"
                    : item.relevance.priority === "high"
                      ? "high"
                      : "normal",
        });
    });

    const source =
        personalized.length > 0
            ? [...personalized, ...publicPulse(input)]
            : publicPulse(input);

    return uniqueBy(
        source.filter((item) => !excludedHrefs.has(item.href)),
        (item) => item.href,
    ).slice(0, 4);
};

const buildGames = (input: NexusFocusInput): NexusGameItem[] => {
    const games: NexusGameItem[] = [];
    const focus = input.personalizedHome?.intelligence.focus_game;

    if (focus) {
        games.push({
            key: `focus-${focus.id}`,
            name: focus.name,
            href: focus.url,
            image: focus.image_url,
            meta: "بازی در اولویت تو",
            signal:
                input.personalizedHome?.intelligence.focus_reason ??
                "Nexus Pulse این بازی را برایت بالاتر آورده",
            personalized: true,
        });
    }

    input.personalizedHome?.followed_games.forEach((game) => {
        games.push({
            key: `follow-${game.id}`,
            name: game.name,
            href: game.url,
            image: game.image_url,
            meta: "دنبال می‌کنی",
            signal: null,
            personalized: true,
        });
    });

    input.channels.forEach((channel) => {
        games.push({
            key: `channel-${channel.id}`,
            name: channel.name,
            href: channel.url,
            image: channel.image_url,
            meta: "صفحه بازی",
            signal:
                channel.videos_count > 0
                    ? `${channel.videos_count.toLocaleString("fa-IR")} ویدیو`
                    : null,
            personalized: false,
        });
    });

    return uniqueBy(games, (item) => item.href).slice(0, 6);
};

const buildRadar = (
    input: NexusFocusInput,
    excludedHrefs: Set<string>,
): NexusRadarSignal[] => {
    const events = input.personalizedHome?.events.map((event) => ({
        key: `event-${event.id}`,
        game: event.game?.name ?? "Game Radar",
        title: event.title,
        description: event.reason || event.summary,
        href: event.url,
        image: event.game?.image_url ?? event.game?.cover_url ?? null,
        time: event.detected_at,
        tone:
            event.priority === "critical" || event.priority === "high"
                ? ("important" as const)
                : ("normal" as const),
    }));

    const publicSignals = input.radar.map((item) => ({
        key: `radar-${item.id}`,
        game: item.title,
        title:
            item.status === "coming"
                ? "در رادار انتشار PlayNexus"
                : "ورودی تازه Game Radar",
        description: item.description,
        href: item.playnexus_url ?? "/game-radar",
        image: item.banner_url ?? item.cover_url,
        time: item.release_date,
        tone: "normal" as const,
    }));

    return uniqueBy(
        [...(events ?? []), ...publicSignals].filter(
            (item) => !excludedHrefs.has(item.href),
        ),
        (item) => item.href,
    ).slice(0, 4);
};

const buildExplore = (input: NexusFocusInput): NexusExploreItem[] => [
    ...input.categories.slice(0, 4).map((category) => ({
        key: `category-${category.id}`,
        title: category.name,
        href: `/categories/${category.slug}`,
        image: category.image_url,
        kind: "category" as const,
        meta: `${category.products_count.toLocaleString("fa-IR")} محصول`,
    })),
    ...input.studios.slice(0, 2).map((studio) => ({
        key: `studio-${studio.id}`,
        title: studio.name,
        href: studio.url,
        image: studio.background_url ?? studio.logo_url,
        kind: "studio" as const,
        meta: `${studio.channels_count.toLocaleString("fa-IR")} بازی و کانال`,
    })),
];

export function buildNexusFocusModel(input: NexusFocusInput): NexusFocusModel {
    const products = uniqueBy(input.products, (product) =>
        String(product.id),
    ).slice(0, 8);

    const latestSlides = buildLatestSlides(input);
    const latestSlideHrefs = new Set(latestSlides.map((item) => item.href));
    const usedContentHrefs = new Set(latestSlideHrefs);

    const videoCandidates = [
        ...(input.personalizedHome?.videos ?? []),
        ...input.freshContent.filter((item) => item.type === "video"),
    ];
    const featuredVideo =
        videoCandidates.find((item) => !usedContentHrefs.has(item.url)) ?? null;

    const pulseExclusions = new Set(usedContentHrefs);
    if (featuredVideo) pulseExclusions.add(featuredVideo.url);

    const pulse = buildPulse(input, pulseExclusions);
    pulse.forEach((item) => usedContentHrefs.add(item.href));

    if (featuredVideo) usedContentHrefs.add(featuredVideo.url);

    const uniqueStories = uniqueBy(
        input.feed.filter((item) => !usedContentHrefs.has(item.url)),
        (item) => item.url,
    );
    const storyFallbacks = uniqueBy(
        input.feed.filter(
            (item) =>
                !latestSlideHrefs.has(item.url) &&
                !uniqueStories.some((story) => story.url === item.url),
        ),
        (item) => item.url,
    );
    const stories = [...uniqueStories, ...storyFallbacks].slice(0, 3);
    stories.forEach((item) => usedContentHrefs.add(item.url));

    return {
        latestSlides,
        pulse,
        games: buildGames(input),
        products,
        featuredVideo,
        stories,
        radar: buildRadar(input, usedContentHrefs),
        explore: buildExplore(input),
    };
}

export { feedImage };
