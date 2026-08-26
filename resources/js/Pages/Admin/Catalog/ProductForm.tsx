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
    Archive,
    BadgeDollarSign,
    Boxes,
    Check,
    ChevronLeft,
    ChevronRight,
    CircleHelp,
    FileSearch,
    Gamepad2,
    ImagePlus,
    Info,
    PackageCheck,
    Save,
    SearchCheck,
    Settings2,
    ShoppingBag,
    Sparkles,
    Truck,
} from "lucide-react";
import { type FormEvent, useMemo, useState } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import PersianDatePicker from "../../../Components/Admin/Form/PersianDatePicker";
import PriceInput from "../../../Components/Admin/Form/PriceInput";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";

interface Option {
    id: number;
    name: string;
}
interface AttributeOption {
    id: number;
    category_id: number;
    name: string;
    type: string;
    options: string[] | null;
    is_required: boolean;
}
interface FormOptions {
    categories: Option[];
    brands: Option[];
    games: Option[];
    platforms: Option[];
    attributes: AttributeOption[];
}
interface ProductItem extends Partial<ProductFormData> {
    id: number;
    tags?: string[];
}
interface ProductFormProps {
    title: string;
    item: ProductItem | null;
    options: FormOptions;
}

interface ProductFormData {
    title: string;
    slug: string;
    sku: string;
    internal_code: string;
    product_type: string;
    category_id: string;
    brand_id: string;
    game_id: string;
    platform_ids: number[];
    short_description: string;
    description: string;
    purchase_notes: string;
    delivery_notes: string;
    return_policy: string;
    warranty: string;
    tags: string[];
    price: number;
    discount_price: number | "";
    compare_price: number | "";
    partner_price: number | "";
    cost_price: number | "";
    stock: number;
    low_stock_threshold: number;
    availability: string;
    weight: number | "";
    length: number | "";
    width: number | "";
    height: number | "";
    barcode: string;
    condition: string;
    requires_shipping: boolean;
    shipping_class: string;
    delivery_method: string;
    minimum_quantity: number;
    maximum_quantity: number | "";
    badge: string;
    status: string;
    visibility: string;
    featured: boolean;
    trade_enabled: boolean;
    allow_reviews: boolean;
    allow_comments: boolean;
    allow_questions: boolean;
    show_stock: boolean;
    seo_title: string;
    seo_description: string;
    seo_keywords: string;
    canonical: string;
    release_date: string;
    attribute_values: Record<string, string>;
}

const productTypes = [
    ["physical_game", "دیسک بازی", "بازی فیزیکی با ارسال پستی"],
    ["digital_game", "بازی دیجیتال", "لایسنس یا کد فعال‌سازی"],
    ["game_account", "اکانت بازی", "اکانت اختصاصی با تحویل امن"],
    ["capacity_account", "اکانت ظرفیتی", "ظرفیت قابل تعریف برای بازی"],
    ["full_capacity", "فول ظرفیت", "دسترسی کامل اکانت"],
    ["gift_card", "گیفت کارت", "کد دیجیتال با Region"],
    ["console", "کنسول", "کنسول و سخت‌افزار بازی"],
    ["controller", "کنترلر", "دسته و کنترلر گیمینگ"],
    ["headset", "هدست", "هدست و تجهیزات صوتی"],
    ["keyboard", "کیبورد", "کیبورد گیمینگ"],
    ["mouse", "ماوس", "ماوس گیمینگ"],
    ["monitor", "مانیتور", "نمایشگر گیمینگ"],
    ["dlc", "DLC", "محتوای افزودنی بازی"],
    ["subscription", "اشتراک", "اشتراک زمان‌دار سرویس"],
    ["gaming_accessory", "لوازم جانبی", "سایر تجهیزات گیمینگ"],
    ["merchandise", "کالای هواداری", "Merchandise و کلکسیونی"],
    ["other", "سایر", "نوع محصول سفارشی"],
] as const;

const steps = [
    { id: "identity", label: "هویت محصول", icon: ShoppingBag },
    { id: "classification", label: "بازی و دسته‌بندی", icon: Gamepad2 },
    { id: "pricing", label: "قیمت‌گذاری", icon: BadgeDollarSign },
    { id: "inventory", label: "موجودی و ارسال", icon: Boxes },
    { id: "content", label: "محتوا و تحویل", icon: PackageCheck },
    { id: "seo", label: "سئو", icon: SearchCheck },
    { id: "publish", label: "انتشار و تنظیمات", icon: Settings2 },
] as const;

type StepId = (typeof steps)[number]["id"];
const optionize = (items: Option[]) =>
    items.map((item) => ({ id: item.id.toString(), label: item.name }));
const numericValue = (value: string) => (value === "" ? "" : Number(value));

