<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mobile_devices', 'push_provider')) {
            return;
        }

        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->string('push_provider', 16)
                ->default('expo')
                ->after('push_token')
                ->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mobile_devices', 'push_provider')) {
            return;
        }

        Schema::table('mobile_devices', function (Blueprint $table) {
            $table->dropIndex(['push_provider']);
            $table->dropColumn('push_provider');
        });
    }
};
