import {
    Alert,
    Button,
    Card,
    Checkbox,
    Chip,
    Input,
    ProgressBar,
    TextArea,
} from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Eye,
    ImagePlus,
    LayoutTemplate,
    Monitor,
    Plus,
    RefreshCw,
    Save,
    Smartphone,
    Trash2,
    UploadCloud,
    X,
} from "lucide-react";
import {
    type ChangeEvent,
    type FormEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import AdminLayout from "../../../Layouts/AdminLayout";
import {
    type UploadProgress,
    uploadFileInChunks,
} from "../../../services/chunkedUpload";

interface HomeSettings {
    home_template: string;
    announcement_enabled: boolean;
    announcement_text: string;
    announcement_url: string;
    featured_categories_enabled: boolean;
    featured_categories_title: string;
    featured_products_enabled: boolean;
    featured_products_title: string;
    latest_products_enabled: boolean;
    latest_products_title: string;
    products_limit: number;
    newsletter_enabled: boolean;
    newsletter_title: string;
    newsletter_description: string;
    seo_title: string;
    seo_description: string;
}

interface HomeSlide {
    id?: number;
    title: string;
    eyebrow: string;
    description: string;
    desktop_image: string;
    desktop_image_url?: string;
    desktop_image_file?: File;
    mobile_image: string;
    mobile_image_url?: string;
    mobile_image_file?: File;
    desktop_upload_token?: string;
    mobile_upload_token?: string;
    alt: string;
    link_type: "url" | "product";
    product_id: number | null;
    button_label: string;
    button_url: string;
    secondary_button_label: string;
    secondary_button_url: string;
    text_position: string;
    overlay: string;
    is_active: boolean;
    starts_at: string;
    ends_at: string;
}
interface HomeSection {
    id?: number;
    title: string;
    subtitle: string;
    content_type: string;
    query_type: string;
    layout: string;
    category_id: number | null;
    item_ids: number[];
    items_limit: number;
    is_active: boolean;
}

interface HomeTemplate {
    key: string;
    label: string;
    focus: "balanced" | "products" | "content";
    description: string;
    available: boolean;
    previewable?: boolean;
    lock_user_override?: boolean;
}

interface Props {
    settings: HomeSettings;
    homeTemplates: HomeTemplate[];
    slides: HomeSlide[];
    sections: HomeSection[];
    categories: Array<{ id: number; name: string }>;
    products: Array<{ id: number; title: string; slug: string }>;
    sectionSources: Record<string, Array<{ id: number; label: string }>>;
}
interface FormData {
    settings: HomeSettings;
    slides: HomeSlide[];
    sections: HomeSection[];
}
type HomeEditorTab = "banners" | "sections" | "general";
const homeEditorTabs: HomeEditorTab[] = ["banners", "sections", "general"];

function TemplateMiniPreview({
    templateKey,
    focus,
}: {
    templateKey: string;
    focus: HomeTemplate["focus"];
}) {
    const block = "rounded-md bg-slate-700/80";
    const accent =
        focus === "products"
            ? "bg-cyan-500/80"
            : focus === "content"
              ? "bg-fuchsia-500/80"
              : "bg-indigo-500/80";

    if (templateKey === "nexus_focus") {
        return (
            <span
                aria-hidden="true"
                className="mb-4 grid h-24 grid-rows-[1.4fr_.45fr_.8fr] gap-1.5 rounded-xl border border-slate-800 bg-slate-950/70 p-2"
            >
                <span className="grid grid-cols-[1.8fr_.7fr] gap-1.5">
                    <span className="rounded-md bg-gradient-to-br from-indigo-500/80 to-cyan-500/65" />
                    <span className="grid gap-1">
                        <span className={block} />
                        <span className={block} />
                    </span>
                </span>
                <span className="grid grid-cols-4 gap-1">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <span className={block} key={index} />
                    ))}
                </span>
                <span className="grid grid-cols-3 gap-1">
                    <span className="rounded-md bg-indigo-500/45" />
                    <span className={block} />
                    <span className={block} />
                </span>
            </span>
        );
    }

    if (templateKey === "dual_spotlight") {
        return (
            <span
                aria-hidden="true"
                className="mb-4 grid h-24 grid-cols-2 gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-2"
            >
                <span className={`${block} bg-fuchsia-500/65`} />
                <span className={`${block} bg-cyan-500/65`} />
                <span className="col-span-2 grid grid-cols-4 gap-1.5">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <span className={block} key={index} />
                    ))}
                </span>
            </span>
        );
    }

    if (templateKey === "storefront") {
        return (
            <span
                aria-hidden="true"
                className="mb-4 grid h-24 grid-cols-[1.6fr_.8fr] gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-2"
            >
                <span className={`${block} bg-cyan-500/65`} />
                <span className="grid gap-1.5">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <span className={block} key={index} />
                    ))}
                </span>
            </span>
        );
    }

    if (templateKey === "default") {
        return (
            <span
                aria-hidden="true"
                className="mb-4 grid h-24 grid-rows-[1.3fr_.7fr] gap-2 rounded-xl border border-slate-800 bg-slate-950/70 p-2"
            >
                <span className={`${block} ${accent}`} />
                <span className="grid grid-cols-3 gap-1.5">
                    {Array.from({ length: 3 }).map((_, index) => (
                        <span className={block} key={index} />
                    ))}
                </span>
            </span>
        );
    }

    return (
        <span
            aria-hidden="true"
            className="mb-4 grid h-24 grid-cols-3 grid-rows-2 gap-1.5 rounded-xl border border-slate-800 bg-slate-950/70 p-2"
        >
            {Array.from({ length: 6 }).map((_, index) => (
                <span
                    className={`${block} ${index === 0 ? accent : ""}`}
                    key={index}
                />
            ))}
        </span>
    );
}

