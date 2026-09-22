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

    private const RESOURCE_LABELS = [
        'game' => ['label' => 'بازی‌ها', 'icon' => '🎮', 'hub' => 'library'],
        'studio' => ['label' => 'استودیوها', 'icon' => '🏭', 'hub' => 'library'],
        'platform' => ['label' => 'پلتفرم‌ها', 'icon' => '🎛', 'hub' => 'library'],
        'collection' => ['label' => 'کالکشن‌ها', 'icon' => '📚', 'hub' => 'content'],
        'feed' => ['label' => 'فید', 'icon' => '📰', 'hub' => 'content'],
        'story' => ['label' => 'استوری', 'icon' => '📱', 'hub' => 'content'],
        'video' => ['label' => 'ویدیوها', 'icon' => '🎬', 'hub' => 'content'],
        'product' => ['label' => 'محصولات', 'icon' => '🛍', 'hub' => 'commerce'],
    ];

    private const MUTABLE_RESOURCES = ['game', 'studio', 'collection', 'feed', 'story', 'video'];

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
        try {
            return $this->dispatch($update);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $chatId = $this->chatIdFromUpdate($update);
            if ($chatId !== null) {
                $this->send(
                    $chatId,
                    "⚠️ <b>این عملیات اجرا نشد</b>\n".$this->formatter->escape($this->friendlyError($exception->getMessage())),
                    $this->menuKeyboard(),
                );
            }

            return ['action' => 'user_error'];
        }
    }

    private function dispatch(array $update): array
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

        if (! str_starts_with($text, '/') && $session?->state === 'awaiting_search') {
            $context = is_array($session->context) ? $session->context : [];
            $resource = $this->resource((string) ($context['resource'] ?? ''));
            $this->sessions->clear($userId, $chatId);

            return $this->sendResourceList($chatId, $resource, $text);
        }

        if (! str_starts_with($text, '/') && $session?->state === 'awaiting_graph_query') {
            $this->sessions->clear($userId, $chatId);

            return $this->executeReadTool($chatId, 'query_playnexus_graph', ['query' => $text]);
        }

        if (! str_starts_with($text, '/') && $session?->state === 'awaiting_restore_id') {
            $context = is_array($session->context) ? $session->context : [];
            $resource = $this->resource((string) ($context['resource'] ?? ''));
            $id = $this->positiveInt(trim($text));
            $this->sessions->clear($userId, $chatId);

            return $this->queueTool($userId, $chatId, 'restore_content', compact('resource', 'id'));
        }

        if (! str_starts_with($text, '/') && $session?->state === 'awaiting_sync_collection') {
            $context = is_array($session->context) ? $session->context : [];
            $collectionId = $this->positiveInt($context['collection_id'] ?? null);
            $videoIds = array_values(array_filter(array_map(
                fn ($value) => filter_var(trim($value), FILTER_VALIDATE_INT) ?: null,
                explode(',', $text),
            )));
            if ($videoIds === []) {
                throw new InvalidArgumentException('حداقل یک Video ID بده؛ مثال: 10,11,12');
            }
            $this->sessions->clear($userId, $chatId);

            return $this->queueTool($userId, $chatId, 'sync_collection_videos', [
                'collection_id' => $collectionId,
                'video_ids' => $videoIds,
            ]);
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
        $messageId = (int) ($callback['message']['message_id'] ?? 0);
        $userId = (string) ($callback['from']['id'] ?? '');

        if ($callbackId !== '') {
            $this->telegram->answerCallbackQuery($callbackId);
        }

        if ($chatId === '' || $userId === '') {
            return ['action' => 'callback_without_actor'];
        }

        $parts = explode(':', $data);
        $action = $parts[0] ?? '';

        if (! in_array($action, ['confirm', 'cancel'], true)) {
            $this->sessions->clear($userId, $chatId);
        }

        if ($action === 'menu') {
            $target = $parts[1] ?? 'home';

            if ($target === 'home') {
                return $this->showMenu($chatId, $messageId);
            }
            if ($target === 'status') {
                return $this->showStatus($chatId, $messageId);
            }
            if ($target === 'help') {
                return $this->sendAndReturn($chatId, $this->formatter->help(), 'help');
            }
            if ($target === 'advanced') {
                return $this->sendAndReturn($chatId, $this->formatter->advancedHelp(), 'advanced_help');
            }
            if ($target === 'graph-schema') {
                return $this->executeReadTool($chatId, 'describe_playnexus_graph', []);
            }
            if (in_array($target, ['content', 'library', 'commerce', 'intelligence', 'system'], true)) {
                return $this->showHub($chatId, $target, $messageId);
            }

            return $this->showResourceHub($chatId, $this->resource($target), $messageId);
        }

        if ($action === 'list') {
            $resource = $this->resource($parts[1] ?? '');
            $offset = max(0, (int) ($parts[2] ?? 0));

            return $this->sendResourceList($chatId, $resource, '', $offset, $messageId);
        }

        if ($action === 'search') {
            $resource = $this->resource($parts[1] ?? '');
            $this->sessions->put($userId, $chatId, 'awaiting_search', compact('resource'));
            $meta = $this->resourceMeta($resource);
            $this->send(
                $chatId,
                "🔎 <b>جستجو در {$this->formatter->escape($meta['label'])}</b>\nعبارت جستجو را در پیام بعدی بفرست.",
                $this->cancelKeyboard("menu:{$resource}"),
            );

            return ['action' => 'awaiting_search', 'resource' => $resource];
        }

        if ($action === 'graph-prompt') {
            $this->sessions->put($userId, $chatId, 'awaiting_graph_query');
            $this->send(
                $chatId,
                "🧠 <b>GraphQL Query</b>\nQuery را در پیام بعدی بفرست. این مسیر فقط‌خواندنی است.",
                $this->cancelKeyboard('menu:intelligence'),
            );

            return ['action' => 'awaiting_graph_query'];
        }

        if ($action === 'events') {
            $offset = max(0, (int) ($parts[1] ?? 0));

            return $this->showEvents($chatId, $offset, $messageId);
        }

        if ($action === 'event-new') {
            $this->executor->authorize('upsert_game_event');
            $this->sessions->put($userId, $chatId, 'awaiting_tool_json', [
                'tool' => 'upsert_game_event',
                'mode' => 'create',
            ]);
            $template = json_encode([
                'game_id' => null,
                'type' => 'update',
                'title' => 'عنوان رویداد',
                'summary' => null,
                'source_type' => 'manual',
                'source_name' => 'Telegram Admin',
                'source_url' => null,
                'importance_score' => 50,
                'confidence' => 1,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) ?: '{}';
            $this->send(
                $chatId,
                "➕ <b>Game Event جدید</b>\nJSON را در پیام بعدی بفرست:\n<pre>".$this->formatter->escape($template)."</pre>",
                $this->cancelKeyboard('menu:intelligence'),
            );

            return ['action' => 'awaiting_event_json'];
        }

        if ($action === 'event-actions') {
            $id = $this->positiveInt($parts[1] ?? null);

            return $this->showEventActions($chatId, $id, $messageId);
        }

        if ($action === 'event-state') {
            $id = $this->positiveInt($parts[1] ?? null);
            $state = (string) ($parts[2] ?? '');

            return $this->queueTool($userId, $chatId, 'set_game_event_state', compact('id', 'state'));
        }

        if ($action === 'restore-prompt') {
            $resource = $this->resource($parts[1] ?? '');
            if (! in_array($resource, ['game', 'studio'], true)) {
                throw new InvalidArgumentException('Restore فقط برای game و studio فعال است.');
            }
            $this->executor->authorize('restore_content');
            $this->sessions->put($userId, $chatId, 'awaiting_restore_id', compact('resource'));
            $this->send(
                $chatId,
                "♻️ <b>Restore {$this->formatter->escape($this->resourceMeta($resource)['label'])}</b>\nID رکورد حذف‌شده را بفرست.",
                $this->cancelKeyboard("menu:{$resource}"),
            );

            return ['action' => 'awaiting_restore_id', 'resource' => $resource];
        }

        if ($action === 'sync-prompt') {
            $collectionId = $this->positiveInt($parts[1] ?? null);
            $this->executor->authorize('sync_collection_videos');
            $this->sessions->put($userId, $chatId, 'awaiting_sync_collection', [
                'collection_id' => $collectionId,
            ]);
            $this->send(
                $chatId,
                "🔗 <b>Sync Collection #{$collectionId}</b>\nVideo IDها را با کاما بفرست؛ مثال: <code>10,11,12</code>",
                $this->cancelKeyboard("view:collection:{$collectionId}"),
            );

            return ['action' => 'awaiting_sync_collection', 'resource' => 'collection', 'resource_id' => $collectionId];
        }

        if ($action === 'view') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);

            return $this->showResource($chatId, $resource, $id, $messageId);
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

            return $this->showMediaSlots($chatId, $resource, $id, $messageId);
        }

        if ($action === 'await-media') {
            $resource = $this->resource($parts[1] ?? '');
            $id = $this->positiveInt($parts[2] ?? null);
            $slot = (string) ($parts[3] ?? '');
            $this->sessions->put($userId, $chatId, 'awaiting_media', compact('resource', 'id', 'slot'));
            $this->send(
                $chatId,
                "📤 <b>ارسال مدیا</b>\n"
                ."Resource: <b>".$this->formatter->escape($this->resourceMeta($resource)['label'])."</b>\n"
                ."ID: <b>{$id}</b>\nSlot: <b>".$this->formatter->escape($slot)."</b>\n\n"
                ."فایل را همین حالا بفرست. برای لغو از دکمه زیر استفاده کن.",
                $this->cancelKeyboard("view:{$resource}:{$id}"),
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
                $this->send($chatId, '⏳ این تأیید منقضی شده یا متعلق به عملیات دیگری است.', $this->menuKeyboard());

                return ['action' => 'confirmation_expired'];
            }

            $tool = (string) ($pending['tool'] ?? '');
            $arguments = is_array($pending['arguments'] ?? null) ? $pending['arguments'] : [];
            $result = $this->executor->execute($tool, $arguments);
            $resource = isset($arguments['resource']) ? (string) $arguments['resource'] : null;
            $resourceId = $arguments['id'] ?? $arguments['collection_id'] ?? null;
            $keyboard = $resource && $resourceId
                ? $this->resourceBackKeyboard($resource, (int) $resourceId)
                : $this->menuKeyboard();

            $this->send($chatId, "✅ <b>عملیات انجام شد</b>\n\n".$this->formatter->result($tool, $result), $keyboard);

            return [
                'action' => 'tool_confirmed:'.$tool,
                'resource' => $resource,
                'resource_id' => $resourceId,
            ];
        }

        if ($action === 'cancel') {
            $this->sessions->clear($userId, $chatId);
            $back = (string) ($parts[1] ?? 'menu-home');
            $this->send($chatId, '✕ عملیات لغو شد.', $this->menuKeyboard());

            return ['action' => 'callback_cancel', 'back' => $back];
        }

        $this->send($chatId, 'این دکمه دیگر معتبر نیست. <code>/menu</code> را باز کن.', $this->menuKeyboard());

        return ['action' => 'unknown_callback'];
    }

    private function showMenu(string $chatId, ?int $messageId = null): array
    {
        $this->render($chatId, $this->formatter->menuText(), $this->menuKeyboard(), $messageId);

        return ['action' => 'menu'];
    }

    private function showHub(string $chatId, string $hub, ?int $messageId = null): array
    {
        $this->render($chatId, $this->formatter->hubText($hub), $this->hubKeyboard($hub), $messageId);

        return ['action' => 'hub:'.$hub];
    }

    private function showResourceHub(string $chatId, string $resource, ?int $messageId = null): array
    {
        $meta = $this->resourceMeta($resource);
        $this->render(
            $chatId,
            $this->formatter->resourceHub($meta['label'], $meta['icon']),
            $this->resourceHubKeyboard($resource),
            $messageId,
        );

        return ['action' => 'resource_hub', 'resource' => $resource];
    }

    private function showStatus(string $chatId, ?int $messageId = null): array
    {
        $settings = $this->settings->resolved();
        $webhook = [];
        $webhookError = null;

        try {
            $webhook = $this->telegram->getWebhookInfo();
        } catch (\Throwable $exception) {
            $webhookError = $exception->getMessage();
        }

        $transport = $this->telegram->lastTransport()
            ?: (string) ($settings['transport_mode'] ?? 'auto');

        $text = "📡 <b>PlayNexus Bot Status</b>\n"
            ."━━━━━━━━━━━━━━━━━━\n"
            ."Bot: <b>@".$this->formatter->escape((string) ($settings['bot_username'] ?? 'نامشخص'))."</b>\n"
            ."Runtime: <b>".(($settings['enabled'] ?? false) ? 'ONLINE ✅' : 'OFFLINE ⛔')."</b>\n"
            ."Transport: <b>".$this->formatter->escape($transport)."</b>\n"
            ."Relay: <b>".((filled($settings['relay_base_url'] ?? null)) ? 'configured' : '—')."</b>\n"
            ."SOCKS/Proxy: <b>".(($settings['use_proxy'] ?? false) ? 'configured' : '—')."</b>\n\n"
            ."✍️ Write: <b>".(($settings['write_enabled'] ?? false) ? 'ON' : 'OFF')."</b>\n"
            ."🚀 Publish: <b>".(($settings['publish_enabled'] ?? false) ? 'ON' : 'OFF')."</b>\n"
            ."🗑 Destructive: <b>".(($settings['destructive_enabled'] ?? false) ? 'ON' : 'OFF')."</b>\n"
            ."🖼 Media: <b>".(($settings['media_enabled'] ?? false) ? 'ON' : 'OFF')."</b>";

        if ($webhookError) {
            $text .= "\n\nWebhook: ❌ ".$this->formatter->escape($webhookError);
        } else {
            $text .= "\n\nWebhook pending: <b>".(int) ($webhook['pending_update_count'] ?? 0)."</b>";
            if (filled($webhook['last_error_message'] ?? null)) {
                $text .= "\nآخرین خطا: ".$this->formatter->escape((string) $webhook['last_error_message']);
            } else {
                $text .= "\nWebhook: <b>Healthy ✅</b>";
            }
        }

        $this->render($chatId, $text, [
            'inline_keyboard' => [
                [
                    ['text' => '🔄 Refresh', 'callback_data' => 'menu:status'],
                    ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
                ],
                [[
                    'text' => '⚙️ Bot Settings',
                    'url' => route('admin.telegram-bot.index'),
                ]],
            ],
        ], $messageId);

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

        if ($status === '') {
            return $this->sendResourceList($chatId, $resource);
        }

        $arguments = [
            'resource' => $resource,
            'limit' => 8,
            'status' => $status,
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];
        $result = $this->executor->execute('select_content', $arguments);
        $meta = $this->resourceMeta($resource);
        $this->send(
            $chatId,
            $this->formatter->result($meta['icon'].' '.$meta['label'].' • '.$status, $result),
            $this->resourceListKeyboard($resource, $result),
        );

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
        if (trim($rest) === '') {
            return $this->showEvents($chatId);
        }

        return $this->executeReadTool($chatId, 'list_game_events', [
            'limit' => 15,
            'game_id' => $this->positiveInt(trim($rest)),
        ]);
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
        $this->send(
            $chatId,
            "✅ <b>مدیا منتقل شد</b>\n\n".$this->formatter->result('نتیجه آپلود', $result),
            $this->resourceBackKeyboard($resource, $id),
        );

        return ['action' => 'media_uploaded', 'resource' => $resource, 'resource_id' => $id];
    }

    private function executeReadTool(string $chatId, string $tool, array $arguments): array
    {
        $result = $this->executor->execute($tool, $arguments);
        $keyboard = isset($arguments['resource'], $arguments['id'])
            ? $this->resourceBackKeyboard((string) $arguments['resource'], (int) $arguments['id'])
            : $this->menuKeyboard();

        $this->send($chatId, $this->formatter->result($tool, $result), $keyboard);

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
            "⚠️ <b>تأیید نهایی</b>\n"
            ."این عملیات داده را تغییر می‌دهد. جزئیات را بررسی کن:\n"
            ."<code>".$this->formatter->escape($tool)."</code>\n"
            ."<pre>".$this->formatter->escape(Str::limit($json, 2400, "\n…"))."</pre>",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '✅ تأیید و اجرا', 'callback_data' => 'confirm:'.$token],
                        ['text' => '✕ لغو', 'callback_data' => 'cancel'],
                    ],
                    [[
                        'text' => '🏠 Home',
                        'callback_data' => 'menu:home',
                    ]],
                ],
            ],
        );

        return [
            'action' => 'confirmation_queued:'.$tool,
            'resource' => $arguments['resource'] ?? null,
            'resource_id' => $arguments['id'] ?? $arguments['collection_id'] ?? null,
        ];
    }

    private function sendResourceList(string $chatId, string $resource, string $query = '', int $offset = 0, ?int $messageId = null): array
    {
        $limit = 8;
        $arguments = [
            'resource' => $resource,
            'limit' => $limit,
            'offset' => max(0, $offset),
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];
        if (trim($query) !== '') {
            $arguments['query'] = trim($query);
        }

        $result = $this->executor->execute('select_content', $arguments);
        $meta = $this->resourceMeta($resource);
        $title = trim($query) !== ''
            ? $meta['icon'].' جستجو: '.Str::limit($query, 40)
            : $meta['icon'].' '.$meta['label'];

        $this->render(
            $chatId,
            $this->formatter->result($title, $result),
            $this->resourceListKeyboard($resource, $result, trim($query) === ''),
            $messageId,
        );

        return [
            'action' => trim($query) !== '' ? 'search' : 'list',
            'resource' => $resource,
            'offset' => max(0, $offset),
        ];
    }

    private function showResource(string $chatId, string $resource, int $id, ?int $messageId = null): array
    {
        $result = $this->executor->execute('get_content', compact('resource', 'id'));
        $meta = $this->resourceMeta($resource);
        $this->render(
            $chatId,
            $meta['icon']." <b>".$this->formatter->escape($meta['label'])."</b>\n━━━━━━━━━━━━━━━━━━\n".$this->formatter->itemDetails($result),
            $this->resourceKeyboard($resource, $id, $result),
            $messageId,
        );

        return ['action' => 'view', 'resource' => $resource, 'resource_id' => $id];
    }

    private function showEvents(string $chatId, int $offset = 0, ?int $messageId = null): array
    {
        $result = $this->executor->execute('list_game_events', [
            'limit' => 8,
            'offset' => max(0, $offset),
        ]);

        $this->render(
            $chatId,
            $this->formatter->result('🧩 Game Events', $result),
            $this->eventListKeyboard($result),
            $messageId,
        );

        return ['action' => 'events', 'offset' => max(0, $offset)];
    }

    private function showEventActions(string $chatId, int $id, ?int $messageId = null): array
    {
        $this->render(
            $chatId,
            "🧩 <b>Game Event #{$id}</b>\nوضعیت جدید را انتخاب کن. این تغییر قبل از اجرا تأیید نهایی می‌خواهد.",
            [
                'inline_keyboard' => [
                    [
                        ['text' => '🟢 Active', 'callback_data' => "event-state:{$id}:active"],
                        ['text' => '🟡 Candidate', 'callback_data' => "event-state:{$id}:candidate"],
                    ],
                    [[
                        'text' => '🚫 Dismiss',
                        'callback_data' => "event-state:{$id}:dismissed",
                    ]],
                    [
                        ['text' => '↩️ Events', 'callback_data' => 'events:0'],
                        ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
                    ],
                ],
            ],
            $messageId,
        );

        return ['action' => 'event_actions', 'resource_id' => $id];
    }

    private function showMediaSlots(string $chatId, string $resource, int $id, ?int $messageId = null): array
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

        $labels = [
            'cover' => '🖼 کاور',
            'background' => '🌌 بک‌گراند',
            'attachment' => '📎 فایل',
            'logo' => '🔷 لوگو',
            'icon' => '🔹 آیکن',
            'media' => '🎞 مدیا',
            'thumbnail' => '🖼 Thumbnail',
            'video' => '🎬 ویدیو',
        ];

        $keyboard = ['inline_keyboard' => []];
        foreach (array_chunk($slots, 2) as $row) {
            $keyboard['inline_keyboard'][] = array_map(
                fn ($slot) => [
                    'text' => $labels[$slot] ?? ('📎 '.$slot),
                    'callback_data' => "await-media:{$resource}:{$id}:{$slot}",
                ],
                $row,
            );
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '↩️ رکورد', 'callback_data' => "view:{$resource}:{$id}"],
            ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
        ];

        $this->render(
            $chatId,
            "🖼 <b>Media Manager</b>\nنوع مدیا را برای #{$id} انتخاب کن.",
            $keyboard,
            $messageId,
        );

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
        $meta = $this->resourceMeta($resource);
        $back = $id ? "view:{$resource}:{$id}" : "menu:{$resource}";
        $this->send(
            $chatId,
            ($mode === 'update' ? '✏️' : '➕')." <b>".($mode === 'update' ? 'ویرایش ' : 'ساخت ')
            .$this->formatter->escape($meta['label'])."</b>\n"
            ."JSON را در پیام بعدی بفرست. قبل از Write تأیید نهایی نمایش داده می‌شود.\n"
            ."<pre>".$this->formatter->escape($template)."</pre>",
            $this->cancelKeyboard($back),
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
                    ['text' => '✨ Content Studio', 'callback_data' => 'menu:content'],
                    ['text' => '🎮 Game Library', 'callback_data' => 'menu:library'],
                ],
                [
                    ['text' => '🛍 Commerce', 'callback_data' => 'menu:commerce'],
                    ['text' => '🧠 Intelligence', 'callback_data' => 'menu:intelligence'],
                ],
                [
                    ['text' => '📡 Status', 'callback_data' => 'menu:status'],
                    ['text' => '⚙️ System', 'callback_data' => 'menu:system'],
                ],
                [[
                    'text' => '🪟 PlayNexus Admin Panel',
                    'url' => route('admin.telegram-bot.index'),
                ]],
            ],
        ];
    }

    private function hubKeyboard(string $hub): array
    {
        $rows = match ($hub) {
            'content' => [
                [
                    ['text' => '📰 فید', 'callback_data' => 'menu:feed'],
                    ['text' => '🎬 ویدیو', 'callback_data' => 'menu:video'],
                ],
                [
                    ['text' => '📱 استوری', 'callback_data' => 'menu:story'],
                    ['text' => '📚 کالکشن', 'callback_data' => 'menu:collection'],
                ],
            ],
            'library' => [
                [
                    ['text' => '🎮 بازی‌ها', 'callback_data' => 'menu:game'],
                    ['text' => '🏭 استودیوها', 'callback_data' => 'menu:studio'],
                ],
                [
                    ['text' => '🎛 پلتفرم‌ها', 'callback_data' => 'menu:platform'],
                    ['text' => '📚 کالکشن‌ها', 'callback_data' => 'menu:collection'],
                ],
            ],
            'commerce' => [
                [[
                    'text' => '🛍 محصولات',
                    'callback_data' => 'menu:product',
                ]],
            ],
            'intelligence' => [
                [
                    ['text' => '🧩 Game Events', 'callback_data' => 'events:0'],
                    ['text' => '🧬 Graph Schema', 'callback_data' => 'menu:graph-schema'],
                ],
                [[
                    'text' => '🧠 اجرای GraphQL Query',
                    'callback_data' => 'graph-prompt',
                ]],
            ],
            'system' => [
                [
                    ['text' => '📡 وضعیت اتصال', 'callback_data' => 'menu:status'],
                    ['text' => '❓ راهنما', 'callback_data' => 'menu:help'],
                ],
                [[
                    'text' => '🧰 ابزارهای پیشرفته',
                    'callback_data' => 'menu:advanced',
                ]],
                [[
                    'text' => '⚙️ تنظیمات Bot',
                    'url' => route('admin.telegram-bot.index'),
                ]],
            ],
            default => [],
        };

        $rows[] = [[
            'text' => '🏠 Home',
            'callback_data' => 'menu:home',
        ]];

        return ['inline_keyboard' => $rows];
    }

    private function resourceHubKeyboard(string $resource): array
    {
        $rows = [
            [
                ['text' => '📋 آخرین‌ها', 'callback_data' => "list:{$resource}:0"],
                ['text' => '🔎 جستجو', 'callback_data' => "search:{$resource}"],
            ],
        ];

        if (in_array($resource, self::MUTABLE_RESOURCES, true)) {
            $rows[] = [[
                'text' => '➕ ساخت جدید',
                'callback_data' => "template:{$resource}:create",
            ]];
        }

        if (in_array($resource, ['game', 'studio'], true)) {
            $rows[] = [[
                'text' => '♻️ Restore با ID',
                'callback_data' => "restore-prompt:{$resource}",
            ]];
        }

        $hub = $this->resourceMeta($resource)['hub'];
        $rows[] = [
            ['text' => '↩️ بخش قبلی', 'callback_data' => "menu:{$hub}"],
            ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function resourceListKeyboard(string $resource, array $result, bool $paginate = true): array
    {
        $rows = [];
        foreach (array_slice((array) ($result['items'] ?? []), 0, 8) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }

            $label = Str::limit((string) ($item['title'] ?? $item['name'] ?? $item['slug'] ?? '#'.$item['id']), 31);
            $state = trim((string) ($item['status'] ?? $item['visibility'] ?? ''));
            $rows[] = [[
                'text' => '#'.$item['id'].' · '.$label.($state !== '' ? ' · '.$state : ''),
                'callback_data' => "view:{$resource}:{$item['id']}",
            ]];
        }

        if ($paginate) {
            $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
            $offset = max(0, (int) ($pagination['offset'] ?? 0));
            $limit = max(1, (int) ($pagination['limit'] ?? 8));
            $total = max(0, (int) ($pagination['total'] ?? 0));
            $nav = [];

            if ($offset > 0) {
                $previous = max(0, $offset - $limit);
                $nav[] = ['text' => '‹ قبلی', 'callback_data' => "list:{$resource}:{$previous}"];
            }

            if (($pagination['has_more'] ?? false) === true) {
                $next = min(10000, $offset + $limit);
                $nav[] = ['text' => 'بعدی ›', 'callback_data' => "list:{$resource}:{$next}"];
            }

            if ($nav !== []) {
                $rows[] = $nav;
            }

            if ($total > 0) {
                $page = (int) floor($offset / $limit) + 1;
                $pages = (int) ceil($total / $limit);
                $rows[] = [[
                    'text' => "صفحه {$page} / {$pages}",
                    'callback_data' => "list:{$resource}:{$offset}",
                ]];
            }
        }

        if (in_array($resource, self::MUTABLE_RESOURCES, true)) {
            $rows[] = [
                ['text' => '➕ جدید', 'callback_data' => "template:{$resource}:create"],
                ['text' => '🔎 جستجو', 'callback_data' => "search:{$resource}"],
            ];
        } else {
            $rows[] = [[
                'text' => '🔎 جستجو',
                'callback_data' => "search:{$resource}",
            ]];
        }

        $rows[] = [
            ['text' => '↩️ '.$this->resourceMeta($resource)['label'], 'callback_data' => "menu:{$resource}"],
            ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function resourceKeyboard(string $resource, int $id, array $item): array
    {
        $rows = [
            [
                ['text' => '🖼 Media', 'callback_data' => "media-slots:{$resource}:{$id}"],
                ['text' => '📦 Assets', 'callback_data' => "assets:{$resource}:{$id}"],
            ],
        ];

        $publicUrl = $this->publicUrl($item);
        if (in_array($resource, self::MUTABLE_RESOURCES, true)) {
            $editRow = [[
                'text' => '✏️ ویرایش',
                'callback_data' => "template:{$resource}:update:{$id}",
            ]];
            if ($publicUrl !== null) {
                $editRow[] = ['text' => '↗️ سایت', 'url' => $publicUrl];
            }
            $rows[] = $editRow;
        } elseif ($publicUrl !== null) {
            $rows[] = [[
                'text' => '↗️ باز کردن در سایت',
                'url' => $publicUrl,
            ]];
        }

        if ($resource === 'collection') {
            $rows[] = [[
                'text' => '🔗 Sync Videos',
                'callback_data' => "sync-prompt:{$id}",
            ]];
        }

        $state = (string) ($item['status'] ?? $item['visibility'] ?? '');
        if ($resource === 'feed') {
            $rows[] = [[
                'text' => $state === 'published' ? '📥 انتقال به Draft' : '🚀 انتشار',
                'callback_data' => ($state === 'published' ? 'unpublish-feed:' : 'publish-feed:').$id,
            ]];
        } elseif (in_array($resource, ['game', 'studio'], true)) {
            $target = $state === 'active' ? 'inactive' : 'active';
            $rows[] = [[
                'text' => $target === 'active' ? '✅ فعال‌سازی' : '⏸ غیرفعال‌سازی',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        } elseif ($resource === 'collection') {
            $target = $state === 'public' ? 'private' : 'public';
            $rows[] = [[
                'text' => $target === 'public' ? '🌐 عمومی کردن' : '🔒 خصوصی کردن',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        } elseif (in_array($resource, ['story', 'video'], true)) {
            $target = $state === 'published' ? 'draft' : 'published';
            $rows[] = [[
                'text' => $target === 'published' ? '🚀 انتشار' : '📥 انتقال به Draft',
                'callback_data' => "state:{$resource}:{$id}:{$target}",
            ]];
        }

        if (in_array($resource, self::MUTABLE_RESOURCES, true)) {
            $rows[] = [[
                'text' => '🗑 حذف',
                'callback_data' => "delete:{$resource}:{$id}",
            ]];
        }

        $rows[] = [
            ['text' => '↩️ فهرست', 'callback_data' => "list:{$resource}:0"],
            ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function eventListKeyboard(array $result): array
    {
        $rows = [];
        foreach (array_slice((array) ($result['items'] ?? []), 0, 8) as $event) {
            if (! is_array($event) || empty($event['id'])) {
                continue;
            }

            $label = Str::limit((string) ($event['title'] ?? 'Game Event'), 31);
            $status = (string) ($event['status'] ?? '');
            $rows[] = [[
                'text' => '🧩 #'.$event['id'].' · '.$label.($status !== '' ? ' · '.$status : ''),
                'callback_data' => 'event-actions:'.$event['id'],
            ]];
        }

        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
        $offset = max(0, (int) ($pagination['offset'] ?? 0));
        $limit = max(1, (int) ($pagination['limit'] ?? 8));
        $nav = [];
        if ($offset > 0) {
            $nav[] = ['text' => '‹ قبلی', 'callback_data' => 'events:'.max(0, $offset - $limit)];
        }
        if (($pagination['has_more'] ?? false) === true) {
            $nav[] = ['text' => 'بعدی ›', 'callback_data' => 'events:'.min(10000, $offset + $limit)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [[
            'text' => '➕ ثبت Game Event',
            'callback_data' => 'event-new',
        ]];
        $rows[] = [
            ['text' => '↩️ Intelligence', 'callback_data' => 'menu:intelligence'],
            ['text' => '🏠 Home', 'callback_data' => 'menu:home'],
        ];

        return ['inline_keyboard' => $rows];
    }

    private function resourceBackKeyboard(string $resource, int $id): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '↩️ رکورد', 'callback_data' => "view:{$resource}:{$id}"],
                    ['text' => '📋 فهرست', 'callback_data' => "list:{$resource}:0"],
                ],
                [[
                    'text' => '🏠 Home',
                    'callback_data' => 'menu:home',
                ]],
            ],
        ];
    }

    private function cancelKeyboard(string $back = 'menu:home'): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '✕ لغو', 'callback_data' => 'cancel'],
                    ['text' => '↩️ بازگشت', 'callback_data' => $back],
                ],
            ],
        ];
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

    private function resourceMeta(string $resource): array
    {
        return self::RESOURCE_LABELS[$resource]
            ?? ['label' => $resource, 'icon' => '•', 'hub' => 'system'];
    }

    private function publicUrl(array $item): ?string
    {
        foreach (['url', 'public_url', 'link_url'] as $key) {
            $value = trim((string) ($item[$key] ?? ''));
            if ($value === '') {
                continue;
            }

            if (str_starts_with($value, '/')) {
                return url($value);
            }

            if (filter_var($value, FILTER_VALIDATE_URL)) {
                return $value;
            }
        }

        return null;
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

    private function chatIdFromUpdate(array $update): ?string
    {
        if (is_array($update['callback_query'] ?? null)) {
            $value = $update['callback_query']['message']['chat']['id'] ?? null;

            return $value !== null ? (string) $value : null;
        }

        $value = $update['message']['chat']['id'] ?? null;

        return $value !== null ? (string) $value : null;
    }

    private function friendlyError(string $message): string
    {
        return match (true) {
            str_contains($message, 'Write operations are disabled') => '✍️ عملیات نوشتن از تنظیمات Bot خاموش است.',
            str_contains($message, 'Publishing operations are disabled') => '🚀 انتشار از تنظیمات Bot خاموش است.',
            str_contains($message, 'Destructive operations are disabled') => '🗑 عملیات حذف/Restore از تنظیمات Bot خاموش است.',
            str_contains($message, 'Media operations are disabled') => '🖼 عملیات مدیا از تنظیمات Bot خاموش است.',
            str_contains($message, 'Telegram bot is disabled') => 'Bot در پنل ادمین غیرفعال است.',
            default => $message,
        };
    }

    private function render(
        string $chatId,
        string $text,
        ?array $keyboard = null,
        ?int $messageId = null,
    ): void {
        if ($messageId && $messageId > 0) {
            try {
                $this->telegram->editMessageText($chatId, $messageId, $text, $keyboard);

                return;
            } catch (RuntimeException $exception) {
                if (str_contains(strtolower($exception->getMessage()), 'message is not modified')) {
                    return;
                }
            }
        }

        $this->send($chatId, $text, $keyboard);
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
