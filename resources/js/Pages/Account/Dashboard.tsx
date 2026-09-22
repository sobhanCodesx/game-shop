import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import {
    BellRing,
    Camera,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    Home,
    KeyRound,
    LifeBuoy,
    Mail,
    MapPin,
    PackageCheck,
    PackageOpen,
    ReceiptText,
    Rss,
    Send,
    Copy,
    Link2Off,
    ShoppingBag,
    Truck,
    Pencil,
    Plus,
    ShieldCheck,
    Smartphone,
    WalletCards,
    Trash2,
    UserRound,
} from "lucide-react";
import {
    lazy,
    Suspense,
    type FormEvent,
    useEffect,
    useMemo,
    useRef,
    useState,
} from "react";

const PersianDatePicker = lazy(
    () => import("../../Components/Admin/Form/PersianDatePicker"),
);
import Pagination from "../../Components/Storefront/Pagination";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { PaginationLink, SharedPageProps } from "../../types";

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    birth_date: string | null;
    avatar_url: string | null;
    has_password: boolean;
    phone_verified: boolean;
};
type Address = {
    id: number;
    title: string;
    recipient_name: string;
    phone: string;
    province: string;
    city: string;
    postal_code: string | null;
    address_line: string;
    plaque: string | null;
    unit: string | null;
    is_default: boolean;
};
type Tab =
    | "overview"
    | "orders"
    | "tracking"
    | "content-notifications"
    | "profile"
    | "addresses"
    | "security";
