<?php

namespace Tests\Feature\Blog;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The token-authenticated write API at /api/v1/blog.
 */
class BlogApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-blog-api-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('blog.api.token', self::TOKEN);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call_api(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->withToken(self::TOKEN)->json($method, $uri, $payload);
    }

    /**
     * A body the FAQ rule accepts, so a test about publishing is about
     * publishing rather than about the FAQ.
     */
    private function bodyWithFaq(string $prose = 'Some copy about a haircut.'): string
    {
        return <<<MARKDOWN
        ## A heading

        {$prose}

        ## FAQ

        ### Does this need a FAQ?

        Yes, every published article renders one as FAQPage schema.

        ### How many questions?

        At least two.
        MARKDOWN;
    }

    /*
    |---------------------------------------------------------------------------
    | The door
    |---------------------------------------------------------------------------
    */

    public function test_a_request_without_a_token_is_refused(): void
    {
        $this->json('GET', '/api/v1/blog/posts')
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_a_request_with_the_wrong_token_is_refused(): void
    {
        $this->withToken('not-the-token')->json('GET', '/api/v1/blog/posts')
            ->assertStatus(401);
    }

    /**
     * The failure mode that matters: a server that never set the variable must
     * close the API, not open it.
     */
    public function test_an_unconfigured_token_closes_the_api_rather_than_opening_it(): void
    {
        config()->set('blog.api.token', '');

        $this->json('GET', '/api/v1/blog/posts')->assertStatus(503);
        $this->withToken('')->json('POST', '/api/v1/blog/posts', [])->assertStatus(503);
        $this->withToken(self::TOKEN)->json('GET', '/api/v1/blog/posts')->assertStatus(503);

        $this->assertSame(0, Post::query()->where('title', '')->count());
    }

    public function test_errors_come_back_as_json_not_as_a_rendered_error_page(): void
    {
        $response = $this->call_api('GET', '/api/v1/blog/posts/no-such-article');

        $response->assertStatus(404);
        $this->assertJson($response->getContent() ?: '');
    }

    /*
    |---------------------------------------------------------------------------
    | Context
    |---------------------------------------------------------------------------
    */

    public function test_the_context_endpoint_publishes_the_vocabulary_a_writer_needs(): void
    {
        $category = Category::factory()->create(['name' => 'Fades', 'slug' => 'fades']);
        $author = Author::factory()->create(['username' => 'writer']);

        $response = $this->call_api('GET', '/api/v1/blog/context')->assertOk();

        $response->assertJsonPath('rules.faq.minimum_questions', 2);
        $this->assertContains('fades', array_column($response->json('categories'), 'slug'));
        $this->assertContains($author->username, array_column($response->json('authors'), 'username'));
        $this->assertNotEmpty($response->json('body.directives'));
        $this->assertNotEmpty($response->json('endpoints'));

        $this->assertSame('Fades', collect($response->json('categories'))->firstWhere('slug', 'fades')['name']);
        $this->assertSame($category->slug, 'fades');
    }

    /*
    |---------------------------------------------------------------------------
    | Create
    |---------------------------------------------------------------------------
    */

    public function test_a_post_is_created_as_a_draft_when_no_publish_date_is_sent(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'A Guide With No Date',
            'body' => 'Half written, no FAQ yet.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'a-guide-with-no-date')
            ->assertJsonPath('data.published_at', null);

        $post = Post::query()->where('slug', 'a-guide-with-no-date')->firstOrFail();

        $this->assertNull($post->published_at);

        /*
         * As a stranger, not as the caller that just created it. call_api()
         * leaves the bearer token on the default headers, and the token now
         * grants preview access to unpublished articles - see
         * App\Services\PreviewAccess - so without this the assertion would be
         * checking the wrong reader.
         */
        $this->flushHeaders();
        $this->get('/blog/'.$post->slug)->assertNotFound();
    }

    public function test_keyword_fields_round_trip_through_create_and_update(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Keyword Ownership',
            'body' => 'Draft copy.',
            'target_keyword' => 'best taper fade',
            'secondary_keywords' => ['low taper fade', 'taper fade guide'],
        ])->assertCreated();

        $response->assertJsonPath('data.target_keyword', 'best taper fade')
            ->assertJsonPath('data.secondary_keywords.1', 'taper fade guide');

        $slug = $response->json('data.slug');

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$slug, [
            'target_keyword' => 'best fade haircut',
            'secondary_keywords' => ['fade haircut guide'],
        ])->assertOk()
            ->assertJsonPath('data.target_keyword', 'best fade haircut')
            ->assertJsonPath('data.secondary_keywords.0', 'fade haircut guide');

        $post = Post::query()->where('slug', $slug)->firstOrFail();
        $this->assertSame(['fade haircut guide'], $post->secondary_keywords);
    }

    public function test_a_draft_preview_uses_the_article_renderer_and_requires_the_token(): void
    {
        $post = Post::factory()->draft()->create([
            'title' => 'A Draft Preview',
            'body' => '## Preview heading\n\nDraft-only prose.',
        ]);

        $this->get('/api/v1/blog/posts/'.$post->slug.'/preview')->assertUnauthorized();

        $this->withToken(self::TOKEN)
            ->get('/api/v1/blog/posts/'.$post->slug.'/preview')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('A Draft Preview')
            ->assertSee('Draft-only prose.');

        /*
         * As a reader, not as the caller above. `withToken()` sets a default
         * header for the rest of the test, and since App\Services\PreviewAccess
         * landed, a token holder is allowed to preview an unpublished article
         * on its public URL - so the token would have to be put down before
         * "the public URL still 404s" is a question about the public.
         */
        $this->flushHeaders();

        $this->get('/blog/'.$post->slug)->assertNotFound();
    }

    public function test_a_post_can_be_published_straight_from_the_api(): void
    {
        $author = Author::factory()->create(['name' => 'Sam Writer']);
        $category = Category::factory()->create(['slug' => 'fades', 'name' => 'Fades']);

        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'The Fade, Explained',
            'body' => $this->bodyWithFaq('A fade is a gradient, cut in.'),
            'excerpt' => 'What a fade actually is.',
            'authors' => [$author->username],
            'categories' => [$category->slug],
            'published_at' => now()->subMinute()->toIso8601String(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.authors.0.username', $author->username)
            ->assertJsonPath('data.categories.0.slug', 'fades')
            ->assertJsonPath('data.faq_questions', 2);

        $this->get('/blog/the-fade-explained')
            ->assertOk()
            ->assertSee('The Fade, Explained')
            ->assertSee('Sam Writer');
    }

    public function test_reading_minutes_are_derived_rather_than_accepted(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Derived Reading Time',
            'body' => str_repeat('word ', 700),
            'reading_minutes' => 99,
        ]);

        $response->assertStatus(201);
        $this->assertNotSame(99, $response->json('data.reading_minutes'));
        $this->assertSame(4, $response->json('data.reading_minutes'));
    }

    public function test_an_article_with_no_byline_is_credited_to_the_house_account(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Unsigned On Arrival',
            'body' => $this->bodyWithFaq(),
            'published_at' => now()->subMinute()->toIso8601String(),
        ])->assertStatus(201);

        $this->assertCount(1, $response->json('data.authors'));
        $this->assertSame(
            config('blog.house_author.username'),
            $response->json('data.authors.0.username'),
        );
    }

    public function test_the_byline_keeps_the_order_it_was_sent_in(): void
    {
        $first = Author::factory()->create(['name' => 'Zoe Last']);
        $second = Author::factory()->create(['name' => 'Aaron First']);

        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Co Written',
            'body' => $this->bodyWithFaq(),
            'authors' => [$first->username, $second->username],
        ])->assertStatus(201);

        $this->assertSame(
            [$first->username, $second->username],
            array_column($response->json('data.authors'), 'username'),
        );
    }

    /*
    |---------------------------------------------------------------------------
    | The guardrails the admin form also enforces
    |---------------------------------------------------------------------------
    */

    public function test_publishing_without_a_faq_section_is_refused(): void
    {
        $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'No FAQ Here',
            'body' => 'Prose and nothing else.',
            'published_at' => now()->subMinute()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertSame(0, Post::query()->where('title', 'No FAQ Here')->count());
    }

    public function test_a_slug_that_shadows_a_category_hub_is_refused(): void
    {
        /* Not one of the seeded slugs - the suite already has the real catalog. */
        $category = Category::factory()->create(['slug' => 'clipper-work']);

        $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Trying To Take The Hub',
            'slug' => $category->slug,
            'body' => $this->bodyWithFaq(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_a_reserved_slug_is_refused(): void
    {
        $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Shadowing The Writer Index',
            'slug' => 'authors',
            'body' => $this->bodyWithFaq(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('slug');
    }

    public function test_an_unknown_category_or_author_is_refused_with_a_useful_message(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Filed Nowhere Real',
            'body' => $this->bodyWithFaq(),
            'authors' => ['nobody'],
            'categories' => ['no-such-category'],
        ])->assertStatus(422);

        $response->assertJsonValidationErrors(['authors.0', 'categories.0']);

        /* Array access, not dot notation: the error key is literally "authors.0". */
        $errors = $response->json('errors');

        $this->assertStringContainsString('nobody', $errors['authors.0'][0]);
        $this->assertStringContainsString('/api/v1/blog/context', $errors['authors.0'][0]);
        $this->assertStringContainsString('no-such-category', $errors['categories.0'][0]);
    }

    public function test_an_account_identifier_is_not_an_author_identifier(): void
    {
        $reader = User::factory()->create();

        $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'Crediting A Customer',
            'body' => $this->bodyWithFaq(),
            'authors' => [$reader->email],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('authors.0');
    }

    /*
    |---------------------------------------------------------------------------
    | Update
    |---------------------------------------------------------------------------
    */

    public function test_an_update_touches_only_the_fields_it_sends(): void
    {
        $author = Author::factory()->create();
        $category = Category::factory()->create(['slug' => 'fades']);

        $post = Post::factory()->create([
            'title' => 'Original Title',
            'excerpt' => 'The original excerpt.',
            'body' => $this->bodyWithFaq('Original prose.'),
            'cta_text' => 'Try it',
            'cta_url' => '/onboarding',
        ]);
        $post->syncAuthors([$author->id]);
        $post->categories()->sync([$category->id]);

        $response = $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'title' => 'A Better Title',
        ])->assertOk();

        $response->assertJsonPath('data.title', 'A Better Title')
            ->assertJsonPath('data.excerpt', 'The original excerpt.')
            ->assertJsonPath('data.cta_text', 'Try it')
            ->assertJsonPath('data.slug', $post->slug)
            ->assertJsonPath('data.authors.0.username', $author->username)
            ->assertJsonPath('data.categories.0.slug', 'fades');

        $this->assertSame('A Better Title', $post->fresh()->title);
    }

    public function test_an_explicit_null_clears_a_field(): void
    {
        /* Published, so the merged body is FAQ-checked - hence a body that has one. */
        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'cta_text' => 'Try it',
            'cta_url' => '/onboarding',
        ]);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'cta_text' => null,
            'cta_url' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.cta_text', null);

        $this->assertNull($post->fresh()->cta_text);
    }

    /**
     * The merged-state check, from the date side: an existing draft with no FAQ
     * cannot be published by a request that only carries a date.
     */
    public function test_publishing_an_existing_draft_that_has_no_faq_is_refused(): void
    {
        $post = Post::factory()->create([
            'body' => 'Prose with no FAQ at all.',
            'published_at' => null,
        ]);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'published_at' => now()->subMinute()->toIso8601String(),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertNull($post->fresh()->published_at);
    }

    /**
     * And from the body side: the FAQ cannot be edited out of a live article.
     */
    public function test_removing_the_faq_from_a_published_article_is_refused(): void
    {
        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'published_at' => now()->subDay(),
        ]);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'body' => 'Rewritten, and the FAQ is gone.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('body');

        $this->assertStringContainsString('## FAQ', (string) $post->fresh()->body);
    }

    public function test_a_post_can_be_reached_by_id_as_well_as_by_slug(): void
    {
        $post = Post::factory()->create();

        $this->call_api('GET', '/api/v1/blog/posts/'.$post->id)
            ->assertOk()
            ->assertJsonPath('data.slug', $post->slug);
    }

    /*
    |---------------------------------------------------------------------------
    | Delete means delist
    |---------------------------------------------------------------------------
    */

    public function test_delete_delists_the_post_and_keeps_the_row(): void
    {
        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'published_at' => now()->subDay(),
        ]);

        $this->get('/blog/'.$post->slug)->assertOk();

        $this->call_api('DELETE', '/api/v1/blog/posts/'.$post->slug)
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
        $this->assertNull($post->fresh()->published_at);

        /* As a stranger - the token would still see it. See above. */
        $this->flushHeaders();
        $this->get('/blog/'.$post->slug)->assertNotFound();
    }

    public function test_a_delisted_post_leaves_the_sitemap_and_comes_back_when_republished(): void
    {
        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'published_at' => now()->subDay(),
        ]);

        $this->get('/sitemap.xml')->assertSee('/blog/'.$post->slug);

        $this->call_api('DELETE', '/api/v1/blog/posts/'.$post->slug)->assertOk();
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$post->slug);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'published_at' => now()->subMinute()->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->get('/blog/'.$post->slug)->assertOk();
    }

    /*
    |---------------------------------------------------------------------------
    | List
    |---------------------------------------------------------------------------
    */

    public function test_the_listing_shows_drafts_and_filters_by_status(): void
    {
        Post::factory()->create(['title' => 'A Live One', 'published_at' => now()->subDay()]);
        Post::factory()->create(['title' => 'A Draft One', 'published_at' => null]);
        Post::factory()->create(['title' => 'A Future One', 'published_at' => now()->addWeek()]);

        $all = $this->call_api('GET', '/api/v1/blog/posts?per_page=100')->assertOk();
        $titles = array_column($all->json('data'), 'title');

        $this->assertContains('A Live One', $titles);
        $this->assertContains('A Draft One', $titles);

        $drafts = $this->call_api('GET', '/api/v1/blog/posts?status=draft&per_page=100')->assertOk();
        $draftTitles = array_column($drafts->json('data'), 'title');

        $this->assertContains('A Draft One', $draftTitles);
        $this->assertNotContains('A Live One', $draftTitles);

        $scheduled = $this->call_api('GET', '/api/v1/blog/posts?status=scheduled&per_page=100')->assertOk();

        $this->assertSame(['A Future One'], array_column($scheduled->json('data'), 'title'));
    }

    public function test_the_listing_can_be_searched_and_filtered_by_category(): void
    {
        $category = Category::factory()->create(['slug' => 'color-talk']);

        /* A nonsense word, because the suite seeds the real catalog and a
         * plausible one ("balayage") matches the articles that shipped with it. */
        $filed = Post::factory()->create(['title' => 'Zibbleflux Costs']);
        $filed->categories()->sync([$category->id]);

        Post::factory()->create(['title' => 'Something Else Entirely']);

        $byCategory = $this->call_api('GET', '/api/v1/blog/posts?category=color-talk&per_page=100')->assertOk();
        $this->assertSame(['Zibbleflux Costs'], array_column($byCategory->json('data'), 'title'));

        $bySearch = $this->call_api('GET', '/api/v1/blog/posts?search=Zibbleflux&per_page=100')->assertOk();
        $this->assertSame(['Zibbleflux Costs'], array_column($bySearch->json('data'), 'title'));
    }

    public function test_keyword_filter_finds_primary_and_secondary_exact_matches(): void
    {
        Post::factory()->create([
            'title' => 'Primary Match',
            'target_keyword' => 'French Crop',
            'secondary_keywords' => ['textured crop'],
        ]);
        Post::factory()->create([
            'title' => 'Secondary Match',
            'target_keyword' => 'Short Hair',
            'secondary_keywords' => ['french crop'],
        ]);
        Post::factory()->create([
            'title' => 'Not Exact',
            'target_keyword' => 'Best French Crop',
        ]);

        $response = $this->call_api('GET', '/api/v1/blog/posts?keyword=French%20Crop&per_page=100')->assertOk();

        $this->assertEqualsCanonicalizing(
            ['Primary Match', 'Secondary Match'],
            array_column($response->json('data'), 'title'),
        );
    }

    public function test_search_matches_the_post_body(): void
    {
        Post::factory()->create(['title' => 'Body Match', 'body' => 'The zibbleflux detail lives only here.']);
        Post::factory()->create(['title' => 'No Match', 'body' => 'Unrelated prose.']);

        $response = $this->call_api('GET', '/api/v1/blog/posts?search=zibbleflux&per_page=100')->assertOk();

        $this->assertSame(['Body Match'], array_column($response->json('data'), 'title'));
    }

    public function test_indexnow_dispatches_only_when_a_post_transitions_to_published(): void
    {
        Queue::fake();
        config()->set('blog.indexnow', ['key' => 'indexnow-test-key', 'enabled' => true]);

        $draft = Post::factory()->draft()->create();
        Queue::assertNothingPushed();

        $draft->update(['published_at' => now()->subMinute()]);
        Queue::assertPushed(SubmitPostToIndexNow::class, 1);

        $draft->update(['excerpt' => 'An edit after publication.']);
        Queue::assertPushed(SubmitPostToIndexNow::class, 1);

        $scheduled = Post::factory()->scheduled()->create();
        Queue::assertPushed(SubmitPostToIndexNow::class, 2);

        $scheduled->update(['published_at' => now()->subMinute()]);
        Queue::assertPushed(SubmitPostToIndexNow::class, 3);
    }

    public function test_indexnow_job_posts_the_public_url(): void
    {
        Http::fake(['api.indexnow.org/indexnow' => Http::response(status: 200)]);
        config()->set('blog.indexnow.key', 'indexnow-test-key');

        config(['app.url' => 'https://carpdraw.com']);
        $post = Post::factory()->create(['slug' => 'chair-guide']);
        (new SubmitPostToIndexNow('https://carpdraw.com/blog/chair-guide'))->handle();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.indexnow.org/indexnow'
            && $request['host'] === 'carpdraw.com'
            && $request['key'] === 'indexnow-test-key'
            && $request['urlList'] === ['https://carpdraw.com/blog/chair-guide']);
    }
}
