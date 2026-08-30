<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('products')->where('status', 'active')->update(['status' => 'published']);
        DB::table('products')->where('status', 'inactive')->update(['status' => 'disabled']);
        DB::table('products')->where('status', 'archive')->update(['status' => 'archived']);
    }

    public function down(): void
    {
        DB::table('products')->where('status', 'published')->update(['status' => 'active']);
        DB::table('products')->where('status', 'disabled')->update(['status' => 'inactive']);
        DB::table('products')->where('status', 'archived')->update(['status' => 'archive']);
    }
};
