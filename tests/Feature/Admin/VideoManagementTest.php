<?php

namespace Tests\Feature\Admin;

use App\Models\SocialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class VideoManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_video_list_is_paginated(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (range(1, 13) as $index) {
            SocialContent::query()->create([
                'user_id' => $admin->id,
                'type' => 'video',
                'title' => "ویدیوی {$index}",
                'slug' => "video-{$index}",
                'status' => 'draft',
            ]);
        }

        $this->actingAs($admin)->get('/admin/videos?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Videos/Index')
                ->has('videos.data', 1)
                ->where('videos.current_page', 2)
                ->where('videos.last_page', 2)
                ->where('videos.total', 13));
    }

    public function test_video_is_required_when_creating_a_video(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/videos', [
            'title' => 'گیم‌پلی جدید',
            'status' => 'draft',
            'featured' => false,
        ])->assertSessionHasErrors('video');
    }

    public function test_admin_can_upload_a_video(): void
    {
        Storage::fake('public');
        config(['media.video.ffmpeg_binary' => 'missing-ffmpeg']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/videos', [
            'title' => 'گیم‌پلی آزمایشی',
            'excerpt' => '<strong>توضیح کوتاه ویدیو</strong>',
            'body' => '<h2 class="unsafe">راهنمای مرحله اول</h2><blockquote>نکته مهم</blockquote><script>alert(1)</script><a href="javascript:alert(1)">لینک ناامن</a><a href="https://example.com/guide">راهنمای کامل</a>',
            'seo_title' => 'گیم‌پلی آزمایشی | PlayNexus',
            'seo_description' => 'توضیحات اختصاصی نتیجه جستجوی ویدیوی آزمایشی.',
            'status' => 'published',
            'featured' => true,
            'video' => UploadedFile::fake()->create('gameplay.mp4', 512, 'video/mp4'),
            'thumbnail' => UploadedFile::fake()->image('thumbnail.jpg', 640, 360),
            'client_duration' => 90,
        ])->assertRedirect('/admin/videos');

        $video = SocialContent::query()->firstOrFail();
        $this->assertSame('video', $video->type);
        $this->assertSame('published', $video->status);
        $this->assertSame('توضیح کوتاه ویدیو', $video->excerpt);
        $this->assertSame('<h2>راهنمای مرحله اول</h2><blockquote>نکته مهم</blockquote><a>لینک ناامن</a><a href="https://example.com/guide" rel="noopener noreferrer">راهنمای کامل</a>', $video->body);
        $this->assertSame('گیم‌پلی آزمایشی | PlayNexus', $video->seo_title);
        $this->assertSame('توضیحات اختصاصی نتیجه جستجوی ویدیوی آزمایشی.', $video->seo_description);
        $this->assertNotNull($video->published_at);
        $this->assertSame(90, $video->duration);
        Storage::disk('public')->assertExists($video->video_path);
        Storage::disk('public')->assertExists($video->thumbnail);
    }

    public function test_admin_can_create_video_from_resumable_chunked_upload(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);
        $token = fake()->uuid();
        $source = tempnam(sys_get_temp_dir(), 'chunk-video-').'.mp4';
        (new Process([
            (string) config('media.video.ffmpeg_binary'), '-y', '-f', 'lavfi', '-i',
            'testsrc=size=320x180:rate=12', '-t', '0.2', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', $source,
        ]))->setTimeout(60)->mustRun();
        $contents = (string) file_get_contents($source);

        $this->actingAs($admin)->postJson('/admin/uploads/chunk', [
            'upload_id' => $token,
            'chunk_index' => 0,
            'total_chunks' => 1,
            'name' => 'chunked.mp4',
            'mime' => 'video/mp4',
            'size' => strlen($contents),
            'chunk' => UploadedFile::fake()->createWithContent('chunk-0', $contents),
        ])->assertOk()->assertJsonPath('received', 0);

        $this->actingAs($admin)->postJson('/admin/uploads/complete', [
            'upload_id' => $token,
        ])->assertOk()->assertJsonPath('token', $token);

        $this->actingAs($admin)->post('/admin/videos', [
            'title' => 'آپلود چندبخشی',
            'status' => 'draft',
            'featured' => false,
            'upload_token' => $token,
        ])->assertRedirect('/admin/videos');

        $video = SocialContent::query()->where('title', 'آپلود چندبخشی')->firstOrFail();
        Storage::disk('public')->assertExists($video->video_path);
        $this->assertFileDoesNotExist(storage_path("app/private/uploads/{$admin->id}/{$token}/assembled"));
        @unlink($source);
    }

    public function test_regular_user_cannot_access_video_management(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/videos')->assertForbidden();
        $this->actingAs($user)->post('/admin/videos', [])->assertForbidden();
    }

    public function test_public_video_page_receives_the_playable_file_url(): void
    {
        $video = SocialContent::query()->create([
            'type' => 'video',
            'title' => 'ویدیوی منتشر شده',
            'slug' => 'published-video',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'excerpt' => 'خلاصه اختصاصی ویدیوی منتشر شده',
            'body' => '<h2>آنچه در این ویدیو می‌بینید</h2><p>محتوای کامل ویدیو</p>',
            'seo_title' => 'تماشای ویدیوی منتشر شده | PlayNexus',
            'seo_description' => 'توضیحات متای اختصاصی ویدیوی منتشر شده برای نتایج جستجو.',
            'thumbnail' => 'videos/thumbnails/published.jpg',
            'video_path' => 'videos/published.mp4',
            'video_mime' => 'video/mp4',
            'duration' => 125,
        ]);

        $response = $this->get("/videos/{$video->slug}?comment_sort=newest&utm_source=test")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Content/Show')
                ->where('content.video_url', 'http://localhost/storage/videos/published.mp4')
                ->where('content.body', '<h2>آنچه در این ویدیو می‌بینید</h2><p>محتوای کامل ویدیو</p>')
                ->where('seo.title', 'تماشای ویدیوی منتشر شده | پلی نکسوس')
                ->where('seo.description', 'توضیحات متای اختصاصی ویدیوی منتشر شده برای نتایج جستجو.')
                ->where('seo.canonical', 'http://localhost/videos/published-video')
                ->where('seo.type', 'video.other')
                ->where('seo.image', 'http://localhost/storage/videos/thumbnails/published.jpg')
                ->where('seo.video.url', 'http://localhost/storage/videos/published.mp4')
                ->where('seo.video.duration', 125)
                ->where('seo.structuredData.@graph.1.@type', 'VideoObject')
                ->where('seo.structuredData.@graph.1.duration', 'PT2M5S')
                ->where('seo.structuredData.@graph.2.@type', 'BreadcrumbList'));

        $response->assertSee('<link data-inertia="canonical" rel="canonical" href="http://localhost/videos/published-video">', false);
        $response->assertSee('<meta data-inertia="og:video" property="og:video" content="http://localhost/storage/videos/published.mp4">', false);
        $response->assertSee('<script data-inertia="structured-data" type="application/ld+json">', false);
    }
}
