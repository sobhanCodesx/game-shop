import { Button, Card, Checkbox, Input } from "@heroui/react";
import { Head, Link, useForm } from "@inertiajs/react";
import { Plus, Save, Trash2 } from "lucide-react";
import type { FormEvent } from "react";

import FormField from "../../../Components/Admin/Form/FormField";
import HeroSelect from "../../../Components/Admin/Form/HeroSelect";
import RichTextEditor from "../../../Components/Admin/Form/RichTextEditor";
import AdminLayout from "../../../Layouts/AdminLayout";

interface CategoryAttributeForm {
    name: string;
    slug: string;
    type: string;
    options: string[];
    is_required: boolean;
    is_filterable: boolean;
}

interface CategoryItem {
    id: number;
    name: string;
    slug: string;
    parent_id: number | null;
    description: string | null;
    sort_order: number;
    status: string;
    attributes: CategoryAttributeForm[];
}

interface CategoryFormProps {
    title: string;
    item: CategoryItem | null;
    options: { categories: Array<{ id: number; name: string }> };
}

interface CategoryFormData {
    name: string;
    slug: string;
    parent_id: string;
    description: string;
    sort_order: number;
    status: string;
    attributes: CategoryAttributeForm[];
}

const emptyAttribute = (): CategoryAttributeForm => ({
    name: "",
    slug: "",
    type: "text",
    options: [],
    is_required: false,
    is_filterable: true,
});