function TemplateLivePreview({
    template,
    onClose,
}: {
    template: HomeTemplate;
    onClose: () => void;
}) {
    const [viewport, setViewport] = useState<
        "desktop" | "desktop-compact" | "mobile" | "mobile-small"
    >("desktop");
    const [refreshKey, setRefreshKey] = useState(0);
    const src = `/?preview_home_template=${encodeURIComponent(template.key)}&admin_template_preview=1&preview_refresh=${refreshKey}`;

    useEffect(() => {
        const previousOverflow = document.body.style.overflow;
        document.body.style.overflow = "hidden";

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === "Escape") onClose();
        };

        window.addEventListener("keydown", onKeyDown);

        return () => {
            document.body.style.overflow = previousOverflow;
            window.removeEventListener("keydown", onKeyDown);
        };
    }, [onClose]);

    return (
        <div
            aria-label={`پیش‌نمایش زنده ${template.label}`}
            aria-modal="true"
            className="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 p-2 backdrop-blur-sm sm:p-5"
            role="dialog"
        >
            <button
                aria-label="بستن پیش‌نمایش"
                className="absolute inset-0 cursor-default"
                onClick={onClose}
                type="button"
            />
            <div className="relative z-10 flex h-[94vh] w-full max-w-[1500px] flex-col overflow-hidden rounded-3xl border border-slate-700 bg-[#080b12] shadow-2xl shadow-black/60">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 px-4 py-3 sm:px-5">
                    <div className="min-w-0">
                        <div className="flex items-center gap-2">
                            <span className="size-2 rounded-full bg-emerald-400" />
                            <strong className="truncate text-sm text-white sm:text-base">
                                پیش‌نمایش زنده — {template.label}
                            </strong>
                        </div>
                        <p className="mt-1 text-[11px] text-slate-500">
                            فقط هنگام باز بودن این پنجره بارگذاری می‌شود و انتخاب شما را ذخیره نمی‌کند.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex rounded-xl border border-slate-800 bg-slate-950 p-1">
                            <button
                                aria-pressed={viewport === "desktop"}
                                className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                                    viewport === "desktop"
                                        ? "bg-indigo-600 text-white"
                                        : "text-slate-400 hover:text-white"
                                }`}
                                onClick={() => setViewport("desktop")}
                                type="button"
                            >
                                <Monitor size={14} />
                                1440px
                            </button>
                            <button
                                aria-pressed={viewport === "desktop-compact"}
                                className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                                    viewport === "desktop-compact"
                                        ? "bg-indigo-600 text-white"
                                        : "text-slate-400 hover:text-white"
                                }`}
                                onClick={() => setViewport("desktop-compact")}
                                type="button"
                            >
                                <Monitor size={13} />
                                1024px
                            </button>
                            <button
                                aria-pressed={viewport === "mobile"}
                                className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                                    viewport === "mobile"
                                        ? "bg-indigo-600 text-white"
                                        : "text-slate-400 hover:text-white"
                                }`}
                                onClick={() => setViewport("mobile")}
                                type="button"
                            >
                                <Smartphone size={14} />
                                390px
                            </button>
                            <button
                                aria-pressed={viewport === "mobile-small"}
                                className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition ${
                                    viewport === "mobile-small"
                                        ? "bg-indigo-600 text-white"
                                        : "text-slate-400 hover:text-white"
                                }`}
                                onClick={() => setViewport("mobile-small")}
                                type="button"
                            >
                                <Smartphone size={13} />
                                320px
                            </button>
                        </div>
                        <button
                            aria-label="بارگذاری مجدد پیش‌نمایش"
                            className="grid size-9 place-items-center rounded-xl border border-slate-800 text-slate-400 transition hover:border-slate-700 hover:text-white"
                            onClick={() => setRefreshKey((value) => value + 1)}
                            type="button"
                        >
                            <RefreshCw size={15} />
                        </button>
                        <a
                            className="grid size-9 place-items-center rounded-xl border border-slate-800 text-slate-400 transition hover:border-slate-700 hover:text-white"
                            href={src}
                            rel="noreferrer"
                            target="_blank"
                            title="باز کردن پیش‌نمایش در تب جدید"
                        >
                            <ExternalLink size={15} />
                        </a>
                        <button
                            aria-label="بستن"
                            className="grid size-9 place-items-center rounded-xl border border-slate-800 text-slate-400 transition hover:border-rose-500/50 hover:text-rose-300"
                            onClick={onClose}
                            type="button"
                        >
                            <X size={16} />
                        </button>
                    </div>
                </div>

                <div className="flex min-h-0 flex-1 items-start justify-center overflow-auto bg-slate-950/80 p-2 sm:p-4">
                    <div
                        className={`h-full overflow-hidden bg-white shadow-2xl transition-[width] duration-200 ${
                            viewport === "mobile"
                                ? "w-[390px] max-w-full rounded-[28px] ring-[8px] ring-slate-800"
                                : viewport === "mobile-small"
                                  ? "w-[320px] max-w-full rounded-[24px] ring-[7px] ring-slate-800"
                                  : viewport === "desktop-compact"
                                    ? "w-[1024px] max-w-full rounded-xl border border-slate-800"
                                    : "w-[1440px] max-w-full rounded-xl border border-slate-800"
                        }`}
                    >
                        <iframe
                            className="h-full w-full bg-white"
                            key={`${template.key}-${refreshKey}`}
                            loading="lazy"
                            referrerPolicy="same-origin"
                            src={src}
                            title={`پیش‌نمایش ${template.label}`}
                        />
                    </div>
                </div>
            </div>
        </div>
    );
}

const blankSlide = (): HomeSlide => ({
    title: "",
    eyebrow: "",
    description: "",
    desktop_image: "",
    mobile_image: "",
    alt: "",
    link_type: "url",
    product_id: null,
    button_label: "مشاهده محصولات",
    button_url: "/products",
    secondary_button_label: "",
    secondary_button_url: "",
    text_position: "right",
    overlay: "dark",
    is_active: true,
    starts_at: "",
    ends_at: "",
});
const blankSection = (): HomeSection => ({
    title: "",
    subtitle: "",
    content_type: "products",
    query_type: "latest",
    layout: "carousel",
    category_id: null,
    item_ids: [],
    items_limit: 10,
    is_active: true,
});

const previewUrl = (file?: File, stored?: string) =>
    file ? URL.createObjectURL(file) : stored;

