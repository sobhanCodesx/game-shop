import { Button } from "@heroui/react";
import { ImagePlus, Images } from "lucide-react";
import { useEffect, useState } from "react";

export default function ImageUploadField({ label, existingUrl, file, onChange, error, aspect = "video", required = false }: { label: string; existingUrl: string | null; file?: File; onChange: (file?: File) => void; error?: string; aspect?: "square" | "video"; required?: boolean }) {
    const [preview, setPreview] = useState<string | null>(existingUrl);
    useEffect(() => {
        if (!file) { setPreview(existingUrl); return; }
        const url = URL.createObjectURL(file);
        setPreview(url);
        return () => URL.revokeObjectURL(url);
    }, [existingUrl, file]);

    return <div>
        <div className="mb-2 flex items-center justify-between"><strong className="text-xs text-slate-300">{label}{required && <span className="text-rose-400"> *</span>}</strong><span className="text-[10px] text-slate-500">JPG، PNG یا WebP</span></div>
        <div className={`overflow-hidden rounded-2xl border border-dashed border-slate-700 bg-slate-950 ${aspect === "square" ? "aspect-square" : "aspect-video"}`}>
            {preview ? <img alt={`پیش‌نمایش ${label}`} className="size-full object-cover" src={preview} /> : <span className="grid size-full place-items-center text-slate-600"><Images size={40} /></span>}
        </div>
        <label className="mt-3 block"><input accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => { onChange(event.target.files?.[0]); event.target.value = ""; }} type="file" /><Button className="pointer-events-none w-full" variant="secondary"><ImagePlus size={17} />{preview ? "جایگزینی تصویر" : "انتخاب تصویر"}</Button></label>
        {error && <small className="mt-2 block text-rose-400">{error}</small>}
    </div>;
}
