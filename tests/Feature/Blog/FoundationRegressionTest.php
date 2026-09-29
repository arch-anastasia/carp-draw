<?php

namespace Tests\Feature\Blog;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FoundationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_can_clear_cover_alt_without_removing_cover(): void
    {
        config(['blog.api.token' => 'review-only']);
        $media = Media::factory()->create(['url' => '/storage/review.webp', 'alt' => 'Old description']);
        $post = Post::factory()->create(['published_at' => null, 'cover_media_id' => $media->id]);
        $this->withToken('review-only')->patchJson('/api/v1/blog/posts/'.$post->id, ['cover_image_alt' => null])->assertOk();
        $this->assertNull($post->fresh()->cover->alt);
    }

    public function test_scheduled_publication_announces_when_it_becomes_public(): void
    {
        $this->freezeTime();
        Queue::fake();
        config(['blog.indexnow.enabled' => true, 'blog.indexnow.key' => 'review-key']);
        $post = Post::factory()->create(['published_at' => now()->addMinute()]);
        $this->travel(2)->minutes();
        $this->get($post->url())->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertSee($post->url());
        Queue::assertPushed(SubmitPostToIndexNow::class, fn ($job) => $job->delay->equalTo($post->published_at));
        Http::fake(['api.indexnow.org/*' => Http::response([], 200)]);
        (new SubmitPostToIndexNow($post->url()))->handle();
        Http::assertSentCount(1);
    }

    public function test_failed_image_storage_does_not_report_created(): void
    {
        config(['blog.api.token' => 'review-only', 'filesystems.runtime_images' => 'public']);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('url')->andReturnUsing(fn ($path) => 'http://localhost/storage/'.$path);
        $disk->shouldReceive('exists')->andReturn(false);
        Storage::shouldReceive('disk')->with('public')->andReturn($disk);
        $response = $this->withToken('review-only')->post('/api/v1/blog/images', [
            'file' => UploadedFile::fake()->image('chair.png', 20, 20),
            'alt' => 'Oak chair',
        ]);
        $response->assertServerError();
        $this->assertDatabaseCount('media', 0);
    }

    public function test_obsolete_scheduled_jobs_skip_postponed_and_killed_posts(): void
    {
        Queue::fake();
        Http::fake();
        config(['blog.indexnow.enabled' => true, 'blog.indexnow.key' => 'review-key']);
        $post = Post::factory()->create(['published_at' => now()->addHour()]);
        $job = new SubmitPostToIndexNow($post->url());
        $job->handle();
        $post->update(['killed_at' => now()]);
        $job->handle();
        Http::assertNothingSent();
    }
}
