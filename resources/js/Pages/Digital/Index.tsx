import { Head, Link } from "@inertiajs/react";
import { Gamepad2, ShieldCheck, Zap } from "lucide-react";
import StorefrontLayout from "../../Layouts/StorefrontLayout";

const money = new Intl.NumberFormat("fa-IR");

export default function Index({ products }: { products: any }) {
    return (
        <StorefrontLayout>
            <Head title="بازی‌های دیجیتال" />
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
                <section className="mb-8 overflow-hidden rounded-[32px] border border-indigo-500/20 bg-[radial-gradient(circle_at_10%_0%,rgba(99,102,241,.22),transparent_40%),var(--store-surface)] p-6 sm:p-9">
                    <div className="flex items-start gap-4">
                        <span className="grid size-14 shrink-0 place-items-center rounded-2xl bg-indigo-500 text-white">
                            <Gamepad2 size={28} />
                        </span>
                        <div>
                            <p className="text-xs font-black tracking-wider text-indigo-500">PLAYNEXUS DIGITAL</p>
                            <h1 className="mt-2 text-3xl font-black sm:text-4xl">بازی دیجیتال، بدون پیچیدگی</h1>
                            <p className="mt-3 max-w-2xl text-sm leading-7 text-[var(--store-muted)]">
                                بازی را انتخاب کن، ظرفیت مناسب را بردار و ادامه خرید و تحویل را مستقیم داخل گفت‌وگوی اختصاصی سفارش انجام بده.
                            </p>
                        </div>
                    </div>
                    <div className="mt-6 flex flex-wrap gap-3 text-xs font-bold text-[var(--store-muted)]">
                        <span className="rounded-full bg-indigo-500/10 px-3 py-2"><Zap className="ml-1 inline" size={14} />ثبت سفارش سریع</span>
                        <span className="rounded-full bg-emerald-500/10 px-3 py-2"><ShieldCheck className="ml-1 inline" size={14} />تحویل داخل حساب PlayNexus</span>
                    </div>
                </section>

                {products.data.length ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {products.data.map((product: any) => {
                            const available = product.offers.filter((offer: any) => offer.available);
                            const min = available.length ? Math.min(...available.map((offer: any) => offer.price)) : null;
                            return (
                                <Link
                                    className="group overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] transition hover:-translate-y-1 hover:border-indigo-500/50"
                                    href={`/digital/${product.slug}`}
                                    key={product.id}
                                >
                                    <div className="aspect-[16/10] overflow-hidden bg-[var(--store-bg)]">
                                        {product.cover_url && (
                                            <img className="size-full object-cover transition duration-500 group-hover:scale-105" src={product.cover_url} alt={product.title} />
                                        )}
                                    </div>
                                    <div className="p-5">
                                        <div className="flex items-center justify-between gap-3">
                                            <h2 className="font-black">{product.title}</h2>
                                            {product.platform?.name && <span className="rounded-lg bg-indigo-500/10 px-2 py-1 text-[10px] font-black text-indigo-500">{product.platform.name}</span>}
                                        </div>
                                        <p className="mt-3 text-xs text-[var(--store-muted)]">
                                            {available.length.toLocaleString("fa-IR")} گزینه موجود
                                        </p>
                                        {min !== null && (
                                            <p className="mt-2 text-sm">
                                                از <strong className="text-lg text-emerald-500">{money.format(min)} تومان</strong>
                                            </p>
                                        )}
                                    </div>
                                </Link>
                            );
                        })}
                    </div>
                ) : (
                    <div className="rounded-3xl border border-dashed border-[var(--store-border)] p-12 text-center text-[var(--store-muted)]">
                        هنوز بازی دیجیتالی منتشر نشده است.
                    </div>
                )}
            </main>
        </StorefrontLayout>
    );
}
