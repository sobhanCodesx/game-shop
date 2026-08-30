import { Button, Card } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { Plus, Save, Trash2 } from "lucide-react";
import { type FormEvent } from "react";

import AdminLayout from "../../../Layouts/AdminLayout";

interface AttributeChoice {
    id: number;
    title: string;
}

interface AttributeOptionData {
    title: string;
    value: string;
    status: string;
    sort_order: number;
}

interface FoundationItem {
    id: number;
    title: string;
    slug: string;
    icon?: string | null;
    inventory_type?: string;
    supports_variants?: boolean;
    supports_shipping?: boolean;
    supports_exchange?: boolean;
    supports_digital_delivery?: boolean;
    supports_digital_inventory?: boolean;
    requires_cover?: boolean;
    input_type?: string;
    is_required?: boolean;
    is_filterable?: boolean;
    is_searchable?: boolean;
    is_visible_on_product?: boolean;
    is_usable_for_variant?: boolean;
    status: string;
    sort_order: number;
    attribute_ids?: number[];
    options?: AttributeOptionData[];
}

interface Props {
    kind: "product-type" | "attribute";
    item: FoundationItem | null;
    attributes: AttributeChoice[];
}

interface FormData {
    title: string;
    slug: string;
    icon: string;
    inventory_type: string;
    supports_variants: boolean;
    supports_shipping: boolean;
    supports_exchange: boolean;
    supports_digital_delivery: boolean;
    supports_digital_inventory: boolean;
    requires_cover: boolean;
    input_type: string;
    is_required: boolean;
    is_filterable: boolean;
    is_searchable: boolean;
    is_visible_on_product: boolean;
    is_usable_for_variant: boolean;
    status: string;
    sort_order: number;
    attribute_ids: number[];
    options: AttributeOptionData[];
}

const inputClass =
    "h-11 w-full rounded-xl border border-slate-700 bg-slate-950/50 px-3 text-sm text-slate-100 outline-none focus:border-indigo-500";

