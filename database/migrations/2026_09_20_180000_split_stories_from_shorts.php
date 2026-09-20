<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy PlayNexus used the "short" type for both Shorts and Stories.
        // Image-based legacy entries are unambiguous Stories, so move only
        // those automatically. Video Shorts remain Shorts; future Stories use
        // the dedicated "story" type.
        DB::table('social_contents')
            ->where('type', 'short')
            ->where('media_type', 'image')
            ->update(['type' => 'story']);
    }

    public function down(): void
    {
        DB::table('social_contents')
            ->where('type', 'story')
            ->where('media_type', 'image')
            ->update(['type' => 'short']);
    }
};
