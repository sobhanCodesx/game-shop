import { type RefObject, useEffect } from "react";

/** Project the playing frame without reading pixels. Cross-origin videos may
 * be drawn to a canvas even when their host does not permit getImageData.
 * This keeps the light live without CORS requests, a second video or a proxy.
 */
export function useVideoAmbientColors(
    shellRef: RefObject<HTMLDivElement | null>,
    canvasRef: RefObject<HTMLCanvasElement | null>,
    source: string | null,
) {
    useEffect(() => {
        const shell = shellRef.current;
        const canvas = canvasRef.current;
        if (!shell || !canvas) return;

        canvas.width = 64;
        canvas.height = 36;
        const context = canvas.getContext("2d", { alpha: false });
        if (!context) return;
        delete shell.dataset.ambientReady;
        let inView = true;
        let lastTime = -1;
        let lastVideo: HTMLVideoElement | null = null;
        let hasFrame = false;
        const reducedMotion = window.matchMedia(
            "(prefers-reduced-motion: reduce)",
        );
        const observer = new IntersectionObserver(
            ([entry]) => {
                inView = entry.isIntersecting;
            },
            { rootMargin: "100px" },
        );
        observer.observe(shell);

        const sample = () => {
            if (!inView || document.hidden || document.fullscreenElement)
                return;
            const video = shell.querySelector<HTMLVideoElement>(
                ".playnexus-player video",
            );
            if (!video || video.readyState < 2 || !video.videoWidth) return;
            if (video === lastVideo && video.currentTime === lastTime) return;
            try {
                // Blend adjacent frames to soften cuts while retaining spatial colors.
                context.globalAlpha =
                    hasFrame && video === lastVideo ? 0.65 : 1;
                context.drawImage(video, 0, 0, canvas.width, canvas.height);
                lastTime = video.currentTime;
                lastVideo = video;
                hasFrame = true;
                shell.dataset.ambientReady = "true";
            } catch {
                // A temporarily unavailable frame must never interrupt playback.
            }
        };
        const timer = window.setInterval(
            sample,
            reducedMotion.matches ? 500 : 80,
        );
        sample();
        return () => {
            window.clearInterval(timer);
            observer.disconnect();
            delete shell.dataset.ambientReady;
            canvas.width = 0;
            canvas.height = 0;
        };
    }, [canvasRef, shellRef, source]);
}
