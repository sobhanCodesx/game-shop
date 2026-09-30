<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('digital_product_media')) {
            Schema::create('digital_product_media', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_product_id')->constrained()->cascadeOnDelete();
                $table->string('type', 20);
                $table->string('path', 500);
                $table->string('alt')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_primary')->default(false)->index();
                $table->timestamps();

                $table->index(['digital_product_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('digital_product_features')) {
            Schema::create('digital_product_features', function (Blueprint $table) {
                $table->id();
                $table->foreignId('digital_product_id')->constrained()->cascadeOnDelete();
                $table->string('name', 120);
                $table->text('value');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['digital_product_id', 'sort_order']);
            });
        }

        if (Schema::hasTable('digital_offers') && Schema::hasColumn('digital_offers', 'supplier_cost')) {
            Schema::table('digital_offers', fn (Blueprint $table) => $table->dropColumn('supplier_cost'));
        }

        if (Schema::hasTable('digital_orders') && Schema::hasColumn('digital_orders', 'supplier_cost')) {
            Schema::table('digital_orders', fn (Blueprint $table) => $table->dropColumn('supplier_cost'));
        }

        if (Schema::hasTable('digital_orders') && DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE digital_orders MODIFY number VARCHAR(64) NOT NULL');

            if (! $this->hasIndex('digital_orders', 'digital_orders_number_unique')) {
                DB::statement('ALTER TABLE digital_orders ADD UNIQUE digital_orders_number_unique (number)');
            }

            if (! $this->hasIndex('digital_orders', 'digital_orders_seller_id_order_status_index')) {
                DB::statement('ALTER TABLE digital_orders ADD INDEX digital_orders_seller_id_order_status_index (seller_id, order_status)');
            }

            if (! $this->hasIndex('digital_orders', 'digital_orders_user_id_created_at_index')) {
                DB::statement('ALTER TABLE digital_orders ADD INDEX digital_orders_user_id_created_at_index (user_id, created_at)');
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('digital_product_features');
        Schema::dropIfExists('digital_product_media');
    }

    private function hasIndex(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT COUNT(*) AS aggregate FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$table, $index],
        );

        return (int) ($row->aggregate ?? 0) > 0;
    }
};
