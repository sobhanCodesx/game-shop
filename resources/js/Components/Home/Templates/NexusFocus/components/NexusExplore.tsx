import { Link } from "@inertiajs/react";
import { Factory, Gamepad2 } from "lucide-react";

import type { NexusExploreItem } from "../types";
import SectionHeader from "./SectionHeader";

export default function NexusExplore({ items }: { items: NexusExploreItem[] }) {
    if (!items.length) return null;

    return (
        <section className="mx-auto max-w-[1360px] px-3 pb-4 pt-14 sm:px-5 sm:pb-6 sm:pt-20">
            <SectionHeader
                description="اگر هنوز دنبال چیز تازه‌ای هستی، از دسته‌بندی‌ها و استودیوها ادامه بده."
                eyebrow="EXPLORE"
                href="/discover"
                title="بیشتر در دنیای بازی بگرد"
            />

            <div className="grid grid-cols-2 gap-2.5 sm:gap-3 lg:grid-cols-12">
                {items.map((item, index) => {
                    const mobileWide = index === 0 || index === 4;
                    const desktopSpan =
                        [
                            "lg:col-span-5",
                            "lg:col-span-4",
                            "lg:col-span-3",
                            "lg:col-span-3",
                            "lg:col-span-4",
                            "lg:col-span-5",
                        ][index] ?? "lg:col-span-3";

                    return (
                        <Link
                            className={`group relative min-h-[170px] overflow-hidden rounded-[20px] border border-[var(--store-border)] bg-[var(--store-surface)] focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-400 sm:min-h-[210px] ${
                                mobileWide ? "col-span-2" : ""
                            } ${desktopSpan}`}
                            href={item.href}
                            key={item.key}
                        >
                            <span className="absolute inset-0 grid place-items-center bg-[radial-gradient(circle_at_top,#312e81,#0f172a_70%)] text-indigo-300/70">
                                {item.kind === "studio" ? (
                                    <Factory size={42} />
                                ) : (
                                    <Gamepad2 size={42} />
                                )}
                            </span>
                            {item.image && (
                                <img
                                    alt=""
                                    className="absolute inset-0 size-full object-cover transition duration-300 group-hover:scale-[1.025]"
                                    decoding="async"
                                    loading="lazy"
                                    onError={(event) => {
                                        event.currentTarget.style.display =
                                            "none";
                                    }}
                                    src={item.image}
                                />
                            )}
                            <span className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/25 to-transparent" />
                            <span className="absolute inset-x-0 bottom-0 p-4 text-white sm:p-5">
                                <small className="text-[9px] font-black tracking-[.12em] text-indigo-200/80">
                                    {item.kind === "studio"
                                        ? "استودیو"
                                        : "دسته‌بندی"}
                                </small>
                                <strong className="mt-1 block truncate text-base font-black sm:text-xl">
                                    {item.title}
                                </strong>
                                <small className="mt-1 block text-[10px] text-white/50 sm:text-xs">
                                    {item.meta}
                                </small>
                            </span>
                        </Link>
                    );
                })}
            </div>
        </section>
    );
}
