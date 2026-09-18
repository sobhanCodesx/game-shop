<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProjectFileManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectFileManagerController extends Controller
{
    public function index(Request $request, ProjectFileManagerService $files): Response
    {
        $data = $request->validate([
            'path' => ['nullable', 'string', 'max:4096'],
            'file' => ['nullable', 'string', 'max:4096'],
        ]);

        $currentPath = $files->normalize($data['path'] ?? '');
        $selectedFile = filled($data['file'] ?? null)
            ? $files->readFile((string) $data['file'])
            : null;

        return Inertia::render('Admin/FileManager/Index', [
            'rootName' => $files->rootName(),
            'currentPath' => $currentPath,
            'parentPath' => $files->parent($currentPath),
            'breadcrumbs' => $files->breadcrumbs($currentPath),
            'entries' => $files->listDirectory($currentPath),
            'selectedFile' => $selectedFile,
            'limits' => [
                'max_edit_bytes' => ProjectFileManagerService::MAX_EDIT_BYTES,
                'max_upload_kilobytes' => ProjectFileManagerService::MAX_UPLOAD_KILOBYTES,
                'max_chunked_upload_bytes' => ProjectFileManagerService::MAX_CHUNKED_UPLOAD_BYTES,
            ],
        ]);
    }

    public function update(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'content' => ['present', 'nullable', 'string'],
            'hash' => ['nullable', 'string', 'size:64'],
        ]);

        $result = $files->save($data['path'], (string) ($data['content'] ?? ''), $data['hash'] ?? null);
        $this->audit($request, 'file_saved', $result);

        return back()->with('success', 'فایل با موفقیت ذخیره شد.');
    }

    public function storeFile(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'directory' => ['nullable', 'string', 'max:4096'],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
        ]);

        $path = $files->createFile($data['directory'] ?? '', $data['name'], $data['content'] ?? '');
        $this->audit($request, 'file_created', ['path' => $path]);

        return back()->with('success', 'فایل جدید ساخته شد.');
    }

    public function storeDirectory(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'directory' => ['nullable', 'string', 'max:4096'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $path = $files->createDirectory($data['directory'] ?? '', $data['name']);
        $this->audit($request, 'directory_created', ['path' => $path]);

        return back()->with('success', 'پوشه جدید ساخته شد.');
    }

    public function upload(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'directory' => ['nullable', 'string', 'max:4096'],
            'file' => ['required', 'file', 'max:'.ProjectFileManagerService::MAX_UPLOAD_KILOBYTES],
            'overwrite' => ['nullable', 'boolean'],
        ]);

        $uploadBytes = $data['file']->getSize();
        $path = $files->upload(
            $data['directory'] ?? '',
            $data['file'],
            (bool) ($data['overwrite'] ?? false),
        );
        $this->audit($request, 'file_uploaded', [
            'path' => $path,
            'bytes' => $uploadBytes,
            'overwrite' => (bool) ($data['overwrite'] ?? false),
        ]);

        return back()->with('success', 'فایل آپلود شد.');
    }

    public function uploadChunk(Request $request, ProjectFileManagerService $files): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
            'directory' => ['nullable', 'string', 'max:4096'],
            'chunk_index' => ['required', 'integer', 'min:0', 'max:9999'],
            'total_chunks' => ['required', 'integer', 'min:1', 'max:10000'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1', 'max:'.ProjectFileManagerService::MAX_CHUNKED_UPLOAD_BYTES],
            'overwrite' => ['nullable', 'boolean'],
            'chunk' => ['required', 'file', 'max:5120'],
        ]);

        $directory = $files->normalize($data['directory'] ?? '');
        $files->listDirectory($directory);

        $tempDirectory = $this->projectUploadDirectory($request, $data['upload_id']);
        File::ensureDirectoryExists($tempDirectory.'/chunks');

        $metadata = [
            'directory' => $directory,
            'name' => basename($data['name']),
            'mime' => $data['mime'] ?: 'application/octet-stream',
            'size' => (int) $data['size'],
            'total_chunks' => (int) $data['total_chunks'],
            'overwrite' => (bool) ($data['overwrite'] ?? false),
        ];

        $metadataPath = $tempDirectory.'/upload.json';
        if (File::isFile($metadataPath)) {
            $existing = json_decode((string) File::get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
            abort_unless($existing === $metadata, 422, 'مشخصات قطعات آپلود با یکدیگر هم‌خوانی ندارد.');
        } else {
            File::put($metadataPath, json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        }

        $request->file('chunk')->move($tempDirectory.'/chunks', (string) $data['chunk_index']);

        return response()->json(['received' => (int) $data['chunk_index']]);
    }

    public function completeChunkedUpload(Request $request, ProjectFileManagerService $files): JsonResponse
    {
        $data = $request->validate([
            'upload_id' => ['required', 'uuid'],
        ]);

        $tempDirectory = $this->projectUploadDirectory($request, $data['upload_id']);
        $metadataPath = $tempDirectory.'/upload.json';
        abort_unless(File::isFile($metadataPath), 422, 'اطلاعات آپلود پیدا نشد.');

        $metadata = json_decode((string) File::get($metadataPath), true, flags: JSON_THROW_ON_ERROR);
        $assembledPath = $tempDirectory.'/assembled';
        $target = fopen($assembledPath, 'wb');
        abort_unless($target !== false, 500, 'امکان ساخت فایل نهایی وجود ندارد.');

        try {
            for ($index = 0; $index < (int) $metadata['total_chunks']; $index++) {
                $chunkPath = $tempDirectory.'/chunks/'.$index;
                abort_unless(File::isFile($chunkPath), 422, "قطعه {$index} هنوز دریافت نشده است.");

                $source = fopen($chunkPath, 'rb');
                abort_unless($source !== false, 500, "خواندن قطعه {$index} ناموفق بود.");
                try {
                    stream_copy_to_stream($source, $target);
                } finally {
                    fclose($source);
                }
            }
        } finally {
            fclose($target);
        }

        abort_unless(
            File::size($assembledPath) === (int) $metadata['size'],
            422,
            'اندازه فایل نهایی معتبر نیست.',
        );

        $uploadedFile = new UploadedFile(
            $assembledPath,
            (string) $metadata['name'],
            (string) ($metadata['mime'] ?: 'application/octet-stream'),
            null,
            true,
        );

        $path = $files->upload(
            (string) $metadata['directory'],
            $uploadedFile,
            (bool) $metadata['overwrite'],
        );

        File::deleteDirectory($tempDirectory);
        $this->audit($request, 'file_uploaded_chunked', [
            'path' => $path,
            'bytes' => (int) $metadata['size'],
            'overwrite' => (bool) $metadata['overwrite'],
        ]);

        return response()->json(['uploaded' => true, 'path' => $path]);
    }

    public function rename(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:4096'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $newPath = $files->rename($data['path'], $data['name']);
        $this->audit($request, 'entry_renamed', [
            'from' => $data['path'],
            'to' => $newPath,
        ]);

        return redirect()->to('/admin/file-manager?path='.rawurlencode($files->parent($newPath) ?? ''))
            ->with('success', 'نام با موفقیت تغییر کرد.');
    }

    public function destroy(Request $request, ProjectFileManagerService $files): RedirectResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:4096'],
        ]);

        $path = $files->normalize($data['path']);
        $parent = $files->parent($path) ?? '';
        $files->delete($path);
        $this->audit($request, 'entry_deleted', ['path' => $path]);

        return redirect()->to('/admin/file-manager?path='.rawurlencode($parent))
            ->with('success', 'فایل یا پوشه حذف شد.');
    }

    public function download(Request $request, ProjectFileManagerService $files): BinaryFileResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string', 'max:4096'],
        ]);

        $absolute = $files->downloadPath($data['path']);
        $this->audit($request, 'file_downloaded', ['path' => $files->normalize($data['path'])]);

        return response()->download($absolute, basename($absolute));
    }

    private function projectUploadDirectory(Request $request, string $uploadId): string
    {
        return storage_path('app/private/project-file-uploads/'.$request->user()->id.'/'.$uploadId);
    }

    private function audit(Request $request, string $action, array $context = []): void
    {
        Log::notice('Project file manager action', [
            'admin_id' => $request->user()?->id,
            'action' => $action,
            ...$context,
        ]);
    }
}
