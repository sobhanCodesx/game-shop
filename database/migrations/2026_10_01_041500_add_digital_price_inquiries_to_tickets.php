<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->foreignId('digital_product_id')
                ->nullable()
                ->after('product_id')
                ->constrained('digital_products')
                ->nullOnDelete();
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->after('digital_product_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->index(
                ['type', 'assigned_user_id', 'status'],
                'tickets_type_assignee_status_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropIndex('tickets_type_assignee_status_index');
            $table->dropConstrainedForeignId('assigned_user_id');
            $table->dropConstrainedForeignId('digital_product_id');
        });
    }
};
