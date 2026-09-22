<?php

namespace App\Services\Telegram;

use App\Models\TelegramBotSession;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

final class TelegramContentWizard
{
    public function __construct(
        private readonly TelegramApiClient $telegram,
        private readonly TelegramBotSessionStore $sessions,
        private readonly TelegramBotExecutor $executor,
        private readonly TelegramBotFormatter $formatter,
    ) {}

    public function handles(?TelegramBotSession $session): bool
    {
        return $session !== null && str_starts_with((string) $session->state, 'wizard_');
    }

    public function start(
        string $userId,
        string $chatId,
        string $resource,
        string $mode = 'create',
        ?int $id = null,
        ?int $messageId = null,
    ): array {
        if (! isset($this->definitions()[$resource])) {
            throw new InvalidArgumentException('این نوع محتوا هنوز فرم مدیریتی ندارد.');
        }

        $definition = $this->definition($resource);
        $tool = $mode === 'update' ? 'update_content' : (string) $definition['tool'];
        $this->executor->authorize($tool);

        $context = [
            'resource' => $resource,
            'mode' => $mode,
            'id' => $id,
            'step' => 0,
            'data' => [],
            'labels' => [],
            'relation_query' => null,
            'relation_offset' => 0,
        ];

        if ($mode === 'update') {
            if (! $id) {
                throw new InvalidArgumentException('رکورد موردنظر برای ویرایش مشخص نیست.');
            }

            $this->sessions->put($userId, $chatId, 'wizard_update_choose', $context);
            $this->render(
                $chatId,
                '✏️ <b>ویرایش '.$this->formatter->escape((string) $definition['label'])."</b>\n"
                .'فقط بخشی را که می‌خواهی تغییر بده انتخاب کن.',
                $this->fieldPickerKeyboard($resource, $id),
                $messageId,
            );

            return ['action' => 'wizard_update_choose', 'resource' => $resource, 'resource_id' => $id];
        }

        $context['data'] = $this->defaults($resource);
        $this->sessions->put($userId, $chatId, 'wizard_field', $context);

        return $this->promptCurrentField($userId, $chatId, $context, $messageId);
    }

    public function startEvent(
        string $userId,
        string $chatId,
        ?int $messageId = null,
    ): array {
        return $this->start($userId, $chatId, 'event', 'create', null, $messageId);
    }

    public function startCollectionSync(
        string $userId,
        string $chatId,
        int $collectionId,
        ?int $messageId = null,
    ): array {
        $this->executor->authorize('sync_collection_videos');

        $context = [
            'collection_id' => $collectionId,
            'selected' => [],
            'selected_labels' => [],
            'query' => null,
            'offset' => 0,
        ];
        $this->sessions->put($userId, $chatId, 'wizard_sync_videos', $context);

        return $this->renderVideoPicker($userId, $chatId, $context, $messageId);
    }

    public function handleText(
        string $userId,
        string $chatId,
        TelegramBotSession $session,
        string $text,
    ): array {
        $context = is_array($session->context) ? $session->context : [];

        if ($session->state === 'wizard_relation_search') {
            $context['relation_query'] = trim($text);
            $context['relation_offset'] = 0;
            $this->sessions->put($userId, $chatId, 'wizard_relation', $context);

            return $this->renderRelation($userId, $chatId, $context);
        }

        if ($session->state === 'wizard_sync_search') {
            $context['query'] = trim($text);
            $context['offset'] = 0;
            $this->sessions->put($userId, $chatId, 'wizard_sync_videos', $context);

            return $this->renderVideoPicker($userId, $chatId, $context);
        }

        if ($session->state !== 'wizard_field') {
            $this->send(
                $chatId,
                'از دکمه‌های همین مرحله استفاده کن یا «لغو» را بزن.',
                $this->cancelKeyboard(),
            );

            return ['action' => 'wizard_waiting_button'];
        }

        $field = $this->currentField($context);
        if (! $field) {
            throw new RuntimeException('مرحله فعلی فرم پیدا نشد.');
        }

        if (! in_array($field['type'], ['text', 'long_text', 'url', 'date', 'number'], true)) {
            $this->send($chatId, 'برای این مرحله از دکمه‌های زیر استفاده کن.', $this->fieldKeyboard($field, $context));

            return ['action' => 'wizard_waiting_button'];
        }

        $value = $this->normalizeTextValue($field, $text);
        $context = $this->storeFieldValue($context, $field, $value, $this->displayValue($field, $value));

        return $this->advance($userId, $chatId, $context);
    }

