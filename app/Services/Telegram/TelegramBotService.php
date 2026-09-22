<?php

namespace App\Services\Telegram;

use App\Models\TelegramBotAudit;
use Illuminate\Support\Str;
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

        $existing = TelegramBotAudit::query()->where('update_id', $updateId)->first();
        if ($existing && in_array($existing->status, ['processing', 'succeeded', 'ignored'], true)) {
            return;
        }

        [$userId, $chatId, $chatType, $messageId, $actionHint] = $this->actor($update);

        $audit = $existing ?: new TelegramBotAudit();
        $audit->fill([
            'update_id' => $updateId,
            'user_id' => $userId,
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'action' => $actionHint,
            'status' => 'processing',
            'payload' => $this->auditPayload($update),
            'error' => null,
        ]);
        $audit->save();

        $isOwner = $this->settings->acceptsUser($userId, $chatType);
        $isPrivateUser = $chatType === 'private' && filled($userId) && filled($chatId);

        if (! $isOwner && ! $isPrivateUser) {
            $audit->status = 'ignored';
            $audit->action = 'unauthorized_or_non_private';
            $audit->save();

            return;
        }

        $this->settings->markWebhookReceived();

        try {
            // A dashboard-generated /start connect_* deep link is an account
            // linking flow even when the Telegram sender is also the bot owner.
            // This keeps the owner's PlayNexus account linkable without ever
            // exposing the admin router to regular users.
            $isAccountLink = $isPrivateUser && $this->isAccountLinkUpdate($update);
            $result = $isOwner && ! $isAccountLink
                ? $this->router->handle($update)
                : $this->userRouter->handle($update);
            $audit->action = (string) ($result['action'] ?? $actionHint ?? 'handled');
            $audit->resource = $result['resource'] ?? null;
            $audit->resource_id = isset($result['resource_id']) ? (int) $result['resource_id'] : null;
            $audit->status = 'succeeded';
            $audit->error = null;
            $audit->save();
        } catch (Throwable $exception) {
            $audit->status = 'failed';
            $audit->error = Str::limit($exception->getMessage(), 2000);
            $audit->save();
            $this->settings->rememberError($exception->getMessage());

            throw $exception;
        }
    }

    private function isAccountLinkUpdate(array $update): bool
    {
        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $text = trim((string) ($message['text'] ?? ''));

        return (bool) preg_match(
            '/^\/start(?:@[A-Za-z0-9_]+)?\s+connect_[A-Za-z0-9]{32}$/',
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
                isset($message['message_id']) ? (int) $message['message_id'] : null,
                'callback',
            ];
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chat = is_array($message['chat'] ?? null) ? $message['chat'] : [];
        $from = is_array($message['from'] ?? null) ? $message['from'] : [];

        return [
            isset($from['id']) ? (string) $from['id'] : null,
            isset($chat['id']) ? (string) $chat['id'] : null,
            isset($chat['type']) ? (string) $chat['type'] : null,
            isset($message['message_id']) ? (int) $message['message_id'] : null,
            isset($message['text']) ? 'command_or_text' : 'media_or_message',
        ];
    }

    private function auditPayload(array $update): array
    {
        if (is_array($update['callback_query'] ?? null)) {
            return [
                'type' => 'callback_query',
                'callback_data' => Str::limit((string) ($update['callback_query']['data'] ?? ''), 200),
            ];
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : [];

        return [
            'type' => isset($message['text']) ? 'text' : 'media',
            'text' => isset($message['text']) ? Str::limit((string) $message['text'], 500) : null,
            'has_photo' => ! empty($message['photo']),
            'has_video' => isset($message['video']),
            'has_document' => isset($message['document']),
            'has_animation' => isset($message['animation']),
        ];
    }
}