export default function CategoryForm({
    title,
    item,
    options,
}: CategoryFormProps) {
    const { data, setData, post, put, processing, errors } =
        useForm<CategoryFormData>({
            name: item?.name ?? "",
            slug: item?.slug ?? "",
            parent_id: item?.parent_id?.toString() ?? "",
            description: item?.description ?? "",
            sort_order: item?.sort_order ?? 0,
            status: item?.status ?? "active",
            attributes: item?.attributes ?? [],
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        item ? put(`/admin/categories/${item.id}`) : post("/admin/categories");
    };

    const updateAttribute = <K extends keyof CategoryAttributeForm>(
        index: number,
        key: K,
        value: CategoryAttributeForm[K],
    ) => {
        const attributes = [...data.attributes];
        attributes[index] = { ...attributes[index], [key]: value };
        setData("attributes", attributes);
    };

    return (
        <AdminLayout
            actions={
                <>
                    <Link href="/admin/categories">
                        <Button variant="secondary">بازگشت</Button>
                    </Link>
                    <Button
                        isDisabled={processing}
                        onPress={() =>
                            document
                                .querySelector<HTMLFormElement>(
                                    "#category-form",
                                )
                                ?.requestSubmit()
                        }
                        variant="primary"
                    >
                        <Save size={17} />
                        ذخیره دسته‌بندی
                    </Button>
                </>
            }
            description="ساختار والد و فرزند و ویژگی‌های قابل فیلتر این دسته را تعریف کنید."
            title={title}
        >
            <Head title={title} />
            <form
                className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_320px]"
                id="category-form"
                onSubmit={submit}
            >
                <div className="space-y-6">
                    <Card
                        className="border border-slate-800/80 bg-slate-900/55"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>اطلاعات و جایگاه دسته</Card.Title>
                        </Card.Header>
                        <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                            <FormField
                                error={errors.name}
                                label="نام دسته"
                                required
                            >
                                <Input
                                    fullWidth
                                    onChange={(e) =>
                                        setData("name", e.target.value)
                                    }
                                    placeholder="مثلاً بازی‌های پلی‌استیشن"
                                    value={data.name}
                                />
                            </FormField>
                            <FormField
                                error={errors.slug}
                                label="نامک URL"
                                required
                            >
                                <Input
                                    dir="ltr"
                                    fullWidth
                                    onChange={(e) =>
                                        setData("slug", e.target.value)
                                    }
                                    placeholder="playstation-games"
                                    value={data.slug}
                                />
                            </FormField>
                            <HeroSelect
                                label="دسته والد"
                                onChange={(value) =>
                                    setData("parent_id", value)
                                }
                                options={options.categories
                                    .filter(
                                        (category) => category.id !== item?.id,
                                    )
                                    .map((category) => ({
                                        id: category.id.toString(),
                                        label: category.name,
                                    }))}
                                placeholder="این دسته والد ندارد"
                                value={data.parent_id}
                            />
                            <FormField
                                error={errors.sort_order}
                                label="ترتیب نمایش"
                            >
                                <Input
                                    fullWidth
                                    min="0"
                                    onChange={(e) =>
                                        setData(
                                            "sort_order",
                                            Number(e.target.value),
                                        )
                                    }
                                    type="number"
                                    value={data.sort_order.toString()}
                                />
                            </FormField>
                            <div className="sm:col-span-2">
                                <FormField
                                    error={errors.description}
                                    label="توضیحات"
                                >
                                    <RichTextEditor
                                        minHeight={160}
                                        onChange={(value) =>
                                            setData("description", value)
                                        }
                                        value={data.description}
                                    />
                                </FormField>
                            </div>
                        </Card.Content>
                    </Card>

                    <Card
                        className="border border-slate-800/80 bg-slate-900/55"
                        variant="secondary"
                    >
                        <Card.Header className="flex items-center justify-between border-b border-slate-800 p-5">
                            <div>
                                <Card.Title>ویژگی‌های محصولات</Card.Title>
                                <Card.Description>
                                    هر ویژگی فقط برای محصولات این دسته نمایش
                                    داده و در فیلتر استفاده می‌شود.
                                </Card.Description>
                            </div>
                            <Button
                                onPress={() =>
                                    setData("attributes", [
                                        ...data.attributes,
                                        emptyAttribute(),
                                    ])
                                }
                                variant="primary"
                            >
                                <Plus size={16} />
                                ویژگی جدید
                            </Button>
                        </Card.Header>
                        <Card.Content className="space-y-4 p-5">
                            {data.attributes.length === 0 && (
                                <div className="rounded-xl border border-dashed border-slate-700 p-8 text-center text-sm text-slate-400">
                                    هنوز ویژگی‌ای تعریف نشده است.
                                </div>
                            )}
                            {data.attributes.map((attribute, index) => (
                                <Card
                                    className="border border-slate-700 bg-slate-950/30"
                                    key={index}
                                    variant="secondary"
                                >
                                    <Card.Content className="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">
                                        <FormField label="عنوان ویژگی" required>
                                            <Input
                                                fullWidth
                                                onChange={(e) =>
                                                    updateAttribute(
                                                        index,
                                                        "name",
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="مثلاً ریجن"
                                                value={attribute.name}
                                            />
                                        </FormField>
                                        <FormField label="شناسه" required>
                                            <Input
                                                dir="ltr"
                                                fullWidth
                                                onChange={(e) =>
                                                    updateAttribute(
                                                        index,
                                                        "slug",
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="region"
                                                value={attribute.slug}
                                            />
                                        </FormField>
                                        <HeroSelect
                                            label="نوع مقدار"
                                            onChange={(value) =>
                                                updateAttribute(
                                                    index,
                                                    "type",
                                                    value,
                                                )
                                            }
                                            options={[
                                                { id: "text", label: "متن" },
                                                { id: "number", label: "عدد" },
                                                {
                                                    id: "select",
                                                    label: "انتخاب از لیست",
                                                },
                                            ]}
                                            value={attribute.type}
                                        />
                                        {attribute.type === "select" && (
                                            <div className="sm:col-span-2">
                                                <FormField
                                                    description="مقادیر را با ویرگول جدا کنید."
                                                    label="گزینه‌ها"
                                                >
                                                    <Input
                                                        fullWidth
                                                        onChange={(e) =>
                                                            updateAttribute(
                                                                index,
                                                                "options",
                                                                e.target.value
                                                                    .split(
                                                                        /[،,]/,
                                                                    )
                                                                    .map((v) =>
                                                                        v.trim(),
                                                                    )
                                                                    .filter(
                                                                        Boolean,
                                                                    ),
                                                            )
                                                        }
                                                        placeholder="Global، US، EU"
                                                        value={attribute.options.join(
                                                            "، ",
                                                        )}
                                                    />
                                                </FormField>
                                            </div>
                                        )}
                                        <div className="flex items-end gap-3">
                                            <Checkbox
                                                isSelected={
                                                    attribute.is_filterable
                                                }
                                                onChange={(selected) =>
                                                    updateAttribute(
                                                        index,
                                                        "is_filterable",
                                                        selected,
                                                    )
                                                }
                                            >
                                                <Checkbox.Control>
                                                    <Checkbox.Indicator />
                                                </Checkbox.Control>
                                                <Checkbox.Content>
                                                    قابل فیلتر
                                                </Checkbox.Content>
                                            </Checkbox>
                                            <Checkbox
                                                isSelected={
                                                    attribute.is_required
                                                }
                                                onChange={(selected) =>
                                                    updateAttribute(
                                                        index,
                                                        "is_required",
                                                        selected,
                                                    )
                                                }
                                            >
                                                <Checkbox.Control>
                                                    <Checkbox.Indicator />
                                                </Checkbox.Control>
                                                <Checkbox.Content>
                                                    اجباری
                                                </Checkbox.Content>
                                            </Checkbox>
                                        </div>
                                        <Button
                                            aria-label="حذف ویژگی"
                                            isIconOnly
                                            onPress={() =>
                                                setData(
                                                    "attributes",
                                                    data.attributes.filter(
                                                        (_, i) => i !== index,
                                                    ),
                                                )
                                            }
                                            variant="danger-soft"
                                        >
                                            <Trash2 size={16} />
                                        </Button>
                                    </Card.Content>
                                </Card>
                            ))}
                        </Card.Content>
                    </Card>
                </div>
                <aside>
                    <Card
                        className="sticky top-28 border border-slate-800 bg-slate-900/55"
                        variant="secondary"
                    >
                        <Card.Header className="border-b border-slate-800 p-5">
                            <Card.Title>وضعیت دسته</Card.Title>
                        </Card.Header>
                        <Card.Content className="p-5">
                            <HeroSelect
                                label="وضعیت"
                                onChange={(value) => setData("status", value)}
                                options={[
                                    { id: "active", label: "فعال" },
                                    { id: "inactive", label: "غیرفعال" },
                                ]}
                                value={data.status}
                            />
                            <div className="mt-5 rounded-xl bg-slate-950/40 p-4 text-sm leading-7 text-slate-400">
                                {data.parent_id
                                    ? "این دسته به‌عنوان فرزند نمایش داده می‌شود."
                                    : "این دسته در سطح اصلی قرار می‌گیرد."}
                            </div>
                        </Card.Content>
                    </Card>
                </aside>
            </form>
        </AdminLayout>
    );
}
