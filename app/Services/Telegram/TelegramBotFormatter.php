<?php

namespace App\Services\Telegram;

use Illuminate\Support\Str;

final class TelegramBotFormatter
{
    public function menuText(): string
    {
        return "✦ <b>PLAYNEXUS CONTROL</b> ✦\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."ربات خصوصی مدیریت PlayNexus آماده است.\n"
            ."از داشبورد زیر وارد بخش موردنظر شو؛ عملیات حساس قبل از اجرا دوباره تأیید می‌شوند.\n\n"
            ."🔐 <b>Private • Owner Only</b>";
    }

    public function hubText(string $hub): string
    {
        return match ($hub) {
            'content' => "✨ <b>Content Studio</b>\nفید، ویدیو، استوری و کالکشن‌ها را مدیریت کن.",
            'library' => "🎮 <b>Game Library</b>\nبازی‌ها، استودیوها، پلتفرم‌ها و کالکشن‌ها.",
            'commerce' => "🛍 <b>Commerce</b>\nمحصولات و مدیای فروشگاه.",
            'intelligence' => "🧠 <b>Intelligence</b>\nGame Events، GraphQL و وضعیت داده‌ها.",
            'system' => "⚙️ <b>System</b>\nسلامت Bot، راهنما و ابزارهای پیشرفته.",
            default => "✦ <b>PlayNexus</b>",
        };
    }

    public function resourceHub(string $label, string $icon): string
    {
        return "{$icon} <b>{$this->escape($label)}</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."عملیات موردنظر را انتخاب کن.";
    }

    public function help(): string
    {
        return "<b>راهنمای PlayNexus Admin Bot</b>\n\n"
            ."تقریباً همه کارهای روزمره از دکمه‌ها قابل انجام است. دستورات مستقیم برای سرعت بیشتر:\n\n"
            ."<code>/menu</code> داشبورد اصلی\n"
            ."<code>/status</code> وضعیت اتصال\n"
            ."<code>/search game query</code> جستجو\n"
            ."<code>/list video published</code> فهرست محتوا\n"
            ."<code>/get video 12</code> نمایش رکورد\n"
            ."<code>/assets video 12</code> نمایش مدیا\n"
            ."<code>/media video 12 video</code> دریافت فایل\n"
            ."<code>/create feed {json}</code> ساخت محتوا\n"
            ."<code>/update video 12 {json}</code> ویرایش\n"
            ."<code>/state video 12 published</code> تغییر وضعیت\n"
            ."<code>/publish feed 12</code> انتشار فید\n"
            ."<code>/delete video 12</code> حذف\n"
            ."<code>/restore game 12</code> بازیابی game/studio\n"
            ."<code>/sync_collection 4 10,11,12</code> همگام‌سازی ویدیوها\n"
            ."<code>/graph { ... }</code> GraphQL فقط‌خواندنی\n"
            ."<code>/tool tool_name {json}</code> ابزار پیشرفته Content Agent\n"
            ."<code>/cancel</code> لغو عملیات جاری";
    }

    public function advancedHelp(): string
    {
        return "🧰 <b>Advanced Tools</b>\n"
            ."برای عملیات خاصی که دکمه اختصاصی ندارند از <code>/tool</code> استفاده کن.\n\n"
            ."نمونه:\n"
            ."<code>/tool select_content {\"resource\":\"video\",\"limit\":5}</code>\n\n"
            ."دستور <code>/schema</code> ساختار Graph را نشان می‌دهد و <code>/graph</code> Query خام را اجرا می‌کند.";
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
                $lines[] = "\n📚 <b>{$from}–{$to}</b> از <b>{$total}</b>";
            }

            return implode("\n", $lines);
        }

        if (array_is_list($result)) {
            $lines = ["<b>".$this->escape($title)."</b>"];
            foreach (array_slice($result, 0, 12) as $item) {
                $lines[] = is_array($item) ? $this->itemLine($item) : '• '.$this->escape((string) $item);
            }

            return implode("\n", $lines);
        }

        if (isset($result['id']) || isset($result['title']) || isset($result['name'])) {
            return "<b>".$this->escape($title)."</b>\n".$this->itemDetails($result);
        }

        $json = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';

        return "<b>".$this->escape($title)."</b>\n<pre>".$this->escape(Str::limit($json, 3200, "\n… truncated"))."</pre>";
    }

    public function itemDetails(array $item): string
    {
        $lines = [];
        foreach ([
            'id' => 'ID',
            'name' => 'نام',
            'title' => 'عنوان',
            'status' => 'وضعیت',
            'visibility' => 'Visibility',
            'slug' => 'Slug',
            'release_date' => 'Release',
            'published_at' => 'Published',
            'updated_at' => 'Updated',
        ] as $key => $label) {
            if (isset($item[$key]) && $item[$key] !== '') {
                $lines[] = "<b>{$label}:</b> ".$this->escape((string) $item[$key]);
            }
        }

        foreach (['url', 'public_url', 'link_url'] as $key) {
            if (filled($item[$key] ?? null)) {
                $url = $this->escape((string) $item[$key]);
                $lines[] = "<a href=\"{$url}\">↗️ باز کردن در PlayNexus</a>";
                break;
            }
        }

        return implode("\n", $lines) ?: '<i>رکورد بدون خلاصه نمایشی است.</i>';
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function itemLine(array $item): string
    {
        $id = isset($item['id']) ? '#'.(int) $item['id'].' ' : '';
        $name = (string) ($item['title'] ?? $item['name'] ?? $item['slug'] ?? 'بدون عنوان');
        $state = (string) ($item['status'] ?? $item['visibility'] ?? '');

        return '• <b>'.$this->escape($id.$name).'</b>'.($state !== '' ? ' — '.$this->escape($state) : '');
    }
}
