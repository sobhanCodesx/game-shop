<?php

namespace App\Services\NexusAi;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NexusAiLiveWebSearchService
{
    private const SEARCH_MODEL = 'openai/gpt-5-mini';

    public function __construct(private readonly NexusAiProviderSettings $providers) {}

    /**
     * Decide whether a query is likely to depend on information that can change
     * after the model's training cutoff.
     */
    public function shouldSearch(string $message, string $intent): bool
    {
        $q = mb_strtolower(trim($message));

        if ($q === '' || $this->isPlayNexusSpecific($q)) {
            return false;
        }

        if (in_array($intent, ['release', 'news'], true)) {
            return true;
        }

        if ($intent === 'purchase_intent' && $this->has($q, [
            'قیمت', 'تخفیف', 'آفر', 'sale', 'discount', 'price',
            'موجود', 'موجودی', 'pre-order', 'preorder', 'پیش خرید', 'پیش‌خرید',
        ])) {
            return true;
        }

        if ($intent === 'platform') {
            return true;
        }

        if ($intent === 'performance' && $this->has($q, [
            'الان', 'آپدیت', 'پچ', 'نسخه', 'latest', 'current', 'update', 'patch',
            'performance mode', 'quality mode', 'fps', 'فریم', 'رزولوشن',
        ])) {
            return true;
        }

        return $this->has($q, [
            'الان', 'همین الان', 'امروز', 'دیروز', 'این هفته', 'این ماه', 'امسال',
            'فعلاً', 'فعلا', 'تا الان', 'جدیدترین', 'آخرین', 'آخرین خبر',
            'آخرین آپدیت', 'آپدیت جدید', 'پچ جدید', 'نسخه جدید', 'آخرین نسخه',
            'ورژن آخر', 'تاریخ انتشار', 'تاریخ عرضه', 'تاریخش', 'کی میاد',
            'کی عرضه', 'چند روز مونده', 'چقدر مونده', 'تاخیر', 'تأخیر', 'به تعویق',
            'رویداد بعدی', 'فصل جدید', 'سیزن جدید', 'dlc جدید', 'دی ال سی جدید',
            'رودمپ', 'سرورها', 'وضعیت سرور', 'آفلاین شده', 'آنلاین شده',
            'تعداد پلیر', 'سیستم مورد نیاز', 'پیش خرید', 'پیش‌خرید', 'قیمت فعلی',
            'قیمت الان', 'گیم پس', 'game pass', 'ps plus', 'پلی استیشن پلاس',
            'حجم بازی', 'حجم دانلود', 'حجم نصب', 'install size', 'download size',
            'امتیاز متاکریتیک', 'metacritic', 'steam reviews', 'امتیاز استیم',
            'فروش بازی', 'نسخه فروخته', 'copies sold', 'sales figures',
            'تعطیل شد', 'خریداری شد', 'اخراج', 'layoff', 'acquired', 'studio closed',
            'today', 'yesterday', 'this week', 'this month', 'this year',
            'right now', 'currently', 'current ', 'latest', 'newest', 'recent',
            'release date', 'release window', 'delay', 'delayed', 'postponed',
            'new update', 'latest update', 'new patch', 'latest patch',
            'roadmap', 'server status', 'player count', 'system requirements',
            'pre-order', 'preorder',
        ]);
    }

