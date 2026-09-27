<?php

namespace App\Models;

use App\Jobs\SubmitPostToIndexNow;
use App\Models\Concerns\HasBlogSlug;
use App\Services\BlogContent;
use App\Services\HouseAuthor;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;

/**
 * One article.
 *
 * The body is stored as GitHub-flavored markdown and rendered on read rather
 * than at save time: the renderer is code, and code changes. Storing the HTML
 * would mean every past post keeps whatever the renderer did on the day it was
 * written, so a fix to image layout or code blocks would only ever reach new
 * articles.
 *
 * Raw HTML in a body is passed through deliberately. Bodies come from admins
 * and from the token-guarded ingest API, never from a visitor.
 *
 * The dates are declared rather than left to be inferred from casts():
 * static analysis could not see through the method, so every `->format()` on
 * one of them was an ignored error in the baseline. Declaring them is cheaper
 * than suppressing them, and the two dates that carry the most meaning here -
 * `published_at` and `dated_at` - are exactly the ones worth checking.
 *
 * @property Carbon|null $published_at
 * @property Carbon|null $killed_at
 * @property Carbon|null $dated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasBlogSlug, HasFactory, HasUuids;

    protected $fillable = [
        'title',
        'slug',
        'target_keyword',
        'secondary_keywords',
        'excerpt',
        'body',
        'cover_media_id',
        'meta_title',
        'meta_description',
        'audience',
        'cta_text',
        'cta_url',
        'reading_minutes',
        'views_count',
        'published_at',
        'killed_at',
        'killed_reason',
        'dated_at',
    ];

    private ?string $renderedHtml = null;

    private ?string $editableHtml = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'killed_at' => 'datetime',
            'dated_at' => 'datetime',
            'secondary_keywords' => 'array',
            'reading_minutes' => 'integer',
            'views_count' => 'integer',
        ];
    }

    protected function publishedAt(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => $this->inSiteTime($value));
    }

    protected function datedAt(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => $this->inSiteTime($value));
    }

    protected function killedAt(): Attribute
    {
        return Attribute::set(fn (mixed $value): ?string => $this->inSiteTime($value));
    }

    private function inSiteTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
    }

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (empty($post->slug)) {
                $post->slug = static::generateUniqueSlug((string) $post->title);
            }
        });

        /*
         * Reading time is derived, never typed. An editor who changes three
         * paragraphs should not have to remember to change a number, and a
         * stale "3 min read" on a fifteen-minute article is a small lie the
         * reader notices at paragraph four.
         */
        static::saving(function (self $post): void {
            if ($post->isKilled()) {
                $post->published_at = null;
            }

            if ($post->isDirty('body')) {
                $post->renderedHtml = null;
                $post->editableHtml = null;
            }

            if ($post->isDirty('body') && ! empty($post->body)) {
                $post->reading_minutes = static::estimateReadingMinutes((string) $post->body);
            }
        });

        /*
         * IndexNow is a publication signal, not an edit signal. Comparing the
         * date on each side of this save keeps routine live-post edits quiet,
         * while a draft or scheduled row moved into the past announces the
         * one moment its public URL starts answering.
         */
        static::saved(function (self $post): void {
            if ($post->wasChanged(['killed_at', 'published_at'])) {
                Cache::forget('discover.feed.v3');
            }

            if ($post->isKilled() || ! config('blog.indexnow.enabled') || ! $post->wasChanged('published_at')) {
                return;
            }

            $previouslyPublished = $post->getOriginal('published_at')?->isPast() ?? false;

            if (! $previouslyPublished && $post->isPublished()) {
                SubmitPostToIndexNow::dispatch($post->url());
            }
        });
    }

    public static function estimateReadingMinutes(string $body, int $wordsPerMinute = 220): int
    {
        $words = max(1, str_word_count(strip_tags($body)));

        return max(1, (int) ceil($words / $wordsPerMinute));
    }

    /**
     * The byline, in the order an editor put it in.
     *
     * A guide is often a barber's knowledge written up by an editor, so a
     * byline is a list. `position` is the printed order, not a ranking: the
     * first name is simply the first name.
     *
     * @return BelongsToMany<Author, $this>
     */
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class, 'author_post')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->orderBy('name')
            /* A byline draws the picture, and the picture is a media row now. */
            ->with('avatar');
    }

    /**
     * Who to print. Never empty while a house account exists: an article with
     * nobody attached is credited to the house byline rather than published
     * unsigned. See App\Services\HouseAuthor.
     *
     * @return Collection<int, Author>
     */
    public function byline(): SupportCollection
    {
        $authors = $this->relationLoaded('authors') ? $this->authors : $this->authors()->get();

        if ($authors->isNotEmpty()) {
            return collect($authors->all());
        }

        return collect(array_filter([app(HouseAuthor::class)->find()]));
    }

    /**
     * Replace the byline, keeping the given order and refusing to leave the
     * article unsigned - an empty list falls back to the house account.
     *
     * @param  array<int, string>  $authorIds
     */
    public function syncAuthors(array $authorIds): void
    {
        $ids = array_values(array_unique(array_filter($authorIds)));

        if ($ids === []) {
            $house = app(HouseAuthor::class)->resolve();
            $ids = [$house->id];
        }

        $this->authors()->sync(collect($ids)
            ->mapWithKeys(fn (string $id, int $index): array => [$id => ['sort_order' => $index]])
            ->all());

        $this->unsetRelation('authors');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class)->withTimestamps();
    }

    /**
     * Rewrites of this article - the one waiting for approval, and every one
     * that has already landed. Newest first, which is the order a history is
     * read in.
     *
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('created_at');
    }

    public function pendingRevision(): ?PostRevision
    {
        return $this->revisions()->pending()->first();
    }

    /**
     * The canonical "visible to the public" filter. A draft has no date; a
     * scheduled post has one in the future.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereNull('killed_at')->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeKilled(Builder $query): Builder
    {
        return $query->whereNotNull('killed_at');
    }

    public function isKilled(): bool
    {
        return $this->killed_at !== null;
    }

    /**
     * The journal as cards: the latest published articles, in the one shape
     * every surface that shows them wants.
     *
     * One source for the app's Discover tab and the web dashboard, so the two
     * cannot drift into showing different articles as "the latest". Cached
     * whole and sliced by the caller, because the feed only changes when an
     * article ships. The base holds 30 - a whole tab of reading for the app's
     * Discover - and the key versions the shape: v3 added audience, so a
     * deployed v2 entry can't serve cards the app's filter can't read.
     *
     * @return array<int, array{title: string, excerpt: string|null, image: string|null, url: string, publishedAt: string|null, readingMinutes: int|null, audience: string|null}>
     */
    public static function journalFeed(int $limit = 10): array
    {
        $posts = Cache::remember('discover.feed.v3', now()->addMinutes(10), fn () => self::query()
            ->published()
            ->with(['cover', 'categories'])
            ->orderByDesc('published_at')
            ->limit(30)
            ->get()
            ->map(fn (self $post) => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'image' => $post->coverImageUrl(),
                'url' => $post->url(),
                'publishedAt' => $post->published_at?->toIso8601String(),
                'readingMinutes' => $post->reading_minutes,
                'audience' => $post->resolvedAudience(),
            ])
            ->values()
            ->all());

        return array_slice($posts, 0, $limit);
    }

    /**
     * Work awaiting publication: drafts and scheduled posts, excluding killed posts.
     *
     * Not `whereNot(published())`: a bare `orWhere` in a scope leaks out of its
     * own parentheses and disjoins whatever the caller had already narrowed to,
     * so this one nests, and callers can chain it like any other.
     *
     * @param  Builder<Post>  $query
     * @return Builder<Post>
     */
    public function scopeUnpublished(Builder $query): Builder
    {
        return $query->whereNull('killed_at')->where(fn (Builder $query) => $query
            ->whereNull('published_at')
            ->orWhere('published_at', '>', now()));
    }

    public function isPublished(): bool
    {
        return ! $this->isKilled() && $this->published_at !== null && $this->published_at->isPast();
    }

    public function isScheduled(): bool
    {
        return ! $this->isKilled() && $this->published_at !== null && $this->published_at->isFuture();
    }

    public function isDraft(): bool
    {
        return ! $this->isKilled() && $this->published_at === null;
    }

    public function displayedUpdatedAt(): ?Carbon
    {
        return $this->dated_at ?? $this->updated_at;
    }

    public function publishableAt(): Carbon
    {
        return $this->dated_at ?? now();
    }

    public function url(): string
    {
        return route('marketing.blog.entry', $this->slug);
    }

    public function coverImageUrl(): ?string
    {
        $path = $this->cover?->url;

        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        if (str_starts_with($path, '/')) {
            return rtrim(config('app.url'), '/').$path;
        }

        return asset('storage/'.$path);
    }

    public function hasCta(): bool
    {
        return ! empty($this->cta_text) && ! empty($this->cta_url);
    }

    public function primaryCategory(): ?Category
    {
        return $this->categories->first();
    }

    public function resolvedAudience(): ?string
    {
        return $this->audience
            ?? $this->primaryCategory()?->audience;
    }

    public function renderedBody(): string
    {
        /*
         * Held for the life of the instance: the template renders the body and
         * reads its outline back out of the same HTML, and rendering twice per
         * request would mean measuring every local image twice as well.
         */
        $content = app(BlogContent::class);

        /*
         * The FAQ is rendered separately, as an accordion and as FAQPage
         * schema, both from faqItems() - so it comes out of the prose here
         * rather than printing twice and appearing in the outline.
         */
        return $this->renderedHtml ??= $content->render(
            $content->withoutFaqSection((string) $this->body),
            $this->resolvedAudience(),
            null,
        );
    }

    public function editableBody(): string
    {
        $content = app(BlogContent::class);

        return $this->editableHtml ??= $content->renderEditable(
            $content->withoutFaqSection((string) $this->body),
            $this->resolvedAudience(),
            null,
        );
    }

    /**
     * The article's h2/h3 outline as a two-level tree, for the sidebar
     * contents. Empty for a post with nothing to navigate.
     *
     * @return array<int, array{id: string, title: string, children: array<int, array{id: string, title: string}>}>
     */
    public function outline(): array
    {
        return app(BlogContent::class)->outline($this->renderedBody());
    }

    /** @return array<int, array{id: string, title: string}> */
    public function styleEntries(): array
    {
        return app(BlogContent::class)->styleEntries((string) $this->body);
    }

    /** @return array<int, array{question: string, answer: string}> */
    public function faqItems(): array
    {
        return app(BlogContent::class)->faqItems((string) $this->body);
    }

    /**
     * The procedure this guide teaches, for the HowTo schema. Null for the
     * guides that explain rather than instruct.
     *
     * @return array{name: string, steps: array<int, array{name: string, text: string}>}|null
     */
    public function howTo(): ?array
    {
        return app(BlogContent::class)->howTo((string) $this->body);
    }

    /** @return array<int, array{url: string, contentUrl: string, alt: string, caption: string, tryable: bool, width: int|null, height: int|null}> */
    public function galleryImages(): array
    {
        return app(BlogContent::class)->galleryImages((string) $this->body);
    }
}
