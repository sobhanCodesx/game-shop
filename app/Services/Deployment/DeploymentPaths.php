<?php

namespace App\Services\Deployment;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class DeploymentPaths
{
    public function root(): string
    {
        $path = (string) config('deployment.directory');
        File::ensureDirectoryExists($path, 0750, true);

        return $path;
    }

    public function operation(string $id): string
    {
        $this->assertId($id);
        return $this->root().DIRECTORY_SEPARATOR.$id;
    }

    public function atomicJson(string $path, array $value): void
    {
        File::ensureDirectoryExists(dirname($path), 0750, true);
        $temporary = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (file_put_contents($temporary, $json, LOCK_EX) === false || ! rename($temporary, $path)) {
            @unlink($temporary);
            throw new RuntimeException('امکان ذخیره امن وضعیت انتشار وجود ندارد.');
        }
    }

    public function readJson(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException('اطلاعات عملیات پیدا نشد.');
        }
        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function assertId(string $id): void
    {
        if (! preg_match('/^[a-f0-9-]{36}$/D', $id)) {
            throw new RuntimeException('شناسه عملیات معتبر نیست.');
        }
    }
}