export default function Edit({
    settings,
    homeTemplates,
    slides,
    sections,
    categories,
    products,
    sectionSources,
}: Props) {
    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm<FormData>({ settings, slides, sections });
    const [uploadProgress, setUploadProgress] = useState<Record<string, UploadProgress>>({});
    const [uploadErrors, setUploadErrors] = useState<Record<string, string>>({});
    const [uploadingKeys, setUploadingKeys] = useState<Set<string>>(new Set());
    const [activeTab, setActiveTab] = useState<HomeEditorTab>("banners");
    const [previewTemplateKey, setPreviewTemplateKey] = useState<string | null>(
        null,
    );
    const feedbackRef = useRef<HTMLDivElement>(null);
    const previewObjectUrls = useRef<Set<string>>(new Set());
    const uploadsInProgress = uploadingKeys.size > 0;

    useEffect(
        () => () => {
            previewObjectUrls.current.forEach((url) => URL.revokeObjectURL(url));
            previewObjectUrls.current.clear();
        },
        [],
    );
    const activeSlides = useMemo(
        () => data.slides.filter((slide) => slide.is_active).length,
        [data.slides],
    );
    const previewTemplate = useMemo(
        () =>
            previewTemplateKey
                ? homeTemplates.find(
                      (template) =>
                          template.key === previewTemplateKey &&
                          (template.available || template.previewable),
                  ) ?? null
                : null,
        [homeTemplates, previewTemplateKey],
    );
    const selectedTemplate = useMemo(
        () =>
            homeTemplates.find(
                (template) =>
                    template.key === data.settings.home_template &&
                    template.available,
            ) ?? null,
        [data.settings.home_template, homeTemplates],
    );

    const updateSetting = <K extends keyof HomeSettings>(
        key: K,
        value: HomeSettings[K],
    ) => setData("settings", { ...data.settings, [key]: value });
    const updateSlide = <K extends keyof HomeSlide>(
        index: number,
        key: K,
        value: HomeSlide[K],
    ) => {
        setData((current) => {
            const next = [...current.slides];
            next[index] = { ...next[index], [key]: value };

            return { ...current, slides: next };
        });
    };
    const moveSlide = (index: number, offset: number) => {
        const target = index + offset;
        if (target < 0 || target >= data.slides.length) return;
        const next = [...data.slides];
        [next[index], next[target]] = [next[target], next[index]];
        setData("slides", next);
    };
    const clearMobileImage = (index: number) => {
        const preview = data.slides[index]?.mobile_image_url;
        if (preview?.startsWith("blob:")) {
            URL.revokeObjectURL(preview);
            previewObjectUrls.current.delete(preview);
        }

        setData((current) => {
            const next = [...current.slides];
            next[index] = {
                ...next[index],
                mobile_image: "",
                mobile_image_url: "",
                mobile_upload_token: undefined,
            };

            return { ...current, slides: next };
        });
        setUploadErrors((current) => ({
            ...current,
            [`${index}-mobile`]: "",
        }));
        setUploadProgress((current) => {
            const next = { ...current };
            delete next[`${index}-mobile`];
            return next;
        });
    };
    const uploadBanner = async (
        index: number,
        kind: "desktop" | "mobile",
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];
        event.target.value = "";
        if (!file) return;
        const key = `${index}-${kind}`;
        const imageUrlKey =
            kind === "desktop" ? "desktop_image_url" : "mobile_image_url";
        const uploadTokenKey =
            kind === "desktop" ? "desktop_upload_token" : "mobile_upload_token";
        const previousPreview = data.slides[index]?.[imageUrlKey];

        setUploadErrors((current) => ({ ...current, [key]: "" }));
        setUploadingKeys((current) => new Set(current).add(key));

        if (previousPreview?.startsWith("blob:")) {
            URL.revokeObjectURL(previousPreview);
            previewObjectUrls.current.delete(previousPreview);
        }

        const preview = URL.createObjectURL(file);
        previewObjectUrls.current.add(preview);
        updateSlide(index, imageUrlKey, preview);

        try {
            const token = await uploadFileInChunks(file, (progress) =>
                setUploadProgress((current) => ({ ...current, [key]: progress })),
            );
            updateSlide(index, uploadTokenKey, token);
        } catch (error) {
            URL.revokeObjectURL(preview);
            previewObjectUrls.current.delete(preview);
            updateSlide(index, imageUrlKey, previousPreview ?? "");
            updateSlide(index, uploadTokenKey, undefined);
            setUploadErrors((current) => ({
                ...current,
                [key]: error instanceof Error ? error.message : "آپلود تصویر انجام نشد.",
            }));
        } finally {
            setUploadingKeys((current) => {
                const next = new Set(current);
                next.delete(key);
                return next;
            });
        }
    };
    const updateSection = <K extends keyof HomeSection>(
        index: number,
        key: K,
        value: HomeSection[K],
    ) => {
        const next = [...data.sections];
        next[index] = { ...next[index], [key]: value };
        setData("sections", next);
    };
    const moveSection = (index: number, offset: number) => {
        const target = index + offset;
        if (target < 0 || target >= data.sections.length) return;
        const next = [...data.sections];
        [next[index], next[target]] = [next[target], next[index]];
        setData("sections", next);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (uploadsInProgress) return;

        post("/admin/home", {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const fresh = page.props as unknown as Pick<
                    Props,
                    "settings" | "slides" | "sections"
                >;
                previewObjectUrls.current.forEach((url) =>
                    URL.revokeObjectURL(url),
                );
                previewObjectUrls.current.clear();
                setData({
                    settings: fresh.settings,
                    slides: fresh.slides,
                    sections: fresh.sections,
                });
                setUploadProgress({});
                setUploadErrors({});

            },
            onError: (validationErrors) => {
                const fields = Object.keys(validationErrors);
                if (fields.some((field) => field.startsWith("slides.")))
                    setActiveTab("banners");
                else if (fields.some((field) => field.startsWith("sections.")))
                    setActiveTab("sections");
                else setActiveTab("general");

                window.setTimeout(
                    () => feedbackRef.current?.scrollIntoView({ behavior: "smooth", block: "center" }),
                    0,
                );
            },
        });
    };

    const actions = (
        <>
            <Button
                isDisabled={!selectedTemplate}
                onPress={() =>
                    selectedTemplate &&
                    setPreviewTemplateKey(selectedTemplate.key)
                }
                variant="secondary"
            >
                <Eye size={17} />
                پیش‌نمایش قالب
            </Button>
            {recentlySuccessful && (
                <Chip
                    className="bg-emerald-500/10 text-emerald-300"
                    size="sm"
                    variant="soft"
                >
                    ذخیره شد
                </Chip>
            )}
            <Button
                isDisabled={processing || uploadsInProgress}
                onPress={() =>
                    document
                        .querySelector<HTMLFormElement>("#home-settings-form")
                        ?.requestSubmit()
                }
                variant="primary"
            >
                <Save size={17} />
                {uploadsInProgress
                    ? "در حال تکمیل آپلود…"
                    : processing
                      ? "در حال ذخیره…"
                      : "ذخیره تغییرات"}
            </Button>
        </>
    );

    return (
        <AdminLayout
            description="بنرهای اسلایدری، ترتیب سکشن‌ها، پیام‌های بازاریابی و سئوی صفحه اول را مدیریت کنید."
            title="مدیریت صفحه اصلی"
        >
            <Head title="مدیریت صفحه اصلی" />
            <form
                className="space-y-7"
                id="home-settings-form"
                onSubmit={submit}
            >
                <div ref={feedbackRef}>
                    {recentlySuccessful && (
                        <Alert color="success">
                            تغییرات و بنرهای صفحه اصلی با موفقیت ذخیره شدند.
                        </Alert>
                    )}
                    {Object.keys(errors).length > 0 && (
                        <Alert color="danger">
                            <div>
                                <p className="font-bold">ذخیره انجام نشد؛ موارد زیر را اصلاح کنید:</p>
                                <ul className="mt-2 list-disc space-y-1 pr-5 text-sm">
                                    {Object.entries(errors).map(([field, message]) => (
                                        <li key={field}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        </Alert>
                    )}
                    {uploadsInProgress && (
                        <Alert color="warning">
                            لطفاً تا پایان آپلود {uploadingKeys.size.toLocaleString("fa-IR")} تصویر صبر کنید؛ سپس ذخیره فعال می‌شود.
                        </Alert>
                    )}
                </div>

                <div className="sticky top-20 z-20 -mx-4 border-y border-slate-800/80 bg-[#080b12]/95 px-4 py-3 shadow-2xl shadow-black/20 backdrop-blur-xl sm:-mx-7 sm:px-7 lg:-mx-8 lg:px-8">
                    <div className="flex flex-col gap-3 xl:flex-row xl:items-center">
                        <div className="grid flex-1 grid-cols-3 gap-2 rounded-2xl border border-slate-800 bg-slate-950/80 p-2" role="tablist">
                            {([
                                ["banners", "بنرها", `${activeSlides.toLocaleString("fa-IR")} بنر فعال`],
                                ["sections", "مدیریت سکشن‌ها", `${data.sections.length.toLocaleString("fa-IR")} سکشن`],
                                ["general", "قالب و تنظیمات", "قالب، پیام‌ها، فروشگاه و سئو"],
                            ] as const).map(([id, label, description]) => (
                                <button
                                    aria-controls={`home-panel-${id}`}
                                    aria-selected={activeTab === id}
                                    className={`rounded-xl px-2 py-2.5 text-center transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:px-4 sm:py-3 sm:text-right ${activeTab === id ? "bg-indigo-600 text-white shadow-lg shadow-indigo-950/30" : "text-slate-400 hover:bg-slate-800/70 hover:text-white"}`}
                                    id={`home-tab-${id}`}
                                    key={id}
                                    onClick={() => setActiveTab(id)}
                                    onKeyDown={(event) => {
                                        if (!["ArrowLeft", "ArrowRight"].includes(event.key)) return;
                                        event.preventDefault();
                                        const current = homeEditorTabs.indexOf(id);
                                        const offset = event.key === "ArrowLeft" ? 1 : -1;
                                        const next =
                                            (current + offset + homeEditorTabs.length) %
                                            homeEditorTabs.length;
                                        setActiveTab(homeEditorTabs[next]);
                                        document
                                            .getElementById(`home-tab-${homeEditorTabs[next]}`)
                                            ?.focus();
                                    }}
                                    role="tab"
                                    tabIndex={activeTab === id ? 0 : -1}
                                    type="button"
                                >
                                    <strong className="block text-[11px] sm:text-sm">{label}</strong>
                                    <span className={`mt-1 hidden text-xs sm:block ${activeTab === id ? "text-indigo-100" : "text-slate-500"}`}>{description}</span>
                                </button>
                            ))}
                        </div>
                        <div className="flex shrink-0 items-center justify-end gap-2">
                            {actions}
                        </div>
                    </div>
                </div>

                <Card
                    aria-labelledby="home-tab-banners"
                    className={`${activeTab === "banners" ? "" : "hidden"} border border-slate-800 bg-slate-900/60`}
                    id="home-panel-banners"
                    role="tabpanel"
                    variant="secondary"
                >
                    <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                        <div>
                            <Card.Title>اسلایدر اصلی</Card.Title>
                            <Card.Description>
                                تصویر پیشنهادی دسکتاپ ۱۹۲۰×۷۲۰ است؛ برای موبایل یک برش افقی نزدیک ۲.۳۵:۱ مثل ۱۲۰۰×۵۲۰ بهترین نتیجه را می‌دهد.
                            </Card.Description>
                        </div>
                        <Button
                            onPress={() =>
                                setData("slides", [
                                    ...data.slides,
                                    blankSlide(),
                                ])
                            }
                            variant="primary"
                        >
                            <Plus size={17} />
                            بنر جدید
                        </Button>
                    </Card.Header>
                    <Card.Content className="space-y-5 p-5">
                        {data.slides.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-700 p-10 text-center">
                                <LayoutTemplate
                                    className="mx-auto mb-3 text-slate-600"
                                    size={36}
                                />
                                <p className="text-slate-400">
                                    برای شروع اولین بنر اسلایدر را اضافه کنید.
                                </p>
                            </div>
                        )}
                        {data.slides.map((slide, index) => (
                            <Card
                                className="overflow-hidden border border-slate-700 bg-slate-950/40"
                                key={slide.id ?? `new-${index}`}
                                variant="secondary"
                            >
                                <div className="relative h-48 bg-slate-900">
                                    {previewUrl(
                                        slide.desktop_image_file,
                                        slide.desktop_image_url,
                                    ) ? (
                                        <img
                                            alt="پیش‌نمایش بنر"
                                            className="h-full w-full object-cover"
                                            src={previewUrl(
                                                slide.desktop_image_file,
                                                slide.desktop_image_url,
                                            )}
                                        />
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-slate-600">
                                            <ImagePlus size={42} />
                                        </div>
                                    )}
                                    <Chip className="absolute right-4 top-4" variant="soft">
                                        بنر {index + 1}
                                    </Chip>
                                </div>
                                <Card.Content className="grid gap-5 p-5 lg:grid-cols-2">
                                    <div className="space-y-3">
                                        <FormField description="JPG، PNG یا WebP تا ۱۰ مگابایت" label="تصویر بنر" required={!slide.desktop_image}>
                                            <label className="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-700 bg-slate-900/60 text-center transition hover:border-indigo-500 focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500/30">
                                                <UploadCloud className="mb-2 text-indigo-400" size={28} />
                                                <span className="text-sm font-bold text-white">انتخاب تصویر</span>
                                                <input accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(event) => void uploadBanner(index, "desktop", event)} type="file" />
                                            </label>
                                        </FormField>
                                        {uploadProgress[`${index}-desktop`] && (
                                            <div className="space-y-2 text-xs text-slate-400">
                                                <div className="flex justify-between"><span>در حال آپلود</span><span>{uploadProgress[`${index}-desktop`].percentage.toLocaleString("fa-IR")}٪</span></div>
                                                <ProgressBar value={uploadProgress[`${index}-desktop`].percentage} />
                                            </div>
                                        )}
                                        {uploadErrors[`${index}-desktop`] && <p className="text-sm text-red-400">{uploadErrors[`${index}-desktop`]}</p>}

                                        <FormField
                                            description="اختیاری؛ اگر انتخاب نشود تصویر دسکتاپ استفاده می‌شود. برای وب موبایل برش افقی نزدیک ۲.۳۵:۱ مثل ۱۲۰۰×۵۲۰ پیشنهاد می‌شود."
                                            label="تصویر مخصوص موبایل"
                                        >
                                            {previewUrl(
                                                slide.mobile_image_file,
                                                slide.mobile_image_url,
                                            ) && (
                                                <div className="mb-3 rounded-2xl border border-slate-800 bg-slate-950/60 p-3">
                                                    <div className="flex justify-center">
                                                        <div className="aspect-[2.35/1] w-full max-w-xs overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-xl">
                                                            <img
                                                                alt="پیش‌نمایش موبایل بنر"
                                                                className="size-full object-contain"
                                                                src={previewUrl(
                                                                    slide.mobile_image_file,
                                                                    slide.mobile_image_url,
                                                                )}
                                                            />
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 flex justify-center">
                                                        <Button
                                                            onPress={() =>
                                                                clearMobileImage(index)
                                                            }
                                                            size="sm"
                                                            variant="danger-soft"
                                                        >
                                                            <Trash2 size={14} />
                                                            حذف نسخه موبایل
                                                        </Button>
                                                    </div>
                                                </div>
                                            )}
                                            <label className="flex min-h-24 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-700 bg-slate-900/60 text-center transition hover:border-indigo-500 focus-within:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500/30">
                                                <UploadCloud className="mb-2 text-fuchsia-400" size={24} />
                                                <span className="text-sm font-bold text-white">
                                                    {slide.mobile_image_url
                                                        ? "تغییر تصویر موبایل"
                                                        : "انتخاب تصویر موبایل"}
                                                </span>
                                                <input
                                                    accept="image/jpeg,image/png,image/webp"
                                                    className="sr-only"
                                                    onChange={(event) =>
                                                        void uploadBanner(
                                                            index,
                                                            "mobile",
                                                            event,
                                                        )
                                                    }
                                                    type="file"
                                                />
                                            </label>
                                        </FormField>
                                        {uploadProgress[`${index}-mobile`] && (
                                            <div className="space-y-2 text-xs text-slate-400">
                                                <div className="flex justify-between">
                                                    <span>در حال آپلود نسخه موبایل</span>
                                                    <span>
                                                        {uploadProgress[`${index}-mobile`].percentage.toLocaleString("fa-IR")}٪
                                                    </span>
                                                </div>
                                                <ProgressBar
                                                    value={
                                                        uploadProgress[`${index}-mobile`]
                                                            .percentage
                                                    }
                                                />
                                            </div>
                                        )}
                                        {uploadErrors[`${index}-mobile`] && (
                                            <p className="text-sm text-red-400">
                                                {uploadErrors[`${index}-mobile`]}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-4">
                                    <FormField description="برای دسترس‌پذیری و سئو، خود تصویر را کوتاه توصیف کنید." label="متن جایگزین تصویر (alt)" required>
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "alt",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="مثلاً تخفیف بازی‌های پلی‌استیشن ۵"
                                            value={slide.alt}
                                        />
                                    </FormField>
                                    <HeroSelect
                                        label="با کلیک روی بنر"
                                        onChange={(value) =>
                                            updateSlide(
                                                index,
                                                "link_type",
                                                value as "url" | "product",
                                            )
                                        }
                                        options={[
                                            { id: "url", label: "رفتن به یک لینک" },
                                            { id: "product", label: "باز کردن محصول مرتبط" },
                                        ]}
                                        value={slide.link_type}
                                    />
                                    {slide.link_type === "url" ? <FormField label="آدرس لینک" required>
                                        <Input
                                            dir="ltr"
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "button_url",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="/products یا https://..."
                                            value={slide.button_url}
                                        />
                                    </FormField> : <FormField description="نام محصول را تایپ و از پیشنهادها انتخاب کنید." label="محصول مرتبط" required>
                                        <Input
                                            fullWidth
                                            list={`banner-products-${index}`}
                                            onChange={(event) => {
                                                const product = products.find((item) => item.title === event.target.value);
                                                updateSlide(index, "product_id", product?.id ?? null);
                                            }}
                                            placeholder="جست‌وجوی نام محصول…"
                                            value={products.find((item) => item.id === slide.product_id)?.title ?? ""}
                                        />
                                        <datalist id={`banner-products-${index}`}>
                                            {products.map((product) => <option key={product.id} value={product.title} />)}
                                        </datalist>
                                    </FormField>}
                                    <div>
                                        <Checkbox
                                            isSelected={slide.is_active}
                                            onChange={(selected) =>
                                                updateSlide(
                                                    index,
                                                    "is_active",
                                                    selected,
                                                )
                                            }
                                        >
                                            <Checkbox.Control>
                                                <Checkbox.Indicator />
                                            </Checkbox.Control>
                                            <Checkbox.Content>
                                                بنر فعال باشد
                                            </Checkbox.Content>
                                        </Checkbox>
                                    </div>
                                    </div>
                                </Card.Content>
                                <Card.Footer className="flex justify-between border-t border-slate-800 px-5 py-3">
                                    <div className="flex gap-2">
                                        <Button
                                            aria-label="انتقال به بالا"
                                            isDisabled={index === 0}
                                            isIconOnly
                                            onPress={() => moveSlide(index, -1)}
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <ArrowUp size={16} />
                                        </Button>
                                        <Button
                                            aria-label="انتقال به پایین"
                                            isDisabled={
                                                index === data.slides.length - 1
                                            }
                                            isIconOnly
                                            onPress={() => moveSlide(index, 1)}
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <ArrowDown size={16} />
                                        </Button>
                                    </div>
                                    <Button
                                        onPress={() =>
                                            setData(
                                                "slides",
                                                data.slides.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                        size="sm"
                                        variant="danger-soft"
                                    >
                                        <Trash2 size={16} />
                                        حذف بنر
                                    </Button>
                                </Card.Footer>
                            </Card>
                        ))}
                    </Card.Content>
                </Card>

                <Card
                    aria-labelledby="home-tab-sections"
                    className={`${activeTab === "sections" ? "" : "hidden"} border border-slate-800 bg-slate-900/60`}
                    id="home-panel-sections"
                    role="tabpanel"
                    variant="secondary"
                >
                    <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                        <div>
                            <Card.Title>اسلایدرهای محتوایی Home</Card.Title>
                            <Card.Description>
                                ردیف‌های هوشمند محصول، پست، ویدیو و شورت را با
                                ترتیب دلخواه بسازید.
                            </Card.Description>
                        </div>
                        <Button
                            onPress={() =>
                                setData("sections", [
                                    ...data.sections,
                                    blankSection(),
                                ])
                            }
                            variant="primary"
                        >
                            <Plus size={17} />
                            سکشن جدید
                        </Button>
                    </Card.Header>
                    <Card.Content className="space-y-4 p-5">
                        {data.sections.length === 0 && (
                            <div className="rounded-2xl border border-dashed border-slate-700 p-8 text-center text-sm text-slate-400">
                                هنوز اسلایدر محتوایی تعریف نشده است.
                            </div>
                        )}
                        {data.sections.map((section, index) => (
                            <Card
                                className="border border-slate-700 bg-slate-950/40"
                                key={section.id ?? `section-${index}`}
                                variant="secondary"
                            >
                                <Card.Content className="grid gap-4 p-4 md:grid-cols-2 xl:grid-cols-4">
                                    <FormField label="عنوان سکشن" required>
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    "title",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="مثلاً تازه‌ترین بازی‌های PS5"
                                            value={section.title}
                                        />
                                    </FormField>
                                    <FormField label="توضیح کوتاه">
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    "subtitle",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="انتخاب‌های جدید برای شما"
                                            value={section.subtitle}
                                        />
                                    </FormField>
                                    <HeroSelect
                                        label="نوع محتوا"
                                        onChange={(value) =>
                                            updateSection(
                                                index,
                                                "content_type",
                                                value,
                                            )
                                        }
                                        options={[
                                            {
                                                id: "products",
                                                label: "محصولات",
                                            },
                                            { id: "categories", label: "دسته‌بندی‌ها" },
                                            { id: "games", label: "بازی‌ها" },
                                            { id: "brands", label: "برندها" },
                                            { id: "platforms", label: "پلتفرم‌ها" },
                                            { id: "posts", label: "پست‌ها" },
                                            { id: "videos", label: "ویدیوها" },
                                            { id: "shorts", label: "شورت‌ها" },
                                        ]}
                                        value={section.content_type}
                                    />
                                    <HeroSelect
                                        label="روش انتخاب"
                                        onChange={(value) =>
                                            updateSection(
                                                index,
                                                "query_type",
                                                value,
                                            )
                                        }
                                        options={[
                                            { id: "latest", label: "جدیدترین" },
                                            { id: "featured", label: "منتخب" },
                                            {
                                                id: "popular",
                                                label: "محبوب‌ترین",
                                            },
                                            { id: "manual", label: "انتخاب دستی" },
                                            ...(section.content_type ===
                                            "products"
                                                ? [
                                                      {
                                                          id: "category",
                                                          label: "از یک دسته خاص",
                                                      },
                                                  ]
                                                : []),
                                        ]}
                                        value={section.query_type}
                                    />
                                    <HeroSelect
                                        label="چیدمان"
                                        onChange={(value) => updateSection(index, "layout", value)}
                                        options={[
                                            { id: "carousel", label: "اسلایدر افقی" },
                                            { id: "grid", label: "شبکه‌ای" },
                                            { id: "featured", label: "ویژه و بزرگ" },
                                            { id: "compact", label: "فشرده" },
                                        ]}
                                        value={section.layout ?? "carousel"}
                                    />
                                    {section.content_type === "products" &&
                                        section.query_type === "category" && (
                                            <HeroSelect
                                                label="دسته‌بندی محصول"
                                                onChange={(value) =>
                                                    updateSection(
                                                        index,
                                                        "category_id",
                                                        value
                                                            ? Number(value)
                                                            : null,
                                                    )
                                                }
                                                options={categories.map(
                                                    (category) => ({
                                                        id: String(category.id),
                                                        label: category.name,
                                                    }),
                                                )}
                                                value={
                                                    section.category_id?.toString() ??
                                                    ""
                                                }
                                            />
                                        )}
                                    {section.query_type === "manual" && (
                                        <div className="md:col-span-2 xl:col-span-4">
                                            <p className="mb-3 text-sm font-bold text-slate-200">آیتم‌های این سکشن</p>
                                            {(sectionSources[section.content_type] ?? []).length ? (
                                                <div className={`grid max-h-52 gap-2 overflow-y-auto rounded-2xl border p-3 sm:grid-cols-2 lg:grid-cols-3 ${(section.item_ids ?? []).length ? "border-slate-800" : "border-amber-500/60 bg-amber-500/5"}`}>
                                                    {(sectionSources[section.content_type] ?? []).map((item) => (
                                                    <Checkbox
                                                        isSelected={(section.item_ids ?? []).includes(item.id)}
                                                        key={item.id}
                                                        onChange={(selected) => updateSection(index, "item_ids", selected ? [...(section.item_ids ?? []), item.id] : (section.item_ids ?? []).filter((id) => id !== item.id))}
                                                    >
                                                        <Checkbox.Control><Checkbox.Indicator /></Checkbox.Control>
                                                        <Checkbox.Content>{item.label}</Checkbox.Content>
                                                    </Checkbox>
                                                    ))}
                                                </div>
                                            ) : (
                                                <div className="rounded-2xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm text-amber-200">
                                                    برای این نوع محتوا هنوز آیتم منتشرشده‌ای وجود ندارد.
                                                </div>
                                            )}
                                            {(sectionSources[section.content_type] ?? []).length > 0 && !(section.item_ids ?? []).length && (
                                                <p className="mt-2 text-xs text-amber-300">برای نمایش این سکشن در صفحه اصلی، حداقل یک آیتم انتخاب کنید.</p>
                                            )}
                                        </div>
                                    )}
                                    <FormField label="تعداد آیتم">
                                        <Input
                                            fullWidth
                                            max="20"
                                            min="4"
                                            onChange={(e) =>
                                                updateSection(
                                                    index,
                                                    "items_limit",
                                                    Number(e.target.value),
                                                )
                                            }
                                            type="number"
                                            value={String(section.items_limit)}
                                        />
                                    </FormField>
                                    <div className="flex items-end">
                                        <Checkbox
                                            isSelected={section.is_active}
                                            onChange={(value) =>
                                                updateSection(
                                                    index,
                                                    "is_active",
                                                    value,
                                                )
                                            }
                                        >
                                            <Checkbox.Control>
                                                <Checkbox.Indicator />
                                            </Checkbox.Control>
                                            <Checkbox.Content>
                                                نمایش در Home
                                            </Checkbox.Content>
                                        </Checkbox>
                                    </div>
                                </Card.Content>
                                <Card.Footer className="flex justify-between border-t border-slate-800 px-4 py-3">
                                    <div className="flex gap-2">
                                        <Button
                                            aria-label="بالا"
                                            isDisabled={index === 0}
                                            isIconOnly
                                            onPress={() =>
                                                moveSection(index, -1)
                                            }
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <ArrowUp size={16} />
                                        </Button>
                                        <Button
                                            aria-label="پایین"
                                            isDisabled={
                                                index ===
                                                data.sections.length - 1
                                            }
                                            isIconOnly
                                            onPress={() =>
                                                moveSection(index, 1)
                                            }
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <ArrowDown size={16} />
                                        </Button>
                                    </div>
                                    <Button
                                        onPress={() =>
                                            setData(
                                                "sections",
                                                data.sections.filter(
                                                    (_, i) => i !== index,
                                                ),
                                            )
                                        }
                                        size="sm"
                                        variant="danger-soft"
                                    >
                                        <Trash2 size={16} />
                                        حذف سکشن
                                    </Button>
                                </Card.Footer>
                            </Card>
                        ))}
                    </Card.Content>
                </Card>

                <div
                    aria-labelledby="home-tab-general"
                    className={`${activeTab === "general" ? "grid" : "hidden"} gap-6 xl:grid-cols-2`}
                    id="home-panel-general"
                    role="tabpanel"
                >
                    <Card
                        className="border border-slate-800 bg-slate-900/60 xl:col-span-2"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <div className="flex items-center gap-3">
                                <span className="grid size-11 place-items-center rounded-2xl bg-indigo-500/10 text-indigo-400">
                                    <LayoutTemplate size={21} />
                                </span>
                                <div>
                                    <Card.Title>قالب صفحه اصلی</Card.Title>
                                    <Card.Description>
                                        تغییر قالب فقط بعد از ذخیره توسط ادمین روی سایت عمومی اعمال می‌شود. انتخاب داخل این صفحه تا قبل از ذخیره هیچ تغییری برای کاربران ایجاد نمی‌کند.
                                    </Card.Description>
                                </div>
                            </div>
                        </Card.Header>
                        <Card.Content className="grid gap-3 p-5 md:grid-cols-2 xl:grid-cols-3">
                            {homeTemplates.map((template) => {
                                const selected =
                                    data.settings.home_template === template.key;
                                const persisted =
                                    settings.home_template === template.key;
                                const focusLabel =
                                    template.focus === "products"
                                        ? "محصول‌محور"
                                        : template.focus === "content"
                                          ? "محتوامحور"
                                          : "متعادل";

                                return (
                                    <article
                                        className={`rounded-2xl border p-4 text-right transition ${selected ? "border-indigo-500 bg-indigo-500/10 shadow-lg shadow-indigo-950/20" : "border-slate-800 bg-slate-950/50"} ${template.available || template.previewable ? "hover:border-indigo-500/60" : "opacity-60"}`}
                                        key={template.key}
                                    >
                                        <button
                                            aria-pressed={selected}
                                            className={`block w-full text-right focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-indigo-400 ${template.available ? "" : "cursor-not-allowed"}`}
                                            disabled={!template.available}
                                            onClick={() =>
                                                template.available &&
                                                updateSetting(
                                                    "home_template",
                                                    template.key,
                                                )
                                            }
                                            type="button"
                                        >
                                            <TemplateMiniPreview
                                                focus={template.focus}
                                                templateKey={template.key}
                                            />
                                            <span className="flex items-start justify-between gap-3">
                                                <span>
                                                    <strong className="block text-sm text-white">
                                                        {template.label}
                                                    </strong>
                                                    <small className="mt-1 block text-[10px] font-bold text-indigo-300">
                                                        {focusLabel}
                                                    </small>
                                                </span>
                                                <Chip
                                                    color={
                                                        selected
                                                            ? "accent"
                                                            : undefined
                                                    }
                                                    size="sm"
                                                    variant="soft"
                                                >
                                                    {selected
                                                        ? persisted
                                                            ? "فعال"
                                                            : "انتخاب‌شده"
                                                        : persisted
                                                          ? "فعال روی سایت"
                                                          : template.available
                                                            ? "آماده"
                                                            : template.previewable
                                                              ? "پیش‌نمایش"
                                                              : "در حال ساخت"}
                                                </Chip>
                                            </span>
                                            <p className="mt-3 text-xs leading-6 text-slate-400">
                                                {template.description}
                                            </p>
                                            {selected && !persisted && (
                                                <p className="mt-2 text-[10px] font-bold text-amber-300">
                                                    این انتخاب هنوز ذخیره نشده است؛ بعد از «ذخیره تغییرات» برای کاربران فعال می‌شود.
                                                </p>
                                            )}
                                            {persisted && template.lock_user_override && (
                                                <p className="mt-2 text-[10px] font-bold text-emerald-300">
                                                    این قالب در حال حاضر برای همه کاربران فعال است و preference کاربر آن را عوض نمی‌کند.
                                                </p>
                                            )}
                                        </button>

                                        <div className="mt-4 flex items-center justify-between gap-2 border-t border-slate-800/80 pt-3">
                                            <span className="text-[10px] text-slate-500">
                                                پیش‌نمایش فقط در صورت درخواست بارگذاری می‌شود.
                                            </span>
                                            <Button
                                                isDisabled={
                                                    !template.available &&
                                                    !template.previewable
                                                }
                                                onPress={() =>
                                                    setPreviewTemplateKey(
                                                        template.key,
                                                    )
                                                }
                                                size="sm"
                                                variant="secondary"
                                            >
                                                <Eye size={14} />
                                                پیش‌نمایش زنده
                                            </Button>
                                        </div>
                                    </article>
                                );
                            })}
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>نوار اطلاع‌رسانی</Card.Title>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            <Checkbox
                                isSelected={data.settings.announcement_enabled}
                                onChange={(v) =>
                                    updateSetting("announcement_enabled", v)
                                }
                            >
                                <Checkbox.Control>
                                    <Checkbox.Indicator />
                                </Checkbox.Control>
                                <Checkbox.Content>
                                    نمایش نوار بالای سایت
                                </Checkbox.Content>
                            </Checkbox>
                            <FormField label="متن پیام">
                                <Input
                                    fullWidth
                                    onChange={(e) =>
                                        updateSetting(
                                            "announcement_text",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.announcement_text}
                                />
                            </FormField>
                            <FormField label="لینک">
                                <Input
                                    dir="ltr"
                                    fullWidth
                                    onChange={(e) =>
                                        updateSetting(
                                            "announcement_url",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.announcement_url}
                                />
                            </FormField>
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>سکشن‌های فروشگاه</Card.Title>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            {(
                                [
                                    [
                                        "featured_categories_enabled",
                                        "featured_categories_title",
                                        "دسته‌بندی‌های منتخب",
                                    ],
                                    [
                                        "featured_products_enabled",
                                        "featured_products_title",
                                        "محصولات ویژه",
                                    ],
                                    [
                                        "latest_products_enabled",
                                        "latest_products_title",
                                        "تازه‌رسیده‌ها",
                                    ],
                                ] as const
                            ).map(([enabled, title, label]) => (
                                <div
                                    className="grid items-end gap-3 sm:grid-cols-[auto_1fr]"
                                    key={enabled}
                                >
                                    <Checkbox
                                        isSelected={data.settings[enabled]}
                                        onChange={(v) =>
                                            updateSetting(enabled, v)
                                        }
                                    >
                                        <Checkbox.Control>
                                            <Checkbox.Indicator />
                                        </Checkbox.Control>
                                        <Checkbox.Content>
                                            {label}
                                        </Checkbox.Content>
                                    </Checkbox>
                                    <Input
                                        fullWidth
                                        onChange={(e) =>
                                            updateSetting(title, e.target.value)
                                        }
                                        value={data.settings[title]}
                                    />
                                </div>
                            ))}
                            <FormField label="تعداد محصول در هر سکشن">
                                <Input
                                    fullWidth
                                    max="16"
                                    min="4"
                                    onChange={(e) =>
                                        updateSetting(
                                            "products_limit",
                                            Number(e.target.value),
                                        )
                                    }
                                    type="number"
                                    value={String(data.settings.products_limit)}
                                />
                            </FormField>
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>عضویت در خبرنامه</Card.Title>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            <Checkbox
                                isSelected={data.settings.newsletter_enabled}
                                onChange={(v) =>
                                    updateSetting("newsletter_enabled", v)
                                }
                            >
                                <Checkbox.Control>
                                    <Checkbox.Indicator />
                                </Checkbox.Control>
                                <Checkbox.Content>
                                    نمایش دعوت به خبرنامه
                                </Checkbox.Content>
                            </Checkbox>
                            <FormField label="عنوان">
                                <Input
                                    fullWidth
                                    onChange={(e) =>
                                        updateSetting(
                                            "newsletter_title",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.newsletter_title}
                                />
                            </FormField>
                            <FormField label="توضیح">
                                <TextArea
                                    fullWidth
                                    onChange={(e) =>
                                        updateSetting(
                                            "newsletter_description",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.newsletter_description}
                                />
                            </FormField>
                        </Card.Content>
                    </Card>
                    <Card
                        className="border border-slate-800 bg-slate-900/60"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>سئوی صفحه اصلی</Card.Title>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            <FormField
                                description={`${data.settings.seo_title.length} از ۶۰ کاراکتر`}
                                label="عنوان SEO"
                            >
                                <Input
                                    fullWidth
                                    maxLength={60}
                                    onChange={(e) =>
                                        updateSetting(
                                            "seo_title",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.seo_title}
                                />
                            </FormField>
                            <FormField
                                description={`${data.settings.seo_description.length} از ۱۶۰ کاراکتر`}
                                label="توضیحات SEO"
                            >
                                <TextArea
                                    fullWidth
                                    maxLength={160}
                                    onChange={(e) =>
                                        updateSetting(
                                            "seo_description",
                                            e.target.value,
                                        )
                                    }
                                    value={data.settings.seo_description}
                                />
                            </FormField>
                        </Card.Content>
                    </Card>
                </div>
            </form>

            {previewTemplate && (
                <TemplateLivePreview
                    onClose={() => setPreviewTemplateKey(null)}
                    template={previewTemplate}
                />
            )}
        </AdminLayout>
    );
}
