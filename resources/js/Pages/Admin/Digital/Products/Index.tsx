import { Button, Card, Chip } from "@heroui/react";
import { Head, Link } from "@inertiajs/react";
import {
    Edit3,
    Gamepad2,
    ImageOff,
    PackageCheck,
    Plus,
    Star,
    UserRound,
} from "lucide-react";

import AdminLayout from "../../../../Layouts/AdminLayout";

const money = new Intl.NumberFormat("fa-IR");
const number = new Intl.NumberFormat("fa-IR");

const statusMeta: Record<
    string,
    { label: string; color: "success" | "warning" | "default" }
> = {
    published: { label: "منتشرشده", color: "success" },
    draft: { label: "پیش‌نویس", color: "warning" },
    hidden: { label: "مخفی", color: "default" },
};

const paginationLabel = (label: string) =>
    label
        .replace("&laquo; Previous", "قبلی")
        .replace("Previous", "قبلی")
        .replace("Next &raquo;", "بعدی")
        .replace("Next", "بعدی");

export default function Index({ products }: { products: any }) {
    return (
        <AdminLayout
            title="محصولات دیجیتال"
            description="مدیریت مستقل اکانت‌های دیجیتال، ظرفیت‌ها، موجودی و فروشنده."
            actions={
                <Link href="/admin/digital-products/create">
                    <Button variant="primary">
                        <Plus size={17} />
                        محصول دیجیتال جدید
                    </Button>
                </Link>
            }
        >
            <Head title="محصولات دیجیتال" />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/30 px-4 py-3">
                <div>
                    <p className="text-sm font-black text-white">
                        {number.format(products.total ?? 0)} محصول دیجیتال
                    </p>
                    <p className="mt-1 text-xs text-slate-500">
                        صفحه {number.format(products.current_page ?? 1)} از{" "}
                        {number.format(products.last_page ?? 1)}
                    </p>
                </div>

                {(products.total ?? 0) > 0 && (
                    <p className="text-xs text-slate-500">
                        نمایش {number.format(products.from ?? 0)} تا{" "}
                        {number.format(products.to ?? 0)}
                    </p>
                )}
            </div>

            {products.data?.length ? (
                <div className="grid gap-5 md:grid-cols-2 2xl:grid-cols-3">
                    {products.data.map((product: any) => {
                        const status =
                            statusMeta[product.status] ?? statusMeta.hidden;
                        const activeOffers = product.offers.filter(
                            (offer: any) => offer.status === "active",
                        );
                        const availableStock = activeOffers.reduce(
                            (sum: number, offer: any) =>
                                sum + Number(offer.available_stock ?? 0),
                            0,
                        );

                        return (
                            <Card
                                className="overflow-hidden border border-slate-800"
                                key={product.id}
                                variant="secondary"
                            >
                                <Card.Content className="p-0">
                                    <div className="relative aspect-[16/10] overflow-hidden bg-slate-950">
                                        {product.cover_url ? (
                                            <img
                                                alt={product.title}
                                                className="size-full object-cover"
                                                loading="lazy"
                                                src={product.cover_url}
                                            />
                                        ) : (
                                            <div className="grid size-full place-items-center text-slate-600">
                                                <div className="text-center">
                                                    <ImageOff
                                                        className="mx-auto"
                                                        size={34}
                                                    />
                                                    <span className="mt-2 block text-xs">
                                                        بدون کاور
                                                    </span>
                                                </div>
                                            </div>
                                        )}

                                        <div className="absolute inset-x-0 bottom-0 h-24 bg-gradient-to-t from-slate-950 via-slate-950/70 to-transparent" />

                                        <div className="absolute inset-x-4 bottom-3 flex items-end justify-between gap-3">
                                            <Chip
                                                color={status.color}
                                                size="sm"
                                                variant="solid"
                                            >
                                                {status.label}
                                            </Chip>

                                            {product.featured && (
                                                <span className="flex items-center gap-1 rounded-full bg-amber-400/15 px-2 py-1 text-[11px] font-bold text-amber-300 backdrop-blur">
                                                    <Star
                                                        fill="currentColor"
                                                        size={12}
                                                    />
                                                    ویژه
                                                </span>
                                            )}
                                        </div>
                                    </div>

                                    <div className="p-5">
                                        <h2 className="line-clamp-2 min-h-12 text-lg font-black leading-6 text-white">
                                            {product.title}
                                        </h2>

                                        <div className="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                                            <span className="flex items-center gap-1.5 rounded-lg bg-slate-950/55 px-2.5 py-1.5">
                                                <Gamepad2 size={13} />
                                                {product.platform?.name ??
                                                    "بدون پلتفرم"}
                                            </span>
                                            <span className="flex items-center gap-1.5 rounded-lg bg-slate-950/55 px-2.5 py-1.5">
                                                <UserRound size={13} />
                                                {product.seller?.name ??
                                                    "بدون فروشنده"}
                                            </span>
                                        </div>

                                        {product.game?.name && (
                                            <p className="mt-3 truncate text-xs font-bold text-slate-500">
                                                بازی: {product.game.name}
                                            </p>
                                        )}

                                        <div className="mt-4 grid grid-cols-2 gap-2">
                                            {product.offers.map((offer: any) => (
                                                <div
                                                    className={`rounded-xl border p-2.5 text-xs ${
                                                        offer.status === "active"
                                                            ? "border-slate-800 bg-slate-950/55"
                                                            : "border-slate-800/60 bg-slate-950/25 opacity-55"
                                                    }`}
                                                    key={offer.id}
                                                >
                                                    <div className="flex items-center justify-between gap-2">
                                                        <strong className="text-slate-200">
                                                            {offer.label}
                                                        </strong>
                                                        <span
                                                            className={`size-1.5 rounded-full ${
                                                                offer.status ===
                                                                "active"
                                                                    ? "bg-emerald-400"
                                                                    : "bg-slate-600"
                                                            }`}
                                                        />
                                                    </div>

                                                    <p className="mt-1 font-black text-emerald-400">
                                                        {offer.status ===
                                                            "active" &&
                                                        offer.price > 0
                                                            ? `${money.format(
                                                                  offer.price,
                                                              )} تومان`
                                                            : "غیرفعال"}
                                                    </p>

                                                    <span className="mt-1 block text-[11px] text-slate-500">
                                                        موجودی قابل فروش:{" "}
                                                        {number.format(
                                                            offer.available_stock ??
                                                                0,
                                                        )}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>

                                        <div className="mt-4 flex items-center justify-between gap-3 border-t border-slate-800 pt-4">
                                            <div className="flex items-center gap-2 text-xs text-slate-400">
                                                <PackageCheck size={15} />
                                                <span>
                                                    {number.format(
                                                        availableStock,
                                                    )}{" "}
                                                    موجودی فعال
                                                </span>
                                            </div>

                                            <Link
                                                className="inline-flex min-h-10 items-center gap-2 rounded-xl bg-indigo-500/15 px-3 text-sm font-black text-indigo-300 transition hover:bg-indigo-500/25"
                                                href={`/admin/digital-products/${product.id}/edit`}
                                            >
                                                <Edit3 size={15} />
                                                ویرایش
                                            </Link>
                                        </div>
                                    </div>
                                </Card.Content>
                            </Card>
                        );
                    })}
                </div>
            ) : (
                <div className="rounded-3xl border border-dashed border-slate-700 bg-slate-950/25 p-12 text-center">
                    <Gamepad2 className="mx-auto text-slate-600" size={38} />
                    <h2 className="mt-4 font-black text-white">
                        هنوز محصول دیجیتالی وجود ندارد
                    </h2>
                    <p className="mt-2 text-sm text-slate-500">
                        اولین محصول را بساز تا اینجا نمایش داده شود.
                    </p>
                </div>
            )}

            {(products.links?.length ?? 0) > 3 && (
                <nav
                    aria-label="صفحه‌بندی محصولات دیجیتال"
                    className="mt-8 flex flex-wrap items-center justify-center gap-2"
                >
                    {products.links.map((link: any, index: number) =>
                        link.url ? (
                            <Link
                                className={`grid min-h-10 min-w-10 place-items-center rounded-xl border px-3 text-sm font-bold transition ${
                                    link.active
                                        ? "border-indigo-500 bg-indigo-600 text-white"
                                        : "border-slate-800 bg-slate-950/35 text-slate-400 hover:border-indigo-500/60 hover:text-white"
                                }`}
                                href={link.url}
                                key={`${link.label}-${index}`}
                                preserveScroll
                            >
                                {paginationLabel(link.label)}
                            </Link>
                        ) : (
                            <span
                                className="grid min-h-10 min-w-10 place-items-center rounded-xl border border-slate-900 px-3 text-sm text-slate-600"
                                key={`${link.label}-${index}`}
                            >
                                {paginationLabel(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            )}
        </AdminLayout>
    );
}
