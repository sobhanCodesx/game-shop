<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // The existing admin "Shorts" section has historically been the Story manager.
        // Move legacy Story records into their own content type so real short-form
        // videos can safely keep type=short without appearing in Story trays.
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