export default function ProductForm({
    title,
    item,
    options,
}: ProductFormProps) {
    const [activeStep, setActiveStep] = useState<StepId>("identity");
    const [tagsText, setTagsText] = useState(item?.tags?.join("، ") ?? "");
    const { data, setData, post, put, processing, errors, transform } =
        useForm<ProductFormData>({
            title: item?.title ?? "",
            slug: item?.slug ?? "",
            sku: item?.sku ?? "",
            internal_code: item?.internal_code ?? "",
            product_type: item?.product_type ?? "",
            category_id: item?.category_id?.toString() ?? "",
            brand_id: item?.brand_id?.toString() ?? "",
            game_id: item?.game_id?.toString() ?? "",
            platform_ids: item?.platform_ids ?? [],
            short_description: item?.short_description ?? "",
            description: item?.description ?? "",
            purchase_notes: item?.purchase_notes ?? "",
            delivery_notes: item?.delivery_notes ?? "",
            return_policy: item?.return_policy ?? "",
            warranty: item?.warranty ?? "",
            tags: item?.tags ?? [],
            price: item?.price ?? 0,
            discount_price: item?.discount_price ?? "",
            compare_price: item?.compare_price ?? "",
            partner_price: item?.partner_price ?? "",
            cost_price: item?.cost_price ?? "",
            stock: item?.stock ?? 0,
            low_stock_threshold: item?.low_stock_threshold ?? 5,
            availability: item?.availability ?? "in_stock",
            weight: item?.weight ?? "",
            length: item?.length ?? "",
            width: item?.width ?? "",
            height: item?.height ?? "",
            barcode: item?.barcode ?? "",
            condition: item?.condition ?? "",
            requires_shipping: item?.requires_shipping ?? true,
            shipping_class: item?.shipping_class ?? "",
            delivery_method: item?.delivery_method ?? "",
            minimum_quantity: item?.minimum_quantity ?? 1,
            maximum_quantity: item?.maximum_quantity ?? "",
            badge: item?.badge ?? "",
            status: item?.status ?? "draft",
            visibility: item?.visibility ?? "public",
            featured: item?.featured ?? false,
            trade_enabled: item?.trade_enabled ?? false,
            allow_reviews: item?.allow_reviews ?? true,
            allow_comments: item?.allow_comments ?? true,
            allow_questions: item?.allow_questions ?? true,
            show_stock: item?.show_stock ?? true,
            seo_title: item?.seo_title ?? "",
            seo_description: item?.seo_description ?? "",
            seo_keywords: item?.seo_keywords ?? "",
            canonical: item?.canonical ?? "",
            release_date: item?.release_date?.slice(0, 10) ?? "",
            attribute_values: item?.attribute_values ?? {},
        });

    const isDigital = [
        "digital_game",
        "game_account",
        "capacity_account",
        "full_capacity",
        "gift_card",
        "dlc",
        "subscription",
    ].includes(data.product_type);
    const categoryAttributes = options.attributes.filter(
        (attribute) => attribute.category_id.toString() === data.category_id,
    );
    const completion = useMemo(() => {
        const required = [
            data.product_type,
            data.title,
            data.slug,
            data.sku,
            data.category_id,
            data.price > 0 ? "price" : "",
            data.status,
        ];
        return Math.round(
            (required.filter(Boolean).length / required.length) * 100,
        );
    }, [data]);
    const currentStep = steps.findIndex((step) => step.id === activeStep);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        transform((values) => ({
            ...values,
            tags: tagsText
                .split(/[،,]/)
                .map((tag) => tag.trim())
                .filter(Boolean),
        }));
        item ? put(`/admin/products/${item.id}`) : post("/admin/products");
    };

    const go = (offset: number) =>
        setActiveStep(
            steps[Math.min(Math.max(currentStep + offset, 0), steps.length - 1)]
                .id,
        );
    const heroInput = (
        key: keyof ProductFormData,
        label: string,
        options?: {
            required?: boolean;
            placeholder?: string;
            dir?: "ltr" | "rtl";
            type?: string;
            description?: string;
        },
    ) => (
        <FormField
            description={options?.description}
            error={errors[key]}
            label={label}
            required={options?.required}
        >
            <Input
                dir={options?.dir}
                fullWidth
                onChange={(event) => setData(key, event.target.value as never)}
                placeholder={options?.placeholder}
                type={options?.type}
                value={String(data[key] ?? "")}
            />
        </FormField>
    );
    const moneyInput = (
        key:
            | "price"
            | "discount_price"
            | "compare_price"
            | "partner_price"
            | "cost_price",
        label: string,
        description: string,
    ) => (
        <PriceInput
            description={description}
            error={errors[key]}
            label={label}
            onChange={(value) => setData(key, value)}
            required={key === "price"}
            value={data[key]}
        />
    );

    return (
        <AdminLayout
            actions={
                <>
                    <Link href="/admin/products">
                        <Button variant="secondary">بازگشت</Button>
                    </Link>
                    <Button
                        isDisabled={processing}
                        onPress={() =>
                            document
                                .querySelector<HTMLFormElement>("#product-form")
                                ?.requestSubmit()
                        }
                        variant="primary"
                    >
                        <Save size={17} />
                        {processing
                            ? "در حال ذخیره..."
                            : item
                              ? "ذخیره تغییرات"
                              : "ثبت محصول"}
                    </Button>
                </>
            }
            description="اطلاعات محصول را مرحله‌به‌مرحله تکمیل کنید؛ هر بخش فقط موارد مرتبط را نمایش می‌دهد."
            title={title}
        >
            <Head title={title} />
            <form id="product-form" onSubmit={submit}>
                <div className="grid gap-6 xl:grid-cols-[260px_minmax(0,1fr)_300px]">
                    <Card
                        className="h-fit border border-slate-800/80 bg-slate-900/55 xl:sticky xl:top-28"
                        variant="secondary"
                    >
                        <Card.Content className="p-4">
                            <div className="mb-5 flex items-center justify-between">
                                <span className="text-xs font-bold text-slate-400">
                                    میزان تکمیل
                                </span>
                                <Chip
                                    color={
                                        completion === 100
                                            ? "success"
                                            : "accent"
                                    }
                                    size="sm"
                                    variant="soft"
                                >
                                    {completion.toLocaleString("fa-IR")}٪
                                </Chip>
                            </div>
                            <ProgressBar
                                aria-label="میزان تکمیل فرم"
                                value={completion}
                            >
                                <ProgressBar.Track>
                                    <ProgressBar.Fill />
                                </ProgressBar.Track>
                            </ProgressBar>
                            <div className="mt-5 space-y-1">
                                {steps.map((step, index) => {
                                    const Icon = step.icon;
                                    const active = activeStep === step.id;
                                    return (
                                        <Button
                                            className="w-full justify-start"
                                            key={step.id}
                                            onPress={() =>
                                                setActiveStep(step.id)
                                            }
                                            variant={
                                                active ? "primary" : "ghost"
                                            }
                                        >
                                            <span className="grid size-6 place-items-center rounded-full bg-black/10 text-xs">
                                                {(index + 1).toLocaleString(
                                                    "fa-IR",
                                                )}
                                            </span>
                                            <Icon size={16} />
                                            {step.label}
                                        </Button>
                                    );
                                })}
                            </div>
                        </Card.Content>
                    </Card>

                    <div className="min-w-0">
                        {activeStep === "identity" && (
                            <section className="space-y-6">
                                <Alert color="accent">
                                    <Info size={18} />
                                    <Alert.Content>
                                        <Alert.Title>
                                            اول نوع محصول را انتخاب کنید
                                        </Alert.Title>
                                        <Alert.Description>
                                            فیلدهای مراحل بعد بر اساس نوع
                                            انتخابی ساده‌تر و مرتبط‌تر می‌شوند.
                                        </Alert.Description>
                                    </Alert.Content>
                                </Alert>
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>نوع محصول</Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                                        {productTypes.map(
                                            ([id, label, description]) => (
                                                <Button
                                                    className={`h-auto min-h-20 justify-start p-4 text-right ${data.product_type === id ? "ring-2 ring-indigo-500" : ""}`}
                                                    key={id}
                                                    onPress={() => {
                                                        setData(
                                                            "product_type",
                                                            id,
                                                        );
                                                        setData(
                                                            "requires_shipping",
                                                            ![
                                                                "digital_game",
                                                                "game_account",
                                                                "capacity_account",
                                                                "full_capacity",
                                                                "gift_card",
                                                                "dlc",
                                                                "subscription",
                                                            ].includes(id),
                                                        );
                                                    }}
                                                    variant={
                                                        data.product_type === id
                                                            ? "primary"
                                                            : "secondary"
                                                    }
                                                >
                                                    <div>
                                                        <p className="font-bold">
                                                            {label}
                                                        </p>
                                                        <p className="mt-1 text-xs opacity-70">
                                                            {description}
                                                        </p>
                                                    </div>
                                                </Button>
                                            ),
                                        )}
                                    </Card.Content>
                                </Card>
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>مشخصات شناسایی</Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                                        {heroInput("title", "عنوان محصول", {
                                            required: true,
                                            placeholder:
                                                "مثلاً دیسک بازی GTA VI برای PS5",
                                            description:
                                                "عنوانی بنویسید که نوع، بازی و پلتفرم را شفاف کند.",
                                        })}
                                        {heroInput("slug", "آدرس محصول", {
                                            required: true,
                                            dir: "ltr",
                                            placeholder: "gta-vi-ps5-disc",
                                        })}
                                        {heroInput("sku", "SKU", {
                                            required: true,
                                            dir: "ltr",
                                            placeholder: "GTA6-PS5-DISC",
                                            description:
                                                "شناسه یکتا و قابل جستجوی انبار.",
                                        })}
                                        {heroInput(
                                            "internal_code",
                                            "کد داخلی",
                                            {
                                                dir: "ltr",
                                                placeholder: "PRD-10024",
                                            },
                                        )}
                                        <PersianDatePicker
                                            description="تاریخ را مستقیماً از تقویم شمسی انتخاب کنید."
                                            error={errors.release_date}
                                            label="تاریخ انتشار"
                                            onChange={(value) =>
                                                setData("release_date", value)
                                            }
                                            value={data.release_date}
                                        />
                                        <div className="sm:col-span-2">
                                            <FormField
                                                error={errors.short_description}
                                                label="توضیح کوتاه"
                                            >
                                                <TextArea
                                                    fullWidth
                                                    maxLength={500}
                                                    onChange={(event) =>
                                                        setData(
                                                            "short_description",
                                                            event.target.value,
                                                        )
                                                    }
                                                    placeholder="در یک یا دو جمله دقیقاً بگویید مشتری چه چیزی دریافت می‌کند."
                                                    value={
                                                        data.short_description
                                                    }
                                                />
                                            </FormField>
                                        </div>
                                    </Card.Content>
                                </Card>
                            </section>
                        )}

                        {activeStep === "classification" && (
                            <Card
                                className="border border-slate-800/80 bg-slate-900/55"
                                variant="secondary"
                            >
                                <Card.Header className="border-b border-slate-800/80 p-5">
                                    <Card.Title>
                                        جایگاه محصول در فروشگاه
                                    </Card.Title>
                                    <Card.Description>
                                        ارتباط درست، جستجو و پیشنهاد محصول را
                                        دقیق‌تر می‌کند.
                                    </Card.Description>
                                </Card.Header>
                                <Card.Content className="space-y-6 p-5">
                                    <div className="grid gap-5 sm:grid-cols-2">
                                        <HeroSelect
                                            error={errors.category_id}
                                            label="دسته‌بندی اصلی"
                                            onChange={(value) =>
                                                setData("category_id", value)
                                            }
                                            options={optionize(
                                                options.categories,
                                            )}
                                            placeholder="یک دسته انتخاب کنید"
                                            required
                                            value={data.category_id}
                                        />
                                        <HeroSelect
                                            error={errors.brand_id}
                                            label="برند"
                                            onChange={(value) =>
                                                setData("brand_id", value)
                                            }
                                            options={optionize(options.brands)}
                                            placeholder="برند را انتخاب کنید"
                                            value={data.brand_id}
                                        />
                                        <HeroSelect
                                            error={errors.game_id}
                                            label="بازی مرتبط"
                                            onChange={(value) =>
                                                setData("game_id", value)
                                            }
                                            options={optionize(options.games)}
                                            placeholder="در صورت ارتباط انتخاب کنید"
                                            value={data.game_id}
                                        />
                                        <FormField
                                            description="با ویرگول فارسی یا انگلیسی جدا کنید."
                                            error={errors.tags}
                                            label="برچسب‌ها"
                                        >
                                            <Input
                                                fullWidth
                                                onChange={(event) =>
                                                    setTagsText(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="PS5، اکشن، Rockstar"
                                                value={tagsText}
                                            />
                                        </FormField>
                                    </div>
                                    <div>
                                        <p className="mb-3 text-sm font-bold text-slate-300">
                                            پلتفرم‌های سازگار
                                        </p>
                                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                            {options.platforms.map(
                                                (platform) => (
                                                    <Checkbox
                                                        isSelected={data.platform_ids.includes(
                                                            platform.id,
                                                        )}
                                                        key={platform.id}
                                                        onChange={(selected) =>
                                                            setData(
                                                                "platform_ids",
                                                                selected
                                                                    ? [
                                                                          ...data.platform_ids,
                                                                          platform.id,
                                                                      ]
                                                                    : data.platform_ids.filter(
                                                                          (
                                                                              id,
                                                                          ) =>
                                                                              id !==
                                                                              platform.id,
                                                                      ),
                                                            )
                                                        }
                                                        variant="secondary"
                                                    >
                                                        <Checkbox.Control>
                                                            <Checkbox.Indicator />
                                                        </Checkbox.Control>
                                                        <Checkbox.Content>
                                                            {platform.name}
                                                        </Checkbox.Content>
                                                    </Checkbox>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                    {categoryAttributes.length > 0 && (
                                        <div className="border-t border-slate-800 pt-6">
                                            <div className="mb-4">
                                                <p className="font-bold text-slate-100">
                                                    ویژگی‌های مخصوص این دسته
                                                </p>
                                                <p className="mt-1 text-xs text-slate-400">
                                                    این مقادیر در فیلتر فروشگاه
                                                    استفاده می‌شوند.
                                                </p>
                                            </div>
                                            <div className="grid gap-5 sm:grid-cols-2">
                                                {categoryAttributes.map(
                                                    (attribute) =>
                                                        attribute.type ===
                                                            "select" &&
                                                        attribute.options ? (
                                                            <HeroSelect
                                                                key={
                                                                    attribute.id
                                                                }
                                                                label={
                                                                    attribute.name
                                                                }
                                                                onChange={(
                                                                    value,
                                                                ) =>
                                                                    setData(
                                                                        "attribute_values",
                                                                        {
                                                                            ...data.attribute_values,
                                                                            [attribute.id]:
                                                                                value,
                                                                        },
                                                                    )
                                                                }
                                                                options={attribute.options.map(
                                                                    (
                                                                        option,
                                                                    ) => ({
                                                                        id: option,
                                                                        label: option,
                                                                    }),
                                                                )}
                                                                required={
                                                                    attribute.is_required
                                                                }
                                                                value={
                                                                    data
                                                                        .attribute_values[
                                                                        attribute
                                                                            .id
                                                                    ] ?? ""
                                                                }
                                                            />
                                                        ) : (
                                                            <FormField
                                                                key={
                                                                    attribute.id
                                                                }
                                                                label={
                                                                    attribute.name
                                                                }
                                                                required={
                                                                    attribute.is_required
                                                                }
                                                            >
                                                                <Input
                                                                    fullWidth
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        setData(
                                                                            "attribute_values",
                                                                            {
                                                                                ...data.attribute_values,
                                                                                [attribute.id]:
                                                                                    event
                                                                                        .target
                                                                                        .value,
                                                                            },
                                                                        )
                                                                    }
                                                                    value={
                                                                        data
                                                                            .attribute_values[
                                                                            attribute
                                                                                .id
                                                                        ] ?? ""
                                                                    }
                                                                />
                                                            </FormField>
                                                        ),
                                                )}
                                            </div>
                                        </div>
                                    )}
                                </Card.Content>
                            </Card>
                        )}

                        {activeStep === "pricing" && (
                            <section className="space-y-6">
                                <Alert color="warning">
                                    <BadgeDollarSign size={18} />
                                    <Alert.Content>
                                        <Alert.Title>
                                            قیمت‌ها فقط در Backend اعمال می‌شوند
                                        </Alert.Title>
                                        <Alert.Description>
                                            قیمت همکار و قیمت تمام‌شده هرگز به
                                            کاربر عادی ارسال نخواهد شد.
                                        </Alert.Description>
                                    </Alert.Content>
                                </Alert>
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>
                                            ساختار قیمت‌گذاری
                                        </Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                                        {moneyInput(
                                            "price",
                                            "قیمت عادی",
                                            "قیمت پایه و مرجع محصول.",
                                        )}
                                        {moneyInput(
                                            "discount_price",
                                            "قیمت فروش",
                                            "قیمت فعلی مشتری؛ باید کمتر از قیمت عادی باشد.",
                                        )}
                                        {moneyInput(
                                            "compare_price",
                                            "قیمت مقایسه‌ای",
                                            "قیمت قبلی برای نمایش خط‌خورده.",
                                        )}
                                        {moneyInput(
                                            "partner_price",
                                            "قیمت همکار",
                                            "فقط برای کاربران Partner و محاسبات Backend.",
                                        )}
                                        {moneyInput(
                                            "cost_price",
                                            "قیمت تمام‌شده",
                                            "محرمانه؛ فقط مدیر مالی مشاهده می‌کند.",
                                        )}
                                    </Card.Content>
                                </Card>
                            </section>
                        )}

                        {activeStep === "inventory" && (
                            <section className="space-y-6">
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>
                                            موجودی و امکان خرید
                                        </Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                                        {heroInput("stock", "موجودی اولیه", {
                                            required: true,
                                            type: "number",
                                        })}
                                        {heroInput(
                                            "low_stock_threshold",
                                            "هشدار موجودی کم",
                                            { type: "number" },
                                        )}
                                        <HeroSelect
                                            label="وضعیت دسترسی"
                                            onChange={(value) =>
                                                setData("availability", value)
                                            }
                                            options={[
                                                ["in_stock", "موجود"],
                                                ["low_stock", "موجودی کم"],
                                                ["out_of_stock", "ناموجود"],
                                                ["preorder", "پیش‌فروش"],
                                                ["coming_soon", "به‌زودی"],
                                                ["discontinued", "توقف تولید"],
                                            ].map(([id, label]) => ({
                                                id,
                                                label,
                                            }))}
                                            value={data.availability}
                                        />
                                        {heroInput("barcode", "بارکد", {
                                            dir: "ltr",
                                        })}
                                        {heroInput(
                                            "minimum_quantity",
                                            "حداقل خرید",
                                            { type: "number" },
                                        )}
                                        {heroInput(
                                            "maximum_quantity",
                                            "حداکثر خرید",
                                            { type: "number" },
                                        )}
                                    </Card.Content>
                                </Card>
                                {!isDigital && (
                                    <Card
                                        className="border border-slate-800/80 bg-slate-900/55"
                                        variant="secondary"
                                    >
                                        <Card.Header className="border-b border-slate-800/80 p-5">
                                            <Card.Title>
                                                ارسال فیزیکی
                                            </Card.Title>
                                        </Card.Header>
                                        <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                                            {heroInput("weight", "وزن (گرم)", {
                                                type: "number",
                                            })}
                                            {heroInput(
                                                "shipping_class",
                                                "کلاس ارسال",
                                            )}
                                            {heroInput(
                                                "length",
                                                "طول (میلی‌متر)",
                                                { type: "number" },
                                            )}
                                            {heroInput(
                                                "width",
                                                "عرض (میلی‌متر)",
                                                { type: "number" },
                                            )}
                                            {heroInput(
                                                "height",
                                                "ارتفاع (میلی‌متر)",
                                                { type: "number" },
                                            )}
                                            <HeroSelect
                                                label="وضعیت کالا"
                                                onChange={(value) =>
                                                    setData("condition", value)
                                                }
                                                options={[
                                                    { id: "new", label: "نو" },
                                                    {
                                                        id: "used",
                                                        label: "کارکرده",
                                                    },
                                                    {
                                                        id: "refurbished",
                                                        label: "بازسازی‌شده",
                                                    },
                                                ]}
                                                value={data.condition}
                                            />
                                        </Card.Content>
                                    </Card>
                                )}
                            </section>
                        )}

                        {activeStep === "content" && (
                            <section className="space-y-6">
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>
                                            معرفی و راهنمای مشتری
                                        </Card.Title>
                                    </Card.Header>
                                    <Card.Content className="space-y-5 p-5">
                                        <FormField
                                            error={errors.description}
                                            label="توضیحات کامل"
                                        >
                                            <RichTextEditor
                                                onChange={(value) =>
                                                    setData(
                                                        "description",
                                                        value,
                                                    )
                                                }
                                                placeholder="ویژگی‌ها، محتویات و شرایط محصول را شفاف توضیح دهید."
                                                value={data.description}
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.purchase_notes}
                                            label="نکات مهم قبل از خرید"
                                        >
                                            <TextArea
                                                fullWidth
                                                onChange={(event) =>
                                                    setData(
                                                        "purchase_notes",
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="هشدارها و پیش‌نیازهایی که مشتری باید قبل از خرید بداند."
                                                value={data.purchase_notes}
                                            />
                                        </FormField>
                                        <FormField
                                            error={errors.delivery_notes}
                                            label="راهنمای تحویل"
                                        >
                                            <TextArea
                                                fullWidth
                                                onChange={(event) =>
                                                    setData(
                                                        "delivery_notes",
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="زمان، روش و مراحل دریافت محصول."
                                                value={data.delivery_notes}
                                            />
                                        </FormField>
                                        <div className="grid gap-5 sm:grid-cols-2">
                                            {heroInput("warranty", "گارانتی", {
                                                placeholder:
                                                    "مثلاً ۱۸ ماه گارانتی رسمی",
                                            })}
                                            <HeroSelect
                                                label="روش تحویل دیجیتال"
                                                onChange={(value) =>
                                                    setData(
                                                        "delivery_method",
                                                        value,
                                                    )
                                                }
                                                options={[
                                                    {
                                                        id: "instant",
                                                        label: "آنی و خودکار",
                                                    },
                                                    {
                                                        id: "manual",
                                                        label: "دستی توسط پشتیبانی",
                                                    },
                                                    {
                                                        id: "scheduled",
                                                        label: "زمان‌بندی‌شده",
                                                    },
                                                ]}
                                                value={data.delivery_method}
                                            />
                                        </div>
                                    </Card.Content>
                                </Card>
                                <Card
                                    className="border border-dashed border-slate-700 bg-slate-900/30"
                                    variant="secondary"
                                >
                                    <Card.Content className="flex flex-col items-center p-8 text-center">
                                        <ImagePlus
                                            className="text-indigo-400"
                                            size={30}
                                        />
                                        <h3 className="mt-3 font-bold">
                                            رسانه و گالری پس از ذخیره اولیه
                                        </h3>
                                        <p className="mt-2 text-sm text-slate-500">
                                            ابتدا محصول را ذخیره کنید تا آپلود
                                            امن، مرتب‌سازی و انتخاب تصویر کاور
                                            فعال شود.
                                        </p>
                                    </Card.Content>
                                </Card>
                            </section>
                        )}

                        {activeStep === "seo" && (
                            <Card
                                className="border border-slate-800/80 bg-slate-900/55"
                                variant="secondary"
                            >
                                <Card.Header className="border-b border-slate-800/80 p-5">
                                    <Card.Title>
                                        نمایش در موتورهای جستجو
                                    </Card.Title>
                                </Card.Header>
                                <Card.Content className="space-y-5 p-5">
                                    {heroInput("seo_title", "عنوان SEO", {
                                        description: `${data.seo_title.length.toLocaleString("fa-IR")} از ۶۰ کاراکتر`,
                                    })}
                                    <FormField
                                        error={errors.seo_description}
                                        label="توضیحات متا"
                                    >
                                        <TextArea
                                            fullWidth
                                            maxLength={500}
                                            onChange={(event) =>
                                                setData(
                                                    "seo_description",
                                                    event.target.value,
                                                )
                                            }
                                            value={data.seo_description}
                                        />
                                    </FormField>
                                    {heroInput("seo_keywords", "کلمات کلیدی", {
                                        placeholder: "GTA VI، PS5، دیسک بازی",
                                    })}
                                    {heroInput("canonical", "Canonical URL", {
                                        dir: "ltr",
                                        placeholder:
                                            "https://example.com/product/...",
                                    })}
                                    <Card
                                        className="border border-slate-700/70 bg-slate-950/40"
                                        variant="secondary"
                                    >
                                        <Card.Content className="p-4">
                                            <p className="text-xs text-emerald-400">
                                                پیش‌نمایش نتیجه جستجو
                                            </p>
                                            <p className="mt-2 text-base font-bold text-sky-300">
                                                {data.seo_title ||
                                                    data.title ||
                                                    "عنوان محصول"}
                                            </p>
                                            <p className="mt-1 text-xs text-emerald-600">
                                                /product/
                                                {data.slug || "product-slug"}
                                            </p>
                                            <p className="mt-2 text-sm text-slate-400">
                                                {data.seo_description ||
                                                    data.short_description ||
                                                    "توضیحات محصول در نتایج جستجو اینجا دیده می‌شود."}
                                            </p>
                                        </Card.Content>
                                    </Card>
                                </Card.Content>
                            </Card>
                        )}

                        {activeStep === "publish" && (
                            <section className="space-y-6">
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>انتشار</Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                                        <HeroSelect
                                            error={errors.status}
                                            label="وضعیت محصول"
                                            onChange={(value) =>
                                                setData("status", value)
                                            }
                                            options={[
                                                {
                                                    id: "draft",
                                                    label: "پیش‌نویس",
                                                },
                                                {
                                                    id: "pending_review",
                                                    label: "در انتظار بررسی",
                                                },
                                                {
                                                    id: "published",
                                                    label: "منتشرشده",
                                                },
                                                { id: "hidden", label: "مخفی" },
                                                {
                                                    id: "archived",
                                                    label: "آرشیو",
                                                },
                                                {
                                                    id: "out_of_stock",
                                                    label: "ناموجود",
                                                },
                                                {
                                                    id: "disabled",
                                                    label: "غیرفعال",
                                                },
                                            ]}
                                            required
                                            value={data.status}
                                        />
                                        <HeroSelect
                                            label="سطح نمایش"
                                            onChange={(value) =>
                                                setData("visibility", value)
                                            }
                                            options={[
                                                {
                                                    id: "public",
                                                    label: "عمومی",
                                                },
                                                {
                                                    id: "private",
                                                    label: "خصوصی",
                                                },
                                                {
                                                    id: "members_only",
                                                    label: "فقط اعضا",
                                                },
                                                {
                                                    id: "hidden",
                                                    label: "پنهان",
                                                },
                                            ]}
                                            value={data.visibility}
                                        />
                                        {heroInput("badge", "نشان محصول", {
                                            placeholder:
                                                "جدید، پرفروش، پیشنهاد ویژه",
                                        })}
                                    </Card.Content>
                                </Card>
                                <Card
                                    className="border border-slate-800/80 bg-slate-900/55"
                                    variant="secondary"
                                >
                                    <Card.Header className="border-b border-slate-800/80 p-5">
                                        <Card.Title>
                                            قابلیت‌های محصول
                                        </Card.Title>
                                    </Card.Header>
                                    <Card.Content className="grid gap-3 p-5 sm:grid-cols-2">
                                        {(
                                            [
                                                ["featured", "محصول ویژه"],
                                                [
                                                    "trade_enabled",
                                                    "امکان معاوضه",
                                                ],
                                                [
                                                    "allow_reviews",
                                                    "فعال‌بودن نقد و امتیاز",
                                                ],
                                                [
                                                    "allow_comments",
                                                    "فعال‌بودن گفتگو",
                                                ],
                                                [
                                                    "allow_questions",
                                                    "فعال‌بودن پرسش و پاسخ",
                                                ],
                                                [
                                                    "show_stock",
                                                    "نمایش موجودی به مشتری",
                                                ],
                                            ] as const
                                        ).map(([key, label]) => (
                                            <Checkbox
                                                isSelected={Boolean(data[key])}
                                                key={key}
                                                onChange={(selected) =>
                                                    setData(key, selected)
                                                }
                                                variant="secondary"
                                            >
                                                <Checkbox.Control>
                                                    <Checkbox.Indicator />
                                                </Checkbox.Control>
                                                <Checkbox.Content>
                                                    {label}
                                                </Checkbox.Content>
                                            </Checkbox>
                                        ))}
                                    </Card.Content>
                                </Card>
                            </section>
                        )}

                        <div className="mt-6 flex items-center justify-between">
                            <Button
                                isDisabled={currentStep === 0}
                                onPress={() => go(-1)}
                                variant="secondary"
                            >
                                <ChevronRight size={17} />
                                مرحله قبل
                            </Button>
                            {currentStep < steps.length - 1 ? (
                                <Button onPress={() => go(1)} variant="primary">
                                    مرحله بعد
                                    <ChevronLeft size={17} />
                                </Button>
                            ) : (
                                <Button
                                    isDisabled={processing}
                                    type="submit"
                                    variant="primary"
                                >
                                    <Save size={17} />
                                    {item ? "ذخیره محصول" : "ثبت محصول"}
                                </Button>
                            )}
                        </div>
                    </div>

                    <aside className="space-y-5 xl:sticky xl:top-28 xl:h-fit">
                        <Card
                            className="border border-indigo-500/20 bg-gradient-to-br from-indigo-500/10 to-slate-900/60"
                            variant="secondary"
                        >
                            <Card.Header className="border-b border-white/5 p-5">
                                <Card.Title className="flex items-center gap-2">
                                    <Sparkles
                                        className="text-indigo-400"
                                        size={18}
                                    />
                                    خلاصه محصول
                                </Card.Title>
                            </Card.Header>
                            <Card.Content className="space-y-4 p-5">
                                <div>
                                    <p className="text-xs text-slate-500">
                                        عنوان
                                    </p>
                                    <p className="mt-1 font-bold text-white">
                                        {data.title || "بدون عنوان"}
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Chip size="sm" variant="soft">
                                        {productTypes.find(
                                            ([id]) => id === data.product_type,
                                        )?.[1] || "نوع انتخاب نشده"}
                                    </Chip>
                                    <Chip size="sm" variant="soft">
                                        {options.categories.find(
                                            (c) =>
                                                c.id.toString() ===
                                                data.category_id,
                                        )?.name || "بدون دسته"}
                                    </Chip>
                                </div>
                                <div className="rounded-xl bg-slate-950/40 p-3">
                                    <p className="text-xs text-slate-500">
                                        قیمت فروش
                                    </p>
                                    <p className="mt-1 text-lg font-black text-emerald-400">
                                        {Number(
                                            data.discount_price || data.price,
                                        ).toLocaleString("fa-IR")}{" "}
                                        تومان
                                    </p>
                                </div>
                            </Card.Content>
                        </Card>
                        <Card
                            className="border border-slate-800/80 bg-slate-900/55"
                            variant="secondary"
                        >
                            <Card.Content className="p-5">
                                <div className="flex items-start gap-3">
                                    <CircleHelp
                                        className="mt-0.5 shrink-0 text-sky-400"
                                        size={19}
                                    />
                                    <div>
                                        <p className="text-sm font-bold text-slate-200">
                                            راهنمای همین مرحله
                                        </p>
                                        <p className="mt-2 text-xs leading-6 text-slate-500">
                                            {activeStep === "identity"
                                                ? "نام محصول باید در نگاه اول نوع، بازی و پلتفرم را مشخص کند."
                                                : activeStep === "pricing"
                                                  ? "قیمت عادی مرجع است؛ قیمت فروش باید کمتر از آن باشد."
                                                  : activeStep === "inventory"
                                                    ? "موجودی واقعی را وارد کنید؛ رزرو سفارش جداگانه مدیریت می‌شود."
                                                    : "فیلدها را به اندازه‌ای تکمیل کنید که مشتری ابهامی درباره خرید نداشته باشد."}
                                        </p>
                                    </div>
                                </div>
                            </Card.Content>
                        </Card>
                        {Object.keys(errors).length > 0 && (
                            <Alert color="danger">
                                <FileSearch size={18} />
                                <Alert.Content>
                                    <Alert.Title>
                                        {Object.keys(
                                            errors,
                                        ).length.toLocaleString("fa-IR")}{" "}
                                        مورد نیاز به اصلاح دارد
                                    </Alert.Title>
                                    <Alert.Description>
                                        خطاها زیر فیلد مربوط نمایش داده شده‌اند.
                                    </Alert.Description>
                                </Alert.Content>
                            </Alert>
                        )}
                    </aside>
                </div>
            </form>
        </AdminLayout>
    );
}
