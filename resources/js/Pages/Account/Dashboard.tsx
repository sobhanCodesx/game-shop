import { Head, router, useForm, usePage } from "@inertiajs/react";
import {
    Camera,
    CheckCircle2,
    Home,
    KeyRound,
    LifeBuoy,
    MapPin,
    Pencil,
    Plus,
    ShieldCheck,
    WalletCards,
    Trash2,
    UserRound,
} from "lucide-react";
import { type FormEvent, useRef, useState } from "react";
import PersianDatePicker from "../../Components/Admin/Form/PersianDatePicker";
import StorefrontLayout from "../../Layouts/StorefrontLayout";
import type { SharedPageProps } from "../../types";

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    birth_date: string | null;
    avatar_url: string | null;
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
type Tab = "overview" | "profile" | "addresses" | "security";
const field =
    "h-12 w-full rounded-2xl border border-[var(--store-border)] bg-[var(--store-surface)] px-4 text-sm outline-none transition focus:border-indigo-500";

export default function Dashboard({
    profile,
    addresses,
    profileCompletion,
    walletBalance,
    orders,
    notifications,
}: {
    profile: Profile;
    addresses: Address[];
    profileCompletion: number;
    walletBalance: number;
    orders: {
        id: number;
        number: string;
        status: string;
        grand_total: number;
        cashback_amount: number;
    }[];
    notifications: {
        id: string;
        title: string;
        message: string;
        read_at: string | null;
    }[];
}) {
    const { flash } = usePage<SharedPageProps>().props;
    const [tab, setTab] = useState<Tab>("overview");
    const tabs: [Tab, string, typeof Home][] = [
        ["overview", "نمای کلی", Home],
        ["profile", "اطلاعات حساب", UserRound],
        ["addresses", "آدرس‌ها", MapPin],
        ["security", "امنیت", KeyRound],
    ];
    return (
        <StorefrontLayout>
            <Head title="حساب کاربری" />
            <main className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-12">
                <section className="relative overflow-hidden rounded-[32px] border border-[var(--store-border)] bg-[var(--store-surface)] p-6 sm:p-8">
                    <div className="absolute -left-20 -top-20 size-64 rounded-full bg-indigo-500/10 blur-3xl" />
                    <div className="relative flex flex-col gap-6 sm:flex-row sm:items-center">
                        <Avatar profile={profile} />
                        <div className="flex-1">
                            <p className="text-sm font-bold text-indigo-500">
                                باشگاه گیمرهای NEXUS
                            </p>
                            <h1 className="mt-2 text-2xl font-black sm:text-3xl">
                                سلام {profile.name} 👋
                            </h1>
                            <p className="mt-2 text-sm text-[var(--store-muted)]">
                                اطلاعات حسابت، آدرس‌های ارسال و امنیت را از
                                اینجا مدیریت کن.
                            </p>
                        </div>
                        <div className="min-w-48 rounded-2xl bg-[var(--store-bg)] p-4">
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
                <div className="mt-6 grid gap-6 lg:grid-cols-[240px_1fr]">
                    <nav className="flex gap-2 overflow-x-auto rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-3 lg:block lg:space-y-2 lg:self-start">
                        {tabs.map(([id, label, Icon]) => (
                            <button
                                className={`flex min-w-max items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold transition lg:w-full ${tab === id ? "bg-indigo-600 text-white shadow-lg shadow-indigo-500/20" : "text-[var(--store-muted)] hover:bg-[var(--store-bg)]"}`}
                                key={id}
                                onClick={() => setTab(id)}
                            >
                                <Icon size={19} />
                                {label}
                            </button>
                        ))}
                        <a
                            className="flex min-w-max items-center gap-3 rounded-2xl px-4 py-3 text-sm font-bold text-[var(--store-muted)] transition hover:bg-[var(--store-bg)] lg:w-full"
                            href="/account/tickets"
                        >
                            <LifeBuoy size={19} />
                            تیکت‌های پشتیبانی
                        </a>
                    </nav>
                    <section className="min-w-0 rounded-3xl border border-[var(--store-border)] bg-[var(--store-surface)] p-5 sm:p-7">
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
                                        {orders.map((order) => (
                                            <a
                                                className="mb-2 flex justify-between rounded-xl bg-[var(--store-bg)] p-3 text-sm"
                                                href={`/orders/${order.id}`}
                                                key={order.id}
                                            >
                                                <span>{order.number}</span>
                                                <strong>
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
                                        {notifications.map((item) => (
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
                        {tab === "profile" && <ProfileForm profile={profile} />}{" "}
                        {tab === "addresses" && (
                            <Addresses
                                addresses={addresses}
                                profile={profile}
                            />
                        )}{" "}
                        {tab === "security" && <PasswordForm />}
                    </section>
                </div>
            </main>
        </StorefrontLayout>
    );
}
function Avatar({ profile }: { profile: Profile }) {
    return profile.avatar_url ? (
        <img
            className="size-24 rounded-3xl object-cover ring-4 ring-indigo-500/10"
            src={profile.avatar_url}
        />
    ) : (
        <div className="grid size-24 place-items-center rounded-3xl bg-indigo-500/10 text-3xl font-black text-indigo-500">
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
            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
            <div className="mt-6 rounded-2xl border border-indigo-500/15 bg-indigo-500/5 p-5">
                <h3 className="font-black">سفارش‌های من</h3>
                <p className="mt-2 text-sm leading-7 text-[var(--store-muted)]">
                    پس از ثبت اولین سفارش، وضعیت خریدها و کدهای پیگیری در این
                    بخش نمایش داده می‌شود.
                </p>
            </div>
        </>
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
            className="group rounded-2xl border border-[var(--store-border)] p-5 text-right transition hover:-translate-y-1 hover:border-indigo-500/40"
            onClick={onClick}
        >
            <span className="grid size-11 place-items-center rounded-xl bg-indigo-500/10 text-indigo-500">
                <Icon size={21} />
            </span>
            <strong className="mt-4 block">{title}</strong>
            <span className="mt-1 block text-xs text-[var(--store-muted)]">
                {text}
            </span>
        </button>
    );
}
function ProfileForm({ profile }: { profile: Profile }) {
    const file = useRef<HTMLInputElement>(null);
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
            <div className="mt-7 flex items-center gap-4">
                <Avatar
                    profile={{
                        ...profile,
                        avatar_url: data.avatar
                            ? URL.createObjectURL(data.avatar)
                            : profile.avatar_url,
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
                        className="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white"
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
            </div>
            <button
                className="mt-7 rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white disabled:opacity-50"
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
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-xl font-black">آدرس‌های ارسال</h2>
                    <p className="mt-2 text-sm text-[var(--store-muted)]">
                        آدرس‌های دقیق برای ارسال بدون دردسر.
                    </p>
                </div>
                <button
                    className="flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white"
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
                    <div className="mt-5 flex gap-2">
                        <button
                            className="rounded-xl bg-indigo-600 px-5 py-2 text-sm font-bold text-white"
                            disabled={processing}
                        >
                            ذخیره آدرس
                        </button>
                        <button
                            className="rounded-xl border border-[var(--store-border)] px-5 py-2 text-sm"
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
                        className="relative rounded-2xl border border-[var(--store-border)] p-5"
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
function PasswordForm() {
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
                برای امنیت بیشتر، رمز قوی و منحصربه‌فرد انتخاب کن.
            </p>
            <div className="mt-7 space-y-5">
                <Field label="رمز عبور فعلی" error={errors.current_password}>
                    <input
                        className={field}
                        type="password"
                        value={data.current_password}
                        onChange={(e) =>
                            setData("current_password", e.target.value)
                        }
                    />
                </Field>
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
                className="mt-7 rounded-2xl bg-indigo-600 px-6 py-3 text-sm font-black text-white"
                disabled={processing}
            >
                تغییر رمز عبور
            </button>
        </form>
    );
}
