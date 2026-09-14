import { useEffect, useState } from "react";

import type { StorefrontTheme } from "./types";

const STORAGE_KEY = "nexus-play-storefront-theme";

const initialTheme = (): StorefrontTheme => {
    if (typeof window === "undefined") return "dark";
    const applied = document.documentElement.dataset.storefrontTheme;
    if (applied === "light" || applied === "dark") return applied;
    let saved;
    try {
        saved = window.localStorage.getItem(STORAGE_KEY);
    } catch {
        // Storage may be unavailable in restricted browser contexts.
    }
    if (saved === "light" || saved === "dark") return saved;
    return window.matchMedia("(prefers-color-scheme: light)").matches
        ? "light"
        : "dark";
};

export function useStorefrontTheme() {
    // Match the server render; the head script already applies the visual theme.
    const [theme, setTheme] = useState<StorefrontTheme>("dark");

    useEffect(() => {
        setTheme(initialTheme());
    }, []);

    const toggleTheme = () => {
        const next = initialTheme() === "dark" ? "light" : "dark";
        document.documentElement.dataset.storefrontTheme = next;
        setTheme(next);
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Keep switching themes usable even when storage is blocked.
        }
    };

    return {
        theme,
        toggleTheme,
    };
}
