<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->setMobileNavigation(true);
    }

    public function down(): void
    {
        $this->setMobileNavigation(false);
    }

    private function setMobileNavigation(bool $enabled): void
    {
        if (! Schema::hasTable('home_settings')) {
            return;
        }

        DB::transaction(function () use ($enabled): void {
            $row = DB::table('home_settings')->where('id', 1)->lockForUpdate()->first();

            $content = [];
            if ($row !== null && isset($row->content)) {
                $decoded = json_decode((string) $row->content, true);
                if (is_array($decoded)) {
                    $content = $decoded;
                }
            }

            $content['nexus_ai_show_in_nav'] = $enabled;

            DB::table('home_settings')->updateOrInsert(
                ['id' => 1],
                [
                    'content' => json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $row?->created_at ?? now(),
                    'updated_at' => now(),
                ],
            );
        });

        Cache::forget('nexus-ai.settings.v2');
        Cache::forget('nexus-ai.settings.v1');
    }
};
