import { Link } from "@inertiajs/react";
import { useCallback, useEffect, useState } from "react";

import DesktopNavigation from "./DesktopNavigation";
import MobileNavigation from "./MobileNavigation";
import StorefrontPanels, { type StorefrontPanel } from "./StorefrontPanels";
import StorefrontStories from "./StorefrontStories";
import type { StorefrontNavigationProps, StorefrontTheme } from "./types";

interface Props extends StorefrontNavigationProps {
    theme: StorefrontTheme;
    onToggleTheme: () => void;
}

const safeUrl = (url: string) =>
    /^https?:\/\//.test(url) || url.startsWith("/") ? url : "#";

export default function StorefrontNavigation({
    announcement,
    categories,
    stories = [],
    user,
    theme,
    onToggleTheme,
    freshContentAt,
}: Props) {
    const [panel, setPanel] = useState<StorefrontPanel>(null);
    const [hasFreshContent, setHasFreshContent] = useState(false);
    const closePanel = useCallback(() => setPanel(null), []);
    useEffect(() => {
        const openSearch = (event: KeyboardEvent) => {
            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === "k"
            ) {
                event.preventDefault();
                setPanel("search");
            }
        };
        window.addEventListener("keydown", openSearch);
        return () => window.removeEventListener("keydown", openSearch);
    }, []);
    useEffect(() => {
        const refresh = () => {
            const seenAt = Number(
                localStorage.getItem("nexus:fresh-content-seen-at") ?? 0,
            );
            setHasFreshContent(
                Boolean(freshContentAt && Date.parse(freshContentAt) > seenAt),
            );
        };
        refresh();
        window.addEventListener("fresh-content-seen", refresh);
        return () => window.removeEventListener("fresh-content-seen", refresh);
    }, [freshContentAt]);

    return (
        <>
            {announcement?.enabled && (
                <Link
                    className="store-announcement block px-4 py-2 text-center text-[11px] font-bold text-white"
                    href={safeUrl(announcement.url)}
                >
                    {announcement.text}
                </Link>
            )}
            <div className="sticky top-0 z-40">
                <DesktopNavigation
                    categories={categories}
                    onOpenAccount={() => setPanel("account")}
                    onOpenSearch={() => setPanel("search")}
                    onToggleTheme={onToggleTheme}
                    theme={theme}
                    user={user}
                    hasFreshContent={hasFreshContent}
                />
                <MobileNavigation
                    activePanel={panel}
                    onOpenPanel={setPanel}
                    onToggleTheme={onToggleTheme}
                    theme={theme}
                    hasFreshContent={hasFreshContent}
                />
                <StorefrontStories stories={stories} />
            </div>
            <StorefrontPanels
                categories={categories}
                onClose={closePanel}
                panel={panel}
                user={user}
            />
        </>
    );
}
