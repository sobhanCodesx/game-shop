import { router } from "@inertiajs/react";
import { useEffect, useRef, useState } from "react";

const SHOW_DELAY = 180;
const MIN_VISIBLE_TIME = 180;

export default function PageTransitionLoader() {
    const [visible, setVisible] = useState(false);
    const [light, setLight] = useState(false);
    const delayTimer = useRef<number | null>(null);
    const hideTimer = useRef<number | null>(null);
    const shownAt = useRef(0);
    const visibleRef = useRef(false);
    const navigationVisitId = useRef<string | null>(null);

    useEffect(() => {
        const clearTimer = (timer: typeof delayTimer) => {
            if (timer.current !== null) window.clearTimeout(timer.current);
            timer.current = null;
        };
        const removeStartListener = router.on("start", (event) => {
            const visit = event.detail.visit;
            const currentUrl = new URL(window.location.href);
            const destinationUrl = visit.url;
            // Pagination, filtering, sorting and other query-string changes are
            // updates to the current screen, not full page navigation.
            const samePage = destinationUrl.pathname === currentUrl.pathname;
            const partialReload =
                visit.only.length > 0 || visit.except.length > 0;

            if (
                visit.method !== "get" ||
                visit.prefetch ||
                visit.async ||
                partialReload ||
                samePage
            ) {
                return;
            }

            navigationVisitId.current = visit.id;
            clearTimer(hideTimer);
            setLight(
                document
                    .querySelector(".storefront-theme")
                    ?.getAttribute("data-theme") === "light",
            );
            delayTimer.current = window.setTimeout(() => {
                shownAt.current = performance.now();
                visibleRef.current = true;
                setVisible(true);
            }, SHOW_DELAY);
        });
        const removeFinishListener = router.on("finish", (event) => {
            if (event.detail.visit.id !== navigationVisitId.current) return;
            navigationVisitId.current = null;
            clearTimer(delayTimer);
            const remaining = Math.max(
                0,
                MIN_VISIBLE_TIME - (performance.now() - shownAt.current),
            );
            hideTimer.current = window.setTimeout(
                () => {
                    visibleRef.current = false;
                    setVisible(false);
                    shownAt.current = 0;
                },
                visibleRef.current ? remaining : 0,
            );
        });

        return () => {
            clearTimer(delayTimer);
            clearTimer(hideTimer);
            removeStartListener();
            removeFinishListener();
        };
    }, []);

    if (!visible) return null;

    return (
        <div
            aria-label="در حال بارگذاری صفحه"
            aria-live="polite"
            className={`page-transition-loader ${light ? "page-transition-loader--light" : ""}`}
            role="status"
        >
            <div className="page-transition-loader__glow" />
            <div className="page-transition-loader__content">
                <div className="page-transition-loader__logo">
                    <span className="page-transition-loader__orbit" />
                    <img alt="" src="/logo.png" />
                </div>
                <strong>پلی نکسوس</strong>
                <span className="page-transition-loader__label">
                    در حال آماده‌سازی صفحه
                </span>
                <span className="page-transition-loader__bar">
                    <span />
                </span>
            </div>
        </div>
    );
}