    /**
     * @return array{required:bool,verified:bool,context:string,sources:array<int,array{title:string,url:string}>,model:?string,error:?string}
     */
    public function resolve(string $message, string $intent): array
    {
        $required = $this->shouldSearch($message, $intent);

        if (! $required) {
            return [
                'required' => false,
                'verified' => false,
                'context' => '',
                'sources' => [],
                'model' => null,
                'error' => null,
            ];
        }

        try {
            $settings = $this->providers
                ->resolved(NexusAiProviderSettings::CLOUDFLARE_WORKERS_AI);

            $accountId = trim((string) ($settings['account_id'] ?? ''));
            $apiToken = trim((string) ($settings['api_token'] ?? ''));
            $gatewayId = trim((string) ($settings['gateway_id'] ?? ''));

            if ($accountId === '' || $apiToken === '') {
                return $this->failed('cloudflare_search_not_configured');
            }

            $request = Http::acceptJson()
                ->asJson()
                ->withToken($apiToken)
                ->withHeaders([
                    'cf-aig-skip-cache' => 'true',
                    'cf-aig-collect-log' => 'true',
                ])
                ->connectTimeout(5)
                ->timeout(35);

            if ($gatewayId !== '') {
                $request = $request->withHeaders(['cf-aig-gateway-id' => $gatewayId]);
            }

            $response = $request->post(
                'https://api.cloudflare.com/client/v4/accounts/'.rawurlencode($accountId).'/ai/v1/responses',
                [
                    'model' => self::SEARCH_MODEL,
                    'input' => $this->researchPrompt($message),
                    'max_output_tokens' => 900,
                    'tools' => [
                        ['type' => 'web_search_preview'],
                    ],
                ],
            );

            if (! $response->successful()) {
                Log::warning('Nexus AI live web search failed.', [
                    'status' => $response->status(),
                    'error' => $this->responseError($response),
                ]);

                return $this->failed('web_search_http_'.$response->status());
            }

            [$text, $sources] = $this->extractSearchResult((array) $response->json());

            if ($text === '') {
                return $this->failed('web_search_empty');
            }

            $sourceLines = collect($sources)
                ->map(fn (array $source): string => '- '.($source['title'] !== '' ? $source['title'].': ' : '').$source['url'])
                ->implode("\n");

            $context = 'LIVE WEB RESEARCH — checked '.now()->toIso8601String()."\n"
                .$text
                .($sourceLines !== '' ? "\n\nSOURCES:\n".$sourceLines : '');

            return [
                'required' => true,
                'verified' => true,
                'context' => $context,
                'sources' => $sources,
                'model' => self::SEARCH_MODEL,
                'error' => null,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return $this->failed('web_search_exception');
        }
    }

    public function unavailableAnswer(string $message): string
    {
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message) === 1) {
            return 'این سؤال به اطلاعات به‌روز نیاز داره و الان نتونستم منبع زنده رو با اطمینان بررسی کنم. نمی‌خوام با اطلاعات قدیمی یا حدسی جوابت بدم؛ چند لحظه دیگه دوباره امتحان کن.';
        }

        return 'This question needs up-to-date information, and I could not verify a live source right now. I do not want to answer from stale or guessed information; please try again in a moment.';
    }

    private function researchPrompt(string $message): string
    {
        return implode("\n", [
            'You are the live-facts research layer for a gaming assistant.',
            'Current date: '.now()->toDateString().'.',
            'Search the public web for the CURRENT facts needed to answer the user.',
            'Prefer first-party and official sources: publisher, developer, platform holder, storefront, support/status page, or official social/newsroom.',
            'When official information conflicts with older articles, use the newest official information.',
            'Use exact calendar dates when the query is date-sensitive.',
            'Do not fill gaps from training-memory. If a current detail cannot be verified, say that in the research brief.',
            'Return a compact evidence brief suitable for another model to answer from. Include source URLs when available.',
            '',
            'USER QUESTION:',
            $message,
        ]);
    }

    /**
     * @return array{0:string,1:array<int,array{title:string,url:string}>}
     */
    private function extractSearchResult(array $payload): array
    {
        $payload = is_array($payload['result'] ?? null)
            ? $payload['result']
            : $payload;

        $text = trim((string) ($payload['output_text'] ?? ''));
        $sources = [];

        foreach ((array) ($payload['output'] ?? []) as $output) {
            foreach ((array) ($output['content'] ?? []) as $content) {
                if (($content['type'] ?? null) === 'output_text' && filled($content['text'] ?? null)) {
                    if ($text === '') {
                        $text = trim((string) $content['text']);
                    }

                    foreach ((array) ($content['annotations'] ?? []) as $annotation) {
                        if (($annotation['type'] ?? null) !== 'url_citation') {
                            continue;
                        }

                        $url = trim((string) ($annotation['url'] ?? ''));
                        if ($url === '') {
                            continue;
                        }

                        $sources[$url] = [
                            'title' => trim((string) ($annotation['title'] ?? '')),
                            'url' => $url,
                        ];
                    }
                }
            }
        }

        return [$text, array_values($sources)];
    }

    private function responseError(Response $response): ?string
    {
        $json = (array) $response->json();

        return data_get($json, 'errors.0.message')
            ?? data_get($json, 'error.message')
            ?? data_get($json, 'result.error.message');
    }

    /**
     * @return array{required:bool,verified:bool,context:string,sources:array<int,array{title:string,url:string}>,model:?string,error:string}
     */
    private function failed(string $error): array
    {
        return [
            'required' => true,
            'verified' => false,
            'context' => '',
            'sources' => [],
            'model' => self::SEARCH_MODEL,
            'error' => $error,
        ];
    }

    private function isPlayNexusSpecific(string $q): bool
    {
        return $this->has($q, [
            'playnexus', 'play nexus', 'پلی نکسوس', 'پلی‌نکسوس',
            'توی سایت', 'تو سایت', 'سایت شما', 'فروشگاه شما', 'داخل سایت',
            'قیمت تو سایت', 'موجودی سایت',
        ]);
    }

    private function has(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
