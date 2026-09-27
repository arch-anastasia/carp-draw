<?php

namespace Tests\Feature\Blog;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Rewriting a live article without the rewrite reaching readers.
 *
 * The guarantee worth guarding hardest is the first one: while a version is
 * pending, /blog/{slug} must keep serving exactly what it served before. Every
 * other feature here is convenience on top of that.
 */
class PostRevisionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** A FAQ section in the shape App\Rules\HasFaqSection demands. */
    private function faq(): string
    {
        return "## FAQ\n### How long does a render take?\nAbout twenty seconds.\n\n### Is the first look free?\nYes, and it needs no card.";
    }

    private function livePost(): Post
    {
        return Post::factory()->create([
            'title' => 'The Live Article',
            'body' => "The live wording.\n\n".$this->faq(),
        ]);
    }

    public function test_opening_a_version_seeds_it_from_the_live_article(): void
    {
        $post = $this->livePost();
        $category = Category::factory()->create();
        $post->categories()->attach($category);

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions')
            ->assertRedirect();

        $revision = $post->pendingRevision();

        $this->assertNotNull($revision);
        $this->assertSame($post->title, $revision->title);
        $this->assertSame($post->body, $revision->body);
        $this->assertSame([$category->id], $revision->category_ids);
    }

    /**
     * The database allows one pending version per article. A double-click on
     * "New version" should land on the draft that already exists rather than
     * blowing up on a constraint.
     */
    public function test_opening_a_second_version_returns_the_one_already_open(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->admin())->post('/admin/posts/'.$post->id.'/versions');
        $first = $post->pendingRevision();

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions')
            ->assertRedirect('/admin/posts/'.$post->id.'/versions/'.$first->id.'/edit');

        $this->assertSame(1, $post->revisions()->pending()->count());
    }

    public function test_the_database_refuses_a_second_pending_version(): void
    {
        $post = $this->livePost();
        PostRevision::factory()->of($post)->create();

        $this->expectException(QueryException::class);

        PostRevision::factory()->of($post)->create();
    }

    /**
     * The whole point. A rewrite in progress changes nothing a reader sees.
     */
    public function test_a_pending_version_leaves_the_live_article_untouched(): void
    {
        $post = $this->livePost();

        PostRevision::factory()->of($post)->create([
            'title' => 'The Rewritten Article',
            'body' => "Completely new wording.\n\n".$this->faq(),
        ]);

        $response = $this->get('/blog/'.$post->slug)->assertOk();

        $response->assertSee('The Live Article');
        $response->assertSee('The live wording.');
        $response->assertDontSee('The Rewritten Article');
        $response->assertDontSee('Completely new wording.');

        $this->assertSame('The Live Article', $post->fresh()->title);
    }

    /**
     * Otherwise the only route to a pending version is the admin editor, and
     * an editor checking the live page could start a second rewrite on top of
     * a first they never knew about.
     */
    public function test_the_live_article_tells_an_admin_a_version_is_pending(): void
    {
        $post = $this->livePost();
        PostRevision::factory()->of($post)->create(['note' => 'Refreshed for 2026']);

        $admin = $this->actingAs($this->admin())->get('/blog/'.$post->slug)->assertOk();

        $admin->assertSee('Version pending');
        $admin->assertSee('Refreshed for 2026');
        $admin->assertSee('?v=draft', false);

        /* And a reader is told nothing at all. */
        $this->app->get('auth')->forgetGuards();
        $reader = $this->get('/blog/'.$post->slug)->assertOk();

        $reader->assertDontSee('Version pending');
        $reader->assertDontSee('Refreshed for 2026');
    }

    public function test_the_pending_version_previews_at_the_same_url_for_an_admin(): void
    {
        $post = $this->livePost();

        PostRevision::factory()->of($post)->create([
            'title' => 'The Rewritten Article',
            'body' => "Completely new wording.\n\n".$this->faq(),
        ]);

        $response = $this->actingAs($this->admin())
            ->get('/blog/'.$post->slug.'?v=draft')
            ->assertOk();

        $response->assertSee('The Rewritten Article');
        $response->assertSee('Completely new wording.');
        $response->assertSee('New version');
        $response->assertSee('Approve &amp; replace', false);

        /* A preview is never indexable, however live the article behind it is. */
        $response->assertSee('noindex, follow', false);
    }

    public function test_the_draft_preview_is_closed_to_everyone_but_a_trusted_caller(): void
    {
        $post = $this->livePost();
        PostRevision::factory()->of($post)->create(['title' => 'The Rewritten Article']);

        $this->get('/blog/'.$post->slug.'?v=draft')->assertNotFound();

        $this->actingAs(User::factory()->create())
            ->get('/blog/'.$post->slug.'?v=draft')
            ->assertNotFound();
    }

    /**
     * Serving the live wording for a `?v=draft` that has no draft behind it
     * would be how a rewrite gets approved without anyone having read it.
     */
    public function test_asking_for_a_draft_that_does_not_exist_is_a_404(): void
    {
        $post = $this->livePost();

        $this->actingAs($this->admin())
            ->get('/blog/'.$post->slug.'?v=draft')
            ->assertNotFound();
    }

    public function test_previewing_a_version_does_not_count_as_a_read(): void
    {
        $post = $this->livePost();
        PostRevision::factory()->of($post)->create();

        $this->actingAs($this->admin())->get('/blog/'.$post->slug.'?v=draft')->assertOk();

        $this->assertSame(0, $post->fresh()->views_count);
    }

    public function test_approving_replaces_the_live_wording(): void
    {
        $post = $this->livePost();
        $author = Author::factory()->create();
        $category = Category::factory()->create();

        $revision = PostRevision::factory()->of($post)->create([
            'title' => 'The Rewritten Article',
            'body' => "Completely new wording.\n\n".$this->faq(),
            'author_ids' => [$author->id],
            'category_ids' => [$category->id],
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply')
            ->assertRedirect($post->url());

        $post->refresh();

        $this->assertSame('The Rewritten Article', $post->title);
        $this->assertStringContainsString('Completely new wording.', $post->body);
        $this->assertTrue($post->authors->contains($author));
        $this->assertTrue($post->categories->contains($category));

        /* And a reader now gets it. */
        $this->app->get('auth')->forgetGuards();
        $this->get('/blog/'.$post->slug)->assertOk()->assertSee('The Rewritten Article');
    }

    /**
     * A rewrite is not a republication. Moving `published_at` would reset the
     * article's age and tell every crawler a four-month-old URL is new.
     */
    public function test_approving_leaves_the_articles_identity_alone(): void
    {
        $post = Post::factory()->publishedAt('2026-05-04 08:00:00')->create([
            'slug' => 'keeps-its-slug',
            'views_count' => 812,
            'body' => "Old wording.\n\n".$this->faq(),
        ]);

        $revision = PostRevision::factory()->of($post)->create([
            'body' => "New wording.\n\n".$this->faq(),
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply');

        $post->refresh();

        $this->assertSame('keeps-its-slug', $post->slug);
        $this->assertSame('2026-05-04 08:00:00', $post->published_at->toDateTimeString());
        $this->assertSame(812, $post->views_count);
    }

    /**
     * History is the reason applied versions are kept, and `superseded` is
     * what makes it readable: without it a past version could say when it
     * landed but never what it changed.
     */
    public function test_approving_records_what_the_article_used_to_say(): void
    {
        $post = $this->livePost();

        $revision = PostRevision::factory()->of($post)->create([
            'title' => 'The Rewritten Article',
            'body' => "New wording.\n\n".$this->faq(),
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply');

        $revision->refresh();

        $this->assertFalse($revision->isPending());
        $this->assertSame($admin->id, $revision->applied_by_id);
        $this->assertSame('The Live Article', $revision->superseded['title']);
        $this->assertStringContainsString('The live wording.', $revision->superseded['body']);

        /* And the diff still reads correctly after the live article moves on. */
        $post->fresh()->update(['title' => 'Renamed Later']);

        $diff = $revision->fresh()->diff();
        $titleChange = collect($diff['fields'])->firstWhere('key', 'title');

        $this->assertSame('The Live Article', $titleChange['before']);
        $this->assertSame('The Rewritten Article', $titleChange['after']);
    }

    public function test_an_applied_version_cannot_be_edited_applied_again_or_deleted(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->applied()->create();

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply')
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id.'/versions/'.$revision->id, [
                'title' => 'Nope',
                'body' => 'Nope.',
            ])
            ->assertForbidden();

        $this->actingAs($this->admin())
            ->delete('/admin/posts/'.$post->id.'/versions/'.$revision->id)
            ->assertForbidden();

        $this->assertDatabaseHas('post_revisions', ['id' => $revision->id]);
    }

    /**
     * A live article renders its FAQ as FAQPage schema, so the rule has to
     * hold for anything that reaches a reader - but only at the moment it
     * would, not on every save of a half-written rewrite.
     */
    public function test_a_version_saves_without_a_faq_but_cannot_be_approved_without_one(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->create();

        $this->actingAs($this->admin())
            ->put('/admin/posts/'.$post->id.'/versions/'.$revision->id, [
                'title' => 'Halfway There',
                'body' => 'Only the first two paragraphs so far.',
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply')
            ->assertSessionHasErrors('body');

        $this->assertSame('The Live Article', $post->fresh()->title);
    }

    /**
     * A revision belongs to exactly one article. Without the check, one
     * article's wording could be applied on top of another's.
     */
    public function test_a_version_cannot_be_applied_to_a_different_article(): void
    {
        $post = $this->livePost();
        $other = Post::factory()->create(['title' => 'Somebody Else']);
        $revision = PostRevision::factory()->of($post)->create();

        $this->actingAs($this->admin())
            ->post('/admin/posts/'.$other->id.'/versions/'.$revision->id.'/apply')
            ->assertNotFound();

        $this->assertSame('Somebody Else', $other->fresh()->title);
    }

    public function test_a_pending_version_can_be_discarded(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->create();

        $this->actingAs($this->admin())
            ->delete('/admin/posts/'.$post->id.'/versions/'.$revision->id)
            ->assertRedirect('/admin/posts/'.$post->id.'/edit');

        $this->assertDatabaseMissing('post_revisions', ['id' => $revision->id]);
        $this->assertSame('The Live Article', $post->fresh()->title);
    }

    public function test_the_version_screens_render_with_a_diff(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->create([
            'title' => 'The Rewritten Article',
            'body' => "The new wording.\n\n".$this->faq(),
        ]);

        $this->actingAs($this->admin())
            ->get('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Blog/Revisions/Edit')
                ->where('diff.hasChanges', true)
                ->where('revision.is_pending', true));

        $this->actingAs($this->admin())
            ->get('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/changes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Blog/Revisions/Diff')
                ->where('diff.fields.0.key', 'title')
                ->where('diff.fields.0.before', 'The Live Article')
                ->where('diff.fields.0.after', 'The Rewritten Article'));

        foreach (['Edit', 'Diff'] as $page) {
            $this->assertFileExists(resource_path("js/Pages/Admin/Blog/Revisions/{$page}.vue"));
        }
    }

    public function test_the_post_editor_lists_the_articles_versions(): void
    {
        $post = $this->livePost();
        PostRevision::factory()->of($post)->create(['note' => 'Refreshed for 2026']);

        $this->actingAs($this->admin())
            ->get('/admin/posts/'.$post->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('versions', 1)
                ->where('versions.0.is_pending', true)
                ->where('versions.0.note', 'Refreshed for 2026'));
    }

    public function test_versions_are_closed_to_everyone_but_admins(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->create();

        $this->post('/admin/posts/'.$post->id.'/versions')->assertRedirect('/signin');
        $this->get('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/edit')->assertRedirect('/signin');

        $reader = User::factory()->create();

        $this->actingAs($reader)->post('/admin/posts/'.$post->id.'/versions')->assertForbidden();
        $this->actingAs($reader)
            ->post('/admin/posts/'.$post->id.'/versions/'.$revision->id.'/apply')
            ->assertForbidden();

        $this->assertSame('The Live Article', $post->fresh()->title);
    }

    /**
     * Applying is a snapshot and an overwrite in one act. A revision marked
     * applied whose wording never reached the post - or a post overwritten
     * with no record of what it said - is worse than either half failing.
     */
    public function test_applying_is_one_transaction(): void
    {
        $post = $this->livePost();
        $revision = PostRevision::factory()->of($post)->create([
            'body' => "New wording.\n\n".$this->faq(),
        ]);

        DB::beginTransaction();
        $revision->apply();
        DB::rollBack();

        $this->assertSame('The Live Article', $post->fresh()->title);
        $this->assertTrue($revision->fresh()->isPending());
    }
}
