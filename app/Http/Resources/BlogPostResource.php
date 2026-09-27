<?php

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One article as the write API returns it.
 *
 * Round-trippable on purpose: every field here is either something you can send
 * back to PUT, or something derived that an agent needs in order to decide what
 * to send. So the byline comes back as usernames and the categories as slugs -
 * the same identifiers the payload takes - rather than as the UUIDs underneath.
 *
 * `faq_questions` is the count the caller is really asking about when it asks
 * whether a post can be published. Publishing is refused below the minimum in
 * App\Rules\HasFaqSection, and a number is cheaper to check than re-parsing the
 * body at the other end.
 *
 * @mixin Post
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource;

        return [
            'id' => $post->id,
            'slug' => $post->slug,
            'title' => $post->title,
            'target_keyword' => $post->target_keyword,
            'secondary_keywords' => $post->secondary_keywords,
            'url' => $post->url(),
            'status' => self::status($post),
            'published_at' => $post->published_at?->toIso8601String(),
            'killed_at' => $post->killed_at?->toIso8601String(),
            'killed_reason' => $post->killed_reason,

            'excerpt' => $post->excerpt,
            'body' => $post->body,
            'reading_minutes' => $post->reading_minutes,
            'faq_questions' => count($post->faqItems()),

            'cover_image' => $post->cover?->url,
            'cover_image_url' => $post->coverImageUrl(),
            'cover_image_alt' => $post->cover?->alt,

            'meta_title' => $post->meta_title,
            'meta_description' => $post->meta_description,

            'audience' => $post->audience,
            /* What the article actually renders as, once the category's side is inherited. */
            'resolved_audience' => $post->resolvedAudience(),

            /* The cut the offer beside the article demonstrates, by the same
               catalog key the payload takes. Null is the default offer. */

            'cta_text' => $post->cta_text,
            'cta_url' => $post->cta_url,

            'authors' => $post->authors->map(fn ($author): array => [
                'name' => $author->name,
                'username' => $author->username,
                'url' => $author->authorUrl(),
            ])->all(),

            'categories' => $post->categories->map(fn ($category): array => [
                'slug' => $category->slug,
                'name' => $category->name,
            ])->all(),

            'views_count' => $post->views_count,
            'created_at' => $post->created_at?->toIso8601String(),
            'updated_at' => $post->updated_at?->toIso8601String(),
        ];
    }

    public static function status(Post $post): string
    {
        return match (true) {
            $post->isKilled() => 'killed',
            $post->isPublished() => 'published',
            $post->isScheduled() => 'scheduled',
            default => 'draft',
        };
    }
}
