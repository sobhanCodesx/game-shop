import { usePage } from "@inertiajs/react";
import type { PropsWithChildren } from "react";

import StorefrontNavigation from "../Components/Storefront/Navigation/StorefrontNavigation";
import { useStorefrontTheme } from "../Components/Storefront/Navigation/useStorefrontTheme";
import type { SharedPageProps } from "../types";

interface Props extends PropsWithChildren {
    announcement?: { enabled: boolean; text: string; url: string };
}

export default function StorefrontLayout({ children, announcement }: Props) {
    const { auth, storefront } = usePage<SharedPageProps>().props;
    const { theme, toggleTheme } = useStorefrontTheme();

    return (
        <div
            className="storefront-theme min-h-screen bg-[var(--store-bg)] pb-24 text-[var(--store-text)] lg:pb-0"
            data-theme={theme}
            dir="rtl"
        >
            <StorefrontNavigation
                announcement={announcement}
                categories={storefront.categories}
                stories={storefront.stories}
                onToggleTheme={toggleTheme}
                theme={theme}
                user={auth.user}
            />
            {children}
        </div>
    );
}
