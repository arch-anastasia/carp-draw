<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBlogImageRequest;
use App\Models\Author;
use App\Models\Category;
use App\Rules\HasFaqSection;
use App\Services\Ingest\ImageIngestor;
use Illuminate\Http\JsonResponse;

/**
 * Everything an agent needs to write a post that will be accepted, in one GET.
 *
 * The alternative is putting this in a prompt, where it goes stale the first
 * time somebody adds a category and nobody notices until an article is filed
 * nowhere. Fetched instead, the vocabulary is always the vocabulary the
 * validator will actually check against: the same tables, read at call time.
 *
 * Call it first, write against what it returns.
 */
class BlogContextController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'categories' => Category::query()->live()->orderBy('name')->get()
                ->map(fn (Category $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'description' => $category->description,
                    /* Inherited by every post filed here that does not overrule it. */
                    'audience' => $category->audience,
                ])->all(),

            'authors' => Author::query()
                ->orderBy('name')
                ->get(['name', 'username', 'role'])
                ->map(fn (Author $author): array => [
                    'username' => $author->username,
                    'name' => $author->name,
                    'role' => $author->role,
                ])->all(),

            'audiences' => [],

            'rules' => [
                'faq' => [
                    'required_to_publish' => true,
                    'minimum_questions' => HasFaqSection::MINIMUM_QUESTIONS,
                    'recommended_questions' => HasFaqSection::RECOMMENDED_QUESTIONS,
                    'shape' => "A '## FAQ' section, then one '### Question' heading per question with its answer underneath. Parsed back out into FAQPage JSON-LD and rendered as an accordion, so it is not printed twice - leave it at the end of the body.",
                ],
                'slug' => 'Optional; derived from the title when omitted. Must be free across posts, category hubs and reserved words, because they share one flat /blog/{slug} space.',
                'publishing' => 'Unless killed, published_at null is a draft, a past time publishes immediately, a future time schedules. Nothing before that time is public or in the sitemap.',
                'killing' => 'Set killed_at to an ISO 8601 timestamp and optionally killed_reason (at most 255 characters) to retire a post. This clears published_at, removes the post from public listings and returns 410 Gone at its blog URL. To restore, set killed_at and killed_reason to null, then set published_at in a separate request. Publishing a killed post returns 422.',
                'byline' => 'authors is a list of usernames from the list above, printed in the order you send them. Omit it and the article is credited to the house byline; nothing publishes unsigned.',
                'reading_minutes' => 'Derived from the body on every save. Do not send it.',
                'keywords' => 'target_keyword is the one exact phrase this post claims. secondary_keywords is a list of other exact phrases it covers; check both with the keyword list filter before drafting.',
            ],

            'body' => [
                'format' => 'GitHub-flavoured markdown. Raw HTML is passed through. Stored as markdown and rendered on read, so a later fix to the renderer reaches old articles too.',
                'headings' => 'h2 and h3 only - they become the anchored "In this article" outline. The h1 is the title.',
                'images' => 'Position with a URL hash: ![alt](/images/x.webp#right), plus #left, #small, #full. A markdown title becomes a printed caption: ![alt](/images/x.webp "Three weeks in"). Upload the file first - see the `images` block below - and use the URL that comes back. Every picture on a post - the cover, the ones in the prose, the galleries - opens in one full-screen viewer the reader can page through, so they do not need to be grouped to be browsable.',
                'tryable' => 'Add {.tryable} after an image to offer a link to CarpDraw. Images never preselect or upload a reference.',
                'directives' => [
                    ':::gallery' => 'A row of captioned images. One markdown image per line, then :::',
                    ':::steps "How to ..."' => 'A numbered procedure - "### Step name" then its text, then :::. Emits HowTo schema. Only the first block on a page counts.',
                    ':::brief' => 'A concise project brief. One line, then :::',
                    ':::try-on' => 'An inline call to action linking to CarpDraw. Close with :::.',
                    ':::upload:::' => 'A link to the CarpDraw drawing workspace entry point.',
                    ':::youtube <video-id>' => 'A click-to-load video, no player until asked.',
                ],
                'tables' => 'Standard GFM pipe tables render as-is, and carry most of the comparison content on this blog.',
            ],

            'images' => [
                'upload' => 'POST /api/v1/blog/images with a multipart `file`, or JSON carrying base64 bytes in `data`. Either way send `alt`. The response is the URL to use.',
                'alt' => 'Required on upload, and it is what the reader who cannot see the picture gets instead. Write what the picture shows, not what the article is about.',
                'using_it' => 'Put the returned url in a post\'s cover_image, or in the body as ![alt](url). The response also carries a ready-made `markdown` field for the second case.',
                'cover' => 'cover_image takes a URL; cover_image_alt describes it. Both are ordinary post fields, so a cover can be added or replaced in the same call as any other edit.',
                'accepted' => 'Raster images only - jpeg, png, gif, webp, avif - up to '.intdiv(ImageIngestor::MAX_BYTES, 1024 * 1024).'MB and '.StoreBlogImageRequest::MAX_DIMENSION.'px a side. SVG is refused because it can carry script. Everything is re-encoded to webp on the way in.',
                'idempotent' => 'The stored filename is a hash of the bytes, so uploading the same picture twice returns the same URL and does not make a second copy.',
                'limits' => 'Nothing here fetches an image for you: no remote URL to download, no path on the server to read. Send the bytes.',
            ],

            'endpoints' => [
                'GET /api/v1/blog/context' => 'This document.',
                'POST /api/v1/blog/images' => 'Upload a picture. 201 with its url, alt and dimensions. See the images block above.',
                'GET /api/v1/blog/posts' => 'List. Filters: status=published|scheduled|draft|killed; keyword=<exact phrase> matches target_keyword case-insensitively or an entry in secondary_keywords; search matches title, excerpt and body; category=<slug>; per_page (max 100).',
                'POST /api/v1/blog/posts' => 'Create. 201 with the post.',
                'GET /api/v1/blog/posts/{slug}' => 'Read one. Accepts the id too.',
                'GET /api/v1/blog/posts/{slug}/preview' => 'Render a draft, scheduled or published post through the public article template. Returns HTML with X-Robots-Tag: noindex and requires the same bearer token.',
                'PATCH /api/v1/blog/posts/{slug}' => 'Partial update - send only what changes. PUT behaves identically.',
                'DELETE /api/v1/blog/posts/{slug}' => 'Delist: clears published_at so the URL stops serving. The row is never deleted. A killed post remains killed and must be explicitly un-killed before publishing.',
            ],

            'auth' => 'Authorization: Bearer <BLOG_API_TOKEN> on every request.',
        ]);
    }
}
