<?php

namespace App\Services\Telegram;

use RuntimeException;

final class TelegramBotExecutor
{
    public function __construct(
        private readonly TelegramBotToolRegistry $registry,
        private readonly TelegramBotSettings $settings,
    ) {}

    public function authorize(string $tool): array
    {
        $definition = $this->registry->definition($tool);
        $settings = $this->settings->resolved();

        if (! ($settings['enabled'] ?? false)) {
            throw new RuntimeException('Telegram bot is disabled.');
        }

        if ($definition['kind'] !== 'read' && ! ($settings['write_enabled'] ?? false)) {
            throw new RuntimeException('Write operations are disabled in Telegram bot settings.');
        }

        if ($definition['kind'] === 'publish' && ! ($settings['publish_enabled'] ?? false)) {
            throw new RuntimeException('Publishing operations are disabled in Telegram bot settings.');
        }

        if ($definition['kind'] === 'destructive' && ! ($settings['destructive_enabled'] ?? false)) {
            throw new RuntimeException('Destructive operations are disabled in Telegram bot settings.');
        }

        if ($definition['kind'] === 'media' && ! ($settings['media_enabled'] ?? false)) {
            throw new RuntimeException('Media operations are disabled in Telegram bot settings.');
        }

        return $definition;
    }

    public function execute(string $tool, array $arguments): array
    {
        $this->authorize($tool);

        return $this->registry->execute($tool, $arguments);
    }

    public function requiresConfirmation(string $tool): bool
    {
        return (bool) ($this->authorize($tool)['confirm'] ?? false);
    }

    public function toolNames(): array
    {
        return $this->registry->names();
    }
}
