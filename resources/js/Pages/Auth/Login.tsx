import { Link, useForm } from "@inertiajs/react";
import { type FormEvent, useState } from "react";
import AuthShell, {
    authButton,
    authInput,
    Field,
    PasswordInput,
} from "../../Components/Auth/AuthShell";
import GoogleLoginButton from "../../Components/Auth/GoogleLoginButton";

export default function Login({ redirect }: { redirect?: string }) {
    const [otp, setOtp] = useState(false);
    const [rememberGoogle, setRememberGoogle] = useState(false);
    const password = useForm({
        identifier: "",
        password: "",
        remember: false,
        redirect: redirect ?? "",
    });
    const code = useForm({ phone: "" });

    return (
        <AuthShell
            title="خوش برگشتی گیمر"
            subtitle="با ایمیل، شماره موبایل یا حساب Google وارد شو."
            eyebrow="ورود امن"
        >
            <GoogleLoginButton redirect={redirect} remember={rememberGoogle} />
            <label className="mt-2 flex cursor-pointer items-center gap-2 text-[11px] text-slate-400">
                <input
                    checked={rememberGoogle}
                    className="accent-violet-500"
                    onChange={(event) =>
                        setRememberGoogle(event.target.checked)
                    }
                    type="checkbox"
                />
                ورود Google را روی این دستگاه به خاطر بسپار
            </label>
            <div className="my-3 flex items-center gap-3 text-[11px] text-slate-600 sm:my-4">
                <span className="h-px flex-1 bg-white/10" /> یا با حساب
                PlayNexus <span className="h-px flex-1 bg-white/10" />
            </div>
            <div className="mb-3 grid grid-cols-2 gap-2 sm:mb-4">
                <button
                    className={`rounded-xl py-2 text-xs font-bold ${!otp ? "bg-violet-600" : "bg-white/5"}`}
                    onClick={() => setOtp(false)}
                    type="button"
                >
                    ورود با رمز
                </button>
                <button
                    className={`rounded-xl py-2 text-xs font-bold ${otp ? "bg-violet-600" : "bg-white/5"}`}
                    onClick={() => setOtp(true)}
                    type="button"
                >
                    ورود پیامکی
                </button>
            </div>
            {otp ? (
                <form
                    className="space-y-3"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        code.post("/login/otp");
                    }}
                >
                    <Field error={code.errors.phone} label="شماره موبایل">
                        <input
                            className={authInput}
                            dir="ltr"
                            inputMode="tel"
                            onChange={(event) =>
                                code.setData("phone", event.target.value)
                            }
                            placeholder="09123456789"
                            value={code.data.phone}
                        />
                    </Field>
                    <button className={authButton} disabled={code.processing}>
                        دریافت کد ورود
                    </button>
                </form>
            ) : (
                <form
                    className="space-y-3"
                    onSubmit={(event: FormEvent) => {
                        event.preventDefault();
                        password.post("/login");
                    }}
                >
                    <Field
                        error={password.errors.identifier}
                        label="ایمیل یا شماره موبایل"
                    >
                        <input
                            className={authInput}
                            dir="ltr"
                            onChange={(event) =>
                                password.setData(
                                    "identifier",
                                    event.target.value,
                                )
                            }
                            placeholder="name@example.com یا 0912..."
                            value={password.data.identifier}
                        />
                    </Field>
                    <Field error={password.errors.password} label="رمز عبور">
                        <PasswordInput
                            autoComplete="current-password"
                            onChange={(event) =>
                                password.setData("password", event.target.value)
                            }
                            value={password.data.password}
                        />
                    </Field>
                    <div className="flex justify-between text-xs">
                        <label>
                            <input
                                checked={password.data.remember}
                                onChange={(event) =>
                                    password.setData(
                                        "remember",
                                        event.target.checked,
                                    )
                                }
                                type="checkbox"
                            />{" "}
                            مرا به خاطر بسپار
                        </label>
                        <Link
                            className="font-bold text-violet-400"
                            href="/forgot-password"
                        >
                            فراموشی رمز
                        </Link>
                    </div>
                    <button
                        className={authButton}
                        disabled={password.processing}
                    >
                        ورود به حساب
                    </button>
                </form>
            )}
            <Link
                className="mt-3 block text-center text-xs font-bold text-violet-400"
                href="/register"
            >
                ساخت حساب جدید
            </Link>
        </AuthShell>
    );
}
