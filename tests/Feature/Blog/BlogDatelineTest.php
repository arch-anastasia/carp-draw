<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The date an article claims.
 *
 * `posts.dated_at` is an editorial answer; `updated_at` is a record of the row
 * being written. Where an editor has given one, the byline, the Article schema
 * and /sitemap.xml must all repeat the same date - an article that says one
 * thing on the page and another in its structured data is worse than either.
 */
class BlogDatelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_hand_picked_dateline_replaces_updated_at_on_the_page(): void
    {
        $post = Post::factory()
            ->publishedAt('2026-05-04 08:00:00')
            ->dated('2026-09-12 09:00:00')
            ->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Updated 12 September 2026')
            ->assertSee('"dateModified":"2026-09-12T09:00:00+00:00"', false);
    }

    /**
     * Without one, nothing changes: the stamp falls back to the row.
     */
    public function test_without_a_dateline_the_record_supplies_the_stamp(): void
    {
        $post = Post::factory()->publishedAt('2026-05-04 08:00:00')->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Updated '.$post->updated_at->format('j F Y'));
    }

    /**
     * And the heuristic that fallback exists for still holds: an article whose
     * row was last written a few hours after it went out was not "updated" in
     * any sense a reader cares about, so it prints its publish date.
     */
    public function test_without_a_dateline_a_fresh_article_prints_its_publish_date(): void
    {
        $this->travelTo('2026-05-04 12:00:00');

        $post = Post::factory()->publishedAt('2026-05-04 08:00:00')->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('4 May 2026')
            ->assertDontSee('Updated 4 May 2026');
    }

    /**
     * A deliberate date prints however close it sits to the publish date. The
     * two-day guard exists to filter mechanical noise, and a date someone
     * typed is not noise.
     */
    public function test_a_dateline_prints_even_when_it_sits_beside_the_publish_date(): void
    {
        $post = Post::factory()
            ->publishedAt('2026-05-04 08:00:00')
            ->dated('2026-05-05 08:00:00')
            ->create();

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Updated 5 May 2026');
    }

    /**
     * A draft has no publish date, so the dateline is the only date it has -
     * and a preview with no byline date is not the page a reader would get.
     */
    public function test_a_draft_preview_prints_its_dateline(): void
    {
        $post = Post::factory()->draft()->dated('2026-09-12 09:00:00')->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Updated 12 September 2026');
    }

    public function test_the_sitemap_reports_the_dateline_it_was_given(): void
    {
        $dated = Post::factory()
            ->publishedAt('2026-05-04 08:00:00')
            ->dated('2026-09-12 09:00:00')
            ->create(['slug' => 'dated-guide']);

        $plain = Post::factory()
            ->publishedAt('2026-05-04 08:00:00')
            ->create(['slug' => 'plain-guide']);

        $body = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('/blog/'.$dated->slug, $body);
        $this->assertStringContainsString('2026-09-12', $body);

        // The one without a dateline still reports when its row last changed.
        $this->assertStringContainsString(
            $plain->updated_at->toAtomString(),
            $body,
        );
    }
}
