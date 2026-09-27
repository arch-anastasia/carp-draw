<?php

namespace Tests\Feature\Blog;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarpDrawBlogSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_published_articles_are_public_and_in_the_sitemap(): void
    {
        $live = Post::factory()->create(['published_at' => now()->subDay()]);
        $draft = Post::factory()->create(['published_at' => null]);
        $scheduled = Post::factory()->create(['published_at' => now()->addDay()]);
        $killed = Post::factory()->create(['killed_at' => now()]);
        $category = Category::factory()->create();
        $live->categories()->attach($category);

        $this->get($live->url())->assertOk();
        $this->get($draft->url())->assertNotFound();
        $this->get($scheduled->url())->assertNotFound();
        $this->get($killed->url())->assertGone();
        $sitemap = $this->get('/sitemap.xml')->assertOk();
        $sitemap->assertSee($live->url())->assertDontSee($draft->url())
            ->assertDontSee($scheduled->url())->assertDontSee($killed->url())
            ->assertDontSee($category->url())->assertDontSee('/blog/authors')
            ->assertDontSee('/contact')->assertDontSee('/privacy');

        $this->travel(2)->days();
        $this->get($scheduled->url())->assertOk();
        $this->get('/sitemap.xml')->assertSee($scheduled->url());
    }

    public function test_reading_does_not_change_the_editorial_date(): void
    {
        $post = Post::factory()->create(['published_at' => now()->subDays(5), 'updated_at' => now()->subDays(3)]);
        $stamp = $post->updated_at->toIso8601String();
        $before = $this->get('/sitemap.xml')->getContent();
        $this->get($post->url())->assertOk()->assertSee('"dateModified":"'.$stamp.'"', false);
        $this->get($post->url())->assertOk();
        $this->assertSame($stamp, $post->fresh()->updated_at->toIso8601String());
        $this->assertSame(2, $post->fresh()->views_count);
        $this->assertSame($before, $this->get('/sitemap.xml')->getContent());
    }

    public function test_api_previews_are_private_and_do_not_count_readers(): void
    {
        config(['blog.api.token' => 'editorial-test-token']);
        $post = Post::factory()->create(['published_at' => now()->subDay(), 'views_count' => 0]);
        $this->withToken('editorial-test-token')->get('/api/v1/blog/posts/'.$post->slug.'/preview')
            ->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('noindex, follow')->assertSee($post->url());
        $this->assertSame(0, $post->fresh()->views_count);
    }

    public function test_archives_are_noindex_and_articles_carry_matching_structured_data(): void
    {
        $category = Category::factory()->create();
        $author = Author::factory()->create(['profile_is_published' => true]);
        $post = Post::factory()->create([
            'published_at' => now()->subDay(),
            'body' => "## Planning\nMeasure carefully.\n\n:::steps \"Plan a chair\"\n### Measure\nMeasure the seat.\n### Draw\nDraw the chair.\n:::\n\n## FAQ\n### Which units?\nUse millimeters.\n### Verify dimensions?\nAlways measure before manufacture.",
        ]);
        $post->categories()->attach($category);
        $post->authors()->attach($author, ['sort_order' => 0]);
        foreach (['/blog/authors', $author->authorUrl(), $category->url(), '/blog?q=chair', '/blog?q=chair&page=2'] as $url) {
            $this->get($url)->assertOk()->assertSee('noindex, follow');
        }
        $this->get('/blog?page=2')->assertStatus(301)->assertRedirect('/blog');
        $this->get($post->url())->assertOk()
            ->assertSee('"@type":"Article"', false)->assertSee('"@type":"FAQPage"', false)
            ->assertSee('"@type":"HowTo"', false)->assertSee('"@type":"BreadcrumbList"', false)
            ->assertSee('index, follow, max-image-preview:large')->assertSee($post->url());
    }

    public function test_api_preview_canonical_uses_the_configured_host(): void
    {
        config(['blog.api.token' => 'editorial-test-token', 'app.url' => 'https://carpdraw.example']);
        $post = Post::factory()->create(['published_at' => null]);
        $response = $this->withToken('editorial-test-token')
            ->get('https://alias.example/api/v1/blog/posts/'.$post->slug.'/preview')->assertOk();
        $response->assertSee('https://carpdraw.example/blog/'.$post->slug, false)
            ->assertDontSee('https://alias.example/blog/'.$post->slug, false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_editor_and_context_are_carpdraw_specific(): void
    {
        config(['blog.api.token' => 'editorial-test-token']);
        $this->withToken('editorial-test-token')->get('/api/v1/blog/context')->assertOk()
            ->assertJsonPath('rules.faq.minimum_questions', 2)
            ->assertJsonMissingPath('looks')->assertJsonMissingPath('rules.look')
            ->assertDontSee('/onboarding')->assertDontSee('face-ratio');
        $post = Post::factory()->create(['published_at' => null]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get($post->url())->assertOk()
            ->assertSee('data-blog-editor', false)->assertSee('data-blog-editor-body', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->actingAs(User::factory()->create())->get('/admin/posts')->assertForbidden();
    }
}
