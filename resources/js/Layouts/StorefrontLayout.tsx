import { router, usePage } from "@inertiajs/react";
import type { PropsWithChildren } from "react";

import StorefrontAuthOverlay from "../Components/Auth/StorefrontAuthOverlay";
import StorefrontFooter from "../Components/Storefront/StorefrontFooter";
import StorefrontNavigation from "../Components/Storefront/Navigation/StorefrontNavigation";
import { useStorefrontTheme } from "../Components/Storefront/Navigation/useStorefrontTheme";
import type { SharedPageProps } from "../types";

interface Props extends PropsWithChildren {
    announcement?: { enabled: boolean; text: string; url: string };
}

export default function StorefrontLayout({ children, announcement }: Props) {
    const { auth, storefront, impersonation } =
        usePage<SharedPageProps>().props;
    const { theme, toggleTheme } = useStorefrontTheme();

    return (
        <div
            className="storefront-theme min-h-screen w-full max-w-full overflow-x-clip bg-[var(--store-bg)] pb-24 text-[var(--store-text)] lg:pb-0"
            data-theme={theme}
            dir="rtl"
        >
            {impersonation?.active && (
                <div className="sticky top-0 z-[100] flex items-center justify-center gap-3 bg-amber-400 px-4 py-2 text-xs font-bold text-slate-950 shadow-lg">
                    در حال مشاهده سایت با حساب {auth.user?.name} هستید.
                    <button
                        className="rounded-lg bg-slate-950 px-3 py-1.5 text-white"
                        onClick={() => router.post("/impersonation/stop")}
                        type="button"
                    >
                        بازگشت به حساب مدیر
                    </button>
                </div>
            )}
            <StorefrontNavigation
                announcement={announcement}
                categories={storefront.categories}
                stories={storefront.stories}
                onToggleTheme={toggleTheme}
                theme={theme}
                user={auth.user}
                freshContentAt={storefront.fresh_content_at}
            />
            {children}
            <StorefrontFooter androidApp={storefront.android_app} />
            <StorefrontAuthOverlay />
        </div>
    );
}
