<?php

namespace App\Console\Commands;

use App\Services\Deployment\PackageBuilder;
use Illuminate\Console\Command;

class BuildDeploymentPackage extends Command
{
    protected $signature = 'deployment:build';
    protected $description = 'Build a signed, production-ready deployment package';
    public function handle(PackageBuilder $builder): int
    {
        try { $result=$builder->build(fn($stage,$progress)=>$this->line("[{$progress}%] {$stage}")); $this->info($result['path']); return self::SUCCESS; }
        catch(\Throwable $e){$this->error($e->getMessage());return self::FAILURE;}
    }
}
