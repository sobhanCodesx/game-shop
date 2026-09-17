import { useVideoProgress } from "../../../lib/videoProgress";

export default function VideoProgressBar({
    contentId,
    duration,
    className = "",
}: {
    contentId: number;
    duration?: number | null;
    className?: string;
}) {
    const { progress } = useVideoProgress(contentId);
    const total = progress?.duration || duration || 0;
    if (!progress || total <= 0 || progress.position <= 0) return null;

    const percent = progress.completed
        ? 100
        : Math.max(0, Math.min(100, (progress.position / total) * 100));

    return (
        <span
            aria-hidden="true"
            className={`pointer-events-none absolute inset-x-0 bottom-0 z-30 h-1 overflow-hidden bg-black/35 ${className}`}
            dir="ltr"
        >
            <span
                className="absolute bottom-0 left-0 top-0 bg-red-600 transition-[width] duration-200"
                style={{ width: `${percent}%` }}
            />
        </span>
    );
}
