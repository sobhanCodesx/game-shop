import {
    Activity,
    BadgePercent,
    Boxes,
    BriefcaseBusiness,
    Building2,
    CircleDollarSign,
    ClipboardList,
    FileText,
    Gamepad2,
    Gauge,
    Images,
    Home,
    LayoutGrid,
    LifeBuoy,
    MessageSquareText,
    PackageSearch,
    PanelsTopLeft,
    ReceiptText,
    Settings,
    ShieldCheck,
    ShoppingBag,
    Star,
    Tags,
    Users,
    Video,
    WalletCards,
    Warehouse,
    type LucideIcon,
} from "lucide-react";

export interface NavigationItem {
    label: string;
    href: string;
    icon: LucideIcon;
    badge?: string;
}

export interface NavigationGroup {
    label: string;
    items: NavigationItem[];
}

export const adminNavigation: NavigationGroup[] = [
    {
        label: "نمای کلی",
        items: [{ label: "داشبورد", href: "/admin", icon: Gauge }],
    },
    {
        label: "فروشگاه",
        items: [
            { label: "صفحه اصلی", href: "/admin/home", icon: Home },
            { label: "محصولات", href: "/admin/products", icon: ShoppingBag },
            {
                label: "دسته‌بندی‌ها",
                href: "/admin/categories",
                icon: LayoutGrid,
            },
            { label: "برندها", href: "/admin/brands", icon: Building2 },
            { label: "بازی‌ها", href: "/admin/games", icon: Gamepad2 },
            {
                label: "پلتفرم‌ها",
                href: "/admin/platforms",
                icon: PanelsTopLeft,
            },
            { label: "انبار", href: "/admin/inventory", icon: Warehouse },
        ],
    },
    {
        label: "تجارت",
        items: [
            { label: "سفارش‌ها", href: "/admin/orders", icon: ClipboardList },
            {
                label: "پرداخت‌ها",
                href: "/admin/payments",
                icon: CircleDollarSign,
            },
            {
                label: "کدهای تخفیف",
                href: "/admin/coupons",
                icon: BadgePercent,
            },
            { label: "نقد و بررسی", href: "/admin/reviews", icon: Star },
            { label: "معاوضه", href: "/admin/trades", icon: Boxes },
        ],
    },
    {
        label: "اجتماعی و محتوا",
        items: [
            {
                label: "کریتورها",
                href: "/admin/creators",
                icon: BriefcaseBusiness,
            },
            { label: "پست‌ها", href: "/admin/posts", icon: FileText },
            { label: "ویدیوها", href: "/admin/videos", icon: Video },
            { label: "ویدیوهای کوتاه", href: "/admin/shorts", icon: Images },
            {
                label: "نظرات",
                href: "/admin/comments",
                icon: MessageSquareText,
            },
        ],
    },
    {
        label: "مدیریت و نظارت",
        items: [
            { label: "کاربران", href: "/admin/users", icon: Users },
            { label: "گزارش‌ها", href: "/admin/reports", icon: ReceiptText },
            {
                label: "نظارت محتوا",
                href: "/admin/moderation",
                icon: ShieldCheck,
            },
            { label: "اعلان‌ها", href: "/admin/notifications", icon: Activity },
            { label: "پشتیبانی", href: "/admin/support", icon: LifeBuoy },
        ],
    },
    {
        label: "سیستم",
        items: [
            { label: "بنرها", href: "/admin/banners", icon: PackageSearch },
            { label: "صفحات", href: "/admin/pages", icon: Tags },
            { label: "تنظیمات", href: "/admin/settings", icon: Settings },
            {
                label: "گزارش مدیران",
                href: "/admin/audit-logs",
                icon: WalletCards,
            },
        ],
    },
];
