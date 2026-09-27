<?php

namespace Tests\Feature\Blog\Admin;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The editorial desk at /admin/posts.
 */
class PostControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_the_desk_is_closed_to_everyone_but_admins(): void
    {
        $this->get('/admin/posts')->assertRedirect('/signin');

        $this->actingAs(User::factory()->create())->get('/admin/posts')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/posts')->assertOk();
    }

    public function test_the_editor_screens_render(): void
    {
        // A controller naming a page that does not exist fails in the
        // browser, not in CI, so the name and the file are both checked.
        $post = Post::factory()->create();

        $this->actingAs($this->admin())
            ->get('/admin/posts/create')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Blog/Posts/Create')
                ->has('authors')
                ->has('categories')
                ->has('audiences')
                ->missing('looks'));

        $this->actingAs($this->admin())
            ->get('/admin/posts/'.$post->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Blog/Posts/Edit')
                ->where('post.id', $post->id));

        foreach (['Index', 'Create', 'Edit'] as $page) {
            $this->assertFileExists(resource_path("js/Pages/Admin/Blog/Posts/{$page}.vue"));
        }
    }

    public function test_a_post_can_be_created_with_categories(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'A Brand New Guide',
                'body' => "The body of the guide.\n\n".$this->faq(),
                'categories' => [$category->id],
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect('/admin/posts')
            ->assertSessionHas('success');

        $post = Post::query()->where('title', 'A Brand New Guide')->firstOrFail();

        $this->assertSame('a-brand-new-guide', $post->slug);
        $this->assertTrue($post->categories->contains($category));
        $this->assertNotNull($post->reading_minutes);
    }

    public function test_a_post_with_no_publish_date_is_a_draft_and_stays_off_the_site(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/posts', ['title' => 'Still Cooking', 'body' => 'Not ready.'])
            ->assertRedirect('/admin/posts');

        $post = Post::query()->where('title', 'Still Cooking')->firstOrFail();

        $this->assertNull($post->published_at);

        $this->app->get('auth')->forgetGuards();
        $this->get('/blog/'.$post->slug)->assertNotFound();
    }

    /**
     * The dateline is editorial, so the editor takes any date it is given -
     * including one ahead of today, which is the case a `before:now` rule
     * would have quietly rejected. See the migration that added the column.
     */
    public function test_the_editor_stores_a_dateline_and_hands_it_back(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id, [
                'title' => $post->title,
                'body' => "Still the body.\n\n".$this->faq(),
                'published_at' => $post->published_at->toDateTimeString(),
                'dated_at' => '2026-09-12T09:00',
            ])
            ->assertRedirect('/admin/posts')
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-12 09:00:00', $post->fresh()->dated_at->toDateTimeString());

        // And comes back in the shape the datetime-local input wants.
        $this->actingAs($this->admin())
            ->get('/admin/posts/'.$post->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('post.dated_at', '2026-09-12T09:00'));
    }

    /** A FAQ section in the shape App\Rules\HasFaqSection demands. */
    private function faq(): string
    {
        return "## FAQ\n### How long does a render take?\n<p>About twenty seconds.</p>\n\n### Is the first look free?\n<p>Yes, and it needs no card.</p>";
    }

    /**
     * Publishing is what pulls the FAQ requirement in: a live article renders
     * its FAQ as FAQPage JSON-LD, and a body without one emits no schema.
     */
    public function test_publishing_a_post_without_a_faq_section_is_refused(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'No Questions Asked',
                'body' => 'Prose only, no questions.',
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('body');

        $this->assertSame(0, Post::query()->where('title', 'No Questions Asked')->count());
    }

    public function test_publishing_an_existing_draft_without_a_faq_section_is_refused(): void
    {
        $post = Post::factory()->create(['body' => 'Prose only.', 'published_at' => null]);

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id, [
                'title' => $post->title,
                'body' => 'Prose only.',
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('body');

        $this->assertNull($post->fresh()->published_at);
    }

    public function test_a_published_post_with_a_faq_renders_the_faqpage_schema(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'Questions Answered',
                'body' => "Prose.\n\n".$this->faq(),
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertSessionHasNoErrors();

        $content = $this->get('/blog/questions-answered')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"FAQPage"', $content);
        $this->assertStringContainsString('How long does a render take?', $content);
    }

    public function test_a_slug_already_used_by_a_category_is_refused(): void
    {
        $category = Category::factory()->create(['slug' => 'taken-by-a-hub']);

        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'Anything',
                'body' => 'Body.',
                'slug' => 'taken-by-a-hub',
            ])
            ->assertSessionHasErrors('slug');

        $this->assertSame(0, Post::query()->where('slug', $category->slug)->count());
    }

    public function test_a_post_keeps_its_own_slug_when_edited(): void
    {
        $post = Post::factory()->create(['slug' => 'unchanged']);

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id, [
                'title' => 'A Better Title',
                'body' => $post->body,
                'slug' => 'unchanged',
            ])
            ->assertRedirect('/admin/posts')
            ->assertSessionHasNoErrors();

        $this->assertSame('A Better Title', $post->fresh()->title);
        $this->assertSame('unchanged', $post->fresh()->slug);
    }

    public function test_a_title_and_a_body_are_both_required(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/posts', ['title' => '', 'body' => ''])
            ->assertSessionHasErrors(['title', 'body']);
    }

    public function test_reading_time_is_recomputed_when_the_body_changes(): void
    {
        $post = Post::factory()->create(['body' => 'Three short words.']);
        $before = $post->fresh()->reading_minutes;

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id, [
                'title' => $post->title,
                'body' => str_repeat('word ', 3000),
            ])
            ->assertRedirect('/admin/posts');

        $this->assertGreaterThan($before, $post->fresh()->reading_minutes);
    }

    public function test_a_post_can_be_deleted(): void
    {
        $post = Post::factory()->create();

        $this->actingAs($this->admin())
            ->delete('/admin/posts/'.$post->id)
            ->assertRedirect('/admin/posts');

        $this->assertNull($post->fresh());
    }

    public function test_a_post_can_be_created_with_a_byline_of_several_writers(): void
    {
        $barber = Author::factory()->create(['name' => 'Dana Reeve']);
        $editor = Author::factory()->create(['name' => 'Sam Okafor']);

        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'A Co Written Guide',
                'body' => "The body of the guide.\n\n".$this->faq(),
                /* The order they were picked is the order they print in. */
                'authors' => [$barber->id, $editor->id],
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect('/admin/posts');

        $post = Post::query()->where('title', 'A Co Written Guide')->firstOrFail();

        $this->assertSame(['Dana Reeve', 'Sam Okafor'], $post->authors->pluck('name')->all());
        $this->assertSame([0, 1], $post->authors->pluck('pivot.sort_order')->all());
    }

    public function test_editing_the_byline_replaces_it_rather_than_adding_to_it(): void
    {
        $barber = Author::factory()->create(['name' => 'Dana Reeve']);
        $editor = Author::factory()->create(['name' => 'Sam Okafor']);
        $post = Post::factory()->by($barber, $editor)->create();

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id, [
                'title' => $post->title,
                'body' => $post->body,
                'authors' => [$editor->id],
            ])
            ->assertRedirect('/admin/posts');

        $this->assertSame(['Sam Okafor'], $post->fresh()->authors->pluck('name')->all());
    }

    public function test_a_post_saved_with_no_byline_is_credited_to_the_house_account(): void
    {
        // Nothing publishes unsigned, so "no byline" means house copy rather
        // than an article with nobody's name on it.
        $this->actingAs($this->admin())
            ->post('/admin/posts', [
                'title' => 'Unsigned House Copy',
                'body' => "The body of the guide.\n\n".$this->faq(),
                'published_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect('/admin/posts');

        $post = Post::query()->where('title', 'Unsigned House Copy')->firstOrFail();

        $this->assertSame([config('blog.house_author.name')], $post->authors->pluck('name')->all());
    }

    public function test_the_listing_filters_by_status(): void
    {
        Post::factory()->create(['title' => 'Live Guide']);
        Post::factory()->draft()->create(['title' => 'Draft Guide']);

        $this->actingAs($this->admin())
            ->get('/admin/posts?status=draft')
            ->assertOk()
            ->assertSee('Draft Guide')
            ->assertDontSee('Live Guide');
    }
}
