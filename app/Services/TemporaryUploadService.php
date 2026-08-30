<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use RuntimeException;

class TemporaryUploadService
{
    public function directory(int $userId, string $token): string
    {
        return storage_path("app/private/uploads/{$userId}/{$token}");
    }

    public function claim(int $userId, string $token): UploadedFile
    {
        $directory = $this->directory($userId, $token);
        $metadataPath = $directory.'/metadata.json';
        $assembledPath = $directory.'/assembled';
        if (! File::isFile($metadataPath) || ! File::isFile($assembledPath)) {
            throw new RuntimeException('فایل آپلودشده کامل نیست یا منقضی شده است.');
        }
        $metadata = json_decode((string) File::get($metadataPath), true, flags: JSON_THROW_ON_ERROR);

        return new UploadedFile($assembledPath, $metadata['name'], $metadata['mime'], null, true);
    }

    public function forget(int $userId, string $token): void
    {
        File::deleteDirectory($this->directory($userId, $token));
    }
}
