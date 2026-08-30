import { Button } from "@heroui/react";
import { Moon, Sun } from "lucide-react";

import type { StorefrontTheme } from "./types";

export default function ThemeToggle({ theme, onToggle }: { theme: StorefrontTheme; onToggle: () => void }) {
    const dark = theme === "dark";
    return (
        <Button aria-label={dark ? "فعال‌کردن تم روشن" : "فعال‌کردن تم تاریک"} className="store-nav-icon" isIconOnly onPress={onToggle} variant="ghost">
            {dark ? <Sun size={19} /> : <Moon size={19} />}
        </Button>
    );
}
