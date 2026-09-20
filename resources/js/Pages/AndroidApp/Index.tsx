import {
    BellRing,
    CheckCircle2,
    Download,
    Gamepad2,
    History,
    Play,
    Radar,
    ShieldCheck,
    Smartphone,
    Sparkles,
    Video,
    Zap,
} from "lucide-react";

import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

type MediaItem = {
    id: string;
    type: "image" | "video";
    url: string | null;
    alt: string;
    caption: string;
};

type Release = {
    id: number;
    version: string;
    version_code: number;
    file_size: number;
    release_notes: string | null;
    is_active: boolean;
    released_at: string | null;
    download_url: string;
};

type LatestRelease = Omit<Release, "id" | "is_active"> & {
    checksum_sha256: string | null;
};

type Props = {
    seo: SeoData;
    page: {
        eyebrow: string;
        hero_title: string;
        hero_description: string;
        promo_title: string;
        promo_description: string;
        seo_title: string;
        seo_description: string;
        media: MediaItem[];
    };
    latest: LatestRelease | null;
    releases: Release[];
};

const formatBytes = (bytes: number) => {
    if (!Number.isFinite(bytes) || bytes <= 0) return "—";
    const mb = bytes / (1024 * 1024);

    if (mb < 1024) {
        return (
            mb.toLocaleString("fa-IR", { maximumFractionDigits: 1 }) +
            " مگابایت"
        );
    }

    return (
        (mb / 1024).toLocaleString("fa-IR", { maximumFractionDigits: 2 }) +
        " گیگابایت"
    );
};

const formatDate = (value: string | null) => {
    if (!value) return "—";

    return new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
        year: "numeric",
        month: "long",
        day: "numeric",
        timeZone: "Asia/Tehran",
    }).format(new Date(value));
};

const features = [
    {
        icon: Radar,
        title: "Game Radar",
        description:
            "بازی‌های تازه و در راه پلی‌استیشن و ایکس‌باکس را سریع‌تر دنبال کن.",
    },
    {
        icon: Video,
        title: "ویدیو و فید",
        description:
            "ویدیوها، خبرها و محتوای گیمینگ پلی نکسوس در تجربه‌ای مخصوص موبایل.",
    },
    {
        icon: BellRing,
        title: "نوتیفیکیشن",
        description:
            "برای محتوا و اتفاق‌های مهمی که دنبال می‌کنی، اعلان دریافت کن.",
    },
    {
        icon: Zap,
        title: "سریع و سبک",
        description:
            "تجربه Native برای دسترسی سریع‌تر به بخش‌های اصلی PlayNexus.",
    },
];

