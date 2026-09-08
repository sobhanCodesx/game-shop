import { Link } from "@inertiajs/react";
import { ArrowRight } from "lucide-react";

import FeedItem from "../../Components/Storefront/Feed/FeedItem";
import Seo, { type SeoData } from "../../Components/Seo";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { FeedItemData } from "../../types";

export default function FeedShow({ seo, item }: { seo: SeoData; item: FeedItemData }) {
    return (
        <StorefrontLayout>
            <Seo seo={seo} />
            <main className="mx-auto w-full max-w-[720px] pb-12 pt-3 sm:px-4 lg:pt-7">
                <Link className="mx-4 mb-3 inline-flex min-h-10 items-center gap-2 rounded-full px-3 text-xs font-black text-[var(--store-muted)] transition hover:bg-[var(--store-surface)] hover:text-[var(--store-text)] sm:mx-0" href="/" preserveScroll>
                    <ArrowRight size={17} /> بازگشت به فید
                </Link>
                <FeedItem detail item={item} />
            </main>
        </StorefrontLayout>
    );
}
