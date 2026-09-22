<?php

namespace App\Notifications\Channels;

use App\Models\User;
use App\Services\Telegram\TelegramApiClient;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use Throwable;

final class TelegramChannel
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || blank($notifiable->telegram_chat_id)) {
            return;
        }

        if (! ($notifiable->contentNotificationPreference?->telegram_enabled ?? false)) {
            return;
        }

        $payload = method_exists($notification, 'toTelegram')
            ? $notification->toTelegram($notifiable)
            : $notification->toArray($notifiable);

        if (! is_array($payload)) {
            return;
        }

        $title = trim((string) ($payload['title'] ?? 'PlayNexus'));
        $message = trim((string) ($payload['message'] ?? ''));
        $text = "🎮 <b>".$this->escape($title)."</b>";
        if ($message !== '') {
            $text .= "\n".$this->escape(Str::limit($message, 700));
        }

        $replyMarkup = $this->replyMarkup($payload['url'] ?? null);
        $image = trim((string) ($payload['image_url'] ?? ''));
        if ($image !== '' && ! filter_var($image, FILTER_VALIDATE_URL)) {
            $image = url('/'.ltrim($image, '/'));
        }

        try {
            if ($image !== '') {
                $this->telegram->sendPhoto(
                    (string) $notifiable->telegram_chat_id,
                    $image,
                    $text,
                    $replyMarkup,
                );

                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->telegram->sendMessage(
            (string) $notifiable->telegram_chat_id,
            $text,
            $replyMarkup,
        );
    }

    private function replyMarkup(mixed $url): ?array
    {
        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        $absolute = filter_var($url, FILTER_VALIDATE_URL)
            ? $url
            : url('/'.ltrim($url, '/'));

        return [
            'inline_keyboard' => [[[
                'text' => 'مشاهده در PlayNexus ↗️',
                'url' => $absolute,
            ]]],
        ];
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
