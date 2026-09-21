<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_patterns', function (Blueprint $table) {
            $table->json('provider_ids')->nullable()->after('provider_id');
        });

        DB::table('sms_patterns')->orderBy('id')->chunkById(100, function ($rows): void {
            foreach ($rows as $row) {
                if (filled($row->provider_id)) {
                    DB::table('sms_patterns')->where('id', $row->id)->update([
                        'provider_ids' => json_encode(['payamak_panel' => $row->provider_id], JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('sms_patterns', function (Blueprint $table) {
            $table->dropColumn('provider_ids');
        });
    }
};
