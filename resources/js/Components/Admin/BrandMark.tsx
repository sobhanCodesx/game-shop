interface BrandMarkProps {
    compact?: boolean;
}

export default function BrandMark({ compact = false }: BrandMarkProps) {
    return (
        <div className="flex items-center gap-3">
            <img
                alt="لوگوی PLAY NEXUS"
                className="size-10 shrink-0 rounded-xl object-cover shadow-lg shadow-cyan-500/20"
                src="/logo.png"
            />
            {!compact && (
                <div>
                    <p className="text-base font-black tracking-tight text-white">
                        PLAY NEXUS
                    </p>
                    <p className="text-[10px] font-semibold tracking-[0.22em] text-slate-500">
                        ADMIN CONSOLE
                    </p>
                </div>
            )}
        </div>
    );
}
