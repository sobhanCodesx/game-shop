<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AndroidAppPage extends Model
{
    protected $fillable = ['content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'eyebrow' => 'PLAYNEXUS ANDROID',
            'hero_title' => 'دانلود اپلیکیشن اندروید پلی نکسوس',
            'hero_description' => 'نسخه رسمی PlayNexus برای اندروید؛ فید گیمینگ، ویدیوها، Game Radar و تجربه سریع‌تر پلی نکسوس را مستقیم روی موبایل داشته باشید.',
            'promo_title' => 'پلی نکسوس؛ همیشه همراهت',
            'promo_description' => 'برای دنبال کردن بازی‌ها، دیدن محتوای تازه و دسترسی سریع‌تر به امکانات PlayNexus، نسخه اندروید را از منبع رسمی دانلود کنید.',
            'seo_title' => 'دانلود نسخه اندروید پلی نکسوس | APK رسمی PlayNexus',
            'seo_description' => 'دانلود آخرین نسخه رسمی اپلیکیشن اندروید PlayNexus، مشاهده تغییرات نسخه‌ها، تاریخچه انتشار، تصاویر و امکانات اپ پلی نکسوس.',
            'media' => [],
        ];
    }

    /** @return array<string, mixed> */
    public static function current(): array
    {
        $content = static::query()->find(1)?->content ?? [];

        return array_replace(static::defaults(), is_array($content) ? $content : []);
    }
}
