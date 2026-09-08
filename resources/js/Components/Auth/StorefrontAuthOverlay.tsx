import { usePage } from "@inertiajs/react";
import { CircleAlert, X } from "lucide-react";
import { useEffect, useState } from "react";
import type { SharedPageProps } from "../../types";
import GuestLoginPrompt from "./GuestLoginPrompt";

export default function StorefrontAuthOverlay() {
    const { auth, flash } = usePage<SharedPageProps>().props;

    return (
        <>
            <GuestLoginPrompt user={auth.user} />
            <FlashError message={flash.error} />
        </>
    );
}

function FlashError({ message }: { message: string | null }) {
    const [visible, setVisible] = useState(Boolean(message));
    useEffect(() => {
        setVisible(Boolean(message));
        if (!message) return;
        const timer = window.setTimeout(() => setVisible(false), 7000);
        return () => window.clearTimeout(timer);
    }, [message]);
    if (!message || !visible) return null;

    return (
        <div className="fixed left-3 top-3 z-[120] flex max-w-[calc(100%-1.5rem)] items-start gap-3 rounded-2xl border border-rose-200 bg-white/95 p-4 text-sm text-rose-700 shadow-2xl backdrop-blur sm:left-5 sm:top-5" role="alert">
            <CircleAlert className="mt-0.5 shrink-0" size={19} />
            <span className="leading-6">{message}</span>
            <button aria-label="بستن پیام" className="mr-2 text-rose-400 hover:text-rose-700" onClick={() => setVisible(false)} type="button"><X size={16} /></button>
        </div>
    );
}
