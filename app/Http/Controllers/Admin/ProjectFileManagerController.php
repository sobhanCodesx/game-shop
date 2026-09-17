<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProjectFileManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    private function audit(Request $request, string $action, array $context = []): void
    {
        Log::notice('Project file manager action', [
            'admin_id' => $request->user()?->id,
            'action' => $action,
            ...$context,
        ]);
    }
}
