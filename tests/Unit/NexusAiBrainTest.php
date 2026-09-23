<?php

namespace Tests\Unit;

use App\Services\NexusAi\NexusAiIdentity;
use App\Services\NexusAi\NexusAiIntentClassifier;
use Tests\TestCase;

class NexusAiBrainTest extends TestCase
{
    public function test_it_classifies_common_gaming_intents(): void
    {
        $classifier = new NexusAiIntentClassifier();

        $this->assertSame('recommendation', $classifier->classify('یه بازی شبیه Hogwarts Legacy معرفی کن'));
        $this->assertSame('recommendation', $classifier->classify('یه بازی جهان باز خفن برای PS5 میخوام، اتمسفر و گرافیک برام مهمه'));
        $this->assertSame('recommendation', $classifier->classify('بعد از Elden Ring چی بزنم؟'));
        $this->assertSame('comparison', $classifier->classify('فرق PS5 و Xbox برای این بازی چیه؟'));
        $this->assertSame('purchase_intent', $classifier->classify('این بازی ارزش خرید داره؟'));
        $this->assertSame('story_lore', $classifier->classify('لور و داستان این شخصیت رو بگو'));
        $this->assertSame('performance', $classifier->classify('روی PS5 چند FPS اجرا میشه؟'));
    }

    public function test_visitor_identity_is_stable_and_does_not_store_raw_identifier(): void
    {
        config()->set('app.key', 'base64:test-secret-key');

        $identity = new NexusAiIdentity();
        $visitorId = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

        $first = $identity->visitorHash($visitorId);
        $second = $identity->visitorHash($visitorId);

        $this->assertSame($first, $second);
        $this->assertNotSame($visitorId, $first);
        $this->assertSame(64, strlen((string) $first));
        $this->assertNull($identity->visitorHash(null));
    }
}
