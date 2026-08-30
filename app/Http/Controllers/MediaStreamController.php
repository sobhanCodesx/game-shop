<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaStreamController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse|RedirectResponse
    {
        $path = ltrim($path, '/');
        abort_if($path === '' || str_contains($path, '..'), 404);

        if (config('media.disk') !== 'public') {
            return redirect()->away((string) MediaStorage::url($path), 301);
        }

        abort_if($path === '' || str_contains($path, '..') || ! Storage::disk('public')->exists($path), 404);

        return response()->file(Storage::disk('public')->path($path), [
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
        ]);
    }
}