    public function handleCallback(
        string $userId,
        string $chatId,
        string $data,
        ?int $messageId = null,
    ): array {
        $session = $this->sessions->get($userId, $chatId);
        if (! $session || ! $this->handles($session)) {
            $this->send($chatId, 'این فرم منقضی شده؛ دوباره از منو شروع کن.');

            return ['action' => 'wizard_expired'];
        }

        $context = is_array($session->context) ? $session->context : [];
        $parts = explode(':', $data);
        $action = $parts[1] ?? '';

        if ($action === 'cancel') {
            $this->sessions->clear($userId, $chatId);
            $this->render(
                $chatId,
                '✕ عملیات لغو شد.',
                ['inline_keyboard' => [[['text' => '🏠 منوی اصلی', 'callback_data' => 'menu:home']]]],
                $messageId,
            );

            return ['action' => 'wizard_cancelled'];
        }

        if ($action === 'field') {
            $key = (string) ($parts[2] ?? '');
            $field = $this->fieldByKey((string) ($context['resource'] ?? ''), $key);
            if (! $field) {
                throw new InvalidArgumentException('فیلد انتخاب‌شده معتبر نیست.');
            }

            $context['editing_field'] = $key;
            $context['step'] = $this->fieldIndex((string) $context['resource'], $key);
            $this->sessions->put($userId, $chatId, 'wizard_field', $context);

            return $this->promptCurrentField($userId, $chatId, $context, $messageId);
        }

        if ($action === 'skip') {
            $field = $this->currentField($context);
            if (! $field || ($field['required'] ?? false)) {
                throw new InvalidArgumentException('این فیلد اجباری است و نمی‌شود ردش کرد.');
            }

            $value = array_key_exists('default', $field)
                ? $field['default']
                : (str_starts_with((string) $field['type'], 'multi_') ? [] : null);

            $context = $this->storeFieldValue(
                $context,
                $field,
                $value,
                $value === null || $value === [] ? 'بدون انتخاب' : $this->displayValue($field, $value),
            );

            return $this->advance($userId, $chatId, $context, $messageId);
        }

        if ($action === 'bool') {
            $field = $this->currentField($context);
            if (! $field || $field['type'] !== 'boolean') {
                throw new InvalidArgumentException('مرحله بله/خیر معتبر نیست.');
            }

            $value = ($parts[2] ?? '0') === '1';
            $context = $this->storeFieldValue($context, $field, $value, $value ? 'بله' : 'خیر');

            return $this->advance($userId, $chatId, $context, $messageId);
        }

        if ($action === 'choice') {
            $field = $this->currentField($context);
            $value = rawurldecode((string) ($parts[2] ?? ''));
            if (! $field || $field['type'] !== 'choice' || ! array_key_exists($value, $field['choices'] ?? [])) {
                throw new InvalidArgumentException('گزینه انتخاب‌شده معتبر نیست.');
            }

            $context = $this->storeFieldValue($context, $field, $value, (string) $field['choices'][$value]);

            return $this->advance($userId, $chatId, $context, $messageId);
        }

        if ($action === 'relation-search') {
            $this->sessions->put($userId, $chatId, 'wizard_relation_search', $context);
            $this->render(
                $chatId,
                '🔎 <b>جستجو</b>'."\n".'اسم موردنظر را بنویس.',
                $this->cancelKeyboard(),
                $messageId,
            );

            return ['action' => 'wizard_relation_search'];
        }

        if ($action === 'relation-all') {
            $context['relation_query'] = null;
            $context['relation_offset'] = 0;
            $this->sessions->put($userId, $chatId, 'wizard_relation', $context);

            return $this->renderRelation($userId, $chatId, $context, $messageId);
        }

        if ($action === 'relation-page') {
            $context['relation_offset'] = max(0, (int) ($parts[2] ?? 0));
            $this->sessions->put($userId, $chatId, 'wizard_relation', $context);

            return $this->renderRelation($userId, $chatId, $context, $messageId);
        }

        if ($action === 'relation') {
            $field = $this->currentField($context);
            if (! $field || ! in_array($field['type'], ['entity', 'multi_entity'], true)) {
                throw new InvalidArgumentException('مرحله انتخاب ارتباط معتبر نیست.');
            }

            $id = max(1, (int) ($parts[2] ?? 0));
            $label = $this->relationLabel($field, $id);

            if ($field['type'] === 'entity') {
                $context = $this->storeFieldValue($context, $field, $id, $label);

                return $this->advance($userId, $chatId, $context, $messageId);
            }

            $selected = array_values(array_unique(array_map('intval', (array) ($context['data'][$field['key']] ?? []))));
            $labels = is_array($context['labels'][$field['key']] ?? null)
                ? $context['labels'][$field['key']]
                : [];

            if (in_array($id, $selected, true)) {
                $selected = array_values(array_filter($selected, fn (int $value): bool => $value !== $id));
                unset($labels[(string) $id]);
            } else {
                $selected[] = $id;
                $labels[(string) $id] = $label;
            }

            $context['data'][$field['key']] = $selected;
            $context['labels'][$field['key']] = $labels;
            $this->sessions->put($userId, $chatId, 'wizard_relation', $context);

            return $this->renderRelation($userId, $chatId, $context, $messageId);
        }

        if ($action === 'relation-done') {
            $field = $this->currentField($context);
            if (! $field || $field['type'] !== 'multi_entity') {
                throw new InvalidArgumentException('انتخاب چندتایی معتبر نیست.');
            }

            $selected = array_values(array_unique(array_map('intval', (array) ($context['data'][$field['key']] ?? []))));
            if (($field['required'] ?? false) && $selected === []) {
                throw new InvalidArgumentException('حداقل یک مورد باید انتخاب شود.');
            }

            return $this->advance($userId, $chatId, $context, $messageId);
        }

        if ($action === 'back') {
            if (($context['mode'] ?? '') === 'update') {
                $this->sessions->put($userId, $chatId, 'wizard_update_choose', $context);
                $definition = $this->definition((string) $context['resource']);
                $this->render(
                    $chatId,
                    '✏️ <b>ویرایش '.$this->formatter->escape((string) $definition['label'])."</b>\n".'بخش موردنظر را انتخاب کن.',
                    $this->fieldPickerKeyboard((string) $context['resource'], (int) $context['id']),
                    $messageId,
                );

                return ['action' => 'wizard_update_choose'];
            }

            $context['step'] = max(0, ((int) ($context['step'] ?? 0)) - 1);
            $this->sessions->put($userId, $chatId, 'wizard_field', $context);

            return $this->promptCurrentField($userId, $chatId, $context, $messageId);
        }

        if ($action === 'sync-search') {
            $this->sessions->put($userId, $chatId, 'wizard_sync_search', $context);
            $this->render(
                $chatId,
                '🔎 <b>جستجوی ویدیو</b>'."\n".'بخشی از عنوان ویدیو را بنویس.',
                $this->cancelKeyboard(),
                $messageId,
            );

            return ['action' => 'wizard_sync_search'];
        }

        if ($action === 'sync-all') {
            $context['query'] = null;
            $context['offset'] = 0;
            $this->sessions->put($userId, $chatId, 'wizard_sync_videos', $context);

            return $this->renderVideoPicker($userId, $chatId, $context, $messageId);
        }

        if ($action === 'sync-page') {
            $context['offset'] = max(0, (int) ($parts[2] ?? 0));
            $this->sessions->put($userId, $chatId, 'wizard_sync_videos', $context);

            return $this->renderVideoPicker($userId, $chatId, $context, $messageId);
        }

        if ($action === 'sync-toggle') {
            $id = max(1, (int) ($parts[2] ?? 0));
            $selected = array_values(array_unique(array_map('intval', (array) ($context['selected'] ?? []))));
            $labels = is_array($context['selected_labels'] ?? null) ? $context['selected_labels'] : [];

            if (in_array($id, $selected, true)) {
                $selected = array_values(array_filter($selected, fn (int $value): bool => $value !== $id));
                unset($labels[(string) $id]);
            } else {
                $item = $this->executor->execute('get_content', ['resource' => 'video', 'id' => $id]);
                $selected[] = $id;
                $labels[(string) $id] = $this->itemLabel($item);
            }

            $context['selected'] = $selected;
            $context['selected_labels'] = $labels;
            $this->sessions->put($userId, $chatId, 'wizard_sync_videos', $context);

            return $this->renderVideoPicker($userId, $chatId, $context, $messageId);
        }

        if ($action === 'sync-done') {
            $selected = array_values(array_unique(array_map('intval', (array) ($context['selected'] ?? []))));
            $arguments = [
                'collection_id' => (int) $context['collection_id'],
                'video_ids' => $selected,
            ];

            return $this->queueConfirmation(
                $userId,
                $chatId,
                'sync_collection_videos',
                $arguments,
                'چینش ویدیوهای کالکشن',
                [
                    'کالکشن' => '#'.(int) $context['collection_id'],
                    'تعداد ویدیو' => (string) count($selected),
                ],
                $messageId,
            );
        }

        throw new InvalidArgumentException('این دکمه مربوط به فرم فعلی نیست.');
    }

