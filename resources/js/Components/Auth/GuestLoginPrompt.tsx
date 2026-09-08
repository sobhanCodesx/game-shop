import { useForm } from "@inertiajs/react";
import { Gamepad2, LogIn, X } from "lucide-react";
import { type FormEvent, useEffect, useState } from "react";
import type { AuthUser } from "../../types";
import GoogleLoginButton from "./GoogleLoginButton";

const STORAGE_KEY = "playnexus:guest-login-prompt-shown-at";
const DAY = 24 * 60 * 60 * 1000;

export default function GuestLoginPrompt({ user }: { user: AuthUser | null }) {
    const [visible, setVisible] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [rememberGoogle, setRememberGoogle] = useState(false);
    const form = useForm({ identifier: "", password: "", remember: true, redirect: "" });

    useEffect(() => {
        if (user) {
            setVisible(false);
            return;
        }
        try {
            const lastShown = Number(localStorage.getItem(STORAGE_KEY) ?? 0);
            if (Date.now() - lastShown < DAY) return;
            localStorage.setItem(STORAGE_KEY, String(Date.now()));
        } catch {
            // Storage can be unavailable in strict privacy modes; the prompt remains dismissible.
        }
        setVisible(true);
    }, [user]);

    if (!visible || user) return null;
    const destination = typeof window === "undefined" ? "/" : `${window.location.pathname}${window.location.search}${window.location.hash}`;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.transform((data) => ({ ...data, redirect: destination }));
        form.post("/login", {
            preserveScroll: true,
            onSuccess: () => setVisible(false),
        });
    };

    return (
        <aside className="fixed bottom-24 left-3 z-50 w-[calc(100%-1.5rem)] max-w-sm overflow-hidden rounded-[26px] border border-indigo-200/70 bg-white/95 p-4 text-slate-900 shadow-[0_24px_70px_rgba(15,23,42,.24)] backdrop-blur-xl sm:left-5 sm:p-5 lg:bottom-5" dir="rtl" role="complementary" aria-label="ورود به حساب PlayNexus">
            <button aria-label="بستن پیشنهاد ورود" className="absolute left-3 top-3 grid size-8 place-items-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-700" onClick={() => setVisible(false)} type="button"><X size={17} /></button>
            <div className="flex items-start gap-3 pl-8">
                <span className="grid size-11 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white shadow-lg shadow-indigo-200"><Gamepad2 size={22} /></span>
                <div><h2 className="text-sm font-black">بازی را با حسابت ادامه بده</h2><p className="mt-1 text-xs leading-6 text-slate-500">ورود سریع، خرید راحت‌تر و دسترسی به همه امکانات؛ محتوا همچنان باز می‌ماند.</p></div>
            </div>
            <div className="mt-4">
                <GoogleLoginButton compact redirect={destination} remember={rememberGoogle} />
                <label className="mt-2 flex cursor-pointer items-center gap-2 px-1 text-[11px] text-slate-500">
                    <input checked={rememberGoogle} className="accent-indigo-600" onChange={(event) => setRememberGoogle(event.target.checked)} type="checkbox" />
                    ورود Google را روی این دستگاه نگه دار
                </label>
                {!showPassword ? (
                    <button className="mt-2 flex h-10 w-full items-center justify-center gap-2 rounded-xl text-xs font-bold text-indigo-600 transition hover:bg-indigo-50" onClick={() => setShowPassword(true)} type="button"><LogIn size={15} /> ورود با ایمیل و رمز</button>
                ) : (
                    <form className="mt-3 space-y-2" onSubmit={submit}>
                        <input autoFocus className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs outline-none focus:border-indigo-400" dir="ltr" onChange={(event) => form.setData("identifier", event.target.value)} placeholder="ایمیل یا شماره موبایل" value={form.data.identifier} />
                        <input autoComplete="current-password" className="h-10 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 text-xs outline-none focus:border-indigo-400" dir="ltr" onChange={(event) => form.setData("password", event.target.value)} placeholder="رمز عبور" type="password" value={form.data.password} />
                        {(form.errors.identifier || form.errors.password) && <p className="text-[11px] leading-5 text-rose-600">{form.errors.identifier || form.errors.password}</p>}
                        <button className="h-10 w-full rounded-xl bg-indigo-600 text-xs font-black text-white disabled:opacity-50" disabled={form.processing}>ورود به حساب</button>
                    </form>
                )}
            </div>
        </aside>
    );
}
