<?php

namespace Tests\Feature;

use App\Services\AndroidReleaseAgentService;
use RuntimeException;
use Tests\TestCase;

class AndroidReleaseAgentServiceTest extends TestCase
{
    public function test_android_release_agent_requires_upload_permission(): void
    {
        config()->set('content_agent.allow_uploads', false);
        config()->set('content_agent.allow_publish', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Android release uploads are disabled');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://media.githubusercontent.com/media/example/repo/main/app.apk',
        ]);
    }

    public function test_android_release_agent_requires_publish_permission(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Android release publishing is disabled');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://media.githubusercontent.com/media/example/repo/main/app.apk',
        ]);
    }

    public function test_android_release_agent_rejects_non_allowlisted_remote_host(): void
    {
        config()->set('content_agent.allow_uploads', true);
        config()->set('content_agent.allow_publish', true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('میزبان امن GitHub');

        app(AndroidReleaseAgentService::class)->publish([
            'source_url' => 'https://example.com/PlayNexus.apk',
            'release_notes' => 'Should never be downloaded.',
        ]);
    }
}
