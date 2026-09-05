<?php

use App\Support\RichText;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $videos = DB::table('social_contents')
            ->where('type', 'video')
            ->whereNull('body')
            ->whereNotNull('excerpt')
            ->get(['id', 'excerpt']);

        foreach ($videos as $video) {
            $plainText = RichText::plainText($video->excerpt);
            if (! $plainText || mb_strlen($plainText) <= 500) {
                continue;
            }

            DB::table('social_contents')->where('id', $video->id)->update([
                'body' => RichText::sanitize($video->excerpt),
                'excerpt' => Str::limit($plainText, 300, '…'),
            ]);
        }
    }

    public function down(): void
    {
        // Editorial content is intentionally retained on rollback.
    }
};
