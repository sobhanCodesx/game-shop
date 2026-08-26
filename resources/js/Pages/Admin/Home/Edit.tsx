import {
    Alert,
    Button,
    Card,
    Checkbox,
    Chip,
    Input,
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
} from "lucide-react";
import { type FormEvent, useMemo } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import PersianDatePicker from "../../../Components/Admin/Form/PersianDatePicker";
import AdminLayout from "../../../Layouts/AdminLayout";

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
    category_id: number | null;
    items_limit: number;
    is_active: boolean;
}

interface Props {
    settings: HomeSettings;
    slides: HomeSlide[];
    sections: HomeSection[];
    categories: Array<{ id: number; name: string }>;
}
interface FormData {
    settings: HomeSettings;
    slides: HomeSlide[];
    sections: HomeSection[];
}

const blankSlide = (): HomeSlide => ({
    title: "",
    eyebrow: "",
    description: "",
    desktop_image: "",
    mobile_image: "",
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
    category_id: null,
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
}: Props) {
    const { data, setData, post, processing, errors, recentlySuccessful } =
        useForm<FormData>({ settings, slides, sections });
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
        const next = [...data.slides];
        next[index] = { ...next[index], [key]: value };
        setData("slides", next);
    };
    const moveSlide = (index: number, offset: number) => {
        const target = index + offset;
        if (target < 0 || target >= data.slides.length) return;
        const next = [...data.slides];
        [next[index], next[target]] = [next[target], next[index]];
        setData("slides", next);
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
        post("/admin/home", { forceFormData: true, preserveScroll: true });
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
                isDisabled={processing}
                onPress={() =>
                    document
                        .querySelector<HTMLFormElement>("#home-settings-form")
                        ?.requestSubmit()
                }
                variant="primary"
            >
                <Save size={17} />
                {processing ? "در حال ذخیره…" : "ذخیره تغییرات"}
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
                {recentlySuccessful && (
                    <Alert color="success">تغییرات صفحه اصلی ذخیره شد.</Alert>
                )}
                {Object.keys(errors).length > 0 && (
                    <Alert color="danger">
                        بعضی اطلاعات معتبر نیستند؛ فیلدهای مشخص‌شده را بررسی
                        کنید.
                    </Alert>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        ["اسلایدها", data.slides.length],
                        ["اسلاید فعال", activeSlides],
                        ["محصول هر سکشن", data.settings.products_limit],
                    ].map(([label, value]) => (
                        <Card
                            className="border border-slate-800 bg-slate-900/60"
                            key={String(label)}
                            variant="secondary"
                        >
                            <Card.Content className="p-5">
                                <p className="text-sm text-slate-400">
                                    {label}
                                </p>
                                <strong className="mt-2 block text-3xl text-white">
                                    {Number(value).toLocaleString("fa-IR")}
                                </strong>
                            </Card.Content>
                        </Card>
                    ))}
                </div>

                <Card
                    className="border border-slate-800 bg-slate-900/60"
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
                                    <div className="absolute inset-0 bg-gradient-to-l from-black/80 to-transparent" />
                                    <div className="absolute inset-y-0 right-0 flex max-w-lg flex-col justify-center p-6">
                                        <Chip
                                            className="mb-2 w-fit"
                                            variant="soft"
                                        >
                                            اسلاید {index + 1}
                                        </Chip>
                                        <strong className="text-xl text-white">
                                            {slide.title || "عنوان بنر"}
                                        </strong>
                                        <p className="mt-2 line-clamp-2 text-sm text-slate-300">
                                            {slide.description ||
                                                "توضیح کوتاه کمپین اینجا نمایش داده می‌شود."}
                                        </p>
                                    </div>
                                </div>
                                <Card.Content className="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                                    <FormField label="عنوان بنر" required>
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "title",
                                                    e.target.value,
                                                )
                                            }
                                            value={slide.title}
                                        />
                                    </FormField>
                                    <FormField label="برچسب بالای عنوان">
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "eyebrow",
                                                    e.target.value,
                                                )
                                            }
                                            placeholder="فروش ویژه آخر هفته"
                                            value={slide.eyebrow}
                                        />
                                    </FormField>
                                    <div className="md:col-span-2 xl:col-span-1">
                                        <FormField label="توضیح">
                                            <TextArea
                                                fullWidth
                                                onChange={(e) =>
                                                    updateSlide(
                                                        index,
                                                        "description",
                                                        e.target.value,
                                                    )
                                                }
                                                value={slide.description}
                                            />
                                        </FormField>
                                    </div>
                                    <FormField
                                        description="حداکثر ۵ مگابایت"
                                        label="تصویر دسکتاپ"
                                        required={!slide.desktop_image}
                                    >
                                        <Input
                                            accept="image/*"
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "desktop_image_file",
                                                    e.target.files?.[0],
                                                )
                                            }
                                            type="file"
                                        />
                                    </FormField>
                                    <FormField
                                        description="اختیاری؛ در نبود آن تصویر دسکتاپ استفاده می‌شود."
                                        label="تصویر موبایل"
                                    >
                                        <Input
                                            accept="image/*"
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "mobile_image_file",
                                                    e.target.files?.[0],
                                                )
                                            }
                                            type="file"
                                        />
                                    </FormField>
                                    <HeroSelect
                                        label="جایگاه متن"
                                        onChange={(value) =>
                                            updateSlide(
                                                index,
                                                "text_position",
                                                value,
                                            )
                                        }
                                        options={[
                                            { id: "right", label: "راست" },
                                            { id: "center", label: "وسط" },
                                            { id: "left", label: "چپ" },
                                        ]}
                                        value={slide.text_position}
                                    />
                                    <FormField label="متن دکمه اصلی">
                                        <Input
                                            fullWidth
                                            onChange={(e) =>
                                                updateSlide(
                                                    index,
                                                    "button_label",
                                                    e.target.value,
                                                )
                                            }
                                            value={slide.button_label}
                                        />
                                    </FormField>
                                    <FormField label="لینک دکمه اصلی">
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
                                            value={slide.button_url}
                                        />
                                    </FormField>
                                    <HeroSelect
                                        label="شدت پوشش تصویر"
                                        onChange={(value) =>
                                            updateSlide(index, "overlay", value)
                                        }
                                        options={[
                                            { id: "dark", label: "تیره" },
                                            { id: "medium", label: "متوسط" },
                                            { id: "light", label: "روشن" },
                                        ]}
                                        value={slide.overlay}
                                    />
                                    <PersianDatePicker
                                        label="شروع نمایش"
                                        onChange={(value) =>
                                            updateSlide(
                                                index,
                                                "starts_at",
                                                value,
                                            )
                                        }
                                        value={slide.starts_at}
                                    />
                                    <PersianDatePicker
                                        label="پایان نمایش"
                                        onChange={(value) =>
                                            updateSlide(index, "ends_at", value)
                                        }
                                        value={slide.ends_at}
                                    />
                                    <div className="flex items-end">
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
                    className="border border-slate-800 bg-slate-900/60"
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

                <div className="grid gap-6 xl:grid-cols-2">
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
