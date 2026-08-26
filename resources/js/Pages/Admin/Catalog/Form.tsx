import { Button, Card, Chip } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowRight, Check, Save } from 'lucide-react';
import { type FormEvent } from 'react';

import AdminLayout from '../../../Layouts/AdminLayout';

interface Option {
    id: number;
    name: string;
}

interface CatalogItem {
    id: number;
    name?: string;
    title?: string;
    slug: string;
    description?: string | null;
    short_description?: string | null;
    parent_id?: number | null;
    website?: string | null;
    developer?: string | null;
    publisher?: string | null;
    release_date?: string | null;
    age_rating?: string | null;
    manufacturer?: string | null;
    sort_order?: number;
    category_id?: number | null;
    brand_id?: number | null;
    game_id?: number | null;
    platform_ids?: number[];
    sku?: string;
    product_type?: string;
    price?: number;
    discount_price?: number | null;
    stock?: number;
    status: string;
    visibility?: string;
    featured?: boolean;
    trade_enabled?: boolean;
}

interface FormOptions {
    categories: Option[];
    brands: Option[];
    games: Option[];
    platforms: Option[];
}

interface CatalogFormProps {
    resource: 'categories' | 'brands' | 'games' | 'platforms' | 'products';
    title: string;
    item: CatalogItem | null;
    options: FormOptions;
}

interface CatalogFormData {
    name: string;
    title: string;
    slug: string;
    description: string;
    short_description: string;
    parent_id: string;
    website: string;
    developer: string;
    publisher: string;
    release_date: string;
    age_rating: string;
    manufacturer: string;
    sort_order: number;
    category_id: string;
    brand_id: string;
    game_id: string;
    platform_ids: number[];
    sku: string;
    product_type: string;
    price: number;
    discount_price: number | '';
    stock: number;
    status: string;
    visibility: string;
    featured: boolean;
    trade_enabled: boolean;
}

const inputClassName =
    'h-11 w-full rounded-xl border border-slate-700/80 bg-slate-950/40 px-3 text-sm text-slate-100 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/15';
const textareaClassName = `${inputClassName} h-28 resize-y py-3 leading-6`;

