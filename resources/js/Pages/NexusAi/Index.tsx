import { Head } from "@inertiajs/react";
import { Bot, MessageCircleMore, ShieldCheck, Sparkles } from "lucide-react";

import StorefrontLayout from "../../Layouts/StorefrontLayout";

type NexusAiConfig = {
    enabled: boolean;
    show_in_nav: boolean;
    title: string;
    description: string;
    nav_label: string;
    launcher_label: string;
    welcome_title: string;
    welcome_text: string;
    status_text: string;
};

export default function NexusAiIndex({ nexusAi }: { nexusAi: NexusAiConfig }) {
    return (
        <StorefrontLayout>
            <Head title={`${nexusAi.title} | PlayNexus`} />

            <main className="mx-auto w-full max-w-6xl px-4 py-8 sm:px-6 sm:py-12">
                <section className="relative overflow-hidden rounded-[32px] border border-[var(--store-border)] bg-[var(--store-surface)] p-6 sm:p-10 lg:p-14">
                    <div className="pointer-events-none absolute -right-24 -top-28 size-96 rounded-full bg-violet-500/10 blur-3xl" />
                    <div className="pointer-events-none absolute -bottom-28 -left-24 size-96 rounded-full bg-cyan-500/10 blur-3xl" />

                    <div className="relative mx-auto max-w-3xl text-center">
                        <div className="mx-auto grid size-16 place-items-center rounded-[22px] bg-gradient-to-br from-violet-600 via-indigo-600 to-cyan-500 text-white shadow-[0_18px_55px_rgba(99,102,241,.28)]">
                            <Bot size={30} strokeWidth={2.1} />
                        </div>

                        <div className="mt-6 flex items-center justify-center gap-2">
                            <Sparkles size={14} className="text-violet-500" />
                            <span className="text-[10px] font-black tracking-[.18em] text-[var(--store-muted)]">
                                PLAYNEXUS INTELLIGENCE
                            </span>
                        </div>

                        <h1 className="mt-3 text-2xl font-black text-[var(--store-text)] sm:text-4xl">
                            {nexusAi.title}
                        </h1>

                        <p className="mx-auto mt-4 max-w-2xl text-sm leading-8 text-[var(--store-muted)]">
                            {nexusAi.description}
                        </p>

                        <div className="mx-auto mt-7 grid max-w-2xl gap-3 sm:grid-cols-2">
                            <div className="flex items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/70 p-4 text-right">
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-violet-500/10 text-violet-500">
                                    <MessageCircleMore size={18} />
                                </span>
                                <div>
                                    <strong className="block text-xs text-[var(--store-text)]">
                                        چت Native داخل PlayNexus
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        بدون iframe و بدون اسکرول دوگانه
                                    </span>
                                </div>
                            </div>

                            <div className="flex items-center gap-3 rounded-2xl border border-[var(--store-border)] bg-[var(--store-bg)]/70 p-4 text-right">
                                <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-emerald-500/10 text-emerald-500">
                                    <ShieldCheck size={18} />
                                </span>
                                <div>
                                    <strong className="block text-xs text-[var(--store-text)]">
                                        اتصال امن به Nexus AI
                                    </strong>
                                    <span className="mt-1 block text-[10px] leading-5 text-[var(--store-muted)]">
                                        Router سرور کلیدها و fallback را مدیریت می‌کند
                                    </span>
                                </div>
                            </div>
                        </div>

                        <p className="mt-7 text-[11px] font-bold text-[var(--store-muted)]">
                            پنل Nexus AI همین حالا باز شده؛ اگر بسته شد، از دکمه شناور پایین صفحه دوباره بازش کن.
                        </p>
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
