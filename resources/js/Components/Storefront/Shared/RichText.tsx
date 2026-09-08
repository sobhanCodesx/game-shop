import { useMemo } from "react";

const allowedTags = new Set([
    "P", "BR", "STRONG", "B", "EM", "I", "U", "UL", "OL", "LI",
    "H2", "H3", "H4", "BLOCKQUOTE", "A", "HR",
]);

function sanitize(value: string): string {
    if (typeof DOMParser === "undefined") return "";

    const document = new DOMParser().parseFromString(value, "text/html");
    document.body.querySelectorAll("script,style,iframe,object,embed,svg,math").forEach((element) => element.remove());
    Array.from(document.body.querySelectorAll("*")).forEach((element) => {
        if (!allowedTags.has(element.tagName)) {
            element.replaceWith(...Array.from(element.childNodes));
            return;
        }
        if (element.tagName === "A") {
            const href = element.getAttribute("href")?.trim() ?? "";
            Array.from(element.attributes).forEach((attribute) => element.removeAttribute(attribute.name));
            if (/^(https?:\/\/|\/|#|mailto:)/i.test(href)) {
                element.setAttribute("href", href);
                element.setAttribute("rel", "noopener noreferrer");
            }
            return;
        }
        Array.from(element.attributes).forEach((attribute) => element.removeAttribute(attribute.name));
    });

    return document.body.innerHTML;
}

export default function RichText({ html, className = "" }: { html: string; className?: string }) {
    const safeHtml = useMemo(() => sanitize(html), [html]);

    return <div className={`store-rich-text ${className}`} dangerouslySetInnerHTML={{ __html: safeHtml }} />;
}
