<?php

namespace App\Services\Telegram;

use Illuminate\Support\Str;

final class TelegramBotFormatter
{
    public function menuText(): string
    {
        return "<b>PlayNexus Admin Bot</b>\n"
            ."مدیریت مستقیم محتوا، مدیا و وضعیت انتشار از تلگرام.\n\n"
            ."برای عملیات حساس همیشه تأیید نهایی لازم است.";
    }

    public function help(): string
    {
        return "<b>راهنمای دستورات</b>\n\n"
            ."<code>/menu</code> منوی اصلی\n"
            ."<code>/status</code> وضعیت اتصال\n"
            ."<code>/search game query</code> جستجو\n"
            ."<code>/list video published</code> فهرست محتوا\n"
            ."<code>/get video 12</code> نمایش رکورد\n"
            ."<code>/assets video 12</code> نمایش مدیا\n"
            ."<code>/media video 12 video</code> آماده دریافت فایل\n"
            ."<code>/create feed {json}</code> ساخت محتوا\n"
            ."<code>/update video 12 {json}</code> ویرایش\n"
            ."<code>/state video 12 published</code> تغییر وضعیت\n"
            ."<code>/publish feed 12</code> انتشار فید\n"
            ."<code>/delete video 12</code> حذف\n"
            ."<code>/restore game 12</code> بازیابی game/studio\n"
            ."<code>/sync_collection 4 10,11,12</code> همگام‌سازی ویدیوها\n"
            ."<code>/graph { ... }</code> GraphQL فقط‌خواندنی\n"
            ."<code>/tool tool_name {json}</code> دسترسی کامل ابزارهای Content Agent\n"
            ."<code>/cancel</code> لغو عملیات جاری";
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
                $lines[] = "\nکل: <b>".(int) $result['pagination']['total']."</b>";
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
                $lines[] = "<a href=\"{$url}\">باز کردن در PlayNexus</a>";
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