    private function promptCurrentField(
        string $userId,
        string $chatId,
        array $context,
        ?int $messageId = null,
    ): array {
        $field = $this->currentField($context);
        if (! $field) {
            return $this->finish($userId, $chatId, $context, $messageId);
        }

        $definition = $this->definition((string) $context['resource']);
        $step = ((int) $context['step']) + 1;
        $total = count($definition['fields']);
        $required = ($field['required'] ?? false) ? ' • اجباری' : ' • اختیاری';
        $hint = filled($field['hint'] ?? null)
            ? "\n\n💡 ".$this->formatter->escape((string) $field['hint'])
            : '';

        $text = '🧩 <b>'.$this->formatter->escape((string) $definition['label'])."</b>\n"
            ."مرحله {$step} از {$total}\n"
            .'━━━━━━━━━━━━━━━━━━'."\n"
            .' <b>'.$this->formatter->escape((string) $field['label']).'</b>'.$required
            .$hint;

        if (in_array($field['type'], ['entity', 'multi_entity'], true)) {
            $this->sessions->put($userId, $chatId, 'wizard_relation', $context);

            return $this->renderRelation($userId, $chatId, $context, $messageId, $text);
        }

        $this->sessions->put($userId, $chatId, 'wizard_field', $context);
        $this->render($chatId, $text, $this->fieldKeyboard($field, $context), $messageId);

        return [
            'action' => 'wizard_field',
            'resource' => $context['resource'],
            'resource_id' => $context['id'] ?? null,
        ];
    }

