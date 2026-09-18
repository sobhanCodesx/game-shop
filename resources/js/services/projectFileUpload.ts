export interface ProjectFileUploadProgress {
    percentage: number;
    uploadedBytes: number;
    totalBytes: number;
}

const chunkSize = 4 * 1024 * 1024;

const csrfToken = (): string =>
    document
        .querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
        ?.getAttribute("content") ?? "";

function sendChunk(
    file: File,
    directory: string,
    overwrite: boolean,
    uploadId: string,
    index: number,
    totalChunks: number,
    onBytes: (loaded: number) => void,
    signal?: AbortSignal,
): Promise<void> {
    return new Promise((resolve, reject) => {
        const start = index * chunkSize;
        const form = new FormData();

        form.append("upload_id", uploadId);
        form.append("directory", directory);
        form.append("chunk_index", String(index));
        form.append("total_chunks", String(totalChunks));
        form.append("name", file.name);
        form.append("mime", file.type || "application/octet-stream");
        form.append("size", String(file.size));
        form.append("overwrite", overwrite ? "1" : "0");
        form.append(
            "chunk",
            file.slice(start, Math.min(start + chunkSize, file.size)),
            `chunk-${index}`,
        );

        const request = new XMLHttpRequest();
        request.open("POST", "/admin/file-manager/upload/chunk");
        request.setRequestHeader("Accept", "application/json");
        request.setRequestHeader("X-CSRF-TOKEN", csrfToken());

        request.upload.onprogress = (event) => onBytes(event.loaded);
        request.onload = () => {
            if (request.status >= 200 && request.status < 300) {
                resolve();
                return;
            }

            let message = "ارسال یکی از قطعات فایل انجام نشد.";
            try {
                const payload = JSON.parse(request.responseText) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                message =
                    Object.values(payload.errors ?? {}).flat().join("\n") ||
                    payload.message ||
                    message;
            } catch {
                //
            }
            reject(new Error(message));
        };
        request.onerror = () =>
            reject(new Error("ارتباط با سرور هنگام آپلود قطع شد."));
        request.onabort = () =>
            reject(new DOMException("Upload cancelled", "AbortError"));

        signal?.addEventListener("abort", () => request.abort(), { once: true });
        request.send(form);
    });
}

async function retry(task: () => Promise<void>, attempts = 3): Promise<void> {
    let lastError: unknown;

    for (let attempt = 0; attempt < attempts; attempt += 1) {
        try {
            await task();
            return;
        } catch (error) {
            lastError = error;
            if (error instanceof DOMException && error.name === "AbortError") {
                throw error;
            }
        }
    }

    throw lastError;
}

export async function uploadProjectFile(
    file: File,
    directory: string,
    overwrite: boolean,
    onProgress: (progress: ProjectFileUploadProgress) => void,
    signal?: AbortSignal,
): Promise<{ path: string }> {
    const uploadId = crypto.randomUUID();
    const totalChunks = Math.ceil(file.size / chunkSize);
    const loaded = new Array<number>(totalChunks).fill(0);
    let cursor = 0;

    const report = () => {
        const uploadedBytes = loaded.reduce((sum, value) => sum + value, 0);
        onProgress({
            percentage: Math.min(
                99,
                Math.round((uploadedBytes / file.size) * 100),
            ),
            uploadedBytes,
            totalBytes: file.size,
        });
    };

    const worker = async () => {
        while (cursor < totalChunks) {
            const index = cursor++;
            await retry(() =>
                sendChunk(
                    file,
                    directory,
                    overwrite,
                    uploadId,
                    index,
                    totalChunks,
                    (bytes) => {
                        loaded[index] = bytes;
                        report();
                    },
                    signal,
                ),
            );

            loaded[index] = Math.min(
                chunkSize,
                file.size - index * chunkSize,
            );
            report();
        }
    };

    await Promise.all(
        Array.from({ length: Math.min(3, totalChunks) }, () => worker()),
    );

    const response = await fetch("/admin/file-manager/upload/complete", {
        method: "POST",
        credentials: "same-origin",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": csrfToken(),
            "X-Requested-With": "XMLHttpRequest",
        },
        body: JSON.stringify({ upload_id: uploadId }),
        signal,
    });

    const payload = (await response.json().catch(() => ({}))) as {
        path?: string;
        message?: string;
        errors?: Record<string, string[]>;
    };

    if (!response.ok || !payload.path) {
        const message =
            Object.values(payload.errors ?? {}).flat().join("\n") ||
            payload.message ||
            "ساخت فایل نهایی روی سرور انجام نشد.";
        throw new Error(message);
    }

    onProgress({
        percentage: 100,
        uploadedBytes: file.size,
        totalBytes: file.size,
    });

    return { path: payload.path };
}
