import { router, usePage } from "@inertiajs/react";
import { Bell, CheckCheck, ExternalLink } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

import type { SharedPageProps } from "../../types";

let pollSubscribers = 0;
let pollTimer: number | undefined;
const refreshNotifications = () => {
    if (document.visibilityState === "visible" && navigator.onLine)
        router.reload({ only: ["notifications", "admin"] });
};
const schedulePoll = () => {
    window.clearTimeout(pollTimer);
    pollTimer = window.setTimeout(
        () => {
            refreshNotifications();
            schedulePoll();
        },
        document.visibilityState === "visible" ? 15_000 : 60_000,
    );
};
const handleVisibility = () => {
    if (document.visibilityState === "visible") refreshNotifications();
    schedulePoll();
};
const startPolling = () => {
    if (++pollSubscribers !== 1) return;
    document.addEventListener("visibilitychange", handleVisibility);
    window.addEventListener("focus", refreshNotifications);
    window.addEventListener("online", refreshNotifications);
    schedulePoll();
};
const stopPolling = () => {
    if (--pollSubscribers > 0) return;
    pollSubscribers = 0;
    window.clearTimeout(pollTimer);
    document.removeEventListener("visibilitychange", handleVisibility);
    window.removeEventListener("focus", refreshNotifications);
    window.removeEventListener("online", refreshNotifications);
};

