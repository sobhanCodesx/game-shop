import { loadLanguage } from "@uiw/codemirror-extensions-langs";
import CodeMirror from "@uiw/react-codemirror";
import { useMemo } from "react";

type Props = {
    filename: string;
    value: string;
    onChange: (value: string) => void;
};

const extensionLanguage: Record<string, string> = {
    php: "php",
    js: "js",
    cjs: "js",
    mjs: "js",
    jsx: "jsx",
    ts: "ts",
    tsx: "tsx",
    json: "json",
    html: "html",
    htm: "html",
    css: "css",
    scss: "sass",
    sass: "sass",
    less: "less",
    md: "markdown",
    markdown: "markdown",
    xml: "xml",
    svg: "xml",
    sql: "sql",
    py: "python",
    sh: "shell",
    bash: "shell",
    yml: "yaml",
    yaml: "yaml",
    vue: "vue",
    java: "java",
    c: "c",
    h: "c",
    cpp: "cpp",
    cc: "cpp",
    hpp: "cpp",
    cs: "csharp",
    rs: "rust",
    go: "go",
    toml: "toml",
};

function languageFor(filename: string) {
    const lowered = filename.toLowerCase();
    const specialNames: Record<string, string> = {
        ".env": "properties",
        ".env.example": "properties",
        ".env.local": "properties",
        "dockerfile": "dockerfile",
    };

    const special = specialNames[lowered];
    const extension = lowered.includes(".") ? lowered.split(".").pop() ?? "" : "";
    const languageName = special ?? extensionLanguage[extension];
    if (!languageName) return null;

    try {
        return loadLanguage(languageName as Parameters<typeof loadLanguage>[0]);
    } catch {
        return null;
    }
}

export default function ProjectCodeEditor({ filename, value, onChange }: Props) {
    const extensions = useMemo(() => {
        const language = languageFor(filename);
        return language ? [language] : [];
    }, [filename]);

    return (
        <div className="min-h-0 flex-1 overflow-hidden bg-[#070b12] text-left" dir="ltr">
            <CodeMirror
                value={value}
                height="58vh"
                minHeight="480px"
                theme="dark"
                extensions={extensions}
                onChange={onChange}
                basicSetup={{
                    lineNumbers: true,
                    highlightActiveLineGutter: true,
                    highlightSpecialChars: true,
                    history: true,
                    foldGutter: true,
                    drawSelection: true,
                    dropCursor: true,
                    allowMultipleSelections: true,
                    indentOnInput: true,
                    syntaxHighlighting: true,
                    bracketMatching: true,
                    closeBrackets: true,
                    autocompletion: true,
                    rectangularSelection: true,
                    crosshairCursor: true,
                    highlightActiveLine: true,
                    highlightSelectionMatches: true,
                    closeBracketsKeymap: true,
                    defaultKeymap: true,
                    searchKeymap: true,
                    historyKeymap: true,
                    foldKeymap: true,
                    completionKeymap: true,
                    lintKeymap: true,
                }}
            />
        </div>
    );
}
