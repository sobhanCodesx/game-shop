import { Bold, Heading2, Heading3, ImagePlus, Italic, Link2, List, ListOrdered, Quote, RotateCcw, UploadCloud } from "lucide-react";
import { useEffect, useRef, useState, type ChangeEvent, type DragEvent } from "react";
import { uploadFileInChunks } from "../../services/chunkedUpload";

interface UploadedStoryImage { path: string; url: string; }
const csrf = () => document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";

export async function uploadGameStoryImage(file: File, progress?: (value: number) => void): Promise<UploadedStoryImage> {
    if (!["image/jpeg", "image/png", "image/webp"].includes(file.type) || file.size > 8 * 1024 * 1024)
        throw new Error("فقط تصاویر JPG، PNG و WebP حداکثر ۸ مگابایت مجاز هستند.");
    const token = await uploadFileInChunks(file, result => progress?.(Math.round(result.percentage * .85)));
    const response = await fetch("/admin/game-stories/inline-image", {
        method: "POST",
        headers: { "Content-Type": "application/json", "Accept": "application/json", "X-CSRF-TOKEN": csrf() },
        body: JSON.stringify({ upload_token: token }),
        credentials: "same-origin",
    });
    if (!response.ok) throw new Error("ذخیره تصویر روی سرور با خطا مواجه شد.");
    const result = await response.json() as UploadedStoryImage;
    if (!result.path || !result.url) throw new Error("آدرس تصویر معتبر دریافت نشد.");
    progress?.(100);
    return result;
}

function escapeHTML(text: string): string {
    return text.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#39;");
}

