<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Safety repair for any environment that may have applied the incorrect
        // temporary split. In PlayNexus, storefront Stories are backed by the
        // existing short-form records managed from /admin/shorts.
        DB::table('social_contents')
            ->where('type', 'story')
            ->update(['type' => 'short']);
    }

    public function down(): void
    {
        // Intentionally no-op. The canonical model is type=short.
    }
};
