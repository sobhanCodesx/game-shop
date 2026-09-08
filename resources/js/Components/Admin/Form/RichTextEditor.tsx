import { Button, ButtonGroup } from "@heroui/react";
import {
    Bold,
    Eraser,
    Heading2,
    Heading3,
    Heading4,
    Italic,
    Link2,
    List,
    ListOrdered,
    Pilcrow,
    Quote,
    Redo2,
    Underline,
    Unlink,
    Undo2,
} from "lucide-react";
import { useEffect, useRef, useState } from "react";

interface RichTextEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    minHeight?: number;
    enableBlocks?: boolean;
}

const commands = [
    { command: "bold", label: "پررنگ", icon: Bold },
    { command: "italic", label: "مورب", icon: Italic },
    { command: "underline", label: "زیرخط", icon: Underline },
    { command: "insertUnorderedList", label: "فهرست نقطه‌ای", icon: List },
    {
        command: "insertOrderedList",
        label: "فهرست شماره‌ای",
        icon: ListOrdered,
    },
] as const;

export default function RichTextEditor({
    value,
    onChange,
    placeholder = "توضیحات را بنویسید…",
    minHeight = 220,
    enableBlocks = false,
}: RichTextEditorProps) {
    const editorRef = useRef<HTMLDivElement>(null);
    const [linkEditorOpen, setLinkEditorOpen] = useState(false);
    const [linkUrl, setLinkUrl] = useState("");
    const savedSelection = useRef<Range | null>(null);
    const focusedRef = useRef(false);

    useEffect(() => {
        const editor = editorRef.current;
        if (editor && !focusedRef.current && editor.innerHTML !== value)
            editor.innerHTML = value;
    }, [value]);

    const execute = (command: string, argument?: string) => {
        editorRef.current?.focus();
        document.execCommand(command, false, argument);
        onChange(editorRef.current?.innerHTML ?? "");
    };

    const openLinkEditor = () => {
        const selection = window.getSelection();
        const range = selection?.rangeCount ? selection.getRangeAt(0) : null;
        if (
            range &&
            editorRef.current?.contains(range.commonAncestorContainer)
        ) {
            savedSelection.current = range.cloneRange();
        }
        setLinkEditorOpen(true);
    };

    const applyLink = () => {
        const value = linkUrl.trim();
        if (!value) return;
        const href = /^(https?:\/\/|\/|#|mailto:)/i.test(value)
            ? value
            : `https://${value}`;
        const selection = window.getSelection();
        selection?.removeAllRanges();
        if (savedSelection.current) selection?.addRange(savedSelection.current);
        execute("createLink", href);
        setLinkUrl("");
        setLinkEditorOpen(false);
    };

    return (
        <div className="overflow-hidden rounded-xl border border-slate-700 bg-slate-950/40 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
            <div className="flex flex-wrap items-center gap-2 border-b border-slate-700 bg-slate-900/80 p-2">
                {enableBlocks && (
                    <ButtonGroup>
                        <Button
                            aria-label="پاراگراف"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("formatBlock", "p")}
                            size="sm"
                            variant="ghost"
                        >
                            <Pilcrow size={16} />
                        </Button>
                        <Button
                            aria-label="عنوان سطح دو"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("formatBlock", "h2")}
                            size="sm"
                            variant="ghost"
                        >
                            <Heading2 size={16} />
                        </Button>
                        <Button
                            aria-label="عنوان سطح سه"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("formatBlock", "h3")}
                            size="sm"
                            variant="ghost"
                        >
                            <Heading3 size={16} />
                        </Button>
                        <Button
                            aria-label="عنوان سطح چهار"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("formatBlock", "h4")}
                            size="sm"
                            variant="ghost"
                        >
                            <Heading4 size={16} />
                        </Button>
                        <Button
                            aria-label="نقل‌قول"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("formatBlock", "blockquote")}
                            size="sm"
                            variant="ghost"
                        >
                            <Quote size={16} />
                        </Button>
                    </ButtonGroup>
                )}
                <ButtonGroup>
                    {commands.map(({ command, label, icon: Icon }) => (
                        <Button
                            aria-label={label}
                            isIconOnly
                            key={command}
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute(command)}
                            size="sm"
                            variant="ghost"
                        >
                            <Icon size={16} />
                        </Button>
                    ))}
                </ButtonGroup>
                {enableBlocks && (
                    <ButtonGroup>
                        <Button
                            aria-label="افزودن لینک"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={openLinkEditor}
                            size="sm"
                            variant="ghost"
                        >
                            <Link2 size={16} />
                        </Button>
                        <Button
                            aria-label="حذف لینک"
                            isIconOnly
                            onMouseDown={(event) => event.preventDefault()}
                            onPress={() => execute("unlink")}
                            size="sm"
                            variant="ghost"
                        >
                            <Unlink size={16} />
                        </Button>
                    </ButtonGroup>
                )}
                <ButtonGroup>
                    <Button
                        aria-label="واگرد"
                        isIconOnly
                        onPress={() => execute("undo")}
                        size="sm"
                        variant="ghost"
                    >
                        <Undo2 size={16} />
                    </Button>
                    <Button
                        aria-label="از نو"
                        isIconOnly
                        onPress={() => execute("redo")}
                        size="sm"
                        variant="ghost"
                    >
                        <Redo2 size={16} />
                    </Button>
                    <Button
                        aria-label="پاک‌کردن قالب‌بندی"
                        isIconOnly
                        onPress={() => execute("removeFormat")}
                        size="sm"
                        variant="ghost"
                    >
                        <Eraser size={16} />
                    </Button>
                </ButtonGroup>
            </div>
            {enableBlocks && linkEditorOpen && (
                <div className="flex items-center gap-2 border-b border-slate-700 bg-slate-900 px-3 py-2">
                    <input
                        aria-label="نشانی لینک"
                        autoFocus
                        className="h-9 min-w-0 flex-1 rounded-lg border border-slate-700 bg-slate-950 px-3 text-sm text-slate-200 outline-none focus:border-primary"
                        dir="ltr"
                        onChange={(event) => setLinkUrl(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === "Enter") {
                                event.preventDefault();
                                applyLink();
                            }
                            if (event.key === "Escape")
                                setLinkEditorOpen(false);
                        }}
                        placeholder="https://example.com"
                        value={linkUrl}
                    />
                    <Button onPress={applyLink} size="sm" variant="primary">
                        ثبت لینک
                    </Button>
                    <Button
                        onPress={() => setLinkEditorOpen(false)}
                        size="sm"
                        variant="ghost"
                    >
                        لغو
                    </Button>
                </div>
            )}
            <div
                className="rich-text-editor prose prose-invert max-w-none px-4 py-3 text-sm leading-8 text-slate-200 empty:before:pointer-events-none empty:before:text-slate-500 empty:before:content-[attr(data-placeholder)] focus:outline-none"
                aria-multiline="true"
                contentEditable={true}
                data-placeholder={placeholder}
                onBlur={(event) => {
                    focusedRef.current = false;
                    onChange(event.currentTarget.innerHTML);
                }}
                onFocus={() => {
                    focusedRef.current = true;
                }}
                onInput={(event) => onChange(event.currentTarget.innerHTML)}
                ref={editorRef}
                role="textbox"
                style={{ minHeight }}
                suppressContentEditableWarning
                tabIndex={0}
            />
        </div>
    );
}