export default function GameStoryEditor({ value, onChange, onImageUploaded }: { value: string; onChange: (html: string) => void; onImageUploaded?: (image: UploadedStoryImage) => void }) {
    const editor = useRef<HTMLDivElement>(null);
    const fileInput = useRef<HTMLInputElement>(null);
    const savedRange = useRef<Range | null>(null);
    const focused = useRef(false);
    const [busy, setBusy] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState("");
    const [pendingImage, setPendingImage] = useState<File | null>(null);
    const [alt, setAlt] = useState("");
    const [linkUrl, setLinkUrl] = useState("");
    const [linkOpen, setLinkOpen] = useState(false);

    useEffect(() => { if (editor.current && !focused.current && editor.current.innerHTML !== value) editor.current.innerHTML = value; }, [value]);

    const rememberSelection = () => {
        const selection = window.getSelection();
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
        if (range && editor.current?.contains(range.commonAncestorContainer)) savedRange.current = range.cloneRange();
    };
    const restoreSelection = () => {
        editor.current?.focus();
        if (savedRange.current) {
            const selection = window.getSelection();
            selection?.removeAllRanges();
            try { selection?.addRange(savedRange.current); } catch { /* DOM changed. */ }
        }
    };
    const command = (name: string, arg?: string) => {
        restoreSelection();
        document.execCommand(name, false, arg);
        onChange(editor.current?.innerHTML || "");
        rememberSelection();
    };
    const chooseFile = (file?: File) => {
        if (!file) return;
        rememberSelection();
        setAlt(file.name.replace(/\.[^.]+$/, "").replaceAll("-", " "));
        setPendingImage(file);
        setError("");
    };
    const upload = async () => {
        if (!pendingImage || busy) return;
        setBusy(true); setError(""); setProgress(0);
        try {
            const image = await uploadGameStoryImage(pendingImage, setProgress);
            const safeAlt = escapeHTML(alt.trim() || "تصویر مرتبط با روایت بازی");
            restoreSelection();
            document.execCommand("insertHTML", false, '<figure><img src="' + escapeHTML(image.url) + '" alt="' + safeAlt + '"><figcaption>' + safeAlt + '</figcaption></figure><p><br></p>');
            onChange(editor.current?.innerHTML || "");
            onImageUploaded?.(image);
            setPendingImage(null);
            rememberSelection();
        } catch (caught) {
            setError(caught instanceof Error ? caught.message : "آپلود انجام نشد.");
        } finally { setBusy(false); setProgress(0); }
    };
    const button = (label: string, icon: React.ReactNode, handle: () => void) =>
        <button aria-label={label} className="grid size-9 place-items-center rounded-lg border border-white/5 text-slate-300 transition hover:border-amber-400/40 hover:bg-amber-400/10 hover:text-amber-200" key={label} onMouseDown={event => event.preventDefault()} onClick={handle} type="button" title={label}>{icon}</button>;
    const drop = (event: DragEvent) => { event.preventDefault(); chooseFile(event.dataTransfer.files[0]); };

    return <div className="overflow-hidden rounded-[22px] border border-amber-600/25 bg-[#141313] shadow-[0_25px_85px_rgba(0,0,0,.25)]" dir="rtl">
        <div className="sticky top-0 z-10 flex flex-wrap items-center gap-1.5 border-b border-white/10 bg-[#1e1c1a]/95 p-3 backdrop-blur-xl">
            {button("تیتر فصل", <Heading2 size={17}/>, () => command("formatBlock", "h2"))}
            {button("زیرعنوان", <Heading3 size={17}/>, () => command("formatBlock", "h3"))}
            {button("متن عادی", <RotateCcw size={17}/>, () => command("formatBlock", "p"))}
            <span className="mx-1 h-6 w-px bg-white/10"/>
            {button("بولد", <Bold size={17}/>, () => command("bold"))}
            {button("مورب", <Italic size={17}/>, () => command("italic"))}
            {button("نقل قول", <Quote size={17}/>, () => command("formatBlock", "blockquote"))}
            {button("فهرست", <List size={17}/>, () => command("insertUnorderedList"))}
            {button("فهرست عددی", <ListOrdered size={17}/>, () => command("insertOrderedList"))}
            {button("درج لینک", <Link2 size={17}/>, () => { rememberSelection(); setLinkOpen(true); })}
            <button className="mr-auto inline-flex items-center gap-2 rounded-xl border border-amber-300/25 bg-amber-500/10 px-3 py-2 text-xs font-bold text-amber-200 transition hover:bg-amber-500/20" onMouseDown={event => event.preventDefault()} onClick={() => { rememberSelection(); fileInput.current?.click(); }} type="button"><ImagePlus size={17}/> تصویر وسط متن</button>
            <input accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event: ChangeEvent<HTMLInputElement>) => { chooseFile(event.target.files?.[0]); event.target.value = ""; }} ref={fileInput} type="file" />
        </div>
        {linkOpen && <div className="flex gap-2 border-b border-white/10 bg-[#24201a] p-3"><input aria-label="نشانی لینک" className="min-w-0 flex-1 rounded-lg border border-white/15 bg-black/20 p-2 text-sm text-white" dir="ltr" onChange={e => setLinkUrl(e.target.value)} placeholder="https://..." value={linkUrl}/><button className="rounded-lg bg-amber-700 px-4 text-sm text-white" onClick={() => { if (/^https?:\/\/|^\//i.test(linkUrl)) command("createLink", linkUrl); setLinkOpen(false); setLinkUrl(""); }} type="button">افزودن</button><button onClick={() => setLinkOpen(false)} type="button">لغو</button></div>}
        <div aria-label="متن روایت، تصاویر را می‌توانید میان پاراگراف‌ها بگذارید" className="min-h-[470px] px-6 py-8 text-[16px] leading-[2.2] text-[#eee2ca] outline-none sm:px-10 [&_h2]:my-6 [&_h2]:text-2xl [&_h2]:font-black [&_h3]:my-4 [&_h3]:text-xl [&_h3]:font-bold [&_img]:my-5 [&_img]:max-h-[500px] [&_img]:w-full [&_img]:rounded-xl [&_img]:object-contain [&_figcaption]:text-center [&_figcaption]:text-xs [&_figcaption]:text-amber-300/60 [&_p]:mb-4" contentEditable data-placeholder="داستان از اینجا آغاز می‌شود…" onBlur={event => { focused.current = false; onChange(event.currentTarget.innerHTML); }} onFocus={() => { focused.current = true; }} onInput={event => { onChange(event.currentTarget.innerHTML); rememberSelection(); }} onKeyUp={rememberSelection} onMouseUp={rememberSelection} onPaste={event => { event.preventDefault(); command("insertText", event.clipboardData.getData("text/plain")); }} onDrop={drop} onDragOver={event => event.preventDefault()} ref={editor} role="textbox" suppressContentEditableWarning tabIndex={0}/>
        {error && <p className="border-t border-red-500/20 p-3 text-xs text-red-300">{error}</p>}
        <div className="border-t border-white/10 bg-white/[.02] px-4 py-3 text-xs text-stone-400"><BookOpenIcon/> برای ساخت فصل جدید «تیتر فصل» را بزن؛ تصاویر را می‌توانی هرجای متن درج کنی.</div>
        {pendingImage && <div className="border-t border-amber-500/20 bg-[#231c14] p-4">
            <p className="mb-3 flex items-center gap-2 text-sm font-bold text-amber-100"><UploadCloud size={17}/> تصویر: {pendingImage.name}</p>
            <label className="mb-2 block text-xs text-stone-300" htmlFor="story-image-alt">توضیح تصویر برای دسترس‌پذیری و سئو (alt)</label>
            <input className="mb-3 w-full rounded-xl border border-amber-500/20 bg-black/20 p-3 text-sm text-white" id="story-image-alt" onChange={event => setAlt(event.target.value)} value={alt}/>
            {busy && <div className="mb-3 h-1 overflow-hidden rounded-full bg-stone-700"><div className="h-full bg-amber-400" style={{width:progress+"%"}}/></div>}
            <div className="flex gap-3"><button className="rounded-xl bg-amber-600 px-4 py-2 text-xs font-bold text-white disabled:opacity-50" disabled={busy} onClick={upload} type="button">{busy ? "در حال آپلود…" : "ثبت تصویر در همین نقطه"}</button><button className="text-xs text-stone-300" disabled={busy} onClick={() => setPendingImage(null)} type="button">انصراف</button></div>
        </div>}
    </div>;
}
function BookOpenIcon() { return <span aria-hidden="true">✦</span>; }
