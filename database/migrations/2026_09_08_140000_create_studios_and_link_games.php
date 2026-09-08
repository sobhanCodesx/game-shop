<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('studios')) {
            Schema::create('studios', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('logo')->nullable();
                $table->string('background')->nullable();
                $table->longText('description')->nullable();
                $table->string('website')->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (Schema::hasTable('games') && ! Schema::hasColumn('games', 'studio_id')) {
            Schema::table('games', function (Blueprint $table) {
                // Keep recovery compatible with databases where the studios table
                // was created by an earlier, partially completed deployment.
                $table->foreignId('studio_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('games') && Schema::hasColumn('games', 'studio_id')) {
            $hasForeignKey = collect(Schema::getForeignKeys('games'))
                ->contains(fn (array $foreign) => in_array('studio_id', $foreign['columns'] ?? [], true));

            if ($hasForeignKey) {
                Schema::table('games', fn (Blueprint $table) => $table->dropForeign(['studio_id']));
            }

            Schema::table('games', fn (Blueprint $table) => $table->dropColumn('studio_id'));
        }

        Schema::dropIfExists('studios');
    }
};
