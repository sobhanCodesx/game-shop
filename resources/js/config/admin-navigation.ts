import {
    BadgePercent,
    Bot,
    Boxes,
    Building2,
    ClipboardList,
    Factory,
    FolderCode,
    Gamepad2,
    Gauge,
    Home,
    Images,
    LayoutGrid,
    LifeBuoy,
    ListChecks,
    ListVideo,
    MessageSquareText,
    Newspaper,
    PanelsTopLeft,
    RefreshCw,
    Rocket,
    Smartphone,
    Settings,
    ShoppingBag,
    Tags,
    TerminalSquare,
    Users,
    Video,
    type LucideIcon,
} from "lucide-react";

export interface NavigationLink {
    type: "link";
    label: string;
    href: string;
    icon: LucideIcon;
    badge?: string;
    exact?: boolean;
    excludeQuery?: Record<string, string>;
    superAdminOnly?: boolean;
}

export interface NavigationParent {
    type: "parent";
    key: string;
    label: string;
    icon: LucideIcon;
    children: NavigationLink[];
}

export type NavigationEntry = NavigationLink | NavigationParent;

const link = (
    label: string,
    href: string,
    icon: LucideIcon,
    options: Omit<NavigationLink, "type" | "label" | "href" | "icon"> = {},
): NavigationLink => ({ type: "link", label, href, icon, ...options });

export const adminNavigation: NavigationEntry[] = [
    link("داشبورد", "/admin", Gauge, { exact: true }),
    link("مشاهده سایت", "/", Home, { exact: true }),
    {
        type: "parent",
        key: "storefront",
        label: "فروشگاه و کاتالوگ",
        icon: ShoppingBag,
        children: [
            link("صفحه اصلی فروشگاه", "/admin/home", Home),
            link("محصولات", "/admin/products", ShoppingBag),
            link("انواع محصول", "/admin/product-types", Boxes),
            link("ویژگی‌های محصول", "/admin/attributes", Tags),
            link("دسته‌بندی‌ها", "/admin/categories", LayoutGrid),
            link("برندها", "/admin/brands", Building2),
            link("بازی‌ها", "/admin/games", Gamepad2),
            link("پلتفرم‌ها", "/admin/platforms", PanelsTopLeft),
        ],
    },
    {
        type: "parent",
        key: "commerce",
        label: "سفارش و تجارت",
        icon: ClipboardList,
        children: [
            link("سفارش‌ها", "/admin/orders", ClipboardList),
            link("درخواست‌های معاوضه", "/admin/tickets?type=exchange", RefreshCw),
            link("کدهای تخفیف", "/admin/coupons", BadgePercent),
        ],
    },
    {
        type: "parent",
        key: "content",
        label: "محتوا و رسانه",
        icon: Newspaper,
        children: [
            link("فید", "/admin/feed", Newspaper),
            link("استودیوهای بازی‌سازی", "/admin/studios", Factory),
            link("ویدیوها", "/admin/videos", Video),
            link("کالکشن‌های ویدیو", "/admin/video-playlists", ListVideo),
            link("ویدیوهای کوتاه", "/admin/shorts", Images),
        ],
    },
    {
        type: "parent",
        key: "people",
        label: "کاربران و پشتیبانی",
        icon: Users,
        children: [
            link("کاربران", "/admin/users", Users),
            link("تیکت‌های پشتیبانی", "/admin/tickets", LifeBuoy, {
                excludeQuery: { type: "exchange" },
            }),
        ],
    },
    {
        type: "parent",
        key: "system",
        label: "سیستم و تنظیمات",
        icon: Settings,
        children: [
            link("تنظیمات", "/admin/settings", Settings),
            link("پنل‌های پیامکی", "/admin/sms-providers", MessageSquareText),
            link("پترن‌های پیامک", "/admin/sms-patterns", ListChecks),
            link("تست پیامک", "/admin/sms-test", MessageSquareText),
            link("ربات تلگرام", "/admin/telegram-bot", Bot, { superAdminOnly: true }),
            link("به‌روزرسانی سیستم", "/admin/deployments", Rocket),
            link("ریلیز نسخه اندروید", "/admin/android-releases", Smartphone),
            link("نگهداری سیستم", "/admin/system-maintenance", TerminalSquare, {
                superAdminOnly: true,
            }),
            link("فایل منیجر", "/admin/file-manager", FolderCode, {
                superAdminOnly: true,
            }),
        ],
    },
];
