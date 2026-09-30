import { Button, Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import { Plus } from "lucide-react";
import AdminLayout from "../../../../Layouts/AdminLayout";

const money = new Intl.NumberFormat("fa-IR");

export default function Index({ products }: { products: any }) {
    return (
        <AdminLayout
            title="محصولات دیجیتال"
            description="کاتالوگ مستقل اکانت‌های دیجیتال؛ ساده، سریع و جدا از کالای فیزیکی."
            actions={<Link href="/admin/digital-products/create"><Button variant="primary"><Plus size={17} />محصول دیجیتال جدید</Button></Link>}
        >
            <Head title="محصولات دیجیتال" />
            <div className="grid gap-4 xl:grid-cols-2">
                {products.data.map((product: any) => (
                    <Card key={product.id} variant="secondary">
                        <Card.Content className="p-5">
                            <div className="flex gap-4">
                                {product.game?.cover && <img className="h-24 w-20 rounded-xl object-cover" src={product.game.cover} alt="" />}
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h2 className="font-black">{product.title}</h2>
                                        <Chip size="sm" variant="soft">{product.status}</Chip>
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">{product.seller?.name} · {product.platform?.name}</p>
                                    <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        {product.offers.map((offer: any) => (
                                            <div className="rounded-xl bg-slate-950/50 p-2 text-xs" key={offer.id}>
                                                <strong className="block">{offer.label}</strong>
                                                <span className="text-emerald-400">{money.format(offer.price)}</span>
                                                <span className="mt-1 block text-slate-500">موجودی {offer.stock}</span>
                                            </div>
                                        ))}
                                    </div>
                                    <Link className="mt-4 inline-block text-sm font-bold text-indigo-400" href={`/admin/digital-products/${product.id}/edit`}>ویرایش</Link>
                                </div>
                            </div>
                        </Card.Content>
                    </Card>
                ))}
            </div>
        </AdminLayout>
    );
}
