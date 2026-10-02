<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE digital_products MODIFY title TEXT NOT NULL');
            DB::statement('ALTER TABLE digital_products MODIFY short_description LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE digital_products MODIFY title VARCHAR(255) NOT NULL');
            DB::statement('ALTER TABLE digital_products MODIFY short_description TEXT NULL');
        }
    }
};
