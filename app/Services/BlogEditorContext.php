<?php

namespace App\Services;

use App\Models\Author;
use App\Models\Category;
use App\Models\Post;
use App\Rules\HasFaqSection;

class BlogEditorContext
{
    public function __construct(private readonly BlogContent $content) {}

    /**
     * The payload the page is bootstrapped with: the article, the lists, the
     * endpoints and the session's CSRF token.
     *
     * The token is here because a marketing page carries no `csrf-token` meta
     * tag and, under `script-src 'self'`, no inline script that could hold one.
     *
     * @return array<string, mixed>
     */
    public function bootstrap(Post $post): array
    {
        return [
            'post' => $this->post($post),
            'options' => $this->options(),
            'endpoints' => [
                'save' => route('admin.posts.inline.update', $post->id),
                'image' => route('admin.blog-images.store'),
                'destroy' => route('admin.posts.destroy', $post->id),
                'publish' => route('admin.posts.publish', $post->id),
                'admin' => route('admin.posts.edit', $post->id),
                'index' => route('admin.posts.index'),
            ],
            'limits' => [
                'metaTitle' => 255,
                'metaDescription' => 300,
                'excerpt' => 500,
                'faqQuestions' => HasFaqSection::MINIMUM_QUESTIONS,
            ],
            'revision' => $this->pendingRevision($post),
            /*
             * The clock the editor's two dates are written in. They are printed
             * and parsed in the app's timezone rather than the browser's - a
             * dateline is an editorial fact about the site, not about where the
             * editor was standing - and a `datetime-local` input cannot say so
             * for itself, so the panel says it in words. See buildPublishing().
             */
            'timezone' => config('app.timezone'),
            'csrf' => csrf_token(),
        ];
    }

    /**
     * The rewrite waiting on this article, if one is.
     *
     * The toolbar carries these because it is the only editorial furniture on
     * the page now: an admin standing on the live article has to be able to
     * see that a rewrite exists, read it, compare it and approve it, or they
     * start a second rewrite on top of the first.
     *
     * @return array{id: string, note: string, previewUrl: string, diffUrl: string, editUrl: string, applyUrl: string}|null
     */
    private function pendingRevision(Post $post): ?array
    {
        $revision = $post->pendingRevision();

        if ($revision === null) {
            return null;
        }

        return [
            'id' => $revision->id,
            'note' => (string) ($revision->note ?: 'A new version of this article is being written.'),
            'previewUrl' => $post->url().'?v=draft',
            'diffUrl' => route('admin.posts.revisions.diff', [$post->id, $revision->id]),
            'editUrl' => route('admin.posts.revisions.edit', [$post->id, $revision->id]),
            'applyUrl' => route('admin.posts.revisions.apply', [$post->id, $revision->id]),
        ];
    }

    /**
     * One article, as the editor holds it.
     *
     * The body is sent as its own two halves - the prose the page renders and
     * the questions the accordion renders - because that is how the editor
     * shows them and how it sends them back. See
     * App\Http\Requests\Admin\UpdatePostInlineRequest.
     *
     * @return array<string, mixed>
     */
    public function post(Post $post): array
    {
        $post->loadMissing(['authors', 'categories', 'cover']);

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'url' => $post->url(),
            'status' => $this->status($post),
            'excerpt' => $post->excerpt,
            'targetKeyword' => $post->target_keyword,
            'secondaryKeywords' => (array) ($post->secondary_keywords ?? []),
            'metaTitle' => $post->meta_title,
            'metaDescription' => $post->meta_description,
            'audience' => $post->audience,
            'resolvedAudience' => $post->resolvedAudience(),
            'ctaText' => $post->cta_text,
            'ctaUrl' => $post->cta_url,
            /* The format datetime-local wants, in the app's own timezone. */
            'publishedAt' => $post->published_at?->format('Y-m-d\TH:i'),
            /* Named clock, for the same reason the panel names it: see bootstrap(). */
            'publishedAtLabel' => $post->published_at?->format('j M Y, H:i T'),
            /*
             * The dateline, and what publishing would do with it. An article
             * dated ahead is scheduled by the same click that puts a
             * today-dated one live, so the button has to say which - see
             * Post::publishableAt() and the admin desk's publish().
             */
            'datedAt' => $post->dated_at?->format('Y-m-d\TH:i'),
            'publishesAt' => $post->publishableAt()->format('j M Y'),
            'publishSchedules' => $post->publishableAt()->isFuture(),
            'coverUrl' => $post->coverImageUrl(),
            'coverPath' => $post->cover?->url,
            'coverAlt' => $post->cover?->alt,
            'categories' => $post->categories->pluck('id')->all(),
            'authors' => $post->authors->pluck('id')->all(),
            'faq' => $post->faqItems(),
            'readingMinutes' => $post->reading_minutes,
            'viewsCount' => $post->views_count,
            'updatedAt' => $post->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The lists an article is filed against.
     *
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => Category::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Category $category): array => [
                    'value' => $category->id,
                    'label' => $category->name,
                ])
                ->all(),

            'authors' => Author::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Author $author): array => [
                    'value' => $author->id,
                    'label' => $author->name,
                ])
                ->all(),

            'audiences' => [],
        ];
    }

    private function status(Post $post): string
    {
        return match (true) {
            $post->isKilled() => 'killed',
            $post->isPublished() => 'published',
            $post->isScheduled() => 'scheduled',
            default => 'draft',
        };
    }

    /**
     * The article's outline, rebuilt from whatever body was just saved, so the
     * contents rail beside the prose can follow a renamed heading without a
     * reload.
     *
     * @return array<int, array{id: string, title: string, children: array<int, array{id: string, title: string}>}>
     */
    public function outline(Post $post): array
    {
        return $this->content->outline($post->renderedBody());
    }
}
