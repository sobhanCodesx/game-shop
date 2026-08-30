import { useMemo } from "react";

const allowedTags = new Set([
    "P", "BR", "STRONG", "B", "EM", "I", "U", "UL", "OL", "LI",
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
        Array.from(element.attributes).forEach((attribute) => element.removeAttribute(attribute.name));
    });

    return document.body.innerHTML;
}

export default function RichText({ html, className = "" }: { html: string; className?: string }) {
    const safeHtml = useMemo(() => sanitize(html), [html]);

    return <div className={`store-rich-text ${className}`} dangerouslySetInnerHTML={{ __html: safeHtml }} />;
}
