<?php

namespace App\Models;

use App\Services\TextDiff;
use Database\Factories\PostRevisionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One rewrite of an article: the wording that is going to replace what is
 * live, or - once applied - the wording that did.
 *
 * A revision carries no identity of its own. It has no slug, no publish date
 * and no readership, because those belong to the article and a rewrite is not
 * a new article. See the migration for why that split is what keeps every
 * public query out of this table.
 *
 * THE FIELD LIST LIVES IN ONE PLACE
 *
 * `WORDING` is the single declaration of which columns a revision carries.
 * Seeding a revision from a post, applying one back, snapshotting what it
 * replaced and diffing the two all read it, so a field added to the editor is
 * added here and nowhere else. The alternative - four hand-kept copies of the
 * same list - fails silently: a new column simply stops being versioned, and
 * approving a revision quietly reverts it.
 *
 * @property Carbon|null $dated_at
 * @property Carbon|null $applied_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class PostRevision extends Model
{
    /** @use HasFactory<PostRevisionFactory> */
    use HasFactory, HasUuids;

    /**
     * The columns a revision holds, in the order a reviewer reads them.
     *
     * Deliberately not `Post::$fillable` minus a few: `slug`, `published_at`
     * and `views_count` are absent because they are the article's identity,
     * and `reading_minutes` because it is derived from the body on save.
     *
     * @var array<int, string>
     */
    public const WORDING = [
        'title',
        'excerpt',
        'body',
        'cover_media_id',
        'meta_title',
        'meta_description',
        'audience',
        'cta_text',
        'cta_url',
        'dated_at',
    ];

    protected $fillable = [
        ...self::WORDING,
        'author_ids',
        'category_ids',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dated_at' => 'datetime',
            'applied_at' => 'datetime',
            'author_ids' => 'array',
            'category_ids' => 'array',
            'superseded' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * @param  Builder<PostRevision>  $query
     * @return Builder<PostRevision>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('applied_at');
    }

    /**
     * @param  Builder<PostRevision>  $query
     * @return Builder<PostRevision>
     */
    public function scopeApplied(Builder $query): Builder
    {
        return $query->whereNotNull('applied_at');
    }

    public function isPending(): bool
    {
        return $this->applied_at === null;
    }

    public static function openFor(Post $post): self
    {
        return $post->revisions()->create([
            ...$post->only(self::WORDING),
            'author_ids' => $post->authors->modelKeys(),
            'category_ids' => $post->categories->modelKeys(),
        ]);
    }

    public function apply(?User $by = null): void
    {
        if (! $this->isPending()) {
            return;
        }

        DB::transaction(function () use ($by): void {
            $post = $this->post()->lockForUpdate()->firstOrFail();
            $post->load(['authors', 'categories']);

            $this->forceFill([
                'superseded' => $this->snapshotOf($post),
                'applied_at' => now(),
                'applied_by_id' => $by?->id,
            ])->save();

            $post->fill($this->only(self::WORDING))->save();
            $post->syncAuthors($this->author_ids ?? []);
            $post->categories()->sync($this->category_ids ?? []);
        });
    }

    /**
     * What this revision changes, field by field.
     *
     * Against the live article while pending - that is the question a reviewer
     * is asking - and against the stored snapshot once applied, which is what
     * the article said on the day this wording replaced it.
     *
     * @return array{
     *     body: array<string, mixed>,
     *     fields: array<int, array{key: string, label: string, before: string, after: string}>,
     *     hasChanges: bool
     * }
     */
    public function diff(): array
    {
        $before = $this->isPending()
            ? $this->snapshotOf($this->post)
            : (array) ($this->superseded ?? []);

        $after = $this->snapshotOf($this);

        $fields = [];

        foreach (self::labels() as $key => $label) {
            $was = (string) ($before[$key] ?? '');
            $now = (string) ($after[$key] ?? '');

            if ($was !== $now) {
                $fields[] = ['key' => $key, 'label' => $label, 'before' => $was, 'after' => $now];
            }
        }

        $body = app(TextDiff::class)->sideBySide(
            (string) ($before['body'] ?? ''),
            (string) ($after['body'] ?? ''),
        );

        return [
            'body' => $body,
            'fields' => $fields,
            'hasChanges' => $fields !== [] || ! $body['identical'],
        ];
    }

    /**
     * The wording of a post or a revision, flattened to strings.
     *
     * Everything comes out as a string because both sides of a diff are
     * compared and printed as text: a null cover and an empty one are the same
     * absence to a reviewer, and a date is read, not calculated with.
     *
     * @return array<string, string>
     */
    private function snapshotOf(Post|self $source): array
    {
        $snapshot = [];

        foreach (self::WORDING as $field) {
            $value = $source->{$field};

            $snapshot[$field] = match (true) {
                $value === null => '',
                $value instanceof Carbon => $value->toDateTimeString(),
                default => (string) $value,
            };
        }

        /*
         * Filing and byline are lists, and a reviewer wants to see "Dana, Sam"
         * rather than two uuids - so they are resolved to names here, which
         * also means the snapshot still reads correctly years later if an
         * author is renamed. It is a record of what the byline said.
         */
        $snapshot['authors'] = $this->names(Author::class, $source instanceof Post
            ? $source->authors->modelKeys()
            : ($source->author_ids ?? []));

        $snapshot['categories'] = $this->names(Category::class, $source instanceof Post
            ? $source->categories->modelKeys()
            : ($source->category_ids ?? []));

        return $snapshot;
    }

    /**
     * @param  class-string<Author|Category>  $model
     * @param  array<int, string>  $ids
     */
    private function names(string $model, array $ids): string
    {
        if ($ids === []) {
            return '';
        }

        $names = $model::query()->whereKey($ids)->pluck('name', 'id');

        /* Kept in the editor's order, which for a byline is the printed one. */
        return collect($ids)
            ->map(fn (string $id): ?string => $names[$id] ?? null)
            ->filter()
            ->implode(', ');
    }

    /**
     * The scalar fields a diff reports, and what to call them on screen.
     * `body` is absent on purpose - it gets the side-by-side view instead.
     *
     * @return array<string, string>
     */
    private static function labels(): array
    {
        return [
            'title' => 'Title',
            'excerpt' => 'Excerpt',
            'cover_media_id' => 'Cover image',
            'meta_title' => 'Meta title',
            'meta_description' => 'Meta description',
            'audience' => 'Audience',
            'cta_text' => 'Call to action',
            'cta_url' => 'Call to action URL',
            'dated_at' => 'Dateline',
            'authors' => 'Byline',
            'categories' => 'Categories',
        ];
    }

    public function asPost(): Post
    {
        $post = $this->post->replicate([]);

        /* replicate() drops the key and the timestamps; the preview needs the
         * article's real identity, because its URL and dates are printed. */
        $post->id = $this->post->id;
        $post->exists = true;
        $post->slug = $this->post->slug;
        $post->published_at = $this->post->published_at;
        $post->created_at = $this->post->created_at;
        $post->updated_at = $this->post->updated_at;

        $post->fill($this->only(self::WORDING));
        $post->reading_minutes = Post::estimateReadingMinutes((string) $this->body);

        $post->setRelation('authors', Author::query()->findMany($this->author_ids ?? [])
            ->sortBy(fn (Author $author): int => array_search($author->id, $this->author_ids ?? [], true))
            ->values());

        $post->setRelation('categories', Category::query()->findMany($this->category_ids ?? []));

        return $post;
    }
}
