<?php

namespace App\Http\Controllers;

use App\Models\AndroidRelease;
use Illuminate\Http\RedirectResponse;

class AndroidReleaseDownloadController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $release = AndroidRelease::query()
            ->where('is_active', true)
            ->orderByDesc('version_code')
            ->first();

        abort_unless($release, 404, 'هنوز نسخه اندروید برای دانلود منتشر نشده است.');

        $response = redirect()->away($release->directUrl());
        $response->headers->set('Cache-Control', 'no-store, max-age=0');

        return $response;
    }
}
