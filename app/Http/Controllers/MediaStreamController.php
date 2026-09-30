<?php

namespace App\Http\Controllers;

use App\Services\MediaStorage;
use App\Services\ProductMediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class MediaStreamController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse|RedirectResponse
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        abort_if(
            $path === ''
                || str_contains($path, '..')
                || str_contains($path, "\0")
                || preg_match('#^https?://#i', $path) === 1,
            404,
        );

        $productDisk = ProductMediaStorage::diskName();
        if ($this->diskHas($productDisk, $path)) {
            return $this->serveFromDisk($productDisk, $path);
        }

        $legacyDisk = (string) config('media.disk', 'public');

        // Existing rows can still point at the pre-migration media disk.
        // Promote them to the product disk when the legacy storage is
        // reachable. If it is remote and cannot be probed, redirect to its
        // configured public URL instead of rendering a broken image.
        if ($legacyDisk !== $productDisk) {
            if ($this->promote($legacyDisk, $productDisk, $path)) {
                return $this->serveFromDisk($productDisk, $path);
            }

            if ($this->diskDriver($legacyDisk) !== 'local') {
                return redirect()->away($this->directDiskUrl($legacyDisk, $path), 302);
            }
        }

        if (! $this->diskHas($legacyDisk, $path)) {
            abort(404);
        }

        return $this->serveFromDisk($legacyDisk, $path);
    }

    private function diskDriver(string $disk): string
    {
        return (string) config("filesystems.disks.{$disk}.driver", '');
    }

    private function diskHas(string $disk, string $path): bool
    {
        try {
            return Storage::disk($disk)->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    private function promote(string $sourceDisk, string $targetDisk, string $path): bool
    {
        if (! $this->diskHas($sourceDisk, $path)) {
            return false;
        }

        $stream = null;

        try {
            $stream = Storage::disk($sourceDisk)->readStream($path);
            if (! is_resource($stream)) {
                return false;
            }

            if (! Storage::disk($targetDisk)->put($path, $stream)) {
                return false;
            }

            return $this->diskHas($targetDisk, $path);
        } catch (Throwable) {
            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function serveFromDisk(string $disk, string $path): BinaryFileResponse|RedirectResponse
    {
        if ($this->diskDriver($disk) === 'local') {
            return response()->file(Storage::disk($disk)->path($path), [
                'Accept-Ranges' => 'bytes',
                'Cache-Control' => 'public, max-age=31536000, immutable',
                'Content-Type' => Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        return redirect()->away($this->directDiskUrl($disk, $path), 302);
    }

    private function directDiskUrl(string $disk, string $path): string
    {
        $baseUrl = config("filesystems.disks.{$disk}.url");
        if (is_string($baseUrl) && trim($baseUrl) !== '') {
            return rtrim($baseUrl, '/').'/'.$path;
        }

        try {
            return (string) Storage::disk($disk)->url($path);
        } catch (Throwable) {
            abort(404);
        }
    }
}
