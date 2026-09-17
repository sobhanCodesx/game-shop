import { usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";

import type { SharedPageProps } from "../types";

export interface VideoProgressRecord {
    contentId: number;
    position: number;
    duration: number;
    completed: boolean;
    updatedAt: number;
}

const STORAGE_PREFIX = "playnexus:watch-progress:v1";
const EVENT_NAME = "playnexus:watch-progress";
const SERVER_WRITE_INTERVAL = 7000;

const serverCache = new Map<number, Map<number, VideoProgressRecord>>();
const serverLoads = new Map<number, Promise<Map<number, VideoProgressRecord>>>();
const lastServerWrite = new Map<string, number>();
const serverTimers = new Map<string, number>();

const scope = (userId: number | null) => (userId ? `user-${userId}` : "guest");
const storageKey = (userId: number | null, contentId: number) =>
    `${STORAGE_PREFIX}:${scope(userId)}:${contentId}`;
const syncKey = (userId: number, contentId: number) => `${userId}:${contentId}`;

const csrfToken = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? "";

function normalize(
    value: Partial<VideoProgressRecord> | null | undefined,
): VideoProgressRecord | null {
    if (!value || !Number.isFinite(Number(value.contentId))) return null;

    const duration = Math.max(0, Math.floor(Number(value.duration) || 0));
    const position = Math.max(
        0,
        duration > 0
            ? Math.min(duration, Math.floor(Number(value.position) || 0))
            : Math.floor(Number(value.position) || 0),
    );

    return {
        contentId: Number(value.contentId),
        position,
        duration,
        completed: Boolean(value.completed),
        updatedAt: Math.max(0, Number(value.updatedAt) || 0),
    };
}

export function readLocalVideoProgress(
    userId: number | null,
    contentId: number,
): VideoProgressRecord | null {
    if (typeof window === "undefined") return null;

    try {
        return normalize(
            JSON.parse(
                localStorage.getItem(storageKey(userId, contentId)) ?? "null",
            ),
        );
    } catch {
        return null;
    }
}

function emitProgress(userId: number | null, record: VideoProgressRecord) {
    if (typeof window === "undefined") return;
    window.dispatchEvent(
        new CustomEvent(EVENT_NAME, {
            detail: { userId, record },
        }),
    );
}

function writeLocalVideoProgress(
    userId: number | null,
    record: VideoProgressRecord,
    emit = true,
) {
    if (typeof window === "undefined") return;

    try {
        localStorage.setItem(
            storageKey(userId, record.contentId),
            JSON.stringify(record),
        );
        if (emit) emitProgress(userId, record);
    } catch {
        // Playback must never fail just because browser storage is unavailable.
    }
}

async function loadServerProgress(userId: number) {
    const cached = serverCache.get(userId);
    if (cached) return cached;

    const existing = serverLoads.get(userId);
    if (existing) return existing;

    const request = fetch("/watch-progress", {
        headers: { Accept: "application/json" },
        credentials: "same-origin",
    })
        .then(async (response) => {
            if (!response.ok) throw new Error("progress-load-failed");
            const payload = (await response.json()) as {
                progress?: Record<string, Partial<VideoProgressRecord>>;
            };
            const records = new Map<number, VideoProgressRecord>();
            Object.values(payload.progress ?? {}).forEach((value) => {
                const record = normalize(value);
                if (record) records.set(record.contentId, record);
            });
            serverCache.set(userId, records);
            return records;
        })
        .catch(() => new Map<number, VideoProgressRecord>())
        .finally(() => serverLoads.delete(userId));

    serverLoads.set(userId, request);
    return request;
}

async function sendServerProgress(
    userId: number,
    record: VideoProgressRecord,
    keepalive = false,
) {
    const token = csrfToken();
    if (!token) return;

    const key = syncKey(userId, record.contentId);
    lastServerWrite.set(key, Date.now());

    try {
        const response = await fetch(`/watch-progress/${record.contentId}`, {
            method: "POST",
            credentials: "same-origin",
            keepalive,
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": token,
            },
            body: JSON.stringify({
                position_seconds: record.position,
                duration_seconds: record.duration || null,
            }),
        });

        if (!response.ok) return;
        const payload = (await response.json()) as {
            progress?: Partial<VideoProgressRecord>;
        };
        const serverRecord = normalize(payload.progress);
        if (!serverRecord) return;

        const cache =
            serverCache.get(userId) ??
            new Map<number, VideoProgressRecord>();
        cache.set(serverRecord.contentId, serverRecord);
        serverCache.set(userId, cache);

        const local = readLocalVideoProgress(userId, record.contentId);
        if (!local || local.updatedAt <= record.updatedAt) {
            writeLocalVideoProgress(userId, serverRecord);
        }
    } catch {
        // Local progress remains authoritative until the next successful sync.
    }
}

