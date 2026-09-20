<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The legacy admin /shorts area is actually PlayNexus' Story manager.
        // Migrate every existing legacy record to the dedicated story type so
        // real short-form videos can safely use type=short going forward.
        DB::table('social_contents')
            ->where('type', 'short')
            ->update(['type' => 'story']);
    }

    public function down(): void
    {
        DB::table('social_contents')
            ->where('type', 'story')
            ->update(['type' => 'short']);
    }
};
