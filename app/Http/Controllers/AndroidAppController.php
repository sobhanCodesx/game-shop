<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AndroidAppController extends Controller
{
    public function __invoke(): BinaryFileResponse
    {
        $files = glob(public_path('apk/*.apk')) ?: [];

        abort_if($files === [], 404, 'فایل اپلیکیشن اندروید پیدا نشد.');

        usort(
            $files,
            static fn (string $a, string $b): int => (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0),
        );

        $apk = $files[0];

        return response()->download(
            $apk,
            basename($apk),
            [
                'Content-Type' => 'application/vnd.android.package-archive',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
            ],
        );
    }
}
