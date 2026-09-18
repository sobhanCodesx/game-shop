import { Head } from "@inertiajs/react";

export interface SeoData {
    title: string;
    description: string;
    canonical: string;
    robots: string;
    type: string;
    siteName: string;
    locale: string;
    image: string;
    imageAlt: string;
    absoluteTitle: boolean;
    structuredData: Record<string, unknown> | null;
    video?: {
        url: string;
        type: string | null;
        duration: number | null;
    };
}

function safeJsonLd(value: Record<string, unknown>): string {
    return JSON.stringify(value)
        .replaceAll("<", "\\u003c")
        .replaceAll(">", "\\u003e")
        .replaceAll("&", "\\u0026");
}

export default function Seo({ seo }: { seo: SeoData }) {
    const locale = (seo.locale || "fa-IR").replace("-", "_");

    return (
        <Head>
            <title>{seo.title}</title>
            <meta
                content={seo.description}
                head-key="description"
                name="description"
            />
            <meta content={seo.robots} head-key="robots" name="robots" />
            <link head-key="canonical" href={seo.canonical} rel="canonical" />

            <meta content={seo.type} head-key="og:type" property="og:type" />
            <meta content={seo.title} head-key="og:title" property="og:title" />
            <meta
                content={seo.description}
                head-key="og:description"
                property="og:description"
            />
            <meta content={seo.canonical} head-key="og:url" property="og:url" />
            <meta
                content={seo.siteName}
                head-key="og:site_name"
                property="og:site_name"
            />
            <meta content={locale} head-key="og:locale" property="og:locale" />
            <meta content={seo.image} head-key="og:image" property="og:image" />
            <meta
                content={seo.imageAlt}
                head-key="og:image:alt"
                property="og:image:alt"
            />

            <meta
                content="summary_large_image"
                head-key="twitter:card"
                name="twitter:card"
            />
            <meta
                content={seo.title}
                head-key="twitter:title"
                name="twitter:title"
            />
            <meta
                content={seo.description}
                head-key="twitter:description"
                name="twitter:description"
            />
            <meta
                content={seo.image}
                head-key="twitter:image"
                name="twitter:image"
            />
            <meta
                content={seo.imageAlt}
                head-key="twitter:image:alt"
                name="twitter:image:alt"
            />

            {seo.video && (
                <link
                    as="image"
                    fetchPriority="high"
                    head-key="video-thumbnail-preload"
                    href={seo.image}
                    rel="preload"
                />
            )}

            {seo.video && (
                <meta
                    content={seo.video.url}
                    head-key="og:video"
                    property="og:video"
                />
            )}
            {seo.video?.type && (
                <meta
                    content={seo.video.type}
                    head-key="og:video:type"
                    property="og:video:type"
                />
            )}
            {seo.video?.duration !== null &&
                seo.video?.duration !== undefined && (
                    <meta
                        content={String(seo.video.duration)}
                        head-key="og:video:duration"
                        property="og:video:duration"
                    />
                )}

            {seo.structuredData && (
                <script
                    dangerouslySetInnerHTML={{
                        __html: safeJsonLd(seo.structuredData),
                    }}
                    head-key="structured-data"
                    type="application/ld+json"
                />
            )}
        </Head>
    );
}