export default function AndroidAppIndex({
    seo,
    page,
    latest,
    releases,
}: Props) {
    const heroMedia = page.media[0] ?? null;

    return (
        <StorefrontLayout>
            <Seo seo={seo} />

            <main className="overflow-hidden bg-[var(--store-bg)] text-[var(--store-text)]">
                <section className="relative border-b border-[var(--store-border)]">
                    <div className="pointer-events-none absolute inset-0 overflow-hidden">
                        <div className="absolute -right-24 -top-20 size-[28rem] rounded-full bg-emerald-500/10 blur-[100px]" />
                        <div className="absolute -bottom-32 left-1/4 size-[32rem] rounded-full bg-indigo-500/10 blur-[120px]" />
                    </div>

                    <div className="relative mx-auto grid min-h-[720px] max-w-[1500px] items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.03fr_.97fr] lg:px-10 lg:py-20">
                        <div>
                            <div className="mb-5 inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-400/10 px-3 py-1.5 text-[11px] font-black tracking-[.14em] text-emerald-400">
                                <Sparkles size={13} />
                                {page.eyebrow}
                            </div>

                            <h1 className="max-w-4xl text-4xl font-black leading-[1.25] tracking-tight sm:text-5xl lg:text-6xl">
                                {page.hero_title}
                            </h1>

                            <p className="mt-6 max-w-2xl text-sm leading-8 text-[var(--store-muted)] sm:text-base">
                                {page.hero_description}
                            </p>

                            <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                                {latest ? (
                                    <a
                                        className="group inline-flex min-h-14 items-center justify-center gap-3 rounded-2xl bg-emerald-400 px-6 py-3 text-sm font-black text-slate-950 shadow-xl shadow-emerald-950/20 transition hover:-translate-y-0.5 hover:bg-emerald-300"
                                        href={latest.download_url}
                                    >
                                        <span className="grid size-9 place-items-center rounded-xl bg-slate-950 text-white">
                                            <Download size={18} />
                                        </span>
                                        <span className="text-right">
                                            <span className="block">
                                                دانلود APK رسمی
                                            </span>
                                            <span className="mt-0.5 block text-[10px] font-bold text-slate-700">
                                                v{latest.version} •{" "}
                                                {formatBytes(latest.file_size)}
                                            </span>
                                        </span>
                                    </a>
                                ) : (
                                    <div className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-6 py-4 text-sm font-bold text-[var(--store-muted)]">
                                        اولین نسخه اندروید به‌زودی منتشر می‌شود
                                    </div>
                                )}

                                <a
                                    className="inline-flex min-h-14 items-center justify-center gap-2 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-5 text-sm font-black transition hover:border-emerald-400/30 hover:text-emerald-400"
                                    href="#versions"
                                >
                                    <History size={17} />
                                    تاریخچه نسخه‌ها
                                </a>
                            </div>

                            {latest && (
                                <div className="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-xs text-[var(--store-muted)]">
                                    <span className="inline-flex items-center gap-1.5">
                                        <ShieldCheck
                                            className="text-emerald-400"
                                            size={14}
                                        />
                                        فایل رسمی PlayNexus
                                    </span>
                                    <span>
                                        Build{" "}
                                        {latest.version_code.toLocaleString(
                                            "fa-IR",
                                        )}
                                    </span>
                                    <span>
                                        انتشار:{" "}
                                        {formatDate(latest.released_at)}
                                    </span>
                                </div>
                            )}
                        </div>

                        <div className="relative mx-auto w-full max-w-[620px]">
                            <div className="absolute -inset-6 rounded-[3rem] bg-gradient-to-br from-emerald-500/10 via-transparent to-indigo-500/10 blur-2xl" />
                            <div className="relative overflow-hidden rounded-[2.2rem] border border-white/10 bg-slate-950 p-3 shadow-2xl shadow-black/30">
                                {heroMedia?.url ? (
                                    heroMedia.type === "video" ? (
                                        <div className="relative aspect-[4/5] overflow-hidden rounded-[1.7rem] bg-black">
                                            <video
                                                className="size-full object-cover"
                                                controls
                                                muted
                                                playsInline
                                                poster="/logo.png"
                                                preload="metadata"
                                            >
                                                <source src={heroMedia.url} />
                                            </video>
                                        </div>
                                    ) : (
                                        <img
                                            alt={heroMedia.alt}
                                            className="aspect-[4/5] size-full rounded-[1.7rem] object-cover"
                                            loading="eager"
                                            src={heroMedia.url}
                                        />
                                    )
                                ) : (
                                    <div className="relative flex aspect-[4/5] flex-col items-center justify-center overflow-hidden rounded-[1.7rem] border border-white/10 bg-[radial-gradient(circle_at_top,#14332c_0%,#0b1117_45%,#06090c_100%)] p-8 text-center text-white">
                                        <div className="absolute inset-x-12 top-10 h-1 rounded-full bg-white/10" />
                                        <img
                                            alt="PlayNexus"
                                            className="size-24 rounded-[2rem] shadow-2xl shadow-emerald-950/40"
                                            src="/logo.png"
                                        />
                                        <span className="mt-6 text-xs font-black tracking-[.2em] text-emerald-300">
                                            PLAY NEXUS
                                        </span>
                                        <strong className="mt-3 text-2xl font-black">
                                            GAMING IN YOUR POCKET
                                        </strong>
                                        <span className="mt-3 max-w-xs text-xs leading-6 text-white/50">
                                            بعد از آپلود اولین اسکرین‌شات یا
                                            ویدیوی تبلیغاتی از پنل، این بخش با
                                            مدیای واقعی اپ جایگزین می‌شود.
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </section>

                <section className="mx-auto max-w-[1500px] px-4 py-14 sm:px-6 lg:px-10 lg:py-20">
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
                        {features.map(({ icon: Icon, title, description }) => (
                            <article
                                className="rounded-[1.7rem] border border-[var(--store-border)] bg-[var(--store-surface)] p-5 transition hover:-translate-y-1 hover:border-emerald-400/20"
                                key={title}
                            >
                                <div className="grid size-11 place-items-center rounded-2xl bg-emerald-400/10 text-emerald-400">
                                    <Icon size={20} />
                                </div>
                                <h2 className="mt-5 text-base font-black">
                                    {title}
                                </h2>
                                <p className="mt-2 text-xs leading-6 text-[var(--store-muted)]">
                                    {description}
                                </p>
                            </article>
                        ))}
                    </div>
                </section>

                {page.media.length > 0 && (
                    <section className="border-y border-[var(--store-border)] bg-[var(--store-surface)]/40">
                        <div className="mx-auto max-w-[1500px] px-4 py-14 sm:px-6 lg:px-10 lg:py-20">
                            <div className="max-w-3xl">
                                <span className="text-xs font-black tracking-[.12em] text-emerald-400">
                                    APP PREVIEW
                                </span>
                                <h2 className="mt-3 text-2xl font-black sm:text-3xl">
                                    {page.promo_title}
                                </h2>
                                <p className="mt-3 text-sm leading-7 text-[var(--store-muted)]">
                                    {page.promo_description}
                                </p>
                            </div>

                            <div className="mt-8 flex snap-x gap-4 overflow-x-auto pb-4">
                                {page.media.map((item) => (
                                    <figure
                                        className="min-w-[78vw] snap-center overflow-hidden rounded-[1.8rem] border border-[var(--store-border)] bg-slate-950 sm:min-w-[420px] lg:min-w-[520px]"
                                        key={item.id}
                                    >
                                        {item.type === "video" ? (
                                            <video
                                                className="aspect-video w-full bg-black object-cover"
                                                controls
                                                playsInline
                                                preload="metadata"
                                            >
                                                {item.url && (
                                                    <source src={item.url} />
                                                )}
                                            </video>
                                        ) : (
                                            item.url && (
                                                <img
                                                    alt={item.alt}
                                                    className="aspect-video w-full object-cover"
                                                    loading="lazy"
                                                    src={item.url}
                                                />
                                            )
                                        )}
                                        {(item.caption || item.alt) && (
                                            <figcaption className="p-4 text-xs leading-6 text-white/60">
                                                {item.caption || item.alt}
                                            </figcaption>
                                        )}
                                    </figure>
                                ))}
                            </div>
                        </div>
                    </section>
                )}

                <section
                    className="mx-auto max-w-[1500px] px-4 py-14 sm:px-6 lg:px-10 lg:py-20"
                    id="versions"
                >
                    <div className="grid gap-10 lg:grid-cols-[.8fr_1.2fr]">
                        <div>
                            <span className="text-xs font-black tracking-[.12em] text-indigo-400">
                                RELEASE HISTORY
                            </span>
                            <h2 className="mt-3 text-2xl font-black sm:text-3xl">
                                نسخه‌های اندروید PlayNexus
                            </h2>
                            <p className="mt-3 max-w-xl text-sm leading-7 text-[var(--store-muted)]">
                                تغییرات هر نسخه و تاریخ انتشار اینجا باقی
                                می‌ماند. نسخه فعال همیشه از دکمه اصلی صفحه قابل
                                دانلود است و نسخه‌های قبلی نیز در تاریخچه حفظ
                                می‌شوند.
                            </p>

                            <div className="mt-6 rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-4 text-xs leading-6 text-[var(--store-muted)]">
                                <div className="flex items-center gap-2 font-black text-[var(--store-text)]">
                                    <ShieldCheck
                                        className="text-emerald-400"
                                        size={16}
                                    />
                                    دانلود امن
                                </div>
                                <p className="mt-2">
                                    برای دریافت آخرین نسخه رسمی، همیشه از صفحه
                                    PlayNexus یا لینک مستقیم دانلود همین صفحه
                                    استفاده کنید.
                                </p>
                                {latest?.checksum_sha256 && (
                                    <code
                                        className="mt-3 block overflow-hidden text-ellipsis whitespace-nowrap rounded-xl bg-black/20 p-3 text-[10px]"
                                        dir="ltr"
                                        title={latest.checksum_sha256}
                                    >
                                        SHA-256: {latest.checksum_sha256}
                                    </code>
                                )}
                            </div>
                        </div>

                        <div className="space-y-3">
                            {releases.map((release) => (
                                <article
                                    className="rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] p-5"
                                    key={release.id}
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex items-center gap-3">
                                            <div
                                                className={
                                                    release.is_active
                                                        ? "grid size-10 place-items-center rounded-xl bg-emerald-400/10 text-emerald-400"
                                                        : "grid size-10 place-items-center rounded-xl bg-white/5 text-[var(--store-muted)]"
                                                }
                                            >
                                                <Smartphone size={18} />
                                            </div>
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-black">
                                                        نسخه {release.version}
                                                    </h3>
                                                    {release.is_active && (
                                                        <span className="rounded-full bg-emerald-400/10 px-2 py-0.5 text-[10px] font-black text-emerald-400">
                                                            نسخه فعال
                                                        </span>
                                                    )}
                                                </div>
                                                <p className="mt-1 text-[11px] text-[var(--store-muted)]">
                                                    Build{" "}
                                                    {release.version_code.toLocaleString(
                                                        "fa-IR",
                                                    )}{" "}
                                                    •{" "}
                                                    {formatBytes(
                                                        release.file_size,
                                                    )}{" "}
                                                    •{" "}
                                                    {formatDate(
                                                        release.released_at,
                                                    )}
                                                </p>
                                            </div>
                                        </div>

                                        <a
                                            aria-label={
                                                "دانلود نسخه " +
                                                release.version
                                            }
                                            className="inline-flex items-center gap-2 rounded-xl border border-[var(--store-border)] px-3 py-2 text-xs font-black transition hover:border-emerald-400/30 hover:text-emerald-400"
                                            href={release.download_url}
                                        >
                                            <Download size={14} />
                                            دانلود
                                        </a>
                                    </div>

                                    <p className="mt-4 whitespace-pre-line text-xs leading-7 text-[var(--store-muted)]">
                                        {release.release_notes ||
                                            "این نسخه بدون یادداشت انتشار ثبت شده است."}
                                    </p>
                                </article>
                            ))}

                            {releases.length === 0 && (
                                <div className="rounded-2xl border border-dashed border-[var(--store-border)] p-10 text-center text-sm text-[var(--store-muted)]">
                                    هنوز نسخه‌ای منتشر نشده است.
                                </div>
                            )}
                        </div>
                    </div>
                </section>

                <section className="border-t border-[var(--store-border)] bg-slate-950 text-white">
                    <div className="mx-auto grid max-w-[1500px] gap-8 px-4 py-14 sm:px-6 lg:grid-cols-[1fr_auto] lg:items-center lg:px-10 lg:py-16">
                        <div>
                            <div className="inline-flex items-center gap-2 text-xs font-black text-emerald-300">
                                <Gamepad2 size={15} />
                                PLAYNEXUS MOBILE
                            </div>
                            <h2 className="mt-3 text-2xl font-black sm:text-3xl">
                                آماده‌ای پلی نکسوس رو روی موبایل ببری؟
                            </h2>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-white/50">
                                آخرین نسخه رسمی را مستقیم از PlayNexus دریافت
                                کن و هر زمان نسخه جدید منتشر شد، همین صفحه
                                تاریخچه و تغییرات آن را نشان می‌دهد.
                            </p>
                        </div>

                        {latest && (
                            <a
                                className="inline-flex min-h-14 items-center justify-center gap-3 rounded-2xl bg-white px-6 text-sm font-black text-slate-950 transition hover:bg-emerald-300"
                                href={latest.download_url}
                            >
                                <Download size={18} />
                                دانلود نسخه {latest.version}
                            </a>
                        )}
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
