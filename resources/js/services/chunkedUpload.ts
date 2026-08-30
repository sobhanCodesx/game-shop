export interface UploadProgress {
    percentage: number;
    uploadedBytes: number;
    totalBytes: number;
    bytesPerSecond: number;
    remainingSeconds: number | null;
}

const chunkSize = 4 * 1024 * 1024;
const csrf = () =>
    document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.content ?? "";

function sendChunk(
    file: File,
    uploadId: string,
    index: number,
    total: number,
    onBytes: (loaded: number) => void,
    signal?: AbortSignal,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const start = index * chunkSize;
        const body = new FormData();
        body.append("upload_id", uploadId);
        body.append("chunk_index", String(index));
        body.append("total_chunks", String(total));
        body.append("name", file.name);
        body.append("mime", file.type);
        body.append("size", String(file.size));
        body.append(
            "chunk",
            file.slice(start, Math.min(start + chunkSize, file.size)),
            `chunk-${index}`,
        );
        const request = new XMLHttpRequest();
        request.open("POST", "/admin/uploads/chunk");
        request.setRequestHeader("Accept", "application/json");
        request.setRequestHeader("X-CSRF-TOKEN", csrf());
        request.upload.onprogress = (event) => onBytes(event.loaded);
        request.onload = () =>
            request.status >= 200 && request.status < 300
                ? resolve()
                : reject(new Error("ارسال یکی از قطعات فایل انجام نشد."));
        request.onerror = () =>
            reject(new Error("ارتباط با سرور هنگام آپلود قطع شد."));
        request.onabort = () =>
            reject(new DOMException("Upload cancelled", "AbortError"));
        signal?.addEventListener("abort", () => request.abort(), {
            once: true,
        });
        request.send(body);
    });
}

async function retry(task: () => Promise<void>, attempts = 3): Promise<void> {
    let error: unknown;
    for (let attempt = 0; attempt < attempts; attempt += 1) {
        try {
            await task();
            return;
        } catch (caught) {
            error = caught;
            if (caught instanceof DOMException && caught.name === "AbortError")
                throw caught;
        }
    }
    throw error;
}

export async function uploadFileInChunks(
    file: File,
    onProgress: (progress: UploadProgress) => void,
    signal?: AbortSignal,
): Promise<string> {
    const uploadId = crypto.randomUUID();
    const total = Math.ceil(file.size / chunkSize);
    const loaded = new Array<number>(total).fill(0);
    const startedAt = performance.now();
    let cursor = 0;
    const report = () => {
        const uploadedBytes = loaded.reduce((sum, value) => sum + value, 0);
        const elapsed = Math.max(0.1, (performance.now() - startedAt) / 1000);
        const bytesPerSecond = uploadedBytes / elapsed;
        onProgress({
            percentage: Math.min(
                99,
                Math.round((uploadedBytes / file.size) * 100),
            ),
            uploadedBytes,
            totalBytes: file.size,
            bytesPerSecond,
            remainingSeconds:
                bytesPerSecond > 0
                    ? Math.ceil((file.size - uploadedBytes) / bytesPerSecond)
                    : null,
        });
    };
    const worker = async () => {
        while (cursor < total) {
            const index = cursor++;
            await retry(() =>
                sendChunk(
                    file,
                    uploadId,
                    index,
                    total,
                    (bytes) => {
                        loaded[index] = bytes;
                        report();
                    },
                    signal,
                ),
            );
            loaded[index] = Math.min(chunkSize, file.size - index * chunkSize);
            report();
        }
    };
    await Promise.all(Array.from({ length: Math.min(3, total) }, worker));
    const response = await fetch("/admin/uploads/complete", {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrf(),
        },
        body: JSON.stringify({ upload_id: uploadId }),
        signal,
    });
    if (!response.ok) throw new Error("ساخت فایل نهایی روی سرور انجام نشد.");
    const result = (await response.json()) as { token: string };
    onProgress({
        percentage: 100,
        uploadedBytes: file.size,
        totalBytes: file.size,
        bytesPerSecond:
            file.size / Math.max(0.1, (performance.now() - startedAt) / 1000),
        remainingSeconds: 0,
    });
    return result.token;
}
