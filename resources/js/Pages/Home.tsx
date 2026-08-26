import { Button, Card, Chip, Input } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import {
    ChevronLeft,
    ChevronRight,
    Gamepad2,
    Headphones,
    Eye,
    Play,
    ShieldCheck,
    ShoppingCart,
    Sparkles,
    Truck,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

interface Pricing {
    regular_price: number;
    final_price: number;
    is_partner_price: boolean;
}
interface Product {
    title: string;
    slug: string;
    category: string | null;
    badge: string | null;
    pricing: Pricing;
}
interface Slide {
    id: number;
    title: string;
    eyebrow: string | null;
    description: string | null;
    desktop_image_url: string;
    mobile_image_url: string | null;
    button_label: string | null;
    button_url: string | null;
    secondary_button_label: string | null;
    secondary_button_url: string | null;
    text_position: string;
    overlay: string;
}
interface Settings {
    announcement_enabled: boolean;
    announcement_text: string;
    announcement_url: string;
    featured_categories_enabled: boolean;
    featured_categories_title: string;
    featured_products_enabled: boolean;
    featured_products_title: string;
    latest_products_enabled: boolean;
    latest_products_title: string;
    newsletter_enabled: boolean;
    newsletter_title: string;
    newsletter_description: string;
    seo_title: string;
    seo_description: string;
}
interface Props {
    settings: Settings;
    slides: Slide[];
    categories: Array<{
        id: number;
        name: string;
        slug: string;
        image: string | null;
        products_count: number;
    }>;
    featuredProducts: Product[];
    latestProducts: Product[];
    contentSections: ContentSection[];
}
interface ContentItem {
    id: number;
    title: string;
    url: string;
    eyebrow: string | null;
    excerpt?: string | null;
    badge?: string | null;
    image_url: string | null;
    duration?: number | null;
    views?: number;
    pricing?: Pricing;
}
interface ContentSection {
    id: number;
    title: string;
    subtitle: string | null;
    content_type: string;
    items: ContentItem[];
}

const money = new Intl.NumberFormat("fa-IR");
const safeUrl = (url: string | null) =>
    url && (/^https?:\/\//.test(url) || url.startsWith("/")) ? url : "#";

function ProductGrid({ products }: { products: Product[] }) {
    if (!products.length)
        return (
            <div className="rounded-2xl border border-dashed border-slate-800 p-10 text-center text-slate-500">
                محصولی برای نمایش آماده نشده است.
            </div>
        );
    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4">
            {products.map((product) => (
                <Link href={`/products/${product.slug}`} key={product.slug}>
                    <Card
                        className="group h-full overflow-hidden border border-slate-800 bg-slate-900/70 transition hover:-translate-y-1 hover:border-indigo-500/50"
                        variant="secondary"
                    >
                        <div className="flex aspect-square items-center justify-center bg-[radial-gradient(circle_at_top,#312e81_0%,#0f172a_55%,#020617_100%)]">
                            <Gamepad2
                                className="text-indigo-400 transition group-hover:scale-110"
                                size={64}
                            />
                            {product.badge && (
                                <Chip
                                    className="absolute right-3 top-3"
                                    color="accent"
                                    variant="soft"
                                >
                                    {product.badge}
                                </Chip>
                            )}
                        </div>
                        <Card.Content className="space-y-3 p-4">
                            <p className="text-xs text-slate-500">
                                {product.category ?? "محصول گیمینگ"}
                            </p>
                            <h3 className="line-clamp-2 min-h-12 font-bold leading-6 text-slate-100">
                                {product.title}
                            </h3>
                            {product.pricing.is_partner_price && (
                                <Chip color="success" size="sm" variant="soft">
                                    قیمت همکار
                                </Chip>
                            )}
                            <div>
                                {product.pricing.final_price !==
                                    product.pricing.regular_price && (
                                    <p className="text-xs text-slate-500 line-through">
                                        {money.format(
                                            product.pricing.regular_price,
                                        )}
                                    </p>
                                )}
                                <p className="text-lg font-black text-white">
                                    {money.format(product.pricing.final_price)}{" "}
                                    <span className="text-xs font-medium text-slate-400">
                                        تومان
                                    </span>
                                </p>
                            </div>
                        </Card.Content>
                    </Card>
                </Link>
            ))}
        </div>
    );
}

const durationLabel = (seconds?: number | null) =>
    seconds
        ? `${Math.floor(seconds / 60).toLocaleString("fa-IR")}:${String(seconds % 60).padStart(2, "0")}`
        : null;

function ContentRail({ section }: { section: ContentSection }) {
    const railRef = useRef<HTMLDivElement>(null);
    const scroll = (direction: number) =>
        railRef.current?.scrollBy({
            left: direction * railRef.current.clientWidth * -0.75,
            behavior: "smooth",
        });
    const isProduct = section.content_type === "products";
    const isShort = section.content_type === "shorts";

    return (
        <section className="mx-auto max-w-7xl px-4 py-10">
            <div className="mb-6 flex items-end justify-between gap-4">
                <div>
                    <p className="text-sm font-bold text-indigo-400">
                        {section.subtitle ??
                            (isProduct
                                ? "انتخاب هوشمند فروشگاه"
                                : "تازه از جامعه گیمرها")}
                    </p>
                    <h2 className="mt-2 text-2xl font-black md:text-3xl">
                        {section.title}
                    </h2>
                </div>
                <div className="flex gap-2">
                    <Button
                        aria-label="قبلی"
                        isIconOnly
                        onPress={() => scroll(-1)}
                        variant="secondary"
                    >
                        <ChevronRight size={18} />
                    </Button>
                    <Button
                        aria-label="بعدی"
                        isIconOnly
                        onPress={() => scroll(1)}
                        variant="secondary"
                    >
                        <ChevronLeft size={18} />
                    </Button>
                </div>
            </div>
            <div
                className="scrollbar-none -mx-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-4 pb-3"
                ref={railRef}
            >
                {section.items.map((item) => (
                    <Link
                        className={`block shrink-0 snap-start ${isShort ? "w-[190px] sm:w-[220px]" : "w-[270px] sm:w-[300px]"}`}
                        href={item.url}
                        key={item.id}
                    >
                        <Card
                            className="group h-full overflow-hidden border border-slate-800 bg-slate-900/70 transition hover:-translate-y-1 hover:border-indigo-500/50"
                            variant="secondary"
                        >
                            <div
                                className={`relative overflow-hidden bg-[radial-gradient(circle_at_top,#312e81_0%,#0f172a_60%,#020617_100%)] ${isShort ? "aspect-[9/14]" : "aspect-video"}`}
                            >
                                {item.image_url ? (
                                    <img
                                        alt={item.title}
                                        className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                                        loading="lazy"
                                        src={item.image_url}
                                    />
                                ) : (
                                    <div className="grid h-full place-items-center">
                                        {isProduct ? (
                                            <Gamepad2
                                                className="text-indigo-400"
                                                size={52}
                                            />
                                        ) : (
                                            <Play
                                                className="text-indigo-300"
                                                fill="currentColor"
                                                size={48}
                                            />
                                        )}
                                    </div>
                                )}
                                {!isProduct && (
                                    <span className="absolute inset-0 grid place-items-center bg-black/10 opacity-0 transition group-hover:opacity-100">
                                        <span className="grid size-12 place-items-center rounded-full bg-white text-slate-950">
                                            <Play
                                                fill="currentColor"
                                                size={20}
                                            />
                                        </span>
                                    </span>
                                )}
                                {durationLabel(item.duration) && (
                                    <Chip
                                        className="absolute bottom-2 left-2 bg-black/75 text-white"
                                        size="sm"
                                    >
                                        {durationLabel(item.duration)}
                                    </Chip>
                                )}
                                {item.badge && (
                                    <Chip
                                        className="absolute right-2 top-2"
                                        color="accent"
                                        size="sm"
                                        variant="soft"
                                    >
                                        {item.badge}
                                    </Chip>
                                )}
                            </div>
                            <Card.Content className="space-y-2 p-4">
                                <p className="text-xs font-bold text-indigo-400">
                                    {item.eyebrow}
                                </p>
                                <h3 className="line-clamp-2 min-h-12 font-bold leading-6 text-white">
                                    {item.title}
                                </h3>
                                {item.excerpt && (
                                    <p className="line-clamp-2 text-xs leading-6 text-slate-400">
                                        {item.excerpt}
                                    </p>
                                )}
                                {item.pricing && (
                                    <p className="text-lg font-black">
                                        {money.format(item.pricing.final_price)}{" "}
                                        <span className="text-xs font-medium text-slate-400">
                                            تومان
                                        </span>
                                    </p>
                                )}
                                {typeof item.views === "number" && (
                                    <p className="flex items-center gap-1 text-xs text-slate-500">
                                        <Eye size={14} />
                                        {money.format(item.views)} بازدید
                                    </p>
                                )}
                            </Card.Content>
                        </Card>
                    </Link>
                ))}
            </div>
        </section>
    );
}

export default function Home({
    settings,
    slides,
    categories,
    featuredProducts,
    latestProducts,
    contentSections,
}: Props) {
    const [activeSlide, setActiveSlide] = useState(0);
    useEffect(() => {
        if (slides.length < 2) return;
        const timer = window.setInterval(
            () => setActiveSlide((current) => (current + 1) % slides.length),
            6000,
        );
        return () => window.clearInterval(timer);
    }, [slides.length]);
    const slide = slides[activeSlide];
    const go = (offset: number) =>
        setActiveSlide(
            (current) => (current + offset + slides.length) % slides.length,
        );

    return (
        <div className="min-h-screen bg-slate-950 text-slate-100" dir="rtl">
            <Head title={settings.seo_title}>
                <meta content={settings.seo_description} name="description" />
            </Head>
            {settings.announcement_enabled && (
                <Link
                    className="block bg-indigo-600 px-4 py-2 text-center text-xs font-bold text-white"
                    href={safeUrl(settings.announcement_url)}
                >
                    {settings.announcement_text}
                </Link>
            )}
            <header className="sticky top-0 z-40 border-b border-slate-800/80 bg-slate-950/90 backdrop-blur-xl">
                <div className="mx-auto flex h-20 max-w-7xl items-center gap-5 px-4">
                    <Link
                        className="flex items-center gap-2 text-xl font-black"
                        href="/"
                    >
                        <span className="grid size-10 place-items-center rounded-xl bg-indigo-600">
                            <Gamepad2 />
                        </span>
                        NEXUS PLAY
                    </Link>
                    <nav className="hidden items-center gap-6 text-sm text-slate-300 lg:flex">
                        <Link href="/products">فروشگاه</Link>
                        <Link href="/categories">دسته‌بندی‌ها</Link>
                        <Link href="/games">بازی‌ها</Link>
                        <Link href="/offers">تخفیف‌ها</Link>
                    </nav>
                    <div className="mr-auto hidden max-w-sm flex-1 md:block">
                        <Input
                            aria-label="جستجوی محصول"
                            placeholder="بازی، کنسول یا تجهیزات جستجو کنید…"
                        />
                    </div>
                    <Button aria-label="سبد خرید" isIconOnly variant="ghost">
                        <ShoppingCart size={20} />
                    </Button>
                </div>
            </header>
            <main>
                <section className="mx-auto max-w-7xl px-4 pt-5">
                    {slide ? (
                        <div className="relative min-h-[480px] overflow-hidden rounded-3xl border border-slate-800 shadow-2xl md:min-h-[560px]">
                            <picture>
                                <source
                                    media="(max-width: 640px)"
                                    srcSet={
                                        slide.mobile_image_url ??
                                        slide.desktop_image_url
                                    }
                                />
                                <img
                                    alt={slide.title}
                                    className="absolute inset-0 h-full w-full object-cover"
                                    src={slide.desktop_image_url}
                                />
                            </picture>
                            <div
                                className={`absolute inset-0 ${slide.overlay === "dark" ? "bg-black/65" : slide.overlay === "medium" ? "bg-black/45" : "bg-black/25"}`}
                            />
                            <div className="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent" />
                            <div
                                className={`relative z-10 flex min-h-[480px] flex-col justify-center p-7 md:min-h-[560px] md:p-16 ${slide.text_position === "center" ? "mx-auto items-center text-center" : slide.text_position === "left" ? "mr-auto items-end text-left" : "ml-auto items-start text-right"} max-w-3xl`}
                            >
                                {slide.eyebrow && (
                                    <Chip
                                        className="mb-5"
                                        color="accent"
                                        variant="soft"
                                    >
                                        <Sparkles size={15} />
                                        {slide.eyebrow}
                                    </Chip>
                                )}
                                <h1 className="text-4xl font-black leading-tight text-white md:text-6xl">
                                    {slide.title}
                                </h1>
                                {slide.description && (
                                    <p className="mt-5 max-w-2xl text-base leading-8 text-slate-200 md:text-lg">
                                        {slide.description}
                                    </p>
                                )}
                                <div className="mt-8 flex flex-wrap gap-3">
                                    {slide.button_label && (
                                        <Link href={safeUrl(slide.button_url)}>
                                            <Button size="lg" variant="primary">
                                                {slide.button_label}
                                                <ChevronLeft size={18} />
                                            </Button>
                                        </Link>
                                    )}
                                    {slide.secondary_button_label && (
                                        <Link
                                            href={safeUrl(
                                                slide.secondary_button_url,
                                            )}
                                        >
                                            <Button
                                                size="lg"
                                                variant="secondary"
                                            >
                                                {slide.secondary_button_label}
                                            </Button>
                                        </Link>
                                    )}
                                </div>
                            </div>
                            {slides.length > 1 && (
                                <>
                                    <Button
                                        aria-label="اسلاید قبلی"
                                        className="absolute right-4 top-1/2 z-20"
                                        isIconOnly
                                        onPress={() => go(-1)}
                                        variant="secondary"
                                    >
                                        <ChevronRight />
                                    </Button>
                                    <Button
                                        aria-label="اسلاید بعدی"
                                        className="absolute left-4 top-1/2 z-20"
                                        isIconOnly
                                        onPress={() => go(1)}
                                        variant="secondary"
                                    >
                                        <ChevronLeft />
                                    </Button>
                                    <div className="absolute bottom-5 left-1/2 z-20 flex -translate-x-1/2 gap-2">
                                        {slides.map((item, index) => (
                                            <button
                                                aria-label={`اسلاید ${index + 1}`}
                                                className={`h-2 rounded-full transition-all ${index === activeSlide ? "w-8 bg-white" : "w-2 bg-white/40"}`}
                                                key={item.id}
                                                onClick={() =>
                                                    setActiveSlide(index)
                                                }
                                                type="button"
                                            />
                                        ))}
                                    </div>
                                </>
                            )}
                        </div>
                    ) : (
                        <div className="flex min-h-[420px] items-center justify-center rounded-3xl border border-slate-800 bg-[radial-gradient(circle_at_top,#312e81,#020617_65%)] text-center">
                            <div>
                                <Gamepad2
                                    className="mx-auto text-indigo-400"
                                    size={72}
                                />
                                <h1 className="mt-5 text-4xl font-black">
                                    دنیای گیمینگ تو از اینجا شروع می‌شود
                                </h1>
                            </div>
                        </div>
                    )}
                </section>
                <section className="mx-auto grid max-w-7xl grid-cols-2 gap-3 px-4 py-8 lg:grid-cols-4">
                    {[
                        [ShieldCheck, "تضمین اصالت", "خرید مطمئن و معتبر"],
                        [Truck, "ارسال سریع", "تحویل امن سفارش"],
                        [Headphones, "پشتیبانی تخصصی", "همراه گیمرها"],
                        [Sparkles, "پیشنهادهای ویژه", "تخفیف‌های واقعی"],
                    ].map(([Icon, title, text]) => (
                        <div
                            className="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 p-4"
                            key={String(title)}
                        >
                            <span className="grid size-11 shrink-0 place-items-center rounded-xl bg-indigo-500/10 text-indigo-400">
                                <Icon size={21} />
                            </span>
                            <div>
                                <strong className="text-sm text-white">
                                    {String(title)}
                                </strong>
                                <p className="mt-1 text-xs text-slate-500">
                                    {String(text)}
                                </p>
                            </div>
                        </div>
                    ))}
                </section>
                {settings.featured_categories_enabled &&
                    categories.length > 0 && (
                        <section className="mx-auto max-w-7xl px-4 py-10">
                            <div className="mb-6 flex items-end justify-between">
                                <div>
                                    <p className="text-sm font-bold text-indigo-400">
                                        انتخاب سریع
                                    </p>
                                    <h2 className="mt-2 text-2xl font-black md:text-3xl">
                                        {settings.featured_categories_title}
                                    </h2>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3 md:grid-cols-4 lg:grid-cols-8">
                                {categories.map((category) => (
                                    <Link
                                        className="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-center transition hover:border-indigo-500 hover:bg-indigo-500/10"
                                        href={`/categories/${category.slug}`}
                                        key={category.id}
                                    >
                                        <div className="mx-auto mb-3 grid aspect-square place-items-center rounded-xl bg-slate-950">
                                            <Gamepad2
                                                className="text-indigo-400"
                                                size={32}
                                            />
                                        </div>
                                        <strong className="text-sm">
                                            {category.name}
                                        </strong>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {money.format(
                                                category.products_count,
                                            )}{" "}
                                            محصول
                                        </p>
                                    </Link>
                                ))}
                            </div>
                        </section>
                    )}
                {settings.featured_products_enabled && (
                    <section className="mx-auto max-w-7xl px-4 py-10">
                        <div className="mb-6">
                            <p className="text-sm font-bold text-rose-400">
                                منتخب فروشگاه
                            </p>
                            <h2 className="mt-2 text-2xl font-black md:text-3xl">
                                {settings.featured_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={featuredProducts} />
                    </section>
                )}
                {settings.latest_products_enabled && (
                    <section className="mx-auto max-w-7xl px-4 py-10">
                        <div className="mb-6">
                            <p className="text-sm font-bold text-emerald-400">
                                همین حالا اضافه شد
                            </p>
                            <h2 className="mt-2 text-2xl font-black md:text-3xl">
                                {settings.latest_products_title}
                            </h2>
                        </div>
                        <ProductGrid products={latestProducts} />
                    </section>
                )}
                {contentSections.map((section) => (
                    <ContentRail key={section.id} section={section} />
                ))}
                {settings.newsletter_enabled && (
                    <section className="mx-auto max-w-7xl px-4 py-14">
                        <Card
                            className="overflow-hidden border border-indigo-500/30 bg-gradient-to-l from-indigo-950 to-slate-900"
                            variant="secondary"
                        >
                            <Card.Content className="flex flex-col gap-6 p-7 md:flex-row md:items-center md:justify-between md:p-10">
                                <div>
                                    <h2 className="text-2xl font-black text-white">
                                        {settings.newsletter_title}
                                    </h2>
                                    <p className="mt-3 max-w-xl leading-7 text-slate-300">
                                        {settings.newsletter_description}
                                    </p>
                                </div>
                                <div className="flex w-full max-w-md gap-2">
                                    <Input
                                        aria-label="ایمیل خبرنامه"
                                        dir="ltr"
                                        placeholder="you@example.com"
                                        type="email"
                                    />
                                    <Button variant="primary">عضویت</Button>
                                </div>
                            </Card.Content>
                        </Card>
                    </section>
                )}
            </main>
            <footer className="border-t border-slate-800 bg-slate-950">
                <div className="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-8 text-sm text-slate-500 md:flex-row md:items-center md:justify-between">
                    <p>
                        © {new Date().getFullYear()} NEXUS PLAY — همراه دنیای
                        بازی
                    </p>
                    <div className="flex gap-5">
                        <Link href="/pages/about">درباره ما</Link>
                        <Link href="/pages/terms">قوانین</Link>
                        <Link href="/support">پشتیبانی</Link>
                    </div>
                </div>
            </footer>
        </div>
    );
}
