<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Cache;
use Throwable;

final class TelegramBotService
{
    public function __construct(
        private readonly TelegramBotSettings $settings,
        private readonly TelegramBotCommandRouter $router,
        private readonly TelegramUserCommandRouter $userRouter,
    ) {}

    public function handleUpdate(array $update): void
    {
        $updateId = (int) ($update['update_id'] ?? 0);
        if ($updateId < 1) {
            return;
        }

        // Telegram can retry the same webhook update. Keep idempotency on the
        // filesystem cache instead of inserting every incoming message into DB.
        $cache = Cache::store('file');
        $dedupeKey = 'telegram-webhook-update:'.$updateId;
        if (! $cache->add($dedupeKey, true, now()->addDay())) {
            return;
        }

        [$userId, $chatId, $chatType] = $this->actor($update);
        $isOwner = $this->settings->acceptsUser($userId, $chatType);
        $isPrivateUser = $chatType === 'private' && filled($userId) && filled($chatId);

        if (! $isOwner && ! $isPrivateUser) {
            return;
        }

        $this->settings->markWebhookReceived();

        try {
            // Account linking and verified self-contact are always handled by
            // the user-safe router, including when the sender is bot owner.
            $isUserAccountUpdate = $isPrivateUser && $this->isUserAccountUpdate($update);
            $isOwner && ! $isUserAccountUpdate
                ? $this->router->handle($update)
                : $this->userRouter->handle($update);
        } catch (Throwable $exception) {
            // Let Telegram retry failures while successful messages stay deduped.
            $cache->forget($dedupeKey);
            $this->settings->rememberError($exception->getMessage());

            throw $exception;
        }
    }

    private function isUserAccountUpdate(array $update): bool
    {
        $message = is_array($update['message'] ?? null) ? $update['message'] : [];

        if (is_array($message['contact'] ?? null)) {
            return true;
        }

        $text = trim((string) ($message['text'] ?? ''));

        return (bool) preg_match(
            '/^\/start(?:@[A-Za-z0-9_]+)?\s+(?:connect_|verifyphone_)[A-Za-z0-9]{32}$/',
            $text,
        );
    }

    private function actor(array $update): array
    {
        if (is_array($update['callback_query'] ?? null)) {
            $callback = $update['callback_query'];
            $message = is_array($callback['message'] ?? null) ? $callback['message'] : [];
            $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
            $from = is_array($callback['from'] ?? null) ? $callback['from'] : [];

            return [
                isset($from['id']) ? (string) $from['id'] : null,
                isset($chat['id']) ? (string) $chat['id'] : null,
                isset($chat['type']) ? (string) $chat['type'] : null,
            ];
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        return [
            isset($from['id']) ? (string) $from['id'] : null,
            isset($chat['id']) ? (string) $chat['id'] : null,
            isset($chat['type']) ? (string) $chat['type'] : null,
        ];
    }
}
