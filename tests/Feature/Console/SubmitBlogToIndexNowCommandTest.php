<?php

namespace Tests\Feature\Console;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SubmitBlogToIndexNowCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Post::query()->delete();
        config()->set('blog.indexnow.key', 'test-key');
        config()->set('blog.indexnow.enabled', true);
    }

    public function test_it_dispatches_one_job_per_published_post(): void
    {
        $published = Post::factory()->count(2)->create();
        Post::factory()->draft()->create();
        Post::factory()->scheduled()->create();

        $this->artisan('blog:indexnow')
            ->expectsOutputToContain('Queued 2 URL(s).')
            ->assertSuccessful();

        Queue::assertPushed(SubmitPostToIndexNow::class, 2);

        foreach ($published as $post) {
            Queue::assertPushed(
                SubmitPostToIndexNow::class,
                fn (SubmitPostToIndexNow $job): bool => $job->url === $post->url(),
            );
        }
    }

    public function test_explicit_slugs_dispatch_only_the_requested_posts(): void
    {
        $wanted = Post::factory()->count(2)->create();
        Post::factory()->create();

        $this->artisan('blog:indexnow', ['slugs' => $wanted->pluck('slug')->all()])
            ->assertSuccessful();

        Queue::assertPushed(SubmitPostToIndexNow::class, 2);
    }

    public function test_dry_run_prints_urls_without_dispatching(): void
    {
        $post = Post::factory()->create();

        config()->set('blog.indexnow.key', null);
        config()->set('blog.indexnow.enabled', false);

        $this->artisan('blog:indexnow', ['--dry-run' => true])
            ->expectsOutput($post->url())
            ->expectsOutput('Would queue 1 URL(s).')
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_missing_key_fails_without_dispatching(): void
    {
        Post::factory()->create();
        config()->set('blog.indexnow.key', null);
        config()->set('blog.indexnow.enabled', false);

        $this->artisan('blog:indexnow')
            ->expectsOutput('INDEXNOW_KEY is not set. No URLs were queued.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }

    public function test_unknown_or_unpublished_slug_fails_without_dispatching(): void
    {
        $published = Post::factory()->create();
        $draft = Post::factory()->draft()->create();

        $this->artisan('blog:indexnow', [
            'slugs' => [$published->slug, $draft->slug, 'missing-post'],
        ])
            ->expectsOutput("Post [{$draft->slug}] is unknown or unpublished.")
            ->expectsOutput('Post [missing-post] is unknown or unpublished.')
            ->assertFailed();

        Queue::assertNothingPushed();
    }
}
