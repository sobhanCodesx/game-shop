import { Button, Card, Checkbox, Chip, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { Filter, ImageIcon, Plus, Save, Sparkles, Trash2 } from "lucide-react";
import { FormEvent, useState } from "react";

import ProductMediaUploader, {
    type ProductMediaItem,
} from "../../../../Components/Admin/Form/ProductMediaUploader";
import AdminLayout from "../../../../Layouts/AdminLayout";
import { normalizeDigits } from "../../../../utils/persian-number";

type Offer = {
    code: "capacity_1" | "capacity_2" | "capacity_3" | "full";
    label: string;
    price: number;
    stock: number;
    status: "active" | "inactive";
};

type AttributeOption = {
    id?: number;
    title: string;
    value: string;
};

type AttributeDefinition = {
    id: number;
    title: string;
    slug: string;
    input_type: "select" | "multi_select" | "boolean";
    is_required: boolean;
    is_filterable: boolean;
    options: AttributeOption[];
};

type FeatureRow = {
    key: string;
    attribute_id: string;
    value: string;
};

type FormData = {
    game_id: string;
    platform_id: string;
    seller_id: string;
    title: string;
    short_description: string;
    support_days: number;
    status: "draft" | "published" | "hidden";
    featured: boolean;
    offers: Offer[];
    attribute_values: Record<string, string[]>;
    media: ProductMediaItem[];
};

const formatter = new Intl.NumberFormat("fa-IR");
const emptyFeatureRow = (): FeatureRow => ({
    key: `feature-${Date.now()}-${Math.random().toString(36).slice(2)}`,
    attribute_id: "",
    value: "",
});

const defaultOffers: Offer[] = [
    { code: "capacity_1", label: "ظرفیت ۱", price: 0, stock: 0, status: "active" },
    { code: "capacity_2", label: "ظرفیت ۲", price: 0, stock: 0, status: "active" },
    { code: "capacity_3", label: "ظرفیت ۳", price: 0, stock: 0, status: "active" },
    { code: "full", label: "فول ظرفیت", price: 0, stock: 0, status: "active" },
];

export default function Form({
    product,
    games,
    platforms,
    sellers,
    attributes = [],
    currentSellerId,
}: {
    product: any;
    games: any[];
    platforms: any[];
    sellers: any[];
    attributes: AttributeDefinition[];
    currentSellerId: number | null;
}) {
    const form = useForm<FormData>({
        game_id: String(product?.game_id ?? ""),
        platform_id: String(product?.platform_id ?? ""),
        seller_id: String(product?.seller_id ?? currentSellerId ?? ""),
        title: product?.title ?? "",
        short_description: product?.short_description ?? "",
        support_days: Number(product?.support_days ?? 7),
        status: product?.status ?? "published",
        featured: Boolean(product?.featured),
        offers: product?.offers?.length
            ? product.offers.map((offer: any) => ({
                  code: offer.code,
                  label: offer.label,
                  price: Number(offer.price),
                  stock: Number(offer.stock),
                  status: offer.status,
              }))
            : defaultOffers,
        attribute_values: product?.attribute_values ?? {},
        media:
            product?.media?.map((media: any) => ({
                key: `stored-${media.id}`,
                id: media.id,
                type: media.type,
                url: media.url,
                previewUrl: media.url,
                alt: media.alt ?? "",
                is_primary: Boolean(media.is_primary),
            })) ?? [],
    });

    const initialFeatureRows: FeatureRow[] = Object.entries(
        product?.attribute_values ?? {},
    ).flatMap(([attributeId, values]: [string, any]) =>
        (Array.isArray(values) ? values : []).map((value: string, index: number) => ({
            key: `stored-${attributeId}-${index}`,
            attribute_id: attributeId,
            value: String(value),
        })),
    );

    const [featureRows, setFeatureRows] = useState<FeatureRow[]>(
        initialFeatureRows.length ? initialFeatureRows : [emptyFeatureRow()],
    );

    const updateFeatureRow = (
        key: string,
        patch: Partial<FeatureRow>,
    ) => {
        setFeatureRows((rows) =>
            rows.map((row) =>
                row.key === key
                    ? {
                          ...row,
                          ...patch,
                          ...(patch.attribute_id !== undefined
                              ? { value: "" }
                              : {}),
                      }
                    : row,
            ),
        );
    };

    const const updateOffer = <K extends keyof Offer>(
        index: number,
        key: K,
        value: Offer[K],
    ) =>
        form.setData(
            "offers",
            form.data.offers.map((offer, i) =>
                i === index ? { ...offer, [key]: value } : offer,
            ),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const attributeValues = featureRows.reduce<Record<string, string[]>>(
            (result, row) => {
                if (!row.attribute_id || !row.value) return result;
                result[row.attribute_id] = Array.from(
                    new Set([...(result[row.attribute_id] ?? []), row.value]),
                );
                return result;
            },
            {},
        );

        form.transform((values) => ({
            ...values,
            attribute_values: attributeValues,
            media: values.media.map((media) => ({
                id: media.id,
                type: media.type,
                file: media.file,
                alt: media.alt,
                is_primary: media.is_primary,
            })),
            ...(product ? { _method: "put" } : {}),
        }) as any);

        form.post(
            product
                ? `/admin/digital-products/${product.id}`
                : "/admin/digital-products",
            { forceFormData: true, preserveScroll: true },
        );
    };

    return (
        <AdminLayout
            title={product ? "ویرایش بازی دیجیتال" : "بازی دیجیتال جدید"}
            description="بازی، مدیا، ویژگی‌های ازپیش‌تعریف‌شده و قیمت ظرفیت‌ها."
        >
            <Head title={product ? "ویرایش بازی دیجیتال" : "بازی دیجیتال جدید"} />

            <form className="mx-auto max-w-6xl space-y-6" onSubmit={submit}>
                <Card className="overflow-hidden" variant="secondary">
                    <Card.Content className="p-0">
                        <div className="border-b border-indigo-500/20 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.25),transparent_45%)] p-6">
                            <div className="flex items-center gap-3">
                                <span className="grid size-12 place-items-center rounded-2xl bg-indigo-500/15 text-indigo-300">
                                    <Sparkles size={23} />
                                </span>
                                <div>
                                    <h2 className="text-xl font-black text-white">
                                        محصول دیجیتال
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-400">
                                        فقط اطلاعات لازم برای فروش؛ بدون فرم فروشگاه فیزیکی.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div className="grid gap-4 p-5 md:grid-cols-2">
                            <label className="text-sm font-bold text-slate-200">
                                بازی
                                <select
                                    className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3"
                                    value={form.data.game_id}
                                    onChange={(event) =>
                                        form.setData("game_id", event.target.value)
                                    }
                                >
                                    <option value="">انتخاب بازی</option>
                                    {games.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            <label className="text-sm font-bold text-slate-200">
                                پلتفرم
                                <select
                                    className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3"
                                    value={form.data.platform_id}
                                    onChange={(event) =>
                                        form.setData("platform_id", event.target.value)
                                    }
                                >
                                    <option value="">انتخاب پلتفرم</option>
                                    {platforms.map((item) => (
                                        <option key={item.id} value={item.id}>
                                            {item.name}
                                        </option>
                                    ))}
                                </select>
                            </label>

                            {!currentSellerId && (
                                <label className="text-sm font-bold text-slate-200">
                                    فروشنده
                                    <select
                                        className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3"
                                        value={form.data.seller_id}
                                        onChange={(event) =>
                                            form.setData("seller_id", event.target.value)
                                        }
                                    >
                                        <option value="">انتخاب فروشنده</option>
                                        {sellers.map((item) => (
                                            <option key={item.id} value={item.id}>
                                                {item.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            )}

                            <Input
                                label="عنوان نمایش (اختیاری)"
                                placeholder="اگر خالی باشد از نام بازی + پلتفرم ساخته می‌شود"
                                value={form.data.title}
                                onChange={(event) =>
                                    form.setData("title", event.target.value)
                                }
                            />

                            <Input
                                label="روزهای پشتیبانی"
                                type="number"
                                min="0"
                                value={String(form.data.support_days)}
                                onChange={(event) =>
                                    form.setData(
                                        "support_days",
                                        Number(event.target.value),
                                    )
                                }
                            />

                            <label className="text-sm font-bold text-slate-200">
                                وضعیت
                                <select
                                    className="mt-2 h-12 w-full rounded-xl border border-slate-700 bg-slate-950 px-3"
                                    value={form.data.status}
                                    onChange={(event) =>
                                        form.setData(
                                            "status",
                                            event.target.value as FormData["status"],
                                        )
                                    }
                                >
                                    <option value="published">منتشرشده</option>
                                    <option value="draft">پیش‌نویس</option>
                                    <option value="hidden">مخفی</option>
                                </select>
                            </label>

                            <div className="md:col-span-2">
                                <label className="text-sm font-bold text-slate-200">
                                    توضیح کوتاه
                                </label>
                                <textarea
                                    className="mt-2 min-h-24 w-full rounded-2xl border border-slate-700 bg-slate-950 p-3 text-sm outline-none focus:border-indigo-500"
                                    value={form.data.short_description}
                                    onChange={(event) =>
                                        form.setData(
                                            "short_description",
                                            event.target.value,
                                        )
                                    }
                                />
                            </div>

                            <Checkbox
                                isSelected={form.data.featured}
                                onChange={(value) => form.setData("featured", value)}
                            >
                                محصول ویژه
                            </Checkbox>
                        </div>
                    </Card.Content>
                </Card>

                <Card variant="secondary">
                    <Card.Content className="p-5">
                        <div className="mb-5 flex items-center gap-3">
                            <span className="grid size-10 place-items-center rounded-xl bg-fuchsia-500/10 text-fuchsia-300">
                                <ImageIcon size={20} />
                            </span>
                            <div>
                                <h2 className="text-lg font-black">مدیا محصول</h2>
                                <p className="mt-1 text-xs text-slate-400">
                                    کاور، اسکرین‌شات و ویدیو. یک تصویر کاور اصلی انتخاب کن.
                                </p>
                            </div>
                        </div>

                        <ProductMediaUploader
                            value={form.data.media}
                            onChange={(media) => form.setData("media", media)}
                            progress={
                                form.progress
                                    ? Math.round(form.progress.percentage ?? 0)
                                    : null
                            }
                            error={(form.errors as any).media}
                        />
                    </Card.Content>
                </Card>

                <Card variant="secondary">
                    <Card.Content className="p-5">
                        <div className="mb-5 flex items-center justify-between gap-4">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-xl bg-cyan-500/10 text-cyan-300">
                                    <Filter size={20} />
                                </span>
                                <div>
                                    <h2 className="text-lg font-black">
                                        ویژگی‌ها و فیلترها
                                    </h2>
                                    <p className="mt-1 text-xs text-slate-400">
                                        اول ویژگی را انتخاب کن، بعد مقدار همان ویژگی را.
                                    </p>
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="secondary"
                                isDisabled={!attributes.length}
                                onPress={() =>
                                    setFeatureRows((rows) => [
                                        ...rows,
                                        emptyFeatureRow(),
                                    ])
                                }
                            >
                                <Plus size={16} />
                                ویژگی
                            </Button>
                        </div>

                        {attributes.length ? (
                            <div className="space-y-3">
                                {featureRows.map((row) => {
                                    const attribute = attributes.find(
                                        (item) =>
                                            String(item.id) === row.attribute_id,
                                    );

                                    return (
                                        <div
                                            className="grid gap-3 rounded-2xl border border-slate-800 bg-slate-950/35 p-4 md:grid-cols-[1fr_1fr_44px]"
                                            key={row.key}
                                        >
                                            <label className="text-sm font-bold text-slate-200">
                                                ویژگی
                                                <select
                                                    className="mt-2 h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm"
                                                    value={row.attribute_id}
                                                    onChange={(event) =>
                                                        updateFeatureRow(
                                                            row.key,
                                                            {
                                                                attribute_id:
                                                                    event.target
                                                                        .value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        انتخاب ویژگی
                                                    </option>
                                                    {attributes.map((item) => (
                                                        <option
                                                            key={item.id}
                                                            value={item.id}
                                                        >
                                                            {item.title}
                                                        </option>
                                                    ))}
                                                </select>
                                            </label>

                                            <label className="text-sm font-bold text-slate-200">
                                                مقدار
                                                <select
                                                    className="mt-2 h-11 w-full rounded-xl border border-slate-700 bg-slate-950 px-3 text-sm disabled:opacity-50"
                                                    disabled={!attribute}
                                                    value={row.value}
                                                    onChange={(event) =>
                                                        updateFeatureRow(
                                                            row.key,
                                                            {
                                                                value: event.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <option value="">
                                                        انتخاب مقدار
                                                    </option>
                                                    {attribute?.options.map(
                                                        (option) => (
                                                            <option
                                                                key={option.value}
                                                                value={option.value}
                                                            >
                                                                {option.title}
                                                            </option>
                                                        ),
                                                    )}
                                                </select>
                                                {row.attribute_id &&
                                                    (form.errors as any)[
                                                        `attribute_values.${row.attribute_id}`
                                                    ] && (
                                                        <p className="mt-2 text-xs text-rose-400">
                                                            {
                                                                (form.errors as any)[
                                                                    `attribute_values.${row.attribute_id}`
                                                                ]
                                                            }
                                                        </p>
                                                    )}
                                            </label>

                                            <Button
                                                aria-label="حذف ویژگی"
                                                className="mt-6"
                                                isIconOnly
                                                type="button"
                                                variant="danger-soft"
                                                onPress={() =>
                                                    setFeatureRows((rows) => {
                                                        const next = rows.filter(
                                                            (item) =>
                                                                item.key !==
                                                                row.key,
                                                        );
                                                        return next.length
                                                            ? next
                                                            : [
                                                                  emptyFeatureRow(),
                                                              ];
                                                    })
                                                }
                                            >
                                                <Trash2 size={16} />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-700 p-6 text-center text-sm text-slate-400">
                                هنوز ویژگی فیلترپذیری در پنل ادمین تعریف نشده است.
                            </div>
                        )}
                    </Card.Content>
                </Card>

                <Card variant="secondary">
                    <Card.Content className="p-5">
                        <div className="mb-5">
                            <h2 className="text-lg font-black">
                                ظرفیت‌ها و قیمت فروش
                            </h2>
                            <p className="mt-1 text-xs text-slate-400">
                                قیمت فروش، موجودی و وضعیت هر ظرفیت.
                            </p>
                        </div>

                        <div className="space-y-4">
                            {form.data.offers.map((offer, index) => (
                                <div
                                    className="grid gap-4 rounded-3xl border border-slate-800 bg-slate-950/35 p-4 md:grid-cols-[150px_1fr_130px_130px]"
                                    key={offer.code}
                                >
                                    <div className="flex items-center">
                                        <strong>{offer.label}</strong>
                                    </div>

                                    <div>
                                        <label className="text-sm font-bold text-slate-200">
                                            قیمت فروش
                                        </label>
                                        <div className="mt-2 flex items-stretch gap-2">
                                            <Input
                                                className="min-w-0 flex-1 text-left text-base font-bold tabular-nums"
                                                dir="ltr"
                                                fullWidth
                                                inputMode="numeric"
                                                placeholder="۰"
                                                value={
                                                    offer.price > 0
                                                        ? formatter.format(
                                                              offer.price,
                                                          )
                                                        : ""
                                                }
                                                onChange={(event) => {
                                                    const normalized =
                                                        normalizeDigits(
                                                            event.target.value,
                                                        ).replace(
                                                            /[^0-9]/g,
                                                            "",
                                                        );
                                                    updateOffer(
                                                        index,
                                                        "price",
                                                        normalized
                                                            ? Number(normalized)
                                                            : 0,
                                                    );
                                                }}
                                            />
                                            <Chip
                                                className="h-auto shrink-0 px-3 text-sm font-bold"
                                                color="accent"
                                                variant="soft"
                                            >
                                                تومان
                                            </Chip>
                                        </div>
                                    </div>

                                    <Input
                                        label="موجودی"
                                        type="number"
                                        min="0"
                                        value={String(offer.stock)}
                                        onChange={(event) =>
                                            updateOffer(
                                                index,
                                                "stock",
                                                Number(event.target.value),
                                            )
                                        }
                                    />

                                    <label className="text-xs font-bold text-slate-300">
                                        وضعیت
                                        <select
                                            className="mt-2 h-10 w-full rounded-xl border border-slate-700 bg-slate-950 px-2"
                                            value={offer.status}
                                            onChange={(event) =>
                                                updateOffer(
                                                    index,
                                                    "status",
                                                    event.target
                                                        .value as Offer["status"],
                                                )
                                            }
                                        >
                                            <option value="active">فعال</option>
                                            <option value="inactive">
                                                غیرفعال
                                            </option>
                                        </select>
                                    </label>
                                </div>
                            ))}
                        </div>
                    </Card.Content>
                </Card>

                {Object.keys(form.errors).length > 0 && (
                    <div className="rounded-2xl border border-rose-500/20 bg-rose-500/10 p-4 text-sm text-rose-300">
                        بعضی اطلاعات معتبر نیست. مدیا، ویژگی‌ها و قیمت‌ها را بررسی کن.
                    </div>
                )}

                <div className="flex items-center justify-between">
                    <Link href="/admin/digital-products">
                        <Button variant="secondary">بازگشت</Button>
                    </Link>
                    <Button
                        isDisabled={form.processing}
                        type="submit"
                        variant="primary"
                    >
                        <Save size={17} />
                        {form.processing
                            ? "در حال ذخیره..."
                            : "ذخیره محصول"}
                    </Button>
                </div>
            </form>
        </AdminLayout>
    );
}
