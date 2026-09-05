<?php

namespace App\Console\Commands;

use App\Models\SmsPatternConfiguration;
use App\Services\Sms\SmsPattern;
use Illuminate\Console\Command;

class SyncSmsPatterns extends Command
{
    protected $signature = 'sms:sync-patterns';

    protected $description = 'Create missing SMS patterns and safely fill their default placeholder IDs';

    public function handle(): int
    {
        $rows = [];

        foreach (SmsPattern::cases() as $pattern) {
            $configuration = SmsPatternConfiguration::query()->firstOrNew(['code' => $pattern->value]);
            $created = ! $configuration->exists;

            if ($created || blank($configuration->provider_id)) {
                $configuration->provider_id = $pattern->defaultProviderId();
            }
            if ($created) {
                $configuration->is_active = true;
            }

            $configuration->save();
            $rows[] = [
                $pattern->label(),
                $pattern->value,
                $configuration->provider_id,
                $pattern->hasRealProviderId($configuration->provider_id) ? 'ready' : 'replace Body ID',
            ];
        }

        $this->table(['Pattern', 'Code', 'Provider ID', 'Status'], $rows);
        $this->info('SMS patterns synchronized. Existing real Body IDs were preserved.');
        $this->line('Replace placeholder IDs from: /admin/sms-patterns');

        return self::SUCCESS;
    }
}