export default function CatalogForm({ resource, title, item, options }: CatalogFormProps) {
    const { data, setData, post, put, processing, errors } = useForm<CatalogFormData>({
        name: item?.name ?? '',
        title: item?.title ?? '',
        slug: item?.slug ?? '',
        description: item?.description ?? '',
        short_description: item?.short_description ?? '',
        parent_id: item?.parent_id?.toString() ?? '',
        website: item?.website ?? '',
        developer: item?.developer ?? '',
        publisher: item?.publisher ?? '',
        release_date: item?.release_date?.slice(0, 10) ?? '',
        age_rating: item?.age_rating ?? '',
        manufacturer: item?.manufacturer ?? '',
        sort_order: item?.sort_order ?? 0,
        category_id: item?.category_id?.toString() ?? '',
        brand_id: item?.brand_id?.toString() ?? '',
        game_id: item?.game_id?.toString() ?? '',
        platform_ids: item?.platform_ids ?? [],
        sku: item?.sku ?? '',
        product_type: item?.product_type ?? 'physical',
        price: item?.price ?? 0,
        discount_price: item?.discount_price ?? '',
        stock: item?.stock ?? 0,
        status: item?.status ?? (resource === 'products' ? 'draft' : 'active'),
        visibility: item?.visibility ?? 'public',
        featured: item?.featured ?? false,
        trade_enabled: item?.trade_enabled ?? false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (item) {
            put(`/admin/${resource}/${item.id}`);
            return;
        }

        post(`/admin/${resource}`);
    };

    const field = (name: keyof CatalogFormData, label: string, element: React.ReactNode) => (
        <label className="block">
            <span className="mb-2 block text-xs font-bold text-slate-400">{label}</span>
            {element}
            {errors[name] && <span className="mt-1.5 block text-xs text-red-400">{errors[name]}</span>}
        </label>
    );

    return (
        <AdminLayout
            actions={
                <>
                    <Link href={`/admin/${resource}`}>
                        <Button variant="secondary">انصراف</Button>
                    </Link>
                    <Button isDisabled={processing} onPress={() => document.querySelector<HTMLFormElement>('#catalog-form')?.requestSubmit()} variant="primary">
                        <Save size={17} />
                        {processing ? 'در حال ذخیره...' : 'ذخیره تغییرات'}
                    </Button>
                </>
            }
            description="اطلاعات را دقیق و کامل وارد کنید؛ فیلدهای ستاره‌دار الزامی هستند."
            title={title}
        >
            <Head title={title} />

            <div className="mb-5 flex items-center gap-2 text-xs text-slate-500">
                <Link className="transition hover:text-indigo-400" href={`/admin/${resource}`}>فهرست</Link>
                <ArrowRight size={13} />
                <span className="text-slate-300">{title}</span>
            </div>

            <form className="grid gap-6 xl:grid-cols-[1fr_320px]" id="catalog-form" onSubmit={submit}>
                <div className="space-y-6">
                    <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                        <Card.Header className="border-b border-slate-800/80 p-5">
                            <Card.Title className="text-base font-black text-white">اطلاعات اصلی</Card.Title>
                        </Card.Header>
                        <Card.Content className="grid gap-5 p-5 sm:grid-cols-2">
                            {resource === 'products'
                                ? field('title', 'عنوان محصول *', <input className={inputClassName} onChange={(event) => setData('title', event.target.value)} value={data.title} />)
                                : field('name', 'نام *', <input className={inputClassName} onChange={(event) => setData('name', event.target.value)} value={data.name} />)}

                            {field('slug', 'نامک URL *', <input className={inputClassName} dir="ltr" onChange={(event) => setData('slug', event.target.value)} placeholder="auto-generated-if-empty" value={data.slug} />)}

                            {resource === 'categories' && field('parent_id', 'دسته والد', (
                                <select className={inputClassName} onChange={(event) => setData('parent_id', event.target.value)} value={data.parent_id}>
                                    <option value="">بدون والد</option>
                                    {options.categories.filter((option) => option.id !== item?.id).map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                                </select>
                            ))}

                            {resource === 'brands' && field('website', 'وب‌سایت', <input className={inputClassName} dir="ltr" onChange={(event) => setData('website', event.target.value)} value={data.website} />)}

                            {resource === 'platforms' && field('manufacturer', 'سازنده', <input className={inputClassName} onChange={(event) => setData('manufacturer', event.target.value)} value={data.manufacturer} />)}

                            {resource === 'games' && (
                                <>
                                    {field('developer', 'توسعه‌دهنده', <input className={inputClassName} onChange={(event) => setData('developer', event.target.value)} value={data.developer} />)}
                                    {field('publisher', 'ناشر', <input className={inputClassName} onChange={(event) => setData('publisher', event.target.value)} value={data.publisher} />)}
                                    {field('release_date', 'تاریخ انتشار', <input className={inputClassName} onChange={(event) => setData('release_date', event.target.value)} type="date" value={data.release_date} />)}
                                    {field('age_rating', 'رده سنی', <input className={inputClassName} onChange={(event) => setData('age_rating', event.target.value)} placeholder="PEGI 18" value={data.age_rating} />)}
                                </>
                            )}

                            {['categories', 'platforms'].includes(resource) && field('sort_order', 'ترتیب نمایش', <input className={inputClassName} min="0" onChange={(event) => setData('sort_order', Number(event.target.value))} type="number" value={data.sort_order} />)}

                            {resource === 'products' && (
                                <>
                                    {field('sku', 'SKU *', <input className={inputClassName} dir="ltr" onChange={(event) => setData('sku', event.target.value)} value={data.sku} />)}
                                    {field('product_type', 'نوع محصول *', (
                                        <select className={inputClassName} onChange={(event) => setData('product_type', event.target.value)} value={data.product_type}>
                                            <option value="physical">فیزیکی</option><option value="digital">دیجیتال</option><option value="game_account">اکانت بازی</option>
                                            <option value="capacity">ظرفیت</option><option value="accessory">لوازم جانبی</option><option value="console">کنسول</option>
                                            <option value="gift_card">گیفت کارت</option><option value="other">سایر</option>
                                        </select>
                                    ))}
                                    {field('category_id', 'دسته‌بندی', <select className={inputClassName} onChange={(event) => setData('category_id', event.target.value)} value={data.category_id}><option value="">انتخاب کنید</option>{options.categories.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select>)}
                                    {field('brand_id', 'برند', <select className={inputClassName} onChange={(event) => setData('brand_id', event.target.value)} value={data.brand_id}><option value="">انتخاب کنید</option>{options.brands.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select>)}
                                    {field('game_id', 'بازی مرتبط', <select className={inputClassName} onChange={(event) => setData('game_id', event.target.value)} value={data.game_id}><option value="">انتخاب کنید</option>{options.games.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}</select>)}
                                </>
                            )}
                        </Card.Content>
                    </Card>

                    {(resource === 'products' || resource === 'games') && (
                        <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                            <Card.Header className="border-b border-slate-800/80 p-5"><Card.Title className="text-base font-black text-white">پلتفرم‌ها</Card.Title></Card.Header>
                            <Card.Content className="p-5">
                                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    {options.platforms.map((platform) => {
                                        const selected = data.platform_ids.includes(platform.id);
                                        return (
                                            <button
                                                className={`flex items-center gap-3 rounded-xl border p-3 text-right text-sm transition ${selected ? 'border-indigo-500 bg-indigo-500/10 text-indigo-300' : 'border-slate-700/80 bg-slate-950/30 text-slate-400 hover:border-slate-600'}`}
                                                key={platform.id}
                                                onClick={() => setData('platform_ids', selected ? data.platform_ids.filter((id) => id !== platform.id) : [...data.platform_ids, platform.id])}
                                                type="button"
                                            >
                                                <span className={`grid size-5 place-items-center rounded-md border ${selected ? 'border-indigo-400 bg-indigo-500 text-white' : 'border-slate-600'}`}>{selected && <Check size={13} />}</span>
                                                {platform.name}
                                            </button>
                                        );
                                    })}
                                </div>
                            </Card.Content>
                        </Card>
                    )}

                    {(resource === 'products' || ['categories', 'brands', 'games'].includes(resource)) && (
                        <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                            <Card.Header className="border-b border-slate-800/80 p-5"><Card.Title className="text-base font-black text-white">توضیحات</Card.Title></Card.Header>
                            <Card.Content className="space-y-5 p-5">
                                {resource === 'products' && field('short_description', 'توضیح کوتاه', <textarea className={textareaClassName} onChange={(event) => setData('short_description', event.target.value)} value={data.short_description} />)}
                                {field('description', 'توضیحات کامل', <textarea className={textareaClassName} onChange={(event) => setData('description', event.target.value)} value={data.description} />)}
                            </Card.Content>
                        </Card>
                    )}

                    {resource === 'products' && (
                        <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                            <Card.Header className="border-b border-slate-800/80 p-5"><Card.Title className="text-base font-black text-white">قیمت و موجودی</Card.Title></Card.Header>
                            <Card.Content className="grid gap-5 p-5 sm:grid-cols-3">
                                {field('price', 'قیمت (تومان) *', <input className={inputClassName} min="0" onChange={(event) => setData('price', Number(event.target.value))} type="number" value={data.price} />)}
                                {field('discount_price', 'قیمت تخفیف', <input className={inputClassName} min="0" onChange={(event) => setData('discount_price', event.target.value === '' ? '' : Number(event.target.value))} type="number" value={data.discount_price} />)}
                                {field('stock', 'موجودی *', <input className={inputClassName} min="0" onChange={(event) => setData('stock', Number(event.target.value))} type="number" value={data.stock} />)}
                            </Card.Content>
                        </Card>
                    )}
                </div>

                <aside className="space-y-6">
                    <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                        <Card.Header className="border-b border-slate-800/80 p-5"><Card.Title className="text-base font-black text-white">انتشار</Card.Title></Card.Header>
                        <Card.Content className="space-y-5 p-5">
                            {field('status', 'وضعیت', (
                                <select className={inputClassName} onChange={(event) => setData('status', event.target.value)} value={data.status}>
                                    {resource === 'products' && <option value="draft">پیش‌نویس</option>}
                                    <option value="active">فعال</option><option value="inactive">غیرفعال</option>
                                </select>
                            ))}
                            {resource === 'products' && field('visibility', 'نمایش', <select className={inputClassName} onChange={(event) => setData('visibility', event.target.value)} value={data.visibility}><option value="public">عمومی</option><option value="private">خصوصی</option></select>)}
                            <div className="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/30 p-3">
                                <span className="text-xs text-slate-500">شناسه</span>
                                <Chip size="sm" variant="soft">{item?.id ?? 'پس از ذخیره'}</Chip>
                            </div>
                        </Card.Content>
                    </Card>

                    {resource === 'products' && (
                        <Card className="border border-slate-800/80 bg-slate-900/55" variant="secondary">
                            <Card.Header className="border-b border-slate-800/80 p-5"><Card.Title className="text-base font-black text-white">قابلیت‌ها</Card.Title></Card.Header>
                            <Card.Content className="space-y-3 p-5">
                                <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-800 p-3 text-sm text-slate-300">
                                    <input checked={data.featured} className="accent-indigo-500" onChange={(event) => setData('featured', event.target.checked)} type="checkbox" /> محصول ویژه
                                </label>
                                <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-800 p-3 text-sm text-slate-300">
                                    <input checked={data.trade_enabled} className="accent-indigo-500" onChange={(event) => setData('trade_enabled', event.target.checked)} type="checkbox" /> امکان معاوضه
                                </label>
                            </Card.Content>
                        </Card>
                    )}
                </aside>
            </form>
        </AdminLayout>
    );
}
