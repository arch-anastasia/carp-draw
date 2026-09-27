<?php

namespace Tests\Feature\Blog;

use App\Models\Post;
use App\Models\PostRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The second door onto an unfinished article: the blog API token.
 *
 * /api/v1/blog deals in source - the markdown an agent wrote. This is the other
 * half of the loop: reading back the rendered page, including the parts that
 * only exist as HTML. Same token, same header, same constant-time comparison as
 * App\Http\Middleware\VerifyBlogApiToken. See App\Services\PreviewAccess.
 *
 * The token grants reading, never writing. The publish and approve controls are
 * gated on an admin session, because a bearer token carries no CSRF and an
 * agent that can read a draft has no business approving one.
 */
class BlogPreviewTokenTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-blog-api-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('blog.api.token', self::TOKEN);
    }

    /**
     * @return array<string, string>
     */
    private function bearer(string $token = self::TOKEN): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_the_token_reads_an_unpublished_article(): void
    {
        $draft = Post::factory()->draft()->create([
            'title' => 'Unfinished Business',
            'body' => "## A heading\n\nSome **bold** copy.",
        ]);

        $response = $this->withHeaders($this->bearer())
            ->get('/blog/'.$draft->slug)
            ->assertOk();

        /* The rendered page, which is the thing the write API cannot return. */
        $response->assertSee('<h2 id="a-heading">A heading</h2>', false);
        $response->assertSee('<strong>bold</strong>', false);
        $response->assertSee('noindex, follow', false);
    }

    public function test_the_token_reads_a_published_article_too(): void
    {
        $post = Post::factory()->create(['title' => 'Already Out']);

        $this->withHeaders($this->bearer())
            ->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('Already Out');
    }

    public function test_the_token_reads_a_pending_version(): void
    {
        $post = Post::factory()->create(['title' => 'The Live Article']);
        PostRevision::factory()->of($post)->create(['title' => 'The Rewritten Article']);

        $this->withHeaders($this->bearer())
            ->get('/blog/'.$post->slug.'?v=draft')
            ->assertOk()
            ->assertSee('The Rewritten Article');
    }

    /**
     * Read-only. The bar carries CSRF-protected forms an agent could not post
     * to anyway, and showing controls that cannot work is worse than none.
     */
    public function test_the_token_gets_no_editorial_controls(): void
    {
        $draft = Post::factory()->draft()->create(['title' => 'Unfinished Business']);

        $response = $this->withHeaders($this->bearer())
            ->get('/blog/'.$draft->slug)
            ->assertOk();

        $response->assertSee('Unfinished Business');
        $response->assertDontSee('Publish now');
        $response->assertDontSee('/publish');
    }

    public function test_a_wrong_token_sees_what_the_public_sees(): void
    {
        $draft = Post::factory()->draft()->create();
        $live = Post::factory()->create(['title' => 'Already Out']);

        $this->withHeaders($this->bearer('not-the-token'))
            ->get('/blog/'.$draft->slug)
            ->assertNotFound();

        /* And a wrong token is not a rejection, just no extra access. */
        $this->withHeaders($this->bearer('not-the-token'))
            ->get('/blog/'.$live->slug)
            ->assertOk()
            ->assertSee('Already Out');
    }

    /**
     * The failure mode of reading a blank token as "no check required" is
     * every draft on a server that forgot the variable becoming public.
     */
    public function test_an_unset_token_opens_nothing(): void
    {
        config()->set('blog.api.token', '');

        $draft = Post::factory()->draft()->create();

        $this->withHeaders(['Authorization' => 'Bearer '])
            ->get('/blog/'.$draft->slug)
            ->assertNotFound();

        $this->get('/blog/'.$draft->slug)->assertNotFound();
    }

    /**
     * The token is a second door, not a replacement: an admin session still
     * gets the controls.
     */
    public function test_an_admin_session_still_gets_the_controls(): void
    {
        $draft = Post::factory()->draft()->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get('/blog/'.$draft->slug)
            ->assertOk()
            ->assertSee('Publish now');
    }
}
