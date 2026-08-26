import { Button, Card, Checkbox, Input } from '@heroui/react';
import { Head, useForm } from '@inertiajs/react';
import { Eye, Gamepad2, ShieldCheck } from 'lucide-react';
import { type FormEvent, useState } from 'react';

import BrandMark from '../../../Components/Admin/BrandMark';

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function Login() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post('/admin/login');
    };

    return (
        <>
            <Head title="ورود به پنل مدیریت" />

            <main className="admin-auth relative grid min-h-screen overflow-hidden bg-background lg:grid-cols-[1.05fr_0.95fr]">
                <section className="relative hidden overflow-hidden border-l border-slate-800/80 lg:flex lg:flex-col lg:justify-between lg:p-12 xl:p-16">
                    <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(99,102,241,0.2),transparent_35%),radial-gradient(circle_at_80%_75%,rgba(14,165,233,0.12),transparent_30%)]" />
                    <div className="absolute inset-0 opacity-20 [background-image:linear-gradient(rgba(148,163,184,.08)_1px,transparent_1px),linear-gradient(90deg,rgba(148,163,184,.08)_1px,transparent_1px)] [background-size:40px_40px]" />

                    <div className="relative z-10">
                        <BrandMark />
                    </div>

                    <div className="relative z-10 max-w-xl">
                        <div className="mb-8 grid size-16 place-items-center rounded-2xl border border-indigo-400/20 bg-indigo-500/15 text-indigo-300 shadow-2xl shadow-indigo-500/20">
                            <Gamepad2 size={31} />
                        </div>
                        <h1 className="text-4xl font-black leading-tight text-white xl:text-5xl">
                            مرکز فرماندهی
                            <span className="mt-2 block bg-gradient-to-l from-indigo-300 to-sky-300 bg-clip-text text-transparent">اکوسیستم گیمینگ</span>
                        </h1>
                        <p className="mt-6 max-w-lg text-base leading-8 text-slate-400">
                            فروشگاه، محتوا، کامیونیتی و عملیات پلتفرم را از یک کنسول سریع و یکپارچه مدیریت کنید.
                        </p>
                    </div>

                    <div className="relative z-10 flex items-center gap-3 text-xs text-slate-600">
                        <ShieldCheck size={16} />
                        اتصال امن و محافظت‌شده با Laravel Session
                    </div>
                </section>

                <section className="flex items-center justify-center px-5 py-10 sm:px-8">
                    <div className="w-full max-w-md">
                        <div className="mb-9 lg:hidden">
                            <BrandMark />
                        </div>

                        <div className="mb-8">
                            <p className="text-sm font-bold text-indigo-400">خوش آمدید</p>
                            <h2 className="mt-2 text-3xl font-black text-white">ورود به پنل مدیریت</h2>
                            <p className="mt-3 text-sm leading-6 text-slate-500">برای ادامه، اطلاعات حساب مدیریتی خود را وارد کنید.</p>
                        </div>

                        <Card className="border border-slate-800/80 bg-slate-900/55 shadow-2xl shadow-black/30" variant="secondary">
                            <Card.Content className="p-6 sm:p-8">
                                <form className="space-y-5" onSubmit={submit}>
                                    <Input
                                        aria-label="ایمیل"
                                        onChange={(event) => setData('email', event.target.value)}
                                        placeholder="admin@example.com"
                                        type="email"
                                        value={data.email}
                                    />
                                    {errors.email && <p className="-mt-3 text-xs text-red-400">{errors.email}</p>}

                                    <div className="relative">
                                        <Input
                                            aria-label="رمز عبور"
                                            onChange={(event) => setData('password', event.target.value)}
                                            placeholder="رمز عبور"
                                            type={showPassword ? 'text' : 'password'}
                                            value={data.password}
                                        />
                                        <button
                                            aria-label={showPassword ? 'پنهان‌کردن رمز' : 'نمایش رمز'}
                                            className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300"
                                            onClick={() => setShowPassword((visible) => !visible)}
                                            type="button"
                                        >
                                            <Eye size={17} />
                                        </button>
                                    </div>

                                    <Checkbox isSelected={data.remember} onChange={(selected) => setData('remember', selected)}>
                                        <Checkbox.Control>
                                            <Checkbox.Indicator />
                                        </Checkbox.Control>
                                        <Checkbox.Content>مرا به خاطر بسپار</Checkbox.Content>
                                    </Checkbox>

                                    <Button className="w-full" isDisabled={processing} type="submit" variant="primary">
                                        {processing ? 'در حال بررسی...' : 'ورود امن به پنل'}
                                    </Button>
                                </form>
                            </Card.Content>
                        </Card>

                        <p className="mt-6 text-center text-xs leading-6 text-slate-600">
                            تلاش‌های ناموفق محدود و برای بررسی امنیتی ثبت می‌شوند.
                        </p>
                    </div>
                </section>
            </main>
        </>
    );
}
