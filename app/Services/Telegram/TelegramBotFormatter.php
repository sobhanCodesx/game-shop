<?php

namespace App\Services\Telegram;

use Illuminate\Support\Str;

final class TelegramBotFormatter
{
    public function menuText(): string
    {
        return "🎮 <b>مرکز مدیریت PlayNexus</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."همه‌چیز از همین‌جا با دکمه و فرم مرحله‌ای قابل مدیریت است.\n"
            ."برای ساخت یا ویرایش محتوا لازم نیست JSON، ID یا دستور فنی بنویسی.\n\n"
            ."🔐 <b>خصوصی • فقط مالک</b>";
    }

    public function hubText(string $hub): string
    {
        return match ($hub) {
            'content' => "✨ <b>استودیوی محتوا</b>\nفید، ویدیو، استوری و کالکشن‌ها را مدیریت کن.",
            'library' => "🎮 <b>کتابخانه بازی</b>\nبازی‌ها، استودیوها، پلتفرم‌ها و کالکشن‌ها.",
            'commerce' => "🛍 <b>فروشگاه</b>\nمحصولات و فایل‌های فروشگاه.",
            'intelligence' => "🧠 <b>هوشمندی</b>\nرویدادهای مهم بازی‌ها را ببین و مدیریت کن.",
            'system' => "⚙️ <b>سیستم</b>\nوضعیت اتصال، راهنما و تنظیمات ربات.",
            default => "🎮 <b>PlayNexus</b>",
        };
    }

    public function resourceHub(string $label, string $icon): string
    {
        return "{$icon} <b>{$this->escape($label)}</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."کاری که می‌خواهی انجام بده را انتخاب کن.";
    }

    public function help(): string
    {
        return "❓ <b>راهنمای ربات PlayNexus</b>\n\n"
            ."برای کارهای روزمره از دکمه‌ها استفاده کن؛ ساخت و ویرایش محتوا کاملاً مرحله‌به‌مرحله است.\n\n"
            ."دستورهای سریع:\n"
            ."<code>/menu</code> منوی اصلی\n"
            ."<code>/new</code> ساخت محتوای جدید\n"
            ."<code>/videos</code> مدیریت ویدیوها\n"
            ."<code>/feeds</code> مدیریت فیدها\n"
            ."<code>/stories</code> مدیریت استوری‌ها\n"
            ."<code>/games</code> مدیریت بازی‌ها\n"
            ."<code>/collections</code> مدیریت کالکشن‌ها\n"
            ."<code>/users</code> کاربران PlayNexus\n"
            ."<code>/status</code> وضعیت اتصال\n"
            ."<code>/cancel</code> لغو عملیات جاری\n\n"
            ."برای جستجو، انتخاب بازی/استودیو/کالکشن، انتشار و مدیا هم از دکمه‌های همان بخش استفاده کن.";
    }

    public function advancedHelp(): string
    {
        return "🧰 <b>ابزارهای فنی</b>\n"
            ."این بخش برای استفاده روزمره لازم نیست. مدیریت عادی PlayNexus از منوها و فرم‌های مرحله‌ای انجام می‌شود.";
    }

    public function result(string $title, array $result): string
    {
        if (isset($result['items']) && is_array($result['items'])) {
            $lines = ["<b>".$this->escape($title)."</b>"];
            foreach (array_slice($result['items'], 0, 12) as $item) {
                if (is_array($item)) {
                    $lines[] = $this->itemLine($item);
                }
            }

            if (isset($result['pagination']['total'])) {
                $total = (int) $result['pagination']['total'];
                $offset = (int) ($result['pagination']['offset'] ?? 0);
                $limit = (int) ($result['pagination']['limit'] ?? count($result['items']));
                $from = $total > 0 ? $offset + 1 : 0;
                $to = min($offset + $limit, $total);
                $lines[] = "\n📚 <b>{$from} تا {$to}</b> از <b>{$total}</b>";
            }

            return implode("\n", $lines);
        }

        if (array_is_list($result)) {
            $lines = ["<b>".$this->escape($title)."</b>"];
            foreach (array_slice($result, 0, 12) as $item) {
                $lines[] = is_array($item)
                    ? $this->itemLine($item)
                    : '• '.$this->escape((string) $item);
            }

            return implode("\n", $lines);
        }

        if (isset($result['id']) || isset($result['title']) || isset($result['name'])) {
            return "<b>".$this->escape($title)."</b>\n".$this->itemDetails($result);
        }

        $lines = ["<b>".$this->escape($title)."</b>"];
        foreach ($result as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $lines[] = '• '.$this->escape($this->friendlyKey((string) $key)).': '
                    .$this->escape($this->friendlyValue($key, $value));
            }
        }

        return implode("\n", array_slice($lines, 0, 18));
    }

    public function itemDetails(array $item): string
    {
        $lines = [];
        foreach ([
            'id' => 'شناسه',
            'name' => 'نام',
            'title' => 'عنوان',
            'status' => 'وضعیت',
            'visibility' => 'نمایش',
            'slug' => 'آدرس',
            'release_date' => 'تاریخ انتشار',
            'published_at' => 'زمان انتشار',
            'updated_at' => 'آخرین ویرایش',
        ] as $key => $label) {
            if (isset($item[$key]) && $item[$key] !== '') {
                $value = in_array($key, ['status', 'visibility'], true)
                    ? $this->translateState((string) $item[$key])
                    : (string) $item[$key];

                $lines[] = "<b>{$label}:</b> ".$this->escape($value);
            }
        }

        foreach (['url', 'public_url', 'link_url'] as $key) {
            if (filled($item[$key] ?? null)) {
                $url = $this->escape((string) $item[$key]);
                $lines[] = "<a href=\"{$url}\">↗️ باز کردن در PlayNexus</a>";
                break;
            }
        }

        return implode("\n", $lines) ?: '<i>اطلاعات نمایشی بیشتری برای این رکورد وجود ندارد.</i>';
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function translateState(string $state): string
    {
        return match ($state) {
            'draft' => 'پیش‌نویس',
            'published' => 'منتشرشده',
            'active' => 'فعال',
            'inactive' => 'غیرفعال',
            'public' => 'عمومی',
            'private' => 'خصوصی',
            'candidate' => 'پیشنهادی',
            'dismissed' => 'ردشده',
            default => $state,
        };
    }

    private function itemLine(array $item): string
    {
        $id = isset($item['id']) ? '#'.(int) $item['id'].' ' : '';
        $name = (string) ($item['title'] ?? $item['name'] ?? $item['slug'] ?? 'بدون عنوان');
        $state = (string) ($item['status'] ?? $item['visibility'] ?? '');

        return '• <b>'.$this->escape($id.$name).'</b>'
            .($state !== '' ? ' — '.$this->escape($this->translateState($state)) : '');
    }

    private function friendlyKey(string $key): string
    {
        return match ($key) {
            'ok' => 'نتیجه',
            'message' => 'پیام',
            'status' => 'وضعیت',
            'total' => 'تعداد',
            'id' => 'شناسه',
            default => Str::headline($key),
        };
    }

    private function friendlyValue(string|int $key, mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }

        if (in_array((string) $key, ['status', 'visibility'], true)) {
            return $this->translateState((string) $value);
        }

        return Str::limit((string) ($value ?? '—'), 180);
    }
}
