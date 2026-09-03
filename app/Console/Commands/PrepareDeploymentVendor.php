<?php

namespace App\Console\Commands;

use App\Services\Deployment\PackageBuilder;
use Illuminate\Console\Command;

class PrepareDeploymentVendor extends Command
{
    protected $signature = 'deployment:prepare';
    protected $description = 'Prepare and cache production PHP dependencies for deployment exports';

    public function handle(PackageBuilder $builder): int
    {
        try {
            $path = $builder->prepareProductionVendor(fn ($stage, $progress) => $this->line("[{$progress}%] {$stage}"));
            $this->info($path);
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
