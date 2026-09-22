<?php

namespace App\Services\Telegram;

use App\Models\TelegramBotSession;
use Illuminate\Support\Str;

final class TelegramBotSessionStore
{
    public function get(string|int $userId, string|int $chatId): ?TelegramBotSession
    {
        $session = TelegramBotSession::query()
            ->where('user_id', (string) $userId)
            ->where('chat_id', (string) $chatId)
            ->first();

        if ($session && $session->expires_at?->isPast()) {
            $session->delete();

            return null;
        }

        return $session;
    }

    public function put(
        string|int $userId,
        string|int $chatId,
        string $state,
        array $context = [],
        ?int $ttlSeconds = null,
    ): TelegramBotSession {
        $ttlSeconds ??= (int) config('telegram_bot.session_ttl_seconds', 1800);

        return TelegramBotSession::query()->updateOrCreate(
            [
                'user_id' => (string) $userId,
                'chat_id' => (string) $chatId,
            ],
            [
                'state' => $state,
                'context' => $context,
                'expires_at' => now()->addSeconds($ttlSeconds),
            ],
        );
    }

    public function clear(string|int $userId, string|int $chatId): void
    {
        TelegramBotSession::query()
            ->where('user_id', (string) $userId)
            ->where('chat_id', (string) $chatId)
            ->delete();
    }

    public function queueConfirmation(
        string|int $userId,
        string|int $chatId,
        string $tool,
        array $arguments,
        string $summary,
    ): string {
        $token = Str::random(12);

        $this->put(
            $userId,
            $chatId,
            'pending_confirmation',
            [
                'token' => $token,
                'tool' => $tool,
                'arguments' => $arguments,
                'summary' => $summary,
            ],
            (int) config('telegram_bot.confirmation_ttl_seconds', 300),
        );

        return $token;
    }

    public function consumeConfirmation(
        string|int $userId,
        string|int $chatId,
        string $token,
    ): ?array {
        $session = $this->get($userId, $chatId);
        if (! $session || $session->state !== 'pending_confirmation') {
            return null;
        }

        $context = is_array($session->context) ? $session->context : [];
        if (! hash_equals((string) ($context['token'] ?? ''), $token)) {
            return null;
        }

        $session->delete();

        return $context;
    }
}
