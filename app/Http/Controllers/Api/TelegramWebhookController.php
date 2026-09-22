<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramBotSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class TelegramWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        TelegramBotSettings $settings,
        TelegramBotService $bot,
    ): JsonResponse {
        $resolved = $settings->resolved();
        $expected = trim((string) ($resolved['webhook_secret'] ?? ''));
        $received = trim((string) $request->header('X-Telegram-Bot-Api-Secret-Token', ''));

        if ($expected === '' || $received === '' || ! hash_equals($expected, $received)) {
            return response()->json(['ok' => false], 403);
        }

        if (! ($resolved['enabled'] ?? false)) {
            return response()->json(['ok' => true]);
        }

        $payload = $request->json()->all();
        if (! is_array($payload) || empty($payload['update_id'])) {
            return response()->json(['ok' => false, 'message' => 'Invalid Telegram update.'], 422);
        }

        try {
            $bot->handleUpdate($payload);

            return response()->json(['ok' => true]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['ok' => false, 'message' => 'Telegram update processing failed.'], 500);
        }
    }
}
