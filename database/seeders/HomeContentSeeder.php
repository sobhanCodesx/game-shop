<?php

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\SocialContent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HomeContentSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['title' => 'پرفروش‌های این هفته', 'subtitle' => 'محبوب‌ترین انتخاب گیمرها', 'content_type' => 'products', 'query_type' => 'popular'],
            ['title' => 'از دنیای گیمینگ چه خبر؟', 'subtitle' => 'آخرین پست‌های جامعه', 'content_type' => 'posts', 'query_type' => 'latest'],
            ['title' => 'ویدیوهای منتخب', 'subtitle' => 'نقد، بررسی و گیم‌پلی', 'content_type' => 'videos', 'query_type' => 'featured'],
            ['title' => 'شورت‌های داغ', 'subtitle' => 'کوتاه، سریع و سرگرم‌کننده', 'content_type' => 'shorts', 'query_type' => 'popular'],
        ];

        foreach ($sections as $index => $section) {
            HomeSection::query()->updateOrCreate(
                ['content_type' => $section['content_type'], 'query_type' => $section['query_type']],
                [...$section, 'items_limit' => 10, 'sort_order' => $index, 'is_active' => true],
            );
        }

        $content = [
            ['post', 'راهنمای انتخاب مانیتور گیمینگ در سال ۲۰۲۶', 'نرخ نوسازی، پنل و رزولوشن مناسب برای سبک بازی شما.', null, 12400, true],
            ['post', '۱۰ بازی که نباید در نسل نهم از دست بدهید', 'انتخاب‌هایی برای طرفداران داستان، رقابت و ماجراجویی.', null, 9800, false],
            ['post', 'چطور بهترین کنترلر را برای PC انتخاب کنیم؟', 'مقایسه ارگونومی، اتصال و سازگاری کنترلرهای محبوب.', null, 7600, false],
            ['video', 'بررسی کامل PlayStation 5 Pro', 'تجربه واقعی عملکرد، کیفیت تصویر و ارزش خرید.', 754, 48300, true],
            ['video', 'مقایسه هدست‌های محبوب گیمینگ', 'تست صدا، میکروفون و راحتی در استفاده طولانی.', 612, 31700, true],
            ['video', 'بهترین تنظیمات برای بازی رقابتی', 'تنظیمات کاربردی برای فریم بهتر و تأخیر کمتر.', 488, 25600, true],
            ['short', 'این حرکت مخفی را دیده بودی؟', null, 28, 87000, true],
            ['short', 'ستاپ گیمینگ مینیمال در ۳۰ ثانیه', null, 30, 69400, true],
            ['short', 'وقتی باس آخر فقط یک HP دارد!', null, 19, 112000, true],
            ['short', 'سه نکته برای Aim بهتر', null, 41, 93600, true],
        ];

        foreach ($content as $index => [$type, $title, $excerpt, $duration, $views, $featured]) {
            SocialContent::query()->updateOrCreate(
                ['slug' => Str::slug("{$type}-{$index}-gaming")],
                compact('type', 'title', 'excerpt', 'duration', 'views', 'featured') + ['status' => 'published', 'published_at' => now()->subHours($index + 1)],
            );
        }
    }
}