    private function advance(
        string $userId,
        string $chatId,
        array $context,
        ?int $messageId = null,
    ): array {
        if (($context['mode'] ?? '') === 'update') {
            return $this->finish($userId, $chatId, $context, $messageId);
        }

        $context['step'] = ((int) ($context['step'] ?? 0)) + 1;
        $context['relation_query'] = null;
        $context['relation_offset'] = 0;
        $this->sessions->put($userId, $chatId, 'wizard_field', $context);

        return $this->promptCurrentField($userId, $chatId, $context, $messageId);
    }

    private function finish(
        string $userId,
        string $chatId,
        array $context,
        ?int $messageId = null,
    ): array {
        $resource = (string) $context['resource'];
        $definition = $this->definition($resource);
        $data = is_array($context['data'] ?? null) ? $context['data'] : [];

        if (($context['mode'] ?? '') === 'update') {
            $arguments = [
                'resource' => $resource,
                'id' => (int) $context['id'],
                'data' => $data,
            ];
            $tool = 'update_content';
            $title = 'ویرایش '.$definition['label'];
        } else {
            $tool = (string) $definition['tool'];
            $arguments = $data;
            $title = 'ساخت '.$definition['label'];
        }

        return $this->queueConfirmation(
            $userId,
            $chatId,
            $tool,
            $arguments,
            $title,
            $this->summary($resource, $data, (array) ($context['labels'] ?? [])),
            $messageId,
        );
    }

    private function queueConfirmation(
        string $userId,
        string $chatId,
        string $tool,
        array $arguments,
        string $title,
        array $summary,
        ?int $messageId = null,
    ): array {
        $this->executor->authorize($tool);

        $lines = [];
        foreach ($summary as $label => $value) {
            $lines[] = '• <b>'.$this->formatter->escape((string) $label).':</b> '.$this->formatter->escape((string) $value);
        }
        if ($lines === []) {
            $lines[] = '• بدون تغییر قابل‌نمایش';
        }

        $token = $this->sessions->queueConfirmation(
            $userId,
            $chatId,
            $tool,
            $arguments,
            implode("\n", $lines),
        );

        $this->render(
            $chatId,
            '✅ <b>'.$this->formatter->escape($title)."</b>\n"
            .'اطلاعات را یک بار مرور کن:'."\n\n"
            .implode("\n", $lines)."\n\n"
            .'اگر درست است تأیید کن.',
            [
                'inline_keyboard' => [
                    [[
                        'text' => '✅ تأیید و اجرا',
                        'callback_data' => 'confirm:'.$token,
                    ]],
                    [[
                        'text' => '✕ لغو',
                        'callback_data' => 'cancel',
                    ]],
                ],
            ],
            $messageId,
        );

        return [
            'action' => 'wizard_confirmation',
            'resource' => $arguments['resource'] ?? ($tool === 'upsert_game_event' ? 'event' : null),
            'resource_id' => $arguments['id'] ?? $arguments['collection_id'] ?? null,
        ];
    }

