<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('platforms', 'is_dual_platform')) {
            Schema::table('platforms', function (Blueprint $table): void {
                $table->boolean('is_dual_platform')->default(false)->after('manufacturer');
            });
        }

        if (! Schema::hasTable('platform_variants')) {
            Schema::create('platform_variants', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('key', 120);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique(['platform_id', 'key']);
                $table->index(['platform_id', 'sort_order']);
            });
        }

        if (! Schema::hasTable('digital_offer_prices')) {
            Schema::create('digital_offer_prices', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('digital_offer_id')->constrained('digital_offers')->cascadeOnDelete();
                $table->foreignId('platform_variant_id')->constrained('platform_variants')->cascadeOnDelete();
                $table->unsignedBigInteger('price')->default(0);
                $table->timestamps();

                $table->unique(['digital_offer_id', 'platform_variant_id']);
                $table->index(['platform_variant_id', 'price']);
            });
        }

        if (Schema::hasTable('digital_orders')) {
            if (! Schema::hasColumn('digital_orders', 'platform_variant_id')) {
                Schema::table('digital_orders', function (Blueprint $table): void {
                    $table->foreignId('platform_variant_id')
                        ->nullable()
                        ->after('digital_offer_id')
                        ->constrained('platform_variants')
                        ->nullOnDelete();
                });
            }

            if (! Schema::hasColumn('digital_orders', 'platform_variant_name')) {
                Schema::table('digital_orders', function (Blueprint $table): void {
                    $table->string('platform_variant_name')->nullable()->after('platform_variant_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('digital_orders') && Schema::hasColumn('digital_orders', 'platform_variant_name')) {
            Schema::table('digital_orders', function (Blueprint $table): void {
                $table->dropColumn('platform_variant_name');
            });
        }

        if (Schema::hasTable('digital_orders') && Schema::hasColumn('digital_orders', 'platform_variant_id')) {
            Schema::table('digital_orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('platform_variant_id');
            });
        }

        Schema::dropIfExists('digital_offer_prices');
        Schema::dropIfExists('platform_variants');

        if (Schema::hasColumn('platforms', 'is_dual_platform')) {
            Schema::table('platforms', function (Blueprint $table): void {
                $table->dropColumn('is_dual_platform');
            });
        }
    }
};
