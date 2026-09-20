<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AndroidAppPage;
use App\Models\AndroidRelease;
use App\Services\MediaStorage;
use App\Services\TemporaryUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Throwable;

class AndroidReleaseController extends Controller
{
    private const MAX_APK_BYTES = 1073741824;
    private const MAX_PAGE_MEDIA_BYTES = 314572800;

    public function index(): Response
    {
        $releases = AndroidRelease::query()
            ->with('releasedBy:id,name')
            ->orderByDesc('version_code')
            ->limit(30)
            ->get()
            ->map(fn (AndroidRelease $release) => $this->payload($release))
            ->values();

        return Inertia::render('Admin/AndroidReleases/Index', [
            'releases' => $releases,
            'latest' => $releases->firstWhere('is_active', true),
            'maxUploadBytes' => self::MAX_APK_BYTES,
            'page' => $this->pagePayload(),
            'maxPageMediaBytes' => self::MAX_PAGE_MEDIA_BYTES,
        ]);
    }

    public function store(Request $request, TemporaryUploadService $uploads): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => ['required', 'uuid'],
            'release_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $file = $uploads->claim((int) $request->user()->id, $data['upload_token']);
        $storedDisk = null;
        $storedPath = null;

        try {
            abort_unless(
                strtolower($file->getClientOriginalExtension()) === 'apk',
                422,
                'فقط فایل APK قابل انتشار است.',
            );

            $size = (int) $file->getSize();
            abort_unless(
                $size > 0 && $size <= self::MAX_APK_BYTES,
                422,
                'حجم APK معتبر نیست یا از سقف مجاز بیشتر است.',
            );

            $next = AndroidRelease::nextVersion();
            $storedDisk = $this->storageDisk();
            $storedPath = 'android-releases/playnexus-'.$next['version'].'.apk';
            $checksum = hash_file('sha256', $file->getRealPath()) ?: null;
            $stream = fopen($file->getRealPath(), 'rb');

            if ($stream === false) {
                throw new RuntimeException('امکان خواندن فایل APK وجود ندارد.');
            }

            try {
                $stored = Storage::disk($storedDisk)->put($storedPath, $stream);
            } finally {
                fclose($stream);
            }

            if (! $stored) {
                throw new RuntimeException('ذخیره APK روی فضای دانلود ناموفق بود.');
            }

            $release = DB::transaction(function () use ($request, $data, $file, $size, $checksum, $next, $storedDisk, $storedPath) {
                AndroidRelease::query()
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                return AndroidRelease::query()->create([
                    'version' => $next['version'],
                    'version_code' => $next['version_code'],
                    'disk' => $storedDisk,
                    'file_path' => $storedPath,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $size,
                    'checksum_sha256' => $checksum,
                    'release_notes' => $data['release_notes'] ?? null,
                    'is_active' => true,
                    'released_by' => $request->user()->id,
                    'released_at' => now(),
                ]);
            });

            Cache::forget('android.latest-release.v1');
            $release->load('releasedBy:id,name');

            return response()->json([
                'message' => 'نسخه '.$release->version.' با موفقیت منتشر شد.',
                'release' => $this->payload($release),
            ], 201);
        } catch (Throwable $exception) {
            if ($storedDisk && $storedPath) {
                try {
                    Storage::disk($storedDisk)->delete($storedPath);
                } catch (Throwable) {
                    // The original exception is more important than cleanup.
                }
            }

            throw $exception;
        } finally {
            $uploads->forget((int) $request->user()->id, $data['upload_token']);
        }
    }

    public function updatePage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'eyebrow' => ['required', 'string', 'max:80'],
            'hero_title' => ['required', 'string', 'max:180'],
            'hero_description' => ['required', 'string', 'max:1200'],
            'promo_title' => ['required', 'string', 'max:180'],
            'promo_description' => ['required', 'string', 'max:1600'],
            'seo_title' => ['required', 'string', 'max:180'],
            'seo_description' => ['required', 'string', 'max:300'],
        ]);

        $current = AndroidAppPage::current();
        $content = array_replace($current, $data);

        AndroidAppPage::query()->updateOrCreate(
            ['id' => 1],
            ['content' => $content],
        );

        return response()->json([
            'message' => 'محتوای صفحه اندروید ذخیره شد.',
            'page' => $this->pagePayload(),
        ]);
    }

    public function uploadPageMedia(Request $request, TemporaryUploadService $uploads): JsonResponse
    {
        $data = $request->validate([
            'upload_token' => ['required', 'uuid'],
            'alt' => ['nullable', 'string', 'max:180'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $userId = (int) $request->user()->id;
        $file = $uploads->claim($userId, $data['upload_token']);
        $path = null;

        try {
            $size = (int) $file->getSize();
            abort_unless(
                $size > 0 && $size <= self::MAX_PAGE_MEDIA_BYTES,
                422,
                'حجم مدیا معتبر نیست یا از سقف مجاز بیشتر است.',
            );

            $mime = strtolower((string) $file->getMimeType());
            $type = str_starts_with($mime, 'image/')
                ? 'image'
                : (str_starts_with($mime, 'video/') ? 'video' : null);

            abort_unless($type, 422, 'فقط تصویر یا ویدیو برای صفحه اندروید قابل آپلود است.');

            $path = $file->store(
                $type === 'image' ? 'android/page/images' : 'android/page/videos',
                (string) config('media.disk', 'public'),
            );
            abort_unless($path, 500, 'ذخیره مدیا انجام نشد.');

            $current = AndroidAppPage::current();
            $media = collect($current['media'] ?? [])->filter(fn ($item) => is_array($item))->values();
            $item = [
                'id' => (string) Str::uuid(),
                'type' => $type,
                'path' => $path,
                'alt' => trim((string) ($data['alt'] ?? '')) ?: 'اپلیکیشن اندروید پلی نکسوس',
                'caption' => trim((string) ($data['caption'] ?? '')),
            ];

            $current['media'] = $media->push($item)->all();
            AndroidAppPage::query()->updateOrCreate(['id' => 1], ['content' => $current]);

            return response()->json([
                'message' => 'مدیا به صفحه اندروید اضافه شد.',
                'page' => $this->pagePayload(),
            ], 201);
        } catch (Throwable $exception) {
            if ($path) {
                MediaStorage::disk()->delete($path);
            }

            throw $exception;
        } finally {
            $uploads->forget($userId, $data['upload_token']);
        }
    }

    public function destroyPageMedia(string $mediaId): JsonResponse
    {
        $current = AndroidAppPage::current();
        $media = collect($current['media'] ?? [])->filter(fn ($item) => is_array($item))->values();
        $target = $media->first(fn (array $item) => ($item['id'] ?? null) === $mediaId);

        abort_unless($target, 404, 'مدیا پیدا نشد.');

        $current['media'] = $media
            ->reject(fn (array $item) => ($item['id'] ?? null) === $mediaId)
            ->values()
            ->all();

        AndroidAppPage::query()->updateOrCreate(['id' => 1], ['content' => $current]);

        if (filled($target['path'] ?? null)) {
            MediaStorage::disk()->delete((string) $target['path']);
        }

        return response()->json([
            'message' => 'مدیا حذف شد.',
            'page' => $this->pagePayload(),
        ]);
    }

    private function storageDisk(): string
    {
        $downloads = (array) config('filesystems.disks.downloads', []);

        if (
            filled($downloads['url'] ?? null)
            && filled($downloads['host'] ?? null)
            && filled($downloads['username'] ?? null)
        ) {
            return 'downloads';
        }

        return 'public';
    }

    /** @return array<string, mixed> */
    private function pagePayload(): array
    {
        $page = AndroidAppPage::current();

        return [
            ...collect($page)->except('media')->all(),
            'media' => collect($page['media'] ?? [])
                ->filter(fn ($item) => is_array($item) && filled($item['path'] ?? null))
                ->map(fn (array $item) => [
                    'id' => (string) ($item['id'] ?? ''),
                    'type' => ($item['type'] ?? 'image') === 'video' ? 'video' : 'image',
                    'url' => MediaStorage::url((string) $item['path']),
                    'alt' => (string) ($item['alt'] ?? ''),
                    'caption' => (string) ($item['caption'] ?? ''),
                ])
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function payload(AndroidRelease $release): array
    {
        return [
            'id' => $release->id,
            'version' => $release->version,
            'version_code' => $release->version_code,
            'file_name' => $release->file_name,
            'file_size' => $release->file_size,
            'checksum_sha256' => $release->checksum_sha256,
            'release_notes' => $release->release_notes,
            'is_active' => $release->is_active,
            'released_by_name' => $release->releasedBy?->name,
            'released_at' => $release->released_at?->toISOString(),
            'download_url' => $release->directUrl(),
        ];
    }
}
