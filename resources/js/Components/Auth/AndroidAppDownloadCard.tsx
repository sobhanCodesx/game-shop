import { Download, Smartphone } from "lucide-react";

export default function AndroidAppDownloadCard() {
    return (
        <a
            className="group mb-3 flex items-center gap-3 rounded-2xl border border-white/[.09] bg-white/[.025] px-3.5 py-3 transition duration-200 hover:border-emerald-400/30 hover:bg-emerald-400/[.035] focus:outline-none focus:ring-2 focus:ring-emerald-400/40 sm:px-4"
            href="/download/android"
        >
            <span className="grid size-10 shrink-0 place-items-center rounded-xl border border-emerald-400/20 bg-emerald-400/[.06] text-emerald-300">
                <Smartphone size={19} strokeWidth={2} />
            </span>

            <span className="min-w-0 flex-1">
                <span className="flex items-center gap-2">
                    <strong className="truncate text-xs font-black text-slate-100 sm:text-sm">
                        اپلیکیشن اندروید PLAY NEXUS
                    </strong>
                    <span className="shrink-0 text-[9px] font-bold text-emerald-300/80">
                        APK
                    </span>
                </span>
                <span className="mt-0.5 block text-[10px] text-slate-500 sm:text-[11px]">
                    دانلود مستقیم نسخه اندروید
                </span>
            </span>

            <span className="grid size-9 shrink-0 place-items-center rounded-xl border border-white/[.08] text-slate-500 transition group-hover:border-emerald-400/20 group-hover:text-emerald-300">
                <Download size={16} strokeWidth={2.2} />
            </span>
        </a>
    );
}