    private function renderRelation(
        string $userId,
        string $chatId,
        array $context,
        ?int $messageId = null,
        ?string $heading = null,
    ): array {
        $field = $this->currentField($context);
        if (! $field || ! in_array($field['type'], ['entity', 'multi_entity'], true)) {
            throw new RuntimeException('فیلد ارتباطی پیدا نشد.');
        }

        $resource = (string) $field['source'];
        $offset = max(0, (int) ($context['relation_offset'] ?? 0));
        $limit = 8;
        $arguments = [
            'resource' => $resource,
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];

        $query = trim((string) ($context['relation_query'] ?? ''));
        if ($query !== '') {
            $arguments['query'] = $query;
        }

        if (($field['key'] ?? '') === 'playlist_ids' && ! empty($context['data']['game_id'])) {
            $arguments['game_id'] = (int) $context['data']['game_id'];
        }

        $result = $this->executor->execute('select_content', $arguments);
        $rows = [];
        $selected = array_map('intval', (array) ($context['data'][$field['key']] ?? []));

        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $id = (int) $item['id'];
            $checked = $field['type'] === 'multi_entity' && in_array($id, $selected, true);
            $rows[] = [[
                'text' => ($checked ? '✅ ' : '').Str::limit($this->itemLabel($item), 34),
                'callback_data' => 'wiz:relation:'.$id,
            ]];
        }

        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
        $nav = [];
        if ($offset > 0) {
            $nav[] = [
                'text' => '‹ قبلی',
                'callback_data' => 'wiz:relation-page:'.max(0, $offset - $limit),
            ];
        }
        if (($pagination['has_more'] ?? false) === true) {
            $nav[] = [
                'text' => 'بعدی ›',
                'callback_data' => 'wiz:relation-page:'.($offset + $limit),
            ];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $searchRow = [[
            'text' => '🔎 جستجو',
            'callback_data' => 'wiz:relation-search',
        ]];
        if ($query !== '') {
            $searchRow[] = [
                'text' => '📋 نمایش همه',
                'callback_data' => 'wiz:relation-all',
            ];
        }
        $rows[] = $searchRow;

        if ($field['type'] === 'multi_entity') {
            $rows[] = [[
                'text' => '✅ تمام شد ('.count($selected).')',
                'callback_data' => 'wiz:relation-done',
            ]];
        } elseif (! ($field['required'] ?? false)) {
            $rows[] = [[
                'text' => '⏭ بدون انتخاب',
                'callback_data' => 'wiz:skip',
            ]];
        }

        $rows[] = [
            ['text' => '↩️ قبلی', 'callback_data' => 'wiz:back'],
            ['text' => '✕ لغو', 'callback_data' => 'wiz:cancel'],
        ];

        $text = $heading ?: (
            '🔗 <b>'.$this->formatter->escape((string) $field['label'])."</b>\n"
            .($query !== '' ? 'نتیجه جستجو برای «'.$this->formatter->escape($query).'»' : 'از لیست انتخاب کن یا جستجو بزن.')
        );

        $this->render($chatId, $text, ['inline_keyboard' => $rows], $messageId);

        return ['action' => 'wizard_relation', 'resource' => $context['resource'] ?? null];
    }