type ContentNotificationPreferences = {
    sms_enabled: boolean;
    email_enabled: boolean;
    feed_enabled: boolean;
    telegram_enabled: boolean;
};
type TelegramIntegration = {
    available: boolean;
    connected: boolean;
    bot_username: string | null;
    linked_at: string | null;
    phone_verification_available: boolean;
};
type CurrentOrder = {
    id: number;
    number: string;
    status: string;
    grand_total: number;
    created_at: string;
    updated_at: string;
    items: { id: number; title: string; quantity: number }[];
};
type OrderSummary = {
    id: number;
    number: string;
    status: string;
    grand_total: number;
    cashback_amount: number;
    created_at: string;
};
type PaginatedOrders = {
    data: OrderSummary[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    total: number;
};
const field =
    "h-12 w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 text-sm outline-none transition focus:border-indigo-500";

export default function Dashboard({
    profile,
    addresses,
    profileCompletion,
    walletBalance,
    orders,
    orderStatusCounts,
    filters,
    accountNotifications,
    currentOrder,
    contentNotificationPreferences,
    telegramIntegration,
}: {
    profile: Profile;
    addresses: Address[];
    profileCompletion: number;
    walletBalance: number;
    orders: PaginatedOrders;
    orderStatusCounts: Record<string, number>;
    filters: { status: string | null; tab: string };
    accountNotifications: {
        id: string;
        title: string;
        message: string;
        read_at: string | null;
    }[];
    currentOrder: CurrentOrder | null;
    contentNotificationPreferences: ContentNotificationPreferences;
    telegramIntegration: TelegramIntegration;
}) {
    const { flash } = usePage<SharedPageProps>().props;
    const requestedTab = filters.tab as Tab;
    const [tab, setTab] = useState<Tab>(
        [
            "overview",
            "orders",
            "tracking",
            "content-notifications",
            "profile",
            "addresses",
            "security",
        ].includes(requestedTab)
            ? requestedTab
            : "overview",
    );
    const [desktopSidebarCollapsed, setDesktopSidebarCollapsed] =
        useState(false);
    const tabs: [Tab, string, typeof Home][] = [
        ["overview", "نمای کلی", Home],
        ["orders", "سفارش‌های من", ShoppingBag],
        ["tracking", "پیگیری سفارش جاری", Truck],
        ["content-notifications", "اطلاع‌رسانی محتوا", BellRing],
        ["profile", "اطلاعات حساب", UserRound],
        ["addresses", "آدرس‌ها", MapPin],
        ["security", "امنیت", KeyRound],
    ];
    return (
        <StorefrontLayout>
            <Head title="حساب کاربری" />
            <main className="mx-auto max-w-7xl px-3 py-4 sm:px-6 sm:py-8 lg:py-12">
                <section className="relative overflow-hidden rounded-[26px] border border-[var(--store-border)] bg-[var(--store-surface)] p-4 sm:rounded-[32px] sm:p-8">
                    <div className="absolute -left-20 -top-20 size-64 rounded-full bg-indigo-500/10 blur-3xl" />
                    <div className="relative grid grid-cols-[auto_1fr] items-center gap-4 sm:flex sm:gap-6">
                        <Avatar profile={profile} />
                        <div className="flex-1">
                            <p className="text-[11px] font-bold text-indigo-500 sm:text-sm">
                                باشگاه گیمرهای NEXUS
                            </p>
                            <h1 className="mt-1 text-lg font-black sm:mt-2 sm:text-3xl">
                                سلام {profile.name} 👋
                            </h1>
                            <p className="mt-1 text-xs leading-6 text-[var(--store-muted)] sm:mt-2 sm:text-sm">
                                اطلاعات حسابت، آدرس‌های ارسال و امنیت را از
                                اینجا مدیریت کن.
                            </p>
                        </div>
                        <div className="col-span-2 min-w-48 rounded-2xl bg-[var(--store-bg)] p-4 sm:col-auto">
                            <p className="mb-3 flex items-center gap-2 text-sm font-black text-emerald-500">
                                <WalletCards size={18} /> کیف پول:{" "}
                                {walletBalance.toLocaleString("fa-IR")} تومان
                            </p>
                            <div className="flex justify-between text-xs font-bold">
                                <span>تکمیل پروفایل</span>
                                <span>
                                    {profileCompletion.toLocaleString("fa-IR")}٪
                                </span>
                            </div>
                            <div className="mt-3 h-2 overflow-hidden rounded-full bg-[var(--store-border)]">
                                <div
                                    className="h-full rounded-full bg-gradient-to-l from-indigo-500 to-fuchsia-500"
                                    style={{ width: `${profileCompletion}%` }}
                                />
                            </div>
                        </div>
                    </div>
                </section>
                {flash?.success && (
                    <p className="mt-5 rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm font-bold text-emerald-600">
                        {flash.success}
                    </p>
                )}
                <div
                    className={`relative mt-4 grid grid-cols-1 gap-3 sm:mt-6 sm:gap-4 lg:gap-6 ${desktopSidebarCollapsed ? "lg:grid-cols-[76px_minmax(0,1fr)]" : "lg:grid-cols-[240px_minmax(0,1fr)]"}`}
                >
                    <nav
                        aria-label="بخش‌های حساب کاربری"
                        className="sticky top-[72px] z-30 -mx-1 overflow-x-auto rounded-2xl border border-[var(--store-border)] bg-[color-mix(in_srgb,var(--store-surface)_94%,transparent)] p-2 shadow-lg shadow-black/5 backdrop-blur-xl lg:top-24 lg:mx-0 lg:self-start lg:overflow-visible lg:rounded-3xl lg:p-3 lg:shadow-none"
                    >
                        <div
                            className={`mb-2 hidden items-center lg:flex ${desktopSidebarCollapsed ? "justify-center" : "justify-between px-2"}`}
                        >
                            <strong
                                className={`text-xs text-[var(--store-text)] ${desktopSidebarCollapsed ? "hidden" : "block"}`}
                            >
                                منوی حساب
                            </strong>
                            <button
                                aria-label={
                                    desktopSidebarCollapsed
                                        ? "باز کردن سایدبار"
                                        : "جمع کردن سایدبار"
                                }
                                className="grid size-10 place-items-center rounded-xl text-indigo-500 transition hover:bg-[var(--store-bg)]"
                                onClick={() =>
                                    setDesktopSidebarCollapsed(
                                        (current) => !current,
                                    )
                                }
                                title={
                                    desktopSidebarCollapsed
                                        ? "باز کردن منو"
                                        : "جمع کردن منو"
                                }
                                type="button"
                            >
                                {desktopSidebarCollapsed ? (
                                    <ChevronLeft size={19} />
                                ) : (
                                    <ChevronRight size={19} />
                                )}
                            </button>
                        </div>
                        <div className="flex min-w-max gap-2 lg:min-w-0 lg:flex-col lg:gap-1.5">
                            {tabs.map(([id, label, Icon]) => (
                                <button
                                    aria-current={
                                        tab === id ? "page" : undefined
                                    }
                                    className={`flex min-h-11 min-w-max items-center justify-center gap-2 rounded-xl px-3 text-xs font-bold transition sm:min-h-12 sm:text-sm lg:w-full ${desktopSidebarCollapsed ? "lg:min-w-0 lg:px-0" : "lg:justify-start lg:px-4"} ${tab === id ? "bg-indigo-600 text-white shadow-lg shadow-indigo-500/20" : "text-[var(--store-muted)] hover:bg-[var(--store-bg)]"}`}
                                    key={id}
                                    onClick={() => setTab(id)}
                                    title={label}
                                    type="button"
                                >
                                    <Icon className="shrink-0" size={19} />
                                    <span
                                        className={desktopSidebarCollapsed ? "lg:hidden" : "lg:block"}
                                    >
                                        {label}
                                    </span>
                                </button>
                            ))}
                            <a
                                className={`flex min-h-11 min-w-max items-center justify-center gap-2 rounded-xl px-3 text-xs font-bold text-[var(--store-muted)] transition hover:bg-[var(--store-bg)] sm:min-h-12 sm:text-sm lg:w-full ${desktopSidebarCollapsed ? "lg:min-w-0 lg:px-0" : "lg:justify-start lg:px-4"}`}
                                href="/account/tickets"
                                title="تیکت‌های پشتیبانی"
                            >
                                <LifeBuoy className="shrink-0" size={19} />
                                <span
                                    className={desktopSidebarCollapsed ? "lg:hidden" : "lg:block"}
                                >
                                    تیکت‌های پشتیبانی
                                </span>
                            </a>
                        </div>
                    </nav>
                    <section className="pn-deferred-zone min-w-0 rounded-[22px] border border-[var(--store-border)] bg-[var(--store-surface)] p-3.5 sm:rounded-3xl sm:p-7">
                        {tab === "overview" && (
                            <>
                                <Overview
                                    profile={profile}
                                    addresses={addresses}
                                    setTab={setTab}
                                />
                                <div className="mt-6 grid gap-4 md:grid-cols-2">
                                    <div>
                                        <h3 className="mb-3 font-black">
                                            سفارش‌های اخیر
                                        </h3>
                                        {orders.data
                                            .slice(0, 5)
                                            .map((order) => (
                                                <a
                                                    className="mb-2 grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 rounded-xl bg-[var(--store-bg)] p-3 text-xs sm:text-sm"
                                                    href={`/orders/${order.id}`}
                                                    key={order.id}
                                                >
                                                    <span
                                                        className="truncate"
                                                        dir="ltr"
                                                    >
                                                        {order.number}
                                                    </span>
                                                    <strong className="whitespace-nowrap">
                                                        {order.grand_total.toLocaleString(
                                                            "fa-IR",
                                                        )}{" "}
                                                        تومان
                                                    </strong>
                                                </a>
                                            ))}
                                    </div>
                                    <div>
                                        <h3 className="mb-3 font-black">
                                            اعلان‌ها
                                        </h3>
                                        {accountNotifications.map((item) => (
                                            <a
                                                className={`mb-2 block rounded-xl border p-3 text-sm transition ${item.read_at ? "border-transparent bg-[var(--store-bg)] opacity-70" : "border-indigo-500/30 bg-indigo-500/10 shadow-sm"}`}
                                                href={`/account/notifications/${item.id}`}
                                                key={item.id}
                                            >
                                                <strong>{item.title}</strong>
                                                <p className="mt-1 text-[var(--store-muted)]">
                                                    {item.message}
                                                </p>
                                            </a>
                                        ))}
                                    </div>
                                </div>
                            </>
                        )}{" "}
                        {tab === "orders" && (
                            <OrdersPanel
                                orders={orders}
                                counts={orderStatusCounts}
                                selectedStatus={filters.status}
                            />
                        )}{" "}
                        {tab === "profile" && (
                            <ProfileForm
                                profile={profile}
                                telegram={telegramIntegration}
                            />
                        )}{" "}
                        {tab === "tracking" && (
                            <OrderTracking order={currentOrder} />
                        )}{" "}
                        {tab === "content-notifications" && (
                            <ContentNotificationsPanel
                                preferences={contentNotificationPreferences}
                                profile={profile}
                                telegram={telegramIntegration}
                            />
                        )}{" "}
                        {tab === "addresses" && (
                            <Addresses
                                addresses={addresses}
                                profile={profile}
                            />
                        )}{" "}
                        {tab === "security" && (
                            <PasswordForm hasPassword={profile.has_password} />
                        )}
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}

const orderFilters = [
    { value: null, label: "همه" },
    { value: "pending", label: "در انتظار تأیید" },
    { value: "approved", label: "تأییدشده" },
    { value: "processing", label: "آماده‌سازی" },
    { value: "shipped", label: "ارسال‌شده" },
    { value: "delivered", label: "تحویل‌شده" },
    { value: "rejected", label: "ردشده" },
    { value: "cancelled", label: "لغوشده" },
] as const;

function OrdersPanel({
    orders,
    counts,
    selectedStatus,
}: {
    orders: PaginatedOrders;
    counts: Record<string, number>;
    selectedStatus: string | null;
}) {
    return (
        <div>
            <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 className="text-xl font-black">سفارش‌های من</h2>
                    <p className="mt-2 text-sm text-[var(--store-muted)]">
                        سفارش‌ها را براساس وضعیت بررسی کن؛ فاکتور پس از تحویل
                        فعال می‌شود.
                    </p>
                </div>
                <span className="text-xs font-bold text-[var(--store-muted)]">
                    {orders.total.toLocaleString("fa-IR")} سفارش
                </span>
            </div>

            <div className="mt-5 flex gap-2 overflow-x-auto pb-2">
                {orderFilters.map((filter) => {
                    const active = selectedStatus === filter.value;
                    const count = filter.value
                        ? (counts[filter.value] ?? 0)
                        : Object.values(counts).reduce(
                              (sum, value) => sum + Number(value),
                              0,
                          );
                    const href = filter.value
                        ? `/account?tab=orders&status=${filter.value}`
                        : "/account?tab=orders";
                    return (
                        <Link
                            className={`flex min-w-max items-center gap-2 rounded-xl border px-3 py-2 text-xs font-bold transition ${active ? "border-indigo-600 bg-indigo-600 text-white" : "border-[var(--store-border)] bg-[var(--store-bg)] text-[var(--store-muted)] hover:border-indigo-500"}`}
                            href={href}
                            key={filter.value ?? "all"}
                            preserveScroll
                        >
                            {filter.label}
                            <span
                                className={`rounded-full px-1.5 py-0.5 text-[10px] ${active ? "bg-white/15" : "bg-[var(--store-surface)]"}`}
                            >
                                {count.toLocaleString("fa-IR")}
                            </span>
                        </Link>
                    );
                })}
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {orders.data.map((order) => (
                    <article
                        className="flex min-h-56 flex-col rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)] p-4"
                        key={order.id}
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p
                                    className="truncate text-xs font-bold text-[var(--store-muted)]"
                                    dir="ltr"
                                >
                                    {order.number}
                                </p>
                                <time className="mt-2 block text-xs text-[var(--store-muted)]">
                                    {new Date(
                                        order.created_at,
                                    ).toLocaleDateString("fa-IR")}
                                </time>
                            </div>
                            <span
                                className={`shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black ${order.status === "delivered" ? "bg-emerald-500/10 text-emerald-600" : order.status === "rejected" || order.status === "cancelled" ? "bg-rose-500/10 text-rose-600" : "bg-indigo-500/10 text-indigo-600"}`}
                            >
                                {orderFilters.find(
                                    (item) => item.value === order.status,
                                )?.label ?? order.status}
                            </span>
                        </div>
                        <div className="mt-5 flex-1 border-y border-[var(--store-border)] py-4">
                            <span className="text-xs text-[var(--store-muted)]">
                                مبلغ نهایی
                            </span>
                            <strong className="mt-1 block text-lg">
                                {order.grand_total.toLocaleString("fa-IR")}{" "}
                                تومان
                            </strong>
                            {order.cashback_amount > 0 && (
                                <span className="mt-2 block text-xs font-bold text-amber-600">
                                    Cashback:{" "}
                                    {order.cashback_amount.toLocaleString(
                                        "fa-IR",
                                    )}{" "}
                                    تومان
                                </span>
                            )}
                        </div>
                        <div className="mt-4 grid grid-cols-2 gap-2">
                            <Link
                                className="flex min-h-10 items-center justify-center rounded-xl border border-[var(--store-border)] text-xs font-bold"
                                href={`/orders/${order.id}`}
                            >
                                جزئیات سفارش
                            </Link>
                            {order.status === "delivered" ? (
                                <Link
                                    className="flex min-h-10 items-center justify-center gap-1.5 rounded-xl bg-indigo-600 text-xs font-black text-white"
                                    href={`/orders/${order.id}/invoice`}
                                >
                                    <ReceiptText size={15} /> مشاهده فاکتور
                                </Link>
                            ) : (
                                <span className="flex min-h-10 items-center justify-center rounded-xl bg-[var(--store-surface)] px-2 text-center text-[10px] text-[var(--store-muted)]">
                                    فاکتور پس از تحویل
                                </span>
                            )}
                        </div>
                    </article>
                ))}
            </div>

            {!orders.data.length && (
                <div className="mt-5 rounded-3xl border border-dashed border-[var(--store-border)] py-16 text-center text-sm text-[var(--store-muted)]">
                    <PackageOpen className="mx-auto mb-3" /> سفارشی با این وضعیت
                    وجود ندارد.
                </div>
            )}
            <Pagination links={orders.links} />
        </div>
    );
}

function Avatar({ profile }: { profile: Profile }) {
    return profile.avatar_url ? (
        <img
            className="size-16 rounded-2xl object-cover ring-4 ring-indigo-500/10 sm:size-24 sm:rounded-3xl"
            decoding="async"
            src={profile.avatar_url}
        />
    ) : (
        <div className="grid size-16 place-items-center rounded-2xl bg-indigo-500/10 text-xl font-black text-indigo-500 sm:size-24 sm:rounded-3xl sm:text-3xl">
            {profile.name.slice(0, 2)}
        </div>
    );
}
function Overview({
    profile,
    addresses,
    setTab,
}: {
    profile: Profile;
    addresses: Address[];
    setTab: (v: Tab) => void;
}) {
    return (
        <>
            <h2 className="text-xl font-black">مرکز حساب کاربری</h2>
            <div className="mt-5 grid grid-cols-2 gap-3 sm:mt-6 sm:gap-4 xl:grid-cols-3">
                <Card
                    icon={UserRound}
                    title="اطلاعات شخصی"
                    text={profile.phone ?? "شماره موبایل ثبت نشده"}
                    onClick={() => setTab("profile")}
                />
                <Card
                    icon={MapPin}
                    title="آدرس‌های من"
                    text={`${addresses.length.toLocaleString("fa-IR")} آدرس ثبت‌شده`}
                    onClick={() => setTab("addresses")}
                />
                <Card
                    icon={ShieldCheck}
                    title="امنیت حساب"
                    text="رمز عبور و دسترسی"
                    onClick={() => setTab("security")}
                />
            </div>
            <div className="mt-5 rounded-2xl border border-indigo-500/15 bg-indigo-500/5 p-4 sm:mt-6 sm:p-5">
                <h3 className="font-black">سفارش‌های من</h3>
                <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                    پس از ثبت اولین سفارش، وضعیت خریدها و کدهای پیگیری در این
                    بخش نمایش داده می‌شود.
                </p>
            </div>
        </>
    );
}

const orderStages = [
    {
        key: "pending",
        label: "ثبت سفارش",
        description: "سفارش با موفقیت ثبت شد",
        icon: ShoppingBag,
    },
    {
        key: "approved",
        label: "تأیید سفارش",
        description: "سفارش توسط فروشگاه تأیید شد",
        icon: CheckCircle2,
    },
    {
        key: "processing",
        label: "آماده‌سازی",
        description: "محصولات در حال آماده‌سازی هستند",
        icon: PackageOpen,
    },
    {
        key: "shipped",
        label: "ارسال سفارش",
        description: "سفارش در مسیر تحویل قرار دارد",
        icon: Truck,
    },
    {
        key: "delivered",
        label: "تحویل‌شده",
        description: "سفارش با موفقیت تحویل شد",
        icon: PackageCheck,
    },
] as const;

function OrderTracking({ order }: { order: CurrentOrder | null }) {
    if (!order)
        return (
            <div className="grid min-h-80 place-items-center rounded-3xl border border-dashed border-[var(--store-border)] bg-[var(--store-bg)] p-8 text-center">
                <div>
                    <ShoppingBag
                        className="mx-auto text-indigo-500"
                        size={42}
                    />
                    <h2 className="mt-4 text-xl font-black">
                        هنوز سفارشی ثبت نکرده‌اید
                    </h2>
                    <p className="mt-2 text-sm text-[var(--store-muted)]">
                        بعد از اولین خرید، روند سفارش از این بخش قابل پیگیری
                        است.
                    </p>
                    <a
                        className="mt-5 inline-flex rounded-xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white"
                        href="/shop"
                    >
                        رفتن به فروشگاه
                    </a>
                </div>
            </div>
        );
    const stopped = ["rejected", "cancelled"].includes(order.status);
    const activeIndex = stopped
        ? 0
        : Math.max(
              0,
              orderStages.findIndex((stage) => stage.key === order.status),
          );
    const progress = stopped
        ? 0
        : (activeIndex / (orderStages.length - 1)) * 100;
    const statusText =
        order.status === "rejected"
            ? "سفارش رد شده است"
            : order.status === "cancelled"
              ? "سفارش لغو شده است"
              : orderStages[activeIndex]?.description;
    return (
        <div>
            <div className="relative overflow-hidden rounded-3xl bg-gradient-to-l from-indigo-700 via-violet-700 to-fuchsia-700 p-6 text-white shadow-xl shadow-indigo-500/15 sm:p-8">
                <div className="absolute -left-12 -top-16 size-52 rounded-full bg-white/10 blur-3xl" />
                <div className="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-bold text-indigo-100">
                            سفارش جاری
                        </p>
                        <h2 className="mt-2 text-2xl font-black" dir="ltr">
                            {order.number}
                        </h2>
                        <p className="mt-2 text-sm text-indigo-100">
                            {statusText}
                        </p>
                    </div>
                    <div className="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur">
                        <span className="block text-xs text-indigo-100">
                            مبلغ سفارش
                        </span>
                        <strong className="mt-1 block text-xl">
                            {order.grand_total.toLocaleString("fa-IR")} تومان
                        </strong>
                    </div>
                </div>
            </div>
            {stopped ? (
                <div className="mt-5 rounded-2xl border border-rose-500/20 bg-rose-500/10 p-5 text-rose-600">
                    <strong>{statusText}</strong>
                    <p className="mt-2 text-sm">
                        برای جزئیات بیشتر وارد صفحه سفارش شوید یا با پشتیبانی
                        تماس بگیرید.
                    </p>
                </div>
            ) : (
                <div className="mt-7 rounded-3xl border border-[var(--store-border)] bg-[var(--store-bg)] p-5 sm:p-7">
                    <div className="relative hidden md:block">
                        <div className="absolute right-[10%] left-[10%] top-6 h-1 rounded-full bg-[var(--store-border)]">
                            <div
                                className="h-full rounded-full bg-gradient-to-l from-indigo-500 to-emerald-500 transition-all duration-700"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <div className="relative grid grid-cols-5">
                            {orderStages.map((stage, index) => (
                                <Stage
                                    key={stage.key}
                                    stage={stage}
                                    complete={index < activeIndex}
                                    active={index === activeIndex}
                                />
                            ))}
                        </div>
                    </div>
                    <div className="space-y-0 md:hidden">
                        {orderStages.map((stage, index) => (
                            <div
                                className="relative flex gap-4 pb-6 last:pb-0"
                                key={stage.key}
                            >
                                {index < orderStages.length - 1 && (
                                    <span
                                        className={`absolute right-[23px] top-11 h-[calc(100%-20px)] w-0.5 ${index < activeIndex ? "bg-emerald-500" : "bg-[var(--store-border)]"}`}
                                    />
                                )}
                                <Stage
                                    stage={stage}
                                    complete={index < activeIndex}
                                    active={index === activeIndex}
                                    mobile
                                />
                            </div>
                        ))}
                    </div>
                </div>
            )}
            <div className="mt-5 grid gap-4 md:grid-cols-[1fr_auto]">
                <div className="rounded-2xl border border-[var(--store-border)] p-5">
                    <h3 className="font-black">محصولات این سفارش</h3>
                    <div className="mt-3 space-y-2">
                        {order.items.map((item) => (
                            <div
                                className="flex justify-between text-sm"
                                key={item.id}
                            >
                                <span>{item.title}</span>
                                <strong>
                                    × {item.quantity.toLocaleString("fa-IR")}
                                </strong>
                            </div>
                        ))}
                    </div>
                </div>
                <a
                    className="flex items-center justify-center rounded-2xl border border-indigo-500/20 bg-indigo-500/10 px-7 py-4 text-sm font-black text-indigo-600 transition hover:bg-indigo-500/15"
                    href={`/orders/${order.id}`}
                >
                    مشاهده جزئیات سفارش
                </a>
            </div>
        </div>
    );
}

function Stage({
    stage,
    complete,
    active,
    mobile = false,
}: {
    stage: (typeof orderStages)[number];
    complete: boolean;
    active: boolean;
    mobile?: boolean;
}) {
    const Icon = stage.icon;
    return (
        <div className={mobile ? "flex items-center gap-4" : "text-center"}>
            <span
                className={`${mobile ? "" : "mx-auto"} relative z-10 grid size-12 shrink-0 place-items-center rounded-2xl border transition ${complete ? "border-emerald-500 bg-emerald-500 text-white" : active ? "border-indigo-500 bg-indigo-600 text-white shadow-lg shadow-indigo-500/30" : "border-[var(--store-border)] bg-[var(--store-surface)] text-[var(--store-muted)]"}`}
            >
                {complete ? <CheckCircle2 size={20} /> : <Icon size={20} />}
            </span>
            <div className={mobile ? "" : "mt-3"}>
                <strong
                    className={`block text-xs ${active ? "text-indigo-500" : ""}`}
                >
                    {stage.label}
                </strong>
                <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                    {stage.description}
                </span>
            </div>
        </div>
    );
}
function Card({
    icon: Icon,
    title,
    text,
    onClick,
}: {
    icon: typeof Home;
    title: string;
    text: string;
    onClick: () => void;
}) {
    return (
        <button
            className="group min-w-0 rounded-2xl border border-[var(--store-border)] p-3 text-right transition hover:-translate-y-1 hover:border-indigo-500/40 sm:p-5"
            onClick={onClick}
        >
            <span className="grid size-11 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                <Icon size={21} />
            </span>
            <strong className="mt-3 block text-xs leading-5 sm:mt-4 sm:text-base">
                {title}
            </strong>
            <span className="mt-1 block text-xs text-[var(--store-muted)]">
                {text}
            </span>
        </button>
    );
}
function ContentNotificationsPanel({
    preferences,
    profile,
    telegram,
}: {
    preferences: ContentNotificationPreferences;
    profile: Profile;
    telegram: TelegramIntegration;
}) {
    const { data, setData, put, processing, recentlySuccessful } =
        useForm<ContentNotificationPreferences>(preferences);
    const [copied, setCopied] = useState(false);
    const lastTelegramRefreshAt = useRef(0);
    const lastTelegramPreference = useRef(preferences.telegram_enabled);

    useEffect(() => {
        if (
            lastTelegramPreference.current !== preferences.telegram_enabled
        ) {
            lastTelegramPreference.current = preferences.telegram_enabled;
            setData("telegram_enabled", preferences.telegram_enabled);
        }
    }, [preferences.telegram_enabled, setData]);

    useEffect(() => {
        const refreshTelegramState = () => {
            if (
                document.visibilityState !== "visible" ||
                Date.now() - lastTelegramRefreshAt.current < 1200
            ) {
                return;
            }

            lastTelegramRefreshAt.current = Date.now();
            router.reload({
                only: [
                    "telegramIntegration",
                    "contentNotificationPreferences",
                ],
                preserveScroll: true,
                preserveState: true,
            });
        };

        window.addEventListener("focus", refreshTelegramState);
        document.addEventListener("visibilitychange", refreshTelegramState);

        return () => {
            window.removeEventListener("focus", refreshTelegramState);
            document.removeEventListener(
                "visibilitychange",
                refreshTelegramState,
            );
        };
    }, []);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        put("/account/content-notifications", { preserveScroll: true });
    };

    const copyBot = async () => {
        if (!telegram.bot_username) return;
        await navigator.clipboard.writeText(telegram.bot_username);
        setCopied(true);
        window.setTimeout(() => setCopied(false), 1800);
    };

    return (
        <form onSubmit={submit}>
            <div className="relative overflow-hidden rounded-[22px] bg-gradient-to-l from-indigo-700 via-violet-700 to-fuchsia-700 p-4 text-white shadow-xl shadow-indigo-500/15 sm:rounded-3xl sm:p-7">
                <div className="absolute -left-12 -top-16 size-52 rounded-full bg-white/10 blur-3xl" />
                <div className="relative flex items-start gap-3 sm:gap-4">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl border border-white/15 bg-white/10 backdrop-blur sm:size-12 sm:rounded-2xl">
                        <BellRing className="size-5 sm:size-6" />
                    </span>
                    <div>
                        <p className="text-xs font-bold text-indigo-100">
                            اعلان‌ها، سفارش‌ها و محتوای مهم
                        </p>
                        <h2 className="mt-1 text-xl font-black sm:text-2xl">
                            روش‌های اطلاع‌رسانی PlayNexus
                        </h2>
                        <p className="mt-2 max-w-2xl text-xs leading-6 text-indigo-100 sm:text-sm">
                            تلگرام را یک‌بار وصل کن؛ بعد اعلان سفارش، پشتیبانی،
                            محتوای دنبال‌شده و کد ورود درخواستی می‌تواند در همان چت برسد.
                        </p>
                    </div>
                </div>
            </div>

            <div className="mt-4 overflow-hidden rounded-[22px] border border-sky-500/20 bg-gradient-to-l from-sky-500/[0.09] via-indigo-500/[0.05] to-transparent p-3.5 sm:mt-5 sm:rounded-3xl sm:p-5">
                <div className="grid grid-cols-[auto_minmax(0,1fr)] gap-3 sm:flex sm:items-center sm:gap-4">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-sky-500 text-white shadow-lg shadow-sky-500/20 sm:size-12 sm:rounded-2xl">
                        <Send className="size-5 sm:size-[23px]" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="font-black">تلگرام</h3>
                            <span className={`rounded-full px-2.5 py-1 text-[10px] font-black ${telegram.connected ? "bg-emerald-500/10 text-emerald-600" : "bg-slate-500/10 text-[var(--store-muted)]"}`}>
                                {telegram.connected ? "متصل ✓" : "متصل نیست"}
                            </span>
                        </div>
                        <p className="mt-1 text-xs leading-6 text-[var(--store-muted)]">
                            {telegram.connected
                                ? "اتصال تأیید شده است؛ کد ورود و اعلان‌های فعال می‌توانند در همین Bot ارسال شوند."
                                : "اتصال فقط یک‌بار انجام می‌شود. بعد از آن برای دریافت اعلان‌ها یا کد ورود هیچ تنظیم دوباره‌ای لازم نیست."}
                        </p>
                        {telegram.bot_username && (
                            <button
                                className="mt-2 inline-flex items-center gap-2 rounded-xl border border-sky-500/20 bg-sky-500/[0.06] px-3 py-2 font-mono text-xs font-bold text-sky-600"
                                onClick={copyBot}
                                type="button"
                            >
                                <Copy size={14} />
                                {telegram.bot_username}
                                <span className="font-sans text-[10px]">
                                    {copied ? "کپی شد" : "کپی"}
                                </span>
                            </button>
                        )}
                    </div>
                    <div className="col-span-2 flex shrink-0 gap-2 sm:col-auto">
                        {telegram.connected ? (
                            <button
                                className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl border border-rose-500/20 px-4 text-xs font-black text-rose-600 sm:flex-none"
                                onClick={() =>
                                    confirm("اتصال تلگرام از حساب قطع شود؟") &&
                                    router.delete("/account/telegram", {
                                        preserveScroll: true,
                                    })
                                }
                                type="button"
                            >
                                <Link2Off size={16} />
                                قطع اتصال
                            </button>
                        ) : (
                            <button
                                className="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-sky-500 px-4 text-xs font-black text-white shadow-lg shadow-sky-500/20 disabled:opacity-50 sm:flex-none"
                                disabled={!telegram.available}
                                onClick={() =>
                                    router.post("/account/telegram/connect", {})
                                }
                                type="button"
                            >
                                <Send size={16} />
                                اتصال تلگرام
                            </button>
                        )}
                    </div>
                </div>
            </div>

            <div className="mt-4 rounded-2xl border border-amber-500/20 bg-amber-500/10 p-3.5 text-[11px] leading-6 text-amber-700 sm:mt-5 sm:p-4 sm:text-xs dark:text-amber-300">
                پیامک، ایمیل و فید شخصی برای انتشار محتوای دنبال‌شده‌اند.
                تلگرام علاوه بر محتوا، اعلان‌های حساب مثل سفارش و پشتیبانی را هم پوشش می‌دهد.
            </div>

            <div className="mt-4 grid gap-2.5 sm:mt-5 sm:gap-3">
                <NotificationMethod
                    active={data.telegram_enabled}
                    description={
                        telegram.connected
                            ? "ارسال اعلان‌های PlayNexus در Bot متصل‌شده"
                            : "برای فعال‌کردن این روش، ابتدا تلگرام را از کارت بالا متصل کن."
                    }
                    icon={Send}
                    label="تلگرام"
                    onChange={(active) =>
                        telegram.connected &&
                        setData("telegram_enabled", active)
                    }
                    recommended={telegram.connected}
                />
                <NotificationMethod
                    active={data.sms_enabled}
                    description={
                        profile.phone
                            ? `ارسال به ${profile.phone}`
                            : "برای دریافت پیامک ابتدا شماره موبایل را در اطلاعات حساب ثبت کنید."
                    }
                    icon={Smartphone}
                    label="پیامک"
                    onChange={(active) => setData("sms_enabled", active)}
                />
                <NotificationMethod
                    active={data.email_enabled}
                    description={`ارسال به ${profile.email}`}
                    icon={Mail}
                    label="ایمیل"
                    onChange={(active) => setData("email_enabled", active)}
                />
                <NotificationMethod
                    active={data.feed_enabled}
                    description="نمایش اعلان انتشارهای تازه کانال‌ها داخل حساب PlayNexus"
                    icon={Rss}
                    label="فید شخصی"
                    onChange={(active) => setData("feed_enabled", active)}
                />
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                <button
                    className="min-h-12 w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white shadow-lg shadow-indigo-500/20 disabled:opacity-50 sm:w-auto"
                    disabled={processing}
                    type="submit"
                >
                    {processing ? "در حال ذخیره…" : "ذخیره روش‌های اطلاع‌رسانی"}
                </button>
                {recentlySuccessful && (
                    <span className="text-xs font-bold text-emerald-600">
                        تنظیمات ذخیره شد.
                    </span>
                )}
            </div>
        </form>
    );
}

function NotificationMethod({
    active,
    description,
    icon: Icon,
    label,
    onChange,
    recommended = false,
    soon = false,
}: {
    active: boolean;
    description: string;
    icon: typeof Home;
    label: string;
    onChange: (active: boolean) => void;
    recommended?: boolean;
    soon?: boolean;
}) {
    return (
        <button
            aria-checked={active}
            className={`grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-2.5 rounded-2xl border p-3 text-right transition sm:gap-3 sm:p-5 ${active ? "border-indigo-500/40 bg-indigo-500/[0.07] shadow-sm" : "border-[var(--store-border)] bg-[var(--store-bg)] hover:border-indigo-500/30"}`}
            onClick={() => onChange(!active)}
            role="switch"
            type="button"
        >
            <span
                className={`grid size-10 shrink-0 place-items-center rounded-xl sm:size-11 ${active ? "bg-indigo-600 text-white" : "bg-[var(--store-surface)] text-[var(--store-muted)]"}`}
            >
                <Icon size={21} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex flex-wrap items-center gap-2">
                    <strong className="text-sm sm:text-base">{label}</strong>
                    {recommended && (
                        <span className="rounded-full bg-emerald-500/10 px-2 py-0.5 text-[10px] font-black text-emerald-600">
                            پیش‌فرض و پیشنهادی
                        </span>
                    )}
                    {soon && (
                        <span className="rounded-full bg-amber-500/10 px-2 py-0.5 text-[10px] font-black text-amber-600">
                            به‌زودی
                        </span>
                    )}
                </span>
                <span className="mt-1 block text-xs leading-6 text-[var(--store-muted)]">
                    {description}
                </span>
            </span>
            <span
                className={`relative h-7 w-12 shrink-0 rounded-full transition ${active ? "bg-indigo-600" : "bg-[var(--store-border)]"}`}
            >
                <span
                    className={`absolute top-1 size-5 rounded-full bg-white shadow transition ${active ? "left-1" : "left-6"}`}
                />
            </span>
        </button>
    );
}
function ProfileForm({
    profile,
    telegram,
}: {
    profile: Profile;
    telegram: TelegramIntegration;
}) {
    const file = useRef<HTMLInputElement>(null);
    const [telegramVerifying, setTelegramVerifying] = useState(false);
    const { data, setData, post, processing, errors } = useForm<{
        name: string;
        phone: string;
        birth_date: string;
        avatar: File | null;
        remove_avatar: boolean;
        _method: string;
    }>({
        name: profile.name,
        phone: profile.phone ?? "",
        birth_date: profile.birth_date ?? "",
        avatar: null,
        remove_avatar: false,
        _method: "patch",
    });
    const avatarPreview = useMemo(
        () =>
            data.avatar
                ? URL.createObjectURL(data.avatar)
                : profile.avatar_url,
        [data.avatar, profile.avatar_url],
    );
    useEffect(() => {
        if (!data.avatar || !avatarPreview) return;
        return () => URL.revokeObjectURL(avatarPreview);
    }, [avatarPreview, data.avatar]);

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post("/account/profile", { forceFormData: true });
    };
    return (
        <form onSubmit={submit}>
            <h2 className="text-xl font-black">اطلاعات حساب</h2>
            <p className="mt-2 text-sm text-[var(--store-muted)]">
                اطلاعات موردنیاز برای ارتباط و ثبت سفارش را کامل کن.
            </p>
            <div className="mt-6 flex flex-col items-start gap-4 min-[380px]:flex-row min-[380px]:items-center sm:mt-7">
                <Avatar
                    profile={{
                        ...profile,
                        avatar_url: avatarPreview,
                    }}
                />
                <div>
                    <input
                        accept="image/jpeg,image/png,image/webp"
                        className="hidden"
                        onChange={(e) =>
                            setData("avatar", e.target.files?.[0] ?? null)
                        }
                        ref={file}
                        type="file"
                    />
                    <button
                        className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white"
                        onClick={() => file.current?.click()}
                        type="button"
                    >
                        <Camera size={16} />
                        انتخاب تصویر
                    </button>
                    {profile.avatar_url && (
                        <button
                            className="mr-2 text-xs text-red-500"
                            onClick={() => setData("remove_avatar", true)}
                            type="button"
                        >
                            حذف تصویر
                        </button>
                    )}
                    <p className="mt-2 text-[11px] text-[var(--store-muted)]">
                        JPG، PNG یا WEBP تا ۲MB
                    </p>
                </div>
            </div>
            <div className="mt-7 grid gap-5 sm:grid-cols-2">
                <Field label="نام و نام خانوادگی" error={errors.name}>
                    <input
                        className={field}
                        value={data.name}
                        onChange={(e) => setData("name", e.target.value)}
                    />
                </Field>
                <Field label="ایمیل">
                    <input
                        className={`${field} opacity-60`}
                        disabled
                        value={profile.email}
                    />
                </Field>
                <Field label="شماره موبایل" error={errors.phone}>
                    <input
                        className={field}
                        dir="ltr"
                        placeholder="09123456789"
                        value={data.phone}
                        onChange={(e) =>
                            setData(
                                "phone",
                                e.target.value.replace(/\D/g, "").slice(0, 11),
                            )
                        }
                    />
                </Field>
                <div className="sm:col-span-2">
                    <div
                        className={`rounded-2xl border p-4 ${profile.phone_verified ? "border-emerald-500/20 bg-emerald-500/10" : "border-amber-500/20 bg-amber-500/10"}`}
                    >
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex items-start gap-3">
                                <span
                                    className={`grid size-10 shrink-0 place-items-center rounded-xl ${profile.phone_verified ? "bg-emerald-500 text-white" : "bg-amber-500 text-white"}`}
                                >
                                    <ShieldCheck size={18} />
                                </span>
                                <div>
                                    <strong className="text-sm">
                                        {profile.phone_verified
                                            ? "شماره موبایل تأیید شده"
                                            : "شماره موبایل هنوز تأیید نشده"}
                                    </strong>
                                    <p className="mt-1 text-xs leading-6 text-[var(--store-muted)]">
                                        {profile.phone_verified
                                            ? "این شماره برای ورود و ثبت سفارش معتبر است."
                                            : "بدون تأیید شماره امکان ثبت سفارش نداری. می‌توانی SMS بگیری یا با شماره رسمی Telegram خودت مستقیم تأیید کنی."}
                                    </p>
                                </div>
                            </div>
                            {!profile.phone_verified &&
                                telegram.phone_verification_available && (
                                    <button
                                        className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-sky-500 px-4 text-xs font-black text-white shadow-lg shadow-sky-500/20 disabled:opacity-50"
                                        disabled={
                                            telegramVerifying ||
                                            data.phone !== (profile.phone ?? "")
                                        }
                                        onClick={() => {
                                            setTelegramVerifying(true);
                                            router.post(
                                                "/account/phone/verify-telegram",
                                                {},
                                                {
                                                    onFinish: () =>
                                                        setTelegramVerifying(
                                                            false,
                                                        ),
                                                },
                                            );
                                        }}
                                        type="button"
                                    >
                                        <Send size={16} />
                                        {telegramVerifying
                                            ? "در حال انتقال…"
                                            : "تأیید امن با Telegram"}
                                    </button>
                                )}
                        </div>
                        {!profile.phone_verified &&
                            data.phone !== (profile.phone ?? "") && (
                                <p className="mt-3 text-[11px] font-bold text-amber-700 dark:text-amber-300">
                                    ابتدا شماره جدید را ذخیره کن؛ سپس تأیید
                                    Telegram برای همان شماره فعال می‌شود.
                                </p>
                            )}
                    </div>
                </div>
                <Suspense
                    fallback={
                        <div className="h-[76px] rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)]" />
                    }
                >
                    <PersianDatePicker
                        description="تاریخ به شمسی نمایش داده می‌شود."
                        error={errors.birth_date}
                        label="تاریخ تولد (اختیاری)"
                        maximumToday
                        name="birth_date"
                        onChange={(value) => setData("birth_date", value)}
                        value={data.birth_date}
                        variant="storefront"
                    />
                </Suspense>
            </div>
            <button
                className="mt-7 min-h-12 w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white disabled:opacity-50 sm:w-auto"
                disabled={processing}
            >
                ذخیره اطلاعات
            </button>
        </form>
    );
}
function Field({
    label,
    error,
    children,
}: {
    label: string;
    error?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block">
            <span className="mb-2 block text-xs font-bold">{label}</span>
            {children}
            {error && (
                <span className="mt-1 block text-xs text-red-500">{error}</span>
            )}
        </label>
    );
}
const empty = {
    title: "خانه",
    recipient_name: "",
    phone: "",
    province: "",
    city: "",
    postal_code: "",
    address_line: "",
    plaque: "",
    unit: "",
    is_default: false,
};
function Addresses({
    addresses,
    profile,
}: {
    addresses: Address[];
    profile: Profile;
}) {
    const [editing, setEditing] = useState<Address | null>(null);
    const [open, setOpen] = useState(false);
    const { data, setData, post, put, processing, errors, reset } = useForm({
        ...empty,
        recipient_name: profile.name,
        phone: profile.phone ?? "",
    });
    const start = (a?: Address) => {
        if (a) {
            setEditing(a);
            Object.entries(a).forEach(
                ([k, v]) =>
                    k !== "id" &&
                    setData(k as keyof typeof data, (v ?? "") as never),
            );
        } else {
            setEditing(null);
            reset();
            setData("recipient_name", profile.name);
            setData("phone", profile.phone ?? "");
        }
        setOpen(true);
    };
    const submit = (e: FormEvent) => {
        e.preventDefault();
        editing
            ? put(`/account/addresses/${editing.id}`, {
                  onSuccess: () => setOpen(false),
              })
            : post("/account/addresses", { onSuccess: () => setOpen(false) });
    };
    return (
        <>
            <div className="flex flex-col items-start gap-4 min-[420px]:flex-row min-[420px]:items-center min-[420px]:justify-between">
                <div>
                    <h2 className="text-xl font-black">آدرس‌های ارسال</h2>
                    <p className="mt-2 text-sm text-[var(--store-muted)]">
                        آدرس‌های دقیق برای ارسال بدون دردسر.
                    </p>
                </div>
                <button
                    className="flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white min-[420px]:w-auto"
                    onClick={() => start()}
                >
                    <Plus size={16} />
                    آدرس جدید
                </button>
            </div>
            {open && (
                <form
                    className="mt-6 rounded-2xl border border-indigo-500/20 bg-indigo-500/5 p-5"
                    onSubmit={submit}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Simple name="title" label="عنوان آدرس" />
                        <Simple name="recipient_name" label="نام گیرنده" />
                        <Simple name="phone" label="شماره گیرنده" />
                        <Simple name="province" label="استان" />
                        <Simple name="city" label="شهر" />
                        <Simple name="postal_code" label="کدپستی ۱۰ رقمی" />
                        <div className="sm:col-span-2">
                            <Simple name="address_line" label="نشانی کامل" />
                        </div>
                        <Simple name="plaque" label="پلاک" />
                        <Simple name="unit" label="واحد" />
                    </div>
                    <label className="mt-4 flex items-center gap-2 text-sm">
                        <input
                            checked={data.is_default}
                            onChange={(e) =>
                                setData("is_default", e.target.checked)
                            }
                            type="checkbox"
                        />
                        آدرس پیش‌فرض باشد
                    </label>
                    {Object.values(errors)[0] && (
                        <p className="mt-3 text-xs text-red-500">
                            {Object.values(errors)[0]}
                        </p>
                    )}
                    <div className="mt-5 grid grid-cols-2 gap-2 sm:flex">
                        <button
                            className="min-h-11 rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white"
                            disabled={processing}
                        >
                            ذخیره آدرس
                        </button>
                        <button
                            className="min-h-11 rounded-xl border border-[var(--store-border)] px-5 py-2 text-sm"
                            onClick={() => setOpen(false)}
                            type="button"
                        >
                            انصراف
                        </button>
                    </div>
                </form>
            )}
            <div className="mt-6 grid gap-4 sm:grid-cols-2">
                {addresses.map((a) => (
                    <article
                        className="relative rounded-2xl border border-[var(--store-border)] p-4 sm:p-5"
                        key={a.id}
                    >
                        {a.is_default && (
                            <span className="absolute left-4 top-4 inline-flex items-center gap-1 text-xs font-bold text-emerald-500">
                                <CheckCircle2 size={14} />
                                پیش‌فرض
                            </span>
                        )}
                        <h3 className="font-black">{a.title}</h3>
                        <p className="mt-3 text-sm leading-7 text-[var(--store-muted)]">
                            {a.province}، {a.city}، {a.address_line}، پلاک{" "}
                            {a.plaque || "—"}
                        </p>
                        <p className="mt-2 text-xs">
                            {a.recipient_name} · {a.phone}
                        </p>
                        <div className="mt-4 flex gap-3">
                            <button
                                className="flex items-center gap-1 text-xs font-bold text-indigo-500"
                                onClick={() => start(a)}
                            >
                                <Pencil size={14} />
                                ویرایش
                            </button>
                            <button
                                className="flex items-center gap-1 text-xs font-bold text-red-500"
                                onClick={() =>
                                    confirm("این آدرس حذف شود؟") &&
                                    router.delete(`/account/addresses/${a.id}`)
                                }
                            >
                                <Trash2 size={14} />
                                حذف
                            </button>
                        </div>
                    </article>
                ))}
                {!addresses.length && !open && (
                    <div className="sm:col-span-2 rounded-2xl border border-dashed border-[var(--store-border)] py-12 text-center text-sm text-[var(--store-muted)]">
                        هنوز آدرسی ثبت نکرده‌اید.
                    </div>
                )}
            </div>
        </>
    );
    function Simple({
        name,
        label,
    }: {
        name: keyof typeof data;
        label: string;
    }) {
        return (
            <Field label={label}>
                <input
                    className={field}
                    value={String(data[name] ?? "")}
                    onChange={(e) => setData(name, e.target.value as never)}
                />
            </Field>
        );
    }
}
function PasswordForm({ hasPassword }: { hasPassword: boolean }) {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: "",
        password: "",
        password_confirmation: "",
    });
    return (
        <form
            className="max-w-xl"
            onSubmit={(e) => {
                e.preventDefault();
                put("/account/password", { onSuccess: () => reset() });
            }}
        >
            <h2 className="text-xl font-black">امنیت حساب</h2>
            <p className="mt-2 text-sm text-[var(--store-muted)]">
                {hasPassword
                    ? "برای امنیت بیشتر، رمز قوی و منحصربه‌فرد انتخاب کن."
                    : "برای اینکه علاوه بر Google با ایمیل هم وارد شوی، یک رمز امن برای PlayNexus تعیین کن."}
            </p>
            <div className="mt-7 space-y-5">
                {hasPassword && (
                    <Field
                        label="رمز عبور فعلی"
                        error={errors.current_password}
                    >
                        <input
                            autoComplete="current-password"
                            className={field}
                            type="password"
                            value={data.current_password}
                            onChange={(e) =>
                                setData("current_password", e.target.value)
                            }
                        />
                    </Field>
                )}
                <Field label="رمز عبور جدید" error={errors.password}>
                    <input
                        className={field}
                        type="password"
                        value={data.password}
                        onChange={(e) => setData("password", e.target.value)}
                    />
                </Field>
                <Field label="تکرار رمز جدید">
                    <input
                        className={field}
                        type="password"
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData("password_confirmation", e.target.value)
                        }
                    />
                </Field>
            </div>
            <button
                className="mt-7 min-h-12 w-full rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white sm:w-auto"
                disabled={processing}
            >
                {hasPassword ? "تغییر رمز عبور" : "تعیین رمز عبور"}
            </button>
        </form>
    );
}
