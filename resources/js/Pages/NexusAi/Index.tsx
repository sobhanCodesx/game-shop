import { Head } from "@inertiajs/react";
import { Bot, ExternalLink, ShieldCheck, Sparkles } from "lucide-react";

import StorefrontLayout from "../../Layouts/StorefrontLayout";

type NexusAiConfig = {
    enabled: boolean;
    show_in_nav: boolean;
    title: string;
    description: string;
    nav_label: string;
    iframe_url: string;
    min_height: number;
    status_text: string;
};

export default function NexusAiIndex({ nexusAi }: { nexusAi: NexusAiConfig }) {
    return (
        <StorefrontLayout>
            <Head title={`${nexusAi.title} | PlayNexus`} />

            <main className="mx-auto w-full max-w-[1500px] px-0 py-0 sm:px-4 sm:py-5 lg:px-6">
                <section className="overflow-hidden border-y border-[var(--store-border)] bg-[var(--store-surface)] sm:rounded-[30px] sm:border">
                    <header className="relative overflow-hidden border-b border-[var(--store-border)] px-5 py-5 sm:px-7 lg:px-9">
                        <div className="pointer-events-none absolute -left-20 -top-24 size-72 rounded-full bg-violet-500/10 blur-3xl" />
                        <div className="pointer-events-none absolute -right-16 -bottom-28 size-72 rounded-full bg-cyan-500/10 blur-3xl" />

                        <div className="relative flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex min-w-0 items-center gap-4">
                                <span className="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-violet-600 to-cyan-600 text-white shadow-lg shadow-violet-500/20">
                                    <Bot size={23} />
                                </span>
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <h1 className="text-xl font-black text-[var(--store-text)] sm:text-2xl">
                                            {nexusAi.title}
                                        </h1>
                                        {nexusAi.status_text && (
                                            <span className="rounded-full border border-emerald-500/15 bg-emerald-500/[.08] px-2.5 py-1 text-[9px] font-black text-emerald-500">
                                                {nexusAi.status_text}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-2 max-w-3xl text-xs leading-6 text-[var(--store-muted)] sm:text-sm">
                                        {nexusAi.description}
                                    </p>
                                </div>
                            </div>

                            <div className="flex shrink-0 items-center gap-2">
                                <span className="hidden items-center gap-2 rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] px-3 py-2 text-[10px] font-bold text-[var(--store-muted)] md:flex">
                                    <ShieldCheck size={14} className="text-emerald-500" />
                                    اتصال امن PlayNexus
                                </span>
                                <a
                                    aria-label="باز کردن Nexus AI در صفحه جدا"
                                    className="grid size-10 place-items-center rounded-xl border border-[var(--store-border)] bg-[var(--store-bg)] text-[var(--store-muted)] transition hover:text-indigo-500"
                                    href={nexusAi.iframe_url}
                                    rel="noreferrer"
                                    target="_blank"
                                >
                                    <ExternalLink size={17} />
                                </a>
                            </div>
                        </div>
                    </header>

                    <div className="relative bg-[#050711]">
                        <div className="pointer-events-none absolute left-1/2 top-0 z-10 flex -translate-x-1/2 items-center gap-2 rounded-b-xl border-x border-b border-white/5 bg-black/25 px-3 py-1.5 text-[8px] font-black tracking-wide text-white/35 backdrop-blur">
                            <Sparkles size={10} />
                            NEXUS INTELLIGENCE
                        </div>
                        <iframe
                            allow="clipboard-write"
                            className="block w-full border-0"
                            loading="eager"
                            src={nexusAi.iframe_url}
                            style={{ minHeight: `${nexusAi.min_height}px`, height: "calc(100dvh - 190px)" }}
                            title={nexusAi.title}
                        />
                    </div>
                </section>
            </main>
        </StorefrontLayout>
    );
}
