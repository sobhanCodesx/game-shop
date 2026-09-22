<?php

namespace App\Services\Telegram;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class TelegramBotCommandRouter
{
    private const RESOURCE_ALIASES = [
        'game' => 'game',
        'games' => 'game',
        'studio' => 'studio',
        'studios' => 'studio',
        'platform' => 'platform',
        'platforms' => 'platform',
        'collection' => 'collection',
        'collections' => 'collection',
        'feed' => 'feed',
        'feeds' => 'feed',
        'post' => 'feed',
        'posts' => 'feed',
        'story' => 'story',
        'stories' => 'story',
        'short' => 'story',
        'shorts' => 'story',
        'video' => 'video',
        'videos' => 'video',
        'product' => 'product',
        'products' => 'product',
    ];

    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramBotSettings $settings,
        private readonly TelegramBotExecutor $executor,
        private readonly TelegramBotFormatter $formatter,
        private readonly TelegramBotSessionStore $sessions,
        private readonly TelegramMediaTransferService $mediaTransfer,
    ) {}

    public function handle(array $update): array
    {
        if (is_array($update['callback_query'] ?? null)) {
            return $this->handleCallback($update['callback_query']);
        }

        $message = is_array($update['message'] ?? null) ? $update['message'] : [];
        $chatId = (string) ($message['chat']['id'] ?? '');
        $userId = (string) ($message['from']['id'] ?? '');

        if ($chatId === '' || $userId === '') {
            return ['action' => 'ignored_empty_actor'];
        }

        $telegramFile = $this->extractTelegramFile($message);
        if ($telegramFile !== null) {
            return $this->handleMedia($userId, $chatId, $message, $telegramFile);
        }

        $text = trim((string) ($message['text'] ?? ''));
        if ($text === '') {
            $this->send($chatId, 'این نوع پیام هنوز عملیاتی ندارد. از <code>/menu</code> استفاده کن.');

            return ['action' => 'unsupported_message'];
        }

        $session = $this->sessions->get($userId, $chatId);
        if (! str_starts_with($text, '/') && $session?->state === 'awaiting_tool_json') {
            return $this->handleAwaitingJson($userId, $chatId, $session->context ?? [], $text);
        }

        if (! str_starts_with($text, '/') && $session?->state === 'pending_confirmation') {
            $this->send($chatId, 'یک عملیات منتظر تأیید است؛ از دکمه‌های تأیید/لغو همان پیام استفاده کن یا <code>/cancel</code> بزن.');

            return ['action' => 'confirmation_waiting'];
        }

        return $this->handleCommand($userId, $chatId, $text);
    }

    private function handleCommand(string $userId, string $chatId, string $text): array
    {
        [$command, $rest] = $this->splitCommand($text);

        return match ($command) {
            '/start', '/menu' => $this->showMenu($chatId),
            '/help' => $this->sendAndReturn($chatId, $this->formatter->help(), 'help'),
            '/status' => $this->showStatus($chatId),
            '/cancel' => $this->cancel($userId, $chatId),
            '/search' => $this->commandSearch($chatId, $rest),
            '/list' => $this->commandList($chatId, $rest),
            '/get' => $this->commandGet($chatId, $rest),
            '/assets' => $this->commandAssets($chatId, $rest),
            '/events' => $this->commandEvents($chatId, $rest),
            '/schema' => $this->executeReadTool($chatId, 'describe_playnexus_graph', []),
            '/graph' => $this->executeReadTool($chatId, 'query_playnexus_graph', ['query' => $rest]),
            '/tool' => $this->commandTool($userId, $chatId, $rest),
            '/create' => $this->commandCreate($userId, $chatId, $rest),
            '/update' => $this->commandUpdate($userId, $chatId, $rest),
            '/state' => $this->commandState($userId, $chatId, $rest),
            '/publish' => $this->commandPublish($userId, $chatId, $rest),
            '/unpublish' => $this->commandUnpublish($userId, $chatId, $rest),
            '/delete' => $this->commandDelete($userId, $chatId, $rest),
            '/restore' => $this->commandRestore($userId, $chatId, $rest),
            '/sync_collection' => $this->commandSyncCollection($userId, $chatId, $rest),
            '/media' => $this->commandMedia($userId, $chatId, $rest),
            default => $this->sendAndReturn(
                $chatId,
                "دستور ناشناخته است.\n\n".$this->formatter->help(),
                'unknown_command',
            ),
        };
    }

    private function handleCallback(array $callback): array
    {
        $callbackId = (string) ($callback['id'] ?? '');
        $data = (string) ($callback['data'] ?? '');
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $userId = (string) ($callback['from']['id'] ?? '');

        if ($callbackId !== '') {
            $this->telegram->answerCallbackQuery($callbackId);
        }

        if ($chatId === '' || $userId === '') {
            return ['action' => 'callback_without_actor'];
        }

        $parts = explode(':', $data);
        $action = $parts[0] ?? '';

        if ($action === 'menu') {
            $resource = $parts[1] ?? '';
            if ($resource === 'home') {
                return $this->showMenu($chatId);
            }
            if ($resource === 'status') {
                return $this->showStatus($chatId);
            }
            if ($resource === 'help') {
                return $this->sendAndReturn($chatId, $this->formatter->help(), 'help');
            }

            return $this->sendResourceList($chatId, $this->resource($resource));
        }

        if ($action === 'view') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);

            return $this->showResource($chatId, $resource, $id);
        }

        if ($action === 'assets') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);

            return $this->executeReadTool($chatId, 'list_content_assets', [
                'resource' => $resource,
                'id' => $id,
            ]);
        }

        if ($action === 'media-slots') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);

            return $this->showMediaSlots($chatId, $resource, $id);
        }

        if ($action === 'await-media') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);
            $slot = (string) ($parts[3] ?? '');
            $this->sessions->put($userId, $chatId, 'awaiting_media', compact('resource', 'id', 'slot'));
            $this->send(
                $chatId,
                "فایل را همین حالا بفرست.\n<b>Resource:</b> ".$this->formatter->escape($resource)
                ."\n<b>ID:</b> {$id}\n<b>Slot:</b> ".$this->formatter->escape($slot)
                ."\n\nحد Bot API رسمی برای دانلود فایل توسط بات 20MB است. برای لغو: <code>/cancel</code>",
            );

            return ['action' => 'awaiting_media', 'resource' => $resource, 'resource_id' => $id];
        }

        if ($action === 'template') {
            $resource = $this->resource($parts[1] ?? '');
            $mode = $parts[2] ?? 'create';
            $id = isset($parts[3]) ? $this->positiveInt($parts[3]) : null;

            return $this->promptJson($userId, $chatId, $resource, $mode, $id);
        }

        if ($action === 'state') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);
            $state = (string) ($parts[3] ?? '');

            return $this->queueTool($userId, $chatId, 'set_content_state', compact('resource', 'id', 'state'));
        }

        if ($action === 'publish-feed') {
            $id = $this->positiveInt($parts[1] ?? null);

            return $this->queueTool($userId, $chatId, 'publish_feed', ['id' => $id]);
        }

        if ($action === 'unpublish-feed') {
            $id = $this->positiveInt($parts[1] ?? null);

            return $this->queueTool($userId, $chatId, 'unpublish_feed', ['id' => $id]);
        }

        if ($action === 'delete') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);

            return $this->queueTool($userId, $chatId, 'delete_content', compact('resource', 'id'));
        }

        if ($action === 'confirm') {
            $token = (string) ($parts[1] ?? '');
            $pending = $this->sessions->consumeConfirmation($userId, $chatId, $token);
            if (! $pending) {
                $this->send($chatId, 'این تأیید منقضی شده یا متعلق به عملیات دیگری است.');

                return ['action' => 'confirmation_expired'];
            }

            $tool = (string) ($pending['tool'] ?? '');
            $arguments = is_array($pending['arguments'] ?? null) ? $pending['arguments'] : [];
            $result = $this->executor->execute($tool, $arguments);
            $this->send($chatId, "✅ عملیات انجام شد.\n\n".$this->formatter->result($tool, $result), $this->menuKeyboard());

            return [
                'action' => 'tool_confirmed:'.$tool,
                'resource' => $arguments['resource'] ?? null,
                'resource_id' => $arguments['id'] ?? $arguments['collection_id'] ?? null,
            ];
        }

        if ($action === 'cancel') {
            $this->sessions->clear($userId, $chatId);
            $this->send($chatId, 'عملیات لغو شد.', $this->menuKeyboard());

            return ['action' => 'callback_cancel'];
        }

        $this->send($chatId, 'این دکمه دیگر معتبر نیست. <code>/menu</code> را باز کن.');

        return ['action' => 'unknown_callback'];
    }

    private function showMenu(string $chatId): array
    {
        $this->send($chatId, $this->formatter->menuText(), $this->menuKeyboard());

        return ['action' => 'menu'];
    }

    private function showStatus(string $chatId): array
    {
        $settings = $this->settings->resolved();
        $webhook = [];
        $webhookError = null;

        try {
            $webhook = $this->telegram->getWebhookInfo();
        } catch (\Throwable $exception) {
            $webhookError = $exception->getMessage();
        }

        $text = "<b>وضعیت PlayNexus Bot</b>\n"
            ."فعال: <b>".(($settings['enabled'] ?? false) ? 'بله ✅' : 'خیر ⛔')."</b>\n"
            ."نوشتن: <b>".(($settings['write_enabled'] ?? false) ? 'فعال' : 'غیرفعال')."</b>\n"
            ."انتشار: <b>".(($settings['publish_enabled'] ?? false) ? 'فعال' : 'غیرفعال')."</b>\n"
            ."حذف: <b>".(($settings['destructive_enabled'] ?? false) ? 'فعال' : 'غیرفعال')."</b>\n"
            ."مدیا: <b>".(($settings['media_enabled'] ?? false) ? 'فعال' : 'غیرفعال')."</b>\n"
            ."Proxy: <b>".(($settings['use_proxy'] ?? false) ? 'فعال' : 'مستقیم')."</b>\n"
            ."Bot: <b>@".$this->formatter->escape((string) ($settings['bot_username'] ?? 'نامشخص'))."</b>";

        if ($webhookError) {
            $text .= "\nWebhook: ❌ ".$this->formatter->escape($webhookError);
        } else {
            $text .= "\nWebhook pending: <b>".(int) ($webhook['pending_update_count'] ?? 0)."</b>";
            if (filled($webhook['last_error_message'] ?? null)) {
                $text .= "\nآخرین خطای Telegram: ".$this->formatter->escape((string) $webhook['last_error_message']);
            }
        }

        $this->send($chatId, $text, $this->menuKeyboard());

        return ['action' => 'status'];
    }

    private function commandSearch(string $chatId, string $rest): array
    {
        [$resourceRaw, $query] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, '');
        $resource = $this->resource($resourceRaw);
        if (trim($query) === '') {
            throw new InvalidArgumentException('نمونه: /search game Witcher');
        }

        return $this->sendResourceList($chatId, $resource, $query);
    }

    private function commandList(string $chatId, string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest), 2) ?: [];
        $resource = $this->resource($parts[0] ?? '');
        $status = trim((string) ($parts[1] ?? ''));

        $arguments = [
            'resource' => $resource,
            'limit' => 12,
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];
        if ($status !== '') {
            $arguments['status'] = $status;
        }

        $result = $this->executor->execute('select_content', $arguments);
        $this->send($chatId, $this->formatter->result("آخرین {$resource}", $result), $this->resourceListKeyboard($resource, $result));

        return ['action' => 'list', 'resource' => $resource];
    }

    private function commandGet(string $chatId, string $rest): array
    {
        [$resourceRaw, $idRaw] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, null);

        return $this->showResource($chatId, $this->resource((string) $resourceRaw), $this->positiveInt($idRaw));
    }

    private function commandAssets(string $chatId, string $rest): array
    {
        [$resourceRaw, $idRaw] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, null);
        $resource = $this->resource((string) $resourceRaw);
        $id = $this->positiveInt($idRaw);

        return $this->executeReadTool($chatId, 'list_content_assets', compact('resource', 'id'));
    }

    private function commandEvents(string $chatId, string $rest): array
    {
        $arguments = ['limit' => 15];
        if (trim($rest) !== '') {
            $arguments['game_id'] = $this->positiveInt(trim($rest));
        }

        return $this->executeReadTool($chatId, 'list_game_events', $arguments);
    }

    private function commandTool(string $userId, string $chatId, string $rest): array
    {
        if (! preg_match('/^([a-z0-9_]+)(?:\s+(.+))?$/si', trim($rest), $match)) {
            throw new InvalidArgumentException('نمونه: /tool select_content {"resource":"video","limit":5}');
        }

        $tool = $match[1];
        $json = trim((string) ($match[2] ?? '{}'));
        $arguments = $this->decodeJson($json);

        return $this->handleTool($userId, $chatId, $tool, $arguments);
    }

    private function commandCreate(string $userId, string $chatId, string $rest): array
    {
        if (! preg_match('/^(\S+)(?:\s+(.+))?$/s', trim($rest), $match)) {
            throw new InvalidArgumentException('نمونه: /create feed {"title":"...","body":"..."}');
        }

        $resource = $this->resource($match[1]);
        $tool = $this->createTool($resource);
        $json = trim((string) ($match[2] ?? ''));

        if ($json === '') {
            return $this->promptJson($userId, $chatId, $resource, 'create');
        }

        return $this->handleTool($userId, $chatId, $tool, $this->decodeJson($json));
    }

    private function commandUpdate(string $userId, string $chatId, string $rest): array
    {
        if (! preg_match('/^(\S+)\s+(\d+)(?:\s+(.+))?$/s', trim($rest), $match)) {
            throw new InvalidArgumentException('نمونه: /update video 12 {"title":"عنوان جدید"}');
        }

        $resource = $this->resource($match[1]);
        $id = $this->positiveInt($match[2]);
        $json = trim((string) ($match[3] ?? ''));

        if ($json === '') {
            return $this->promptJson($userId, $chatId, $resource, 'update', $id);
        }

        return $this->handleTool($userId, $chatId, 'update_content', [
            'resource' => $resource,
            'id' => $id,
            'data' => $this->decodeJson($json),
        ]);
    }

    private function commandState(string $userId, string $chatId, string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest), 3) ?: [];
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('نمونه: /state video 12 published');
        }

        $resource = $this->resource($parts[0]);
        $id = $this->positiveInt($parts[1]);
        $state = $parts[2];

        return $this->queueTool($userId, $chatId, 'set_content_state', compact('resource', 'id', 'state'));
    }

    private function commandPublish(string $userId, string $chatId, string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest), 2) ?: [];
        if (($parts[0] ?? '') !== 'feed') {
            throw new InvalidArgumentException('برای feed از /publish feed ID استفاده کن؛ برای video/story از /state استفاده کن.');
        }

        return $this->queueTool($userId, $chatId, 'publish_feed', ['id' => $this->positiveInt($parts[1] ?? null)]);
    }

    private function commandUnpublish(string $userId, string $chatId, string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest), 2) ?: [];
        if (($parts[0] ?? '') !== 'feed') {
            throw new InvalidArgumentException('نمونه: /unpublish feed 12');
        }

        return $this->queueTool($userId, $chatId, 'unpublish_feed', ['id' => $this->positiveInt($parts[1] ?? null)]);
    }

    private function commandDelete(string $userId, string $chatId, string $rest): array
    {
        [$resourceRaw, $idRaw] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, null);
        $resource = $this->resource((string) $resourceRaw);
        $id = $this->positiveInt($idRaw);

        return $this->queueTool($userId, $chatId, 'delete_content', compact('resource', 'id'));
    }

    private function commandRestore(string $userId, string $chatId, string $rest): array
    {
        [$resourceRaw, $idRaw] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, null);
        $resource = $this->resource((string) $resourceRaw);
        if (! in_array($resource, ['game', 'studio'], true)) {
            throw new InvalidArgumentException('فقط game و studio قابل restore هستند.');
        }
        $id = $this->positiveInt($idRaw);

        return $this->queueTool($userId, $chatId, 'restore_content', compact('resource', 'id'));
    }

    private function commandSyncCollection(string $userId, string $chatId, string $rest): array
    {
        [$collectionRaw, $idsRaw] = array_pad(preg_split('/\s+/', trim($rest), 2) ?: [], 2, '');
        $collectionId = $this->positiveInt($collectionRaw);
        $videoIds = array_values(array_filter(array_map(
            fn ($value) => filter_var(trim($value), FILTER_VALIDATE_INT) ?: null,
            explode(',', $idsRaw),
        )));
        if ($videoIds === []) {
            throw new InvalidArgumentException('حداقل یک video id بده. نمونه: /sync_collection 4 10,11,12');
        }

        return $this->queueTool($userId, $chatId, 'sync_collection_videos', [
            'collection_id' => $collectionId,
            'video_ids' => $videoIds,
        ]);
    }

    private function commandMedia(string $userId, string $chatId, string $rest): array
    {
        $parts = preg_split('/\s+/', trim($rest), 3) ?: [];
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('نمونه: /media video 12 video');
        }

        $resource = $this->resource($parts[0]);
        $id = $this->positiveInt($parts[1]);
        $slot = $parts[2];

        $this->sessions->put($userId, $chatId, 'awaiting_media', compact('resource', 'id', 'slot'));
        $this->send($chatId, "حالا فایل را بفرست. برای لغو <code>/cancel</code>.");

        return ['action' => 'awaiting_media', 'resource' => $resource, 'resource_id' => $id];
    }

    private function handleAwaitingJson(string $userId, string $chatId, array $context, string $text): array
    {
        $tool = (string) ($context['tool'] ?? '');
        $payload = $this->decodeJson($text);

        if (($context['mode'] ?? '') === 'update') {
            $payload = [
                'resource' => (string) $context['resource'],
                'id' => (int) $context['id'],
                'data' => $payload,
            ];
        }

        return $this->handleTool($userId, $chatId, $tool, $payload);
    }

    private function handleMedia(string $userId, string $chatId, array $message, array $telegramFile): array
    {
        $session = $this->sessions->get($userId, $chatId);
        if (! $session || $session->state !== 'awaiting_media') {
            $this->send(
                $chatId,
                "فایل دریافت شد اما مقصد مشخص نیست. اول مثلاً <code>/media video 12 video</code> بزن یا از منوی رکورد، «افزودن مدیا» را انتخاب کن.",
            );

            return ['action' => 'media_without_target'];
        }

        $context = is_array($session->context) ? $session->context : [];
        $resource = $this->resource((string) ($context['resource'] ?? ''));
        $id = $this->positiveInt($context['id'] ?? null);
        $slot = trim((string) ($context['slot'] ?? ''));
        if ($slot === '') {
            throw new RuntimeException('Media slot is missing from the Telegram session.');
        }

        $this->telegram->sendChatAction($chatId, 'typing');
        $result = $this->mediaTransfer->attach(
            $telegramFile,
            $resource,
            $id,
            $slot,
            filled($message['caption'] ?? null) ? (string) $message['caption'] : null,
        );
        $this->sessions->clear($userId, $chatId);
        $this->send($chatId, "✅ مدیا به PlayNexus منتقل شد.\n\n".$this->formatter->result('نتیجه آپلود', $result), $this->menuKeyboard());

        return ['action' => 'media_uploaded', 'resource' => $resource, 'resource_id' => $id];
    }

    private function executeReadTool(string $chatId, string $tool, array $arguments): array
    {
        $result = $this->executor->execute($tool, $arguments);
        $this->send($chatId, $this->formatter->result($tool, $result), $this->menuKeyboard());

        return [
            'action' => 'tool:'.$tool,
            'resource' => $arguments['resource'] ?? null,
            'resource_id' => $arguments['id'] ?? null,
        ];
    }

    private function handleTool(string $userId, string $chatId, string $tool, array $arguments): array
    {
        $definition = $this->executor->authorize($tool);

        if ($definition['confirm'] ?? false) {
            return $this->queueTool($userId, $chatId, $tool, $arguments);
        }

        return $this->executeReadTool($chatId, $tool, $arguments);
    }

    private function queueTool(string $userId, string $chatId, string $tool, array $arguments): array
    {
        $this->executor->authorize($tool);
        $json = json_encode($arguments, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
        $summary = $tool."\n".Str::limit($json, 1800, "\n…");
        $token = $this->sessions->queueConfirmation($userId, $chatId, $tool, $arguments, $summary);

        $this->send(
            $chatId,
            "<b>تأیید عملیات</b>\n<code>".$this->formatter->escape($tool)."</code>\n<pre>".$this->formatter->escape(Str::limit($json, 2500, "\n…"))."</pre>",
            [
                'inline_keyboard' => [[
                    ['text' => '✅ تأیید و اجرا', 'callback_data' => 'confirm:'.$token],
                    ['text' => '❌ لغو', 'callback_data' => 'cancel:'.$token],
                ]],
            ],
        );

        return [
            'action' => 'confirmation_queued:'.$tool,
            'resource' => $arguments['resource'] ?? null,
            'resource_id' => $arguments['id'] ?? $arguments['collection_id'] ?? null,
        ];
    }

    private function sendResourceList(string $chatId, string $resource, string $query = ''): array
    {
        $arguments = [
            'resource' => $resource,
            'limit' => 10,
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];
        if (trim($query) !== '') {
            $arguments['query'] = trim($query);
        }

        $result = $this->executor->execute('select_content', $arguments);
        $this->send(
            $chatId,
            $this->formatter->result($query !== '' ? "نتیجه جستجو: {$query}" : "آخرین {$resource}", $result),
            $this->resourceListKeyboard($resource, $result),
        );

        return ['action' => $query !== '' ? 'search' : 'list', 'resource' => $resource];
    }

    private function showResource(string $chatId, string $resource, int $id): array
    {
        $result = $this->executor->execute('get_content', compact('resource', 'id'));
        $this->send($chatId, $this->formatter->itemDetails($result), $this->resourceKeyboard($resource, $id, $result));

        return ['action' => 'view', 'resource' => $resource, 'resource_id' => $id];
    }

    private function showMediaSlots(string $chatId, string $resource, int $id): array
    {
        $slots = match ($resource) {
            'game' => ['cover', 'background', 'attachment'],
            'studio' => ['logo', 'background', 'attachment'],
            'platform' => ['icon', 'attachment'],
            'collection' => ['logo', 'attachment'],
            'feed' => ['media', 'attachment'],
            'story' => ['media', 'thumbnail', 'attachment'],
            'video' => ['video', 'thumbnail', 'attachment'],
            'product' => ['media', 'attachment'],
            default => [],
        };

        $keyboard = ['inline_keyboard' => []];
        foreach (array_chunk($slots, 2) as $row) {
            $keyboard['inline_keyboard'][] = array_map(
                fn ($slot) => [
                    'text' => '📎 '.$slot,
                    'callback_data' => "await-media:{$resource}:{$id}:{$slot}",
                ],
                $row,
            );
        }
        $keyboard['inline_keyboard'][] = [[
            'text' => '↩️ بازگشت',
            'callback_data' => "view:{$resource}:{$id}",
        ]];

        $this->send($chatId, '<b>Slot مدیا را انتخاب کن:</b>', $keyboard);

        return ['action' => 'media_slots', 'resource' => $resource, 'resource_id' => $id];
    }

    private function promptJson(string $userId, string $chatId, string $resource, string $mode, ?int $id = null): array
    {
        $tool = $mode === 'update' ? 'update_content' : $this->createTool($resource);
        $this->executor->authorize($tool);

        $this->sessions->put($userId, $chatId, 'awaiting_tool_json', [
            'tool' => $tool,
            'mode' => $mode,
            'resource' => $resource,
            'id' => $id,
        ]);

        $template = $this->template($resource, $mode);
        $this->send(
            $chatId,
            "<b>".($mode === 'update' ? 'ویرایش' : 'ساخت')." {$resource}</b>\n"
            ."JSON را در پیام بعدی بفرست.\n<pre>".$this->formatter->escape($template)."</pre>\n"
            ."لغو: <code>/cancel</code>",
        );

        return ['action' => 'awaiting_json', 'resource' => $resource, 'resource_id' => $id];
    }

    private function cancel(string $userId, string $chatId): array
    {
        $this->sessions->clear($userId, $chatId);
        $this->send($chatId, 'عملیات جاری لغو شد.', $this->menuKeyboard());

        return ['action' => 'cancel'];
    }

    private function menuKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🎮 بازی‌ها', 'callback_data' => 'menu:game'],
                    ['text' => '🏭 استودیوها', 'callback_data' => 'menu:studio'],
                ],
                [
                    ['text' => '📰 فید', 'callback_data' => 'menu:feed'],
                    ['text' => '🎬 ویدیو', 'callback_data' => 'menu:video'],
                ],
                [
                    ['text' => '📱 استوری', 'callback_data' => 'menu:story'],
                    ['text' => '📚 کالکشن', 'callback_data' => 'menu:collection'],
                ],
                [
                    ['text' => '🛍 محصولات', 'callback_data' => 'menu:product'],
                    ['text' => '🎛 پلتفرم‌ها', 'callback_data' => 'menu:platform'],
                ],
                [
                    ['text' => '📊 وضعیت', 'callback_data' => 'menu:status'],
                    ['text' => '❓ راهنما', 'callback_data' => 'menu:help'],
                ],
                [[
                    'text' => '⚙️ پنل تنظیمات بات',
                    'url' => route('admin.telegram-bot.index'),
                ]],
            ],
        ];
    }

    private function resourceListKeyboard(string $resource, array $result): array
    {
        $rows = [];
        foreach (array_slice((array) ($result['items'] ?? []), 0, 8) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $label = Str::limit((string) ($item['title'] ?? $item['name'] ?? $item['slug'] ?? '#'.$item['id']), 30);
            $rows[] = [[
                'text' => '#'.$item['id'].' '.$label,
                'callback_data' => "view:{$resource}:{$item['id']}",
            ]];
        }

        if (in_array($resource, ['game', 'studio', 'collection', 'feed', 'story', 'video'], true)) {
            $rows[] = [[
                'text' => '➕ ساخت جدید',
                'callback_data' => "template:{$resource}:create",
            ]];
        }

        $rows[] = [[
            'text' => '🏠 منوی اصلی',
            'callback_data' => 'menu:home',
        ]];

        return ['inline_keyboard' => $rows];
    }

    private function resourceKeyboard(string $resource, int $id, array $item): array
    {
        $rows = [
            [
                ['text' => '📎 مدیا', 'callback_data' => "media-slots:{$resource}:{$id}"],
                ['text' => '🗂 Assets', 'callback_data' => "assets:{$resource}:{$id}"],
            ],
        ];

        if (in_array($resource, ['game', 'studio', 'collection', 'feed', 'story', 'video'], true)) {
            $rows[] = [[
                'text' => '✏️ ویرایش JSON',
                'callback_data' => "template:{$resource}:update:{$id}",
            ]];
        }

        $state = (string) ($item['status'] ?? $item['visibility'] ?? '');
        if ($resource === 'feed') {
            $rows[] = [[
                'text' => $state === 'published' ? '📥 Draft' : '🚀 Publish',
                'callback_data' => ($state === 'published' ? 'unpublish-feed:' : 'publish-feed:').$id,
            ]];
        } elseif (in_array($resource, ['game', 'studio'], true)) {
            $target = $state === 'active' ? 'inactive' : 'active';
            $rows[] = [[
                'text' => $target === 'active' ? '✅ فعال‌سازی' : '⏸ غیرفعال',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        } elseif ($resource === 'collection') {
            $target = $state === 'public' ? 'private' : 'public';
            $rows[] = [[
                'text' => $target === 'public' ? '🌐 عمومی' : '🔒 خصوصی',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        } elseif (in_array($resource, ['story', 'video'], true)) {
            $target = $state === 'published' ? 'draft' : 'published';
            $rows[] = [[
                'text' => $target === 'published' ? '🚀 انتشار' : '📥 Draft',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        }

        if (in_array($resource, ['game', 'studio', 'collection', 'feed', 'story', 'video'], true)) {
            $rows[] = [[
                'text' => '🗑 حذف',
                'callback_data' => "delete:{$resource}:{$id}",
            ]];
        }

        $rows[] = [[
            'text' => '↩️ فهرست',
            'callback_data' => "menu:{$resource}",
        ]];

        return ['inline_keyboard' => $rows];
    }

    private function extractTelegramFile(array $message): ?array
    {
        if (is_array($message['photo'] ?? null) && $message['photo'] !== []) {
            $photo = end($message['photo']);
            if (is_array($photo) && filled($photo['file_id'] ?? null)) {
                return [
                    'file_id' => (string) $photo['file_id'],
                    'file_size' => isset($photo['file_size']) ? (int) $photo['file_size'] : null,
                    'file_name' => 'telegram-photo-'.$message['message_id'].'.jpg',
                    'mime' => 'image/jpeg',
                ];
            }
        }

        foreach (['video', 'document', 'animation', 'video_note'] as $type) {
            $file = is_array($message[$type] ?? null) ? $message[$type] : null;
            if (! $file || blank($file['file_id'] ?? null)) {
                continue;
            }

            return [
                'file_id' => (string) $file['file_id'],
                'file_size' => isset($file['file_size']) ? (int) $file['file_size'] : null,
                'file_name' => (string) ($file['file_name'] ?? "telegram-{$type}-{$message['message_id']}".($type === 'video_note' ? '.mp4' : '')),
                'mime' => (string) ($file['mime_type'] ?? ($type === 'video_note' ? 'video/mp4' : 'application/octet-stream')),
                'duration' => isset($file['duration']) ? (int) $file['duration'] : null,
            ];
        }

        return null;
    }

    private function createTool(string $resource): string
    {
        return match ($resource) {
            'game' => 'create_game',
            'studio' => 'create_studio',
            'collection' => 'create_collection',
            'feed' => 'create_feed',
            'story' => 'create_story',
            'video' => 'create_video',
            default => throw new InvalidArgumentException("ساخت {$resource} از Content Agent پشتیبانی نمی‌شود."),
        };
    }

    private function template(string $resource, string $mode): string
    {
        $templates = [
            'game' => ['name' => 'نام بازی', 'studio_id' => null, 'developer' => null, 'publisher' => null, 'release_date' => null, 'description' => null, 'platform_ids' => []],
            'studio' => ['name' => 'نام استودیو', 'description' => null, 'website' => null],
            'collection' => ['title' => 'عنوان کالکشن', 'game_id' => null, 'studio_id' => null, 'description' => null],
            'feed' => ['title' => 'عنوان فید', 'body' => '<p>متن فید</p>', 'feed_type' => 'post', 'feed_badge' => 'news', 'game_id' => null, 'notify_followers' => false],
            'story' => ['title' => 'عنوان استوری', 'excerpt' => null, 'game_id' => null, 'link_url' => null, 'link_label' => null],
            'video' => ['title' => 'عنوان ویدیو', 'game_id' => null, 'playlist_ids' => [], 'excerpt' => null, 'body' => null, 'featured' => false, 'allow_comments' => true],
        ];

        $payload = $templates[$resource] ?? [];
        if ($mode === 'update') {
            $payload = array_slice($payload, 0, max(1, min(4, count($payload))), true);
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
    }

    private function resource(string $value): string
    {
        $resource = self::RESOURCE_ALIASES[strtolower(trim($value))] ?? null;
        if (! $resource) {
            throw new InvalidArgumentException('Resource نامعتبر است. game/studio/platform/collection/feed/story/video/product');
        }

        return $resource;
    }

    private function positiveInt(mixed $value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new InvalidArgumentException('ID باید عدد مثبت باشد.');
        }

        return (int) $id;
    }

    private function decodeJson(string $json): array
    {
        try {
            $decoded = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidArgumentException('JSON معتبر نیست: '.$exception->getMessage(), 0, $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new InvalidArgumentException('JSON باید یک object باشد.');
        }

        return $decoded;
    }

    private function splitCommand(string $text): array
    {
        $parts = preg_split('/\s+/', trim($text), 2) ?: [];
        $command = strtolower((string) ($parts[0] ?? ''));
        $command = preg_replace('/@[^\s]+$/', '', $command) ?: $command;

        return [$command, trim((string) ($parts[1] ?? ''))];
    }

    private function send(string $chatId, string $text, ?array $keyboard = null): void
    {
        $this->telegram->sendMessage($chatId, $text, $keyboard);
    }

    private function sendAndReturn(string $chatId, string $text, string $action): array
    {
        $this->send($chatId, $text, $this->menuKeyboard());

        return ['action' => $action];
    }
}