    private function renderVideoPicker(
        string $userId,
        string $chatId,
        array $context,
        ?int $messageId = null,
    ): array {
        $offset = max(0, (int) ($context['offset'] ?? 0));
        $limit = 8;
        $arguments = [
            'resource' => 'video',
            'limit' => $limit,
            'offset' => $offset,
            'order_by' => 'updated_at',
            'order_dir' => 'desc',
        ];
        $query = trim((string) ($context['query'] ?? ''));
        if ($query !== '') {
            $arguments['query'] = $query;
        }

        $result = $this->executor->execute('select_content', $arguments);
        $selected = array_map('intval', (array) ($context['selected'] ?? []));
        $rows = [];

        foreach ((array) ($result['items'] ?? []) as $item) {
            if (! is_array($item) || empty($item['id'])) {
                continue;
            }
            $id = (int) $item['id'];
            $rows[] = [[
                'text' => (in_array($id, $selected, true) ? '✅ ' : '').Str::limit($this->itemLabel($item), 34),
                'callback_data' => 'wiz:sync-toggle:'.$id,
            ]];
        }

        $pagination = is_array($result['pagination'] ?? null) ? $result['pagination'] : [];
        $nav = [];
        if ($offset > 0) {
            $nav[] = ['text' => '‹ قبلی', 'callback_data' => 'wiz:sync-page:'.max(0, $offset - $limit)];
        }
        if (($pagination['has_more'] ?? false) === true) {
            $nav[] = ['text' => 'بعدی ›', 'callback_data' => 'wiz:sync-page:'.($offset + $limit)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $rows[] = [
            ['text' => '🔎 جستجوی ویدیو', 'callback_data' => 'wiz:sync-search'],
            ['text' => '📋 همه', 'callback_data' => 'wiz:sync-all'],
        ];
        $rows[] = [[
            'text' => '✅ ثبت چینش ('.count($selected).')',
            'callback_data' => 'wiz:sync-done',
        ]];
        $rows[] = [[
            'text' => '✕ لغو',
            'callback_data' => 'wiz:cancel',
        ]];

        $this->render(
            $chatId,
            '🎬 <b>ویدیوهای کالکشن</b>'."\n"
            .'ویدیوها را تیک بزن؛ ترتیب انتخاب، ترتیب کالکشن خواهد بود.',
            ['inline_keyboard' => $rows],
            $messageId,
        );

        return ['action' => 'wizard_sync_videos', 'resource' => 'collection', 'resource_id' => (int) $context['collection_id']];
    }

    private function fieldKeyboard(array $field, array $context): array
    {
        $rows = [];

        if ($field['type'] === 'boolean') {
            $rows[] = [
                ['text' => '✅ بله', 'callback_data' => 'wiz:bool:1'],
                ['text' => '❌ خیر', 'callback_data' => 'wiz:bool:0'],
            ];
        }

        if ($field['type'] === 'choice') {
            foreach (array_chunk($field['choices'], 2, true) as $chunk) {
                $row = [];
                foreach ($chunk as $value => $label) {
                    $row[] = [
                        'text' => (string) $label,
                        'callback_data' => 'wiz:choice:'.rawurlencode((string) $value),
                    ];
                }
                $rows[] = $row;
            }
        }

        if (! ($field['required'] ?? false)) {
            $defaultLabel = array_key_exists('default', $field)
                ? '⏭ استفاده از مقدار پیش‌فرض'
                : '⏭ رد کردن';
            $rows[] = [[
                'text' => $defaultLabel,
                'callback_data' => 'wiz:skip',
            ]];
        }

        $nav = [];
        if (((int) ($context['step'] ?? 0)) > 0 || ($context['mode'] ?? '') === 'update') {
            $nav[] = ['text' => '↩️ قبلی', 'callback_data' => 'wiz:back'];
        }
        $nav[] = ['text' => '✕ لغو', 'callback_data' => 'wiz:cancel'];
        $rows[] = $nav;

        return ['inline_keyboard' => $rows];
    }

    private function fieldPickerKeyboard(string $resource, int $id): array
    {
        $rows = [];
        foreach ($this->definition($resource)['fields'] as $field) {
            $rows[] = [[
                'text' => '✏️ '.$field['label'],
                'callback_data' => 'wiz:field:'.$field['key'],
            ]];
        }
        $rows[] = [[
            'text' => '↩️ بازگشت به رکورد',
            'callback_data' => "view:{$resource}:{$id}",
        ]];

        return ['inline_keyboard' => $rows];
    }

    private function cancelKeyboard(): array
    {
        return [
            'inline_keyboard' => [[[
                'text' => '✕ لغو',
                'callback_data' => 'wiz:cancel',
            ]]],
        ];
    }

    private function currentField(array $context): ?array
    {
        $resource = (string) ($context['resource'] ?? '');
        $fields = $this->definition($resource)['fields'];
        $index = max(0, (int) ($context['step'] ?? 0));

        return $fields[$index] ?? null;
    }

    private function fieldByKey(string $resource, string $key): ?array
    {
        foreach ($this->definition($resource)['fields'] as $field) {
            if ($field['key'] === $key) {
                return $field;
            }
        }

        return null;
    }

    private function fieldIndex(string $resource, string $key): int
    {
        foreach ($this->definition($resource)['fields'] as $index => $field) {
            if ($field['key'] === $key) {
                return $index;
            }
        }

        throw new InvalidArgumentException('فیلد پیدا نشد.');
    }

    private function storeFieldValue(array $context, array $field, mixed $value, mixed $display): array
    {
        $context['data'][$field['key']] = $value;
        $context['labels'][$field['key']] = $display;

        return $context;
    }

    private function normalizeTextValue(array $field, string $text): mixed
    {
        $value = trim($text);
        if ($value === '' && ($field['required'] ?? false)) {
            throw new InvalidArgumentException('این فیلد نمی‌تواند خالی باشد.');
        }

        if ($field['type'] === 'number') {
            if (! is_numeric($value)) {
                throw new InvalidArgumentException('لطفاً فقط عدد وارد کن.');
            }
            $number = (int) $value;
            if (isset($field['min']) && $number < $field['min']) {
                throw new InvalidArgumentException('عدد واردشده از حداقل مجاز کمتر است.');
            }
            if (isset($field['max']) && $number > $field['max']) {
                throw new InvalidArgumentException('عدد واردشده از حداکثر مجاز بیشتر است.');
            }

            return $number;
        }

        if ($field['type'] === 'url' && $value !== '' && ! str_starts_with($value, '/') && ! filter_var($value, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('لینک معتبر وارد کن؛ مثلاً https://example.com');
        }

        if (isset($field['max']) && mb_strlen($value) > (int) $field['max']) {
            throw new InvalidArgumentException('متن از حد مجاز طولانی‌تر است.');
        }

        return $value === '' ? null : $value;
    }

    private function relationLabel(array $field, int $id): string
    {
        $item = $this->executor->execute('get_content', [
            'resource' => (string) $field['source'],
            'id' => $id,
        ]);

        return $this->itemLabel($item);
    }

    private function itemLabel(array $item): string
    {
        return (string) ($item['title'] ?? $item['name'] ?? $item['slug'] ?? ('#'.($item['id'] ?? '?')));
    }

    private function displayValue(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'بدون مقدار';
        }
        if (is_bool($value)) {
            return $value ? 'بله' : 'خیر';
        }
        if (is_array($value)) {
            return count($value).' مورد';
        }

        return Str::limit((string) $value, 100);
    }

    private function summary(string $resource, array $data, array $labels): array
    {
        $summary = [];
        foreach ($this->definition($resource)['fields'] as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $data)) {
                continue;
            }

            $display = $labels[$key] ?? $this->displayValue($field, $data[$key]);
            if (is_array($display)) {
                $display = $display === [] ? 'بدون انتخاب' : implode('، ', array_values($display));
            }
            $summary[(string) $field['label']] = (string) $display;
        }

        return $summary;
    }

