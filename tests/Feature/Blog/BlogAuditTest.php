<?php

namespace Tests\Feature\Blog;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Author;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;
use App\Models\User;
use App\Services\BlogContent;
use App\Services\HouseAuthor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BlogAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['blog.api.token' => 'audit-test-token']);
    }

    public function test_author_schema_and_links_match_profile_visibility(): void
    {
        $author = Author::factory()->create(['profile_is_published' => false]);
        $post = Post::factory()->create();
        $post->authors()->sync([$author->id => ['sort_order' => 0]]);
        $this->get($post->url())->assertOk()->assertSee($author->name)
            ->assertDontSee($author->authorUrl(), false);
        $author->update(['profile_is_published' => true]);
        $this->get($author->authorUrl())->assertOk()
            ->assertSee('"mainEntity":{"@type":"Person"', false);
        $this->get($post->url())->assertOk()->assertSee($author->authorUrl(), false);
    }

    public function test_inline_cover_alt_text_is_saved_and_can_be_cleared(): void
    {
        $post = Post::factory()->create(['published_at' => null]);
        $admin = User::factory()->admin()->create();
        foreach (['Oak chair with a curved back', ''] as $alt) {
            $this->actingAs($admin)->patchJson('/admin/posts/'.$post->id.'/inline', [
                'cover_image_path' => '/storage/blog/chair.webp',
                'cover_image_alt' => $alt,
            ])->assertOk();
            $this->assertSame($alt ?: null, $post->fresh()->cover->alt);
        }
    }

    public function test_unsafe_slugs_are_rejected_by_the_api(): void
    {
        foreach (['two/parts', 'query?x=1', 'fragment#x', 'UPPERCASE', 'authors', 'trailing-'] as $slug) {
            $this->withToken('audit-test-token')->postJson('/api/v1/blog/posts', [
                'title' => 'A chair guide', 'body' => 'Draft content.', 'slug' => $slug,
            ])->assertUnprocessable()->assertJsonValidationErrors('slug');
        }
        $this->assertDatabaseCount('posts', 0);
    }

    public function test_renaming_a_published_article_preserves_direct_redirects_and_reserves_old_urls(): void
    {
        $post = Post::factory()->create(['slug' => 'first-chair-guide']);
        $post->update(['slug' => 'second-chair-guide']);
        $post->update(['slug' => 'final-chair-guide']);
        foreach (['first-chair-guide', 'second-chair-guide'] as $slug) {
            $this->get('/blog/'.$slug)->assertStatus(301)->assertRedirect($post->url());
            $this->assertTrue(Category::blogSlugTaken($slug));
        }
        $this->get('/sitemap.xml')->assertSee($post->url())->assertDontSee('/blog/first-chair-guide');
        $post->update(['published_at' => null]);
        $this->get('/blog/first-chair-guide')->assertNotFound();
        $post->update(['killed_at' => now()]);
        $this->get('/blog/first-chair-guide')->assertGone();
    }

    public function test_draft_slug_changes_do_not_create_public_redirects(): void
    {
        $post = Post::factory()->create(['published_at' => null, 'slug' => 'draft-old']);
        $post->update(['slug' => 'draft-new']);
        $this->get('/blog/draft-old')->assertNotFound();
        $this->assertFalse(Post::blogSlugTaken('draft-old'));
    }

    public function test_api_round_trips_the_editorial_date_without_changing_it_on_partial_updates(): void
    {
        $post = Post::factory()->create(['published_at' => null]);
        $url = '/api/v1/blog/posts/'.$post->slug;
        $this->withToken('audit-test-token')->patchJson($url, ['dated_at' => '2026-08-10T09:00:00Z'])
            ->assertOk()->assertJsonPath('data.dated_at', '2026-08-10T09:00:00+00:00');
        $this->withToken('audit-test-token')->patchJson($url, ['title' => 'Updated title'])
            ->assertOk()->assertJsonPath('data.dated_at', '2026-08-10T09:00:00+00:00');
        $this->withToken('audit-test-token')->patchJson($url, ['dated_at' => null])
            ->assertOk()->assertJsonPath('data.dated_at', null);
        $this->withToken('audit-test-token')->patchJson($url, ['dated_at' => 'not-a-date'])
            ->assertUnprocessable();
    }

    public function test_creating_a_live_post_announces_it_once_and_editing_does_not(): void
    {
        Queue::fake();
        config(['blog.indexnow.enabled' => true, 'blog.indexnow.key' => 'audit-key']);
        $post = Post::factory()->create();
        Queue::assertPushed(SubmitPostToIndexNow::class, 1);
        $post->update(['title' => 'Updated chair guide']);
        Queue::assertPushed(SubmitPostToIndexNow::class, 1);
    }

    public function test_stale_indexnow_jobs_do_not_submit_unpublished_or_missing_articles(): void
    {
        Http::fake();
        config(['blog.indexnow.key' => 'audit-key']);
        $post = Post::factory()->create(['published_at' => null]);
        (new SubmitPostToIndexNow($post->url()))->handle();
        (new SubmitPostToIndexNow(url('/blog/nonexistent')))->handle();
        Http::assertNothingSent();
    }

    public function test_publish_time_boundary_matches_public_queries(): void
    {
        $this->freezeTime();
        $post = Post::factory()->create(['published_at' => now()]);
        $this->assertTrue($post->isPublished());
        $this->get($post->url())->assertOk();
    }

    public function test_inline_images_use_known_media_dimensions_and_never_invent_them(): void
    {
        Media::factory()->create(['url' => 'https://cdn.example/chair.webp', 'width' => 640, 'height' => 960]);
        $html = app(BlogContent::class)->render('![A chair](https://cdn.example/chair.webp)');
        $this->assertStringContainsString('width="640" height="960"', $html);
        $unknown = app(BlogContent::class)->render('![A diagram](https://cdn.example/unknown.webp)');
        $this->assertStringNotContainsString('width="1200"', $unknown);
    }

    public function test_journal_structured_data_lists_only_visible_articles_in_visible_order(): void
    {
        $first = Post::factory()->create(['title' => 'Featured chair guide', 'published_at' => now()->subDays(5)]);
        $second = Post::factory()->create(['title' => 'Latest chair guide', 'published_at' => now()->subDay()]);
        $draft = Post::factory()->create(['published_at' => null]);
        config(['blog.start_here' => [$first->slug]]);
        $html = $this->get('/blog')->assertOk()->getContent();
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
        $nodes = array_map(fn ($json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
        $list = collect($nodes)->first(fn ($node) => isset($node['mainEntity']['itemListElement']))['mainEntity'] ?? null;
        $this->assertNotNull($list);
        $this->assertSame([$first->url(), $second->url()], array_column($list['itemListElement'], 'url'));
        $this->assertStringNotContainsString($draft->url(), $html);
    }

    public function test_malformed_api_fields_and_filters_return_validation_errors(): void
    {
        foreach (['slug', 'body', 'published_at'] as $field) {
            $this->withToken('audit-test-token')->postJson('/api/v1/blog/posts', array_replace([
                'title' => 'A chair guide', 'body' => 'Draft content.',
            ], [$field => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->withToken('audit-test-token')->getJson('/api/v1/blog/posts?search[]=invalid')
            ->assertUnprocessable()->assertJsonValidationErrors('search');
        $this->withToken('audit-test-token')->getJson('/api/v1/blog/posts?status=unknown')
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_failed_api_relation_save_rolls_back_article_and_media_changes(): void
    {
        $post = Post::factory()->create(['published_at' => null]);
        $post->authors()->detach();
        $house = \Mockery::mock(HouseAuthor::class);
        $house->shouldReceive('resolve')->andThrow(new \RuntimeException('Simulated relation failure'));
        $this->app->instance(HouseAuthor::class, $house);
        $this->withToken('audit-test-token')->postJson('/api/v1/blog/posts', [
            'title' => 'Atomic draft', 'body' => 'Draft content.', 'cover_image' => '/storage/atomic.webp',
        ])->assertServerError();
        $this->assertDatabaseMissing('posts', ['title' => 'Atomic draft']);
        $this->assertDatabaseMissing('media', ['url' => '/storage/atomic.webp']);

        $title = $post->title;
        $this->withToken('audit-test-token')->patchJson('/api/v1/blog/posts/'.$post->slug, [
            'title' => 'Must roll back', 'cover_image' => '/storage/atomic-update.webp',
        ])->assertServerError();
        $this->assertSame($title, $post->fresh()->title);
        $this->assertDatabaseMissing('media', ['url' => '/storage/atomic-update.webp']);
    }

    public function test_a_retired_author_cannot_be_silently_accepted_as_a_byline(): void
    {
        $author = Author::factory()->create();
        $author->delete();
        $this->withToken('audit-test-token')->postJson('/api/v1/blog/posts', [
            'title' => 'Draft', 'body' => 'Draft content.', 'authors' => [$author->username],
        ])->assertUnprocessable()->assertJsonValidationErrors('authors.0');
    }

    public function test_a_post_can_restore_its_own_old_slug_without_a_redirect_loop(): void
    {
        $post = Post::factory()->create(['slug' => 'original-chair']);
        $post->update(['slug' => 'revised-chair']);
        $this->assertFalse(Post::blogSlugTaken('original-chair', $post->id));
        $post->update(['slug' => 'original-chair']);
        $this->get('/blog/original-chair')->assertOk();
        $this->get('/blog/revised-chair')->assertStatus(301)->assertRedirect($post->url());
    }
}
