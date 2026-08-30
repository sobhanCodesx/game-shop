import { Link, useForm } from "@inertiajs/react";
import { type FormEvent, useState } from "react";
import AuthShell, { authButton, authInput, Field } from "../../Components/Auth/AuthShell";

export default function Register() {
    const [channel, setChannel] = useState<"email" | "mobile">("mobile");
    const { data, setData, post, processing, errors } = useForm({
        channel: "mobile",
        first_name: "",
        last_name: "",
        email: "",
        phone: "",
        password: "",
        password_confirmation: "",
    });
    const select = (value: "email" | "mobile") => {
        setChannel(value);
        setData("channel", value);
    };

    return <AuthShell title="به جمع گیمرها بپیوند" subtitle="با ایمیل یا شماره موبایل حساب بساز." eyebrow="ثبت‌نام سریع">
        <div className="mb-5 grid grid-cols-2 gap-2">
            <button className={`rounded-xl py-2 text-xs font-bold ${channel === "email" ? "bg-violet-600" : "bg-white/5"}`} onClick={() => select("email")}>ایمیل</button>
            <button className={`rounded-xl py-2 text-xs font-bold ${channel === "mobile" ? "bg-violet-600" : "bg-white/5"}`} onClick={() => select("mobile")}>موبایل</button>
        </div>
        <form autoComplete="off" className="space-y-4" onSubmit={(event: FormEvent) => { event.preventDefault(); post("/register"); }}>
            <div className="grid grid-cols-2 gap-3">
                <Field error={errors.first_name} label="نام"><input autoComplete="off" className={authInput} onChange={event => setData("first_name", event.target.value)} value={data.first_name} /></Field>
                <Field error={errors.last_name} label="نام خانوادگی"><input autoComplete="off" className={authInput} onChange={event => setData("last_name", event.target.value)} value={data.last_name} /></Field>
            </div>
            {channel === "email"
                ? <Field error={errors.email} label="آدرس ایمیل"><input autoComplete="off" className={authInput} dir="ltr" onChange={event => setData("email", event.target.value)} type="email" value={data.email} /></Field>
                : <Field error={errors.phone} label="شماره موبایل"><input autoComplete="off" className={authInput} dir="ltr" inputMode="tel" onChange={event => setData("phone", event.target.value)} placeholder="09123456789" value={data.phone} /></Field>}
            <Field error={errors.password} hint="حداقل ۸ کاراکتر، حروف و عدد" label="رمز عبور"><input autoComplete="new-password" className={authInput} dir="ltr" onChange={event => setData("password", event.target.value)} type="password" value={data.password} /></Field>
            <Field label="تکرار رمز عبور"><input autoComplete="new-password" className={authInput} dir="ltr" onChange={event => setData("password_confirmation", event.target.value)} type="password" value={data.password_confirmation} /></Field>
            <button className={authButton} disabled={processing}>ساخت حساب و دریافت کد</button>
        </form>
        <p className="mt-5 text-center text-xs text-slate-500">قبلاً ثبت‌نام کردی؟ <Link className="font-bold text-violet-400" href="/login">وارد شو</Link></p>
    </AuthShell>;
}