    private function defaults(string $resource): array
    {
        $defaults = [];
        foreach ($this->definition($resource)['fields'] as $field) {
            if (array_key_exists('default', $field)) {
                $defaults[$field['key']] = $field['default'];
            }
        }

        return $defaults;
    }

    private function definition(string $resource): array
    {
        $definition = $this->definitions()[$resource] ?? null;
        if (! $definition) {
            throw new InvalidArgumentException('فرم این بخش پیدا نشد.');
        }

        return $definition;
    }

    private function definitions(): array
    {
        return [
            'game' => [
                'label' => 'بازی',
                'tool' => 'create_game',
                'fields' => [
                    ['key' => 'name', 'label' => 'نام بازی', 'type' => 'text', 'required' => true, 'max' => 160, 'hint' => 'نام رسمی بازی را بنویس.'],
                    ['key' => 'studio_id', 'label' => 'استودیوی سازنده', 'type' => 'entity', 'source' => 'studio'],
                    ['key' => 'developer', 'label' => 'توسعه‌دهنده', 'type' => 'text', 'max' => 160],
                    ['key' => 'publisher', 'label' => 'ناشر', 'type' => 'text', 'max' => 160],
                    ['key' => 'release_date', 'label' => 'تاریخ انتشار', 'type' => 'date', 'hint' => 'مثال: 2026-10-15'],
                    ['key' => 'description', 'label' => 'توضیحات', 'type' => 'long_text', 'max' => 100000],
                    ['key' => 'platform_ids', 'label' => 'پلتفرم‌ها', 'type' => 'multi_entity', 'source' => 'platform', 'default' => []],
                ],
            ],
            'studio' => [
                'label' => 'استودیو',
                'tool' => 'create_studio',
                'fields' => [
                    ['key' => 'name', 'label' => 'نام استودیو', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['key' => 'description', 'label' => 'توضیحات', 'type' => 'long_text', 'max' => 100000],
                    ['key' => 'website', 'label' => 'وب‌سایت رسمی', 'type' => 'url', 'max' => 255],
                ],
            ],
            'collection' => [
                'label' => 'کالکشن',
                'tool' => 'create_collection',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان کالکشن', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['key' => 'game_id', 'label' => 'بازی مرتبط', 'type' => 'entity', 'source' => 'game'],
                    ['key' => 'studio_id', 'label' => 'استودیوی مرتبط', 'type' => 'entity', 'source' => 'studio'],
                    ['key' => 'description', 'label' => 'توضیحات', 'type' => 'long_text', 'max' => 100000],
                ],
            ],
            'video' => [
                'label' => 'ویدیو',
                'tool' => 'create_video',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان ویدیو', 'type' => 'text', 'required' => true, 'max' => 160, 'hint' => 'فقط عنوانی که در صفحه ویدیو نمایش داده می‌شود.'],
                    ['key' => 'game_id', 'label' => 'بازی مرتبط', 'type' => 'entity', 'source' => 'game', 'hint' => 'اگر ویدیو مربوط به بازی خاصی است انتخابش کن.'],
                    ['key' => 'playlist_ids', 'label' => 'کالکشن', 'type' => 'multi_entity', 'source' => 'collection', 'default' => [], 'hint' => 'اختیاری؛ می‌توانی ردش کنی.'],
                    ['key' => 'excerpt', 'label' => 'توضیح کوتاه', 'type' => 'long_text', 'max' => 500, 'hint' => 'اختیاری؛ یک توضیح کوتاه برای ویدیو.'],
                ],
            ],
            'feed' => [
                'label' => 'فید',
                'tool' => 'create_feed',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان فید', 'type' => 'text', 'required' => true, 'max' => 160],
                    ['key' => 'body', 'label' => 'متن فید', 'type' => 'long_text', 'max' => 100000],
                    ['key' => 'feed_type', 'label' => 'نوع محتوا', 'type' => 'choice', 'required' => true, 'default' => 'post', 'choices' => [
                        'post' => '📝 پست', 'news' => '📰 خبر', 'article' => '📚 مقاله', 'video' => '🎬 ویدیو',
                        'clip' => '🎞 کلیپ', 'trailer' => '🍿 تریلر', 'game_update' => '🔄 آپدیت بازی',
                        'review' => '⭐ بررسی', 'image' => '🖼 تصویر',
                    ]],
                    ['key' => 'feed_badge', 'label' => 'برچسب فید', 'type' => 'choice', 'default' => 'news', 'choices' => [
                        'breaking' => '🚨 فوری', 'news' => '📰 خبر', 'trailer' => '🍿 تریلر',
                        'gameplay' => '🎮 گیم‌پلی', 'update' => '🔄 آپدیت', 'rumor' => '💭 شایعه',
                        'review' => '⭐ بررسی', 'patch_notes' => '🛠 پچ‌نوت',
                    ]],
                    ['key' => 'game_id', 'label' => 'بازی مرتبط', 'type' => 'entity', 'source' => 'game'],
                    ['key' => 'related_product_id', 'label' => 'محصول مرتبط', 'type' => 'entity', 'source' => 'product'],
                    ['key' => 'related_content_id', 'label' => 'ویدیوی مرتبط', 'type' => 'entity', 'source' => 'video'],
                    ['key' => 'allow_comments', 'label' => 'کامنت فعال باشد؟', 'type' => 'boolean', 'default' => true],
                    ['key' => 'notify_followers', 'label' => 'به دنبال‌کننده‌ها اعلان برود؟', 'type' => 'boolean', 'default' => false],
                    ['key' => 'seo_title', 'label' => 'عنوان سئو', 'type' => 'text', 'max' => 60],
                    ['key' => 'seo_description', 'label' => 'توضیحات سئو', 'type' => 'long_text', 'max' => 160],
                ],
            ],
            'story' => [
                'label' => 'استوری',
                'tool' => 'create_story',
                'fields' => [
                    ['key' => 'title', 'label' => 'عنوان استوری', 'type' => 'text', 'required' => true, 'max' => 100],
                    ['key' => 'excerpt', 'label' => 'توضیح کوتاه', 'type' => 'long_text', 'max' => 240],
                    ['key' => 'game_id', 'label' => 'بازی مرتبط', 'type' => 'entity', 'source' => 'game'],
                    ['key' => 'link_url', 'label' => 'لینک مقصد', 'type' => 'url', 'max' => 500],
                    ['key' => 'link_label', 'label' => 'متن دکمه لینک', 'type' => 'text', 'max' => 60],
                ],
            ],
            'event' => [
                'label' => 'رویداد بازی',
                'tool' => 'upsert_game_event',
                'fields' => [
                    ['key' => 'game_id', 'label' => 'بازی', 'type' => 'entity', 'source' => 'game', 'required' => true],
                    ['key' => 'type', 'label' => 'نوع رویداد', 'type' => 'choice', 'required' => true, 'choices' => [
                        'release_date_changed' => '📅 تغییر تاریخ انتشار',
                        'released' => '🚀 منتشر شد',
                        'major_patch' => '🛠 آپدیت مهم',
                        'dlc_announced' => '📣 DLC جدید',
                        'dlc_released' => '📦 DLC منتشر شد',
                        'subscription_added' => '➕ اضافه‌شدن به سرویس',
                        'subscription_leaving' => '➖ خروج از سرویس',
                        'price_drop' => '💰 کاهش قیمت',
                        'free_weekend' => '🎁 آخرهفته رایگان',
                        'major_trailer' => '🍿 تریلر مهم',
                        'preload_available' => '⬇️ پری‌لود فعال شد',
                        'server_issue' => '⚠️ اختلال سرور',
                        'server_restored' => '✅ سرورها پایدار شدند',
                        'major_news' => '📰 خبر مهم',
                    ]],
                    ['key' => 'title', 'label' => 'عنوان رویداد', 'type' => 'text', 'required' => true, 'max' => 200],
                    ['key' => 'summary', 'label' => 'خلاصه', 'type' => 'long_text', 'max' => 3000],
                    ['key' => 'source_name', 'label' => 'نام منبع', 'type' => 'text', 'max' => 120, 'default' => 'Telegram Admin'],
                    ['key' => 'source_url', 'label' => 'لینک منبع', 'type' => 'url', 'max' => 1000],
                    ['key' => 'importance_score', 'label' => 'اهمیت از ۰ تا ۱۰۰', 'type' => 'number', 'min' => 0, 'max' => 100, 'default' => 50],
                ],
            ],
        ];
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
}
