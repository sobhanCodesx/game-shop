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
    Eye,
    ImagePlus,
    LayoutTemplate,
    Plus,
    Save,
    Trash2,
    UploadCloud,
} from "lucide-react";
import {
    type ChangeEvent,
    type FormEvent,
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

interface Props {
    settings: HomeSettings;
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
    const feedbackRef = useRef<HTMLDivElement>(null);
    const uploadsInProgress = uploadingKeys.size > 0;
    const activeSlides = useMemo(
        () => data.slides.filter((slide) => slide.is_active).length,
        [data.slides],
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
    const uploadBanner = async (
        index: number,
        kind: "desktop" | "mobile",
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const file = event.target.files?.[0];
        event.target.value = "";
        if (!file) return;
        const key = `${index}-${kind}`;
        setUploadErrors((current) => ({ ...current, [key]: "" }));
        setUploadingKeys((current) => new Set(current).add(key));
        const preview = URL.createObjectURL(file);
        updateSlide(index, `${kind}_image_url` as keyof HomeSlide, preview);
        try {
            const token = await uploadFileInChunks(file, (progress) =>
                setUploadProgress((current) => ({ ...current, [key]: progress })),
            );
            updateSlide(index, `${kind}_upload_token` as keyof HomeSlide, token);
        } catch (error) {
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
            onSuccess: () =>
                window.setTimeout(
                    () => feedbackRef.current?.scrollIntoView({ behavior: "smooth", block: "center" }),
                    0,
                ),
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
            <Link href="/" target="_blank">
                <Button variant="secondary">
                    <Eye size={17} />
                    مشاهده Home
                </Button>
            </Link>
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
            actions={actions}
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

                <div className="grid gap-2 rounded-2xl border border-slate-800 bg-slate-950/60 p-2 sm:grid-cols-3" role="tablist">
                    {([
                        ["banners", "بنرها", `${activeSlides.toLocaleString("fa-IR")} بنر فعال`],
                        ["sections", "مدیریت سکشن‌ها", `${data.sections.length.toLocaleString("fa-IR")} سکشن`],
                        ["general", "اطلاعات کلی سایت", "پیام‌ها، فروشگاه و سئو"],
                    ] as const).map(([id, label, description]) => (
                        <button
                            aria-selected={activeTab === id}
                            className={`rounded-xl px-4 py-3 text-right transition ${activeTab === id ? "bg-indigo-600 text-white shadow-lg shadow-indigo-950/30" : "text-slate-400 hover:bg-slate-800/70 hover:text-white"}`}
                            key={id}
                            onClick={() => setActiveTab(id)}
                            role="tab"
                            type="button"
                        >
                            <strong className="block text-sm">{label}</strong>
                            <span className={`mt-1 block text-xs ${activeTab === id ? "text-indigo-100" : "text-slate-500"}`}>{description}</span>
                        </button>
                    ))}
                </div>

                <Card
                    className={`${activeTab === "banners" ? "" : "hidden"} border border-slate-800 bg-slate-900/60`}
                    variant="secondary"
                >
                    <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                        <div>
                            <Card.Title>اسلایدر اصلی</Card.Title>
                            <Card.Description>
                                تصویر پیشنهادی دسکتاپ ۱۹۲۰×۷۲۰ و موبایل ۸۰۰×۱۰۰۰
                                پیکسل است.
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
                                            <label className="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-700 bg-slate-900/60 text-center transition hover:border-indigo-500">
                                                <UploadCloud className="mb-2 text-indigo-400" size={28} />
                                                <span className="text-sm font-bold text-white">انتخاب یا رها کردن تصویر</span>
                                                <input accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => void uploadBanner(index, "desktop", event)} type="file" />
                                            </label>
                                        </FormField>
                                        {uploadProgress[`${index}-desktop`] && (
                                            <div className="space-y-2 text-xs text-slate-400">
                                                <div className="flex justify-between"><span>در حال آپلود</span><span>{uploadProgress[`${index}-desktop`].percentage.toLocaleString("fa-IR")}٪</span></div>
                                                <ProgressBar value={uploadProgress[`${index}-desktop`].percentage} />
                                            </div>
                                        )}
                                        {uploadErrors[`${index}-desktop`] && <p className="text-sm text-red-400">{uploadErrors[`${index}-desktop`]}</p>}
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
                    className={`${activeTab === "sections" ? "" : "hidden"} border border-slate-800 bg-slate-900/60`}
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

                <div className={`${activeTab === "general" ? "grid" : "hidden"} gap-6 xl:grid-cols-2`}>
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
        </AdminLayout>
    );
}
