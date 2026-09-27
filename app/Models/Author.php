<?php

namespace App\Models;

use Database\Factories\AuthorFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $published_posts_count
 * @property array<int, mixed>|null $links
 */
class Author extends Model
{
    /** @use HasFactory<AuthorFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'username',
        'role',
        'bio',
        'avatar_media_id',
        'website_url',
        'links',
        'profile_is_published',
        'user_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'links' => 'array',
            'profile_is_published' => 'boolean',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function avatar(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<Post, $this> */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class)->withPivot('sort_order');
    }

    /**
     * @param  Builder<Author>  $query
     * @return Builder<Author>
     */
    public function scopePublishedProfiles(Builder $query): Builder
    {
        return $query->where('profile_is_published', true)->with('avatar');
    }

    public function authorUrl(): string
    {
        return route('marketing.blog.author', $this->username);
    }

    public function roleLine(): ?string
    {
        return $this->role ?: null;
    }

    /** @return array<int, array{label: string, url: string}> */
    public function profileLinks(): array
    {
        $links = [];

        if ($this->website_url) {
            $links[] = ['label' => parse_url($this->website_url, PHP_URL_HOST) ?: 'Website', 'url' => $this->website_url];
        }

        foreach ((array) ($this->links ?? []) as $link) {
            $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : '';

            if ($url === '' || ! str_starts_with($url, 'http')) {
                continue;
            }

            $links[] = [
                'label' => trim((string) ($link['label'] ?? '')) ?: (parse_url($url, PHP_URL_HOST) ?: $url),
                'url' => $url,
            ];
        }

        return $links;
    }

    /** @return array<int, array{label: string, url: string}> */
    public function profileLinksForForm(): array
    {
        return collect((array) ($this->links ?? []))
            ->filter(fn ($link): bool => is_array($link))
            ->map(fn (array $link): array => [
                'label' => (string) ($link['label'] ?? ''),
                'url' => (string) ($link['url'] ?? ''),
            ])->values()->all();
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];
        $letters = collect($words)->filter()->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $letters !== '' ? $letters : '?';
    }

    public function avatarUrl(): ?string
    {
        $path = $this->avatar?->url;

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
}
