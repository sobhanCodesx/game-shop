import { Button, Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import { ArrowRight, Eye, Gamepad2, Play } from "lucide-react";

interface Props {
    content: {
        title: string;
        slug: string;
        type: string;
        excerpt: string | null;
        duration: number | null;
        views: number;
        thumbnail_url: string | null;
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
        <main
            className="min-h-screen bg-slate-950 px-4 py-10 text-slate-100"
            dir="rtl"
        >
            <Head title={content.title} />
            <div className="mx-auto max-w-5xl">
                <Link href="/">
                    <Button className="mb-5" variant="ghost">
                        <ArrowRight size={17} />
                        بازگشت به Home
                    </Button>
                </Link>
                <Card
                    className="overflow-hidden border border-slate-800 bg-slate-900/70"
                    variant="secondary"
                >
                    <div
                        className={`relative overflow-hidden bg-[radial-gradient(circle_at_top,#312e81,#020617_65%)] ${content.type === "short" ? "mx-auto aspect-[9/14] max-h-[720px] max-w-md" : "aspect-video"}`}
                    >
                        {content.thumbnail_url ? (
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
                        {content.type !== "post" && (
                            <span className="absolute inset-0 grid place-items-center">
                                <span className="grid size-20 place-items-center rounded-full bg-white/90 text-slate-950 shadow-2xl">
                                    <Play fill="currentColor" size={32} />
                                </span>
                            </span>
                        )}
                    </div>
                    <Card.Content className="space-y-5 p-6 md:p-9">
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
                                    ).format(new Date(content.published_at))}
                                </span>
                            )}
                        </div>
                        <h1 className="text-3xl font-black leading-tight text-white md:text-5xl">
                            {content.title}
                        </h1>
                        {content.excerpt && (
                            <p className="text-base leading-9 text-slate-300 md:text-lg">
                                {content.excerpt}
                            </p>
                        )}
                    </Card.Content>
                </Card>
            </div>
        </main>
    );
}
