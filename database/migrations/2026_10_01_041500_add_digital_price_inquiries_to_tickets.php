<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX = 'tickets_type_assignee_status_index';

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

        $indexes = collect(Schema::getIndexes('tickets'))
            ->pluck('name')
            ->filter();

        if (! $indexes->contains(self::INDEX)) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->index(
                    ['type', 'assigned_user_id', 'status'],
                    self::INDEX,
                );
            });
        }
    }

    public function down(): void
    {
        $indexes = collect(Schema::getIndexes('tickets'))
            ->pluck('name')
            ->filter();

        if ($indexes->contains(self::INDEX)) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropIndex(self::INDEX);
            });
        }

        if (Schema::hasColumn('tickets', 'assigned_user_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('assigned_user_id');
            });
        }

        if (Schema::hasColumn('tickets', 'digital_product_id')) {
            Schema::table('tickets', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('digital_product_id');
            });
        }
    }
};
