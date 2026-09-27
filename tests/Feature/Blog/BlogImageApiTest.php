<?php

namespace Tests\Feature\Blog;

use App\Models\Media;
use App\Models\Post;
use App\Services\Ingest\ImageIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Pictures, through the token-authenticated write API.
 *
 * The article half of this API is covered by BlogApiTest; this is the half that
 * decides whether an agent with no checkout can illustrate what it writes.
 */
class BlogImageApiTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'test-blog-api-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('blog.api.token', self::TOKEN);
        Storage::fake('public');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call_api(string $method, string $uri, array $payload = []): TestResponse
    {
        return $this->withToken(self::TOKEN)->json($method, $uri, $payload);
    }

    private function pngBytes(int $width = 12, int $height = 8): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);
        $contents = (string) ob_get_clean();
        imagedestroy($image);

        return $contents;
    }

    private function pathFromUrl(string $url): string
    {
        return Str::after(ltrim((string) parse_url($url, PHP_URL_PATH), '/'), 'storage/');
    }

    /*
    |---------------------------------------------------------------------------
    | Upload
    |---------------------------------------------------------------------------
    */

    public function test_base64_bytes_are_stored_and_handed_back_as_a_usable_url(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A mid fade, three weeks after the cut',
        ])->assertStatus(201);

        $url = (string) $response->json('data.url');

        $this->assertStringContainsString('/storage/blog/', $url);

        Storage::disk('public')->assertExists($this->pathFromUrl($url));

        $response->assertJsonPath('data.alt', 'A mid fade, three weeks after the cut')
            ->assertJsonPath('data.markdown', '![A mid fade, three weeks after the cut]('.$url.')')
            ->assertJsonPath('data.width', 12)
            ->assertJsonPath('data.height', 8);

        $this->assertGreaterThan(0, (int) $response->json('data.bytes'));
        $this->assertNotNull(Media::query()->where('url', $url)->first());
    }

    /**
     * Everything stored is re-encoded, which is what makes the format uniform
     * and drops whatever the uploaded file was carrying besides pixels.
     */
    public function test_an_uploaded_picture_is_re_encoded_as_webp(): void
    {
        if (! function_exists('imagewebp')) {
            $this->markTestSkipped('This PHP has no GD webp encoder; ImageIngestor stores the original bytes instead.');
        }

        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A picture that arrived as a png',
        ])->assertStatus(201);

        $this->assertStringEndsWith('.webp', (string) $response->json('data.url'));
        $response->assertJsonPath('data.mime', 'image/webp');
    }

    public function test_a_multipart_upload_lands_in_the_same_place(): void
    {
        $file = UploadedFile::fake()->image('barber.png', 24, 16);

        $response = $this->withToken(self::TOKEN)
            ->post('/api/v1/blog/images', [
                'file' => $file,
                'alt' => 'A barber checking a neckline',
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $url = (string) $response->json('data.url');

        $this->assertStringContainsString('/storage/blog/', $url);
        Storage::disk('public')->assertExists($this->pathFromUrl($url));
        $response->assertJsonPath('data.width', 24)->assertJsonPath('data.height', 16);
    }

    /**
     * The stored name is a hash of the bytes, so an agent that retries mid-draft
     * does not leave a second copy of the same picture behind.
     */
    public function test_uploading_the_same_picture_twice_is_one_file_and_one_media_row(): void
    {
        $payload = ['data' => base64_encode($this->pngBytes()), 'alt' => 'The same picture'];

        $first = $this->call_api('POST', '/api/v1/blog/images', $payload)->assertStatus(201);
        $second = $this->call_api('POST', '/api/v1/blog/images', $payload)->assertStatus(201);

        $this->assertSame($first->json('data.url'), $second->json('data.url'));
        $this->assertSame(1, Media::query()->where('url', $first->json('data.url'))->count());
    }

    public function test_the_file_is_named_after_what_it_shows(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A textured crop from the front',
        ])->assertStatus(201);

        $this->assertStringContainsString('/storage/blog/a-textured-crop-from-the-front-', (string) $response->json('data.url'));
    }

    /**
     * A filename is one path component, and a filesystem stops accepting them
     * somewhere around 255 bytes. The public disk is configured not to throw,
     * so an overrun would be a silent non-write behind a 201.
     */
    public function test_a_very_long_description_still_produces_a_file(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => str_repeat('a low taper fade seen from the side ', 7),
        ])->assertStatus(201);

        $file = basename((string) parse_url((string) $response->json('data.url'), PHP_URL_PATH));

        $this->assertLessThan(120, strlen($file));
        Storage::disk('public')->assertExists($this->pathFromUrl((string) $response->json('data.url')));
    }

    /**
     * A description in a script that does not transliterate leaves nothing to
     * name the file after, and the separator would start the filename.
     */
    public function test_a_description_that_slugs_to_nothing_still_names_the_file(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => '前髪のカット',
        ])->assertStatus(201);

        $file = basename((string) parse_url((string) $response->json('data.url'), PHP_URL_PATH));

        $this->assertStringStartsWith('image-', $file);
        Storage::disk('public')->assertExists($this->pathFromUrl((string) $response->json('data.url')));
    }

    public function test_a_data_uri_is_accepted_as_readily_as_bare_base64(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => 'data:image/png;base64,'.base64_encode($this->pngBytes()),
            'alt' => 'A picture that arrived as a data uri',
        ])->assertStatus(201);

        Storage::disk('public')->assertExists($this->pathFromUrl((string) $response->json('data.url')));
        $response->assertJsonPath('data.width', 12);
    }

    public function test_an_explicit_name_overrides_the_alt_text_as_the_filename(): void
    {
        $response = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A textured crop from the front',
            'name' => 'french-crop-hero',
        ])->assertStatus(201);

        $this->assertStringContainsString('/storage/blog/french-crop-hero-', (string) $response->json('data.url'));
    }

    /*
    |---------------------------------------------------------------------------
    | What is refused
    |---------------------------------------------------------------------------
    */

    public function test_an_upload_without_a_token_is_refused(): void
    {
        $this->json('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'Nobody sent a token',
        ])->assertStatus(401);

        $this->assertNothingWasStored();
    }

    public function test_an_upload_with_no_picture_at_all_is_refused(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', ['alt' => 'Nothing attached'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file', 'data']);
    }

    public function test_alt_text_is_required(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('alt');

        $this->assertNothingWasStored();
    }

    public function test_bytes_that_are_not_an_image_are_refused_rather_than_stored(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode('this is a text file wearing a hat'),
            'alt' => 'Not a picture',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data');

        $this->assertNothingWasStored();
    }

    public function test_data_that_is_not_base64_is_refused(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', [
            'data' => '***not base64***',
            'alt' => 'Not decodable',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data');
    }

    /**
     * An SVG is a script host, and one served from our own origin would be
     * stored XSS on a page a reader trusts.
     */
    public function test_an_svg_is_refused(): void
    {
        $this->withToken(self::TOKEN)
            ->post('/api/v1/blog/images', [
                'file' => UploadedFile::fake()->create('logo.svg', 4, 'image/svg+xml'),
                'alt' => 'A logo',
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');

        $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'alt' => 'A logo',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data');

        $this->assertNothingWasStored();
    }

    public function test_an_oversized_payload_is_refused_with_a_422_rather_than_a_500(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode(str_repeat('a', ImageIngestor::MAX_BYTES + 1024)),
            'alt' => 'Far too big',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('data');
    }

    /**
     * A file this server has no business opening: a path on the machine, and a
     * URL for it to go and fetch. Neither is a way in.
     */
    public function test_a_local_path_or_a_remote_url_is_not_a_way_to_supply_bytes(): void
    {
        $this->call_api('POST', '/api/v1/blog/images', [
            'source' => base_path('.env'),
            'alt' => 'The environment file',
        ])->assertStatus(422);

        $this->call_api('POST', '/api/v1/blog/images', [
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'alt' => 'Somebody else\'s metadata',
        ])->assertStatus(422);

        $this->assertNothingWasStored();
    }

    /*
    |---------------------------------------------------------------------------
    | Using the picture
    |---------------------------------------------------------------------------
    */

    public function test_an_uploaded_picture_becomes_a_cover_and_prints_its_alt_text(): void
    {
        $upload = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A low taper, four weeks in',
        ])->assertStatus(201);

        $response = $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'A Guide With A Cover',
            'body' => $this->bodyWithFaq(),
            'cover_image' => $upload->json('data.url'),
            'cover_image_alt' => 'A low taper, four weeks in',
            'published_at' => now()->subMinute()->toIso8601String(),
        ])->assertStatus(201);

        $response->assertJsonPath('data.cover_image', $upload->json('data.url'))
            ->assertJsonPath('data.cover_image_alt', 'A low taper, four weeks in');

        $post = Post::query()->where('slug', 'a-guide-with-a-cover')->firstOrFail();

        $this->assertSame($upload->json('data.id'), $post->cover_media_id);

        $this->get('/blog/'.$post->slug)
            ->assertOk()
            ->assertSee('alt="A low taper, four weeks in"', false);
    }

    public function test_a_body_image_renders_from_the_markdown_the_upload_handed_back(): void
    {
        $upload = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A french crop from the side',
        ])->assertStatus(201);

        $this->call_api('POST', '/api/v1/blog/posts', [
            'title' => 'A Guide With Pictures In It',
            'body' => "## The cut\n\n".$upload->json('data.markdown')."\n\n".$this->bodyWithFaq(),
            'published_at' => now()->subMinute()->toIso8601String(),
        ])->assertStatus(201);

        $this->get('/blog/a-guide-with-pictures-in-it')
            ->assertOk()
            ->assertSee((string) $upload->json('data.url'), false)
            ->assertSee('alt="A french crop from the side"', false);
    }

    /**
     * The cover's description is a property of the picture, so it can be fixed
     * without resending the cover it belongs to.
     */
    public function test_the_cover_alt_text_can_be_corrected_on_its_own(): void
    {
        $upload = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A had cut',
        ])->assertStatus(201);

        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'cover_media_id' => $upload->json('data.id'),
        ]);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, [
            'cover_image_alt' => 'A hard part, cut in with a trimmer',
        ])
            ->assertOk()
            ->assertJsonPath('data.cover_image_alt', 'A hard part, cut in with a trimmer')
            ->assertJsonPath('data.cover_image', $upload->json('data.url'));

        $this->assertSame('A hard part, cut in with a trimmer', $post->fresh()->cover?->alt);
    }

    /**
     * Editing anything else must not quietly strip the description off the
     * picture - Media::forUrl() only writes alt when one is supplied, and the
     * partial-update merge is what keeps it supplied.
     */
    public function test_an_unrelated_edit_leaves_the_cover_and_its_alt_alone(): void
    {
        $upload = $this->call_api('POST', '/api/v1/blog/images', [
            'data' => base64_encode($this->pngBytes()),
            'alt' => 'A buzz cut, straight on',
        ])->assertStatus(201);

        $post = Post::factory()->create([
            'body' => $this->bodyWithFaq(),
            'cover_media_id' => $upload->json('data.id'),
        ]);

        $this->call_api('PATCH', '/api/v1/blog/posts/'.$post->slug, ['title' => 'A Better Title'])
            ->assertOk()
            ->assertJsonPath('data.cover_image', $upload->json('data.url'))
            ->assertJsonPath('data.cover_image_alt', 'A buzz cut, straight on');
    }

    /*
    |---------------------------------------------------------------------------
    | Context
    |---------------------------------------------------------------------------
    */

    public function test_the_context_endpoint_tells_a_writer_how_to_send_a_picture(): void
    {
        $response = $this->call_api('GET', '/api/v1/blog/context')->assertOk();

        $images = $response->json('images');

        $this->assertNotEmpty($images);
        $this->assertStringContainsString('/api/v1/blog/images', (string) $images['upload']);
        $this->assertArrayHasKey('POST /api/v1/blog/images', $response->json('endpoints'));
        $this->assertStringNotContainsString('has to already exist', (string) $response->json('body.images'));
    }

    /**
     * Nothing reached the disk and nothing reached the library. Counted by
     * where the file would have gone rather than in total, because the suite
     * seeds the real catalog and its media rows are already there.
     */
    private function assertNothingWasStored(): void
    {
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, Media::query()->where('url', 'like', '%/storage/blog/%')->count());
    }

    /**
     * A body the FAQ rule accepts, so a test about pictures is about pictures.
     */
    private function bodyWithFaq(): string
    {
        return <<<'MARKDOWN'
        ## A heading

        Some copy about a haircut.

        ## FAQ

        ### Does this need a FAQ?

        Yes, every published article renders one as FAQPage schema.

        ### How many questions?

        At least two.
        MARKDOWN;
    }
}