export default function FoundationForm({ kind, item, attributes }: Props) {
    const isType = kind === "product-type";
    const resource = isType ? "product-types" : "attributes";
    const entityLabel = isType ? "نوع محصول" : "ویژگی";
    const { data, setData, post, put, processing, errors } = useForm<FormData>({
        title: item?.title ?? "",
        slug: item?.slug ?? "",
        icon: item?.icon ?? "",
        inventory_type: item?.inventory_type ?? "standard",
        supports_variants: item?.supports_variants ?? true,
        supports_shipping: item?.supports_shipping ?? false,
        supports_exchange: item?.supports_exchange ?? false,
        supports_digital_delivery: item?.supports_digital_delivery ?? false,
        supports_digital_inventory: item?.supports_digital_inventory ?? false,
        requires_cover: item?.requires_cover ?? true,
        input_type: item?.input_type ?? "text",
        is_required: item?.is_required ?? false,
        is_filterable: item?.is_filterable ?? false,
        is_searchable: item?.is_searchable ?? false,
        is_visible_on_product: item?.is_visible_on_product ?? true,
        is_usable_for_variant: item?.is_usable_for_variant ?? false,
        status: item?.status ?? "active",
        sort_order: item?.sort_order ?? 0,
        attribute_ids: item?.attribute_ids ?? [],
        options: item?.options ?? [],
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        item
            ? put(`/admin/${resource}/${item.id}`)
            : post(`/admin/${resource}`);
    };
    const usesOptions = ["select", "multi_select"].includes(data.input_type);
    const toggle = (key: keyof FormData, label: string) => (
        <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-800 p-3 text-sm text-slate-300">
            <input
                checked={Boolean(data[key])}
                className="accent-indigo-500"
                onChange={(event) =>
                    setData(key, event.target.checked as never)
                }
                type="checkbox"
            />
            {label}
        </label>
    );

    return (
        <AdminLayout
            actions={
                <>
                    <Link href={`/admin/${resource}`}>
                        <Button variant="secondary">انصراف</Button>
                    </Link>
                    <Button
                        isDisabled={processing}
                        onPress={() =>
                            document
                                .querySelector<HTMLFormElement>(
                                    "#foundation-form",
                                )
                                ?.requestSubmit()
                        }
                        variant="primary"
                    >
                        <Save size={17} />
                        ذخیره
                    </Button>
                </>
            }
            description="تنظیمات این بخش مستقیماً فرم و رفتار محصولات را کنترل می‌کند."
            title={`${item ? "ویرایش" : "ایجاد"} ${entityLabel}`}
        >
            <Head title={`${item ? "ویرایش" : "ایجاد"} ${entityLabel}`} />
            <form
                className="grid gap-6 xl:grid-cols-[1fr_340px]"
                id="foundation-form"
                onSubmit={submit}
            >
                <div className="space-y-6">
                    <Card
                        className="border border-slate-800 bg-slate-900/55"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>اطلاعات اصلی</Card.Title>
                        </Card.Header>
                        <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                            <label>
                                <span className="mb-2 block text-xs text-slate-400">
                                    عنوان *
                                </span>
                                <input
                                    className={inputClass}
                                    onChange={(event) =>
                                        setData("title", event.target.value)
                                    }
                                    value={data.title}
                                />
                                {errors.title && (
                                    <small className="text-red-400">
                                        {errors.title}
                                    </small>
                                )}
                            </label>
                            <label>
                                <span className="mb-2 block text-xs text-slate-400">
                                    نامک انگلیسی *
                                </span>
                                <input
                                    className={inputClass}
                                    dir="ltr"
                                    onChange={(event) =>
                                        setData("slug", event.target.value)
                                    }
                                    value={data.slug}
                                />
                                {errors.slug && (
                                    <small className="text-red-400">
                                        {errors.slug}
                                    </small>
                                )}
                            </label>
                            {isType ? (
                                <>
                                    <label>
                                        <span className="mb-2 block text-xs text-slate-400">
                                            نوع موجودی
                                        </span>
                                        <select
                                            className={inputClass}
                                            onChange={(event) =>
                                                setData(
                                                    "inventory_type",
                                                    event.target.value,
                                                )
                                            }
                                            value={data.inventory_type}
                                        >
                                            <option value="none">
                                                بدون موجودی
                                            </option>
                                            <option value="standard">
                                                فیزیکی
                                            </option>
                                            <option value="digital">
                                                دیجیتال
                                            </option>
                                        </select>
                                    </label>
                                    <label>
                                        <span className="mb-2 block text-xs text-slate-400">
                                            آیکن
                                        </span>
                                        <input
                                            className={inputClass}
                                            onChange={(event) =>
                                                setData(
                                                    "icon",
                                                    event.target.value,
                                                )
                                            }
                                            value={data.icon}
                                        />
                                    </label>
                                </>
                            ) : (
                                <label>
                                    <span className="mb-2 block text-xs text-slate-400">
                                        نوع ورودی
                                    </span>
                                    <select
                                        className={inputClass}
                                        onChange={(event) =>
                                            setData(
                                                "input_type",
                                                event.target.value,
                                            )
                                        }
                                        value={data.input_type}
                                    >
                                        <option value="text">متن کوتاه</option>
                                        <option value="textarea">
                                            متن بلند
                                        </option>
                                        <option value="number">عدد</option>
                                        <option value="select">
                                            انتخاب تکی
                                        </option>
                                        <option value="multi_select">
                                            انتخاب چندتایی
                                        </option>
                                        <option value="boolean">بله/خیر</option>
                                        <option value="date">تاریخ</option>
                                    </select>
                                </label>
                            )}
                            <label>
                                <span className="mb-2 block text-xs text-slate-400">
                                    ترتیب
                                </span>
                                <input
                                    className={inputClass}
                                    min="0"
                                    onChange={(event) =>
                                        setData(
                                            "sort_order",
                                            Number(event.target.value),
                                        )
                                    }
                                    type="number"
                                    value={data.sort_order}
                                />
                            </label>
                        </Card.Content>
                    </Card>

                    {!isType && usesOptions && (
                        <Card
                            className="border border-slate-800 bg-slate-900/55"
                            variant="secondary"
                        >
                            <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                                <Card.Title>گزینه‌ها</Card.Title>
                                <Button
                                    onPress={() =>
                                        setData("options", [
                                            ...data.options,
                                            {
                                                title: "",
                                                value: "",
                                                status: "active",
                                                sort_order: data.options.length,
                                            },
                                        ])
                                    }
                                    size="sm"
                                    variant="secondary"
                                >
                                    <Plus size={15} />
                                    افزودن گزینه
                                </Button>
                            </Card.Header>
                            <Card.Content className="space-y-3 p-5">
                                {data.options.map((option, index) => (
                                    <div
                                        className="grid gap-3 rounded-xl border border-slate-800 p-3 sm:grid-cols-[1fr_1fr_90px_44px]"
                                        key={`${index}-${option.value}`}
                                    >
                                        <input
                                            className={inputClass}
                                            onChange={(event) =>
                                                setData(
                                                    "options",
                                                    data.options.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      title: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                            placeholder="عنوان"
                                            value={option.title}
                                        />
                                        <input
                                            className={inputClass}
                                            dir="ltr"
                                            onChange={(event) =>
                                                setData(
                                                    "options",
                                                    data.options.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      value: event
                                                                          .target
                                                                          .value,
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                            placeholder="value"
                                            value={option.value}
                                        />
                                        <input
                                            className={inputClass}
                                            min="0"
                                            onChange={(event) =>
                                                setData(
                                                    "options",
                                                    data.options.map(
                                                        (row, rowIndex) =>
                                                            rowIndex === index
                                                                ? {
                                                                      ...row,
                                                                      sort_order:
                                                                          Number(
                                                                              event
                                                                                  .target
                                                                                  .value,
                                                                          ),
                                                                  }
                                                                : row,
                                                    ),
                                                )
                                            }
                                            type="number"
                                            value={option.sort_order}
                                        />
                                        <Button
                                            aria-label="حذف گزینه"
                                            isIconOnly
                                            onPress={() =>
                                                setData(
                                                    "options",
                                                    data.options.filter(
                                                        (_, rowIndex) =>
                                                            rowIndex !== index,
                                                    ),
                                                )
                                            }
                                            variant="danger-soft"
                                        >
                                            <Trash2 size={15} />
                                        </Button>
                                    </div>
                                ))}
                            </Card.Content>
                        </Card>
                    )}

                    {isType && (
                        <Card
                            className="border border-slate-800 bg-slate-900/55"
                            variant="secondary"
                        >
                            <Card.Header className="border-b border-slate-800 p-5">
                                <Card.Title>ویژگی‌های مجاز</Card.Title>
                            </Card.Header>
                            <Card.Content className="grid gap-3 p-5 sm:grid-cols-2">
                                {attributes.map((attribute) => (
                                    <label
                                        className="flex items-center gap-3 rounded-xl border border-slate-800 p-3 text-sm text-slate-300"
                                        key={attribute.id}
                                    >
                                        <input
                                            checked={data.attribute_ids.includes(
                                                attribute.id,
                                            )}
                                            onChange={() =>
                                                setData(
                                                    "attribute_ids",
                                                    data.attribute_ids.includes(
                                                        attribute.id,
                                                    )
                                                        ? data.attribute_ids.filter(
                                                              (id) =>
                                                                  id !==
                                                                  attribute.id,
                                                          )
                                                        : [
                                                              ...data.attribute_ids,
                                                              attribute.id,
                                                          ],
                                                )
                                            }
                                            type="checkbox"
                                        />
                                        {attribute.title}
                                    </label>
                                ))}
                            </Card.Content>
                        </Card>
                    )}
                </div>

                <aside className="space-y-6">
                    <Card
                        className="border border-slate-800 bg-slate-900/55"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>رفتار و نمایش</Card.Title>
                        </Card.Header>
                        <Card.Content className="space-y-3 p-5">
                            {isType ? (
                                <>
                                    {toggle(
                                        "supports_variants",
                                        "پشتیبانی از تنوع",
                                    )}
                                    {toggle(
                                        "supports_shipping",
                                        "نیازمند ارسال",
                                    )}
                                    {toggle("supports_exchange", "قابل معاوضه")}
                                    {toggle(
                                        "supports_digital_delivery",
                                        "تحویل دیجیتال",
                                    )}
                                    {toggle(
                                        "supports_digital_inventory",
                                        "موجودی دیجیتال",
                                    )}
                                    {toggle("requires_cover", "کاور اجباری")}
                                </>
                            ) : (
                                <>
                                    {toggle(
                                        "is_required",
                                        "الزامی به‌صورت پیش‌فرض",
                                    )}
                                    {toggle("is_filterable", "قابل فیلتر")}
                                    {toggle("is_searchable", "قابل جستجو")}
                                    {toggle(
                                        "is_visible_on_product",
                                        "نمایش در صفحه محصول",
                                    )}
                                    {toggle(
                                        "is_usable_for_variant",
                                        "قابل استفاده در تنوع",
                                    )}
                                </>
                            )}
                            <label>
                                <span className="mb-2 block text-xs text-slate-400">
                                    وضعیت
                                </span>
                                <select
                                    className={inputClass}
                                    onChange={(event) =>
                                        setData("status", event.target.value)
                                    }
                                    value={data.status}
                                >
                                    <option value="active">فعال</option>
                                    <option value="inactive">غیرفعال</option>
                                </select>
                            </label>
                        </Card.Content>
                    </Card>
                </aside>
            </form>
        </AdminLayout>
    );
}
