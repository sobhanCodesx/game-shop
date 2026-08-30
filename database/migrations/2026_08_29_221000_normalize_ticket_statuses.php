<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tickets')->whereIn('status', ['answered', 'customer_reply'])->update(['status' => 'open']);
    }

    public function down(): void {}
};
