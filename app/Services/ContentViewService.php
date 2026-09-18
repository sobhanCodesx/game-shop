<?php

namespace App\Services;

use App\Models\SocialContent;
use Illuminate\Http\Request;

class ContentViewService
{
    public function record(Request $request, SocialContent $content): bool
    {
        $sessionKey = match ($content->type) {
            'video' => 'viewed_videos',
            'short' => 'viewed_shorts',
            default => 'viewed_posts',
        };

        $viewed = array_map('intval', (array) $request->session()->get($sessionKey, []));

        if (in_array($content->id, $viewed, true)) {
            return false;
        }

        $content->increment('views');
        $request->session()->put(
            $sessionKey,
            [...array_slice($viewed, -399), (int) $content->id],
        );

        return true;
    }
}
