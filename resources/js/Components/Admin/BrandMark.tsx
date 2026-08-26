import { Gamepad2 } from 'lucide-react';

interface BrandMarkProps {
    compact?: boolean;
}

export default function BrandMark({ compact = false }: BrandMarkProps) {
    return (
        <div className="flex items-center gap-3">
            <div className="grid size-10 shrink-0 place-items-center rounded-xl bg-indigo-500 text-white shadow-lg shadow-indigo-500/25">
                <Gamepad2 aria-hidden="true" size={22} />
            </div>
            {!compact && (
                <div>
                    <p className="text-base font-black tracking-tight text-white">NEXUS PLAY</p>
                    <p className="text-[10px] font-semibold tracking-[0.22em] text-slate-500">ADMIN CONSOLE</p>
                </div>
            )}
        </div>
    );
}
