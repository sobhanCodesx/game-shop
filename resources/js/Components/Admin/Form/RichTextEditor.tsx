import { Button, ButtonGroup } from "@heroui/react";
import {
    Bold,
    Eraser,
    Italic,
    List,
    ListOrdered,
    Redo2,
    Underline,
    Undo2,
} from "lucide-react";
import { useEffect, useRef } from "react";

interface RichTextEditorProps {
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    minHeight?: number;
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
}: RichTextEditorProps) {
    const editorRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const editor = editorRef.current;
        if (
            editor &&
            editor !== document.activeElement &&
            editor.innerHTML !== value
        )
            editor.innerHTML = value;
    }, [value]);

    const execute = (command: string) => {
        editorRef.current?.focus();
        document.execCommand(command);
        onChange(editorRef.current?.innerHTML ?? "");
    };

    return (
        <div className="overflow-hidden rounded-xl border border-slate-700 bg-slate-950/40 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
            <div className="flex flex-wrap items-center gap-2 border-b border-slate-700 bg-slate-900/80 p-2">
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
            <div
                className="rich-text-editor prose prose-invert max-w-none px-4 py-3 text-sm leading-8 text-slate-200 empty:before:pointer-events-none empty:before:text-slate-500 empty:before:content-[attr(data-placeholder)] focus:outline-none"
                contentEditable
                data-placeholder={placeholder}
                onInput={(event) => onChange(event.currentTarget.innerHTML)}
                ref={editorRef}
                role="textbox"
                style={{ minHeight }}
                suppressContentEditableWarning
            />
        </div>
    );
}
