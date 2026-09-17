<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ProjectFileManagerService
{
    public const MAX_EDIT_BYTES = 10 * 1024 * 1024;

    public const MAX_UPLOAD_KILOBYTES = 50 * 1024;

    private string $root;

    public function __construct()
    {
        $root = realpath(base_path());
        if ($root === false) {
            throw new RuntimeException('Project root could not be resolved.');
        }

        $this->root = rtrim($root, DIRECTORY_SEPARATOR);
    }

    public function rootName(): string
    {
        return basename($this->root);
    }

    public function normalize(string|null $path): string
    {
        $path = trim(str_replace('\\', '/', (string) $path));

        if ($path === '' || $path === '.') {
            return '';
        }

        if (
            str_contains($path, "\0")
            || str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:\//', $path)
        ) {
            throw ValidationException::withMessages([
                'path' => 'مسیر فایل معتبر نیست.',
            ]);
        }

        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw ValidationException::withMessages([
                    'path' => 'خروج از ریشه پروژه مجاز نیست.',
                ]);
            }

            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listDirectory(string|null $path): array
    {
        $relative = $this->normalize($path);
        $absolute = $this->existingPath($relative);

        abort_unless(is_dir($absolute), 422, 'مسیر انتخاب‌شده پوشه نیست.');
        abort_unless(is_readable($absolute), 403, 'پوشه قابل خواندن نیست.');

        $entries = [];
        foreach (new \DirectoryIterator($absolute) as $item) {
            if ($item->isDot()) {
                continue;
            }

            $entryAbsolute = $item->getPathname();
            $entryRelative = $this->join($relative, $item->getFilename());
            $isLink = is_link($entryAbsolute);
            $realTarget = @realpath($entryAbsolute);
            $targetInsideProject = ! $isLink || ($realTarget !== false && $this->isInsideRoot($realTarget));
            $isDirectory = $targetInsideProject && is_dir($entryAbsolute);
            $isFile = $targetInsideProject && is_file($entryAbsolute);

            $entries[] = [
                'name' => $item->getFilename(),
                'path' => $entryRelative,
                'type' => $isDirectory ? 'directory' : ($isFile ? 'file' : 'link'),
                'is_link' => $isLink,
                'accessible' => $targetInsideProject,
                'hidden' => str_starts_with($item->getFilename(), '.'),
                'size' => $isFile ? (@filesize($entryAbsolute) ?: 0) : null,
                'modified_at' => ($mtime = @filemtime($entryAbsolute)) ? date(DATE_ATOM, $mtime) : null,
                'readable' => is_readable($entryAbsolute),
                'writable' => is_writable($entryAbsolute),
                'extension' => $isFile ? strtolower(pathinfo($item->getFilename(), PATHINFO_EXTENSION)) : null,
            ];
        }

        usort($entries, function (array $a, array $b): int {
            $typeA = $a['type'] === 'directory' ? 0 : ($a['type'] === 'file' ? 1 : 2);
            $typeB = $b['type'] === 'directory' ? 0 : ($b['type'] === 'file' ? 1 : 2);

            return $typeA <=> $typeB ?: strnatcasecmp($a['name'], $b['name']);
        });

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    public function readFile(string $path): array
    {
        $relative = $this->normalize($path);
        $absolute = $this->existingPath($relative);

        abort_unless($relative !== '' && is_file($absolute), 422, 'مسیر انتخاب‌شده فایل نیست.');
        abort_unless(is_readable($absolute), 403, 'فایل قابل خواندن نیست.');

        $size = @filesize($absolute) ?: 0;
        $mime = function_exists('mime_content_type') ? (@mime_content_type($absolute) ?: null) : null;
        $tooLarge = $size > self::MAX_EDIT_BYTES;
        $content = null;
        $binary = false;
        $hash = null;

        if (! $tooLarge) {
            $raw = file_get_contents($absolute);
            abort_if($raw === false, 500, 'خواندن فایل ناموفق بود.');

            $binary = str_contains($raw, "\0") || preg_match('//u', $raw) !== 1;
            if (! $binary) {
                $content = $raw;
                $hash = hash('sha256', $raw);
            }
        }

        return [
            'name' => basename($relative),
            'path' => $relative,
            'size' => $size,
            'modified_at' => ($mtime = @filemtime($absolute)) ? date(DATE_ATOM, $mtime) : null,
            'mime' => $mime,
            'content' => $content,
            'hash' => $hash,
            'binary' => $binary,
            'too_large' => $tooLarge,
            'editable' => ! $tooLarge && ! $binary && is_writable($absolute),
            'readable' => is_readable($absolute),
            'writable' => is_writable($absolute),
        ];
    }

    public function save(string $path, string $content, string|null $expectedHash = null): array
    {
        $relative = $this->normalize($path);
        $absolute = $this->existingPath($relative);

        abort_unless($relative !== '' && is_file($absolute), 422, 'مسیر انتخاب‌شده فایل نیست.');
        abort_unless(is_writable($absolute), 403, 'فایل قابل ویرایش نیست.');
        abort_if(strlen($content) > self::MAX_EDIT_BYTES, 422, 'حجم متن برای ویرایش آنلاین بیش از حد مجاز است.');

        if ($expectedHash) {
            $current = file_get_contents($absolute);
            abort_if($current === false, 500, 'خواندن نسخه فعلی فایل ناموفق بود.');

            if (! hash_equals(hash('sha256', $current), $expectedHash)) {
                throw ValidationException::withMessages([
                    'content' => 'این فایل بعد از باز شدن تغییر کرده است. صفحه را تازه‌سازی کنید تا تغییرات جدید از بین نرود.',
                ]);
            }
        }

        $written = file_put_contents($absolute, $content, LOCK_EX);
        abort_if($written === false, 500, 'ذخیره فایل ناموفق بود.');
        clearstatcache(true, $absolute);

        return [
            'path' => $relative,
            'bytes' => $written,
            'hash' => hash('sha256', $content),
        ];
    }

    public function createFile(string|null $directory, string $name, string $content = ''): string
    {
        $directory = $this->normalize($directory);
        $this->assertName($name);
        $relative = $this->join($directory, $name);
        $absolute = $this->newPath($relative);

        abort_if(file_exists($absolute) || is_link($absolute), 422, 'فایلی با این نام از قبل وجود دارد.');
        abort_if(strlen($content) > self::MAX_EDIT_BYTES, 422, 'حجم فایل بیش از حد مجاز برای ساخت آنلاین است.');
        abort_if(file_put_contents($absolute, $content, LOCK_EX) === false, 500, 'ساخت فایل ناموفق بود.');

        return $relative;
    }

    public function createDirectory(string|null $directory, string $name): string
    {
        $directory = $this->normalize($directory);
        $this->assertName($name);
        $relative = $this->join($directory, $name);
        $absolute = $this->newPath($relative);

        abort_if(file_exists($absolute) || is_link($absolute), 422, 'پوشه‌ای با این نام از قبل وجود دارد.');
        abort_unless(@mkdir($absolute, 0755), 500, 'ساخت پوشه ناموفق بود.');

        return $relative;
    }

    public function rename(string $path, string $newName): string
    {
        $relative = $this->normalize($path);
        abort_if($relative === '', 422, 'تغییر نام ریشه پروژه مجاز نیست.');
        $this->assertName($newName);

        $absolute = $this->existingPath($relative, true);
        $parentRelative = $this->parent($relative) ?? '';
        $newRelative = $this->join($parentRelative, $newName);
        $newAbsolute = $this->newPath($newRelative);

        abort_if(file_exists($newAbsolute) || is_link($newAbsolute), 422, 'نام مقصد از قبل وجود دارد.');
        abort_unless(@rename($absolute, $newAbsolute), 500, 'تغییر نام ناموفق بود.');

        return $newRelative;
    }

    public function delete(string $path): void
    {
        $relative = $this->normalize($path);
        abort_if($relative === '', 422, 'حذف ریشه پروژه مجاز نیست.');

        $absolute = $this->existingPath($relative, true);

        if (is_link($absolute) || is_file($absolute)) {
            abort_unless(@unlink($absolute), 500, 'حذف فایل ناموفق بود.');
            return;
        }

        abort_unless(is_dir($absolute), 422, 'مسیر انتخاب‌شده معتبر نیست.');
        abort_unless(File::deleteDirectory($absolute), 500, 'حذف پوشه ناموفق بود.');
    }

    public function upload(string|null $directory, UploadedFile $file, bool $overwrite = false): string
    {
        $directory = $this->normalize($directory);
        $name = basename($file->getClientOriginalName());
        $this->assertName($name);

        $relative = $this->join($directory, $name);
        $absolute = $this->newPath($relative);

        if (file_exists($absolute) || is_link($absolute)) {
            abort_unless($overwrite, 422, 'فایلی با این نام وجود دارد. برای جایگزینی، تأیید overwrite لازم است.');
            abort_if(is_dir($absolute) && ! is_link($absolute), 422, 'امکان جایگزینی پوشه با فایل وجود ندارد.');

            if (is_link($absolute)) {
                $this->existingPath($relative, true);
                abort_unless(@unlink($absolute), 500, 'حذف نسخه قبلی فایل ناموفق بود.');
            }
        }

        $file->move(dirname($absolute), basename($absolute));

        return $relative;
    }

    public function downloadPath(string $path): string
    {
        $relative = $this->normalize($path);
        $absolute = $this->existingPath($relative);

        abort_unless($relative !== '' && is_file($absolute) && is_readable($absolute), 404);

        return $absolute;
    }

    public function parent(string|null $path): string|null
    {
        $relative = $this->normalize($path);
        if ($relative === '') {
            return null;
        }

        $parent = str_replace('\\', '/', dirname($relative));

        return $parent === '.' ? '' : $parent;
    }

    /**
     * @return array<int, array{label:string,path:string}>
     */
    public function breadcrumbs(string|null $path): array
    {
        $relative = $this->normalize($path);
        $crumbs = [['label' => $this->rootName(), 'path' => '']];
        $built = '';

        foreach (array_filter(explode('/', $relative), fn (string $part) => $part !== '') as $part) {
            $built = $this->join($built, $part);
            $crumbs[] = ['label' => $part, 'path' => $built];
        }

        return $crumbs;
    }

    private function existingPath(string $relative, bool $allowBrokenSymlink = false): string
    {
        $absolute = $this->absolute($relative);
        $exists = file_exists($absolute) || is_link($absolute);
        abort_unless($exists, 404, 'فایل یا پوشه پیدا نشد.');

        $real = @realpath($absolute);
        if ($real === false) {
            abort_unless($allowBrokenSymlink && is_link($absolute), 404, 'مسیر قابل دسترسی نیست.');
            $this->assertParentInsideRoot($absolute);

            return $absolute;
        }

        abort_unless($this->isInsideRoot($real), 403, 'دسترسی به خارج از ریشه پروژه مجاز نیست.');

        return $absolute;
    }

    private function newPath(string $relative): string
    {
        abort_if($relative === '', 422, 'مسیر مقصد معتبر نیست.');
        $absolute = $this->absolute($relative);
        $this->assertParentInsideRoot($absolute);

        return $absolute;
    }

    private function assertParentInsideRoot(string $absolute): void
    {
        $parent = @realpath(dirname($absolute));
        abort_unless($parent !== false && is_dir($parent), 422, 'پوشه مقصد وجود ندارد.');
        abort_unless($this->isInsideRoot($parent), 403, 'دسترسی به خارج از ریشه پروژه مجاز نیست.');
    }

    private function absolute(string $relative): string
    {
        if ($relative === '') {
            return $this->root;
        }

        return $this->root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    private function isInsideRoot(string $absolute): bool
    {
        $absolute = rtrim($absolute, DIRECTORY_SEPARATOR);

        return $absolute === $this->root || str_starts_with($absolute, $this->root.DIRECTORY_SEPARATOR);
    }

    private function join(string|null $directory, string $name): string
    {
        $directory = trim((string) $directory, '/');

        return $directory === '' ? $name : $directory.'/'.$name;
    }

    private function assertName(string $name): void
    {
        $name = trim($name);

        if (
            $name === ''
            || $name === '.'
            || $name === '..'
            || str_contains($name, '/')
            || str_contains($name, '\\')
            || str_contains($name, "\0")
        ) {
            throw ValidationException::withMessages([
                'name' => 'نام فایل یا پوشه معتبر نیست.',
            ]);
        }
    }
}
