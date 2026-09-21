import { Button, Card, Input } from "@heroui/react";
import { useForm } from "@inertiajs/react";
import { CheckCircle2, Mail } from "lucide-react";
import type { FormEvent } from "react";

export default function NewsletterSignup({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    const { data, setData, post, processing, errors, recentlySuccessful, reset } =
        useForm({
            email: "",
        });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        post("/newsletter", {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <section className="mx-auto max-w-[1536px] px-3 py-8 sm:px-4 sm:py-14">
            <Card
                className="storefront-dark-panel overflow-hidden border border-indigo-500/30 bg-gradient-to-l from-indigo-950 to-slate-900"
                variant="secondary"
            >
                <Card.Content className="flex flex-col gap-5 p-5 sm:p-7 md:flex-row md:items-center md:justify-between md:p-10">
                    <div>
                        <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-black text-indigo-200">
                            <Mail size={13} />
                            NEXUS NEWSLETTER
                        </div>
                        <h2 className="text-xl font-black text-white sm:text-2xl">
                            {title}
                        </h2>
                        <p className="mt-2 max-w-xl text-sm leading-6 text-slate-300 sm:mt-3 sm:text-base sm:leading-7">
                            {description}
                        </p>
                    </div>

                    <form
                        className="w-full min-w-0 max-w-md"
                        onSubmit={submit}
                    >
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-[minmax(0,1fr)_auto]">
                            <Input
                                aria-label="ایمیل خبرنامه"
                                className="min-w-0"
                                dir="ltr"
                                aria-invalid={Boolean(errors.email)}
                                onChange={(event) =>
                                    setData("email", event.target.value)
                                }
                                placeholder="you@example.com"
                                type="email"
                                value={data.email}
                            />
                            <Button
                                className="w-full shrink-0 sm:w-auto"
                                isDisabled={processing}
                                type="submit"
                                variant="primary"
                            >
                                {processing ? "در حال ثبت…" : "عضویت"}
                            </Button>
                        </div>
                        {errors.email && (
                            <p
                                className="mt-2 text-xs font-bold text-rose-300"
                                role="alert"
                            >
                                {errors.email}
                            </p>
                        )}
                        {recentlySuccessful && (
                            <p
                                className="mt-2 flex items-center gap-1.5 text-xs font-bold text-emerald-300"
                                role="status"
                            >
                                <CheckCircle2 size={14} />
                                عضویت انجام شد؛ از این به بعد خبرهای مهم را دریافت می‌کنی.
                            </p>
                        )}
                    </form>
                </Card.Content>
            </Card>
        </section>
    );
}
