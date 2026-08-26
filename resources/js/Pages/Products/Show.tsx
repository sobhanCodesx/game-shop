import { Button, Card, Chip } from '@heroui/react';
import { Head } from '@inertiajs/react';
import { BadgeCheck, CalendarDays, Gamepad2, PackageCheck } from 'lucide-react';

interface ProductPageProps {
    product: {
        title: string;
        slug: string;
        short_description: string | null;
        availability: string;
        release_date: string | null;
        category: string | null;
        brand: string | null;
        platforms: string[];
        attributes: Array<{ name: string; value: string }>;
        pricing: {
            regular_price: number;
            sale_price: number;
            final_price: number;
            is_partner_price: boolean;
            discount_amount: number;
        };
    };
}

const money = new Intl.NumberFormat('fa-IR');

export default function Show({ product }: ProductPageProps) {
    const { pricing } = product;
    const persianReleaseDate = product.release_date
        ? new Intl.DateTimeFormat('fa-IR-u-ca-persian', { dateStyle: 'long' }).format(new Date(product.release_date))
        : null;

    return (
        <main className="min-h-screen bg-slate-950 px-4 py-12 text-slate-100" dir="rtl">
            <Head title={product.title} />
            <div className="mx-auto grid max-w-6xl gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
                <section className="space-y-6">
                    <Card className="border border-slate-800 bg-slate-900/70" variant="secondary">
                        <Card.Content className="space-y-5 p-7">
                            <div className="flex flex-wrap gap-2">
                                {product.category && <Chip variant="soft">{product.category}</Chip>}
                                {product.brand && <Chip variant="soft">{product.brand}</Chip>}
                                {product.platforms.map((platform) => <Chip key={platform} variant="soft">{platform}</Chip>)}
                            </div>
                            <h1 className="text-3xl font-black leading-tight text-white">{product.title}</h1>
                            {product.short_description && <p className="leading-8 text-slate-300">{product.short_description}</p>}
                            {persianReleaseDate && <div className="flex items-center gap-2 text-sm text-slate-400"><CalendarDays size={18} /> تاریخ انتشار: {persianReleaseDate}</div>}
                        </Card.Content>
                    </Card>
                    {product.attributes.length > 0 && <Card className="border border-slate-800 bg-slate-900/70" variant="secondary"><Card.Header className="border-b border-slate-800 p-5"><Card.Title>مشخصات محصول</Card.Title></Card.Header><Card.Content className="grid gap-3 p-5 sm:grid-cols-2">{product.attributes.map((attribute) => <div className="flex justify-between rounded-xl bg-slate-950/50 px-4 py-3" key={attribute.name}><span className="text-slate-400">{attribute.name}</span><strong>{attribute.value}</strong></div>)}</Card.Content></Card>}
                </section>
                <aside>
                    <Card className="sticky top-6 border border-slate-700 bg-slate-900 shadow-2xl" variant="secondary">
                        <Card.Content className="space-y-5 p-6">
                            {pricing.is_partner_price && <Chip color="success" variant="soft"><BadgeCheck size={15} /> قیمت ویژه همکار</Chip>}
                            <div>
                                {pricing.final_price !== pricing.regular_price && <div className="mb-1 text-sm text-slate-500 line-through">{money.format(pricing.regular_price)} تومان</div>}
                                <div className="flex items-end gap-2"><strong className="text-3xl font-black text-white">{money.format(pricing.final_price)}</strong><span className="pb-1 text-slate-300">تومان</span></div>
                            </div>
                            <div className="flex items-center gap-2 rounded-xl bg-emerald-500/10 p-3 text-sm text-emerald-300"><PackageCheck size={18} /> آماده ثبت سفارش</div>
                            <Button fullWidth size="lg" variant="primary"><Gamepad2 size={19} /> افزودن به سبد خرید</Button>
                        </Card.Content>
                    </Card>
                </aside>
            </div>
        </main>
    );
}
