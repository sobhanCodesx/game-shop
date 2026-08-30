import { Button, Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import { MediaPlayer, MediaProvider } from "@vidstack/react";
import {
    defaultLayoutIcons,
    DefaultVideoLayout,
} from "@vidstack/react/player/layouts/default";
import "@vidstack/react/player/styles/default/theme.css";
import "@vidstack/react/player/styles/default/layouts/video.css";
import { ArrowRight, Eye, Gamepad2, Play } from "lucide-react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

interface Props {
    content: {
        title: string;
        slug: string;
        type: string;
        excerpt: string | null;
        duration: number | null;
        views: number;
        thumbnail_url: string | null;
        video_url: string | null;
        published_at: string | null;
    };
}
const number = new Intl.NumberFormat("fa-IR");

export default function Show({ content }: Props) {
    const typeLabel =
        content.type === "video"
            ? "ویدیو"
            : content.type === "short"
              ? "شورت"
              : "پست";
    return (
        <StorefrontLayout>
            <main
                className="relative min-h-screen overflow-hidden bg-slate-950 px-4 py-6 text-slate-100 md:py-8"
                dir="rtl"
            >
                <Head title={content.title} />
                <div className="pointer-events-none absolute inset-x-0 top-0 h-[600px] bg-[radial-gradient(circle_at_50%_0%,rgba(79,70,229,.22),transparent_58%)]" />
                <div className="relative mx-auto max-w-5xl">
                    <Link href={content.type === "video" ? "/videos" : "/"}>
                        <Button className="mb-5" variant="ghost">
                            <ArrowRight size={17} />
                            {content.type === "video"
                                ? "بازگشت به آرشیو ویدیوها"
                                : "بازگشت به Home"}
                        </Button>
                    </Link>
                    <Card
                        className="overflow-hidden border border-white/10 bg-slate-900/70 shadow-[0_30px_100px_-40px_rgba(79,70,229,.65)] md:rounded-[28px]"
                        variant="secondary"
                    >
                        <div
                            className={`relative overflow-hidden bg-[radial-gradient(circle_at_top,#312e81,#020617_65%)] ${content.type === "short" ? "mx-auto aspect-[9/14] max-h-[720px] max-w-md" : "aspect-video w-full"}`}
                        >
                            {content.video_url ? (
                                <MediaPlayer
                                    className="h-full w-full overflow-hidden"
                                    playsInline
                                    poster={content.thumbnail_url ?? undefined}
                                    src={content.video_url}
                                    title={content.title}
                                >
                                    <MediaProvider />
                                    <DefaultVideoLayout
                                        icons={defaultLayoutIcons}
                                    />
                                </MediaPlayer>
                            ) : content.thumbnail_url ? (
                                <img
                                    alt={content.title}
                                    className="h-full w-full object-cover"
                                    src={content.thumbnail_url}
                                />
                            ) : (
                                <div className="grid h-full place-items-center">
                                    <Gamepad2
                                        className="text-indigo-400"
                                        size={80}
                                    />
                                </div>
                            )}
                            {content.type !== "post" && !content.video_url && (
                                <span className="absolute inset-0 grid place-items-center">
                                    <span className="grid size-20 place-items-center rounded-full bg-white/90 text-slate-950 shadow-2xl">
                                        <Play fill="currentColor" size={32} />
                                    </span>
                                </span>
                            )}
                        </div>
                        <Card.Content className="space-y-5 p-6 md:p-10">
                            <div className="flex flex-wrap items-center gap-3">
                                <Chip color="accent" variant="soft">
                                    {typeLabel}
                                </Chip>
                                <span className="flex items-center gap-1 text-sm text-slate-400">
                                    <Eye size={16} />
                                    {number.format(content.views)} بازدید
                                </span>
                                {content.published_at && (
                                    <span className="text-sm text-slate-500">
                                        {new Intl.DateTimeFormat(
                                            "fa-IR-u-ca-persian",
                                            { dateStyle: "long" },
                                        ).format(
                                            new Date(content.published_at),
                                        )}
                                    </span>
                                )}
                            </div>
                            <h1 className="max-w-4xl text-2xl font-black leading-tight text-white md:text-4xl">
                                {content.title}
                            </h1>
                            {content.excerpt && (
                                <div
                                    className="prose prose-invert max-w-none text-sm leading-8 text-slate-300 md:text-base"
                                    dangerouslySetInnerHTML={{
                                        __html: content.excerpt,
                                    }}
                                />
                            )}
                        </Card.Content>
                    </Card>
                </div>
            </main>
        </StorefrontLayout>
    );
}
