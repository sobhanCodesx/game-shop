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
    permission?: string;
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
    link("داشبورد", "/admin", Gauge, { exact: true, permission: "dashboard.view" }),
    link("مشاهده سایت", "/", Home, { exact: true }),
    {
        type: "parent",
        key: "storefront",
        label: "فروشگاه و کاتالوگ",
        icon: ShoppingBag,
        children: [
            link("صفحه اصلی فروشگاه", "/admin/home", Home, { permission: "storefront.manage" }),
            link("محصولات", "/admin/products", ShoppingBag, { permission: "catalog.manage" }),
            link("انواع محصول", "/admin/product-types", Boxes, { permission: "catalog.manage" }),
            link("ویژگی‌های محصول", "/admin/attributes", Tags, { permission: "catalog.manage" }),
            link("دسته‌بندی‌ها", "/admin/categories", LayoutGrid, { permission: "catalog.manage" }),
            link("برندها", "/admin/brands", Building2, { permission: "catalog.manage" }),
            link("بازی‌ها", "/admin/games", Gamepad2, { permission: "catalog.manage" }),
            link("پلتفرم‌ها", "/admin/platforms", PanelsTopLeft, { permission: "catalog.manage" }),
        ],
    },
    {
        type: "parent",
        key: "commerce",
        label: "سفارش و تجارت",
        icon: ClipboardList,
        children: [
            link("سفارش‌ها", "/admin/orders", ClipboardList, { permission: "orders.manage" }),
            link("درخواست‌های معاوضه", "/admin/tickets?type=exchange", RefreshCw, { permission: "support.manage" }),
            link("کدهای تخفیف", "/admin/coupons", BadgePercent, { permission: "coupons.manage" }),
        ],
    },
    {
        type: "parent",
        key: "content",
        label: "محتوا و رسانه",
        icon: Newspaper,
        children: [
            link("فید", "/admin/feed", Newspaper, { permission: "content.manage" }),
            link("استودیوهای بازی‌سازی", "/admin/studios", Factory, { permission: "content.manage" }),
            link("ویدیوها", "/admin/videos", Video, { permission: "content.manage" }),
            link("کالکشن‌های ویدیو", "/admin/video-playlists", ListVideo, { permission: "content.manage" }),
            link("ویدیوهای کوتاه", "/admin/shorts", Images, { permission: "content.manage" }),
        ],
    },
    {
        type: "parent",
        key: "people",
        label: "کاربران و پشتیبانی",
        icon: Users,
        children: [
            link("کاربران", "/admin/users", Users, { permission: "users.manage" }),
            link("تیکت‌های پشتیبانی", "/admin/tickets", LifeBuoy, {
                excludeQuery: { type: "exchange" },
                permission: "support.manage",
            }),
        ],
    },
    {
        type: "parent",
        key: "system",
        label: "سیستم و تنظیمات",
        icon: Settings,
        children: [
            link("تنظیمات", "/admin/settings", Settings, { permission: "settings.manage" }),
            link("Nexus AI", "/admin/nexus-ai", Bot, { permission: "settings.manage" }),
            link("پنل‌های پیامکی", "/admin/sms-providers", MessageSquareText, { permission: "sms.manage" }),
            link("پترن‌های پیامک", "/admin/sms-patterns", ListChecks, { permission: "sms.manage" }),
            link("تست پیامک", "/admin/sms-test", MessageSquareText, { permission: "sms.manage" }),
            link("ربات تلگرام", "/admin/telegram-bot", Bot, {
                superAdminOnly: true,
                permission: "system.maintenance",
            }),
            link("به‌روزرسانی سیستم", "/admin/deployments", Rocket, { permission: "system.deployments" }),
            link("ریلیز نسخه اندروید", "/admin/android-releases", Smartphone, { permission: "system.deployments" }),
            link("نگهداری سیستم", "/admin/system-maintenance", TerminalSquare, {
                superAdminOnly: true,
                permission: "system.maintenance",
            }),
            link("فایل منیجر", "/admin/file-manager", FolderCode, {
                superAdminOnly: true,
                permission: "system.files.manage",
            }),
        ],
    },
];
