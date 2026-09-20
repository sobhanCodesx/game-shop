<?php

namespace App\Http\Controllers;

use App\Models\AndroidAppPage;
use App\Models\AndroidRelease;
use App\Services\MediaStorage;
use App\Support\Seo;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AndroidAppPageController extends Controller
{
    public function __invoke(): Response
    {
        $page = AndroidAppPage::current();
        $releases = AndroidRelease::query()
            ->orderByDesc('version_code')
            ->limit(12)
            ->get();

        $latest = $releases->firstWhere('is_active', true) ?? $releases->first();
        $media = collect($page['media'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['path'] ?? null))
            ->map(function (array $item): array {
                return [
                    'id' => (string) ($item['id'] ?? ''),
                    'type' => ($item['type'] ?? 'image') === 'video' ? 'video' : 'image',
                    'url' => MediaStorage::url((string) $item['path']),
                    'alt' => (string) ($item['alt'] ?? 'اپلیکیشن اندروید پلی نکسوس'),
                    'caption' => (string) ($item['caption'] ?? ''),
                ];
            })
            ->values();

        $firstImage = $media->firstWhere('type', 'image');
        $image = $firstImage && filled($firstImage['url'] ?? null)
            ? $this->absoluteUrl((string) $firstImage['url'])
            : url((string) config('seo.default_image', '/logo.png'));

        $canonical = route('android.app');
        $downloadUrl = route('android.apk.download');
        $description = Str::limit((string) $page['seo_description'], 160, '…');
        $screenshots = $media
            ->where('type', 'image')
            ->pluck('url')
            ->filter()
            ->map(fn (string $url) => $this->absoluteUrl($url))
            ->values()
            ->all();

        $software = array_filter([
            '@type' => 'SoftwareApplication',
            '@id' => $canonical.'#app',
            'name' => 'PlayNexus Android',
            'alternateName' => 'اپلیکیشن اندروید پلی نکسوس',
            'url' => $canonical,
            'downloadUrl' => $downloadUrl,
            'operatingSystem' => 'Android',
            'applicationCategory' => 'EntertainmentApplication',
            'description' => $description,
            'image' => $image,
            'screenshot' => $screenshots !== [] ? $screenshots : null,
            'softwareVersion' => $latest?->version,
            'fileSize' => $latest ? $latest->file_size.' bytes' : null,
            'datePublished' => $latest?->released_at?->toAtomString(),
            'releaseNotes' => filled($latest?->release_notes) ? $latest->release_notes : null,
            'publisher' => [
                '@type' => 'Organization',
                'name' => 'PlayNexus',
                'url' => route('home'),
            ],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);

        return Inertia::render('AndroidApp/Index', [
            ...Seo::page([
                'title' => (string) $page['seo_title'],
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
                'type' => 'website',
                'image' => $image,
                'imageAlt' => 'دانلود اپلیکیشن اندروید پلی نکسوس',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'WebPage',
                            '@id' => $canonical.'#webpage',
                            'url' => $canonical,
                            'name' => (string) $page['seo_title'],
                            'description' => $description,
                            'mainEntity' => ['@id' => $canonical.'#app'],
                            'inLanguage' => (string) config('seo.locale', 'fa-IR'),
                        ],
                        $software,
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                ['@type' => 'ListItem', 'position' => 1, 'name' => 'خانه', 'item' => route('home')],
                                ['@type' => 'ListItem', 'position' => 2, 'name' => 'دانلود نسخه اندروید', 'item' => $canonical],
                            ],
                        ],
                    ],
                ],
            ]),
            'page' => [
                ...collect($page)->except('media')->all(),
                'media' => $media,
            ],
            'latest' => $latest ? [
                'version' => $latest->version,
                'version_code' => $latest->version_code,
                'file_size' => $latest->file_size,
                'checksum_sha256' => $latest->checksum_sha256,
                'release_notes' => $latest->release_notes,
                'released_at' => $latest->released_at?->toISOString(),
                'download_url' => $downloadUrl,
            ] : null,
            'releases' => $releases->map(fn (AndroidRelease $release) => [
                'id' => $release->id,
                'version' => $release->version,
                'version_code' => $release->version_code,
                'file_size' => $release->file_size,
                'release_notes' => $release->release_notes,
                'is_active' => $release->is_active,
                'released_at' => $release->released_at?->toISOString(),
                'download_url' => $release->directUrl(),
            ])->values(),
        ]);
    }

    private function absoluteUrl(string $value): string
    {
        return Str::startsWith($value, ['http://', 'https://']) ? $value : url($value);
    }
}
