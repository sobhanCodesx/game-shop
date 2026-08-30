import { useEffect, useState } from "react";

import type { StorefrontTheme } from "./types";

const STORAGE_KEY = "nexus-play-storefront-theme";

const initialTheme = (): StorefrontTheme => {
    if (typeof window === "undefined") return "dark";
    const saved = window.localStorage.getItem(STORAGE_KEY);
    if (saved === "light" || saved === "dark") return saved;
    return window.matchMedia("(prefers-color-scheme: light)").matches
        ? "light"
        : "dark";
};

export function useStorefrontTheme() {
    const [theme, setTheme] = useState<StorefrontTheme>(initialTheme);

    useEffect(() => {
        window.localStorage.setItem(STORAGE_KEY, theme);
    }, [theme]);

    return {
        theme,
        toggleTheme: () =>
            setTheme((current) => (current === "dark" ? "light" : "dark")),
    };
}