function queueServerProgress(
    userId: number,
    record: VideoProgressRecord,
    immediate = false,
) {
    if (typeof window === "undefined") return;

    const key = syncKey(userId, record.contentId);
    const previousTimer = serverTimers.get(key);
    if (previousTimer) {
        window.clearTimeout(previousTimer);
        serverTimers.delete(key);
    }

    const elapsed = Date.now() - (lastServerWrite.get(key) ?? 0);
    if (immediate || elapsed >= SERVER_WRITE_INTERVAL) {
        void sendServerProgress(userId, record, immediate);
        return;
    }

    const timer = window.setTimeout(() => {
        serverTimers.delete(key);
        const latest = readLocalVideoProgress(userId, record.contentId);
        if (latest) void sendServerProgress(userId, latest);
    }, SERVER_WRITE_INTERVAL - elapsed);
    serverTimers.set(key, timer);
}

export function saveVideoProgress({
    userId,
    contentId,
    position,
    duration,
    completed = false,
    immediate = false,
}: {
    userId: number | null;
    contentId: number;
    position: number;
    duration: number;
    completed?: boolean;
    immediate?: boolean;
}) {
    const safeDuration = Math.max(0, Math.floor(duration || 0));
    const safePosition = Math.max(
        0,
        safeDuration > 0
            ? Math.min(safeDuration, Math.floor(position || 0))
            : Math.floor(position || 0),
    );
    const isCompleted =
        completed ||
        (safeDuration > 0 &&
            (safePosition >= safeDuration - 5 ||
                safePosition / safeDuration >= 0.98));
    const record: VideoProgressRecord = {
        contentId,
        position:
            isCompleted && safeDuration > 0 ? safeDuration : safePosition,
        duration: safeDuration,
        completed: isCompleted,
        updatedAt: Date.now(),
    };

    writeLocalVideoProgress(userId, record);

    if (userId) {
        const cache = serverCache.get(userId);
        cache?.set(contentId, record);
        queueServerProgress(userId, record, immediate);
    }

    return record;
}

export function flushVideoProgress(userId: number | null, contentId: number) {
    if (!userId || typeof window === "undefined") return;
    const record = readLocalVideoProgress(userId, contentId);
    if (!record) return;

    const key = syncKey(userId, contentId);
    const timer = serverTimers.get(key);
    if (timer) {
        window.clearTimeout(timer);
        serverTimers.delete(key);
    }
    void sendServerProgress(userId, record, true);
}

export function useVideoProgress(contentId: number) {
    const { auth } = usePage<SharedPageProps>().props;
    const userId = auth.user?.id ?? null;
    const [progress, setProgress] = useState<VideoProgressRecord | null>(null);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        let active = true;
        const key = storageKey(userId, contentId);
        const userLocal = readLocalVideoProgress(userId, contentId);
        const guestLocal = userId
            ? readLocalVideoProgress(null, contentId)
            : null;
        const initialLocal =
            userLocal && guestLocal
                ? userLocal.updatedAt >= guestLocal.updatedAt
                    ? userLocal
                    : guestLocal
                : userLocal ?? guestLocal;

        if (userId && initialLocal && initialLocal !== userLocal) {
            writeLocalVideoProgress(userId, initialLocal, false);
        }
        setProgress(initialLocal);

        const sync = async () => {
            if (!userId) {
                if (active) setReady(true);
                return;
            }

            const records = await loadServerProgress(userId);
            if (!active) return;

            const currentLocal =
                readLocalVideoProgress(userId, contentId) ?? initialLocal;
            const remote = records.get(contentId) ?? null;
            const chosen =
                currentLocal &&
                (!remote || currentLocal.updatedAt > remote.updatedAt + 1500)
                    ? currentLocal
                    : remote ?? currentLocal;

            if (chosen) {
                writeLocalVideoProgress(userId, chosen, false);
                setProgress(chosen);
            }

            if (
                currentLocal &&
                (!remote || currentLocal.updatedAt > remote.updatedAt + 1500)
            ) {
                queueServerProgress(userId, currentLocal, true);
            }

            setReady(true);
        };

        void sync();

        const onProgress = (event: Event) => {
            const detail = (
                event as CustomEvent<{
                    userId: number | null;
                    record: VideoProgressRecord;
                }>
            ).detail;
            if (
                detail?.userId === userId &&
                detail.record.contentId === contentId
            ) {
                setProgress(detail.record);
            }
        };
        const onStorage = (event: StorageEvent) => {
            if (event.key !== key) return;
            setProgress(readLocalVideoProgress(userId, contentId));
        };

        window.addEventListener(EVENT_NAME, onProgress);
        window.addEventListener("storage", onStorage);
        return () => {
            active = false;
            window.removeEventListener(EVENT_NAME, onProgress);
            window.removeEventListener("storage", onStorage);
        };
    }, [contentId, userId]);

    return { progress, ready, userId };
}
