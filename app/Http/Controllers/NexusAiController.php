<?php

namespace App\Http\Controllers;

use App\Services\NexusAiSettings;
use App\Support\Seo;
use Inertia\Inertia;
use Inertia\Response;

class NexusAiController extends Controller
{
    public function __invoke(NexusAiSettings $settings): Response
    {
        $config = $settings->publicConfig();

        abort_unless($config['enabled'] && $config['page_enabled'], 404);

        $siteName = (string) config('seo.site_name', 'PlayNexus');
        $locale = (string) config('seo.locale', 'fa-IR');
        $canonical = route('nexus-ai.index');
        $title = 'Nexus AI | دستیار هوش مصنوعی گیمینگ پلی نکسوس';
        $description = 'با Nexus AI درباره بازی‌ها، انتخاب بازی، لور، باس‌ها، بیلد، پرفورمنس و دنیای گیم گفتگو کن؛ دستیار گیمینگ هوشمند PlayNexus با پاسخ فارسی و کانتکست زنده سایت.';

        return Inertia::render('NexusAi/Index', [
            ...Seo::page([
                'title' => $title,
                'description' => $description,
                'canonical' => $canonical,
                'robots' => 'index, follow, max-image-preview:large, max-snippet:-1',
                'type' => 'website',
                'siteName' => $siteName,
                'locale' => $locale,
                'image' => url((string) config('seo.default_image', '/logo.png')),
                'imageAlt' => 'Nexus AI دستیار گیمینگ پلی نکسوس',
                'structuredData' => [
                    '@context' => 'https://schema.org',
                    '@graph' => [
                        [
                            '@type' => 'WebPage',
                            '@id' => $canonical.'#webpage',
                            'url' => $canonical,
                            'name' => $title,
                            'description' => $description,
                            'inLanguage' => $locale,
                            'isPartOf' => [
                                '@type' => 'WebSite',
                                'name' => $siteName,
                                'url' => route('home'),
                            ],
                            'mainEntity' => [
                                '@id' => $canonical.'#app',
                            ],
                        ],
                        [
                            '@type' => 'WebApplication',
                            '@id' => $canonical.'#app',
                            'name' => 'Nexus AI',
                            'url' => $canonical,
                            'applicationCategory' => 'GameApplication',
                            'operatingSystem' => 'Web',
                            'description' => $description,
                            'inLanguage' => $locale,
                            'isAccessibleForFree' => true,
                            'publisher' => [
                                '@type' => 'Organization',
                                'name' => $siteName,
                                'url' => route('home'),
                            ],
                        ],
                        [
                            '@type' => 'BreadcrumbList',
                            '@id' => $canonical.'#breadcrumb',
                            'itemListElement' => [
                                [
                                    '@type' => 'ListItem',
                                    'position' => 1,
                                    'name' => 'خانه',
                                    'item' => route('home'),
                                ],
                                [
                                    '@type' => 'ListItem',
                                    'position' => 2,
                                    'name' => 'Nexus AI',
                                    'item' => $canonical,
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
            'nexusAi' => $config,
        ]);
    }
}