export default function NotificationPopover({
    admin = false,
}: {
    admin?: boolean;
}) {
    const { notifications } = usePage<SharedPageProps>().props;

    const [open, setOpen] = useState(false);
    const [navigating, setNavigating] = useState<string | null>(null);

    const root = useRef<HTMLDivElement>(null);
    const panel = useRef<HTMLElement>(null);

    const unread = notifications?.unread_count ?? 0;

    useEffect(() => {
        const handleOutsidePress = (event: PointerEvent) => {
            const target = event.target as Node;
            if (
                !root.current?.contains(target) &&
                !panel.current?.contains(target)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener("pointerdown", handleOutsidePress);

        return () => {
            document.removeEventListener("pointerdown", handleOutsidePress);
        };
    }, []);

    useEffect(() => {
        startPolling();
        return stopPolling;
    }, []);

    const read = (id: string) => {
        setNavigating(id);
        setOpen(false);
        router.patch(
            `/account/notifications/${id}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setNavigating(null),
            },
        );
    };

    return (
        <div
            ref={root}
            className="relative inline-flex size-11 shrink-0 items-center justify-center"
        >
            <button
                type="button"
                aria-expanded={open}
                aria-haspopup="dialog"
                aria-label="اعلان‌ها"
                onClick={() => {
                    setOpen((current) => {
                        if (!current) refreshNotifications();
                        return !current;
                    });
                }}
                className={`
                    relative
                    inline-grid
                    size-11
                    shrink-0
                    cursor-pointer
                    place-items-center
                    rounded-2xl
                    transition
                    ${
                        admin
                            ? "text-slate-400 hover:bg-slate-800 hover:text-white"
                            : "text-[var(--store-text)] hover:bg-[var(--store-accent-soft)] hover:text-indigo-500"
                    }
                `}
            >
                <Bell size={20} />

                {unread > 0 && (
                    <span
                        className={`
                            absolute
                            -left-1
                            -top-1
                            grid
                            min-h-5
                            min-w-5
                            place-items-center
                            rounded-full
                            bg-red-600
                            px-1
                            text-[10px]
                            font-black
                            leading-none
                            text-white
                            ring-2
                            ${
                                admin
                                    ? "ring-[#080b12]"
                                    : "ring-[var(--store-header)]"
                            }
                        `}
                    >
                        {unread.toLocaleString("fa-IR")}
                    </span>
                )}
            </button>

            {open &&
                typeof document !== "undefined" &&
                createPortal(
                    <section
                        ref={panel}
                        className={`
                        fixed
                        inset-x-3
                        top-20
                        z-[70]
                        max-h-[calc(100dvh-6rem)]
                        w-auto
                        overflow-hidden
                        rounded-3xl
                        border
                        shadow-2xl
                        lg:fixed
                        lg:inset-x-auto
                        lg:left-5
                        lg:top-20
                        lg:max-h-none
                        lg:w-[min(390px,calc(100vw-2rem))]
                        ${
                            admin
                                ? "border-slate-800 bg-[#0d121d] text-slate-100"
                                : "border-[var(--store-border)] bg-[var(--store-panel)] text-[var(--store-text)]"
                        }
                    `}
                    >
                        <header
                            className={`
                            flex
                            items-center
                            justify-between
                            border-b
                            p-4
                            ${
                                admin
                                    ? "border-slate-800"
                                    : "border-[var(--store-border)]"
                            }
                        `}
                        >
                            <div>
                                <h2 className="font-black">اعلان‌ها</h2>

                                <p
                                    className={`
                                    mt-1
                                    text-xs
                                    ${
                                        admin
                                            ? "text-slate-500"
                                            : "text-[var(--store-muted)]"
                                    }
                                `}
                                >
                                    {unread
                                        ? `${unread.toLocaleString(
                                              "fa-IR",
                                          )} اعلان خوانده‌نشده`
                                        : "همه اعلان‌ها خوانده شده‌اند"}
                                </p>
                            </div>

                            {unread > 0 && (
                                <button
                                    type="button"
                                    className="flex cursor-pointer items-center gap-1 text-xs font-bold text-indigo-500"
                                    onClick={() =>
                                        router.patch(
                                            "/account/notifications",
                                            {},
                                            {
                                                preserveScroll: true,
                                            },
                                        )
                                    }
                                >
                                    <CheckCheck size={15} />
                                    خواندن همه
                                </button>
                            )}
                        </header>

                        <div className="max-h-[calc(100dvh-11.5rem)] overflow-y-auto p-2 lg:max-h-[420px]">
                            {notifications?.latest.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => read(item.id)}
                                    disabled={navigating === item.id}
                                    className={`
                                        mb-1
                                        flex
                                        w-full
                                        cursor-pointer
                                        gap-3
                                        rounded-2xl
                                        border
                                        p-3
                                        text-right
                                        transition
                                        ${
                                            item.read_at
                                                ? admin
                                                    ? "border-transparent text-slate-500 hover:bg-slate-800/50"
                                                    : "border-transparent text-[var(--store-muted)] hover:bg-[var(--store-bg)]"
                                                : admin
                                                  ? "border-indigo-500/25 bg-indigo-500/10 text-white"
                                                  : "border-indigo-500/20 bg-indigo-500/10"
                                        }
                                    `}
                                >
                                    <span
                                        className={`
                                            mt-1
                                            size-2
                                            shrink-0
                                            rounded-full
                                            ${
                                                item.read_at
                                                    ? "bg-slate-500/30"
                                                    : "bg-red-500"
                                            }
                                        `}
                                    />

                                    <span className="min-w-0 flex-1">
                                        <strong className="block truncate text-sm">
                                            {item.title}
                                        </strong>

                                        <span className="mt-1 line-clamp-2 text-xs leading-5 opacity-75">
                                            {item.message}
                                        </span>
                                        <small className="mt-1 block text-[10px] opacity-45">
                                            {relativeTime(item.created_at)}
                                        </small>
                                    </span>

                                    <ExternalLink
                                        size={14}
                                        className="shrink-0 opacity-35"
                                    />
                                </button>
                            ))}

                            {!notifications?.latest.length && (
                                <div className="py-12 text-center text-sm opacity-50">
                                    اعلانی وجود ندارد.
                                </div>
                            )}
                        </div>
                    </section>,
                    document.querySelector(".storefront-theme") ??
                        document.body,
                )}
        </div>
    );
}

function relativeTime(value: string): string {
    const seconds = Math.max(
        0,
        Math.floor((Date.now() - new Date(value).getTime()) / 1000),
    );
    if (seconds < 60) return "همین حالا";
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes.toLocaleString("fa-IR")} دقیقه پیش`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours.toLocaleString("fa-IR")} ساعت پیش`;
    return new Date(value).toLocaleDateString("fa-IR");
}
