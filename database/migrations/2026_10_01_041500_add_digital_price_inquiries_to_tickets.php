<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tickets', 'digital_product_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreignId('digital_product_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('digital_products')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tickets', 'assigned_user_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->foreignId('assigned_user_id')
                    ->nullable()
                    ->after('digital_product_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('tickets', 'tickets_type_assignee_status_index')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->index(
                    ['type', 'assigned_user_id', 'status'],
                    'tickets_type_assignee_status_index',
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('tickets', 'tickets_type_assignee_status_index')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropIndex('tickets_type_assignee_status_index');
            });
        }

        // These columns can pre-date this migration on installations that were
        // repaired manually or by an earlier partial deployment. Keep rollback
        // conservative rather than deleting data we cannot prove we created.
    }
};
