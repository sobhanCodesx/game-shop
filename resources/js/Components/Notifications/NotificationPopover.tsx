import { router, usePage } from "@inertiajs/react";
import {
    Bell,
    CheckCheck,
    ExternalLink,
} from "lucide-react";
import {
    useEffect,
    useRef,
    useState,
} from "react";

import type { SharedPageProps } from "../../types";

export default function NotificationPopover({
    admin = false,
}: {
    admin?: boolean;
}) {
    const { notifications } =
        usePage<SharedPageProps>().props;

    const [open, setOpen] = useState(false);

    const root = useRef<HTMLDivElement>(null);

    const unread =
        notifications?.unread_count ?? 0;

    useEffect(() => {
        const handleOutsidePress = (
            event: PointerEvent,
        ) => {
            if (
                !root.current?.contains(
                    event.target as Node,
                )
            ) {
                setOpen(false);
            }
        };

        document.addEventListener(
            "pointerdown",
            handleOutsidePress,
        );

        return () => {
            document.removeEventListener(
                "pointerdown",
                handleOutsidePress,
            );
        };
    }, []);

    useEffect(() => {
        const timer = window.setInterval(() => {
            router.reload({
                only: ["notifications", "admin"],
            });
        }, 20_000);

        return () => {
            window.clearInterval(timer);
        };
    }, []);

    const read = (id: string) => {
        router.patch(
            `/account/notifications/${id}`,
            {},
            {
                preserveScroll: true,
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
                onClick={() =>
                    setOpen((current) => !current)
                }
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
                        {unread.toLocaleString(
                            "fa-IR",
                        )}
                    </span>
                )}
            </button>

            {open && (
                <section
                    className={`
                        absolute
                        left-0
                        top-[calc(100%+.7rem)]
                        z-[70]
                        w-[min(390px,calc(100vw-2rem))]
                        overflow-hidden
                        rounded-3xl
                        border
                        shadow-2xl
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
                            <h2 className="font-black">
                                اعلان‌ها
                            </h2>

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
                                            preserveScroll:
                                                true,
                                        },
                                    )
                                }
                            >
                                <CheckCheck
                                    size={15}
                                />

                                خواندن همه
                            </button>
                        )}
                    </header>

                    <div className="max-h-[420px] overflow-y-auto p-2">
                        {notifications?.latest.map(
                            (item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() =>
                                        read(item.id)
                                    }
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
                                            {
                                                item.title
                                            }
                                        </strong>

                                        <span className="mt-1 line-clamp-2 text-xs leading-5 opacity-75">
                                            {
                                                item.message
                                            }
                                        </span>
                                    </span>

                                    <ExternalLink
                                        size={14}
                                        className="shrink-0 opacity-35"
                                    />
                                </button>
                            ),
                        )}

                        {!notifications?.latest
                            .length && (
                            <div className="py-12 text-center text-sm opacity-50">
                                اعلانی وجود ندارد.
                            </div>
                        )}
                    </div>
                </section>
            )}
        </div>
    );
}