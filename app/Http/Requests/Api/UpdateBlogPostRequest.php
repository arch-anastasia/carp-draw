<?php

namespace App\Http\Requests\Api;

use App\Models\Post;

class UpdateBlogPostRequest extends BlogPostRequest
{
    protected function existingPost(): ?Post
    {
        $blogPost = $this->route('blogPost');

        return $blogPost instanceof Post ? $blogPost : null;
    }

    protected function prepareForValidation(): void
    {
        $blogPost = $this->existingPost();

        if (! $blogPost instanceof Post) {
            return;
        }

        if (filled($this->input('killed_at')) && ! $this->has('published_at')) {
            $this->merge(['published_at' => null]);
        }

        $this->merge($this->absentFields($blogPost));
    }

    /**
     * The stored value of every field the caller did not send.
     *
     * `has()` rather than `filled()`: an explicit null is a change - it is how
     * a caller clears a CTA or unpublishes - while an absent key is not.
     *
     * @return array<string, mixed>
     */
    private function absentFields(Post $blogPost): array
    {
        $stored = [
            'title' => $blogPost->title,
            'slug' => $blogPost->slug,
            'target_keyword' => $blogPost->target_keyword,
            'secondary_keywords' => $blogPost->secondary_keywords,
            'excerpt' => $blogPost->excerpt,
            'body' => $blogPost->body,
            'cover_image' => $blogPost->cover?->url,
            'cover_image_alt' => $blogPost->cover?->alt,
            'meta_title' => $blogPost->meta_title,
            'meta_description' => $blogPost->meta_description,
            'audience' => $blogPost->audience,
            /* By key, because that is what the payload takes - the column is
               resolved back out of it in BlogPostController::attributes(). */
            'cta_text' => $blogPost->cta_text,
            'cta_url' => $blogPost->cta_url,
            'published_at' => $blogPost->published_at?->toIso8601String(),
            'killed_at' => $blogPost->killed_at?->toIso8601String(),
            'killed_reason' => $blogPost->killed_reason,
            'authors' => $blogPost->authors->pluck('username')->all(),
            'categories' => $blogPost->categories->pluck('slug')->all(),
        ];

        return collect($stored)
            ->reject(fn (mixed $value, string $field): bool => $this->has($field))
            ->all();
    }
}
