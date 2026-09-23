<?php

namespace App\Services\NexusAi;

use App\Services\NexusAiSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class NexusAiRouter
{
    private const COOLDOWN_PREFIX = 'nexus-ai:provider-cooldown:';

    public function __construct(
        private readonly NexusAiProviderSettings $providers,
        private readonly NexusAiProviderClient $client,
        private readonly NexusAiContextService $context,
        private readonly NexusAiKnowledgeService $knowledge,
        private readonly NexusAiIntentClassifier $intent,
        private readonly NexusAiSettings $settings,
    ) {
    }

    public function chat(string $message, array $history): array
    {
        $config = $this->settings->all();
        $autopilot = (bool) ($config['nexus_ai_autopilot'] ?? true);
        $freeFirst = $autopilot || (bool) ($config['nexus_ai_free_first'] ?? true);
        $timeout = $autopilot ? 20 : (int) ($config['nexus_ai_provider_timeout_seconds'] ?? 20);
        $maxTokens = $autopilot ? 1200 : (int) ($config['nexus_ai_max_output_tokens'] ?? 1200);
        $temperature = $autopilot ? 0.65 : (float) ($config['nexus_ai_temperature'] ?? 0.65);

        $contextResult = $this->context->build($message);
        $liveContext = (string) ($contextResult['context'] ?? '');
        $contextTerms = is_array($contextResult['terms'] ?? null) ? $contextResult['terms'] : [];
        $knowledge = $this->knowledge->promptContext();
        $intent = $this->intent->classify($message);
        $messages = $this->messages($message, $history, $liveContext, $knowledge, $config, $intent);
        $attempted = [];

        foreach ($this->providers->ordered($freeFirst, $autopilot) as $provider) {
            $key = $provider['key'];
            if ($this->isCoolingDown($key)) {
                continue;
            }

            $attempted[] = $key;

            try {
                $answer = $this->client->chat(
                    $key,
                    $provider['settings'],
                    $messages,
                    $message,
                    $history,
                    $timeout,
                    $maxTokens,
                    $temperature,
                    $liveContext,
                );

                return [
                    'answer' => $answer,
                    'provider' => $key,
                    'model' => $provider['settings']['model'] ?? null,
                    'intent' => $intent,
                    'context_terms' => $contextTerms,
                    'context_chars' => mb_strlen($liveContext) + mb_strlen($knowledge),
                    'fallback_count' => max(0, count($attempted) - 1),
                ];
            } catch (NexusAiProviderException $exception) {
                $this->coolDown($key, $exception);
                Log::warning('Nexus AI provider failed; falling back.', [
                    'provider' => $key,
                    'status' => $exception->status,
                ]);
            } catch (Throwable $exception) {
                $this->coolDown($key);
                report($exception);
            }
        }

        throw new RuntimeException(
            $attempted === []
                ? 'No configured Nexus AI provider is available.'
                : 'All configured Nexus AI providers failed.',
        );
    }

    public function health(): array
    {
        $config = $this->settings->all();
        $autopilot = (bool) ($config['nexus_ai_autopilot'] ?? true);
        $available = [];

        foreach ($this->providers->ordered(true, $autopilot) as $provider) {
            if (! $this->isCoolingDown($provider['key'])) {
                $available[] = [
                    'key' => $provider['key'],
                    'free_tier' => $provider['free_tier'],
                ];
            }
        }

        return [
            'available' => $available !== [],
            'provider_count' => count($available),
            'autopilot' => $autopilot,
            'providers' => $available,
        ];
    }

    private function messages(
        string $message,
        array $history,
        string $context,
        string $knowledge,
        array $config,
        string $intent,
    ): array {
        $system = <<<'PROMPT'
You are Nexus AI, the gamer-native assistant inside PlayNexus.

PERSONALITY
- When the user writes Persian, use real colloquial Persian from gaming conversations in Iran. Prefer "اگه، می‌خوای، می‌تونی، یه، اینجوری، به نظرم" over formal written Persian such as "اگر می‌خواهید، می‌توانید، یک عنوان، محسوب می‌شود".
- Sound like a close gaming friend who knows games deeply. Warm, relaxed, energetic and opinionated when useful; never sound like customer support, a review article, a press release, or a generic AI.
- Mirror the user's energy. Casual user = very casual answer. Technical user = precise technical answer with the same friendly voice.
- Natural gamer vocabulary is encouraged when relevant: build, boss, open world, FPS, performance mode, NG+, lore, soulslike, grind, patch, endgame and similar terms.
- You may occasionally use friendly words like "رفیق" or "ببین" when natural, but never repeat them mechanically.
- Light emoji use is welcome (for example 🎮🔥) when it fits. Never spam emojis.
- Never start with canned phrases such as "حتماً!" or "به عنوان یک هوش مصنوعی". Jump straight into the answer.
- Do not rewrite the user's request in formal language before answering.
- For recommendations, give your concrete pick in the first sentence, then explain why it matches the user's exact criteria. If there is an important caveat, say it plainly.
- Do not ask a clarification when platform + important preference are already clear.
- Ask at most one focused clarification only when the missing detail would materially change the answer.
- If the user is excited about a game, share that energy naturally. If they dislike something, do not argue; adapt the recommendation.
- Keep the answer sounding like chat, not an article. Short sentences and natural contractions are preferred.

STYLE EXAMPLE
Bad: "اگر می‌خواهید یک عنوان جهان‌باز با گرافیک چشم‌نواز تجربه کنید، Horizon Forbidden West گزینه مناسبی محسوب می‌شود."
Good: "اگه جهان‌باز خوشگل و اتمسفریک می‌خوای، من اول **Horizon Forbidden West** رو می‌ندازم جلوت 🔥 دنیاش برای گشت‌وگذار خیلی حال می‌ده و روی PS5 هم واقعاً چشم‌نوازه."

ACCURACY
- Use PLAYNEXUS LIVE CONTEXT as the authoritative source for PlayNexus catalog, content, products, collections, radar and game relations.
- Never invent PlayNexus prices, availability, products, release states, URLs, or catalog facts.
- Separate confirmed facts from uncertainty. If you do not know a current fact, say so briefly instead of guessing.
- Respect platform constraints exactly; for example, if the user asks for PS5, do not recommend a game unavailable on PS5.
- In recommendations, prefer stable high-confidence traits over unnecessary technical trivia.
- Never invent or casually assert ray tracing support, exact FPS/resolution modes, seasonal updates, patch details, live-service status, prices, dates, or platform features unless PLAYNEXUS LIVE CONTEXT supports them or you are highly confident they are established facts.
- If a technical detail is not needed to answer the user's request, leave it out rather than padding the answer.
- Avoid major story spoilers unless the user explicitly asks for them.

WRITING
- Prefer useful, concrete answers over filler.
- Keep paragraphs short and readable on mobile.
- Use clean Markdown for comparisons, builds, steps and short lists when it genuinely improves readability.
- Do not repeat the user's question back to them.
PROMPT;

        if ($intent === 'recommendation') {
            $system .= "\nCURRENT TASK IS A GAME RECOMMENDATION. Give at least one concrete game recommendation immediately. Do not ask a follow-up question before giving the recommendation. Use the constraints already present in the user's message and make a best-effort pick. You may optionally end with one short refinement question after the recommendation if it would improve a second round.";
        } elseif ((bool) ($config['nexus_ai_clarify_ambiguity'] ?? true)) {
            $system .= "\nIf the user's request is materially ambiguous, ask one focused clarification instead of giving a generic answer.";
        }

        if ((bool) ($config['nexus_ai_spoiler_guard'] ?? true)) {
            $system .= "\nDo not reveal major story spoilers unless the user explicitly asks for spoilers or the question clearly requires them.";
        }

        $style = (string) ($config['nexus_ai_response_style'] ?? 'balanced');
        $system .= match ($style) {
            'concise' => "\nKeep answers concise and high-signal unless the user asks for detail.",
            'detailed' => "\nGive thorough answers with useful nuance, while avoiding repetition.",
            default => "\nGive enough detail to be genuinely useful, but avoid unnecessary repetition.",
        };

        if ($knowledge !== '') {
            $system .= "\n\nPLAYNEXUS ADMIN KNOWLEDGE:\n".$knowledge;
        }

        if ($context !== '') {
            $system .= "\n\n".$context;
        }

        $messages = [['role' => 'system', 'content' => $system]];

        foreach (array_slice($history, -8) as $item) {
            $role = $item['role'] ?? null;
            $content = trim((string) ($item['content'] ?? ''));
            if (! in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }

            $messages[] = ['role' => $role, 'content' => mb_substr($content, 0, 3000)];
        }

        $messages[] = ['role' => 'user', 'content' => $message];

        return $messages;
    }

    private function coolDown(string $provider, ?NexusAiProviderException $exception = null): void
    {
        $seconds = match ($exception?->status) {
            401, 402, 403 => 300,
            429 => max(30, min(3600, $exception->retryAfter ?? 120)),
            500, 502, 503, 504 => 30,
            default => 15,
        };

        try {
            Cache::put(self::COOLDOWN_PREFIX.$provider, true, now()->addSeconds($seconds));
        } catch (Throwable) {
        }
    }

    private function isCoolingDown(string $provider): bool
    {
        try {
            return (bool) Cache::get(self::COOLDOWN_PREFIX.$provider, false);
        } catch (Throwable) {
            return false;
        }
    }
}
